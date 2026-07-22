<?php

namespace App\Services\SiteAudit;

use App\SiteAuditCrawl;
use App\SiteAuditCrawlStat;
use App\SiteAuditFinding;
use App\SiteAuditPage;
use Illuminate\Support\Facades\DB;

class SiteAuditPruner
{
    public function deleteCrawl(SiteAuditCrawl $crawl): void
    {
        DB::transaction(function () use ($crawl) {
            SiteAuditFinding::query()->where('crawl_id', $crawl->id)->delete();
            SiteAuditPage::query()->where('crawl_id', $crawl->id)->delete();
            SiteAuditCrawlStat::query()->where('crawl_id', $crawl->id)->delete();
            $crawl->delete();
        });

        SiteAuditUserAgentSession::clear((int) $crawl->id);
    }

    /**
     * Оставляет N последних краулов проекта, остальные удаляет.
     *
     * @return int сколько краулов удалено
     */
    public function pruneProject(int $projectId, ?int $keep = null): int
    {
        $keep = $keep !== null
            ? max(1, $keep)
            : max(1, (int) config('site_audit.history_keep_per_project', 10));

        $ids = SiteAuditCrawl::query()
            ->where('project_id', $projectId)
            ->orderByDesc('id')
            ->pluck('id');

        if ($ids->count() <= $keep) {
            return 0;
        }

        $toDelete = $ids->slice($keep)->values();
        $deleted = 0;

        foreach ($toDelete as $id) {
            $crawl = SiteAuditCrawl::query()->find($id);
            if (! $crawl) {
                continue;
            }
            // не трогаем активные
            if (! $crawl->isFinished()) {
                continue;
            }
            $this->deleteCrawl($crawl);
            $deleted++;
        }

        return $deleted;
    }

    /**
     * Prune по всем проектам пользователя (или всем, если userId=null).
     */
    public function pruneAll(?int $userId = null, ?int $keep = null): int
    {
        $q = SiteAuditCrawl::query()->select('project_id')->distinct();
        if ($userId) {
            $q->where('user_id', $userId);
        }

        $total = 0;
        foreach ($q->pluck('project_id') as $projectId) {
            $total += $this->pruneProject((int) $projectId, $keep);
        }

        return $total;
    }
}
