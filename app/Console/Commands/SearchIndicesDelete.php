<?php

namespace App\Console\Commands;

use App\MonitoringSettings;
use App\SearchIndex;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SearchIndicesDelete extends Command
{
    /**
     * @var string
     */
    protected $signature = 'search-indices:delete
                            {--batch=50000 : Rows per DELETE batch (PRIMARY KEY range)}
                            {--dry-run : Only resolve cutoff id, do not delete}';

    /**
     * @var string
     */
    protected $description = 'Delete search_indices older than monitoring retention (batched by id)';

    /**
     * @return int
     */
    public function handle()
    {
        $days = (int) ((new MonitoringSettings())->getValue('search_indices_days_delete') ?: 30);
        if ($days < 1) {
            $days = 30;
        }

        $cutoff = Carbon::today()->subDays($days);
        $batch = max(1000, (int) $this->option('batch'));

        $minId = SearchIndex::query()->min('id');
        if ($minId === null) {
            $this->info('Deleted 0 records! (empty table)');

            return 0;
        }

        $cutoffId = $this->findFirstIdOnOrAfter($cutoff);
        if ($cutoffId === null) {
            $this->info("Deleted 0 records! (all rows >= {$cutoff->toDateString()}, retention {$days}d)");

            return 0;
        }

        $approx = max(0, (int) $cutoffId - (int) $minId);
        $this->info("Retention {$days}d, cutoff_date={$cutoff->toDateTimeString()}, delete id < {$cutoffId} (~{$approx} id span), batch={$batch}");

        if ($this->option('dry-run')) {
            $this->warn('Dry-run: no rows deleted.');

            return 0;
        }

        $total = 0;
        $cur = (int) $minId;

        while ($cur < $cutoffId) {
            $start = $cur;
            $end = min($cur + $batch, $cutoffId);
            $deleted = SearchIndex::query()
                ->where('id', '>=', $start)
                ->where('id', '<', $end)
                ->delete();

            $total += (int) $deleted;
            $cur = $end;

            if ($deleted > 0) {
                $this->line("  batch [{$start},{$end}) deleted={$deleted} total={$total}");
            }
        }

        // Gaps / non-contiguous leftovers below cutoff
        do {
            $deleted = DB::delete(
                'DELETE FROM search_indices WHERE id < ? LIMIT ?',
                [$cutoffId, $batch]
            );
            $total += (int) $deleted;
            if ($deleted > 0) {
                $this->line("  gap-fill deleted={$deleted} total={$total}");
            }
        } while ($deleted > 0);

        $this->info("Deleted {$total} records!");

        return 0;
    }

    /**
     * Smallest id with created_at >= $cutoff (PK binary search — no created_at index needed).
     * Rows with id < result are older than retention and safe to delete.
     *
     * @return int|null null when nothing to delete
     */
    private function findFirstIdOnOrAfter(Carbon $cutoff): ?int
    {
        $oldest = SearchIndex::query()->orderBy('id')->first(['id', 'created_at']);
        if ($oldest === null) {
            return null;
        }

        if (Carbon::parse($oldest->created_at)->gte($cutoff)) {
            return null;
        }

        $newest = SearchIndex::query()->orderByDesc('id')->first(['id', 'created_at']);
        if ($newest !== null && Carbon::parse($newest->created_at)->lt($cutoff)) {
            return (int) $newest->id + 1;
        }

        $lo = (int) $oldest->id;
        $hi = (int) SearchIndex::query()->max('id');
        $answer = $hi + 1;

        while ($lo <= $hi) {
            $mid = intdiv($lo + $hi, 2);
            $row = SearchIndex::query()
                ->where('id', '>=', $mid)
                ->orderBy('id')
                ->first(['id', 'created_at']);

            if ($row === null) {
                $hi = $mid - 1;
                continue;
            }

            if (Carbon::parse($row->created_at)->lt($cutoff)) {
                $lo = (int) $row->id + 1;
            } else {
                $answer = (int) $row->id;
                $hi = $mid - 1;
            }
        }

        if ($answer <= (int) $oldest->id) {
            return null;
        }

        return $answer;
    }
}
