<?php

namespace App\ViewComposers;

use App\SeoChecklist\SeoChecklistProject;
use App\SeoChecklist\SeoChecklistUserPreference;
use App\Services\SeoChecklist\SeoChecklistService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class SeoChecklistDueBadgeComposer
{
    public function compose(View $view): void
    {
        $defaults = [
            'seoChecklistNavVisible' => false,
            'seoChecklistDueCount' => 0,
            'seoChecklistOverdueCount' => 0,
            'seoChecklistReviewCount' => 0,
            'seoChecklistUnreadNotesCount' => 0,
            'seoChecklistActiveTimer' => null,
            'seoChecklistModuleTitle' => SeoChecklistUserPreference::defaultTitle(),
        ];

        $user = Auth::user();
        if (!$user || !SeoChecklistProject::tableReady()) {
            $view->with($defaults);

            return;
        }

        try {
            $canAccess = method_exists($user, 'can') && $user->can('SEO Checklist');
            if (!$canAccess) {
                $view->with($defaults);

                return;
            }

            $userId = (int) $user->id;
            $service = app(SeoChecklistService::class);
            $alerts = $service->dueAlertsForUser($userId);
            $unreadNotesCount = $service->unreadNotesCountForUser($userId);
            $reviewCount = $service->reviewQueueCountForUser($userId);
            $activeTimer = null;
            if (Schema::hasTable('seo_checklist_item_time_logs')) {
                $active = $service->activeTimerForUser($userId);
                if ($active) {
                    $log = $active['log'];
                    $item = $active['item'];
                    $project = $active['project'];
                    $anchorId = $item->parent_id ? (int) $item->parent_id : (int) $item->id;
                    $activeTimer = [
                        'item_id' => $item->id,
                        'project_id' => $project->id,
                        'domain' => $project->domain,
                        'title' => $item->title,
                        'url' => route('pages.seo-checklist.show', ['id' => $project->id]) . '#sc-item-' . $anchorId,
                        'started_at' => $log->started_at ? $log->started_at->toIso8601String() : null,
                        'elapsed_seconds' => $log->elapsedSeconds(),
                        'time_spent_seconds' => (int) $item->time_spent_seconds,
                        'display_seconds' => $item->displayTimeSpentSeconds($userId),
                        'stop_url' => route('pages.seo-checklist.timer.stop-active'),
                    ];
                }
            }

            $view->with([
                'seoChecklistNavVisible' => true,
                'seoChecklistDueCount' => (int) ($alerts['count'] ?? 0),
                'seoChecklistOverdueCount' => (int) ($alerts['overdue'] ?? 0),
                'seoChecklistReviewCount' => $reviewCount,
                'seoChecklistUnreadNotesCount' => $unreadNotesCount,
                'seoChecklistActiveTimer' => $activeTimer,
                'seoChecklistModuleTitle' => SeoChecklistUserPreference::moduleTitleFor($userId),
            ]);
        } catch (\Throwable $e) {
            $view->with($defaults);
        }
    }
}
