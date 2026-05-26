@php
    $project = $report['project'] ?? [];
    $summary = $report['summary'] ?? [];
    $incidents = $report['incidents'] ?? [];
    $timeline = $report['timeline'] ?? [];
@endphp
<style>
    @page {
        background-color: #ffffff;
        margin-left: 14mm;
        margin-right: 14mm;
        margin-top: 22mm;
        margin-bottom: 18mm;
        margin-header: 8mm;
        margin-footer: 10mm;
    }
    body { font-family: dejavusans, sans-serif; font-size: 9pt; color: #334155; line-height: 1.45; background-color: #ffffff; margin: 0; padding: 0; }
    .report-body { width: 100%; margin: 0; padding: 0; }
    .sec { margin-bottom: 16pt; page-break-inside: avoid; }
    .sec-h {
        font-size: 12pt; font-weight: bold; color: #0f172a;
        margin: 0 0 10pt 0; padding-bottom: 5pt;
        border-bottom: 1.5pt solid #e2e8f0;
    }
    .sec-lead { font-size: 8pt; color: #64748b; margin: 0 0 8pt 0; line-height: 1.4; }
    .tbl { width: 100%; border-collapse: collapse; }
    .tbl th {
        background-color: #f1f5f9; color: #334155; font-size: 7.5pt; font-weight: bold;
        padding: 6pt 6pt; text-align: left; border-bottom: 1pt solid #cbd5e1;
    }
    .tbl td { padding: 5pt 6pt; border-bottom: 0.4pt solid #e2e8f0; font-size: 8pt; vertical-align: top; }
    .tbl tr.alt td { background-color: #f8fafc; }
    .kpi-t { font-size: 7pt; color: #64748b; text-transform: uppercase; letter-spacing: 0.4pt; line-height: 1.3; }
    .kpi-v { font-size: 15pt; font-weight: bold; color: #1e3f9e; line-height: 1.15; margin-top: 3pt; }
    .pill-ok { background-color: #ecfdf5; color: #047857; padding: 2pt 7pt; font-size: 7.5pt; font-weight: bold; }
    .pill-bad { background-color: #fef2f2; color: #b91c1c; padding: 2pt 7pt; font-size: 7.5pt; font-weight: bold; }
</style>

<div class="report-body">

<div class="sec">
    <div class="sec-h">{{ __('Site monitoring report') }}</div>
    <p class="sec-lead"><strong>{{ $project['name'] ?? '—' }}</strong><br>{{ $project['link'] ?? '' }}</p>
    @if(!empty($meta['generated_at']))
        <p class="sec-lead">{{ __('Generated') }}: {{ $meta['generated_at'] }}</p>
    @endif
</div>

<div class="sec">
    <div class="sec-h">{{ __('Summary') }}</div>
    <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:separate;border-spacing:6pt 0;">
        <tr>
            @foreach([
                [__('Site monitoring stats total checks'), $summary['total_checks'] ?? 0],
                [__('Site monitoring stats failures'), $summary['failed_checks'] ?? 0],
                [__('Site monitoring stats success rate'), $summary['success_rate'] !== null ? $summary['success_rate'] . '%' : '—'],
                [__('Uptime'), $summary['uptime_percent'] !== null ? $summary['uptime_percent'] . '%' : '—'],
            ] as $kpi)
                <td width="25%" style="background:#ffffff;border:0.5pt solid #e2e8f0;border-top:2.5pt solid #2f5de0;padding:11pt 9pt;">
                    <div class="kpi-t">{{ $kpi[0] }}</div>
                    <div class="kpi-v">{{ $kpi[1] }}</div>
                </td>
            @endforeach
        </tr>
    </table>
    <p class="sec-lead" style="margin-top:10pt;">
        {{ __('Status') }}: {{ $summary['current_status'] ?? '—' }}
        @if(isset($summary['current_code']) && $summary['current_code'] !== null)
            · HTTP {{ $summary['current_code'] }}
        @endif
        @if(!empty($summary['last_check']))
            · {{ __('Last check') }}: {{ $summary['last_check'] }}
        @endif
    </p>
</div>

@if(count($incidents) > 0)
<div class="sec">
    <div class="sec-h">{{ __('Site monitoring incidents') }}</div>
    <table class="tbl">
        <thead>
        <tr>
            <th>{{ __('Date') }}</th>
            <th>{{ __('Duration') }}</th>
            <th>HTTP</th>
            <th>{{ __('checks') }}</th>
        </tr>
        </thead>
        <tbody>
        @foreach($incidents as $i => $inc)
            <tr @if($i % 2) class="alt" @endif>
                <td>{{ $inc['started_at'] ?? '—' }} → {{ $inc['ended_at'] ?? '…' }}</td>
                <td>
                    @if(!empty($inc['ongoing']))
                        {{ __('Ongoing') }}
                    @else
                        {{ $inc['duration_minutes'] ?? '—' }} {{ __('min') }}
                    @endif
                </td>
                <td>{{ $inc['started_code'] ?? '—' }}</td>
                <td>{{ $inc['checks_while_down'] ?? 0 }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endif

@if(count($timeline) > 0)
<div class="sec">
    <div class="sec-h">{{ __('Site monitoring check history') }}</div>
    <p class="sec-lead">{{ __('Site monitoring report history limit', ['count' => count($timeline)]) }}</p>
    <table class="tbl">
        <thead>
        <tr>
            <th>{{ __('Date') }}</th>
            <th>{{ __('Status') }}</th>
            <th>HTTP</th>
            <th>{{ __('Uptime') }}</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        @foreach($timeline as $i => $row)
            <tr @if($i % 2) class="alt" @endif>
                <td>{{ $row['at'] ?? '—' }}</td>
                <td>
                    @if(!empty($row['broken']))
                        <span class="pill-bad">{{ $row['status'] ?? '—' }}</span>
                    @else
                        <span class="pill-ok">{{ $row['status'] ?? '—' }}</span>
                    @endif
                </td>
                <td>{{ $row['http_code'] ?? '—' }}</td>
                <td>{{ isset($row['uptime_percent']) ? $row['uptime_percent'] . '%' : '—' }}</td>
                <td>{{ $row['source_label'] ?? '' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@else
<div class="sec">
    <div class="sec-h">{{ __('Site monitoring check history') }}</div>
    <p class="sec-lead">{{ __('Site monitoring stats no history') }}</p>
</div>
@endif

</div>
