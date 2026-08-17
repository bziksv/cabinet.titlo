@php
    $snapshot = $snapshot ?? [];
    $cover = $snapshot['cover'] ?? [];
    $traffic = $snapshot['traffic'] ?? null;
    $positions = $snapshot['positions'] ?? null;
    $conversions = $snapshot['conversions'] ?? null;
    $kpiGoalsEval = is_array($snapshot['kpi_goals'] ?? null) ? $snapshot['kpi_goals'] : [];
    $quality = $snapshot['quality'] ?? null;
    $scorecard = $snapshot['scorecard'] ?? [];
    $insights = $snapshot['insights'] ?? [];
    $comments = is_array($report->comments_json ?? null) ? $report->comments_json : [];
    $isPublic = !empty($isPublicView);
    $brand = \App\SeoReports\SeoReportBrandColor::normalize(
        $cover['agency']['brand_color'] ?? ($project->brand_color ?: '#1d4ed8')
    );
    $projectSettings = method_exists($project, 'reportSettings')
        ? $project->reportSettings()
        : (is_array($project->settings_json ?? null) ? $project->settings_json : []);
    $mirrorDomains = is_array($projectSettings['mirror_domains'] ?? null) ? $projectSettings['mirror_domains'] : [];
    $confirmedOnly = !empty($projectSettings['confirmed_sources_only']);
    $metricOn = static function (string $section, string $metric) use ($projectSettings): bool {
        return \App\SeoReports\SeoReportMetricRegistry::enabled($projectSettings, $section, $metric);
    };
    $kpiLabels = [
        'visits' => __('Visits'),
        'users' => __('Users'),
        'pageviews' => __('Pageviews'),
        'bounce_rate' => __('Bounce rate'),
        'page_depth' => __('Page depth'),
        'avg_visit_duration' => __('Avg. visit duration'),
    ];
    $toc = [];
    foreach ($sections as $section) {
        $key = $section['key'];
        if ($key === 'cover') {
            continue;
        }
        $enabled = array_key_exists('enabled', $section) ? !empty($section['enabled']) : true;
        $clientVisible = array_key_exists('client_visible', $section) ? !empty($section['client_visible']) : true;
        if ($isPublic && !$clientVisible) {
            continue;
        }
        if (!$enabled && $isPublic) {
            continue;
        }
        $toc[] = ['key' => $key, 'title' => $section['title']];
    }

    $fmtDur = static function ($sec) {
        $sec = (int) round((float) $sec);
        return sprintf('%d:%02d', intdiv($sec, 60), $sec % 60);
    };
    $fmtNum = static function ($v, $decimals = 0) {
        return number_format((float) $v, $decimals, ',', ' ');
    };
    $metrikaLabel = static function ($name, $id = null): string {
        return \App\SeoReports\SeoReportMetrikaLabels::label(
            is_string($name) ? $name : (string) ($name ?? ''),
            $id
        );
    };
    $fmtKpiMetric = static function (string $metric, $value) use ($fmtNum, $fmtDur): string {
        if ($value === null || $value === '') {
            return '—';
        }
        if ($metric === 'bounce_rate') {
            return $fmtNum($value, 1) . '%';
        }
        if ($metric === 'page_depth') {
            return $fmtNum($value, 2);
        }
        if ($metric === 'avg_visit_duration') {
            return $fmtDur($value);
        }

        return $fmtNum($value);
    };
    $reportDomain = (string) ($cover['domain'] ?? ($project->domain ?? ''));
    $trafficHeroShown = false;
@endphp

<div class="cabinet-sr-report {{ count($toc) > 1 ? 'cabinet-sr-report--with-toc' : '' }}"
     style="--sr-accent: {{ $brand }};"
     data-sr-report>
    @if(count($toc) > 1)
        <div class="cabinet-sr-toc-slot" data-sr-toc-slot>
            <aside class="cabinet-sr-toc" data-sr-toc aria-label="{{ __('Report sections') }}">
                <div class="cabinet-sr-toc__head">
                    <div class="cabinet-sr-toc__title">{{ __('Report sections') }}</div>
                    <div class="cabinet-sr-toc__bar" data-sr-toc-bar></div>
                </div>
                <nav class="cabinet-sr-toc__links">
                    @foreach($toc as $item)
                        <a href="#sr-{{ $item['key'] }}"
                           data-sr-toc-link="sr-{{ $item['key'] }}"
                           data-sr-section-jump="{{ $item['key'] }}">{{ $item['title'] }}</a>
                    @endforeach
                </nav>
            </aside>
        </div>
    @endif

    <div class="cabinet-sr-report__main">
    @if(!empty($cover['compare_label']))
        <label class="cabinet-sr-compare-toggle">
            <input type="checkbox" checked data-sr-compare-toggle>
            <span>{{ __('Show period compare') }}</span>
        </label>
    @endif

    <section class="cabinet-sr-report-block cabinet-sr-cover" id="sr-cover">
        <div class="cabinet-sr-cover__accent" aria-hidden="true"></div>
        <div class="cabinet-sr-cover__row">
            <div class="cabinet-sr-cover__main">
                @if(!empty($cover['agency']['logo_url']) || !empty($cover['agency']['name']))
                    <div class="cabinet-sr-cover__brand">
                        @if(!empty($cover['agency']['logo_url']))
                            <img class="cabinet-sr-cover__logo" src="{{ $cover['agency']['logo_url'] }}"
                                 alt="{{ $cover['agency']['name'] ?? '' }}">
                        @endif
                        @if(!empty($cover['agency']['name']))
                            <div class="cabinet-sr-cover__agency">{{ $cover['agency']['name'] }}</div>
                        @endif
                    </div>
                @endif
                <h1 class="cabinet-sr-cover__title">{{ $cover['title'] ?? ($project->domain) }}</h1>
                <div class="cabinet-sr-cover__periods">
                    <div class="cabinet-sr-period-chip is-current">
                        <span>{{ __('Report period') }}</span>
                        <strong>{{ $cover['period_label'] ?? '—' }}</strong>
                    </div>
                    @if(!empty($cover['compare_label']))
                        <div class="cabinet-sr-period-chip is-compare cabinet-sr-compare-only">
                            <span>{{ __('Compared with') }}</span>
                            <strong>{{ $cover['compare_label'] }}</strong>
                            @if(!empty($cover['compare_baseline']['reason']))
                                <em>{{ $cover['compare_baseline']['reason'] }}</em>
                            @endif
                        </div>
                    @endif
                </div>
                @if($mirrorDomains !== [])
                    <p class="cabinet-sr-cover__meta">
                        {{ __('Mirror domains') }}: {{ implode(', ', $mirrorDomains) }}
                    </p>
                @endif
                @if(!empty($cover['data_as_of']))
                    <p class="cabinet-sr-cover__meta">{{ __('Data as of') }}: {{ \Carbon\Carbon::parse($cover['data_as_of'])->format('d.m.Y H:i') }}</p>
                @endif
                @if($quality)
                    <span class="cabinet-sr-badge {{ $quality === 'full' ? 'cabinet-sr-badge--ok' : 'cabinet-sr-badge--warn' }}">
                        {{ $quality === 'full' ? __('Full data') : ($quality === 'partial' ? __('Partial data') : __('No data')) }}
                    </span>
                @endif
            </div>
            @php
                $hasManager = !empty($cover['manager']['name'])
                    || !empty($cover['manager']['phone'])
                    || !empty($cover['manager']['email'])
                    || !empty($cover['manager']['avatar_url']);
            @endphp
            @if($hasManager)
                <div class="cabinet-sr-cover__manager">
                    @if(!empty($cover['manager']['avatar_url']))
                        <img class="cabinet-sr-cover__avatar" src="{{ $cover['manager']['avatar_url'] }}" alt="">
                    @endif
                    @if(!empty($cover['manager']['name']))
                        <div class="cabinet-sr-cover__manager-label">{{ __('Your manager') }}</div>
                        <div class="cabinet-sr-cover__manager-name">{{ $cover['manager']['name'] }}</div>
                    @endif
                    @if(!empty($cover['manager']['phone']))
                        <a href="tel:{{ preg_replace('/\s+/', '', $cover['manager']['phone']) }}">{{ $cover['manager']['phone'] }}</a>
                    @endif
                    @if(!empty($cover['manager']['email']))
                        <div><a href="mailto:{{ $cover['manager']['email'] }}">{{ $cover['manager']['email'] }}</a></div>
                    @endif
                </div>
            @endif
        </div>
    </section>

    @if(!empty($scorecard))
        @php
            $scoreHasTrafficCompare = false;
            foreach (['users', 'visits', 'pageviews'] as $tk) {
                if (($traffic['kpis'][$tk]['prev'] ?? null) !== null) {
                    $scoreHasTrafficCompare = true;
                    break;
                }
            }
            $scoreExtra = collect($scorecard)->filter(static function ($card) {
                return !in_array((string) ($card['key'] ?? ''), ['visits', 'users', 'pageviews'], true);
            })->values();
        @endphp
        @if($scoreHasTrafficCompare && is_array($traffic))
            @include('pages.partials.seo-reports-traffic-hero')
            @php $trafficHeroShown = true; @endphp
            @if($scoreExtra->isNotEmpty())
                <section class="cabinet-sr-scorecard cabinet-sr-scorecard--extra" id="sr-scorecard">
                    @foreach($scoreExtra as $card)
                        @php
                            $cardKey = (string) ($card['key'] ?? '');
                            $cardKpi = is_array($traffic['kpis'][$cardKey] ?? null) ? $traffic['kpis'][$cardKey] : null;
                            $cardPrev = $cardKpi['prev'] ?? null;
                            $cardCur = $cardKpi['value'] ?? null;
                        @endphp
                        <div class="cabinet-sr-kpi">
                            <div class="cabinet-sr-kpi__label">
                                <span class="cabinet-sr-tip" title="{{ __('Metric tip: :name', ['name' => $card['label']]) }}">{{ $card['label'] }}</span>
                            </div>
                            @include('pages.partials.seo-reports-kpi-compare', [
                                'curValue' => $cardCur !== null ? $cardCur : null,
                                'prevValue' => $cardPrev,
                                'curDisplay' => $card['value'] ?? '—',
                                'prevDisplay' => $cardPrev !== null && $cardKey !== ''
                                    ? $fmtKpiMetric($cardKey, $cardPrev)
                                    : null,
                                'deltaDisplay' => !empty($card['delta']) ? $card['delta'] : null,
                                'deltaClass' => $card['delta_class'] ?? '',
                            ])
                        </div>
                    @endforeach
                </section>
            @endif
        @else
            <section class="cabinet-sr-scorecard" id="sr-scorecard">
                @foreach($scorecard as $card)
                    @php
                        $cardKey = (string) ($card['key'] ?? '');
                        $cardKpi = is_array($traffic['kpis'][$cardKey] ?? null) ? $traffic['kpis'][$cardKey] : null;
                        $cardPrev = $cardKpi['prev'] ?? null;
                        $cardCur = $cardKpi['value'] ?? null;
                    @endphp
                    <div class="cabinet-sr-kpi">
                        <div class="cabinet-sr-kpi__label">
                            <span class="cabinet-sr-tip" title="{{ __('Metric tip: :name', ['name' => $card['label']]) }}">{{ $card['label'] }}</span>
                        </div>
                        @include('pages.partials.seo-reports-kpi-compare', [
                            'curValue' => $cardCur !== null ? $cardCur : null,
                            'prevValue' => $cardPrev,
                            'curDisplay' => $card['value'] ?? '—',
                            'prevDisplay' => $cardPrev !== null && $cardKey !== ''
                                ? $fmtKpiMetric($cardKey, $cardPrev)
                                : null,
                            'deltaDisplay' => !empty($card['delta']) ? $card['delta'] : null,
                            'deltaClass' => $card['delta_class'] ?? '',
                        ])
                        @if($cardKey === 'visits' && !empty($traffic['series_users']))
                            <div class="cabinet-sr-spark cabinet-sr-spark--mini" aria-hidden="true">
                                @php
                                    $vals = array_values($traffic['series_users']);
                                    $max = max(1, max($vals ?: [1]));
                                @endphp
                                @foreach(array_slice($vals, -14) as $v)
                                    <span style="height: {{ max(4, (int) round(28 * $v / $max)) }}px"></span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </section>
        @endif
    @endif

    @if(!empty($kpiGoalsEval))
        <section class="cabinet-sr-goals-strip" id="sr-kpi-goals-strip">
            @foreach($kpiGoalsEval as $g)
                @php
                    $pctRaw = $g['pct'] !== null ? (float) $g['pct'] : null;
                    $pctBar = $pctRaw !== null ? max(0, min(100, $pctRaw)) : 0;
                    $pctOver = $pctRaw !== null && $pctRaw > 150;
                    $pctLabel = $pctRaw === null
                        ? '—'
                        : ($pctOver
                            ? '×' . $fmtNum($pctRaw / 100, 1)
                            : $fmtNum($pctRaw, 1) . '%');
                @endphp
                <div class="cabinet-sr-goal-card cabinet-sr-goal-card--{{ $g['tone'] ?? 'yellow' }}">
                    <div class="cabinet-sr-goal-card__label">{{ __('Goal') }}: {{ $g['label'] }}</div>
                    <div class="cabinet-sr-goal-card__pct">{{ $pctLabel }}</div>
                    <div class="cabinet-sr-goal-card__bar" aria-hidden="true">
                        <i style="width: {{ $pctBar }}%"></i>
                    </div>
                    <div class="cabinet-sr-goal-card__meta">
                        {{ $g['actual'] !== null ? $fmtNum($g['actual']) : '—' }}
                        / {{ $fmtNum($g['target'] ?? 0) }}
                        <span class="cabinet-sr-goal-card__meta-hint">{{ __('fact / target') }}</span>
                    </div>
                    <div class="cabinet-sr-goal-card__why">
                        @if($pctOver)
                            {{ __('Goal exceeded by factor', ['factor' => $fmtNum($pctRaw / 100, 1)]) }}
                        @else
                            {{ $g['why'] ?? '' }}
                        @endif
                    </div>
                </div>
            @endforeach
        </section>
    @endif

    @foreach($sections as $section)
        @php
            $key = $section['key'];
            if ($key === 'cover') {
                continue;
            }
            $enabled = array_key_exists('enabled', $section) ? !empty($section['enabled']) : true;
            $clientVisible = array_key_exists('client_visible', $section) ? !empty($section['client_visible']) : true;
            if ($isPublic && !$clientVisible) {
                continue;
            }
            if (!$isPublic && !$enabled && !isset($section['source_status'])) {
                continue;
            }
            $showDead = !$isPublic && $enabled && !$clientVisible;
            $hiddenClient = !$isPublic && !$clientVisible;
            if ($confirmedOnly && $isPublic && in_array($key, ['summary', 'work_done', 'work_plan'], true)) {
                $textField = $key === 'summary' ? $report->summary_text
                    : ($key === 'work_done' ? $report->work_done_text : $report->work_plan_text);
                $checklistItems = is_array($snapshot[$key]['from_checklist'] ?? null)
                    ? $snapshot[$key]['from_checklist']
                    : [];
                $hasChecklist = $checklistItems !== [];
                if ($key === 'summary') {
                    if (trim((string) $textField) === '' && empty($insights) && empty($snapshot['recommendations'])) {
                        continue;
                    }
                } elseif (trim((string) $textField) === '' && !$hasChecklist) {
                    continue;
                }
            }
        @endphp

        <section class="cabinet-sr-report-block {{ $hiddenClient ? 'cabinet-sr-report-block--hidden-client' : '' }}"
                 id="sr-{{ $key }}">
            <header class="cabinet-sr-section-head">
                <h2 class="cabinet-sr-section-head__title">{{ $section['title'] }}</h2>
            </header>

            @if(!$isPublic && !$enabled)
                <p class="small text-secondary mb-0">{{ __('Section disabled in project settings') }}</p>
            @elseif($showDead)
                <p class="small text-secondary mb-0">
                    {{ __('Not connected') }} — {{ __('Hidden for client') }}.
                    @if(!empty($section['message'])) {{ $section['message'] }} @endif
                    <a href="{{ route('pages.seo-reports.settings', ['id' => $project->id]) }}">{{ __('Connect source') }}</a>
                </p>
            @elseif($key === 'summary')
                @if($report->summary_text)
                    <div class="cabinet-sr-prose">{!! nl2br(e(\App\SeoReports\SeoReportMetrikaLabels::localizeText((string) $report->summary_text))) !!}</div>
                @elseif(!empty($insights))
                    <ul class="cabinet-sr-bullets">
                        @foreach($insights as $b)
                            <li>{{ \App\SeoReports\SeoReportMetrikaLabels::localizeText((string) $b) }}</li>
                        @endforeach
                    </ul>
                @else
                    <p class="small text-secondary mb-0">{{ __('Placeholder: manual content will appear here') }}</p>
                @endif
            @elseif($key === 'traffic' && is_array($traffic))
                @php
                    $trafficMode = (string) ($traffic['mode'] ?? 'all');
                    $isSearchOnlyTraffic = $trafficMode === 'search_only';
                @endphp
                @if(!empty($traffic['scope_label']) && $trafficMode !== 'all')
                    <p class="cabinet-sr-traffic-scope-note">
                        {{ __('Traffic KPI scope') }}:
                        <strong>{{ $traffic['scope_label'] }}</strong>
                    </p>
                @elseif($isSearchOnlyTraffic)
                    <p class="cabinet-sr-traffic-scope-note">
                        {{ __('Traffic KPI scope') }}:
                        <strong>{{ __('Traffic mode search only') }}</strong>
                    </p>
                @endif
                @php
                    $trafficHasCompare = false;
                    foreach ($kpiLabels as $metric => $_label) {
                        if (!$metricOn('traffic', $metric)) {
                            continue;
                        }
                        if (($traffic['kpis'][$metric]['prev'] ?? null) !== null) {
                            $trafficHasCompare = true;
                            break;
                        }
                    }
                    $trafficVolumeKeys = ['users', 'visits', 'pageviews'];
                    $trafficQualityKeys = ['page_depth', 'avg_visit_duration', 'bounce_rate'];
                @endphp
                @if($trafficHasCompare)
                    @if(empty($trafficHeroShown))
                        @include('pages.partials.seo-reports-traffic-hero')
                        @php $trafficHeroShown = true; @endphp
                    @endif
                    {{-- Остальные KPI (если вдруг не вошли в hero) --}}
                    <div class="cabinet-sr-kpi-grid mt-3">
                        @foreach($kpiLabels as $metric => $label)
                            @continue(!$metricOn('traffic', $metric))
                            @continue(in_array($metric, array_merge($trafficVolumeKeys, $trafficQualityKeys), true))
                            @php
                                $kpi = $traffic['kpis'][$metric] ?? null;
                                $value = $kpi['value'] ?? null;
                                $prev = $kpi['prev'] ?? null;
                                $delta = $kpi['delta_pct'] ?? null;
                                $display = $fmtKpiMetric($metric, $value);
                                $deltaClass = '';
                                if ($delta !== null) {
                                    $deltaClass = $delta > 0 ? 'is-up' : ($delta < 0 ? 'is-down' : '');
                                }
                            @endphp
                            <div class="cabinet-sr-kpi">
                                <div class="cabinet-sr-kpi__label">{{ $label }}</div>
                                @include('pages.partials.seo-reports-kpi-compare', [
                                    'curValue' => $value,
                                    'prevValue' => $prev,
                                    'curDisplay' => $display,
                                    'prevDisplay' => $prev !== null ? $fmtKpiMetric($metric, $prev) : null,
                                    'deltaDisplay' => $delta !== null
                                        ? (($delta > 0 ? '+' : '') . $fmtNum($delta, 1) . '%')
                                        : null,
                                    'deltaClass' => $deltaClass,
                                ])
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="cabinet-sr-kpi-grid">
                        @foreach($kpiLabels as $metric => $label)
                            @continue(!$metricOn('traffic', $metric))
                            @php
                                $kpi = $traffic['kpis'][$metric] ?? null;
                                $value = $kpi['value'] ?? null;
                                $prev = $kpi['prev'] ?? null;
                                $delta = $kpi['delta_pct'] ?? null;
                                $display = $fmtKpiMetric($metric, $value);
                                $deltaClass = '';
                                if ($delta !== null) {
                                    $deltaClass = $delta > 0 ? 'is-up' : ($delta < 0 ? 'is-down' : '');
                                    if ($metric === 'bounce_rate') {
                                        $deltaClass = $delta < 0 ? 'is-up' : ($delta > 0 ? 'is-down' : '');
                                    }
                                }
                            @endphp
                            <div class="cabinet-sr-kpi">
                                <div class="cabinet-sr-kpi__label">{{ $label }}</div>
                                @include('pages.partials.seo-reports-kpi-compare', [
                                    'curValue' => $value,
                                    'prevValue' => $prev,
                                    'curDisplay' => $display,
                                    'prevDisplay' => $prev !== null ? $fmtKpiMetric($metric, $prev) : null,
                                    'deltaDisplay' => $delta !== null
                                        ? (($delta > 0 ? '+' : '') . $fmtNum($delta, 1) . '%')
                                        : null,
                                    'deltaClass' => $deltaClass,
                                ])
                            </div>
                        @endforeach
                    </div>
                @endif

                @php
                    $trafficComment = $comments['traffic'] ?? ($traffic['auto_comment'] ?? null);
                @endphp
                @if($trafficComment && $metricOn('traffic', 'comment'))
                    <p class="cabinet-sr-comment mt-2">{{ $trafficComment }}</p>
                @endif

                @if(!empty($traffic['series_users']) && $metricOn('traffic', 'series_users'))
                    @include('pages.partials.seo-reports-day-chart', [
                        'series' => $traffic['series_users'],
                        'title' => __('Users by day'),
                        'unitLabel' => __('Users'),
                    ])
                @endif

                @if(!empty($traffic['channels']) && $metricOn('traffic', 'channels'))
                    <h3 class="h6 mt-3">{{ __('Channels') }}</h3>
                    <div class="table-responsive">
                        <table class="cabinet-sr-data-table">
                            <thead>
                            <tr>
                                <th>{{ __('Channel') }}</th>
                                <th>{{ __('Visits') }}</th>
                                <th>{{ __('Bounce rate') }}</th>
                                <th>{{ __('Page depth') }}</th>
                                <th>{{ __('Avg. visit duration') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($traffic['channels'] as $row)
                                <tr>
                                    <td>{{ $metrikaLabel($row['name'] ?? '', $row['id'] ?? null) }}</td>
                                    <td>{{ $fmtNum($row['visits'] ?? 0) }}</td>
                                    <td>{{ $fmtNum($row['bounce_rate'] ?? 0, 1) }}%</td>
                                    <td>{{ $fmtNum($row['page_depth'] ?? 0, 2) }}</td>
                                    <td>{{ $fmtDur($row['avg_visit_duration'] ?? 0) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if(!empty($traffic['channels']))
                        @php $channelCount = count($traffic['channels']); @endphp
                        @if($channelCount > 1)
                            <div class="cabinet-sr-bars mt-2">
                                @php $maxCh = max(1, max(array_column($traffic['channels'], 'visits') ?: [1])); @endphp
                                @foreach(array_slice($traffic['channels'], 0, 6) as $row)
                                    <div class="cabinet-sr-bars__row">
                                        <span>{{ $metrikaLabel($row['name'] ?? '', $row['id'] ?? null) }}</span>
                                        <div class="cabinet-sr-bars__track">
                                            <i style="width: {{ max(4, (int) round(100 * ($row['visits'] ?? 0) / $maxCh)) }}%"></i>
                                            <b>{{ $fmtNum($row['visits'] ?? 0) }}</b>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    @endif
                @endif

                @if(!empty($traffic['channel_months']) && $metricOn('traffic', 'channel_months'))
                    <h3 class="h6 mt-3">{{ __('Channels by month') }}</h3>
                    <div class="table-responsive">
                        <table class="cabinet-sr-data-table">
                            <thead>
                            <tr>
                                <th>{{ __('Month') }}</th>
                                <th>{{ __('Top channel') }}</th>
                                <th>{{ __('Visits') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($traffic['channel_months'] as $month)
                                @php $top = $month['channels'][0] ?? null; @endphp
                                <tr>
                                    <td>{{ $month['month'] }}</td>
                                    <td>{{ $metrikaLabel($top['name'] ?? '', $top['id'] ?? null) }}</td>
                                    <td>{{ $top ? $fmtNum($top['visits'] ?? 0) : '—' }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                @if(!empty($traffic['sources']) && $metricOn('traffic', 'sources'))
                    <h3 class="h6 mt-3">{{ __('Traffic sources') }}</h3>
                    <div class="table-responsive">
                        <table class="cabinet-sr-data-table">
                            <thead>
                            <tr>
                                <th>{{ __('Source') }}</th>
                                <th>{{ __('Visits') }}</th>
                                <th>{{ __('Users') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($traffic['sources'] as $row)
                                <tr>
                                    <td>{{ $row['name'] }}</td>
                                    <td>{{ $fmtNum($row['visits'] ?? 0) }}</td>
                                    <td>{{ $fmtNum($row['users'] ?? 0) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                @php $search = $traffic['search'] ?? null; @endphp
                @if(is_array($search) && $metricOn('traffic', 'search'))
                    <h3 class="h6 mt-3">
                        {{ $isSearchOnlyTraffic ? __('Search engines share') : __('Search traffic') }}
                    </h3>
                    {{-- При режиме «только поиск» KPI/график по дням дублируют блок выше — показываем доли ПС --}}
                    @if(!$isSearchOnlyTraffic)
                        <div class="cabinet-sr-kpi-grid">
                            @foreach(['visits' => __('Visits'), 'users' => __('Users'), 'bounce_rate' => __('Bounce rate'), 'page_depth' => __('Page depth')] as $metric => $label)
                                @php
                                    $kpi = $search['kpis'][$metric] ?? null;
                                    $value = $kpi['value'] ?? null;
                                    $prev = $kpi['prev'] ?? null;
                                    $delta = $kpi['delta_pct'] ?? null;
                                    $display = $fmtKpiMetric($metric, $value);
                                    $deltaClass = '';
                                    if ($delta !== null) {
                                        $deltaClass = $delta > 0 ? 'is-up' : ($delta < 0 ? 'is-down' : '');
                                        if ($metric === 'bounce_rate') {
                                            $deltaClass = $delta < 0 ? 'is-up' : ($delta > 0 ? 'is-down' : '');
                                        }
                                    }
                                @endphp
                                <div class="cabinet-sr-kpi">
                                    <div class="cabinet-sr-kpi__label">{{ $label }}</div>
                                    <div class="cabinet-sr-kpi__value">{{ $display }}</div>
                                    @include('pages.partials.seo-reports-kpi-compare', [
                                        'curValue' => $value,
                                        'prevValue' => $prev,
                                        'prevDisplay' => $prev !== null ? $fmtKpiMetric($metric, $prev) : null,
                                        'deltaDisplay' => $delta !== null
                                            ? (($delta > 0 ? '+' : '') . $fmtNum($delta, 1) . '%')
                                            : null,
                                        'deltaClass' => $deltaClass,
                                    ])
                                </div>
                            @endforeach
                        </div>
                        @if(!empty($search['series_visits']))
                            @include('pages.partials.seo-reports-day-chart', [
                                'series' => $search['series_visits'],
                                'title' => __('Search visits by day'),
                                'unitLabel' => __('Visits'),
                            ])
                        @endif
                    @endif
                    @if(!empty($search['engines']))
                        @php
                            $engineSlices = [];
                            foreach ($search['engines'] as $row) {
                                $engineSlices[] = [
                                    'label' => (string) ($row['name'] ?? '—'),
                                    'value' => (float) ($row['visits'] ?? 0),
                                ];
                            }
                            $engineTotal = max(1, (int) array_sum(array_column($search['engines'], 'visits')));
                        @endphp
                        @include('pages.partials.seo-reports-donut-chart', [
                            'slices' => $engineSlices,
                            'title' => __('Search engines share'),
                            'unitLabel' => __('Visits'),
                        ])
                        <div class="table-responsive mt-2">
                            <table class="cabinet-sr-data-table">
                                <thead>
                                <tr>
                                    <th>{{ __('Search engine') }}</th>
                                    <th>{{ __('Visits') }}</th>
                                    <th>{{ __('Traffic share') }}</th>
                                    <th>{{ __('Bounce rate') }}</th>
                                    <th>{{ __('Compare') }}</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($search['engines'] as $row)
                                    @php $ev = (int) ($row['visits'] ?? 0); @endphp
                                    <tr>
                                        <td>{{ $row['name'] }}</td>
                                        <td>{{ $fmtNum($ev) }}</td>
                                        <td>{{ $fmtNum(100 * $ev / $engineTotal, 1) }}%</td>
                                        <td>{{ $fmtNum($row['bounce_rate'] ?? 0, 1) }}%</td>
                                        <td>
                                            @if(isset($row['visits_delta_pct']) && $row['visits_delta_pct'] !== null)
                                                {{ $row['visits_delta_pct'] > 0 ? '+' : '' }}{{ $fmtNum($row['visits_delta_pct'], 1) }}%
                                            @elseif(isset($row['visits_prev']))
                                                {{ $fmtNum($row['visits_prev']) }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                @endif

                @if(!empty($traffic['devices']) && $metricOn('traffic', 'devices'))
                    <h3 class="h6 mt-3">{{ __('Devices') }}</h3>
                    @php
                        $deviceSlices = [];
                        foreach (array_slice($traffic['devices'], 0, 8) as $row) {
                            $deviceSlices[] = [
                                'label' => $metrikaLabel($row['name'] ?? '', $row['id'] ?? null),
                                'value' => (float) ($row['visits'] ?? 0),
                            ];
                        }
                        $devTotal = max(1, (int) array_sum(array_column($traffic['devices'], 'visits')));
                    @endphp
                    @include('pages.partials.seo-reports-donut-chart', [
                        'slices' => $deviceSlices,
                        'title' => __('Devices share'),
                        'unitLabel' => __('Visits'),
                    ])
                    <div class="table-responsive mt-2">
                        <table class="cabinet-sr-data-table">
                            <thead>
                            <tr>
                                <th>{{ __('Device') }}</th>
                                <th>{{ __('Visits') }}</th>
                                <th>{{ __('Traffic share') }}</th>
                                <th>{{ __('Bounce rate') }}</th>
                                <th>{{ __('Page depth') }}</th>
                                <th>{{ __('Avg. visit duration') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($traffic['devices'] as $row)
                                @php $devVisits = (int) ($row['visits'] ?? 0); @endphp
                                <tr>
                                    <td>{{ $metrikaLabel($row['name'] ?? '', $row['id'] ?? null) }}</td>
                                    <td>{{ $fmtNum($devVisits) }}</td>
                                    <td>{{ $fmtNum(100 * $devVisits / $devTotal, 1) }}%</td>
                                    <td>{{ $fmtNum($row['bounce_rate'] ?? 0, 1) }}%</td>
                                    <td>{{ $fmtNum($row['page_depth'] ?? 0, 2) }}</td>
                                    <td>{{ $fmtDur($row['avg_visit_duration'] ?? 0) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                @if(!empty($traffic['geo']) && $metricOn('traffic', 'geo'))
                    <h3 class="h6 mt-3">{{ __('Geography') }}</h3>
                    <div class="table-responsive">
                        <table class="cabinet-sr-data-table">
                            <thead>
                            <tr>
                                <th>{{ __('City') }}</th>
                                <th>{{ __('Visits') }}</th>
                                <th>{{ __('Users') }}</th>
                                <th>{{ __('Bounce rate') }}</th>
                                <th>{{ __('Page depth') }}</th>
                                <th>{{ __('Avg. visit duration') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($traffic['geo'] as $row)
                                <tr>
                                    <td>{{ $row['name'] }}</td>
                                    <td>{{ $fmtNum($row['visits'] ?? 0) }}</td>
                                    <td>{{ $fmtNum($row['users'] ?? 0) }}</td>
                                    <td>{{ $fmtNum($row['bounce_rate'] ?? 0, 1) }}%</td>
                                    <td>{{ $fmtNum($row['page_depth'] ?? 0, 2) }}</td>
                                    <td>{{ $fmtDur($row['avg_visit_duration'] ?? 0) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="cabinet-sr-bars mt-2">
                        @php $maxGeo = max(1, max(array_column($traffic['geo'], 'visits') ?: [1])); @endphp
                        @foreach(array_slice($traffic['geo'], 0, 8) as $row)
                            <div class="cabinet-sr-bars__row">
                                <span>{{ $row['name'] }}</span>
                                <i style="width: {{ max(4, (int) round(100 * ($row['visits'] ?? 0) / $maxGeo)) }}%"></i>
                            </div>
                        @endforeach
                    </div>
                @endif

                @if(!empty($traffic['landings']) && $metricOn('traffic', 'landings'))
                    <h3 class="h6 mt-3">{{ __('Top landing pages') }}</h3>
                    <div class="table-responsive">
                        <table class="cabinet-sr-data-table">
                            <thead>
                            <tr>
                                <th>{{ __('URL') }}</th>
                                <th>{{ __('Visits') }}</th>
                                <th>{{ __('Delta') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($traffic['landings'] as $row)
                                <tr>
                                    <td class="cabinet-sr-url">@include('pages.partials.seo-reports-landing-link', ['value' => $row['name'] ?? null, 'domain' => $reportDomain])</td>
                                    <td>{{ $fmtNum($row['visits'] ?? 0) }}</td>
                                    <td>
                                        @if(isset($row['visits_delta_pct']) && $row['visits_delta_pct'] !== null)
                                            {{ $row['visits_delta_pct'] > 0 ? '+' : '' }}{{ $fmtNum($row['visits_delta_pct'], 1) }}%
                                        @else
                                            —
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                @if(!empty($traffic['landings_search']) && $metricOn('traffic', 'landings_search'))
                    <h3 class="h6 mt-3">{{ __('Top landing pages from search') }}</h3>
                    <div class="table-responsive">
                        <table class="cabinet-sr-data-table">
                            <thead>
                            <tr>
                                <th>{{ __('URL') }}</th>
                                <th>{{ __('Visits') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($traffic['landings_search'] as $row)
                                <tr>
                                    <td class="cabinet-sr-url">@include('pages.partials.seo-reports-landing-link', ['value' => $row['name'] ?? null, 'domain' => $reportDomain])</td>
                                    <td>{{ $fmtNum($row['visits'] ?? 0) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                @if(!empty($traffic['landings_social']) && $metricOn('traffic', 'landings_social'))
                    <h3 class="h6 mt-3">{{ __('Top landing pages from social') }}</h3>
                    <div class="table-responsive">
                        <table class="cabinet-sr-data-table">
                            <thead>
                            <tr>
                                <th>{{ __('URL') }}</th>
                                <th>{{ __('Visits') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($traffic['landings_social'] as $row)
                                <tr>
                                    <td class="cabinet-sr-url">@include('pages.partials.seo-reports-landing-link', ['value' => $row['name'] ?? null, 'domain' => $reportDomain])</td>
                                    <td>{{ $fmtNum($row['visits'] ?? 0) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            @elseif($key === 'positions' && is_array($positions))
                @php
                    $sum = $positions['summary'] ?? [];
                    $dyn = $positions['dynamics'] ?? [];
                @endphp
                @if($metricOn('positions', 'summary'))
                <div class="cabinet-sr-kpi-grid">
                    @foreach(['top3' => 'TOP-3', 'top10' => 'TOP-10', 'top30' => 'TOP-30', 'top100' => 'TOP-100'] as $k => $label)
                        <div class="cabinet-sr-kpi">
                            <div class="cabinet-sr-kpi__label">{{ $label }}</div>
                            <div class="cabinet-sr-kpi__value">{{ $sum[$k] ?? '—' }}</div>
                            @if(isset($sum['diff_' . $k]) && $sum['diff_' . $k] !== null && $sum['diff_' . $k] !== '')
                                <div class="cabinet-sr-kpi__delta">{{ $sum['diff_' . $k] }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>
                @endif
                @if(!empty($dyn['pairs']) && $metricOn('positions', 'dynamics'))
                    <div class="cabinet-sr-kpi-grid mt-2">
                        <div class="cabinet-sr-kpi">
                            <div class="cabinet-sr-kpi__label">{{ __('Improved') }}</div>
                            <div class="cabinet-sr-kpi__value is-up">{{ (int) ($dyn['improved'] ?? 0) }}</div>
                        </div>
                        <div class="cabinet-sr-kpi">
                            <div class="cabinet-sr-kpi__label">{{ __('Unchanged') }}</div>
                            <div class="cabinet-sr-kpi__value">{{ (int) ($dyn['unchanged'] ?? 0) }}</div>
                        </div>
                        <div class="cabinet-sr-kpi">
                            <div class="cabinet-sr-kpi__label">{{ __('Worsened') }}</div>
                            <div class="cabinet-sr-kpi__value is-down">{{ (int) ($dyn['worsened'] ?? 0) }}</div>
                        </div>
                    </div>
                @endif
                @if(!empty($positions['top_baskets']) && $metricOn('positions', 'top_baskets'))
                    <h3 class="h6 mt-3">{{ __('TOP baskets') }}</h3>
                    <div class="cabinet-sr-bars">
                        @php $maxB = max(1, max(array_column($positions['top_baskets'], 'value') ?: [1])); @endphp
                        @foreach($positions['top_baskets'] as $b)
                            <div class="cabinet-sr-bars__row">
                                <span>{{ $b['label'] }} ({{ $b['value'] }}@if(!empty($b['diff'])) {{ $b['diff'] }}@endif)</span>
                                <i style="width: {{ max(4, (int) round(100 * ($b['value'] ?? 0) / $maxB)) }}%"></i>
                            </div>
                        @endforeach
                    </div>
                @endif
                @if(!empty($positions['visibility_by_engine']) && $metricOn('positions', 'visibility_by_engine'))
                    <h3 class="h6 mt-3">{{ __('Visibility by search engine') }}</h3>
                    <div class="table-responsive">
                        <table class="cabinet-sr-data-table">
                            <thead>
                            <tr>
                                <th>{{ __('Search engine') }}</th>
                                <th>{{ __('Region') }}</th>
                                <th>TOP-10 %</th>
                                <th>TOP-10</th>
                                <th>{{ __('Queries') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($positions['visibility_by_engine'] as $row)
                                <tr>
                                    <td>{{ $row['engine'] }}</td>
                                    <td>{{ $row['region'] ?? '—' }}</td>
                                    <td>{{ $fmtNum($row['pct'] ?? 0, 1) }}%</td>
                                    <td>{{ $row['top10'] ?? '—' }}</td>
                                    <td>{{ $row['words'] ?? '—' }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                @if(!empty($positions['visibility_series']) && $metricOn('positions', 'visibility_series'))
                    <h3 class="h6 mt-3">{{ __('Visibility TOP-10') }}</h3>
                    <div class="cabinet-sr-spark" aria-hidden="true">
                        @php
                            $vals = array_column($positions['visibility_series'], 'pct');
                            $max = max(1, max($vals ?: [1]));
                        @endphp
                        @foreach($positions['visibility_series'] as $point)
                            <span title="{{ $point['date'] }}: {{ $point['pct'] }}%"
                                  style="height: {{ max(8, (int) round(48 * $point['pct'] / $max)) }}px"></span>
                        @endforeach
                    </div>
                    <p class="small text-secondary mb-0 mt-1">
                        {{ __('Share of queries in TOP-10 by day') }}
                        @php $last = end($positions['visibility_series']); @endphp
                        @if($last)
                            · {{ __('Now') }}: {{ $last['pct'] }}% ({{ $last['top10'] }}/{{ $last['words'] }})
                        @endif
                    </p>
                @endif
                @if(
                    ($metricOn('positions', 'phrases_improved') && !empty($positions['phrases']['improved']))
                    || ($metricOn('positions', 'phrases_worsened') && !empty($positions['phrases']['worsened']))
                )
                    @foreach(['improved' => __('Improved queries'), 'worsened' => __('Worsened queries')] as $bucket => $title)
                        @continue($bucket === 'improved' && !$metricOn('positions', 'phrases_improved'))
                        @continue($bucket === 'worsened' && !$metricOn('positions', 'phrases_worsened'))
                        @if(!empty($positions['phrases'][$bucket]))
                            <h3 class="h6 mt-3">{{ $title }}</h3>
                            <div class="table-responsive">
                                <table class="cabinet-sr-data-table">
                                    <thead>
                                    <tr>
                                        <th>{{ __('Query') }}</th>
                                        <th>{{ __('Search engine') }}</th>
                                        <th>{{ __('Was') }}</th>
                                        <th>{{ __('Became') }}</th>
                                        <th>{{ __('Landing URL') }}</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach($positions['phrases'][$bucket] as $row)
                                        <tr>
                                            <td>{{ $row['query'] ?? '—' }}</td>
                                            <td>{{ $row['engine'] ?? '—' }}</td>
                                            <td>{{ $row['pos_from'] ?? '—' }}</td>
                                            <td class="{{ $bucket === 'improved' ? 'text-success' : 'text-danger' }}">{{ $row['pos_to'] ?? '—' }}</td>
                                            <td class="cabinet-sr-url">@include('pages.partials.seo-reports-landing-link', ['value' => $row['url'] ?? null, 'domain' => $reportDomain])</td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    @endforeach
                @endif
                @if(!empty($positions['by_engine']) && $metricOn('positions', 'by_engine'))
                    <div class="table-responsive mt-3">
                        <table class="cabinet-sr-data-table">
                            <thead>
                            <tr>
                                <th>{{ __('Search engine') }}</th>
                                <th>{{ __('Region') }}</th>
                                <th>{{ __('Queries') }}</th>
                                <th>TOP-10</th>
                                <th>TOP-100</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($positions['by_engine'] as $row)
                                <tr>
                                    <td>{{ $row['engine'] }}</td>
                                    <td>{{ $row['region'] ?? '—' }}</td>
                                    <td>{{ $row['words'] ?? '—' }}</td>
                                    <td>{{ $row['top10'] ?? '—' }}</td>
                                    <td>{{ $row['top100'] ?? '—' }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                @if(!empty($positions['quick_wins']) && $metricOn('positions', 'quick_wins'))
                    <h3 class="h6 mt-3">{{ __('Quick wins') }}</h3>
                    <div class="table-responsive">
                        <table class="cabinet-sr-data-table">
                            <thead>
                            <tr>
                                <th>{{ __('Query') }}</th>
                                <th>{{ __('Search engine') }}</th>
                                <th>{{ __('Became') }}</th>
                                <th>{{ __('Landing URL') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($positions['quick_wins'] as $row)
                                <tr>
                                    <td>{{ $row['query'] ?? '—' }}</td>
                                    <td>{{ $row['engine'] ?? '—' }}</td>
                                    <td>{{ $row['pos_to'] ?? ($row['position'] ?? '—') }}</td>
                                    <td class="cabinet-sr-url">@include('pages.partials.seo-reports-landing-link', ['value' => $row['url'] ?? null, 'domain' => $reportDomain])</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                @if(!empty($positions['risk']) && $metricOn('positions', 'risk'))
                    <h3 class="h6 mt-3">{{ __('Risk list') }}</h3>
                    <div class="table-responsive">
                        <table class="cabinet-sr-data-table">
                            <thead>
                            <tr>
                                <th>{{ __('Query') }}</th>
                                <th>{{ __('Search engine') }}</th>
                                <th>{{ __('Was') }}</th>
                                <th>{{ __('Became') }}</th>
                                <th>{{ __('Landing URL') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($positions['risk'] as $row)
                                <tr>
                                    <td>{{ $row['query'] ?? '—' }}</td>
                                    <td>{{ $row['engine'] ?? '—' }}</td>
                                    <td>{{ $row['pos_from'] ?? ($row['was'] ?? '—') }}</td>
                                    <td class="text-danger">{{ $row['pos_to'] ?? ($row['now'] ?? '—') }}</td>
                                    <td class="cabinet-sr-url">@include('pages.partials.seo-reports-landing-link', ['value' => $row['url'] ?? null, 'domain' => $reportDomain])</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                @if(!empty($positions['groups']) && $metricOn('positions', 'groups'))
                    <h3 class="h6 mt-3">{{ __('Keyword groups') }}</h3>
                    <div class="table-responsive">
                        <table class="cabinet-sr-data-table">
                            <thead>
                            <tr>
                                <th>{{ __('Group') }}</th>
                                <th>{{ __('Queries') }}</th>
                                <th>TOP-3</th>
                                <th>TOP-10</th>
                                <th>TOP-30</th>
                                <th>TOP-100</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($positions['groups'] as $g)
                                @php $gw = max(1, (int) ($g['words'] ?? 0)); @endphp
                                <tr>
                                    <td>{{ $g['name'] ?? '—' }}</td>
                                    <td>{{ $fmtNum($g['words'] ?? 0) }}</td>
                                    <td>
                                        {{ $fmtNum($g['top3'] ?? 0) }}
                                        <span class="text-secondary small">({{ $fmtNum(100 * ((int) ($g['top3'] ?? 0)) / $gw, 0) }}%)</span>
                                    </td>
                                    <td>
                                        {{ $fmtNum($g['top10'] ?? 0) }}
                                        <span class="text-secondary small">({{ $fmtNum(100 * ((int) ($g['top10'] ?? 0)) / $gw, 0) }}%)</span>
                                    </td>
                                    <td>
                                        {{ $fmtNum($g['top30'] ?? 0) }}
                                        <span class="text-secondary small">({{ $fmtNum(100 * ((int) ($g['top30'] ?? 0)) / $gw, 0) }}%)</span>
                                    </td>
                                    <td>
                                        {{ $fmtNum($g['top100'] ?? 0) }}
                                        <span class="text-secondary small">({{ $fmtNum(100 * ((int) ($g['top100'] ?? 0)) / $gw, 0) }}%)</span>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                @if(!empty($positions['competitors']['urls']) && $metricOn('positions', 'competitors'))
                    <h3 class="h6 mt-3">{{ __('Competitors from monitoring') }}</h3>
                    <ul class="cabinet-sr-bullets">
                        @foreach($positions['competitors']['urls'] as $url)
                            <li>@include('pages.partials.seo-reports-landing-link', ['value' => $url, 'domain' => $reportDomain])</li>
                        @endforeach
                    </ul>
                    <p class="small text-secondary mb-0">
                        {{ __('Competitors tracked') }}: {{ (int) ($positions['competitors']['count'] ?? 0) }}
                    </p>
                @endif
                @if(!empty($positions['note']))
                    <p class="small text-secondary mb-0 mt-2">{{ $positions['note'] }}</p>
                @endif
                @if(!empty($comments['positions']))
                    <p class="cabinet-sr-comment mt-2">{{ $comments['positions'] }}</p>
                @endif
            @elseif($key === 'conversions' && is_array($conversions))
                @if(!empty($conversions['goals']) && $metricOn('conversions', 'goals'))
                    <h3 class="h6">{{ __('Conversions by goals') }}</h3>
                    <div class="table-responsive">
                        <table class="cabinet-sr-data-table">
                            <thead>
                            <tr>
                                <th>{{ __('Goal') }}</th>
                                <th>{{ __('Goal reaches') }}</th>
                                <th>{{ __('Conversion rate') }}</th>
                                <th>{{ __('Cost per conversion') }}</th>
                                <th>{{ __('Delta') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($conversions['goals'] as $goal)
                                @php
                                    $reaches = $goal['reaches']['value'] ?? null;
                                    $rate = $goal['conversion_rate']['value'] ?? null;
                                    $delta = $goal['reaches']['delta_pct'] ?? null;
                                    $cpa = $goal['cost_per_conversion'] ?? null;
                                @endphp
                                <tr>
                                    <td>{{ $goal['name'] ?? ('#' . ($goal['id'] ?? '')) }}</td>
                                    <td>{{ $reaches !== null ? $fmtNum($reaches) : '—' }}</td>
                                    <td>{{ $rate !== null ? $fmtNum($rate, 2) . '%' : '—' }}</td>
                                    <td>{{ $cpa !== null ? $fmtNum($cpa, 2) : '—' }}</td>
                                    <td>
                                        @if($delta !== null)
                                            <span class="{{ $delta > 0 ? 'text-success' : ($delta < 0 ? 'text-danger' : '') }}">
                                                {{ $delta > 0 ? '+' : '' }}{{ $fmtNum($delta, 1) }}%
                                            </span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="small text-secondary">{{ __('Cost per conversion needs ads spend') }}</p>
                @endif

                @if(!empty($conversions['channels_by_goal']) && $metricOn('conversions', 'channels_by_goal'))
                    @foreach($conversions['channels_by_goal'] as $goalId => $channelRows)
                        @if(!empty($channelRows))
                            @php
                                $goalTitle = '#' . $goalId;
                                foreach (($conversions['goals'] ?? []) as $g) {
                                    if ((int) ($g['id'] ?? 0) === (int) $goalId) {
                                        $goalTitle = (string) ($g['name'] ?? $goalTitle);
                                        break;
                                    }
                                }
                            @endphp
                            <h3 class="h6 mt-3">{{ __('Channels') }} · {{ $goalTitle }}</h3>
                            <div class="table-responsive">
                                <table class="cabinet-sr-data-table">
                                    <thead>
                                    <tr>
                                        <th>{{ __('Channel') }}</th>
                                        <th>{{ __('Goal reaches') }}</th>
                                        <th>{{ __('Conversion rate') }}</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach($channelRows as $row)
                                        <tr>
                                            <td>{{ $row['name'] ?? '—' }}</td>
                                            <td>{{ $fmtNum($row['reaches'] ?? 0) }}</td>
                                            <td>{{ $fmtNum($row['conversion_rate'] ?? 0, 2) }}%</td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                            @if(count($channelRows) > 0)
                                <div class="cabinet-sr-bars mt-2">
                                    @php $maxCh = max(1, max(array_column($channelRows, 'reaches') ?: [1])); @endphp
                                    @foreach(array_slice($channelRows, 0, 6) as $row)
                                        <div class="cabinet-sr-bars__row">
                                            <span>{{ $row['name'] ?? '—' }}</span>
                                            <i style="width: {{ max(4, (int) round(100 * ($row['reaches'] ?? 0) / $maxCh)) }}%"></i>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        @endif
                    @endforeach
                @endif

                @if(!empty($conversions['search_goals']) && $metricOn('conversions', 'search_goals'))
                    <h3 class="h6 mt-3">{{ __('Conversions from search') }}</h3>
                    <div class="table-responsive">
                        <table class="cabinet-sr-data-table">
                            <thead>
                            <tr>
                                <th>{{ __('Goal') }}</th>
                                <th>{{ __('Goal reaches') }}</th>
                                <th>{{ __('Conversion rate') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($conversions['search_goals'] as $goal)
                                <tr>
                                    <td>{{ $goal['name'] ?? '—' }}</td>
                                    <td>{{ $fmtNum($goal['reaches'] ?? 0) }}</td>
                                    <td>{{ $fmtNum($goal['conversion_rate'] ?? 0, 2) }}%</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                @if(!empty($conversions['ad_goals']) && $metricOn('conversions', 'ad_goals'))
                    <h3 class="h6 mt-3">{{ __('Conversions from ads') }}</h3>
                    <div class="table-responsive">
                        <table class="cabinet-sr-data-table">
                            <thead>
                            <tr>
                                <th>{{ __('Goal') }}</th>
                                <th>{{ __('Goal reaches') }}</th>
                                <th>{{ __('Conversion rate') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($conversions['ad_goals'] as $goal)
                                <tr>
                                    <td>{{ $goal['name'] ?? '—' }}</td>
                                    <td>{{ $fmtNum($goal['reaches'] ?? 0) }}</td>
                                    <td>{{ $fmtNum($goal['conversion_rate'] ?? 0, 2) }}%</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                @if(!empty($conversions['social_goals']) && $metricOn('conversions', 'social_goals'))
                    <h3 class="h6 mt-3">{{ __('Conversions from social') }}</h3>
                    <div class="table-responsive">
                        <table class="cabinet-sr-data-table">
                            <thead>
                            <tr>
                                <th>{{ __('Goal') }}</th>
                                <th>{{ __('Goal reaches') }}</th>
                                <th>{{ __('Conversion rate') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($conversions['social_goals'] as $goal)
                                <tr>
                                    <td>{{ $goal['name'] ?? '—' }}</td>
                                    <td>{{ $fmtNum($goal['reaches'] ?? 0) }}</td>
                                    <td>{{ $fmtNum($goal['conversion_rate'] ?? 0, 2) }}%</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                @if(!empty($comments['conversions']))
                    <p class="cabinet-sr-comment mt-2">{{ $comments['conversions'] }}</p>
                @elseif(!empty($conversions['comment']))
                    <p class="cabinet-sr-comment mt-2">{{ $conversions['comment'] }}</p>
                @endif
            @elseif($key === 'ecommerce')
                @php $ecom = is_array($snapshot['ecommerce'] ?? null) ? $snapshot['ecommerce'] : null; @endphp
                @if(is_array($ecom) && !empty($ecom['available']))
                    <div class="cabinet-sr-kpi-grid">
                        @foreach([
                            'users' => __('Users'),
                            'purchases' => __('Purchases'),
                            'revenue' => __('Revenue'),
                            'cr' => __('CR'),
                            'rpv' => 'RPV',
                            'aov' => __('Avg. check'),
                        ] as $ek => $elabel)
                            <div class="cabinet-sr-kpi">
                                <div class="cabinet-sr-kpi__label">{{ $elabel }}</div>
                                <div class="cabinet-sr-kpi__value">
                                    @if(in_array($ek, ['cr'], true))
                                        {{ $fmtNum($ecom[$ek] ?? 0, 2) }}%
                                    @elseif(in_array($ek, ['revenue', 'rpv', 'aov'], true))
                                        {{ $fmtNum($ecom[$ek] ?? 0, 2) }}
                                    @else
                                        {{ $fmtNum($ecom[$ek] ?? 0) }}
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                    @if(!empty($ecom['by_source']))
                        <h3 class="h6 mt-3">{{ __('Revenue by traffic source') }}</h3>
                        <div class="table-responsive">
                            <table class="cabinet-sr-data-table">
                                <thead><tr><th>{{ __('Channel') }}</th><th>{{ __('Purchases') }}</th><th>{{ __('Revenue') }}</th></tr></thead>
                                <tbody>
                                @foreach($ecom['by_source'] as $row)
                                    <tr>
                                        <td>{{ $metrikaLabel($row['name'] ?? '', $row['id'] ?? null) }}</td>
                                        <td>{{ $fmtNum($row['purchases'] ?? 0) }}</td>
                                        <td>{{ $fmtNum($row['revenue'] ?? 0, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        </div>
                        <div class="cabinet-sr-bars mt-2">
                            @php $maxRev = max(1, max(array_column($ecom['by_source'], 'revenue') ?: [1])); @endphp
                            @foreach(array_slice($ecom['by_source'], 0, 8) as $row)
                                <div class="cabinet-sr-bars__row">
                                    <span>{{ $metrikaLabel($row['name'] ?? '', $row['id'] ?? null) }}</span>
                                    <div class="cabinet-sr-bars__track">
                                        <i style="width: {{ max(4, (int) round(100 * ($row['revenue'] ?? 0) / $maxRev)) }}%"></i>
                                        <b>{{ $fmtNum($row['revenue'] ?? 0, 0) }}</b>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                    @if(!empty($ecom['categories']))
                        <h3 class="h6 mt-3">{{ __('Popular categories') }}</h3>
                        <div class="table-responsive">
                            <table class="cabinet-sr-data-table">
                                <thead><tr><th>{{ __('Category') }}</th><th>{{ __('Purchases') }}</th><th>{{ __('Revenue') }}</th></tr></thead>
                                <tbody>
                                @foreach($ecom['categories'] as $row)
                                    <tr>
                                        <td>{{ $row['name'] }}</td>
                                        <td>{{ $fmtNum($row['purchases'] ?? 0) }}</td>
                                        <td>{{ $fmtNum($row['revenue'] ?? 0, 2) }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                    @if(!empty($ecom['products']))
                        <h3 class="h6 mt-3">{{ __('Popular products') }}</h3>
                        <div class="table-responsive">
                            <table class="cabinet-sr-data-table">
                                <thead><tr><th>{{ __('Product') }}</th><th>{{ __('Purchases') }}</th><th>{{ __('Revenue') }}</th></tr></thead>
                                <tbody>
                                @foreach($ecom['products'] as $row)
                                    <tr>
                                        <td>{{ $row['name'] }}</td>
                                        <td>{{ $fmtNum($row['purchases'] ?? 0) }}</td>
                                        <td>{{ $fmtNum($row['revenue'] ?? 0, 2) }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                @else
                    <p class="small text-secondary mb-0">
                        {{ is_array($ecom) ? ($ecom['note'] ?? __('Ecommerce metrics require Metrika ecommerce tracking')) : __('Not connected') }}
                    </p>
                @endif
            @elseif($key === 'direct' && is_array($snapshot['direct'] ?? null))
                @php $direct = $snapshot['direct']; @endphp
                @if(!empty($direct['note']))
                    <p class="small text-secondary">{{ $direct['note'] }}</p>
                @endif
                @if($metricOn('direct', 'kpis'))
                <div class="cabinet-sr-kpi-grid">
                    @foreach($kpiLabels as $metric => $label)
                        @php
                            $kpi = $direct['kpis'][$metric] ?? null;
                            $value = $kpi['value'] ?? null;
                            $delta = $kpi['delta_pct'] ?? null;
                        @endphp
                        <div class="cabinet-sr-kpi">
                            <div class="cabinet-sr-kpi__label">{{ $label }}</div>
                            <div class="cabinet-sr-kpi__value">
                                @if($metric === 'bounce_rate' && $value !== null)
                                    {{ $fmtNum($value, 1) }}%
                                @elseif($value !== null)
                                    {{ $fmtNum($value, in_array($metric, ['page_depth'], true) ? 2 : 0) }}
                                @else
                                    —
                                @endif
                            </div>
                            @if($delta !== null)
                                <div class="cabinet-sr-kpi__delta">{{ $delta > 0 ? '+' : '' }}{{ $fmtNum($delta, 1) }}%</div>
                            @endif
                        </div>
                    @endforeach
                </div>
                @endif
                @if(!empty($direct['spend']) && $metricOn('direct', 'spend') && (isset($direct['spend']['cost']) || isset($direct['spend']['clicks'])))
                    <div class="cabinet-sr-kpi-grid mt-2">
                        @foreach(['clicks' => __('Clicks'), 'cost' => __('Ad spend'), 'cpc' => 'CPC', 'ctr' => 'CTR'] as $sk => $sl)
                            <div class="cabinet-sr-kpi">
                                <div class="cabinet-sr-kpi__label">{{ $sl }}</div>
                                <div class="cabinet-sr-kpi__value">
                                    @if(($direct['spend'][$sk] ?? null) !== null)
                                        {{ $fmtNum($direct['spend'][$sk], in_array($sk, ['cost', 'cpc', 'ctr'], true) ? 2 : 0) }}{{ $sk === 'ctr' ? '%' : '' }}
                                    @else
                                        —
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
                @if(!empty($direct['series_visits']) && $metricOn('direct', 'series_visits'))
                    @include('pages.partials.seo-reports-day-chart', [
                        'series' => $direct['series_visits'],
                        'title' => __('Ad visits by day'),
                        'unitLabel' => __('Visits'),
                    ])
                @endif
                @if(!empty($direct['engines']) && $metricOn('direct', 'engines'))
                    <h3 class="h6 mt-3">{{ __('Ad engines') }}</h3>
                    <div class="table-responsive">
                        <table class="cabinet-sr-data-table">
                            <thead><tr><th>{{ __('Engine') }}</th><th>{{ __('Visits') }}</th><th>{{ __('Bounce rate') }}</th></tr></thead>
                            <tbody>
                            @foreach($direct['engines'] as $row)
                                <tr>
                                    <td>{{ $row['name'] ?? '—' }}</td>
                                    <td>{{ $fmtNum($row['visits'] ?? 0) }}</td>
                                    <td>{{ $fmtNum($row['bounce_rate'] ?? 0, 1) }}%</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                @if(!empty($direct['campaigns']) && $metricOn('direct', 'campaigns'))
                    <h3 class="h6 mt-3">{{ __('Campaigns') }}</h3>
                    <div class="table-responsive">
                        <table class="cabinet-sr-data-table">
                            <thead><tr><th>{{ __('Campaign') }}</th><th>{{ __('Visits') }}</th><th>{{ __('Bounce rate') }}</th></tr></thead>
                            <tbody>
                            @foreach($direct['campaigns'] as $row)
                                <tr>
                                    <td>{{ $row['name'] ?? '—' }}</td>
                                    <td>{{ $fmtNum($row['visits'] ?? 0) }}</td>
                                    <td>{{ $fmtNum($row['bounce_rate'] ?? 0, 1) }}%</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                @if(!empty($direct['platforms']) && $metricOn('direct', 'platforms'))
                    <h3 class="h6 mt-3">{{ __('Ad platforms') }}</h3>
                    <div class="table-responsive">
                        <table class="cabinet-sr-data-table">
                            <thead><tr><th>{{ __('Platform') }}</th><th>{{ __('Visits') }}</th></tr></thead>
                            <tbody>
                            @foreach($direct['platforms'] as $row)
                                <tr>
                                    <td>{{ $row['name'] ?? '—' }}</td>
                                    <td>{{ $fmtNum($row['visits'] ?? 0) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                @if(!empty($direct['phrases']) && $metricOn('direct', 'phrases'))
                    <h3 class="h6 mt-3">{{ __('Search phrases') }}</h3>
                    <div class="table-responsive">
                        <table class="cabinet-sr-data-table">
                            <thead><tr><th>{{ __('Query') }}</th><th>{{ __('Visits') }}</th></tr></thead>
                            <tbody>
                            @foreach($direct['phrases'] as $row)
                                <tr>
                                    <td>{{ $row['name'] ?? '—' }}</td>
                                    <td>{{ $fmtNum($row['visits'] ?? 0) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                @if(!empty($direct['landings']) && $metricOn('direct', 'landings'))
                    <h3 class="h6 mt-3">{{ __('Top landing pages from ads') }}</h3>
                    <div class="table-responsive">
                        <table class="cabinet-sr-data-table">
                            <thead><tr><th>{{ __('URL') }}</th><th>{{ __('Visits') }}</th></tr></thead>
                            <tbody>
                            @foreach($direct['landings'] as $row)
                                <tr>
                                    <td class="cabinet-sr-url">@include('pages.partials.seo-reports-landing-link', ['value' => $row['name'] ?? null, 'domain' => $reportDomain])</td>
                                    <td>{{ $fmtNum($row['visits'] ?? 0) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                @if(!empty($direct['conversions']) && $metricOn('direct', 'conversions'))
                    <h3 class="h6 mt-3">{{ __('Conversions from ads') }}</h3>
                    <div class="table-responsive">
                        <table class="cabinet-sr-data-table">
                            <thead><tr><th>{{ __('Goal') }}</th><th>{{ __('Goal reaches') }}</th><th>{{ __('Conversion rate') }}</th></tr></thead>
                            <tbody>
                            @foreach($direct['conversions'] as $goal)
                                <tr>
                                    <td>{{ $goal['name'] ?? '—' }}</td>
                                    <td>{{ $fmtNum($goal['reaches'] ?? 0) }}</td>
                                    <td>{{ $fmtNum($goal['conversion_rate'] ?? 0, 2) }}%</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                @if(!empty($direct['fix']) && $metricOn('direct', 'fix'))
                    <h3 class="h6 mt-3">{{ __('What to fix') }}</h3>
                    <ul class="cabinet-sr-bullets">
                        @foreach($direct['fix'] as $hint)
                            <li>{{ $hint }}</li>
                        @endforeach
                    </ul>
                @endif
            @elseif($key === 'calls' && is_array($snapshot['calls'] ?? null))
                @php $calls = $snapshot['calls']; @endphp
                <div class="cabinet-sr-kpi-grid">
                    <div class="cabinet-sr-kpi"><div class="cabinet-sr-kpi__label">{{ __('Calls total') }}</div><div class="cabinet-sr-kpi__value">{{ $fmtNum($calls['total'] ?? 0) }}</div></div>
                    <div class="cabinet-sr-kpi"><div class="cabinet-sr-kpi__label">{{ __('First calls') }}</div><div class="cabinet-sr-kpi__value">{{ $fmtNum($calls['first'] ?? 0) }}</div></div>
                    <div class="cabinet-sr-kpi"><div class="cabinet-sr-kpi__label">{{ __('Missed calls') }}</div><div class="cabinet-sr-kpi__value">{{ $fmtNum($calls['missed'] ?? 0) }}</div></div>
                    <div class="cabinet-sr-kpi"><div class="cabinet-sr-kpi__label">{{ __('Talk avg') }}</div><div class="cabinet-sr-kpi__value">{{ $fmtDur($calls['talk_avg'] ?? 0) }}</div></div>
                    <div class="cabinet-sr-kpi"><div class="cabinet-sr-kpi__label">{{ __('Hold avg') }}</div><div class="cabinet-sr-kpi__value">{{ $fmtDur($calls['hold_avg'] ?? 0) }}</div></div>
                </div>
                @if(!empty($calls['by_channel']))
                    <h3 class="h6 mt-3">{{ __('Calls by channel') }}</h3>
                    <div class="table-responsive">
                        <table class="cabinet-sr-data-table">
                            <thead><tr><th>{{ __('Channel') }}</th><th>{{ __('Calls total') }}</th><th>{{ __('Missed calls') }}</th></tr></thead>
                            <tbody>
                            @foreach($calls['by_channel'] as $row)
                                <tr>
                                    <td>{{ $row['name'] }}</td>
                                    <td>{{ $fmtNum($row['calls'] ?? 0) }}</td>
                                    <td>{{ $fmtNum($row['missed'] ?? 0) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="cabinet-sr-bars mt-2">
                        @php $maxCalls = max(1, max(array_column($calls['by_channel'], 'calls') ?: [1])); @endphp
                        @foreach(array_slice($calls['by_channel'], 0, 8) as $row)
                            <div class="cabinet-sr-bars__row">
                                <span>{{ $row['name'] }}</span>
                                <i style="width: {{ max(4, (int) round(100 * ($row['calls'] ?? 0) / $maxCalls)) }}%"></i>
                            </div>
                        @endforeach
                    </div>
                @endif
            @elseif(in_array($key, ['gsc', 'webmaster'], true) && is_array($snapshot[$key] ?? null))
                @php $sc = $snapshot[$key]; @endphp
                @if(!empty($sc['note']))
                    <p class="small text-secondary">{{ $sc['note'] }}</p>
                @endif
                @if($metricOn($key, 'kpis'))
                <div class="cabinet-sr-kpi-grid">
                    @foreach(['clicks' => __('Clicks'), 'impressions' => __('Impressions'), 'ctr' => 'CTR', 'position' => __('Avg. position')] as $sk => $sl)
                        <div class="cabinet-sr-kpi">
                            <div class="cabinet-sr-kpi__label">{{ $sl }}</div>
                            <div class="cabinet-sr-kpi__value">
                                @if(($sc['kpis'][$sk] ?? null) !== null)
                                    {{ in_array($sk, ['ctr', 'position'], true) ? $fmtNum($sc['kpis'][$sk], 2) : $fmtNum($sc['kpis'][$sk]) }}{{ $sk === 'ctr' ? '%' : '' }}
                                @else
                                    —
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
                @endif
                @if(!empty($sc['queries']) && $metricOn($key, 'queries'))
                    <h3 class="h6 mt-3">{{ __('Top queries') }}</h3>
                    <div class="table-responsive">
                        <table class="cabinet-sr-data-table">
                            <thead><tr><th>{{ __('Query') }}</th><th>{{ __('Clicks') }}</th><th>{{ __('Impressions') }}</th><th>CTR</th><th>{{ __('Avg. position') }}</th></tr></thead>
                            <tbody>
                            @foreach(array_slice($sc['queries'], 0, 25) as $row)
                                <tr>
                                    <td>{{ $row['name'] ?? '—' }}</td>
                                    <td>{{ $fmtNum($row['clicks'] ?? 0) }}</td>
                                    <td>{{ $fmtNum($row['impressions'] ?? 0) }}</td>
                                    <td>{{ $row['ctr'] !== null ? $fmtNum($row['ctr'], 2) . '%' : '—' }}</td>
                                    <td>{{ $row['position'] !== null ? $fmtNum($row['position'], 1) : '—' }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                @if(!empty($sc['pages']) && $metricOn($key, 'pages'))
                    <h3 class="h6 mt-3">{{ __('Top pages') }}</h3>
                    <div class="table-responsive">
                        <table class="cabinet-sr-data-table">
                            <thead><tr><th>{{ __('URL') }}</th><th>{{ __('Clicks') }}</th><th>{{ __('Impressions') }}</th></tr></thead>
                            <tbody>
                            @foreach(array_slice($sc['pages'], 0, 25) as $row)
                                <tr>
                                    <td class="cabinet-sr-url">@include('pages.partials.seo-reports-landing-link', ['value' => $row['name'] ?? null, 'domain' => $reportDomain])</td>
                                    <td>{{ $fmtNum($row['clicks'] ?? 0) }}</td>
                                    <td>{{ $fmtNum($row['impressions'] ?? 0) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                @if($key === 'webmaster' && !empty($sc['diagnostics']) && $metricOn('webmaster', 'diagnostics'))
                    <h3 class="h6 mt-3">{{ __('Webmaster diagnostics errors') }}</h3>
                    <div class="table-responsive">
                        <table class="cabinet-sr-data-table">
                            <thead>
                            <tr>
                                <th>{{ __('Severity') }}</th>
                                <th>{{ __('Problem') }}</th>
                                <th>{{ __('Updated') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($sc['diagnostics'] as $problem)
                                <tr>
                                    <td>
                                        <span class="cabinet-sr-badge @if(($problem['severity'] ?? '') === 'FATAL') cabinet-sr-badge--warn @elseif(($problem['severity'] ?? '') === 'CRITICAL') cabinet-sr-badge--warn @else cabinet-sr-badge--manual @endif">
                                            {{ $problem['severity'] ?? '—' }}
                                        </span>
                                    </td>
                                    <td>{{ $problem['label'] ?? ($problem['code'] ?? '—') }}</td>
                                    <td class="cabinet-sr-table__muted">
                                        @if(!empty($problem['last_state_update']))
                                            {{ \Illuminate\Support\Str::limit(str_replace('T', ' ', (string) $problem['last_state_update']), 19, '') }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                @if($key === 'webmaster' && !empty($sc['meta_duplicates']) && $metricOn('webmaster', 'meta_duplicates'))
                    <h3 class="h6 mt-3">{{ __('Webmaster meta duplicates') }}</h3>
                    <ul class="cabinet-sr-plain-list">
                        @foreach($sc['meta_duplicates'] as $problem)
                            <li>
                                <strong>{{ $problem['label'] ?? ($problem['code'] ?? '—') }}</strong>
                                <span class="text-secondary">· {{ $problem['severity'] ?? '' }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
                @if($key === 'webmaster' && $metricOn('webmaster', 'filtered_pages') && is_array($sc['filtered_pages'] ?? null))
                    @php $fp = $sc['filtered_pages']; @endphp
                    @if(!empty($fp['summary']))
                        <h3 class="h6 mt-3">{{ __('Webmaster filtered pages') }}</h3>
                        <div class="cabinet-sr-kpi-grid">
                            @foreach(array_slice($fp['summary'], 0, 8) as $row)
                                <div class="cabinet-sr-kpi @if(($row['status'] ?? '') === 'LOW_QUALITY') is-warn @endif">
                                    <div class="cabinet-sr-kpi__label">{{ $row['label'] ?? ($row['status'] ?? '—') }}</div>
                                    <div class="cabinet-sr-kpi__value">{{ $fmtNum($row['count'] ?? 0) }}</div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                    @if(!empty($fp['low_quality']))
                        <h3 class="h6 mt-3">{{ __('Low-quality / low-value pages') }}</h3>
                        <div class="table-responsive">
                            <table class="cabinet-sr-data-table">
                                <thead>
                                <tr>
                                    <th>{{ __('URL') }}</th>
                                    <th>{{ __('Title') }}</th>
                                    <th>{{ __('Date') }}</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach(array_slice($fp['low_quality'], 0, 30) as $row)
                                    <tr>
                                        <td class="cabinet-sr-url">@include('pages.partials.seo-reports-landing-link', ['value' => $row['url'] ?? null, 'domain' => $reportDomain])</td>
                                        <td>{{ $row['title'] ?? '—' }}</td>
                                        <td class="cabinet-sr-table__muted">
                                            @if(!empty($row['event_date']))
                                                {{ \Illuminate\Support\Str::limit(str_replace('T', ' ', (string) $row['event_date']), 16, '') }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                @endif
            @elseif($key === 'google_ads' && is_array($snapshot['google_ads'] ?? null))
                @php $gads = $snapshot['google_ads']; @endphp
                @if(!empty($gads['note']))
                    <p class="small text-secondary">{{ $gads['note'] }}</p>
                @endif
                @if($metricOn('google_ads', 'kpis'))
                <div class="cabinet-sr-kpi-grid">
                    @foreach($kpiLabels as $metric => $label)
                        @php $kpi = $gads['kpis'][$metric] ?? null; $value = $kpi['value'] ?? null; @endphp
                        <div class="cabinet-sr-kpi">
                            <div class="cabinet-sr-kpi__label">{{ $label }}</div>
                            <div class="cabinet-sr-kpi__value">
                                @if($metric === 'bounce_rate' && $value !== null)
                                    {{ $fmtNum($value, 1) }}%
                                @elseif($value !== null)
                                    {{ $fmtNum($value, $metric === 'page_depth' ? 2 : 0) }}
                                @else
                                    —
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
                @endif
                @if(!empty($gads['campaigns']) && $metricOn('google_ads', 'campaigns'))
                    <h3 class="h6 mt-3">{{ __('Campaigns') }}</h3>
                    <div class="table-responsive">
                        <table class="cabinet-sr-data-table">
                            <thead><tr><th>{{ __('Campaign') }}</th><th>{{ __('Visits') }}</th><th>{{ __('Users') }}</th></tr></thead>
                            <tbody>
                            @foreach(array_slice($gads['campaigns'], 0, 25) as $row)
                                <tr>
                                    <td>{{ $row['name'] ?? '—' }}</td>
                                    <td>{{ $fmtNum($row['visits'] ?? 0) }}</td>
                                    <td>{{ $fmtNum($row['users'] ?? 0) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                @if(!empty($gads['landings']) && $metricOn('google_ads', 'landings'))
                    <h3 class="h6 mt-3">{{ __('Top landing pages from ads') }}</h3>
                    <div class="table-responsive">
                        <table class="cabinet-sr-data-table">
                            <thead><tr><th>{{ __('URL') }}</th><th>{{ __('Visits') }}</th></tr></thead>
                            <tbody>
                            @foreach($gads['landings'] as $row)
                                <tr><td class="cabinet-sr-url">@include('pages.partials.seo-reports-landing-link', ['value' => $row['name'] ?? null, 'domain' => $reportDomain])</td><td>{{ $fmtNum($row['visits'] ?? 0) }}</td></tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                @if(!empty($gads['phrases']) && $metricOn('google_ads', 'phrases'))
                    <h3 class="h6 mt-3">{{ __('Search phrases') }}</h3>
                    <div class="table-responsive">
                        <table class="cabinet-sr-data-table">
                            <thead><tr><th>{{ __('Query') }}</th><th>{{ __('Visits') }}</th></tr></thead>
                            <tbody>
                            @foreach($gads['phrases'] as $row)
                                <tr><td>{{ $row['name'] ?? '—' }}</td><td>{{ $fmtNum($row['visits'] ?? 0) }}</td></tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                @if(!empty($gads['conversions']) && $metricOn('google_ads', 'conversions'))
                    <h3 class="h6 mt-3">{{ __('Conversions from ads') }}</h3>
                    <div class="table-responsive">
                        <table class="cabinet-sr-data-table">
                            <thead><tr><th>{{ __('Goal') }}</th><th>{{ __('Goal reaches') }}</th><th>{{ __('Conversion rate') }}</th></tr></thead>
                            <tbody>
                            @foreach($gads['conversions'] as $goal)
                                <tr>
                                    <td>{{ $goal['name'] ?? '—' }}</td>
                                    <td>{{ $fmtNum($goal['reaches'] ?? 0) }}</td>
                                    <td>{{ $fmtNum($goal['conversion_rate'] ?? 0, 2) }}%</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            @elseif($key === 'vk_ads' && is_array($snapshot[$key] ?? null))
                @php $ads = $snapshot[$key]; $ak = $ads['kpis'] ?? []; @endphp
                @if(!empty($ads['note']))
                    <p class="small text-secondary">{{ $ads['note'] }}</p>
                @endif
                <div class="cabinet-sr-kpi-grid">
                    @foreach([
                        'reach' => __('Reach'),
                        'impressions' => __('Impressions'),
                        'clicks' => __('Clicks'),
                        'ctr' => 'CTR',
                        'cpc' => 'CPC',
                        'cpm' => 'CPM',
                        'spend' => __('Spend'),
                    ] as $sk => $sl)
                        <div class="cabinet-sr-kpi">
                            <div class="cabinet-sr-kpi__label">{{ $sl }}</div>
                            <div class="cabinet-sr-kpi__value">
                                @if(($ak[$sk] ?? null) !== null)
                                    {{ $fmtNum($ak[$sk], in_array($sk, ['ctr', 'cpc', 'cpm'], true) ? 2 : 0) }}{{ $sk === 'ctr' ? '%' : '' }}
                                @else
                                    —
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
                @if(!empty($ads['campaigns']))
                    <h3 class="h6 mt-3">{{ __('Campaigns') }}</h3>
                    <div class="table-responsive">
                        <table class="cabinet-sr-data-table">
                            <thead><tr><th>{{ __('Campaign') }}</th><th>{{ __('Impressions') }}</th><th>{{ __('Clicks') }}</th><th>CTR</th><th>{{ __('Spend') }}</th></tr></thead>
                            <tbody>
                            @foreach(array_slice($ads['campaigns'], 0, 25) as $row)
                                <tr>
                                    <td>{{ $row['name'] ?? '—' }}</td>
                                    <td>{{ $fmtNum($row['impressions'] ?? 0) }}</td>
                                    <td>{{ $fmtNum($row['clicks'] ?? 0) }}</td>
                                    <td>{{ ($row['ctr'] ?? null) !== null ? $fmtNum($row['ctr'], 2) . '%' : '—' }}</td>
                                    <td>{{ $fmtNum($row['spend'] ?? 0, 2) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                @if(!empty($ads['ads']))
                    <h3 class="h6 mt-3">{{ __('Ads') }}</h3>
                    <div class="table-responsive">
                        <table class="cabinet-sr-data-table">
                            <thead><tr><th>{{ __('Ad') }}</th><th>{{ __('Impressions') }}</th><th>{{ __('Clicks') }}</th><th>CTR</th></tr></thead>
                            <tbody>
                            @foreach(array_slice($ads['ads'], 0, 25) as $row)
                                <tr>
                                    <td>{{ $row['name'] ?? '—' }}</td>
                                    <td>{{ $fmtNum($row['impressions'] ?? 0) }}</td>
                                    <td>{{ $fmtNum($row['clicks'] ?? 0) }}</td>
                                    <td>{{ ($row['ctr'] ?? null) !== null ? $fmtNum($row['ctr'], 2) . '%' : '—' }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                @if(!empty($ads['demography']))
                    <h3 class="h6 mt-3">{{ __('Demography') }}</h3>
                    <div class="table-responsive">
                        <table class="cabinet-sr-data-table">
                            <thead><tr><th>{{ __('Segment') }}</th><th>{{ __('Clicks') }}</th><th>{{ __('Impressions') }}</th></tr></thead>
                            <tbody>
                            @foreach(array_slice($ads['demography'], 0, 20) as $row)
                                <tr>
                                    <td>{{ $row['name'] ?? '—' }}</td>
                                    <td>{{ $fmtNum($row['clicks'] ?? 0) }}</td>
                                    <td>{{ ($row['impressions'] ?? null) !== null ? $fmtNum($row['impressions']) : '—' }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            @elseif($key === 'vk_smm' && is_array($snapshot['vk_smm'] ?? null))
                @php $smm = $snapshot['vk_smm']; $skp = $smm['kpis'] ?? []; @endphp
                @if(!empty($smm['note']))
                    <p class="small text-secondary">{{ $smm['note'] }}</p>
                @endif
                <div class="cabinet-sr-kpi-grid">
                    @foreach([
                        'subscribers' => __('Subscribers'),
                        'reach' => __('Reach'),
                        'impressions' => __('Views'),
                        'visitors' => __('Visitors'),
                        'likes' => __('Likes'),
                        'comments' => __('Comments'),
                        'shares' => __('Shares'),
                        'posts' => __('Posts'),
                        'er' => 'ER',
                    ] as $sk => $sl)
                        <div class="cabinet-sr-kpi">
                            <div class="cabinet-sr-kpi__label">{{ $sl }}</div>
                            <div class="cabinet-sr-kpi__value">
                                @if(($skp[$sk] ?? null) !== null)
                                    {{ $fmtNum($skp[$sk], $sk === 'er' ? 2 : 0) }}{{ $sk === 'er' ? '%' : '' }}
                                @else
                                    —
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
                @if(!empty($smm['dynamics']))
                    <h3 class="h6 mt-3">{{ __('Subscribers dynamics') }}</h3>
                    <div class="table-responsive">
                        <table class="cabinet-sr-data-table">
                            <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Subscribers') }}</th><th>{{ __('Reach') }}</th><th>{{ __('Views') }}</th></tr></thead>
                            <tbody>
                            @foreach(array_slice($smm['dynamics'], 0, 31) as $row)
                                <tr>
                                    <td>{{ $row['date'] ?? '—' }}</td>
                                    <td>{{ ($row['subscribers'] ?? null) !== null ? $fmtNum($row['subscribers']) : '—' }}</td>
                                    <td>{{ $fmtNum($row['reach'] ?? 0) }}</td>
                                    <td>{{ $fmtNum($row['views'] ?? 0) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                @if(!empty($smm['engagement']))
                    <h3 class="h6 mt-3">{{ __('Engagement') }}</h3>
                    <p class="small mb-0">
                        {{ __('Likes') }}: {{ $fmtNum($smm['engagement']['likes'] ?? 0) }} ·
                        {{ __('Comments') }}: {{ $fmtNum($smm['engagement']['comments'] ?? 0) }} ·
                        {{ __('Shares') }}: {{ $fmtNum($smm['engagement']['shares'] ?? 0) }} ·
                        ER: {{ ($smm['engagement']['er'] ?? null) !== null ? $fmtNum($smm['engagement']['er'], 2) . '%' : '—' }}
                    </p>
                @endif
                @if(!empty($smm['top_posts']))
                    <h3 class="h6 mt-3">{{ __('Top posts') }}</h3>
                    <div class="table-responsive">
                        <table class="cabinet-sr-data-table">
                            <thead><tr><th>{{ __('Post') }}</th><th>{{ __('Likes') }}</th><th>{{ __('Comments') }}</th><th>{{ __('Shares') }}</th><th>{{ __('Views') }}</th></tr></thead>
                            <tbody>
                            @foreach(array_slice($smm['top_posts'], 0, 15) as $row)
                                <tr>
                                    <td>{{ $row['name'] ?? '—' }}</td>
                                    <td>{{ $fmtNum($row['likes'] ?? 0) }}</td>
                                    <td>{{ $fmtNum($row['comments'] ?? 0) }}</td>
                                    <td>{{ $fmtNum($row['shares'] ?? 0) }}</td>
                                    <td>{{ $fmtNum($row['views'] ?? 0) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                @if(!empty($smm['demography']))
                    <h3 class="h6 mt-3">{{ __('Audience demography') }}</h3>
                    <div class="table-responsive">
                        <table class="cabinet-sr-data-table">
                            <thead><tr><th>{{ __('Segment') }}</th><th>{{ __('Count') }}</th></tr></thead>
                            <tbody>
                            @foreach(array_slice($smm['demography'], 0, 20) as $row)
                                <tr>
                                    <td>{{ $row['name'] ?? '—' }}</td>
                                    <td>{{ $fmtNum($row['clicks'] ?? 0) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                @if(!empty($smm['post_stats']) && empty($smm['top_posts']))
                    <h3 class="h6 mt-3">{{ __('Post stats') }}</h3>
                    <div class="table-responsive">
                        <table class="cabinet-sr-data-table">
                            <thead><tr><th>{{ __('Post') }}</th><th>{{ __('Likes') }}</th><th>{{ __('Comments') }}</th></tr></thead>
                            <tbody>
                            @foreach(array_slice($smm['post_stats'], 0, 20) as $row)
                                <tr>
                                    <td>{{ $row['name'] ?? '—' }}</td>
                                    <td>{{ $fmtNum($row['likes'] ?? 0) }}</td>
                                    <td>{{ $fmtNum($row['comments'] ?? 0) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            @elseif(in_array($key, ['gsc', 'webmaster', 'direct', 'google_ads', 'vk_ads', 'vk_smm', 'calls'], true))
                <p class="small text-secondary mb-0">
                    {{ $section['message'] ?? __('Not connected') }}
                    @if(!$isPublic)
                        · <a href="{{ route('pages.seo-reports.settings', ['id' => $project->id]) }}">{{ __('Connect source') }}</a>
                    @endif
                </p>
                @if(!$isPublic && !empty($comments[$key]))
                    <p class="cabinet-sr-comment mt-2">{{ $comments[$key] }}</p>
                @endif
            @elseif($key === 'work_done')
                @php
                    $workDoneSnap = is_array($snapshot['work_done'] ?? null) ? $snapshot['work_done'] : [];
                    $workDoneItems = is_array($workDoneSnap['from_checklist'] ?? null) ? $workDoneSnap['from_checklist'] : [];
                @endphp
                @if($workDoneItems !== [])
                    <h3 class="h6">{{ __('From SEO checklist') }}</h3>
                    <ul class="cabinet-sr-bullets">
                        @foreach($workDoneItems as $item)
                            <li>
                                {{ $item['title'] ?? '—' }}
                                @if(!empty($item['done_at']))
                                    <span class="text-secondary small">· {{ \Carbon\Carbon::parse($item['done_at'])->format('d.m.Y') }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
                @if($report->work_done_text)
                    @if($workDoneItems !== [])
                        <h3 class="h6 mt-3">{{ __('Manager notes') }}</h3>
                    @endif
                    <div class="cabinet-sr-prose">{!! nl2br(e($report->work_done_text)) !!}</div>
                @elseif($workDoneItems === [])
                    <p class="small text-secondary mb-0">{{ __('Placeholder: manual content will appear here') }}</p>
                @endif
                @if(!empty($workDoneSnap['open_url']) && !$isPublic)
                    <p class="small mt-2 mb-0"><a href="{{ $workDoneSnap['open_url'] }}">{{ __('Open source project') }}</a></p>
                @endif
            @elseif($key === 'work_plan')
                @php
                    $workPlanSnap = is_array($snapshot['work_plan'] ?? null) ? $snapshot['work_plan'] : [];
                    $workPlanItems = is_array($workPlanSnap['from_checklist'] ?? null) ? $workPlanSnap['from_checklist'] : [];
                @endphp
                @if($workPlanItems !== [])
                    <h3 class="h6">{{ __('From SEO checklist') }}</h3>
                    <ul class="cabinet-sr-bullets">
                        @foreach($workPlanItems as $item)
                            <li>
                                {{ $item['title'] ?? '—' }}
                                @if(!empty($item['due_at']))
                                    <span class="text-secondary small {{ !empty($item['overdue']) ? 'text-danger' : '' }}">
                                        · {{ \Carbon\Carbon::parse($item['due_at'])->format('d.m.Y') }}
                                        @if(!empty($item['overdue'])) — {{ __('Overdue') }}@endif
                                    </span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
                @if($report->work_plan_text)
                    @if($workPlanItems !== [])
                        <h3 class="h6 mt-3">{{ __('Manager notes') }}</h3>
                    @endif
                    <div class="cabinet-sr-prose">{!! nl2br(e($report->work_plan_text)) !!}</div>
                @elseif($workPlanItems === [])
                    <p class="small text-secondary mb-0">{{ __('Placeholder: manual content will appear here') }}</p>
                @endif
                @if(!empty($workPlanSnap['open_url']) && !$isPublic)
                    <p class="small mt-2 mb-0"><a href="{{ $workPlanSnap['open_url'] }}">{{ __('Open source project') }}</a></p>
                @endif
            @elseif($key === 'insights')
                @php $recs = is_array($snapshot['recommendations'] ?? null) ? $snapshot['recommendations'] : []; @endphp
                @if(!empty($comments['recommendations']))
                    <div class="cabinet-sr-prose">{!! nl2br(e($comments['recommendations'])) !!}</div>
                @elseif($recs !== [])
                    <ul class="cabinet-sr-bullets">
                        @foreach($recs as $r)
                            <li><strong>{{ $r['priority'] ?? 'P3' }}</strong> — {{ $r['text'] ?? '' }}</li>
                        @endforeach
                    </ul>
                @elseif(!empty($insights))
                    <ul class="cabinet-sr-bullets">
                        @foreach($insights as $b)
                            <li>{{ $b }}</li>
                        @endforeach
                    </ul>
                @else
                    <p class="small text-secondary mb-0">{{ __('Placeholder: manual content will appear here') }}</p>
                @endif
            @elseif($key === 'kpi_goals')
                @if(!empty($kpiGoalsEval))
                    <div class="cabinet-sr-goals-strip cabinet-sr-goals-strip--section">
                        @foreach($kpiGoalsEval as $g)
                            @php
                                $pctRaw = $g['pct'] !== null ? (float) $g['pct'] : null;
                                $pctOver = $pctRaw !== null && $pctRaw > 150;
                                $pctLabel = $pctRaw === null
                                    ? '—'
                                    : ($pctOver
                                        ? '×' . $fmtNum($pctRaw / 100, 1)
                                        : $fmtNum($pctRaw, 1) . '%');
                            @endphp
                            <div class="cabinet-sr-goal-card cabinet-sr-goal-card--{{ $g['tone'] ?? 'yellow' }}">
                                <div class="cabinet-sr-goal-card__label">{{ __('Goal') }}: {{ $g['label'] }}</div>
                                <div class="cabinet-sr-goal-card__pct">{{ $pctLabel }}</div>
                                <div class="cabinet-sr-goal-card__meta">
                                    {{ $g['actual'] !== null ? $fmtNum($g['actual']) : '—' }}
                                    / {{ $fmtNum($g['target'] ?? 0) }}
                                    <span class="cabinet-sr-goal-card__meta-hint">{{ __('fact / target') }}</span>
                                </div>
                                <div class="cabinet-sr-goal-card__why">
                                    @if($pctOver)
                                        {{ __('Goal exceeded by factor', ['factor' => $fmtNum($pctRaw / 100, 1)]) }}
                                    @else
                                        {{ $g['why'] ?? '' }}
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="small text-secondary mb-0">
                        {{ __('No KPI goals') }}
                        @if(!$isPublic)
                            · <a href="{{ route('pages.seo-reports.settings', ['id' => $project->id]) }}">{{ __('Settings') }}</a>
                        @endif
                    </p>
                @endif
            @elseif($key === 'titlo_audit' && !empty($snapshot['titlo_audit']))
                @php
                    $a = $snapshot['titlo_audit'];
                    $b = is_array($a['buckets'] ?? null) ? $a['buckets'] : [];
                    $bucketLabels = is_array($a['bucket_labels'] ?? null) ? $a['bucket_labels'] : [
                        'critical' => __('Critical issues'),
                        'other' => __('Other issues'),
                        'important' => 'Важные замечания',
                        'warning' => __('Warnings'),
                        'info' => __('Info'),
                    ];
                    $topIssues = is_array($a['top_issues'] ?? null) ? $a['top_issues'] : [];
                    $pagesFetched = (int) ($a['pages_fetched'] ?? 0);
                    $bucketTotal = max(1, (int) ($b['critical'] ?? 0) + (int) ($b['other'] ?? 0) + (int) ($b['important'] ?? 0) + (int) ($b['warning'] ?? 0) + (int) ($b['info'] ?? 0));
                @endphp
                @if(!empty($a['summary']))
                    <p class="cabinet-sr-comment">{{ $a['summary'] }}</p>
                @endif
                <p class="small text-secondary mb-2">{{ $a['hint'] ?? __('SEO report audit hint') }}</p>
                <div class="cabinet-sr-kpi-grid">
                    <div class="cabinet-sr-kpi">
                        <div class="cabinet-sr-kpi__label">{{ __('Pages checked') }}</div>
                        <div class="cabinet-sr-kpi__value">{{ $pagesFetched > 0 ? $fmtNum($pagesFetched) : '—' }}</div>
                    </div>
                    @foreach(['critical' => 'is-down', 'other' => '', 'important' => '', 'warning' => '', 'info' => ''] as $bk => $tone)
                        <div class="cabinet-sr-kpi">
                            <div class="cabinet-sr-kpi__label">{{ $bucketLabels[$bk] ?? $bk }}</div>
                            <div class="cabinet-sr-kpi__value {{ $tone }}">{{ $fmtNum($b[$bk] ?? 0) }}</div>
                            <div class="cabinet-sr-kpi__compare" aria-hidden="true">
                                <span class="cabinet-sr-kpi__compare-row">
                                    <em></em>
                                    <i class="is-cur" style="width: {{ max(4, (int) round(100 * (int)($b[$bk] ?? 0) / $bucketTotal)) }}%"></i>
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
                @if(!empty($a['finished_at']))
                    <p class="small text-secondary mt-2 mb-0">
                        {{ __('Crawl finished at') }}:
                        {{ \Carbon\Carbon::parse($a['finished_at'])->format('d.m.Y H:i') }}
                    </p>
                @endif
                @if($topIssues !== [])
                    <h3 class="h6 mt-3">{{ __('Top issues to fix') }}</h3>
                    <div class="table-responsive">
                        <table class="cabinet-sr-data-table">
                            <thead>
                            <tr>
                                <th>{{ __('Issue') }}</th>
                                <th>{{ __('Severity') }}</th>
                                <th>{{ __('Count') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($topIssues as $issue)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $issue['title'] ?? $issue['code'] ?? '—' }}</div>
                                        @if(!empty($issue['what']))
                                            <div class="small text-secondary">{{ $issue['what'] }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="cabinet-sr-sev cabinet-sr-sev--{{ $issue['severity'] ?? 'info' }}">
                                            {{ $issue['severity_label'] ?? ($issue['severity'] ?? '') }}
                                        </span>
                                    </td>
                                    <td>{{ $fmtNum($issue['count'] ?? 0) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                @if(!empty($a['public_url']))
                    <p class="small mt-2 mb-0"><a href="{{ $a['public_url'] }}" target="_blank" rel="noopener">{{ __('Open full Site Audit') }}</a></p>
                @elseif(!empty($a['open_url']) && !$isPublic)
                    <p class="small mt-2 mb-0"><a href="{{ $a['open_url'] }}">{{ __('Open source project') }}</a></p>
                @endif
            @elseif($key === 'titlo_checklist' && !empty($snapshot['titlo_checklist']))
                @php $c = $snapshot['titlo_checklist']; @endphp
                <div class="cabinet-sr-kpi-grid">
                    <div class="cabinet-sr-kpi"><div class="cabinet-sr-kpi__label">{{ __('Closed in period') }}</div><div class="cabinet-sr-kpi__value">{{ (int)($c['closed_in_period'] ?? 0) }}</div></div>
                    <div class="cabinet-sr-kpi"><div class="cabinet-sr-kpi__label">{{ __('Overdue') }}</div><div class="cabinet-sr-kpi__value">{{ (int)($c['overdue'] ?? 0) }}</div></div>
                    <div class="cabinet-sr-kpi"><div class="cabinet-sr-kpi__label">{{ __('Progress') }}</div><div class="cabinet-sr-kpi__value">{{ (int)($c['progress_done'] ?? 0) }}/{{ (int)($c['progress_total'] ?? 0) }}</div></div>
                </div>
                @if(!empty($c['open_url']) && !$isPublic)
                    <p class="small mt-2 mb-0"><a href="{{ $c['open_url'] }}">{{ __('Open source project') }}</a></p>
                @endif
            @elseif($key === 'titlo_relevance' && !empty($snapshot['titlo_relevance']))
                @php
                    $rel = $snapshot['titlo_relevance'];
                    $avgPoints = $rel['avg_points'] ?? $rel['avg_score'] ?? null;
                    // Старые снимки ошибочно делили total_points на count_checks → 0.x вместо ~0–100.
                    if ($avgPoints !== null && (float) $avgPoints > 0 && (float) $avgPoints < 3
                        && !empty($rel['count_checks']) && (int) $rel['count_checks'] > 1) {
                        $avgPoints = round((float) $avgPoints * (int) $rel['count_checks'], 1);
                    }
                    $avgPos = $rel['avg_position'] ?? null;
                    $scoreTone = $rel['score_tone'] ?? (
                        $avgPoints === null ? 'neutral' : ((float)$avgPoints >= 70 ? 'good' : ((float)$avgPoints >= 40 ? 'warn' : 'bad'))
                    );
                    $posTone = $rel['position_tone'] ?? (
                        $avgPos === null ? 'neutral' : ((float)$avgPos <= 10 ? 'good' : ((float)$avgPos <= 30 ? 'warn' : 'bad'))
                    );
                @endphp
                @if(!empty($rel['summary']))
                    <p class="cabinet-sr-comment">{{ $rel['summary'] }}</p>
                @endif
                <p class="small text-secondary mb-2">{{ $rel['hint'] ?? __('SEO report relevance hint') }}</p>
                <div class="cabinet-sr-kpi-grid">
                    <div class="cabinet-sr-kpi">
                        <div class="cabinet-sr-kpi__label">{{ __('Relevance score') }}</div>
                        <div class="cabinet-sr-kpi__value cabinet-sr-tone--{{ $scoreTone }}">
                            {{ $avgPoints !== null ? $fmtNum($avgPoints, 1) : '—' }}
                            @if($avgPoints !== null)<span class="cabinet-sr-kpi__unit">/100</span>@endif
                        </div>
                        <div class="small text-secondary mt-1">{{ __('Relevance score tip') }}</div>
                        @if($avgPoints !== null)
                            <div class="cabinet-sr-meter mt-2" aria-hidden="true">
                                <i style="width: {{ max(4, min(100, (int) round((float)$avgPoints))) }}%"></i>
                            </div>
                        @endif
                    </div>
                    <div class="cabinet-sr-kpi">
                        <div class="cabinet-sr-kpi__label">{{ __('Avg. SERP position') }}</div>
                        <div class="cabinet-sr-kpi__value cabinet-sr-tone--{{ $posTone }}">
                            {{ $avgPos !== null ? $fmtNum($avgPos, 1) : '—' }}
                        </div>
                        <div class="small text-secondary mt-1">{{ __('Relevance position tip') }}</div>
                    </div>
                    <div class="cabinet-sr-kpi">
                        <div class="cabinet-sr-kpi__label">{{ __('Queries / landings') }}</div>
                        <div class="cabinet-sr-kpi__value">{{ $fmtNum($rel['count_sites'] ?? 0) }}</div>
                        <div class="small text-secondary mt-1">{{ __('Relevance sites tip') }}</div>
                    </div>
                    <div class="cabinet-sr-kpi">
                        <div class="cabinet-sr-kpi__label">{{ __('History runs') }}</div>
                        <div class="cabinet-sr-kpi__value">{{ $fmtNum($rel['count_checks'] ?? $rel['analyses'] ?? 0) }}</div>
                        <div class="small text-secondary mt-1">{{ __('Relevance checks tip') }}</div>
                    </div>
                </div>
                @if(!empty($rel['last_check']))
                    <p class="small text-secondary mt-2 mb-0">
                        {{ __('Last check') }}:
                        {{ \Carbon\Carbon::parse($rel['last_check'])->format('d.m.Y H:i') }}
                    </p>
                @endif
                @if(!empty($rel['open_url']) && !$isPublic)
                    <p class="small mt-2 mb-0"><a href="{{ $rel['open_url'] }}">{{ __('Open source project') }}</a></p>
                @endif
            @elseif($key === 'titlo_uptime' && !empty($snapshot['titlo_uptime']))
                @php $u = $snapshot['titlo_uptime']; @endphp
                <div class="cabinet-sr-kpi-grid">
                    <div class="cabinet-sr-kpi"><div class="cabinet-sr-kpi__label">{{ __('Uptime') }}</div><div class="cabinet-sr-kpi__value">{{ ($u['uptime_percent'] ?? null) !== null ? $fmtNum($u['uptime_percent'], 2) . '%' : '—' }}</div></div>
                    <div class="cabinet-sr-kpi"><div class="cabinet-sr-kpi__label">{{ __('Incidents') }}</div><div class="cabinet-sr-kpi__value">{{ !empty($u['broken']) ? __('Yes') : __('No') }}</div></div>
                    <div class="cabinet-sr-kpi"><div class="cabinet-sr-kpi__label">{{ __('Domain days left') }}</div><div class="cabinet-sr-kpi__value">{{ ($u['domain_days_left'] ?? null) !== null ? (int)$u['domain_days_left'] : '—' }}</div></div>
                </div>
                @if(!empty($u['open_url']) && !$isPublic)
                    <p class="small mt-2 mb-0"><a href="{{ $u['open_url'] }}">{{ __('Open source project') }}</a></p>
                @endif
            @else
                <p class="small text-secondary mb-0">{{ __('Placeholder: data will be collected on generate') }}</p>
            @endif

            @if($isPublic && $enabled && $clientVisible)
                <div class="cabinet-sr-react" data-sr-react-section="{{ $key }}">
                    <div class="cabinet-sr-react__label">{{ __('SEO report client feedback label') }}</div>
                    <div class="cabinet-sr-react__actions">
                        <button type="button" data-sr-react="like" title="{{ __('SEO report react like tip') }}">
                            👍 {{ __('Looks good') }}
                        </button>
                        <button type="button" data-sr-react="question" title="{{ __('SEO report react question tip') }}">
                            {{ __('Ask a question') }}
                        </button>
                        <button type="button" data-sr-react="clarify" title="{{ __('SEO report react clarify tip') }}">
                            {{ __('Need clarification') }}
                        </button>
                    </div>
                    <div class="cabinet-sr-react__form" data-sr-react-form hidden>
                        <label class="cabinet-sr-react__form-label" data-sr-react-form-label></label>
                        <textarea class="cabinet-sr-react__textarea"
                                  data-sr-react-text
                                  rows="3"
                                  maxlength="500"
                                  placeholder="{{ __('SEO report react comment placeholder') }}"></textarea>
                        <div class="cabinet-sr-react__form-actions">
                            <button type="button" data-sr-react-send>{{ __('Send to manager') }}</button>
                            <button type="button" data-sr-react-cancel>{{ __('Cancel') }}</button>
                        </div>
                    </div>
                    <p class="cabinet-sr-react__status" data-sr-react-status hidden></p>
                </div>
            @endif
        </section>
    @endforeach
    </div>
</div>
