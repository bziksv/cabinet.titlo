<?php

namespace App\Services\Database;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class DatabaseInventoryService
{
    private const CACHE_KEY = 'cabinet.database-inventory.snapshot';

    /**
     * @return array<string, mixed>
     */
    public function getSnapshot(bool $fresh = false): array
    {
        $ttl = (int) config('cabinet-database-admin.snapshot_cache_seconds', 3600);

        if ($fresh) {
            Cache::forget(self::CACHE_KEY);
        }

        $snapshot = Cache::remember(self::CACHE_KEY, $ttl, function () {
            return $this->buildSnapshot(false);
        });

        // Статусы OPTIMIZE не кэшируем вместе со снимком — иначе после F5 «Идёт…» пропадает
        return $this->withLiveOptimizeStatus($snapshot);
    }

    /**
     * @param array<string, mixed> $snapshot
     * @return array<string, mixed>
     */
    private function withLiveOptimizeStatus(array $snapshot): array
    {
        if (! isset($snapshot['tables']) || ! is_array($snapshot['tables'])) {
            return $snapshot;
        }

        try {
            $latest = app(TableOptimizeService::class)->latestRunsByTable();
        } catch (\Throwable $e) {
            return $snapshot;
        }

        foreach ($snapshot['tables'] as $i => $table) {
            $name = (string) ($table['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $snapshot['tables'][$i]['optimize'] = $latest[$name] ?? null;
        }

        return $snapshot;
    }

    /**
     * @return array<string, mixed>
     */
    public function refreshMetadata(): array
    {
        Cache::forget(self::CACHE_KEY);
        $snapshot = $this->buildSnapshot(false);
        Cache::put(self::CACHE_KEY, $snapshot, (int) config('cabinet-database-admin.snapshot_cache_seconds', 3600));

        return $snapshot;
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * Последовательно сканирует даты пакетами (не все таблицы за один HTTP-запрос).
     *
     * @return array{snapshot: array<string, mixed>, batch: int, remaining: int, skipped_large: int, skipped_list: int}
     */
    public function probeDatesBatch(): array
    {
        $snapshot = $this->getSnapshot(false);
        $batchSize = max(1, (int) config('cabinet-database-admin.date_probe_batch_size', 5));
        $lightAboveMb = (int) config('cabinet-database-admin.date_probe_light_above_mb', 500);
        $lightTables = array_flip(config('cabinet-database-admin.date_probe_light_tables', []));
        $timeout = (int) config('cabinet-database-admin.date_probe_timeout_seconds', 8);

        $processed = 0;
        $lightCount = 0;

        foreach ($snapshot['tables'] as $i => $table) {
            if ($processed >= $batchSize) {
                break;
            }

            if (empty($table['date_column']) || ! empty($table['date_probed'])) {
                continue;
            }

            $name = (string) $table['name'];
            $col = (string) $table['date_column'];
            $useLight = isset($lightTables[$name]) || ($table['size_mb'] ?? 0) > $lightAboveMb;

            if ($useLight) {
                $result = $this->probeLightByPrimaryKey($name, $col, $timeout);
                $lightCount++;
            } else {
                $result = $this->probeMinMax($name, $col, $timeout);
            }

            $snapshot['tables'][$i]['data_min'] = $result['min'];
            $snapshot['tables'][$i]['data_max'] = $result['max'];
            $snapshot['tables'][$i]['date_probed'] = $result['ok'];
            $snapshot['tables'][$i]['date_probe_method'] = $useLight ? 'light_pk' : 'minmax';
            unset($snapshot['tables'][$i]['date_error'], $snapshot['tables'][$i]['date_skipped']);
            if (! empty($result['extra_max'])) {
                $snapshot['tables'][$i]['data_max_extra'] = $result['extra_max'];
                $snapshot['tables'][$i]['data_max_extra_column'] = $result['extra_column'];
            }
            if (! $result['ok'] && ! empty($result['error'])) {
                $snapshot['tables'][$i]['date_error'] = $result['error'];
            }
            $processed++;
        }

        $remaining = $this->countDateProbeRemaining($snapshot);

        $snapshot['dates_probed_at'] = now()->toDateTimeString();
        $snapshot['date_probe_progress'] = [
            'remaining' => $remaining,
            'batch_size' => $batchSize,
            'light_above_mb' => $lightAboveMb,
        ];

        Cache::put(self::CACHE_KEY, $snapshot, (int) config('cabinet-database-admin.snapshot_cache_seconds', 3600));

        return [
            'snapshot' => $snapshot,
            'batch' => $processed,
            'remaining' => $remaining,
            'light_count' => $lightCount,
        ];
    }

    /**
     * @param array<string, mixed> $snapshot
     */
    private function countDateProbeRemaining(array $snapshot): int
    {
        $remaining = 0;
        foreach ($snapshot['tables'] as $table) {
            if (empty($table['date_column']) || ! empty($table['date_probed'])) {
                continue;
            }
            $remaining++;
        }

        return $remaining;
    }

    public function resetDateProbeFlags(): array
    {
        $snapshot = $this->getSnapshot(false);
        foreach ($snapshot['tables'] as $i => $table) {
            $snapshot['tables'][$i]['data_min'] = null;
            $snapshot['tables'][$i]['data_max'] = null;
            $snapshot['tables'][$i]['date_probed'] = false;
            unset(
                $snapshot['tables'][$i]['date_error'],
                $snapshot['tables'][$i]['date_skipped'],
                $snapshot['tables'][$i]['date_probe_method'],
                $snapshot['tables'][$i]['data_max_extra'],
                $snapshot['tables'][$i]['data_max_extra_column']
            );
        }
        unset($snapshot['dates_probed_at'], $snapshot['date_probe_progress']);
        Cache::put(self::CACHE_KEY, $snapshot, (int) config('cabinet-database-admin.snapshot_cache_seconds', 3600));

        return $snapshot;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSnapshot(bool $withDates): array
    {
        $schema = (string) config('database.connections.mysql.database');
        $modelMap = EloquentTableMap::build();
        $foreignKeys = $this->loadForeignKeys($schema);
        $dateColumns = $this->loadDateColumns($schema);

        $rows = DB::select(
            'SELECT TABLE_NAME AS name, ENGINE AS engine, TABLE_ROWS AS rows_estimate,
                    DATA_LENGTH AS data_length, INDEX_LENGTH AS index_length,
                    DATA_FREE AS data_free,
                    CREATE_TIME AS create_time, UPDATE_TIME AS update_time,
                    TABLE_COMMENT AS table_comment
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = ?
             ORDER BY (DATA_LENGTH + INDEX_LENGTH) DESC',
            [$schema]
        );

        $tableNames = array_map(static function ($row) {
            return (string) $row->name;
        }, $rows);
        $codeRefsApp = $this->buildCodeReferenceCounts($tableNames, false);
        $codeRefsAll = $this->buildCodeReferenceCounts($tableNames, true);
        $optimizeLatest = app(TableOptimizeService::class)->latestRunsByTable();

        $tables = [];
        $totalBytes = 0;
        $orphanCount = 0;
        $largeCount = 0;
        $largeThreshold = (int) config('cabinet-database-admin.large_table_mb', 100) * 1024 * 1024;

        foreach ($rows as $row) {
            $name = (string) $row->name;
            $bytes = (int) $row->data_length + (int) $row->index_length;
            $dataFreeBytes = (int) ($row->data_free ?? 0);
            $totalBytes += $bytes;
            if ($bytes >= $largeThreshold) {
                $largeCount++;
            }

            $models = $modelMap[$name] ?? [];
            $refsApp = (int) ($codeRefsApp[$name] ?? 0);
            $refsAll = (int) ($codeRefsAll[$name] ?? 0);
            $system = TableModuleResolver::isSystemTable($name);
            $modules = TableModuleResolver::resolve($name);
            $status = $this->classifyTable($name, $models, $refsApp, $system, $modules);

            if ($status === 'orphan') {
                $orphanCount++;
            }

            $orphanNote = config('cabinet-database-admin.orphan_notes.' . $name);
            $sizeMb = round($bytes / 1024 / 1024, 1);
            $dataFreeMb = round($dataFreeBytes / 1024 / 1024, 1);
            $tables[] = [
                'name' => $name,
                'size_mb' => $sizeMb,
                'size_bytes' => $bytes,
                'data_free_mb' => $dataFreeMb,
                'data_free_bytes' => $dataFreeBytes,
                'optimize' => $optimizeLatest[$name] ?? null,
                'rows_estimate' => (int) $row->rows_estimate,
                'engine' => (string) ($row->engine ?? ''),
                'schema_created' => $row->create_time ? (string) $row->create_time : null,
                'schema_updated' => $row->update_time ? (string) $row->update_time : null,
                'comment' => trim((string) ($row->table_comment ?? '')),
                'models' => $models,
                'code_refs' => $refsApp,
                'code_refs_migrations' => max(0, $refsAll - $refsApp),
                'modules' => $modules,
                'system' => $system ? TableModuleResolver::systemMeta($name) : null,
                'status' => $status,
                'orphan_note' => is_string($orphanNote) ? $orphanNote : null,
                'date_column' => $dateColumns[$name] ?? null,
                'data_min' => null,
                'data_max' => null,
                'date_probed' => false,
                'fk_out' => $foreignKeys['out'][$name] ?? [],
                'fk_in' => $foreignKeys['in'][$name] ?? [],
            ];
        }

        $snapshot = [
            'generated_at' => now()->toDateTimeString(),
            'database' => $schema,
            'host' => (string) config('database.connections.mysql.host'),
            'summary' => [
                'tables' => count($tables),
                'total_gb' => round($totalBytes / 1024 / 1024 / 1024, 2),
                'total_mb' => round($totalBytes / 1024 / 1024, 1),
                'orphan' => $orphanCount,
                'large' => $largeCount,
                'models_mapped' => count($modelMap),
            ],
            'tables' => $tables,
            'dates_probed_at' => null,
        ];

        return $snapshot;
    }

    /**
     * Быстрая оценка диапазона: первая/последняя строка по PRIMARY KEY (не полный MIN/MAX).
     *
     * @return array{ok:bool,min:?string,max:?string,error:?string,extra_max?:string,extra_column?:string}
     */
    private function probeLightByPrimaryKey(string $table, string $column, int $timeoutSeconds): array
    {
        $safeTable = str_replace('`', '', $table);
        $safeCol = str_replace('`', '', $column);

        try {
            DB::statement('SET SESSION max_statement_time = ' . max(1, $timeoutSeconds));
        } catch (\Throwable $e) {
        }

        try {
            $minRow = DB::selectOne(
                "SELECT `{$safeCol}` AS v FROM `{$safeTable}` ORDER BY `id` ASC LIMIT 1"
            );
            $maxRow = DB::selectOne(
                "SELECT `{$safeCol}` AS v FROM `{$safeTable}` ORDER BY `id` DESC LIMIT 1"
            );

            $extraColumn = config('cabinet-database-admin.date_probe_light_extra_column.' . $safeTable);
            $extraMax = null;
            if (is_string($extraColumn) && $extraColumn !== '' && $extraColumn !== $safeCol) {
                $safeExtra = str_replace('`', '', $extraColumn);
                $extraRow = DB::selectOne(
                    "SELECT `{$safeExtra}` AS v FROM `{$safeTable}` ORDER BY `id` DESC LIMIT 1"
                );
                $extraMax = $extraRow && $extraRow->v ? (string) $extraRow->v : null;
            }

            DB::statement('SET SESSION max_statement_time = 0');

            $out = [
                'ok' => true,
                'min' => $minRow && $minRow->v ? (string) $minRow->v : null,
                'max' => $maxRow && $maxRow->v ? (string) $maxRow->v : null,
                'error' => null,
            ];
            if ($extraMax !== null) {
                $out['extra_max'] = $extraMax;
                $out['extra_column'] = $extraColumn;
            }

            return $out;
        } catch (\Throwable $e) {
            try {
                DB::statement('SET SESSION max_statement_time = 0');
            } catch (\Throwable $ignored) {
            }

            return [
                'ok' => false,
                'min' => null,
                'max' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array{ok:bool,min:?string,max:?string,error:?string}
     */
    private function probeMinMax(string $table, string $column, int $timeoutSeconds): array
    {
        $safeTable = str_replace('`', '', $table);
        $safeCol = str_replace('`', '', $column);

        try {
            DB::statement('SET SESSION max_statement_time = ' . max(1, $timeoutSeconds));
        } catch (\Throwable $e) {
            // MariaDB / старый MySQL — без лимита
        }

        try {
            $row = DB::selectOne(
                "SELECT MIN(`{$safeCol}`) AS mn, MAX(`{$safeCol}`) AS mx FROM `{$safeTable}`"
            );
            DB::statement('SET SESSION max_statement_time = 0');

            return [
                'ok' => true,
                'min' => $row && $row->mn ? (string) $row->mn : null,
                'max' => $row && $row->mx ? (string) $row->mx : null,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            try {
                DB::statement('SET SESSION max_statement_time = 0');
            } catch (\Throwable $ignored) {
            }

            return [
                'ok' => false,
                'min' => null,
                'max' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * @param list<string> $models
     * @param list<array{title:string,uri:string}> $modules
     */
    private function classifyTable(string $table, array $models, int $codeRefs, bool $system, array $modules): string
    {
        if ($system) {
            return 'system';
        }
        if ($models !== [] || $codeRefs > 0) {
            return 'linked';
        }
        if ($modules !== [] && ($modules[0]['title'] ?? '') !== '—') {
            return 'linked';
        }

        return 'orphan';
    }

    /**
     * @param list<string> $tableNames
     * @return array<string, int> table => count of files mentioning table name
     */
    private function buildCodeReferenceCounts(array $tableNames, bool $includeMigrations): array
    {
        $counts = array_fill_keys($tableNames, 0);
        $dirs = [
            app_path(),
            base_path('routes'),
            resource_path('views'),
        ];
        if ($includeMigrations) {
            $dirs[] = base_path('database/migrations');
        }

        foreach ($dirs as $dir) {
            if (! is_dir($dir)) {
                continue;
            }
            foreach (File::allFiles($dir) as $file) {
                $path = $file->getPathname();
                if (! preg_match('/\.(php|blade\.php)$/', $path)) {
                    continue;
                }
                $content = @file_get_contents($path);
                if ($content === false) {
                    continue;
                }
                foreach ($tableNames as $table) {
                    if (strpos($content, $table) !== false) {
                        $counts[$table]++;
                    }
                }
            }
        }

        return $counts;
    }

    /**
     * @return array{out: array<string, list<array<string,string>>>, in: array<string, list<array<string,string>>>}
     */
    private function loadForeignKeys(string $schema): array
    {
        $out = [];
        $in = [];

        $rows = DB::select(
            'SELECT TABLE_NAME AS tbl, COLUMN_NAME AS col,
                    REFERENCED_TABLE_NAME AS ref_tbl, REFERENCED_COLUMN_NAME AS ref_col
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = ? AND REFERENCED_TABLE_NAME IS NOT NULL',
            [$schema]
        );

        foreach ($rows as $row) {
            $entry = [
                'column' => (string) $row->col,
                'ref_table' => (string) $row->ref_tbl,
                'ref_column' => (string) $row->ref_col,
            ];
            $tbl = (string) $row->tbl;
            $refTbl = (string) $row->ref_tbl;
            $out[$tbl][] = $entry;
            $in[$refTbl][] = [
                'column' => (string) $row->ref_col,
                'ref_table' => $tbl,
                'ref_column' => (string) $row->col,
            ];
        }

        return ['out' => $out, 'in' => $in];
    }

    /**
     * @return array<string, string> table => preferred date column
     */
    private function loadDateColumns(string $schema): array
    {
        $preferred = ['created_at', 'updated_at', 'date', 'checked_at', 'published_at', 'started_at'];
        $byTable = [];

        $rows = DB::select(
            "SELECT TABLE_NAME AS tbl, COLUMN_NAME AS col, DATA_TYPE AS dtype
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ?
               AND (COLUMN_NAME IN ('created_at','updated_at','date','checked_at','published_at','started_at')
                    OR COLUMN_NAME LIKE '%\_at' OR DATA_TYPE IN ('date','datetime','timestamp'))
             ORDER BY TABLE_NAME, ORDINAL_POSITION",
            [$schema]
        );

        foreach ($rows as $row) {
            $tbl = (string) $row->tbl;
            $col = (string) $row->col;
            if (! isset($byTable[$tbl])) {
                $byTable[$tbl] = [];
            }
            $byTable[$tbl][] = $col;
        }

        $result = [];
        foreach ($byTable as $tbl => $cols) {
            foreach ($preferred as $want) {
                if (in_array($want, $cols, true)) {
                    $result[$tbl] = $want;
                    break;
                }
            }
            if (! isset($result[$tbl]) && $cols !== []) {
                $result[$tbl] = $cols[0];
            }
        }

        return $result;
    }

    /**
     * TRUNCATE + OPTIMIZE для таблиц из clearable_tables (только failed_jobs по умолчанию).
     *
     * @return array{table: string, deleted: int}
     */
    public function clearTable(string $table): array
    {
        $table = strtolower(trim($table));
        if (! preg_match('/^[a-z][a-z0-9_]{0,62}$/', $table)) {
            throw new \InvalidArgumentException(__('Database preview invalid table'));
        }

        $allowed = array_flip(config('cabinet-database-admin.clearable_tables', []));
        if (! isset($allowed[$table])) {
            throw new \InvalidArgumentException(__('Database table clear not allowed'));
        }

        if (! $this->tableExists($table)) {
            throw new \InvalidArgumentException(__('Database preview table not found'));
        }

        $deleted = (int) DB::table($table)->count();
        DB::statement('TRUNCATE TABLE `' . $table . '`');

        try {
            DB::statement('OPTIMIZE TABLE `' . $table . '`');
        } catch (\Throwable $e) {
            // OPTIMIZE может быть недоступен — данные уже удалены
        }

        $this->refreshMetadata();

        return [
            'table' => $table,
            'deleted' => $deleted,
        ];
    }

    private function tableExists(string $table): bool
    {
        $schema = (string) DB::connection()->getDatabaseName();

        return (bool) DB::selectOne(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? LIMIT 1',
            [$schema, $table]
        );
    }
}
