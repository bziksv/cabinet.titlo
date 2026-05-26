<?php

namespace App\Services;

use App\Http\Controllers\CroneController;
use App\LinkTracking;
use App\ProjectTracking;
use App\User;

class TelegramBacklinkTestService
{
    /**
     * @return array{ok:int,fail:int,skipped:int,error:string,redirect_route:string}
     */
    public function runForUser(User $user): array
    {
        if (!$user->isTelegramConnected()) {
            return [
                'ok' => 0,
                'fail' => 0,
                'skipped' => 0,
                'error' => 'no_telegram',
                'redirect_route' => 'profile.index',
            ];
        }

        $projectIds = ProjectTracking::where('user_id', $user->id)->pluck('id');
        if ($projectIds->isEmpty()) {
            return [
                'ok' => 0,
                'fail' => 0,
                'skipped' => 0,
                'error' => 'no_projects',
                'redirect_route' => 'backlink',
            ];
        }

        $links = LinkTracking::with('project')
            ->whereIn('project_tracking_id', $projectIds)
            ->where('broken', true)
            ->get();

        if ($links->isEmpty()) {
            return [
                'ok' => 0,
                'fail' => 0,
                'skipped' => 0,
                'error' => 'no_broken',
                'redirect_route' => 'backlink',
            ];
        }

        if (empty(config('app.telegram_bot_token'))) {
            return [
                'ok' => 0,
                'fail' => 0,
                'skipped' => 0,
                'error' => 'no_token',
                'redirect_route' => 'admin.telegram-proxy.index',
            ];
        }

        $crone = new CroneController();
        $telegramOk = 0;
        $telegramFail = 0;
        $linksSkipped = 0;
        $lastApiError = '';
        $maxProjects = (int) config('cabinet-backlink.notifications.test_max_per_run', 10);
        $projectsAttempted = 0;

        foreach ($links->groupBy('project_tracking_id') as $projectId => $projectLinks) {
            if ($projectsAttempted >= $maxProjects) {
                break;
            }

            $project = ProjectTracking::find($projectId);
            if (!$project) {
                continue;
            }

            $problemCount = 0;
            foreach ($projectLinks as $link) {
                $crone->result = [];
                $crone->analyseLink(
                    $link->site_donor,
                    $link->link,
                    $link->anchor,
                    (bool) $link->nofollow,
                    (bool) $link->noindex
                );

                if (isset($crone->result['error'])) {
                    $problemCount++;
                } else {
                    $linksSkipped++;
                }
            }

            $projectsAttempted++;
            $countToReport = $problemCount > 0 ? $problemCount : 0;
            if ($user->sendBrokenLinkProjectTelegram($project, $countToReport, true)) {
                $telegramOk++;
            } else {
                $telegramFail++;
                $lastApiError = TelegramBotService::$lastError;
            }
        }

        return [
            'ok' => $telegramOk,
            'fail' => $telegramFail,
            'skipped' => $linksSkipped,
            'error' => $lastApiError,
            'redirect_route' => 'admin.telegram-proxy.index',
        ];
    }
}
