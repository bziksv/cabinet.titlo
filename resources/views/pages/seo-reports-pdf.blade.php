<!DOCTYPE html>
<html lang="ru">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $project->domain }} — SEO Report</title>
    @php
        $pdfBrand = \App\SeoReports\SeoReportBrandColor::normalize($project->brand_color ?: '#0f172a');
        $pdfDomain = (string) ($project->domain ?? '');
        $pdfLanding = static function ($value) use ($pdfDomain) {
            $value = trim((string) ($value ?? ''));
            $href = \App\SeoReports\SeoReportLandingUrl::href($value !== '' ? $value : null, $pdfDomain);
            if ($href) {
                $label = e($value !== '' ? $value : $href);

                return '<a href="' . e($href) . '">' . $label . '</a>';
            }

            return e($value !== '' ? $value : '—');
        };
    @endphp
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #0f172a;
            margin: 16px 18px 20px;
            line-height: 1.35;
        }
        h1 {
            font-size: 16px;
            font-weight: bold;
            margin: 0 0 4px;
            color: {{ $pdfBrand }};
        }
        h2 {
            font-size: 12px;
            font-weight: bold;
            margin: 14px 0 6px;
            padding-bottom: 3px;
            border-bottom: 2px solid {{ $pdfBrand }};
        }
        .logo { max-height: 36px; margin-bottom: 8px; }
        h3 {
            font-size: 10.5px;
            font-weight: bold;
            margin: 10px 0 4px;
        }
        .meta { color: #64748b; margin-bottom: 8px; font-size: 9.5px; }
        .kpis { width: 100%; margin: 6px 0 10px; }
        .kpis td {
            width: 16.66%;
            border: 1px solid #e2e8f0;
            padding: 6px 7px;
            vertical-align: top;
        }
        .kpi-label { color: #64748b; font-size: 8px; text-transform: uppercase; }
        .kpi-value { font-size: 13px; font-weight: bold; margin-top: 2px; }
        .kpi-delta { font-size: 8.5px; color: #64748b; }
        table.data { width: 100%; border-collapse: collapse; margin: 4px 0 8px; }
        table.data th, table.data td {
            text-align: left;
            padding: 4px 6px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: top;
        }
        table.data th {
            color: #475569;
            font-weight: bold;
            font-size: 8.5px;
            background: #f8fafc;
        }
        .section { page-break-inside: avoid; margin-bottom: 8px; }
        .prose { white-space: pre-wrap; font-size: 9.5px; }
        .footer { margin-top: 16px; color: #94a3b8; font-size: 8px; }
        .up { color: #15803d; }
        .down { color: #b91c1c; }
        ul { margin: 4px 0 8px 16px; padding: 0; }
        li { margin-bottom: 2px; }
    </style>
</head>
<body>
@php
    $cover = $snapshot['cover'] ?? [];
    $traffic = $snapshot['traffic'] ?? null;
    $positions = $snapshot['positions'] ?? null;
    $conversions = $snapshot['conversions'] ?? null;
    $direct = $snapshot['direct'] ?? null;
    $ecommerce = $snapshot['ecommerce'] ?? null;
    $calls = $snapshot['calls'] ?? null;
    $scorecard = $snapshot['scorecard'] ?? [];
    $insights = $snapshot['insights'] ?? [];
    $recommendations = $snapshot['recommendations'] ?? [];
    $kpiGoals = $snapshot['kpi_goals'] ?? [];
    $fmt = static function ($v, $d = 0) {
        return number_format((float) $v, $d, ',', ' ');
    };
@endphp

@if(!empty($cover['agency']['logo_url']))
    <img class="logo" src="{{ $cover['agency']['logo_url'] }}" alt="">
@endif
<h1>{{ $cover['title'] ?? ('SEO-отчёт · ' . $project->domain) }}</h1>
<div class="meta">
    {{ $project->domain }}
    · {{ $cover['period_label'] ?? '' }}
    @if(!empty($cover['compare_label']))
        · сравнение: {{ $cover['compare_label'] }}
        @if(!empty($cover['compare_baseline']['reason']))
            ({{ $cover['compare_baseline']['reason'] }})
        @endif
    @endif
    · {{ $generatedAt }}
</div>

@if(!empty($cover['agency']['name']) || !empty($cover['manager']['name']))
    <div class="meta">
        @if(!empty($cover['agency']['name'])){{ $cover['agency']['name'] }}@endif
        @if(!empty($cover['manager']['name']))
            · менеджер: {{ $cover['manager']['name'] }}
            @if(!empty($cover['manager']['phone'])) {{ $cover['manager']['phone'] }}@endif
        @endif
    </div>
@endif

@if(!empty($scorecard))
    <table class="kpis">
        <tr>
            @foreach(array_slice($scorecard, 0, 6) as $card)
                <td>
                    <div class="kpi-label">{{ $card['label'] }}</div>
                    <div class="kpi-value">{{ $card['value'] }}</div>
                    @if(!empty($card['delta']))
                        <div class="kpi-delta">{{ $card['delta'] }}</div>
                    @endif
                </td>
            @endforeach
        </tr>
    </table>
@endif

@if($report->summary_text || !empty($insights))
    <div class="section">
        <h2>Резюме</h2>
        @if($report->summary_text)
            <div class="prose">{{ $report->summary_text }}</div>
        @else
            <ul>
                @foreach($insights as $b)
                    <li>{{ $b }}</li>
                @endforeach
            </ul>
        @endif
    </div>
@endif

@if(is_array($traffic))
    <div class="section">
        <h2>Трафик</h2>
        <table class="kpis">
            <tr>
                @foreach(['visits' => 'Визиты', 'users' => 'Пользователи', 'bounce_rate' => 'Отказы'] as $metric => $label)
                    @php $kpi = $traffic['kpis'][$metric] ?? null; @endphp
                    <td>
                        <div class="kpi-label">{{ $label }}</div>
                        <div class="kpi-value">
                            @if($kpi && $kpi['value'] !== null)
                                {{ $metric === 'bounce_rate' ? $fmt($kpi['value'], 1) . '%' : $fmt($kpi['value']) }}
                            @else
                                —
                            @endif
                        </div>
                        @if($kpi && $kpi['delta_pct'] !== null)
                            <div class="kpi-delta {{ $kpi['delta_pct'] >= 0 ? 'up' : 'down' }}">
                                {{ $kpi['delta_pct'] > 0 ? '+' : '' }}{{ $fmt($kpi['delta_pct'], 1) }}%
                            </div>
                        @endif
                    </td>
                @endforeach
            </tr>
        </table>

        @if(!empty($traffic['channels']))
            <h3>Каналы</h3>
            <table class="data">
                <thead>
                <tr>
                    <th>Канал</th>
                    <th>Визиты</th>
                    <th>Отказы</th>
                    <th>Глубина</th>
                </tr>
                </thead>
                <tbody>
                @foreach(array_slice($traffic['channels'], 0, 12) as $row)
                    <tr>
                        <td>{{ \App\SeoReports\SeoReportMetrikaLabels::label($row['name'] ?? '', $row['id'] ?? null) }}</td>
                        <td>{{ $fmt($row['visits'] ?? 0) }}</td>
                        <td>{{ $fmt($row['bounce_rate'] ?? 0, 1) }}%</td>
                        <td>{{ $fmt($row['page_depth'] ?? 0, 2) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif

        @if(!empty($traffic['search']['engines']))
            <h3>Поисковые системы</h3>
            <table class="data">
                <thead>
                <tr>
                    <th>ПС</th>
                    <th>Визиты</th>
                    <th>Дельта</th>
                </tr>
                </thead>
                <tbody>
                @foreach($traffic['search']['engines'] as $row)
                    <tr>
                        <td>{{ $row['name'] }}</td>
                        <td>{{ $fmt($row['visits'] ?? 0) }}</td>
                        <td>
                            @if(isset($row['visits_delta_pct']) && $row['visits_delta_pct'] !== null)
                                {{ $row['visits_delta_pct'] > 0 ? '+' : '' }}{{ $fmt($row['visits_delta_pct'], 1) }}%
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif

        @if(!empty($traffic['landings']))
            <h3>Топ посадочных</h3>
            <table class="data">
                <thead>
                <tr>
                    <th>URL</th>
                    <th>Визиты</th>
                    <th>Дельта</th>
                </tr>
                </thead>
                <tbody>
                @foreach(array_slice($traffic['landings'], 0, 15) as $row)
                    <tr>
                        <td>{!! $pdfLanding($row['name'] ?? null) !!}</td>
                        <td>{{ $fmt($row['visits'] ?? 0) }}</td>
                        <td>
                            @if(isset($row['visits_delta_pct']) && $row['visits_delta_pct'] !== null)
                                {{ $row['visits_delta_pct'] > 0 ? '+' : '' }}{{ $fmt($row['visits_delta_pct'], 1) }}%
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endif

@if(is_array($positions))
    <div class="section">
        <h2>Позиции</h2>
        @php $sum = $positions['summary'] ?? []; $dyn = $positions['dynamics'] ?? []; @endphp
        <table class="kpis">
            <tr>
                @foreach(['top3' => 'TOP-3', 'top10' => 'TOP-10', 'top100' => 'TOP-100'] as $k => $label)
                    <td>
                        <div class="kpi-label">{{ $label }}</div>
                        <div class="kpi-value">{{ $sum[$k] ?? '—' }}</div>
                    </td>
                @endforeach
                <td>
                    <div class="kpi-label">Улучшилось</div>
                    <div class="kpi-value up">{{ (int) ($dyn['improved'] ?? 0) }}</div>
                </td>
                <td>
                    <div class="kpi-label">Ухудшилось</div>
                    <div class="kpi-value down">{{ (int) ($dyn['worsened'] ?? 0) }}</div>
                </td>
            </tr>
        </table>

        @if(!empty($positions['phrases']['improved']))
            <h3>Рост позиций</h3>
            <table class="data">
                <thead>
                <tr>
                    <th>Запрос</th>
                    <th>Было</th>
                    <th>Стало</th>
                    <th>URL</th>
                </tr>
                </thead>
                <tbody>
                @foreach($positions['phrases']['improved'] as $row)
                    <tr>
                        <td>{{ $row['query'] }}</td>
                        <td>{{ $row['pos_from'] }}</td>
                        <td>{{ $row['pos_to'] }}</td>
                        <td>{!! $pdfLanding($row['url'] ?? null) !!}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endif

@if(is_array($conversions) && !empty($conversions['goals']))
    <div class="section">
        <h2>Конверсии</h2>
        <table class="data">
            <thead>
            <tr>
                <th>Цель</th>
                <th>Достижения</th>
                <th>Конверсия %</th>
                <th>Δ</th>
            </tr>
            </thead>
            <tbody>
            @foreach($conversions['goals'] as $goal)
                <tr>
                    <td>{{ $goal['name'] ?? '—' }}</td>
                    <td>{{ isset($goal['reaches']['value']) ? $fmt($goal['reaches']['value']) : '—' }}</td>
                    <td>{{ isset($goal['conversion_rate']['value']) ? $fmt($goal['conversion_rate']['value'], 2) . '%' : '—' }}</td>
                    <td>
                        @if(isset($goal['reaches']['delta_pct']) && $goal['reaches']['delta_pct'] !== null)
                            {{ $goal['reaches']['delta_pct'] > 0 ? '+' : '' }}{{ $fmt($goal['reaches']['delta_pct'], 1) }}%
                        @else
                            —
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
        @if(!empty($conversions['search_goals']))
            <h3>Конверсии из поиска</h3>
            <table class="data">
                <thead>
                <tr>
                    <th>Цель</th>
                    <th>Достижения</th>
                    <th>Конверсия %</th>
                </tr>
                </thead>
                <tbody>
                @foreach($conversions['search_goals'] as $goal)
                    <tr>
                        <td>{{ $goal['name'] ?? '—' }}</td>
                        <td>{{ $fmt($goal['reaches'] ?? 0) }}</td>
                        <td>{{ $fmt($goal['conversion_rate'] ?? 0, 2) }}%</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endif

@if(!empty($kpiGoals))
    <div class="section">
        <h2>Цели KPI</h2>
        <table class="data">
            <thead><tr><th>Цель</th><th>Факт</th><th>План</th><th>%</th></tr></thead>
            <tbody>
            @foreach($kpiGoals as $g)
                <tr>
                    <td>{{ $g['label'] ?? '—' }}</td>
                    <td>{{ $g['actual'] !== null ? $fmt($g['actual']) : '—' }}</td>
                    <td>{{ $fmt($g['target'] ?? 0) }}</td>
                    <td>{{ $g['pct'] !== null ? $fmt($g['pct'], 1) . '%' : '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif

@if(is_array($traffic) && !empty($traffic['devices']))
    <div class="section">
        <h2>Устройства</h2>
        <table class="data">
            <thead><tr><th>Устройство</th><th>Визиты</th></tr></thead>
            <tbody>
            @foreach(array_slice($traffic['devices'], 0, 8) as $row)
                <tr><td>{{ $row['name'] }}</td><td>{{ $fmt($row['visits'] ?? 0) }}</td></tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif

@if(is_array($traffic) && !empty($traffic['geo']))
    <div class="section">
        <h2>География</h2>
        <table class="data">
            <thead><tr><th>Регион</th><th>Визиты</th></tr></thead>
            <tbody>
            @foreach(array_slice($traffic['geo'], 0, 10) as $row)
                <tr><td>{{ $row['name'] }}</td><td>{{ $fmt($row['visits'] ?? 0) }}</td></tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif

@if(is_array($positions) && !empty($positions['phrases']['worsened']))
    <div class="section">
        <h2>Падение позиций</h2>
        <table class="data">
            <thead><tr><th>Запрос</th><th>Было</th><th>Стало</th></tr></thead>
            <tbody>
            @foreach(array_slice($positions['phrases']['worsened'], 0, 15) as $row)
                <tr>
                    <td>{{ $row['query'] }}</td>
                    <td>{{ $row['pos_from'] }}</td>
                    <td>{{ $row['pos_to'] }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif

@if(is_array($direct))
    <div class="section">
        <h2>Реклама (Метрика)</h2>
        @if(!empty($direct['note']))<div class="meta">{{ $direct['note'] }}</div>@endif
        @if(!empty($direct['landings']))
            <h3>Посадочные из рекламы</h3>
            <table class="data">
                <thead><tr><th>URL</th><th>Визиты</th></tr></thead>
                <tbody>
                @foreach(array_slice($direct['landings'], 0, 12) as $row)
                    <tr><td>{!! $pdfLanding($row['name'] ?? null) !!}</td><td>{{ $fmt($row['visits'] ?? 0) }}</td></tr>
                @endforeach
                </tbody>
            </table>
        @endif
        @if(!empty($direct['fix']))
            <h3>Что поправить</h3>
            <ul>@foreach($direct['fix'] as $h)<li>{{ $h }}</li>@endforeach</ul>
        @endif
    </div>
@endif

@if(is_array($ecommerce) && !empty($ecommerce['available']))
    <div class="section">
        <h2>Электронная коммерция</h2>
        <table class="kpis">
            <tr>
                <td><div class="kpi-label">Покупки</div><div class="kpi-value">{{ $fmt($ecommerce['purchases'] ?? 0) }}</div></td>
                <td><div class="kpi-label">Доход</div><div class="kpi-value">{{ $fmt($ecommerce['revenue'] ?? 0, 2) }}</div></td>
                <td><div class="kpi-label">CR</div><div class="kpi-value">{{ $fmt($ecommerce['cr'] ?? 0, 2) }}%</div></td>
                <td><div class="kpi-label">Средний чек</div><div class="kpi-value">{{ $fmt($ecommerce['aov'] ?? 0, 2) }}</div></td>
            </tr>
        </table>
    </div>
@endif

@if(is_array($calls))
    <div class="section">
        <h2>Звонки</h2>
        <table class="kpis">
            <tr>
                <td><div class="kpi-label">Всего</div><div class="kpi-value">{{ $fmt($calls['total'] ?? 0) }}</div></td>
                <td><div class="kpi-label">Первичные</div><div class="kpi-value">{{ $fmt($calls['first'] ?? 0) }}</div></td>
                <td><div class="kpi-label">Пропущенные</div><div class="kpi-value">{{ $fmt($calls['missed'] ?? 0) }}</div></td>
            </tr>
        </table>
    </div>
@endif

@if(!empty($recommendations))
    <div class="section">
        <h2>Рекомендации</h2>
        <ul>
            @foreach($recommendations as $r)
                <li><strong>{{ $r['priority'] ?? 'P3' }}</strong> — {{ $r['text'] ?? '' }}</li>
            @endforeach
        </ul>
    </div>
@endif

@if(!empty($snapshot['titlo_audit']))
    @php
        $audit = $snapshot['titlo_audit'];
        $b = is_array($audit['buckets'] ?? null) ? $audit['buckets'] : [];
        $topIssues = is_array($audit['top_issues'] ?? null) ? array_slice($audit['top_issues'], 0, 6) : [];
    @endphp
    <div class="section">
        <h2>Аудит сайта</h2>
        @if(!empty($audit['summary']))
            <div class="meta">{{ $audit['summary'] }}</div>
        @endif
        <div class="meta">
            Страниц: {{ (int)($audit['pages_fetched'] ?? 0) }}
            · грубые: {{ (int)($b['critical'] ?? 0) }}
            · прочие: {{ (int)($b['other'] ?? 0) }}
            · предупреждения: {{ (int)($b['warning'] ?? 0) }}
        </div>
        @if($topIssues !== [])
            <table>
                <thead><tr><th>Проблема</th><th>Важность</th><th>Кол-во</th></tr></thead>
                <tbody>
                @foreach($topIssues as $issue)
                    <tr>
                        <td>{{ $issue['title'] ?? $issue['code'] ?? '—' }}</td>
                        <td>{{ $issue['severity_label'] ?? ($issue['severity'] ?? '') }}</td>
                        <td>{{ (int)($issue['count'] ?? 0) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endif
@if(!empty($snapshot['titlo_checklist']))
    @php $c = $snapshot['titlo_checklist']; @endphp
    <div class="section">
        <h2>SEO-чеклист</h2>
        <div class="meta">Закрыто за период: {{ (int)($c['closed_in_period'] ?? 0) }} · просрочено: {{ (int)($c['overdue'] ?? 0) }}</div>
    </div>
@endif
@if(!empty($snapshot['titlo_relevance']))
    @php
        $rel = $snapshot['titlo_relevance'];
        $avgPoints = $rel['avg_points'] ?? null;
        if ($avgPoints !== null && (float) $avgPoints > 0 && (float) $avgPoints < 3
            && !empty($rel['count_checks']) && (int) $rel['count_checks'] > 1) {
            $avgPoints = round((float) $avgPoints * (int) $rel['count_checks'], 1);
        }
    @endphp
    <div class="section">
        <h2>Релевантность</h2>
        @if(!empty($rel['summary']))
            <div class="meta">{{ $rel['summary'] }}</div>
        @endif
        <div class="meta">
            Балл: {{ $avgPoints !== null ? $fmt($avgPoints, 1) . '/100' : '—' }}
            · позиция: {{ ($rel['avg_position'] ?? null) !== null ? $fmt($rel['avg_position'], 1) : '—' }}
            · запросов/URL: {{ (int)($rel['count_sites'] ?? 0) }}
            · проверок: {{ (int)($rel['count_checks'] ?? 0) }}
        </div>
    </div>
@endif
@if(!empty($snapshot['titlo_uptime']))
    @php $u = $snapshot['titlo_uptime']; @endphp
    <div class="section">
        <h2>Доступность сайта</h2>
        <div class="meta">Uptime: {{ $u['uptime_percent'] !== null ? $fmt($u['uptime_percent'], 2) . '%' : '—' }} · домен дней: {{ $u['domain_days_left'] !== null ? (int)$u['domain_days_left'] : '—' }}</div>
    </div>
@endif

@php
    $workDoneItems = is_array($snapshot['work_done']['from_checklist'] ?? null) ? $snapshot['work_done']['from_checklist'] : [];
    $workPlanItems = is_array($snapshot['work_plan']['from_checklist'] ?? null) ? $snapshot['work_plan']['from_checklist'] : [];
@endphp
@if($workDoneItems !== [] || $report->work_done_text)
    <div class="section">
        <h2>Выполненные работы</h2>
        @if($workDoneItems !== [])
            <div class="meta">Из SEO-чеклиста</div>
            <ul>
                @foreach($workDoneItems as $item)
                    <li>{{ $item['title'] ?? '—' }}@if(!empty($item['done_at'])) — {{ \Carbon\Carbon::parse($item['done_at'])->format('d.m.Y') }}@endif</li>
                @endforeach
            </ul>
        @endif
        @if($report->work_done_text)
            @if($workDoneItems !== [])<div class="meta">Комментарий менеджера</div>@endif
            <div class="prose">{{ $report->work_done_text }}</div>
        @endif
    </div>
@endif

@if($workPlanItems !== [] || $report->work_plan_text)
    <div class="section">
        <h2>План работ</h2>
        @if($workPlanItems !== [])
            <div class="meta">Из SEO-чеклиста</div>
            <ul>
                @foreach($workPlanItems as $item)
                    <li>{{ $item['title'] ?? '—' }}@if(!empty($item['due_at'])) — {{ \Carbon\Carbon::parse($item['due_at'])->format('d.m.Y') }}@endif</li>
                @endforeach
            </ul>
        @endif
        @if($report->work_plan_text)
            @if($workPlanItems !== [])<div class="meta">Комментарий менеджера</div>@endif
            <div class="prose">{{ $report->work_plan_text }}</div>
        @endif
    </div>
@endif

<div class="footer">SEO-отчёт Titlo · {{ $project->domain }} · {{ $generatedAt }}</div>
</body>
</html>
