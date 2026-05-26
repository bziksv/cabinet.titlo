@php
    $project = $report['project'] ?? [];
    $summary = $report['summary'] ?? [];
    $timeline = $report['timeline'] ?? [];
@endphp
<style>
    @page { background-color: #ffffff; margin-left: 14mm; margin-right: 14mm; margin-top: 22mm; margin-bottom: 18mm; margin-header: 8mm; margin-footer: 10mm; }
    body { font-family: dejavusans, sans-serif; font-size: 9pt; color: #334155; line-height: 1.45; }
    .sec { margin-bottom: 16pt; page-break-inside: avoid; }
    .sec-h { font-size: 12pt; font-weight: bold; color: #0f172a; margin: 0 0 10pt 0; padding-bottom: 5pt; border-bottom: 1.5pt solid #e2e8f0; }
    .sec-lead { font-size: 8pt; color: #64748b; margin: 0 0 8pt 0; }
    .tbl { width: 100%; border-collapse: collapse; }
    .tbl th { background-color: #f1f5f9; font-size: 7.5pt; padding: 6pt; text-align: left; border-bottom: 1pt solid #cbd5e1; }
    .tbl td { padding: 5pt 6pt; border-bottom: 0.4pt solid #e2e8f0; font-size: 8pt; vertical-align: top; }
    .kpi-t { font-size: 7pt; color: #64748b; text-transform: uppercase; }
    .kpi-v { font-size: 15pt; font-weight: bold; color: #1e3f9e; margin-top: 3pt; }
</style>

<div class="sec">
    <div class="sec-h">{{ __('Domain information report') }}</div>
    <p class="sec-lead"><strong>{{ $project['domain'] ?? '—' }}</strong></p>
    @if(!empty($meta['generated_at']))
        <p class="sec-lead">{{ __('Generated') }}: {{ $meta['generated_at'] }}</p>
    @endif
</div>

<div class="sec">
    <div class="sec-h">{{ __('Summary') }}</div>
    <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:separate;border-spacing:6pt 0;">
        <tr>
            @foreach([
                [__('Domain information stats total checks'), $summary['total_checks'] ?? 0],
                [__('Domain information stats failures'), $summary['failed_checks'] ?? 0],
                [__('Domain information stats success rate'), $summary['success_rate'] !== null ? $summary['success_rate'] . '%' : '—'],
            ] as $kpi)
                <td width="33%" style="border:0.5pt solid #e2e8f0;border-top:2.5pt solid #2f5de0;padding:11pt 9pt;">
                    <div class="kpi-t">{{ $kpi[0] }}</div>
                    <div class="kpi-v">{{ $kpi[1] }}</div>
                </td>
            @endforeach
        </tr>
    </table>
</div>

@if(count($timeline) > 0)
    <div class="sec">
        <div class="sec-h">{{ __('Domain information check history') }}</div>
        <table class="tbl">
            <thead>
            <tr>
                <th>{{ __('Date') }}</th>
                <th>{{ __('Status') }}</th>
                <th>{{ __('Domain information') }}</th>
                <th>DNS</th>
            </tr>
            </thead>
            <tbody>
            @foreach($timeline as $row)
                <tr>
                    <td>{{ $row['at'] ?? '—' }}</td>
                    <td>{{ $row['status'] ?? '—' }}</td>
                    <td>{{ $row['info_preview'] ?? '—' }}</td>
                    <td>{{ !empty($row['dns_changed']) ? __('Changed') : '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif
