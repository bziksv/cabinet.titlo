<?php

namespace App\Services\Database;

use Illuminate\Support\Facades\DB;

/**
 * Последние N строк таблицы для админки /admin/database (только SELECT).
 */
class TableRowPreviewService
{
    /**
     * @return array{table: string, limit: int, order_column: string, columns: list<string>, rows: list<array<string, mixed>>, note: ?string}
     */
    public function preview(string $table, ?int $limit = null): array
    {
        $table = $this->sanitizeTableName($table);
        if (! $this->tableExists($table)) {
            throw new \InvalidArgumentException(__('Database preview table not found'));
        }

        $limit = $limit ?? (int) config('cabinet-database-admin.row_preview_limit', 10);
        $limit = max(1, min(20, $limit));

        if ($table === 'failed_jobs') {
            return $this->previewFailedJobs($limit);
        }

        if ($table === 'search_indices') {
            return $this->previewSearchIndices($limit);
        }

        return $this->previewGeneric($table, $limit);
    }

    public function sanitizeTableName(string $table): string
    {
        $table = strtolower(trim($table));
        if (! preg_match('/^[a-z][a-z0-9_]{0,62}$/', $table)) {
            throw new \InvalidArgumentException(__('Database preview invalid table'));
        }

        return $table;
    }

    public function tableExists(string $table): bool
    {
        $schema = (string) config('database.connections.mysql.database');

        return DB::table('information_schema.tables')
            ->where('table_schema', $schema)
            ->where('table_name', $table)
            ->exists();
    }

    /**
     * @return array{table: string, limit: int, order_column: string, columns: list<string>, rows: list<array<string, mixed>>, note: ?string}
     */
    private function previewFailedJobs(int $limit): array
    {
        $rows = DB::table('failed_jobs')
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'connection', 'queue', 'payload', 'exception', 'failed_at']);

        $out = [];
        foreach ($rows as $row) {
            $parsed = $this->parseFailedJobPayload((string) ($row->payload ?? ''));
            $out[] = [
                'id' => $row->id,
                'failed_at' => (string) $row->failed_at,
                'connection' => $row->connection,
                'queue' => $row->queue,
                'job' => $parsed['job'],
                'job_class' => $parsed['job_class'],
                'error' => $this->summarizeException((string) ($row->exception ?? '')),
                'exception_excerpt' => $this->truncate((string) ($row->exception ?? ''), 600),
            ];
        }

        return [
            'table' => 'failed_jobs',
            'limit' => $limit,
            'order_column' => 'id DESC',
            'columns' => ['id', 'failed_at', 'connection', 'queue', 'job', 'job_class', 'error'],
            'rows' => $out,
            'note' => __('Database preview failed jobs note'),
        ];
    }

    /**
     * @return array{table: string, limit: int, order_column: string, columns: list<string>, rows: list<array<string, mixed>>, note: ?string}
     */
    private function previewSearchIndices(int $limit): array
    {
        $columns = [
            'id', 'source', 'lr', 'url', 'position', 'title', 'snippet', 'query', 'created_at', 'updated_at',
        ];

        $rows = DB::table('search_indices')
            ->orderByDesc('id')
            ->limit($limit)
            ->get($columns);

        $out = [];
        foreach ($rows as $row) {
            $item = [];
            foreach ($columns as $col) {
                $item[$col] = $this->formatCellValue($row->{$col} ?? null);
            }
            $out[] = $item;
        }

        return [
            'table' => 'search_indices',
            'limit' => $limit,
            'order_column' => 'id DESC',
            'columns' => $columns,
            'rows' => $out,
            'note' => __('Database preview search indices note'),
        ];
    }

    /**
     * @return array{table: string, limit: int, order_column: string, columns: list<string>, rows: list<array<string, mixed>>, note: ?string}
     */
    private function previewGeneric(string $table, int $limit): array
    {
        $schema = (string) config('database.connections.mysql.database');
        $meta = DB::table('information_schema.columns')
            ->where('table_schema', $schema)
            ->where('table_name', $table)
            ->orderBy('ordinal_position')
            ->get(['column_name', 'data_type']);

        if ($meta->isEmpty()) {
            throw new \InvalidArgumentException(__('Database preview table not found'));
        }

        $exclude = array_flip(config('cabinet-database-admin.row_preview_exclude_columns.' . $table, []));
        $globalExclude = array_flip(config('cabinet-database-admin.row_preview_exclude_columns_global', [
            'password', 'remember_token', 'payload', 'exception',
        ]));

        $orderCol = $this->resolveOrderColumn($meta->pluck('column_name')->all(), $table);
        $maxChars = (int) config('cabinet-database-admin.row_preview_max_cell_chars', 400);

        $selectParts = [];
        $columns = [];
        foreach ($meta as $col) {
            $name = (string) $col->column_name;
            if (isset($exclude[$name]) || isset($globalExclude[$name])) {
                continue;
            }
            $columns[] = $name;
            $type = strtolower((string) $col->data_type);
            if (in_array($type, ['longtext', 'mediumtext', 'text', 'blob', 'longblob', 'mediumblob'], true)) {
                $selectParts[] = "LEFT(`{$name}`, {$maxChars}) AS `{$name}`";
            } else {
                $selectParts[] = "`{$name}`";
            }
            if (count($columns) >= 12) {
                break;
            }
        }

        if ($columns === []) {
            throw new \RuntimeException(__('Database preview no columns'));
        }

        $sql = 'SELECT ' . implode(', ', $selectParts)
            . ' FROM `' . str_replace('`', '', $table) . '`'
            . ' ORDER BY `' . str_replace('`', '', $orderCol) . '` DESC'
            . ' LIMIT ' . (int) $limit;

        $rawRows = DB::select($sql);
        $rows = [];
        foreach ($rawRows as $row) {
            $item = [];
            foreach ($columns as $col) {
                $val = $row->{$col} ?? null;
                $item[$col] = $this->formatCellValue($val);
            }
            $rows[] = $item;
        }

        $note = null;
        if (count($meta) > count($columns)) {
            $note = __('Database preview columns truncated');
        }

        return [
            'table' => $table,
            'limit' => $limit,
            'order_column' => $orderCol . ' DESC',
            'columns' => $columns,
            'rows' => $rows,
            'note' => $note,
        ];
    }

    /**
     * @param list<string> $columnNames
     */
    private function resolveOrderColumn(array $columnNames, string $table): string
    {
        $byId = array_flip(config('cabinet-database-admin.row_preview_order_by_id_tables', []));
        if (isset($byId[$table]) && in_array('id', $columnNames, true)) {
            return 'id';
        }

        foreach (['failed_at', 'updated_at', 'created_at', 'last_check', 'id'] as $candidate) {
            if (in_array($candidate, $columnNames, true)) {
                return $candidate;
            }
        }

        return $columnNames[0];
    }

    /**
     * @return array{job: string, job_class: string}
     */
    private function parseFailedJobPayload(string $payload): array
    {
        $job = '—';
        $jobClass = '—';

        if ($payload === '') {
            return compact('job', 'jobClass');
        }

        $data = json_decode($payload, true);
        if (! is_array($data)) {
            return ['job' => $this->truncate($payload, 120), 'job_class' => '—'];
        }

        if (! empty($data['displayName'])) {
            $job = (string) $data['displayName'];
        }

        if (! empty($data['data']['commandName'])) {
            $jobClass = (string) $data['data']['commandName'];
        } elseif (! empty($data['job'])) {
            $jobClass = (string) $data['job'];
        }

        return [
            'job' => $this->truncate($job, 200),
            'job_class' => $this->truncate($jobClass, 200),
        ];
    }

    private function summarizeException(string $exception): string
    {
        if ($exception === '') {
            return '—';
        }

        $lines = preg_split('/\r\n|\r|\n/', $exception);
        $first = trim((string) ($lines[0] ?? ''));

        return $this->truncate($first !== '' ? $first : $exception, 300);
    }

    /**
     * @param mixed $val
     */
    private function formatCellValue($val): string
    {
        if ($val === null) {
            return '—';
        }
        if (is_bool($val)) {
            return $val ? '1' : '0';
        }
        if (is_scalar($val)) {
            return $this->truncate((string) $val, (int) config('cabinet-database-admin.row_preview_max_cell_chars', 400));
        }

        return $this->truncate(json_encode($val, JSON_UNESCAPED_UNICODE), 400);
    }

    private function truncate(string $text, int $max): string
    {
        if (mb_strlen($text) <= $max) {
            return $text;
        }

        return mb_substr($text, 0, $max) . '…';
    }
}
