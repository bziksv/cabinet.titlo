<!DOCTYPE html>
<html lang="ru">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $project->domain }} — SEO Checklist</title>
    <style>
        /* DomPDF: weight 600/синтетика ломает кириллицу — только regular/bold DejaVu */
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #0f172a;
            margin: 18px 20px 22px;
            line-height: 1.35;
        }
        h1 {
            font-family: DejaVu Sans, sans-serif;
            font-size: 16px;
            font-weight: bold;
            margin: 0 0 4px;
            color: #0f172a;
        }
        .meta {
            color: #64748b;
            margin-bottom: 10px;
            font-size: 9.5px;
        }
        .progress {
            margin: 6px 0 14px;
        }
        .bar {
            height: 7px;
            background: #e2e8f0;
            border-radius: 4px;
            overflow: hidden;
        }
        .bar span {
            display: block;
            height: 100%;
            background: #0f766e;
        }
        .stage {
            margin-bottom: 12px;
            page-break-inside: avoid;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            overflow: hidden;
        }
        .stage-head {
            background: #f1f5f9;
            padding: 6px 8px;
            border-bottom: 1px solid #e2e8f0;
        }
        .stage-head h2 {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            font-weight: bold;
            margin: 0;
            color: #0f172a;
        }
        .stage-head .pct {
            color: #64748b;
            font-weight: normal;
            font-size: 9px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            text-align: left;
            padding: 5px 8px;
            vertical-align: top;
            border-bottom: 1px solid #e2e8f0;
            font-family: DejaVu Sans, sans-serif;
        }
        th {
            color: #475569;
            font-weight: bold;
            font-size: 9px;
            background: #f8fafc;
            text-transform: none;
        }
        tr:last-child td {
            border-bottom: 0;
        }
        .task-title {
            font-weight: bold;
            font-size: 9.5px;
        }
        .task-help {
            color: #64748b;
            font-size: 8.5px;
            margin-top: 2px;
            font-weight: normal;
        }
        .done { color: #0f766e; font-weight: bold; }
        .skip { color: #64748b; }
        .blocked { color: #b45309; font-weight: bold; }
        .todo, .doing { color: #0f172a; }
        .badge {
            font-size: 8px;
            color: #c2410c;
            font-weight: bold;
        }
        .role {
            font-size: 8.5px;
            color: #334155;
        }
        .footer {
            margin-top: 14px;
            color: #94a3b8;
            font-size: 8px;
        }
    </style>
</head>
<body>
    <h1>SEO — {{ $project->domain }}</h1>
    <div class="meta">
        {{ $project->domain }}
        · {{ $project->progress_done }}/{{ $project->progress_total }} ({{ $pct }}%)
        · {{ $generatedAt }}
    </div>
    <div class="progress">
        <div class="bar"><span style="width: {{ max(0, min(100, (int) $pct)) }}%"></span></div>
    </div>

    @foreach($stages as $stage)
        @php
            $stagePct = $stage['total'] > 0 ? (int) round(100 * $stage['done'] / $stage['total']) : 0;
        @endphp
        <div class="stage">
            <div class="stage-head">
                <h2>
                    {{ $stage['title'] }}
                    <span class="pct">— {{ $stage['done'] }}/{{ $stage['total'] }} ({{ $stagePct }}%)</span>
                </h2>
            </div>
            <table>
                <thead>
                    <tr>
                        {{-- Явные UTF-8 строки: DomPDF + __('…') + font-weight:600 давали «??????» --}}
                        <th style="width: 52%">Задача</th>
                        <th style="width: 14%">Роль</th>
                        <th style="width: 18%">Статус</th>
                        <th style="width: 16%">Время</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($stage['items'] as $item)
                        <tr>
                            <td>
                                <div class="task-title">
                                    {{ $item->title }}
                                    @if($item->is_important)
                                        <span class="badge">★</span>
                                    @endif
                                    @if($item->repeat_rule)
                                        <span class="badge">↻</span>
                                    @endif
                                </div>
                                @php
                                    $reportHelp = trim((string) ($item->client_help ?? ''));
                                    if ($reportHelp === '') {
                                        $reportHelp = trim((string) ($item->help ?? ''));
                                    }
                                @endphp
                                @if($reportHelp !== '')
                                    <div class="task-help">{{ \Illuminate\Support\Str::limit($reportHelp, 160) }}</div>
                                @endif
                            </td>
                            <td class="role">{{ $roleLabels[$item->role] ?? $item->role }}</td>
                            <td class="{{ $item->status }}">{{ $statusLabels[$item->status] ?? $item->status }}</td>
                            <td class="role">{{ \App\Services\SeoChecklist\SeoChecklistService::formatDuration((int) $item->time_spent_seconds) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endforeach

    <div class="footer">cabinet.titlo.ru · SEO Checklist</div>
</body>
</html>
