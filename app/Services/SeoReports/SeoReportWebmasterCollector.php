<?php

namespace App\Services\SeoReports;

use App\SeoReports\SeoReport;
use App\SeoReports\SeoReportMetricRegistry;
use App\SeoReports\SeoReportProject;
use App\SeoReports\SeoReportSectionRegistry;
use App\Services\YandexWebmaster\YandexWebmasterService;
use Carbon\Carbon;

/**
 * Сбор блока Яндекс.Вебмастер: диагностика, дубли мета, отфильтрованные,
 * KPI / топ запросов через Search Queries API (+ CSV как fallback).
 */
class SeoReportWebmasterCollector
{
    private const META_PROBLEM_CODES = [
        'DUPLICATE_CONTENT_ATTRS',
        'DOCUMENTS_MISSING_TITLE',
        'DOCUMENTS_MISSING_DESCRIPTION',
    ];

    private const PROBLEM_LABELS = [
        'CONNECT_FAILED' => 'Роботы не смогли посетить сайт',
        'DISALLOWED_IN_ROBOTS' => 'Сайт закрыт в robots.txt',
        'DNS_ERROR' => 'Ошибка DNS',
        'MAIN_PAGE_ERROR' => 'Главная страница возвращает ошибку',
        'THREATS' => 'Нарушения или проблемы с безопасностью',
        'INSIGNIFICANT_CGI_PARAMETER' => 'Дубли с GET-параметрами',
        'SLOW_AVG_RESPONSE_TIME' => 'Долгий ответ сервера',
        'SSL_CERTIFICATE_ERROR' => 'Проблема SSL-сертификата',
        'URL_ALERT_4XX' => 'Страницы отвечают 4xx',
        'URL_ALERT_5XX' => 'Страницы отвечают 5xx',
        'DISALLOWED_URLS_ALERT' => 'Полезные страницы закрыты от индексации',
        'DOCUMENTS_MISSING_DESCRIPTION' => 'Нет Description на многих страницах',
        'DOCUMENTS_MISSING_TITLE' => 'Нет title на многих страницах',
        'DUPLICATE_CONTENT_ATTRS' => 'Одинаковые title и Description',
        'DUPLICATE_PAGES' => 'Дубли контента страниц',
        'ERROR_IN_ROBOTS_TXT' => 'Ошибки в robots.txt',
        'ERRORS_IN_SITEMAPS' => 'Ошибки в Sitemap',
        'FAVICON_ERROR' => 'Favicon недоступен',
        'MAIN_MIRROR_IS_NOT_HTTPS' => 'Сайт без HTTPS',
        'MAIN_PAGE_REDIRECTS' => 'Главная редиректит на другой сайт',
        'NO_METRIKA_COUNTER_BINDING' => 'Не привязан счётчик Метрики',
        'NO_METRIKA_COUNTER_CRAWL_ENABLED' => 'Не включён обход по Метрике',
        'NO_ROBOTS_TXT' => 'Нет robots.txt',
        'NO_SITEMAPS' => 'Нет Sitemap',
        'NO_SITEMAP_MODIFICATIONS' => 'Sitemap давно не обновлялся',
        'SOFT_404' => 'Некорректные Soft 404',
        'TOO_MANY_DOMAINS_ON_SEARCH' => 'В поиске видны поддомены',
        'FAVICON_PROBLEM' => 'Проблема с favicon',
        'NO_METRIKA_COUNTER' => 'Ошибка счётчика Метрики',
        'NO_REGIONS' => 'Не задан регион',
        'NOT_IN_SPRAV' => 'Нет в Яндекс Справочнике',
        'NOT_MOBILE_FRIENDLY' => 'Не адаптирован под мобильные',
    ];

    private const EXCLUDED_STATUS_LABELS = [
        'LOW_QUALITY' => 'Малополезная / низкокачественная',
        'DUPLICATE' => 'Дубль',
        'NO_INDEX' => 'noindex',
        'CLEAN_PARAMS' => 'Clean-param',
        'NOT_CANONICAL' => 'Не canonical',
        'NOT_MAIN_MIRROR' => 'Неглавное зеркало',
        'ROBOTS_URL_ERROR' => 'Запрет в robots.txt',
        'ROBOTS_HOST_ERROR' => 'Сайт закрыт в robots.txt',
        'HTTP_ERROR' => 'HTTP-ошибка',
        'REDIRECT_NOTSEARCHABLE' => 'Редирект',
        'PARSER_ERROR' => 'Ошибка разбора',
        'OTHER' => 'Другое',
    ];

    /** @var YandexWebmasterService */
    private $webmaster;

    public function __construct(YandexWebmasterService $webmaster)
    {
        $this->webmaster = $webmaster;
    }

    /**
     * @return array{ok:bool,status:string,progress:string,message?:string,data?:array<string,mixed>}
     */
    public function collect(SeoReportProject $project, SeoReport $report): array
    {
        $settings = method_exists($project, 'reportSettings')
            ? $project->reportSettings()
            : (is_array($project->settings_json) ? $project->settings_json : []);
        $hostId = trim((string) ($settings['webmaster_host'] ?? ''));
        $import = is_array($settings['webmaster_import'] ?? null) ? $settings['webmaster_import'] : null;

        $base = [
            'source' => 'webmaster',
            'property' => $hostId !== '' ? $hostId : null,
            'kpis' => is_array($import['kpis'] ?? null) ? $import['kpis'] : [],
            'queries' => is_array($import['queries'] ?? null) ? $import['queries'] : [],
            'pages' => is_array($import['pages'] ?? null) ? $import['pages'] : [],
            'imported_at' => $import['imported_at'] ?? null,
            'diagnostics' => [],
            'meta_duplicates' => [],
            'filtered_pages' => [
                'summary' => [],
                'low_quality' => [],
                'samples' => [],
            ],
            'note' => null,
        ];

        $userId = (int) $project->user_id;
        $needDiag = SeoReportMetricRegistry::enabled($settings, 'webmaster', 'diagnostics')
            || SeoReportMetricRegistry::enabled($settings, 'webmaster', 'meta_duplicates');
        $needFiltered = SeoReportMetricRegistry::enabled($settings, 'webmaster', 'filtered_pages');
        $needKpis = SeoReportMetricRegistry::enabled($settings, 'webmaster', 'kpis');
        $needQueries = SeoReportMetricRegistry::enabled($settings, 'webmaster', 'queries');
        $needSearchApi = $needKpis || $needQueries;
        $needApi = $needDiag || $needFiltered || $needSearchApi;

        if ($hostId === '') {
            if ($this->importHasSearchData($import)) {
                return $this->ok($base, 'import');
            }

            return [
                'ok' => false,
                'status' => SeoReportSectionRegistry::SOURCE_STATUS_NOT_CONNECTED,
                'progress' => 'skip',
                'message' => __('Yandex Webmaster is not connected'),
            ];
        }

        if ($needApi && !$this->webmaster->isConnected($userId)) {
            if ($this->importHasSearchData($import)) {
                $base['note'] = __('Connect Yandex Webmaster OAuth');

                return $this->ok($base, 'import');
            }

            return [
                'ok' => false,
                'status' => SeoReportSectionRegistry::SOURCE_STATUS_NOT_CONNECTED,
                'progress' => 'skip',
                'message' => __('Connect Yandex Webmaster OAuth'),
            ];
        }

        $date1 = optional($report->period_from)->format('Y-m-d');
        $date2 = optional($report->period_to)->format('Y-m-d');
        if (!$date1 || !$date2) {
            $date2 = Carbon::now()->subDay()->format('Y-m-d');
            $date1 = Carbon::now()->subDays(28)->format('Y-m-d');
        }

        $apiErrors = [];
        $fromApiSearch = false;

        if ($needApi && $this->webmaster->isConnected($userId)) {
            if ($needDiag) {
                $diag = $this->webmaster->getDiagnostics($userId, $hostId);
                if (!empty($diag['ok'])) {
                    $parsed = $this->parseDiagnostics(is_array($diag['problems'] ?? null) ? $diag['problems'] : []);
                    $base['diagnostics'] = $parsed['diagnostics'];
                    $base['meta_duplicates'] = $parsed['meta_duplicates'];
                } else {
                    $apiErrors[] = (string) ($diag['message'] ?? __('Yandex Webmaster API error'));
                }
            }

            if ($needFiltered) {
                $samples = $this->webmaster->getSearchUrlEventSamples($userId, $hostId, 100);
                if (!empty($samples['ok'])) {
                    $base['filtered_pages'] = $this->parseExcludedSamples(
                        is_array($samples['samples'] ?? null) ? $samples['samples'] : []
                    );
                } else {
                    $apiErrors[] = (string) ($samples['message'] ?? __('Yandex Webmaster API error'));
                }
            }

            if ($needKpis) {
                $hist = $this->webmaster->getSearchQueriesHistoryAll($userId, $hostId, $date1, $date2);
                if (!empty($hist['ok']) && is_array($hist['kpis'] ?? null)) {
                    $base['kpis'] = $hist['kpis'];
                    $fromApiSearch = true;
                } else {
                    $apiErrors[] = (string) ($hist['message'] ?? __('Yandex Webmaster API error'));
                }
            }

            if ($needQueries) {
                $popular = $this->webmaster->getSearchQueriesPopular($userId, $hostId, $date1, $date2, 25);
                if (!empty($popular['ok']) && is_array($popular['queries'] ?? null)) {
                    $base['queries'] = $popular['queries'];
                    $fromApiSearch = true;
                } else {
                    $apiErrors[] = (string) ($popular['message'] ?? __('Yandex Webmaster API error'));
                }
            }
        }

        $hasImport = $this->importHasSearchData($import);
        $hasDiag = !empty($base['diagnostics']);
        $hasMeta = !empty($base['meta_duplicates']);
        $hasFiltered = !empty($base['filtered_pages']['summary'])
            || !empty($base['filtered_pages']['low_quality']);
        $hasSearch = $this->hasSearchKpis($base) || !empty($base['queries']) || !empty($base['pages']);

        if (!$hasImport && !$hasDiag && !$hasMeta && !$hasFiltered && !$hasSearch) {
            if ($apiErrors) {
                return [
                    'ok' => false,
                    'status' => SeoReportSectionRegistry::SOURCE_STATUS_ERROR,
                    'progress' => 'error',
                    'message' => $apiErrors[0],
                ];
            }

            return [
                'ok' => false,
                'status' => SeoReportSectionRegistry::SOURCE_STATUS_EMPTY,
                'progress' => 'empty',
                'message' => __('No Webmaster data for period'),
            ];
        }

        if ($apiErrors) {
            $base['note'] = implode('; ', array_unique($apiErrors));
        }

        $source = $fromApiSearch && $hasImport
            ? 'api+import'
            : ($fromApiSearch ? 'api' : ($hasImport ? 'import+api' : 'api'));

        return $this->ok($base, $source);
    }

    /**
     * @param array<string,mixed> $base
     * @return array{ok:bool,status:string,progress:string,data:array<string,mixed>}
     */
    private function ok(array $base, string $source): array
    {
        $base['source'] = $source;

        return [
            'ok' => true,
            'status' => SeoReportSectionRegistry::SOURCE_STATUS_OK,
            'progress' => 'ok',
            'data' => $base,
        ];
    }

    /**
     * @param array<string,mixed>|null $import
     */
    private function importHasSearchData(?array $import): bool
    {
        return is_array($import)
            && (!empty($import['queries']) || !empty($import['pages']) || !empty($import['kpis']));
    }

    /**
     * @param array<string,mixed> $base
     */
    private function hasSearchKpis(array $base): bool
    {
        $kpis = is_array($base['kpis'] ?? null) ? $base['kpis'] : [];
        foreach (['clicks', 'impressions', 'ctr', 'position'] as $key) {
            if (isset($kpis[$key]) && $kpis[$key] !== null && $kpis[$key] !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string,array{severity:string,state:string,last_state_update:?string}> $problems
     * @return array{diagnostics:list<array<string,mixed>>,meta_duplicates:list<array<string,mixed>>}
     */
    private function parseDiagnostics(array $problems): array
    {
        $diagnostics = [];
        $meta = [];
        foreach ($problems as $code => $row) {
            $state = strtoupper((string) ($row['state'] ?? ''));
            if ($state !== '' && $state !== 'PRESENT') {
                continue;
            }
            $severity = strtoupper((string) ($row['severity'] ?? ''));
            $label = self::PROBLEM_LABELS[$code] ?? $code;
            $item = [
                'code' => (string) $code,
                'label' => $label,
                'severity' => $severity !== '' ? $severity : 'POSSIBLE_PROBLEM',
                'updated_at' => $row['last_state_update'] ?? null,
            ];
            if (in_array((string) $code, self::META_PROBLEM_CODES, true)) {
                $meta[] = $item;
            } else {
                $diagnostics[] = $item;
            }
        }

        return ['diagnostics' => $diagnostics, 'meta_duplicates' => $meta];
    }

    /**
     * @param list<array<string,mixed>> $samples
     * @return array{summary:list<array{status:string,label:string,count:int}>,low_quality:list<array<string,mixed>>,samples:list<array<string,mixed>>}
     */
    private function parseExcludedSamples(array $samples): array
    {
        $counts = [];
        $lowQuality = [];
        $normalized = [];
        foreach ($samples as $row) {
            if (!is_array($row)) {
                continue;
            }
            $status = strtoupper(trim((string) ($row['excluded_url_status'] ?? '')));
            if ($status === '') {
                continue;
            }
            $counts[$status] = ($counts[$status] ?? 0) + 1;
            $item = [
                'url' => (string) ($row['url'] ?? ''),
                'title' => (string) ($row['title'] ?? ''),
                'status' => $status,
                'label' => self::EXCLUDED_STATUS_LABELS[$status] ?? $status,
                'event_date' => $row['event_date'] ?? null,
            ];
            $normalized[] = $item;
            if ($status === 'LOW_QUALITY' && count($lowQuality) < 25) {
                $lowQuality[] = $item;
            }
        }

        $summary = [];
        foreach ($counts as $status => $count) {
            $summary[] = [
                'status' => $status,
                'label' => self::EXCLUDED_STATUS_LABELS[$status] ?? $status,
                'count' => $count,
            ];
        }
        usort($summary, static function ($a, $b) {
            return ($b['count'] ?? 0) <=> ($a['count'] ?? 0);
        });

        return [
            'summary' => $summary,
            'low_quality' => $lowQuality,
            'samples' => array_slice($normalized, 0, 50),
        ];
    }
}
