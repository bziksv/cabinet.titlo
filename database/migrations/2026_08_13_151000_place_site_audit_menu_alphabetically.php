<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Site Audit вышел из беты — ставим «Аудит сайта» по алфавиту (RU),
 * а не в хвосте рядом с бета-модулями.
 */
class PlaceSiteAuditMenuAlphabetically extends Migration
{
    /** main_projects.id — пункты админ-шестерёнки (не в сайдбаре). */
    private const ADMIN_PROJECT_IDS = [16, 26, 29, 17, 27, 33, 31];

    public function up(): void
    {
        if (! Schema::hasTable('main_projects')) {
            return;
        }

        $audit = DB::table('main_projects')
            ->where('link', 'like', '%/site-audit%')
            ->first();

        if ($audit === null) {
            return;
        }

        $auditId = (int) $audit->id;
        $labels = $this->projectLabels();

        if (! isset($labels[$auditId])) {
            return;
        }

        $this->placeInMainProjects($auditId, $labels);
        $this->placeInUserMenus($auditId, $labels);
    }

    public function down(): void
    {
        // Порядок меню после выхода из беты не откатываем.
    }

    /**
     * @return array<int, string> id => отображаемое RU-название
     */
    private function projectLabels(): array
    {
        $ruPath = base_path('resources/lang/ru.json');
        $ru = is_file($ruPath) ? json_decode((string) file_get_contents($ruPath), true) : [];
        if (! is_array($ru)) {
            $ru = [];
        }

        $rows = DB::table('main_projects')
            ->where('show', 1)
            ->whereNotIn('id', self::ADMIN_PROJECT_IDS)
            ->get(['id', 'title']);

        $labels = [];
        foreach ($rows as $row) {
            $title = (string) $row->title;
            $labels[(int) $row->id] = (string) ($ru[$title] ?? $title);
        }

        return $labels;
    }

    /**
     * @param array<int, string> $labels
     */
    private function placeInMainProjects(int $auditId, array $labels): void
    {
        $prevId = $this->alphabeticPredecessorId($auditId, array_keys($labels), $labels);

        // Сначала уводим аудит на свободный хвост (unique position).
        $parking = ((int) DB::table('main_projects')->max('position')) + 100;
        DB::table('main_projects')->where('id', $auditId)->update([
            'position' => $parking,
            'updated_at' => now(),
        ]);

        if ($prevId === null) {
            $minPos = (int) DB::table('main_projects')->where('id', '!=', $auditId)->min('position');
            $target = $minPos - 1;
            if ($this->positionTaken($target, $auditId)) {
                $this->shiftPositionsFrom($target);
            }
            DB::table('main_projects')->where('id', $auditId)->update([
                'position' => $target,
                'updated_at' => now(),
            ]);

            return;
        }

        $prevPos = (int) DB::table('main_projects')->where('id', $prevId)->value('position');
        $target = $prevPos + 1;

        if ($this->positionTaken($target, $auditId)) {
            $this->shiftPositionsFrom($target);
        }

        DB::table('main_projects')->where('id', $auditId)->update([
            'position' => $target,
            'updated_at' => now(),
        ]);
    }

    private function positionTaken(int $position, int $exceptId): bool
    {
        return DB::table('main_projects')
            ->where('position', $position)
            ->where('id', '!=', $exceptId)
            ->exists();
    }

    /** Сдвиг вниз по убыванию position — иначе unique key ломается. */
    private function shiftPositionsFrom(int $from): void
    {
        $ids = DB::table('main_projects')
            ->where('position', '>=', $from)
            ->orderByDesc('position')
            ->pluck('id');

        foreach ($ids as $id) {
            DB::table('main_projects')->where('id', $id)->increment('position');
        }
    }

    /**
     * @param array<int, string> $labels
     */
    private function placeInUserMenus(int $auditId, array $labels): void
    {
        if (! Schema::hasTable('menu_items_position')) {
            return;
        }

        DB::table('menu_items_position')->orderBy('id')->chunk(50, function ($rows) use ($auditId, $labels) {
            foreach ($rows as $row) {
                if (empty($row->positions)) {
                    continue;
                }

                $positions = json_decode($row->positions, true);
                if (! is_array($positions)) {
                    continue;
                }

                $changed = false;
                $without = $this->removeIdFromPositions($positions, $auditId, $changed);

                $presentIds = $this->collectIds($without);
                $prevId = $this->alphabeticPredecessorId($auditId, $presentIds, $labels);

                $updated = $this->insertAfterIdInPositions($without, $prevId, $auditId, $changed);

                if ($changed) {
                    DB::table('menu_items_position')
                        ->where('id', $row->id)
                        ->update(['positions' => json_encode($updated)]);
                }
            }
        });
    }

    /**
     * @param list<int> $candidateIds
     * @param array<int, string> $labels
     */
    private function alphabeticPredecessorId(int $auditId, array $candidateIds, array $labels): ?int
    {
        $auditLabel = $labels[$auditId] ?? null;
        if ($auditLabel === null) {
            return null;
        }

        $bestId = null;
        $bestLabel = null;

        foreach ($candidateIds as $id) {
            $id = (int) $id;
            if ($id === $auditId || ! isset($labels[$id])) {
                continue;
            }
            $label = $labels[$id];
            if ($this->compareLabels($label, $auditLabel) >= 0) {
                continue;
            }
            if ($bestId === null || $this->compareLabels($label, (string) $bestLabel) > 0) {
                $bestId = $id;
                $bestLabel = $label;
            }
        }

        return $bestId;
    }

    private function compareLabels(string $a, string $b): int
    {
        if (class_exists(\Collator::class)) {
            $collator = new \Collator('ru_RU');
            $cmp = $collator->compare($a, $b);
            if ($cmp !== false) {
                return (int) $cmp;
            }
        }

        return strcmp(mb_strtolower($a, 'UTF-8'), mb_strtolower($b, 'UTF-8'));
    }

    /**
     * @return list<int>
     */
    private function collectIds(array $positions): array
    {
        $ids = [];
        foreach ($positions as $item) {
            if (isset($item[0]) && is_array($item[0]) && ! empty($item[0]['dir'])) {
                foreach ($item as $entry) {
                    if (isset($entry['id'])) {
                        $ids[] = (int) $entry['id'];
                    }
                }
                continue;
            }
            if (isset($item['id'])) {
                $ids[] = (int) $item['id'];
            }
        }

        return $ids;
    }

    private function removeIdFromPositions(array $positions, int $removeId, bool &$changed): array
    {
        $result = [];

        foreach ($positions as $item) {
            if (isset($item[0]) && is_array($item[0]) && ! empty($item[0]['dir'])) {
                $group = [];
                foreach ($item as $entry) {
                    if (isset($entry['id']) && (int) $entry['id'] === $removeId) {
                        $changed = true;
                        continue;
                    }
                    $group[] = $entry;
                }
                if (count($group) > 1) {
                    $result[] = $group;
                } else {
                    $changed = true;
                }
                continue;
            }

            if (isset($item['id']) && (int) $item['id'] === $removeId) {
                $changed = true;
                continue;
            }

            $result[] = $item;
        }

        return $result;
    }

    /**
     * @param int|null $afterId null = в начало списка
     */
    private function insertAfterIdInPositions(array $positions, ?int $afterId, int $newId, bool &$changed): array
    {
        if ($this->positionsContainId($positions, $newId)) {
            return $positions;
        }

        if ($afterId === null) {
            array_unshift($positions, ['id' => $newId]);
            $changed = true;

            return $positions;
        }

        $result = [];
        $inserted = false;

        foreach ($positions as $item) {
            if (isset($item[0]) && is_array($item[0]) && ! empty($item[0]['dir'])) {
                $group = [];
                foreach ($item as $entry) {
                    $group[] = $entry;
                    if (isset($entry['id']) && (int) $entry['id'] === $afterId) {
                        $group[] = ['id' => $newId];
                        $inserted = true;
                        $changed = true;
                    }
                }
                $result[] = $group;
                continue;
            }

            $result[] = $item;
            if (isset($item['id']) && (int) $item['id'] === $afterId) {
                $result[] = ['id' => $newId];
                $inserted = true;
                $changed = true;
            }
        }

        if (! $inserted) {
            $result[] = ['id' => $newId];
            $changed = true;
        }

        return $result;
    }

    private function positionsContainId(array $positions, int $searchId): bool
    {
        foreach ($positions as $item) {
            if (isset($item[0]) && is_array($item[0]) && ! empty($item[0]['dir'])) {
                foreach ($item as $entry) {
                    if (isset($entry['id']) && (int) $entry['id'] === $searchId) {
                        return true;
                    }
                }
                continue;
            }
            if (isset($item['id']) && (int) $item['id'] === $searchId) {
                return true;
            }
        }

        return false;
    }
}
