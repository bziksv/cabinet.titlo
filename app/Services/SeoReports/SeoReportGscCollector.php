<?php

namespace App\Services\SeoReports;

use App\SeoReports\SeoReport;
use App\SeoReports\SeoReportMetricRegistry;
use App\SeoReports\SeoReportProject;
use App\SeoReports\SeoReportSectionRegistry;
use App\Services\GoogleSearchConsole\GoogleSearchConsoleService;
use Carbon\Carbon;

/**
 * Сбор блока Google Search Console: KPI / топ запросов / страниц через Search Analytics API.
 * CSV-import остаётся fallback.
 */
class SeoReportGscCollector
{
    /** @var GoogleSearchConsoleService */
    private $gsc;

    public function __construct(GoogleSearchConsoleService $gsc)
    {
        $this->gsc = $gsc;
    }

    /**
     * @return array{ok:bool,status:string,progress:string,message?:string,data?:array<string,mixed>}
     */
    public function collect(SeoReportProject $project, SeoReport $report): array
    {
        $settings = method_exists($project, 'reportSettings')
            ? $project->reportSettings()
            : (is_array($project->settings_json) ? $project->settings_json : []);
        $propertyId = trim((string) ($settings['gsc_property'] ?? ''));
        $import = is_array($settings['gsc_import'] ?? null) ? $settings['gsc_import'] : null;

        $base = [
            'source' => 'gsc',
            'property' => $propertyId !== '' ? $propertyId : null,
            'kpis' => is_array($import['kpis'] ?? null) ? $import['kpis'] : [],
            'queries' => is_array($import['queries'] ?? null) ? $import['queries'] : [],
            'pages' => is_array($import['pages'] ?? null) ? $import['pages'] : [],
            'imported_at' => $import['imported_at'] ?? null,
            'note' => null,
        ];

        $userId = (int) $project->user_id;
        $needKpis = SeoReportMetricRegistry::enabled($settings, 'gsc', 'kpis');
        $needQueries = SeoReportMetricRegistry::enabled($settings, 'gsc', 'queries');
        $needPages = SeoReportMetricRegistry::enabled($settings, 'gsc', 'pages');
        $needApi = $needKpis || $needQueries || $needPages;

        if ($propertyId === '') {
            if ($this->importHasSearchData($import)) {
                return $this->ok($base, 'import');
            }

            return [
                'ok' => false,
                'status' => SeoReportSectionRegistry::SOURCE_STATUS_NOT_CONNECTED,
                'progress' => 'skip',
                'message' => __('Google Search Console is not connected'),
            ];
        }

        if ($needApi && !$this->gsc->isConnected($userId)) {
            if ($this->importHasSearchData($import)) {
                $base['note'] = __('Connect Google Search Console OAuth');

                return $this->ok($base, 'import');
            }

            return [
                'ok' => false,
                'status' => SeoReportSectionRegistry::SOURCE_STATUS_NOT_CONNECTED,
                'progress' => 'skip',
                'message' => __('Connect Google Search Console OAuth'),
            ];
        }

        $date1 = optional($report->period_from)->format('Y-m-d');
        $date2 = optional($report->period_to)->format('Y-m-d');
        if (!$date1 || !$date2) {
            $date2 = Carbon::now()->subDay()->format('Y-m-d');
            $date1 = Carbon::now()->subDays(28)->format('Y-m-d');
        }

        $apiErrors = [];
        $fromApi = false;

        if ($needApi && $this->gsc->isConnected($userId)) {
            if ($needKpis) {
                $kpiRes = $this->gsc->fetchSearchAnalytics($userId, $propertyId, $date1, $date2, [], 1);
                if (!empty($kpiRes['ok'])) {
                    $base['kpis'] = $this->aggregateKpis(is_array($kpiRes['rows'] ?? null) ? $kpiRes['rows'] : []);
                    $fromApi = true;
                } else {
                    $apiErrors[] = (string) ($kpiRes['message'] ?? __('Could not load GSC search analytics'));
                }
            }

            if ($needQueries) {
                $qRes = $this->gsc->fetchSearchAnalytics($userId, $propertyId, $date1, $date2, ['query'], 25);
                if (!empty($qRes['ok'])) {
                    $base['queries'] = $this->mapDimensionRows(
                        is_array($qRes['rows'] ?? null) ? $qRes['rows'] : [],
                        'query'
                    );
                    $fromApi = true;
                } else {
                    $apiErrors[] = (string) ($qRes['message'] ?? __('Could not load GSC search analytics'));
                }
            }

            if ($needPages) {
                $pRes = $this->gsc->fetchSearchAnalytics($userId, $propertyId, $date1, $date2, ['page'], 25);
                if (!empty($pRes['ok'])) {
                    $base['pages'] = $this->mapDimensionRows(
                        is_array($pRes['rows'] ?? null) ? $pRes['rows'] : [],
                        'page'
                    );
                    $fromApi = true;
                } else {
                    $apiErrors[] = (string) ($pRes['message'] ?? __('Could not load GSC search analytics'));
                }
            }
        }

        $hasImport = $this->importHasSearchData($import);
        $hasData = $this->hasRenderableData($base);

        if (!$hasData && !$fromApi) {
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
                'message' => __('No GSC data for period'),
            ];
        }

        if ($apiErrors) {
            $base['note'] = implode('; ', array_unique($apiErrors));
        }

        $source = $fromApi && $hasImport ? 'api+import' : ($fromApi ? 'api' : 'import');

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
    private function hasRenderableData(array $base): bool
    {
        $kpis = is_array($base['kpis'] ?? null) ? $base['kpis'] : [];
        foreach (['clicks', 'impressions', 'ctr', 'position'] as $key) {
            if (isset($kpis[$key]) && $kpis[$key] !== null && $kpis[$key] !== '') {
                return true;
            }
        }

        return !empty($base['queries']) || !empty($base['pages']);
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{clicks:?float,impressions:?float,ctr:?float,position:?float}
     */
    private function aggregateKpis(array $rows): array
    {
        $clicks = 0.0;
        $impressions = 0.0;
        $ctrSum = 0.0;
        $posSum = 0.0;
        $weight = 0.0;
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $c = (float) ($row['clicks'] ?? 0);
            $i = (float) ($row['impressions'] ?? 0);
            $clicks += $c;
            $impressions += $i;
            $w = $i > 0 ? $i : 1.0;
            $ctrSum += (float) ($row['ctr'] ?? 0) * $w;
            $posSum += (float) ($row['position'] ?? 0) * $w;
            $weight += $w;
        }

        if ($rows === []) {
            return [
                'clicks' => 0,
                'impressions' => 0,
                'ctr' => 0,
                'position' => null,
            ];
        }

        return [
            'clicks' => (int) round($clicks),
            'impressions' => (int) round($impressions),
            'ctr' => $weight > 0 ? round(($ctrSum / $weight) * 100, 2) : 0,
            'position' => $weight > 0 ? round($posSum / $weight, 1) : null,
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array{name:string,clicks:int,impressions:int,ctr:?float,position:?float}>
     */
    private function mapDimensionRows(array $rows, string $dimension): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $keys = is_array($row['keys'] ?? null) ? $row['keys'] : [];
            $name = trim((string) ($keys[0] ?? ''));
            if ($name === '') {
                continue;
            }
            $ctr = isset($row['ctr']) ? round((float) $row['ctr'] * 100, 2) : null;
            $out[] = [
                'name' => $name,
                'clicks' => (int) round((float) ($row['clicks'] ?? 0)),
                'impressions' => (int) round((float) ($row['impressions'] ?? 0)),
                'ctr' => $ctr,
                'position' => isset($row['position']) ? round((float) $row['position'], 1) : null,
                'dimension' => $dimension,
            ];
        }

        return $out;
    }
}
