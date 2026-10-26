<?php

namespace App\Services\SiteAudit;

use App\SiteAuditCrawl;
use App\SiteAuditFinding;
use App\SiteAuditProject;

class SiteAuditPageRecheck
{
    /**
     * Повторно скачать один URL текущей проверки и обновить замечания этой страницы.
     *
     * @return array{ok:bool,message:string,level:string}
     */
    public function recheck(SiteAuditCrawl $crawl, SiteAuditFinding $finding): array
    {
        $code = (string) $finding->code;
        if (! SiteAuditPageProcessor::isRecheckableCode($code)) {
            return [
                'ok' => false,
                'level' => 'error',
                'message' => 'Это замечание считается по всему обходу. Одной страницей его не перепроверить.',
            ];
        }

        if (! $crawl->isFinished()) {
            return [
                'ok' => false,
                'level' => 'error',
                'message' => 'Дождитесь окончания проверки, затем можно перепроверить страницу.',
            ];
        }

        $project = $crawl->project;
        if (! $project instanceof SiteAuditProject) {
            return [
                'ok' => false,
                'level' => 'error',
                'message' => 'Проект не найден.',
            ];
        }

        $url = trim((string) $finding->url);
        if (! $this->urlBelongsToProject($url, (string) $project->domain)) {
            return [
                'ok' => false,
                'level' => 'error',
                'message' => 'Можно перепроверить только страницы этого сайта.',
            ];
        }

        if ($code === 'broken_internal_link') {
            return $this->recheckBrokenInternalLink($crawl, $finding, $project, $url);
        }

        $progress = is_array($crawl->progress_json) ? $crawl->progress_json : [];
        $settings = array_merge(
            is_array($project->settings_json) ? $project->settings_json : [],
            is_array($progress['settings'] ?? null) ? $progress['settings'] : []
        );
        $host = SiteAuditUrlNormalizer::hostOf('https://' . $project->domain) ?: (string) $project->domain;
        $requestedHash = SiteAuditUrlNormalizer::hash($url);
        $robots = $this->freshRobotsGroups((string) $project->domain);

        $processed = (new SiteAuditPageProcessor())->process(
            (int) $crawl->id,
            $url,
            $host,
            $settings,
            $robots['groups']
        );
        $finalUrl = trim((string) ($processed['url'] ?? $url));
        $finalHash = SiteAuditUrlNormalizer::hash($finalUrl !== '' ? $finalUrl : $url);
        $status = isset($processed['status_code']) ? (int) $processed['status_code'] : 0;

        if ($finalHash !== $requestedHash && $status >= 200 && $status < 400) {
            SiteAuditFinding::query()
                ->where('crawl_id', $crawl->id)
                ->where('url_hash', $requestedHash)
                ->where('code', $code)
                ->delete();
        }

        $still = SiteAuditFinding::query()
            ->where('crawl_id', $crawl->id)
            ->where('code', $code)
            ->whereIn('url_hash', array_values(array_unique([$requestedHash, $finalHash])))
            ->exists();

        $outcome = $this->outcome($status, $still);
        if (empty($robots['fresh']) && $code === 'robots_blocked') {
            $outcome['message'] .= ' robots.txt сейчас не скачался, правило взято из обхода.';
            if ($outcome['level'] === 'status') {
                $outcome['level'] = 'warning';
            }
        }

        return $outcome;
    }

    /**
     * Битая внутренняя ссылка: строка = битый целевой URL.
     * Перекачиваем цель; если отвечает — снимаем finding и чистим page_has_broken_links.
     *
     * @return array{ok:bool,message:string,level:string}
     */
    private function recheckBrokenInternalLink(
        SiteAuditCrawl $crawl,
        SiteAuditFinding $finding,
        SiteAuditProject $project,
        string $url
    ): array {
        $progress = is_array($crawl->progress_json) ? $crawl->progress_json : [];
        $settings = array_merge(
            is_array($project->settings_json) ? $project->settings_json : [],
            is_array($progress['settings'] ?? null) ? $progress['settings'] : []
        );
        $host = SiteAuditUrlNormalizer::hostOf('https://' . $project->domain) ?: (string) $project->domain;
        $requestedHash = SiteAuditUrlNormalizer::hash($url);
        $robots = $this->freshRobotsGroups((string) $project->domain);

        $processed = (new SiteAuditPageProcessor())->process(
            (int) $crawl->id,
            $url,
            $host,
            $settings,
            $robots['groups']
        );
        $finalUrl = trim((string) ($processed['url'] ?? $url));
        $finalHash = SiteAuditUrlNormalizer::hash($finalUrl !== '' ? $finalUrl : $url);
        $status = isset($processed['status_code']) ? (int) $processed['status_code'] : 0;
        $hashes = array_values(array_unique(array_filter([$requestedHash, $finalHash])));

        if ($status >= 200 && $status < 400) {
            SiteAuditFinding::query()
                ->where('crawl_id', $crawl->id)
                ->where('code', 'broken_internal_link')
                ->whereIn('url_hash', $hashes)
                ->delete();

            $this->prunePageHasBrokenLinks(
                (int) $crawl->id,
                [$url, $finalUrl],
                $hashes
            );

            return [
                'ok' => true,
                'level' => 'status',
                'message' => 'Адрес отвечает ' . $status . '. Битая ссылка снята.',
            ];
        }

        $meta = is_array($finding->meta_json) ? $finding->meta_json : [];
        $meta['status'] = $status > 0 ? $status : null;
        $meta['source'] = 'recheck';
        if ($status <= 0) {
            $meta['error'] = 'no_response';
        } else {
            unset($meta['error']);
        }
        $finding->meta_json = $meta;
        $finding->save();

        if ($status >= 400) {
            return [
                'ok' => true,
                'level' => 'warning',
                'message' => 'Адрес всё ещё отвечает ' . $status . '. Замечание осталось.',
            ];
        }

        return [
            'ok' => true,
            'level' => 'warning',
            'message' => 'Адрес не ответил. Замечание осталось.',
        ];
    }

    /**
     * Убрать починенную цель из samples «Страницы с битыми ссылками»; пустые — удалить.
     *
     * @param  string[]  $urls
     * @param  string[]  $hashes
     */
    private function prunePageHasBrokenLinks(int $crawlId, array $urls, array $hashes): void
    {
        $urlSet = [];
        foreach ($urls as $u) {
            $u = trim((string) $u);
            if ($u !== '') {
                $urlSet[$u] = true;
            }
        }
        $hashSet = array_fill_keys(array_filter($hashes), true);
        if ($urlSet === [] && $hashSet === []) {
            return;
        }

        $rows = SiteAuditFinding::query()
            ->where('crawl_id', $crawlId)
            ->where('code', 'page_has_broken_links')
            ->get();

        foreach ($rows as $row) {
            $meta = is_array($row->meta_json) ? $row->meta_json : [];
            $samples = is_array($meta['samples'] ?? null) ? $meta['samples'] : [];
            if ($samples === []) {
                continue;
            }

            $kept = [];
            $changed = false;
            foreach ($samples as $sample) {
                if (! is_array($sample)) {
                    $kept[] = $sample;
                    continue;
                }
                $su = trim((string) ($sample['url'] ?? ''));
                if ($su === '') {
                    $kept[] = $sample;
                    continue;
                }
                $sh = SiteAuditUrlNormalizer::hash($su);
                if (isset($urlSet[$su]) || isset($hashSet[$sh])) {
                    $changed = true;
                    continue;
                }
                $kept[] = $sample;
            }

            if (! $changed) {
                continue;
            }

            if ($kept === []) {
                $row->delete();
                continue;
            }

            $meta['samples'] = $kept;
            $meta['count'] = count($kept);
            $row->meta_json = $meta;
            $row->save();
        }

        // Если цель была в обходе как 5xx/4xx — process() уже обновил page-level findings.
        // Страница без записи в site_audit_pages возможна при HEAD-only битости — ничего доп.
        unset($urlSet);
    }

    /**
     * Свежий robots.txt. groups = null — не скачался, тогда страница считается по снимку обхода.
     *
     * @return array{fresh:bool,groups:?array}
     */
    private function freshRobotsGroups(string $domain): array
    {
        try {
            $analyzed = (new SiteAuditRobotsTxt())->analyze($domain);
        } catch (\Throwable $e) {
            return ['fresh' => false, 'groups' => null];
        }

        if (empty($analyzed['ok'])) {
            return ['fresh' => false, 'groups' => null];
        }

        return [
            'fresh' => true,
            'groups' => is_array($analyzed['groups'] ?? null) ? $analyzed['groups'] : [],
        ];
    }

    /**
     * @return array{ok:bool,message:string,level:string}
     */
    private function outcome(int $status, bool $still): array
    {
        if (! $still) {
            if ($status >= 200 && $status < 400) {
                return [
                    'ok' => true,
                    'level' => 'status',
                    'message' => 'Страница отвечает ' . $status . '. Замечание снято.',
                ];
            }

            return [
                'ok' => true,
                'level' => 'status',
                'message' => 'Замечание снято.',
            ];
        }

        if ($status >= 400) {
            return [
                'ok' => true,
                'level' => 'warning',
                'message' => 'Страница всё ещё отвечает ' . $status . '. Замечание осталось.',
            ];
        }

        if ($status >= 200 && $status < 400) {
            return [
                'ok' => true,
                'level' => 'warning',
                'message' => 'Страница отвечает ' . $status . ', но замечание осталось.',
            ];
        }

        return [
            'ok' => true,
            'level' => 'warning',
            'message' => 'Страница не ответила. Замечание осталось.',
        ];
    }

    private function urlBelongsToProject(string $url, string $domain): bool
    {
        if (! preg_match('#^https?://#i', $url)) {
            return false;
        }

        $pageHost = SiteAuditUrlNormalizer::hostOf($url);
        $projectHost = SiteAuditUrlNormalizer::preferHostFromDomain($domain);
        if ($pageHost === null || $projectHost === '') {
            return false;
        }

        $bare = function ($host) {
            $host = strtolower((string) $host);

            return strpos($host, 'www.') === 0 ? substr($host, 4) : $host;
        };

        return $bare($pageHost) === $bare($projectHost);
    }
}
