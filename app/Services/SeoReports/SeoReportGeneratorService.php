<?php

namespace App\Services\SeoReports;

use App\MonitoringProject;
use App\SeoReports\SeoReport;
use App\SeoReports\SeoReportKpiGoals;
use App\SeoReports\SeoReportProject;
use App\SeoReports\SeoReportSectionRegistry;
use App\Services\YandexMetrika\YandexMetrikaService;
use Carbon\Carbon;
use Throwable;

class SeoReportGeneratorService
{
    private const ORGANIC_FILTER = "ym:s:lastTrafficSource=='organic'";
    private const AD_FILTER = "ym:s:lastTrafficSource=='ad'";
    private const SOCIAL_FILTER = "ym:s:lastTrafficSource=='social'";

    /** @var YandexMetrikaService */
    private $metrika;

    /** @var SeoReportPositionsCollector */
    private $positionsCollector;

    /** @var SeoReportInsightsBuilder */
    private $insights;

    /** @var SeoReportTitloModulesCollector */
    private $titloModules;

    /** @var SeoReportExternalAdsCollector */
    private $externalAds;

    /** @var SeoReportWebmasterCollector */
    private $webmasterCollector;

    /** @var SeoReportGscCollector */
    private $gscCollector;

    public function __construct(
        YandexMetrikaService $metrika,
        SeoReportPositionsCollector $positionsCollector,
        SeoReportInsightsBuilder $insights,
        SeoReportTitloModulesCollector $titloModules,
        SeoReportExternalAdsCollector $externalAds,
        SeoReportWebmasterCollector $webmasterCollector,
        SeoReportGscCollector $gscCollector
    ) {
        $this->metrika = $metrika;
        $this->positionsCollector = $positionsCollector;
        $this->insights = $insights;
        $this->titloModules = $titloModules;
        $this->externalAds = $externalAds;
        $this->webmasterCollector = $webmasterCollector;
        $this->gscCollector = $gscCollector;
    }

    /**
     * @return array{ok:bool,message?:string,report?:SeoReport}
     */
    public function generate(SeoReport $report): array
    {
        $project = $report->project;
        if (!$project) {
            return ['ok' => false, 'message' => __('Project not found')];
        }

        $report->status = SeoReport::STATUS_GENERATING;
        $report->fail_reason = null;
        $report->save();

        try {
            $toggles = $project->resolvedSectionToggles();
            $settings = $project->reportSettings();
            $trafficScope = \App\SeoReports\SeoReportTrafficScope::normalize($settings);
            $trafficFilter = \App\SeoReports\SeoReportTrafficScope::metrikaFilter($trafficScope);
            $searchOnly = $trafficScope['mode'] === \App\SeoReports\SeoReportTrafficScope::MODE_SEARCH;

            $sectionStates = [];
            $snapshot = [
                'generated_at' => Carbon::now()->toIso8601String(),
                'quality' => 'partial',
                'cover' => $this->buildCover($project, $report),
                'traffic' => null,
                'positions' => null,
                'conversions' => null,
                'titlo_audit' => null,
                'titlo_checklist' => null,
                'titlo_relevance' => null,
                'titlo_uptime' => null,
                'work_facts' => [],
                'work_done' => null,
                'work_plan' => null,
                'scorecard' => [],
                'insights' => [],
                'progress' => [],
                'audit_log' => [],
            ];

            $sourcesOk = 0;
            $sourcesTried = 0;

            $catalog = SeoReportSectionRegistry::all();
            foreach (SeoReportSectionRegistry::orderedKeys($settings) as $key) {
                $meta = $catalog[$key] ?? null;
                if (!$meta) {
                    continue;
                }
                $enabled = !empty($toggles[$key]);
                $source = (string) $meta['source'];
                $status = SeoReportSectionRegistry::SOURCE_STATUS_NOT_CONNECTED;

                if (!$enabled) {
                    $sectionStates[$key] = [
                        'enabled' => false,
                        'source_status' => $status,
                    ];
                    continue;
                }

                if ($source === 'manual' || $source === 'computed') {
                    $sectionStates[$key] = [
                        'enabled' => true,
                        'source_status' => SeoReportSectionRegistry::SOURCE_STATUS_MANUAL,
                    ];
                    continue;
                }

                if ($key === 'traffic') {
                    $sourcesTried++;
                    $traffic = $this->collectTraffic($project, $report, $trafficFilter, $trafficScope);
                    $snapshot['progress']['metrika'] = $traffic['progress'];
                    if ($traffic['ok']) {
                        $sourcesOk++;
                        $status = SeoReportSectionRegistry::SOURCE_STATUS_OK;
                        $snapshot['traffic'] = $traffic['data'];
                    } else {
                        $status = $traffic['status'];
                    }
                    $sectionStates[$key] = [
                        'enabled' => true,
                        'source_status' => $status,
                        'message' => $traffic['message'] ?? null,
                    ];
                    continue;
                }

                if ($key === 'positions') {
                    $sourcesTried++;
                    $positions = $this->collectPositions($project, $report);
                    $snapshot['progress']['monitoring'] = $positions['progress'];
                    if ($positions['ok']) {
                        $sourcesOk++;
                        $status = SeoReportSectionRegistry::SOURCE_STATUS_OK;
                        $snapshot['positions'] = $positions['data'];
                    } else {
                        $status = $positions['status'];
                    }
                    $sectionStates[$key] = [
                        'enabled' => true,
                        'source_status' => $status,
                        'message' => $positions['message'] ?? null,
                    ];
                    continue;
                }

                if ($key === 'conversions') {
                    $sourcesTried++;
                    $conversions = $this->collectConversions($project, $report, $searchOnly);
                    $snapshot['progress']['conversions'] = $conversions['progress'];
                    if ($conversions['ok']) {
                        $sourcesOk++;
                        $status = SeoReportSectionRegistry::SOURCE_STATUS_OK;
                        $snapshot['conversions'] = $conversions['data'];
                    } else {
                        $status = $conversions['status'];
                    }
                    $sectionStates[$key] = [
                        'enabled' => true,
                        'source_status' => $status,
                        'message' => $conversions['message'] ?? null,
                    ];
                    continue;
                }

                if ($key === 'ecommerce') {
                    $sourcesTried++;
                    $ecomResult = $this->collectEcommerceSection($project, $report, $searchOnly);
                    $snapshot['progress']['ecommerce'] = $ecomResult['progress'];
                    if ($ecomResult['ok']) {
                        $sourcesOk++;
                        $snapshot['ecommerce'] = $ecomResult['data'];
                        if (is_array($snapshot['traffic'] ?? null)) {
                            $snapshot['traffic']['ecommerce'] = $ecomResult['data'];
                        }
                    }
                    $sectionStates[$key] = [
                        'enabled' => true,
                        'source_status' => $ecomResult['status'],
                        'message' => $ecomResult['message'] ?? null,
                    ];
                    continue;
                }

                if ($key === 'direct') {
                    $sourcesTried++;
                    $direct = $this->collectDirectFromMetrika($project, $report);
                    $snapshot['progress']['direct'] = $direct['progress'];
                    if ($direct['ok']) {
                        $sourcesOk++;
                        $snapshot['direct'] = $direct['data'];
                    }
                    $sectionStates[$key] = [
                        'enabled' => true,
                        'source_status' => $direct['status'],
                        'message' => $direct['message'] ?? null,
                    ];
                    continue;
                }

                if ($key === 'google_ads') {
                    $sourcesTried++;
                    $gads = $this->collectGoogleAdsFromMetrika($project, $report);
                    $snapshot['progress']['google_ads'] = $gads['progress'];
                    if ($gads['ok']) {
                        $sourcesOk++;
                        $snapshot['google_ads'] = $gads['data'];
                    }
                    $sectionStates[$key] = [
                        'enabled' => true,
                        'source_status' => $gads['status'],
                        'message' => $gads['message'] ?? null,
                    ];
                    continue;
                }

                if ($key === 'calls') {
                    $sourcesTried++;
                    $calls = $this->collectCallsFromMetrika($project, $report);
                    $snapshot['progress']['calls'] = $calls['progress'];
                    if ($calls['ok']) {
                        $sourcesOk++;
                        $snapshot['calls'] = $calls['data'];
                    }
                    $sectionStates[$key] = [
                        'enabled' => true,
                        'source_status' => $calls['status'],
                        'message' => $calls['message'] ?? null,
                    ];
                    continue;
                }

                if (in_array($key, ['vk_ads', 'vk_smm'], true)) {
                    $sourcesTried++;
                    $ext = $this->externalAds->collect($key, $project, $report);
                    $snapshot['progress'][$key] = $ext['progress'];
                    if ($ext['ok']) {
                        $sourcesOk++;
                        $snapshot[$key] = $ext['data'];
                    }
                    $sectionStates[$key] = [
                        'enabled' => true,
                        'source_status' => $ext['status'],
                        'message' => $ext['message'] ?? null,
                    ];
                    continue;
                }

                if (in_array($key, ['titlo_audit', 'titlo_checklist', 'titlo_relevance', 'titlo_uptime'], true)) {
                    $sourcesTried++;
                    $titlo = $this->collectTitloSection($key, $project, $report);
                    $snapshot['progress'][$key] = $titlo['progress'];
                    if ($titlo['ok']) {
                        $sourcesOk++;
                        $status = SeoReportSectionRegistry::SOURCE_STATUS_OK;
                        $snapshot[$key] = $titlo['data'];
                        if (!empty($titlo['fact'])) {
                            $snapshot['work_facts'][] = $titlo['fact'];
                        }
                    } else {
                        $status = $titlo['status'];
                    }
                    $sectionStates[$key] = [
                        'enabled' => true,
                        'source_status' => $status,
                        'message' => $titlo['message'] ?? null,
                    ];
                    continue;
                }

                if ($source === 'metrika') {
                    $sectionStates[$key] = [
                        'enabled' => true,
                        'source_status' => $project->metrika_counter_id
                            ? SeoReportSectionRegistry::SOURCE_STATUS_OK
                            : SeoReportSectionRegistry::SOURCE_STATUS_NOT_CONNECTED,
                    ];
                    continue;
                }

                if ($source === 'monitoring') {
                    $sectionStates[$key] = [
                        'enabled' => true,
                        'source_status' => $project->monitoring_project_id
                            ? SeoReportSectionRegistry::SOURCE_STATUS_OK
                            : SeoReportSectionRegistry::SOURCE_STATUS_NOT_CONNECTED,
                    ];
                    continue;
                }

                if ($source === 'gsc') {
                    $sourcesTried++;
                    $gsc = $this->gscCollector->collect($project, $report);
                    $snapshot['progress']['gsc'] = $gsc['progress'];
                    if (!empty($gsc['ok']) && is_array($gsc['data'] ?? null)) {
                        $sourcesOk++;
                        $snapshot[$key] = $gsc['data'];
                        $sectionStates[$key] = [
                            'enabled' => true,
                            'source_status' => $gsc['status'],
                            'message' => $gsc['message'] ?? null,
                        ];
                    } else {
                        $sectionStates[$key] = [
                            'enabled' => true,
                            'source_status' => $gsc['status'] ?? SeoReportSectionRegistry::SOURCE_STATUS_NOT_CONNECTED,
                            'message' => $gsc['message'] ?? __('Google Search Console is not connected'),
                        ];
                    }
                    continue;
                }

                if ($source === 'webmaster') {
                    $sourcesTried++;
                    $wm = $this->webmasterCollector->collect($project, $report);
                    $snapshot['progress']['webmaster'] = $wm['progress'];
                    if (!empty($wm['ok']) && is_array($wm['data'] ?? null)) {
                        $sourcesOk++;
                        $snapshot[$key] = $wm['data'];
                        $sectionStates[$key] = [
                            'enabled' => true,
                            'source_status' => $wm['status'],
                            'message' => $wm['message'] ?? null,
                        ];
                    } else {
                        $sectionStates[$key] = [
                            'enabled' => true,
                            'source_status' => $wm['status'] ?? SeoReportSectionRegistry::SOURCE_STATUS_NOT_CONNECTED,
                            'message' => $wm['message'] ?? __('Yandex Webmaster is not connected'),
                        ];
                    }
                    continue;
                }

                $sectionStates[$key] = [
                    'enabled' => true,
                    'source_status' => SeoReportSectionRegistry::SOURCE_STATUS_NOT_CONNECTED,
                    'message' => $this->integrationMissingMessage($key, $source),
                ];
            }

            if ($sourcesTried > 0 && $sourcesOk === $sourcesTried) {
                $snapshot['quality'] = 'full';
            } elseif ($sourcesOk > 0) {
                $snapshot['quality'] = 'partial';
            } else {
                $snapshot['quality'] = 'empty';
            }

            $qualityFlags = [];
            foreach ($sectionStates as $stateKey => $state) {
                if (empty($state['enabled'])) {
                    continue;
                }
                $st = (string) ($state['source_status'] ?? '');
                if (!in_array($st, [
                    SeoReportSectionRegistry::SOURCE_STATUS_ERROR,
                    SeoReportSectionRegistry::SOURCE_STATUS_EMPTY,
                    SeoReportSectionRegistry::SOURCE_STATUS_NOT_CONNECTED,
                ], true)) {
                    continue;
                }
                // Manual/computed never emit quality noise; only data sources.
                $meta = SeoReportSectionRegistry::all()[$stateKey] ?? null;
                $src = (string) ($meta['source'] ?? '');
                if (in_array($src, ['manual', 'computed'], true)) {
                    continue;
                }
                $qualityFlags[] = [
                    'section' => $stateKey,
                    'title' => $meta['title'] ?? $stateKey,
                    'status' => $st,
                    'message' => $state['message'] ?? null,
                ];
            }
            $snapshot['data_quality'] = [
                'level' => $snapshot['quality'],
                'flags' => $qualityFlags,
                'generated_at' => Carbon::now()->toIso8601String(),
            ];

            $snapshot['insights'] = $this->insights->bullets($snapshot);
            $snapshot['anomalies'] = $this->insights->anomalies($snapshot);
            $snapshot['recommendations'] = $this->insights->recommendations($snapshot);
            $snapshot['scorecard'] = $this->buildScorecard($snapshot);
            $settings = $project->reportSettings();
            $snapshot['kpi_goals'] = SeoReportKpiGoals::evaluate(
                SeoReportKpiGoals::fromSettings($settings),
                $snapshot
            );
            $trafficComment = $this->insights->trafficComment($snapshot);
            if ($trafficComment && is_array($snapshot['traffic'])) {
                $snapshot['traffic']['auto_comment'] = $trafficComment;
            }

            if (!empty($settings['enable_ai_summary'])) {
                $snapshot['ai_summary'] = $this->insights->aiNarrative($snapshot);
            }

            $snapshot['cover']['compare_baseline'] = [
                'label' => $snapshot['cover']['compare_label'] ?? null,
                'reason' => ($report->compare_from && $report->compare_to)
                    ? __('Previous equal-length period')
                    : null,
            ];
            $snapshot['cover']['data_as_of'] = Carbon::now()->toIso8601String();
            $snapshot['sources_as_of'] = [
                'metrika' => !empty($snapshot['traffic']) ? Carbon::now()->toIso8601String() : null,
                'monitoring' => $snapshot['positions']['data_as_of'] ?? null,
                'conversions' => !empty($snapshot['conversions']) ? Carbon::now()->toIso8601String() : null,
            ];

            if (trim((string) $report->summary_text) === '') {
                if (!empty($snapshot['ai_summary'])) {
                    $report->summary_text = (string) $snapshot['ai_summary'];
                } elseif ($snapshot['insights'] !== []) {
                    $report->summary_text = implode("\n", array_map(static function ($b) {
                        return '• ' . $b;
                    }, $snapshot['insights']));
                }
            }

            // «Выполненные работы» / «План работ»: задачи из SEO-чеклиста + ручной текст менеджера (в UI отдельно).
            if (!empty($toggles['work_done']) || !empty($toggles['work_plan'])) {
                $workFromChecklist = $this->titloModules->collectWorkFromChecklist(
                    (int) $project->user_id,
                    (string) $project->domain,
                    $report->period_from,
                    $report->period_to
                );
                if (!empty($workFromChecklist['ok']) && is_array($workFromChecklist['data'] ?? null)) {
                    $wd = $workFromChecklist['data'];
                    $meta = [
                        'checklist_project_id' => (int) ($wd['project_id'] ?? 0),
                        'open_url' => (string) ($wd['open_url'] ?? ''),
                    ];
                    if (!empty($toggles['work_done'])) {
                        $snapshot['work_done'] = $meta + [
                            'from_checklist' => is_array($wd['closed'] ?? null) ? $wd['closed'] : [],
                        ];
                    }
                    if (!empty($toggles['work_plan'])) {
                        $snapshot['work_plan'] = $meta + [
                            'from_checklist' => is_array($wd['plan'] ?? null) ? $wd['plan'] : [],
                        ];
                    }
                }
            }

            $comments = is_array($report->comments_json) ? $report->comments_json : [];
            if (trim((string) ($comments['recommendations'] ?? '')) === '' && !empty($snapshot['recommendations'])) {
                $comments['recommendations'] = implode("\n", array_map(static function ($r) {
                    return ($r['priority'] ?? 'P3') . ': ' . ($r['text'] ?? '');
                }, $snapshot['recommendations']));
                $report->comments_json = $comments;
            }

            // Сразу доступен по публичной ссылке; «Опубликовать» остаётся для явного подтверждения при необходимости.
            $snapshot['requires_publish'] = false;
            if (empty($snapshot['published_at'])) {
                $snapshot['published_at'] = Carbon::now()->toIso8601String();
            }

            $snapshot['audit_log'][] = [
                'at' => Carbon::now()->toIso8601String(),
                'action' => 'generated',
                'user_id' => (int) $report->user_id,
            ];

            $report->snapshot_json = $snapshot;
            $report->section_states = $sectionStates;
            $report->status = SeoReport::STATUS_READY;
            $report->generated_at = Carbon::now();
            $report->ensurePublicToken();
            $report->save();

            $project->touch();

            return ['ok' => true, 'report' => $report];
        } catch (Throwable $e) {
            $report->status = SeoReport::STATUS_FAILED;
            $report->fail_reason = mb_substr($e->getMessage(), 0, 240);
            $report->save();

            return ['ok' => false, 'message' => $report->fail_reason, 'report' => $report];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCover(SeoReportProject $project, SeoReport $report): array
    {
        $periodLabel = optional($report->period_from)->format('d.m.Y')
            . ' — '
            . optional($report->period_to)->format('d.m.Y');

        return [
            'title' => trim((string) ($project->title ?: '')) !== ''
                ? (string) $project->title
                : ('SEO-отчёт · ' . $project->domain),
            'domain' => $project->domain,
            'period_label' => $periodLabel,
            'compare_label' => ($report->compare_from && $report->compare_to)
                ? (optional($report->compare_from)->format('d.m.Y') . ' — ' . optional($report->compare_to)->format('d.m.Y'))
                : null,
            'agency' => [
                'name' => $project->brandingAgencyName(),
                'address' => $project->brandingAgencyAddress(),
                'email' => $project->brandingAgencyEmail(),
                'phone' => $project->brandingAgencyPhone(),
                'logo_url' => $project->agencyLogoUrl(),
                'brand_color' => $project->brandingColor() ?: '#1d4ed8',
            ],
            'manager' => [
                'name' => $project->brandingManagerName(),
                'phone' => $project->brandingManagerPhone(),
                'email' => $project->brandingManagerEmail(),
                'avatar_url' => $project->managerAvatarUrl(),
            ],
        ];
    }

    /**
     * @param array{mode:string,channels:list<string>} $trafficScope
     * @return array{ok:bool,status:string,message?:string,progress:string,data?:array}
     */
    private function collectTraffic(
        SeoReportProject $project,
        SeoReport $report,
        ?string $filter,
        array $trafficScope
    ): array {
        $counterId = (int) $project->metrika_counter_id;
        $userId = (int) $project->user_id;
        if ($counterId < 1 || !$this->metrika->isConnected($userId)) {
            return [
                'ok' => false,
                'status' => SeoReportSectionRegistry::SOURCE_STATUS_NOT_CONNECTED,
                'message' => __('Yandex Metrika is not connected'),
                'progress' => 'skip',
            ];
        }

        $date1 = optional($report->period_from)->format('Y-m-d');
        $date2 = optional($report->period_to)->format('Y-m-d');
        if (!$date1 || !$date2) {
            return [
                'ok' => false,
                'status' => SeoReportSectionRegistry::SOURCE_STATUS_ERROR,
                'message' => __('Invalid period'),
                'progress' => 'error',
            ];
        }

        $current = $this->metrika->sessionTotalsForPeriodFiltered($userId, $counterId, $date1, $date2, $filter);
        if ($current === null) {
            return [
                'ok' => false,
                'status' => SeoReportSectionRegistry::SOURCE_STATUS_ERROR,
                'message' => __('Could not load Metrika stats'),
                'progress' => 'error',
            ];
        }

        $compare = null;
        if ($report->compare_from && $report->compare_to) {
            $compare = $this->metrika->sessionTotalsForPeriodFiltered(
                $userId,
                $counterId,
                $report->compare_from->format('Y-m-d'),
                $report->compare_to->format('Y-m-d'),
                $filter
            );
        }

        $kpis = $this->kpiBundle($current, $compare);
        if ($filter === null || $filter === '') {
            $series = $this->metrika->usersByDateForPeriod($userId, $counterId, $date1, $date2);
        } else {
            $seriesRows = $this->metrika->customDimensionRowsForPeriod(
                $userId, $counterId, $date1, $date2, 'ym:s:date',
                ['ym:s:users'], 40, $filter, 'ym:s:date'
            ) ?: [];
            $series = [];
            foreach ($seriesRows as $row) {
                $day = (string) ($row['name'] ?? '');
                if ($day !== '') {
                    $series[$day] = (int) round((float) (($row['metrics'][0] ?? 0)));
                }
            }
        }

        $channels = $this->metrika->dimensionRowsForPeriod(
            $userId, $counterId, $date1, $date2, 'ym:s:lastTrafficSource', 15, $filter
        ) ?: [];

        $sources = $this->metrika->dimensionRowsForPeriod(
            $userId, $counterId, $date1, $date2, 'ym:s:lastReferalSource', 15, $filter
        ) ?: [];

        $devices = $this->metrika->dimensionRowsForPeriod(
            $userId, $counterId, $date1, $date2, 'ym:s:deviceCategory', 10, $filter
        ) ?: [];

        $geo = $this->metrika->dimensionRowsForPeriod(
            $userId, $counterId, $date1, $date2, 'ym:s:regionCity', 15, $filter
        ) ?: [];

        $landings = $this->metrika->dimensionRowsForPeriod(
            $userId, $counterId, $date1, $date2, 'ym:s:startURLPathFull', 20, $filter
        ) ?: [];

        $landingsSearch = $this->metrika->dimensionRowsForPeriod(
            $userId, $counterId, $date1, $date2, 'ym:s:startURLPathFull', 15, self::ORGANIC_FILTER
        ) ?: [];

        // Search engines + search KPIs always organic-scoped
        $searchEngines = $this->metrika->dimensionRowsForPeriod(
            $userId, $counterId, $date1, $date2, 'ym:s:lastSearchEngineRoot', 10, self::ORGANIC_FILTER
        ) ?: [];
        if ($report->compare_from && $report->compare_to && $searchEngines !== []) {
            $prevEngines = $this->metrika->dimensionRowsForPeriod(
                $userId,
                $counterId,
                $report->compare_from->format('Y-m-d'),
                $report->compare_to->format('Y-m-d'),
                'ym:s:lastSearchEngineRoot',
                20,
                self::ORGANIC_FILTER
            ) ?: [];
            $prevMap = [];
            foreach ($prevEngines as $row) {
                $prevMap[(string) $row['name']] = (float) ($row['visits'] ?? 0);
            }
            foreach ($searchEngines as &$row) {
                $prev = $prevMap[(string) ($row['name'] ?? '')] ?? 0.0;
                $row['visits_prev'] = $prev;
                $row['visits_delta_pct'] = $this->deltaPct($row['visits'] ?? null, $prev);
            }
            unset($row);
        }
        $searchCurrent = $this->metrika->sessionTotalsForPeriodFiltered(
            $userId, $counterId, $date1, $date2, self::ORGANIC_FILTER
        );
        $searchCompare = null;
        if ($report->compare_from && $report->compare_to) {
            $searchCompare = $this->metrika->sessionTotalsForPeriodFiltered(
                $userId,
                $counterId,
                $report->compare_from->format('Y-m-d'),
                $report->compare_to->format('Y-m-d'),
                self::ORGANIC_FILTER
            );
        }
        $searchSeries = $this->metrika->visitsByDateForPeriodFiltered(
            $userId, $counterId, $date1, $date2, self::ORGANIC_FILTER
        ) ?: [];

        // Channel monthly dynamics (last 6 months) — best-effort
        $channelMonths = $this->channelMonths($userId, $counterId, $date2);

        // Landing compare deltas + URL normalize
        $landings = $this->normalizeLandingRows($this->attachVisitDeltas(
            $landings,
            $userId,
            $counterId,
            $report,
            $filter
        ), (string) $project->domain);

        $landingsSearch = $this->normalizeLandingRows($landingsSearch, (string) $project->domain);

        $landingsSocial = $this->normalizeLandingRows(
            $this->metrika->dimensionRowsForPeriod(
                $userId, $counterId, $date1, $date2, 'ym:s:startURLPathFull', 15, self::SOCIAL_FILTER
            ) ?: [],
            (string) $project->domain
        );
        $socialKpis = $this->metrika->sessionTotalsForPeriodFiltered(
            $userId, $counterId, $date1, $date2, self::SOCIAL_FILTER
        );

        return [
            'ok' => true,
            'status' => SeoReportSectionRegistry::SOURCE_STATUS_OK,
            'progress' => 'ok',
            'data' => [
                'counter_id' => $counterId,
                'mode' => $trafficScope['mode'] ?? 'all',
                'channels_scope' => $trafficScope['channels'] ?? [],
                'scope_label' => \App\SeoReports\SeoReportTrafficScope::label($trafficScope),
                'kpis' => $kpis,
                'series_users' => $series ?: [],
                'channels' => $channels,
                'sources' => $sources,
                'devices' => $devices,
                'geo' => $geo,
                'landings' => $landings,
                'landings_search' => $landingsSearch,
                'landings_social' => $landingsSocial,
                'social' => [
                    'kpis' => $this->kpiBundle($socialKpis ?: [], null),
                ],
                'channel_months' => $channelMonths,
                'search' => [
                    'engines' => $searchEngines,
                    'kpis' => $this->kpiBundle($searchCurrent ?: [], $searchCompare),
                    'series_visits' => $searchSeries,
                ],
            ],
        ];
    }

    /**
     * @return array{ok:bool,status:string,message?:string,progress:string,data?:array}
     */
    private function collectEcommerceSection(SeoReportProject $project, SeoReport $report, bool $searchOnly): array
    {
        $counterId = (int) $project->metrika_counter_id;
        $userId = (int) $project->user_id;
        if ($counterId < 1 || !$this->metrika->isConnected($userId)) {
            return [
                'ok' => false,
                'status' => SeoReportSectionRegistry::SOURCE_STATUS_NOT_CONNECTED,
                'message' => __('Yandex Metrika is not connected'),
                'progress' => 'skip',
            ];
        }
        $date1 = optional($report->period_from)->format('Y-m-d');
        $date2 = optional($report->period_to)->format('Y-m-d');
        if (!$date1 || !$date2) {
            return [
                'ok' => false,
                'status' => SeoReportSectionRegistry::SOURCE_STATUS_ERROR,
                'message' => __('Invalid period'),
                'progress' => 'error',
            ];
        }

        $filter = $searchOnly ? self::ORGANIC_FILTER : null;
        $metrics = [
            'ym:s:users',
            'ym:s:ecommercePurchases',
            'ym:s:ecommerceRevenue',
        ];
        $totals = $this->metrika->customTotalsForPeriod($userId, $counterId, $date1, $date2, $metrics, $filter);
        if ($totals === null) {
            return [
                'ok' => false,
                'status' => SeoReportSectionRegistry::SOURCE_STATUS_EMPTY,
                'message' => __('Ecommerce metrics require Metrika ecommerce tracking'),
                'progress' => 'empty',
                'data' => [
                    'available' => false,
                    'note' => __('Ecommerce metrics require Metrika ecommerce tracking'),
                ],
            ];
        }

        $users = (float) ($totals[0] ?? 0);
        $purchases = (float) ($totals[1] ?? 0);
        $revenue = (float) ($totals[2] ?? 0);
        if ($purchases <= 0 && $revenue <= 0) {
            return [
                'ok' => false,
                'status' => SeoReportSectionRegistry::SOURCE_STATUS_EMPTY,
                'message' => __('No ecommerce data for period'),
                'progress' => 'empty',
                'data' => [
                    'available' => false,
                    'note' => __('No ecommerce data for period'),
                    'users' => $users,
                ],
            ];
        }

        $bySource = $this->metrika->customDimensionRowsForPeriod(
            $userId, $counterId, $date1, $date2, 'ym:s:lastTrafficSource', $metrics, 12, $filter
        ) ?: [];
        $products = $this->metrika->customDimensionRowsForPeriod(
            $userId, $counterId, $date1, $date2, 'ym:s:productName',
            ['ym:s:ecommercePurchases', 'ym:s:ecommerceRevenue'], 12, $filter
        ) ?: [];
        $categories = $this->metrika->customDimensionRowsForPeriod(
            $userId, $counterId, $date1, $date2, 'ym:s:productCategory',
            ['ym:s:ecommercePurchases', 'ym:s:ecommerceRevenue'], 12, $filter
        ) ?: [];

        $mapDim = static function (array $rows, bool $withUsers = false): array {
            $out = [];
            foreach ($rows as $row) {
                $m = $row['metrics'] ?? [];
                if ($withUsers) {
                    $out[] = [
                        'name' => $row['name'] ?? '—',
                        'users' => (float) ($m[0] ?? 0),
                        'purchases' => (float) ($m[1] ?? 0),
                        'revenue' => (float) ($m[2] ?? 0),
                    ];
                } else {
                    $out[] = [
                        'name' => $row['name'] ?? '—',
                        'purchases' => (float) ($m[0] ?? 0),
                        'revenue' => (float) ($m[1] ?? 0),
                    ];
                }
            }

            return $out;
        };

        return [
            'ok' => true,
            'status' => SeoReportSectionRegistry::SOURCE_STATUS_OK,
            'progress' => 'ok',
            'data' => [
                'available' => true,
                'users' => $users,
                'purchases' => $purchases,
                'revenue' => $revenue,
                'cr' => $users > 0 ? round($purchases / $users * 100, 2) : 0.0,
                'rpv' => $users > 0 ? round($revenue / $users, 2) : 0.0,
                'aov' => $purchases > 0 ? round($revenue / $purchases, 2) : 0.0,
                'by_source' => $mapDim($bySource, true),
                'products' => $mapDim($products),
                'categories' => $mapDim($categories),
            ],
        ];
    }

    /**
     * Рекламный трафик из Метрики (без OAuth Директа): поведение + посадочные + цели.
     *
     * @return array{ok:bool,status:string,message?:string,progress:string,data?:array}
     */
    private function collectDirectFromMetrika(SeoReportProject $project, SeoReport $report): array
    {
        $counterId = (int) $project->metrika_counter_id;
        $userId = (int) $project->user_id;
        if ($counterId < 1 || !$this->metrika->isConnected($userId)) {
            return [
                'ok' => false,
                'status' => SeoReportSectionRegistry::SOURCE_STATUS_NOT_CONNECTED,
                'message' => __('Yandex Direct is not connected — open settings after OAuth is available'),
                'progress' => 'skip',
            ];
        }
        $date1 = optional($report->period_from)->format('Y-m-d');
        $date2 = optional($report->period_to)->format('Y-m-d');
        if (!$date1 || !$date2) {
            return [
                'ok' => false,
                'status' => SeoReportSectionRegistry::SOURCE_STATUS_ERROR,
                'message' => __('Invalid period'),
                'progress' => 'error',
            ];
        }

        $current = $this->metrika->sessionTotalsForPeriodFiltered(
            $userId, $counterId, $date1, $date2, self::AD_FILTER
        );
        if ($current === null) {
            return [
                'ok' => false,
                'status' => SeoReportSectionRegistry::SOURCE_STATUS_ERROR,
                'message' => __('Could not load Metrika stats'),
                'progress' => 'error',
            ];
        }
        if ((float) ($current['visits'] ?? 0) <= 0) {
            return [
                'ok' => false,
                'status' => SeoReportSectionRegistry::SOURCE_STATUS_EMPTY,
                'message' => __('No ad traffic in Metrika for period'),
                'progress' => 'empty',
            ];
        }

        $compare = null;
        if ($report->compare_from && $report->compare_to) {
            $compare = $this->metrika->sessionTotalsForPeriodFiltered(
                $userId,
                $counterId,
                $report->compare_from->format('Y-m-d'),
                $report->compare_to->format('Y-m-d'),
                self::AD_FILTER
            );
        }

        $engines = $this->metrika->dimensionRowsForPeriod(
            $userId, $counterId, $date1, $date2, 'ym:s:lastAdvEngine', 15, self::AD_FILTER
        ) ?: [];
        $landings = $this->normalizeLandingRows(
            $this->metrika->dimensionRowsForPeriod(
                $userId, $counterId, $date1, $date2, 'ym:s:startURLPathFull', 15, self::AD_FILTER
            ) ?: [],
            (string) $project->domain
        );
        $campaigns = $this->metrika->dimensionRowsForPeriod(
            $userId, $counterId, $date1, $date2, 'ym:s:lastDirectClickOrder', 20, self::AD_FILTER
        ) ?: [];
        $platforms = $this->metrika->dimensionRowsForPeriod(
            $userId, $counterId, $date1, $date2, 'ym:s:lastDirectPlatformType', 10, self::AD_FILTER
        ) ?: [];
        $phrases = $this->metrika->dimensionRowsForPeriod(
            $userId, $counterId, $date1, $date2, 'ym:s:lastSearchPhrase', 20, self::AD_FILTER
        ) ?: [];
        $series = $this->metrika->customDimensionRowsForPeriod(
            $userId, $counterId, $date1, $date2, 'ym:s:date',
            ['ym:s:visits', 'ym:s:bounceRate'], 40, self::AD_FILTER, 'ym:s:date'
        ) ?: [];
        $seriesVisits = [];
        foreach ($series as $row) {
            $seriesVisits[(string) ($row['name'] ?? '')] = (float) (($row['metrics'][0] ?? 0));
        }

        $costTotals = $this->metrika->customTotalsForPeriod(
            $userId, $counterId, $date1, $date2,
            ['ym:s:RUBAdCost', 'ym:s:clicks'],
            self::AD_FILTER
        );
        $adCost = is_array($costTotals) ? (float) ($costTotals[0] ?? 0) : null;
        $adClicks = is_array($costTotals) ? (float) ($costTotals[1] ?? 0) : null;
        $visits = (float) ($current['visits'] ?? 0);
        $spendKpis = [
            'cost' => $adCost !== null && $adCost > 0 ? $adCost : null,
            'clicks' => $adClicks !== null && $adClicks > 0 ? $adClicks : null,
            'cpc' => ($adCost !== null && $adClicks !== null && $adClicks > 0)
                ? round($adCost / $adClicks, 2) : null,
            'ctr' => ($adClicks !== null && $visits > 0)
                ? round($adClicks / max($visits, 1) * 100, 2) : null,
        ];

        $settings = $project->reportSettings();
        $goalIds = isset($settings['metrika_goal_ids']) && is_array($settings['metrika_goal_ids'])
            ? array_values(array_filter(array_map('intval', $settings['metrika_goal_ids'])))
            : [];
        $goalConversions = [];
        if ($goalIds !== []) {
            $goalNames = [];
            foreach ($this->metrika->listGoals($userId, $counterId) ?: [] as $g) {
                $goalNames[(int) $g['id']] = (string) $g['name'];
            }
            $goalTotals = $this->metrika->goalTotalsForPeriod(
                $userId, $counterId, $date1, $date2, $goalIds, self::AD_FILTER
            ) ?: [];
            foreach ($goalIds as $goalId) {
                $cur = $goalTotals[$goalId] ?? ['reaches' => 0.0, 'conversion_rate' => 0.0];
                $goalConversions[] = [
                    'id' => $goalId,
                    'name' => $goalNames[$goalId] ?? ('Goal #' . $goalId),
                    'reaches' => $cur['reaches'],
                    'conversion_rate' => $cur['conversion_rate'],
                ];
            }
        }

        return [
            'ok' => true,
            'status' => SeoReportSectionRegistry::SOURCE_STATUS_OK,
            'progress' => 'ok',
            'data' => [
                'source' => 'metrika_ad',
                'note' => __('Ad traffic from Metrika (Direct OAuth later for spend/CTR)'),
                'kpis' => $this->kpiBundle($current, $compare),
                'spend' => $spendKpis,
                'series_visits' => $seriesVisits,
                'engines' => $engines,
                'campaigns' => $campaigns,
                'platforms' => $platforms,
                'phrases' => $phrases,
                'landings' => $landings,
                'conversions' => $goalConversions,
                'fix' => $this->adsFixHints($current, $goalConversions),
            ],
        ];
    }

    /**
     * Google Ads lite из Метрики (lastAdvEngine содержит google).
     *
     * @return array{ok:bool,status:string,message?:string,progress:string,data?:array}
     */
    private function collectGoogleAdsFromMetrika(SeoReportProject $project, SeoReport $report): array
    {
        $counterId = (int) $project->metrika_counter_id;
        $userId = (int) $project->user_id;
        if ($counterId < 1 || !$this->metrika->isConnected($userId)) {
            return [
                'ok' => false,
                'status' => SeoReportSectionRegistry::SOURCE_STATUS_NOT_CONNECTED,
                'message' => __('Google Ads is not connected — cabinet OAuth will be added later'),
                'progress' => 'skip',
            ];
        }
        $date1 = optional($report->period_from)->format('Y-m-d');
        $date2 = optional($report->period_to)->format('Y-m-d');
        if (!$date1 || !$date2) {
            return [
                'ok' => false,
                'status' => SeoReportSectionRegistry::SOURCE_STATUS_ERROR,
                'message' => __('Invalid period'),
                'progress' => 'error',
            ];
        }

        $filter = "ym:s:lastTrafficSource=='ad' AND ym:s:lastAdvEngine=@'google'";
        $current = $this->metrika->sessionTotalsForPeriodFiltered(
            $userId, $counterId, $date1, $date2, $filter
        );
        if ($current === null) {
            return [
                'ok' => false,
                'status' => SeoReportSectionRegistry::SOURCE_STATUS_ERROR,
                'message' => __('Could not load Metrika stats'),
                'progress' => 'error',
            ];
        }
        if ((float) ($current['visits'] ?? 0) <= 0) {
            return [
                'ok' => false,
                'status' => SeoReportSectionRegistry::SOURCE_STATUS_EMPTY,
                'message' => __('No Google Ads traffic in Metrika for period'),
                'progress' => 'empty',
            ];
        }

        $landings = $this->normalizeLandingRows(
            $this->metrika->dimensionRowsForPeriod(
                $userId, $counterId, $date1, $date2, 'ym:s:startURLPathFull', 15, $filter
            ) ?: [],
            (string) $project->domain
        );
        $phrases = $this->metrika->dimensionRowsForPeriod(
            $userId, $counterId, $date1, $date2, 'ym:s:lastSearchPhrase', 15, $filter
        ) ?: [];
        $campaigns = $this->metrika->dimensionRowsForPeriod(
            $userId, $counterId, $date1, $date2, 'ym:s:lastUTMCampaign', 25, $filter
        ) ?: [];
        if ($campaigns === []) {
            $campaigns = $this->metrika->dimensionRowsForPeriod(
                $userId, $counterId, $date1, $date2, 'ym:s:lastDirectClickOrder', 25, $filter
            ) ?: [];
        }
        $campaigns = array_values(array_filter($campaigns, static function ($row) {
            $name = trim((string) ($row['name'] ?? ''));

            return $name !== '' && $name !== '-' && mb_strtolower($name) !== 'undefined';
        }));

        $settings = $project->reportSettings();
        $goalIds = isset($settings['metrika_goal_ids']) && is_array($settings['metrika_goal_ids'])
            ? array_values(array_filter(array_map('intval', $settings['metrika_goal_ids'])))
            : [];
        $goalConversions = [];
        if ($goalIds !== []) {
            $goalNames = [];
            foreach ($this->metrika->listGoals($userId, $counterId) ?: [] as $g) {
                $goalNames[(int) $g['id']] = (string) $g['name'];
            }
            $goalTotals = $this->metrika->goalTotalsForPeriod(
                $userId, $counterId, $date1, $date2, $goalIds, $filter
            ) ?: [];
            foreach ($goalIds as $goalId) {
                $cur = $goalTotals[$goalId] ?? ['reaches' => 0.0, 'conversion_rate' => 0.0];
                $goalConversions[] = [
                    'id' => $goalId,
                    'name' => $goalNames[$goalId] ?? ('Goal #' . $goalId),
                    'reaches' => $cur['reaches'],
                    'conversion_rate' => $cur['conversion_rate'],
                ];
            }
        }

        return [
            'ok' => true,
            'status' => SeoReportSectionRegistry::SOURCE_STATUS_OK,
            'progress' => 'ok',
            'data' => [
                'source' => 'metrika_google_ads',
                'note' => __('Google Ads traffic from Metrika (OAuth later for spend)'),
                'kpis' => $this->kpiBundle($current, null),
                'campaigns' => $campaigns,
                'landings' => $landings,
                'phrases' => $phrases,
                'conversions' => $goalConversions,
            ],
        ];
    }

    /**
     * @param array<string,mixed> $kpisRaw
     * @param list<array<string,mixed>> $goalConversions
     * @return list<string>
     */
    private function adsFixHints(array $kpisRaw, array $goalConversions): array
    {
        $hints = [];
        $bounce = (float) ($kpisRaw['bounce_rate'] ?? 0);
        if ($bounce >= 45) {
            $hints[] = 'Высокий отказ в рекламном трафике — проверить посадочные и соответствие объявлений.';
        }
        $depth = (float) ($kpisRaw['page_depth'] ?? 0);
        if ($depth > 0 && $depth < 1.3) {
            $hints[] = 'Низкая глубина просмотра из рекламы — упростить первый экран и CTA.';
        }
        $reaches = 0.0;
        foreach ($goalConversions as $g) {
            $reaches += (float) ($g['reaches'] ?? 0);
        }
        if ($reaches <= 0 && (float) ($kpisRaw['visits'] ?? 0) >= 50) {
            $hints[] = 'Есть рекламный трафик, но нет конверсий по целям — проверить цели и формы.';
        }
        if ($hints === []) {
            $hints[] = 'Критичных проблем в рекламном срезе Метрики не видно — смотрите кампании после подключения Директа.';
        }

        return $hints;
    }

    /**
     * @return array{ok:bool,status:string,message?:string,progress:string,data?:array}
     */
    private function collectCallsFromMetrika(SeoReportProject $project, SeoReport $report): array
    {
        $counterId = (int) $project->metrika_counter_id;
        $userId = (int) $project->user_id;
        if ($counterId < 1 || !$this->metrika->isConnected($userId)) {
            return [
                'ok' => false,
                'status' => SeoReportSectionRegistry::SOURCE_STATUS_NOT_CONNECTED,
                'message' => __('Call tracking is not connected'),
                'progress' => 'skip',
            ];
        }
        $date1 = optional($report->period_from)->format('Y-m-d');
        $date2 = optional($report->period_to)->format('Y-m-d');
        if (!$date1 || !$date2) {
            return [
                'ok' => false,
                'status' => SeoReportSectionRegistry::SOURCE_STATUS_ERROR,
                'message' => __('Invalid period'),
                'progress' => 'error',
            ];
        }

        $metrics = [
            'ym:s:calls',
            'ym:s:missedCalls',
            'ym:s:firstCallVisits',
            'ym:s:callTalkDurationAverage',
            'ym:s:callHoldDurationAverage',
        ];
        $totals = $this->metrika->customTotalsForPeriod($userId, $counterId, $date1, $date2, $metrics);
        if ($totals === null) {
            return [
                'ok' => false,
                'status' => SeoReportSectionRegistry::SOURCE_STATUS_NOT_CONNECTED,
                'message' => __('Metrika calltracking is not enabled on counter'),
                'progress' => 'skip',
            ];
        }
        $calls = (float) ($totals[0] ?? 0);
        if ($calls <= 0) {
            return [
                'ok' => false,
                'status' => SeoReportSectionRegistry::SOURCE_STATUS_EMPTY,
                'message' => __('No calls in period'),
                'progress' => 'empty',
            ];
        }

        $byChannel = $this->metrika->customDimensionRowsForPeriod(
            $userId, $counterId, $date1, $date2, 'ym:s:lastTrafficSource',
            ['ym:s:calls', 'ym:s:missedCalls'], 12
        ) ?: [];
        $channels = [];
        foreach ($byChannel as $row) {
            $m = $row['metrics'] ?? [];
            $channels[] = [
                'name' => $row['name'] ?? '—',
                'calls' => (float) ($m[0] ?? 0),
                'missed' => (float) ($m[1] ?? 0),
            ];
        }

        return [
            'ok' => true,
            'status' => SeoReportSectionRegistry::SOURCE_STATUS_OK,
            'progress' => 'ok',
            'data' => [
                'source' => 'metrika_calltracking',
                'total' => $calls,
                'missed' => (float) ($totals[1] ?? 0),
                'first' => (float) ($totals[2] ?? 0),
                'talk_avg' => (float) ($totals[3] ?? 0),
                'hold_avg' => (float) ($totals[4] ?? 0),
                'by_channel' => $channels,
            ],
        ];
    }

    /**
     * @return array{ok:bool,status:string,message?:string,progress:string,data?:array}
     */
    /**
     * @return array{ok:bool,status:string,message?:string,progress:string,data?:array,fact?:string}
     */
    private function collectTitloSection(string $key, SeoReportProject $project, SeoReport $report): array
    {
        $userId = (int) $project->user_id;
        $domain = (string) $project->domain;

        if ($key === 'titlo_audit') {
            $r = $this->titloModules->collectAudit($userId, $domain);
            if (!empty($r['ok']) && !empty($r['data'])) {
                if (!empty($r['data']['summary'])) {
                    $r['fact'] = (string) $r['data']['summary'];
                } else {
                    $b = is_array($r['data']['buckets'] ?? null) ? $r['data']['buckets'] : [];
                    $r['fact'] = sprintf(
                        'Аудит сайта: %d стр., грубые %d, прочие %d, предупреждения %d',
                        (int) ($r['data']['pages_fetched'] ?? 0),
                        (int) ($b['critical'] ?? 0),
                        (int) ($b['other'] ?? 0),
                        (int) ($b['warning'] ?? 0)
                    );
                }
            }

            return $r;
        }

        if ($key === 'titlo_checklist') {
            $r = $this->titloModules->collectChecklist(
                $userId,
                $domain,
                $report->period_from,
                $report->period_to
            );
            if (!empty($r['ok'])) {
                $r['fact'] = sprintf(
                    'SEO-чеклист: закрыто за период %d, просрочено %d',
                    (int) ($r['data']['closed_in_period'] ?? 0),
                    (int) ($r['data']['overdue'] ?? 0)
                );
            }

            return $r;
        }

        if ($key === 'titlo_relevance') {
            $r = $this->titloModules->collectRelevance($userId, $domain);
            if (!empty($r['ok'])) {
                if (!empty($r['data']['summary'])) {
                    $r['fact'] = (string) $r['data']['summary'];
                } else {
                    $r['fact'] = sprintf(
                        'Релевантность: балл %s/100, ср. позиция %s, запросов/URL %d',
                        $r['data']['avg_points'] !== null ? (string) $r['data']['avg_points'] : '—',
                        $r['data']['avg_position'] !== null ? (string) $r['data']['avg_position'] : '—',
                        (int) ($r['data']['count_sites'] ?? 0)
                    );
                }
            }

            return $r;
        }

        if ($key === 'titlo_uptime') {
            $r = $this->titloModules->collectUptime($userId, $domain);
            if (!empty($r['ok'])) {
                $uptime = $r['data']['uptime_percent'];
                $r['fact'] = 'Доступность: '
                    . ($uptime !== null ? number_format((float) $uptime, 2, ',', ' ') . '%' : '—');
                if (isset($r['data']['domain_days_left']) && $r['data']['domain_days_left'] !== null) {
                    $r['fact'] .= ', домен ещё ' . (int) $r['data']['domain_days_left'] . ' дн.';
                }
            }

            return $r;
        }

        return [
            'ok' => false,
            'status' => SeoReportSectionRegistry::SOURCE_STATUS_NOT_CONNECTED,
            'message' => __('Source is not connected yet'),
            'progress' => 'skip',
        ];
    }

    private function collectConversions(SeoReportProject $project, SeoReport $report, bool $searchOnly): array
    {
        $counterId = (int) $project->metrika_counter_id;
        $userId = (int) $project->user_id;
        if ($counterId < 1 || !$this->metrika->isConnected($userId)) {
            return [
                'ok' => false,
                'status' => SeoReportSectionRegistry::SOURCE_STATUS_NOT_CONNECTED,
                'message' => __('Yandex Metrika is not connected'),
                'progress' => 'skip',
            ];
        }

        $settings = $project->reportSettings();
        $goalIds = isset($settings['metrika_goal_ids']) && is_array($settings['metrika_goal_ids'])
            ? array_values(array_filter(array_map('intval', $settings['metrika_goal_ids'])))
            : [];

        if ($goalIds === []) {
            return [
                'ok' => false,
                'status' => SeoReportSectionRegistry::SOURCE_STATUS_NOT_CONNECTED,
                'message' => __('Select Metrika goals in project settings'),
                'progress' => 'skip',
            ];
        }

        $date1 = optional($report->period_from)->format('Y-m-d');
        $date2 = optional($report->period_to)->format('Y-m-d');
        if (!$date1 || !$date2) {
            return [
                'ok' => false,
                'status' => SeoReportSectionRegistry::SOURCE_STATUS_ERROR,
                'message' => __('Invalid period'),
                'progress' => 'error',
            ];
        }

        $allGoals = $this->metrika->listGoals($userId, $counterId) ?: [];
        $goalNames = [];
        foreach ($allGoals as $g) {
            $goalNames[(int) $g['id']] = (string) $g['name'];
        }

        $filter = $searchOnly ? self::ORGANIC_FILTER : null;
        $current = $this->metrika->goalTotalsForPeriod($userId, $counterId, $date1, $date2, $goalIds, $filter);
        if ($current === null) {
            return [
                'ok' => false,
                'status' => SeoReportSectionRegistry::SOURCE_STATUS_ERROR,
                'message' => __('Could not load Metrika stats'),
                'progress' => 'error',
            ];
        }

        $compare = null;
        if ($report->compare_from && $report->compare_to) {
            $compare = $this->metrika->goalTotalsForPeriod(
                $userId,
                $counterId,
                $report->compare_from->format('Y-m-d'),
                $report->compare_to->format('Y-m-d'),
                $goalIds,
                $filter
            );
        }

        $goalsOut = [];
        $channelsByGoal = [];
        foreach ($goalIds as $goalId) {
            $cur = $current[$goalId] ?? ['reaches' => 0.0, 'conversion_rate' => 0.0];
            $prev = is_array($compare) ? ($compare[$goalId] ?? null) : null;
            $goalsOut[] = [
                'id' => $goalId,
                'name' => $goalNames[$goalId] ?? ('Goal #' . $goalId),
                'reaches' => [
                    'value' => $cur['reaches'],
                    'prev' => $prev['reaches'] ?? null,
                    'delta_pct' => $this->deltaPct($cur['reaches'], $prev['reaches'] ?? null),
                ],
                'conversion_rate' => [
                    'value' => $cur['conversion_rate'],
                    'prev' => $prev['conversion_rate'] ?? null,
                    'delta_pct' => $this->deltaPct($cur['conversion_rate'], $prev['conversion_rate'] ?? null),
                ],
                // Cost requires ad spend (Direct/Ads) — filled in F3.
                'cost_per_conversion' => null,
            ];

            $channelsByGoal[$goalId] = $this->metrika->goalChannelsForPeriod(
                $userId, $counterId, $date1, $date2, $goalId, $filter
            ) ?: [];
        }

        $searchGoals = null;
        if (!$searchOnly) {
            $searchCurrent = $this->metrika->goalTotalsForPeriod(
                $userId, $counterId, $date1, $date2, $goalIds, self::ORGANIC_FILTER
            );
            if ($searchCurrent !== null) {
                $searchGoals = [];
                foreach ($goalIds as $goalId) {
                    $cur = $searchCurrent[$goalId] ?? ['reaches' => 0.0, 'conversion_rate' => 0.0];
                    $searchGoals[] = [
                        'id' => $goalId,
                        'name' => $goalNames[$goalId] ?? ('Goal #' . $goalId),
                        'reaches' => $cur['reaches'],
                        'conversion_rate' => $cur['conversion_rate'],
                    ];
                }
            }
        }

        $socialGoals = null;
        $socialCurrent = $this->metrika->goalTotalsForPeriod(
            $userId, $counterId, $date1, $date2, $goalIds, self::SOCIAL_FILTER
        );
        if ($socialCurrent !== null) {
            $socialGoals = [];
            foreach ($goalIds as $goalId) {
                $cur = $socialCurrent[$goalId] ?? ['reaches' => 0.0, 'conversion_rate' => 0.0];
                $socialGoals[] = [
                    'id' => $goalId,
                    'name' => $goalNames[$goalId] ?? ('Goal #' . $goalId),
                    'reaches' => $cur['reaches'],
                    'conversion_rate' => $cur['conversion_rate'],
                ];
            }
        }

        $adGoals = null;
        $adCurrent = $this->metrika->goalTotalsForPeriod(
            $userId, $counterId, $date1, $date2, $goalIds, self::AD_FILTER
        );
        if ($adCurrent !== null) {
            $adGoals = [];
            foreach ($goalIds as $goalId) {
                $cur = $adCurrent[$goalId] ?? ['reaches' => 0.0, 'conversion_rate' => 0.0];
                $adGoals[] = [
                    'id' => $goalId,
                    'name' => $goalNames[$goalId] ?? ('Goal #' . $goalId),
                    'reaches' => $cur['reaches'],
                    'conversion_rate' => $cur['conversion_rate'],
                ];
            }
        }

        // Цена конверсии — если Метрика отдаёт cost по цели (импорт из Директа).
        $costMetrics = [];
        foreach ($goalIds as $goalId) {
            $costMetrics[] = 'ym:s:goal' . $goalId . 'cost';
        }
        $costs = $this->metrika->customTotalsForPeriod(
            $userId, $counterId, $date1, $date2, $costMetrics, $filter
        );
        if (is_array($costs)) {
            foreach ($goalsOut as $i => $goal) {
                $cost = (float) ($costs[$i] ?? 0);
                $reaches = (float) ($goal['reaches']['value'] ?? 0);
                if ($cost > 0 && $reaches > 0) {
                    $goalsOut[$i]['cost_per_conversion'] = round($cost / $reaches, 2);
                    $goalsOut[$i]['cost'] = $cost;
                }
            }
        }

        return [
            'ok' => true,
            'status' => SeoReportSectionRegistry::SOURCE_STATUS_OK,
            'progress' => 'ok',
            'data' => [
                'goals' => $goalsOut,
                'channels_by_goal' => $channelsByGoal,
                'search_goals' => $searchGoals,
                'social_goals' => $socialGoals,
                'ad_goals' => $adGoals,
                'comment' => __('Conversions auto comment'),
            ],
        ];
    }

    private function integrationMissingMessage(string $key, string $source): string
    {
        $map = [
            'direct' => __('Yandex Direct is not connected — open settings after OAuth is available'),
            'google_ads' => __('Google Ads is not connected — cabinet OAuth will be added later'),
            'vk_ads' => __('VK Ads is not connected'),
            'vk_smm' => __('VK community is not connected'),
            'calls' => __('Call tracking is not connected'),
        ];

        return $map[$key] ?? $map[$source] ?? __('Source is not connected yet');
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private function normalizeLandingRows(array $rows, string $domain): array
    {
        $merged = [];
        foreach ($rows as $row) {
            $raw = (string) ($row['name'] ?? '');
            $canon = $this->canonicalizeUrl($raw, $domain);
            if ($canon === '') {
                continue;
            }
            if (!isset($merged[$canon])) {
                $row['name'] = $canon;
                $row['raw_name'] = $raw;
                $merged[$canon] = $row;
                continue;
            }
            $merged[$canon]['visits'] = (float) ($merged[$canon]['visits'] ?? 0) + (float) ($row['visits'] ?? 0);
            $merged[$canon]['users'] = (float) ($merged[$canon]['users'] ?? 0) + (float) ($row['users'] ?? 0);
            if (isset($row['visits_prev'])) {
                $merged[$canon]['visits_prev'] = (float) ($merged[$canon]['visits_prev'] ?? 0)
                    + (float) $row['visits_prev'];
            }
        }

        $out = array_values($merged);
        usort($out, static function ($a, $b) {
            return ((float) ($b['visits'] ?? 0)) <=> ((float) ($a['visits'] ?? 0));
        });
        foreach ($out as &$row) {
            if (isset($row['visits_prev'])) {
                $row['visits_delta_pct'] = $this->deltaPct($row['visits'] ?? null, $row['visits_prev']);
            }
        }
        unset($row);

        return $out;
    }

    private function canonicalizeUrl(string $url, string $domain): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (strpos($url, '//') === 0) {
            $url = 'https:' . $url;
        }
        if (!preg_match('#^https?://#i', $url) && strpos($url, '/') === 0) {
            $url = 'https://' . $domain . $url;
        }
        $parts = parse_url($url);
        if ($parts === false) {
            return preg_replace('/[?#].*$/', '', $url) ?: $url;
        }
        $path = $parts['path'] ?? '/';
        if ($path === '') {
            $path = '/';
        }
        // без query/fragment; host приводим к домену проекта если пуст
        return $path;
    }

    /**
     * @return array{ok:bool,status:string,message?:string,progress:string,data?:array}
     */
    private function collectPositions(SeoReportProject $project, SeoReport $report): array
    {
        $monitoringId = (int) $project->monitoring_project_id;
        if ($monitoringId < 1) {
            return [
                'ok' => false,
                'status' => SeoReportSectionRegistry::SOURCE_STATUS_NOT_CONNECTED,
                'message' => __('Monitoring project is not linked'),
                'progress' => 'skip',
            ];
        }

        $monitoring = MonitoringProject::query()->find($monitoringId);
        if (!$monitoring) {
            return [
                'ok' => false,
                'status' => SeoReportSectionRegistry::SOURCE_STATUS_ERROR,
                'message' => __('Monitoring project not found'),
                'progress' => 'error',
            ];
        }

        $result = $this->positionsCollector->collect(
            $monitoring,
            $report->period_from,
            $report->period_to
        );

        return [
            'ok' => $result['ok'],
            'status' => $result['ok']
                ? SeoReportSectionRegistry::SOURCE_STATUS_OK
                : ($result['status'] === 'empty'
                    ? SeoReportSectionRegistry::SOURCE_STATUS_EMPTY
                    : SeoReportSectionRegistry::SOURCE_STATUS_ERROR),
            'message' => $result['message'] ?? null,
            'progress' => $result['progress'],
            'data' => $result['data'] ?? null,
        ];
    }

    /**
     * @param array<string,float|null> $current
     * @param array<string,float|null>|null $compare
     * @return array<string, array{value:mixed,prev:mixed,delta_pct:?float}>
     */
    private function kpiBundle(array $current, ?array $compare): array
    {
        $metrics = ['visits', 'users', 'pageviews', 'bounce_rate', 'page_depth', 'avg_visit_duration'];
        $kpis = [];
        foreach ($metrics as $metric) {
            $value = $current[$metric] ?? null;
            $prev = is_array($compare) ? ($compare[$metric] ?? null) : null;
            $kpis[$metric] = [
                'value' => $value,
                'prev' => $prev,
                'delta_pct' => $this->deltaPct($value, $prev),
            ];
        }

        return $kpis;
    }

    /**
     * @return list<array{month:string,channels:list<array{name:string,visits:float}>}>
     */
    private function channelMonths(int $userId, int $counterId, string $dateTo): array
    {
        $out = [];
        try {
            $end = Carbon::parse($dateTo)->startOfMonth();
            for ($i = 5; $i >= 0; $i--) {
                $from = $end->copy()->subMonthsNoOverflow($i)->startOfMonth();
                $to = $from->copy()->endOfMonth();
                if ($to->gt(Carbon::parse($dateTo))) {
                    $to = Carbon::parse($dateTo);
                }
                $rows = $this->metrika->dimensionRowsForPeriod(
                    $userId,
                    $counterId,
                    $from->format('Y-m-d'),
                    $to->format('Y-m-d'),
                    'ym:s:lastTrafficSource',
                    8
                );
                $out[] = [
                    'month' => $from->format('Y-m'),
                    'channels' => $rows ?: [],
                ];
            }
        } catch (Throwable $e) {
            return [];
        }

        return $out;
    }

    /**
     * @param list<array<string,mixed>> $landings
     * @return list<array<string,mixed>>
     */
    private function attachVisitDeltas(
        array $landings,
        int $userId,
        int $counterId,
        SeoReport $report,
        ?string $filter
    ): array {
        if ($landings === [] || !$report->compare_from || !$report->compare_to) {
            return $landings;
        }

        $prevRows = $this->metrika->dimensionRowsForPeriod(
            $userId,
            $counterId,
            $report->compare_from->format('Y-m-d'),
            $report->compare_to->format('Y-m-d'),
            'ym:s:startURLPathFull',
            50,
            $filter
        ) ?: [];

        $prevMap = [];
        foreach ($prevRows as $row) {
            $prevMap[(string) $row['name']] = (float) ($row['visits'] ?? 0);
        }

        foreach ($landings as &$row) {
            $name = (string) ($row['name'] ?? '');
            $prev = $prevMap[$name] ?? 0.0;
            $row['visits_prev'] = $prev;
            $row['visits_delta_pct'] = $this->deltaPct($row['visits'] ?? null, $prev);
        }
        unset($row);

        return $landings;
    }

    /**
     * @param array<string, mixed> $snapshot
     * @return list<array{key:string,label:string,value:string,delta:?string,delta_class:string}>
     */
    private function buildScorecard(array $snapshot): array
    {
        $cards = [];
        $traffic = is_array($snapshot['traffic'] ?? null) ? $snapshot['traffic'] : null;
        if ($traffic) {
            foreach (['visits' => __('Visits'), 'users' => __('Users'), 'bounce_rate' => __('Bounce rate')] as $key => $label) {
                $kpi = $traffic['kpis'][$key] ?? null;
                if (!is_array($kpi) || $kpi['value'] === null) {
                    continue;
                }
                $value = $key === 'bounce_rate'
                    ? number_format((float) $kpi['value'], 1, ',', ' ') . '%'
                    : number_format((float) $kpi['value'], 0, ',', ' ');
                $delta = $kpi['delta_pct'];
                $deltaClass = '';
                if ($delta !== null) {
                    $deltaClass = $delta > 0 ? 'is-up' : ($delta < 0 ? 'is-down' : '');
                    if ($key === 'bounce_rate') {
                        $deltaClass = $delta < 0 ? 'is-up' : ($delta > 0 ? 'is-down' : '');
                    }
                }
                $cards[] = [
                    'key' => $key,
                    'label' => $label,
                    'value' => $value,
                    'delta' => $delta !== null
                        ? (($delta > 0 ? '+' : '') . number_format((float) $delta, 1, ',', ' ') . '%')
                        : null,
                    'delta_class' => $deltaClass,
                ];
            }
        }

        $positions = is_array($snapshot['positions'] ?? null) ? $snapshot['positions'] : null;
        if ($positions) {
            $top10 = $positions['summary']['top10'] ?? null;
            if ($top10 !== null && $top10 !== '') {
                $cards[] = [
                    'key' => 'top10',
                    'label' => 'TOP-10',
                    'value' => (string) $top10,
                    'delta' => isset($positions['summary']['diff_top10'])
                        ? (string) $positions['summary']['diff_top10']
                        : null,
                    'delta_class' => '',
                ];
            }
            $dyn = $positions['dynamics'] ?? [];
            if (!empty($dyn['pairs'])) {
                $cards[] = [
                    'key' => 'pos_up',
                    'label' => __('Improved'),
                    'value' => (string) ((int) ($dyn['improved'] ?? 0)),
                    'delta' => null,
                    'delta_class' => 'is-up',
                ];
                $cards[] = [
                    'key' => 'pos_down',
                    'label' => __('Worsened'),
                    'value' => (string) ((int) ($dyn['worsened'] ?? 0)),
                    'delta' => null,
                    'delta_class' => 'is-down',
                ];
            }
        }

        return array_slice($cards, 0, 6);
    }

    /**
     * @param mixed $value
     * @param mixed $prev
     */
    private function deltaPct($value, $prev): ?float
    {
        if ($value === null || $prev === null) {
            return null;
        }
        $value = (float) $value;
        $prev = (float) $prev;
        if (abs($prev) < 0.00001) {
            return $value > 0 ? 100.0 : 0.0;
        }

        return round((($value - $prev) / $prev) * 100, 1);
    }
}
