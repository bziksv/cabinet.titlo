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
    .tbl th.r { text-align: right; }
    .tbl td { padding: 5pt 6pt; border-bottom: 0.4pt solid #e2e8f0; font-size: 8pt; vertical-align: top; }
    .tbl td.r { text-align: right; font-variant-numeric: tabular-nums; }
    .tbl tr.alt td { background-color: #f8fafc; }
    .kpi-t { font-size: 7pt; color: #64748b; text-transform: uppercase; letter-spacing: 0.4pt; line-height: 1.3; }
    .kpi-v { font-size: 15pt; font-weight: bold; color: #1e3f9e; line-height: 1.15; margin-top: 3pt; }
    .pill-yes { background-color: #ecfdf5; color: #047857; padding: 2pt 7pt; font-size: 7.5pt; font-weight: bold; }
    .pill-no { background-color: #f8fafc; color: #94a3b8; padding: 2pt 7pt; font-size: 7.5pt; }
    .tag { display: inline-block; font-size: 7pt; font-weight: bold; padding: 2pt 6pt; letter-spacing: 0.3pt; }
    .tag-main { background: #dbeafe; color: #1e40af; }
    .tag-comp { background: #ffedd5; color: #9a3412; }
    .d-pos { color: #15803d; font-weight: bold; }
    .d-neg { color: #b45309; font-weight: bold; }
    .chart-box { border: 0.5pt solid #e2e8f0; padding: 8pt; background: #ffffff; margin-bottom: 8pt; }
    .legend { font-size: 7.5pt; color: #64748b; margin-top: 4pt; }
    .legend span { margin-right: 12pt; }
    .dot { display: inline-block; width: 8pt; height: 8pt; margin-right: 3pt; vertical-align: middle; }
    .page-break { page-break-before: always; }
</style>

<div class="report-body">

<div class="sec">
    <div class="sec-h">{{ __('Summary') }}</div>
    @if($hasCompare)<p class="sec-lead"><span class="tag tag-main">{{ __('Your page') }}</span></p>@endif
    <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:separate;border-spacing:6pt 0;">
        <tr>
            @foreach([
                [__('Number of words'), $general['countWords'] ?? 0],
                [__('Number of characters'), $general['textLength'] ?? 0],
                [__('Number of spaces'), $general['countSpaces'] ?? 0],
                [__('Number of characters without spaces'), $general['lengthWithOutSpaces'] ?? 0],
            ] as $kpi)
                <td width="25%" style="background:#ffffff;border:0.5pt solid #e2e8f0;border-top:2.5pt solid #2f5de0;padding:11pt 9pt;">
                    <div class="kpi-t">{{ $kpi[0] }}</div>
                    <div class="kpi-v">{{ number_format($kpi[1], 0, ',', ' ') }}</div>
                </td>
            @endforeach
        </tr>
    </table>
    @if($hasCompare)
        <p class="sec-lead" style="margin-top:12pt;"><span class="tag tag-comp">{{ $competitorLabel }}</span></p>
        <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:separate;border-spacing:6pt 0;">
            <tr>
                @foreach([
                    [__('Number of words'), $competitorGeneral['countWords'] ?? 0],
                    [__('Number of characters'), $competitorGeneral['textLength'] ?? 0],
                    [__('Number of spaces'), $competitorGeneral['countSpaces'] ?? 0],
                    [__('Number of characters without spaces'), $competitorGeneral['lengthWithOutSpaces'] ?? 0],
                ] as $kpi)
                    <td width="25%" style="background:#fffbeb;border:0.5pt solid #fde68a;border-top:2.5pt solid #d97706;padding:11pt 9pt;">
                        <div class="kpi-t">{{ $kpi[0] }}</div>
                        <div class="kpi-v" style="color:#b45309;">{{ number_format($kpi[1], 0, ',', ' ') }}</div>
                    </td>
                @endforeach
            </tr>
        </table>
    @endif
</div>

<div class="sec">
    <div class="sec-h">{{ __('Analysis settings') }}</div>
    <table class="tbl">
        <tbody>
        <tr>
            <td width="50%">{{ __('Track the text in the noindex tag') }} — <span class="{{ !empty($request['noIndex']) ? 'pill-yes' : 'pill-no' }}">{{ !empty($request['noIndex']) ? $yes : $no }}</span></td>
            <td width="50%">{{ __('Track words in the alt, title, and data-text attributes') }} — <span class="{{ !empty($request['hiddenText']) ? 'pill-yes' : 'pill-no' }}">{{ !empty($request['hiddenText']) ? $yes : $no }}</span></td>
        </tr>
        <tr class="alt">
            <td>{{ __('Exclude conjunctions, prepositions, pronouns') }} — <span class="{{ \App\TextAnalyzer::shouldExcludeConjunctionsPrepositionsPronouns($request ?? []) ? 'pill-yes' : 'pill-no' }}">{{ \App\TextAnalyzer::shouldExcludeConjunctionsPrepositionsPronouns($request ?? []) ? $yes : $no }}</span></td>
            <td>{{ __('Exclude') }} — @if(!empty($request['removeWords']) && !empty($request['listWords']))<span class="pill-yes">{{ $request['listWords'] }}</span>@else<span class="pill-no">{{ $no }}</span>@endif</td>
        </tr>
        @if($hasCompare)
        <tr>
            <td colspan="2">{{ __('Compare with competitor') }} — <span class="pill-yes">{{ $competitorLabel }}</span></td>
        </tr>
        @endif
        </tbody>
    </table>
</div>

@if(!empty($zipfRows))
<div class="sec">
    <div class="sec-h">{{ __('Text analysis according to Zipfs law') }}</div>
    @if(!empty($zipfChartPath))
    <div class="chart-box">
        <img src="{{ $zipfChartPath }}" width="182mm" height="67mm" style="display:block;margin:0 auto;" alt="" />
        <div class="legend">
            <span><span class="dot" style="background:#2f5de0;"></span>{{ $hasCompare ? __('Your page') : __('Actual values') }}</span>
            <span><span class="dot" style="background:#ea580c;"></span>{{ __('Ideal values') }}</span>
            @if($hasCompare)
            <span><span class="dot" style="background:#b45309;"></span>{{ $competitorLabel }}</span>
            @endif
        </div>
    </div>
    @endif
    <table class="tbl">
        <thead>
        <tr>
            <th class="r" width="5%">#</th>
            <th>{{ __('Word') }}</th>
            <th class="r">{{ $hasCompare ? __('Your page') : __('Actual values') }}</th>
            <th class="r">{{ __('Ideal values') }}</th>
            @if($hasCompare)<th class="r">{{ $competitorLabel }}</th>@endif
            <th class="r">Δ</th>
        </tr>
        </thead>
        <tbody>
        @php
            $zipfCompByWord = [];
            if ($hasCompare) {
                foreach ($zipfRowsCompetitor as $zr) {
                    $zipfCompByWord[$zr['word']] = $zr['actual'];
                }
            }
        @endphp
        @foreach(array_slice($zipfRows, 0, 12) as $i => $row)
            @php
                $compActual = $hasCompare ? ($zipfCompByWord[$row['word']] ?? null) : null;
            @endphp
            <tr class="{{ $i % 2 ? 'alt' : '' }}">
                <td class="r">{{ $row['rank'] }}</td>
                <td><strong>{{ $row['word'] }}</strong></td>
                <td class="r">{{ $row['actual'] }}</td>
                <td class="r">{{ $row['ideal'] }}</td>
                @if($hasCompare)<td class="r">{{ $compActual !== null ? $compActual : '—' }}</td>@endif
                <td class="r {{ $row['delta'] > 0 ? 'd-pos' : ($row['delta'] < 0 ? 'd-neg' : '') }}">{{ $row['delta'] > 0 ? '+' : '' }}{{ $row['delta'] }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endif

@if(!empty($cloudText) || !empty($cloudLinks) || !empty($cloudBoth))
<div class="sec">
    <div class="sec-h">{{ __('The clouds') }}</div>
    <p class="sec-lead">{{ __('Word cloud display limit hint') }}</p>
    @foreach([
        ['title' => __('Text Area'), 'main' => $cloudText, 'comp' => $cloudTextCompetitor],
        ['title' => __('Link Zone'), 'main' => $cloudLinks, 'comp' => $cloudLinksCompetitor],
        ['title' => __('Text and Link zone'), 'main' => $cloudBoth, 'comp' => $cloudBothCompetitor],
    ] as $zone)
        @if(empty($zone['main']) && empty($zone['comp'])) @continue @endif
        <p style="font-size:9pt;font-weight:bold;color:#475569;margin:10pt 0 4pt 0;">{{ $zone['title'] }}</p>
        <table class="tbl" style="margin-bottom:6pt;">
            <thead>
            <tr>
                <th width="6%" class="r">#</th>
                <th>{{ __('Word') }}</th>
                <th class="r" width="14%">{{ __('Your page') }}</th>
                @if($hasCompare)<th class="r" width="14%">{{ $competitorLabel }}</th>@endif
            </tr>
            </thead>
            <tbody>
            @php $maxRows = max(count($zone['main']), $hasCompare ? count($zone['comp'] ?? []) : 0); @endphp
            @for($i = 0; $i < min($maxRows, 10); $i++)
                <tr class="{{ $i % 2 ? 'alt' : '' }}">
                    <td class="r">{{ $i + 1 }}</td>
                    <td>{{ ($zone['main'][$i]['text'] ?? $zone['comp'][$i]['text'] ?? '') }}</td>
                    <td class="r">{{ $zone['main'][$i]['weight'] ?? '—' }}</td>
                    @if($hasCompare)<td class="r">{{ $zone['comp'][$i]['weight'] ?? '—' }}</td>@endif
                </tr>
            @endfor
            </tbody>
        </table>
    @endforeach
</div>
@endif

<div class="page-break"></div>

<div class="sec">
    <div class="sec-h">{{ __('General word analysis') }}@if($hasCompare) — {{ __('Compare with competitor') }}@endif</div>
    <table class="tbl">
        <thead>
        @if($hasCompare)
        <tr>
            <th rowspan="2">{{ __('Word') }}</th>
            <th colspan="3" style="text-align:center;border-left:1pt solid #cbd5e1;">{{ __('Your page') }}</th>
            <th colspan="3" style="text-align:center;border-left:1pt solid #cbd5e1;">{{ $competitorLabel }}</th>
            <th rowspan="2" class="r" style="border-left:1pt solid #cbd5e1;">Δ</th>
        </tr>
        <tr>
            <th class="r" style="border-left:1pt solid #cbd5e1;">{{ __('Common area') }}</th>
            <th class="r">{{ __('Text Area') }}</th>
            <th class="r">{{ __('Link Zone') }}</th>
            <th class="r" style="border-left:1pt solid #cbd5e1;">{{ __('Common area') }}</th>
            <th class="r">{{ __('Text Area') }}</th>
            <th class="r">{{ __('Link Zone') }}</th>
        </tr>
        @else
        <tr>
            <th>{{ __('Word') }}</th>
            <th class="r">{{ __('Density') }}</th>
            <th class="r">{{ __('Common area') }}</th>
            <th class="r">{{ __('Text Area') }}</th>
            <th class="r">{{ __('Link Zone') }}</th>
        </tr>
        @endif
        </thead>
        <tbody>
        @if($hasCompare)
            @foreach(array_slice($compareWords, 0, 40) as $i => $row)
                @php
                    $main = $row['main'] ?? null;
                    $comp = $row['competitor'] ?? null;
                    $delta = (int)($row['delta_total'] ?? 0);
                    $dClass = $delta > 0 ? 'd-pos' : ($delta < 0 ? 'd-neg' : '');
                @endphp
                <tr class="{{ $i % 2 ? 'alt' : '' }}">
                    <td><strong>{{ $row['text'] }}</strong></td>
                    <td class="r" style="border-left:1pt solid #e2e8f0;">{{ $main['total'] ?? '—' }}</td>
                    <td class="r">{{ $main['inText'] ?? '—' }}</td>
                    <td class="r">{{ $main['inLink'] ?? '—' }}</td>
                    <td class="r" style="border-left:1pt solid #e2e8f0;">{{ $comp['total'] ?? '—' }}</td>
                    <td class="r">{{ $comp['inText'] ?? '—' }}</td>
                    <td class="r">{{ $comp['inLink'] ?? '—' }}</td>
                    <td class="r {{ $dClass }}" style="border-left:1pt solid #e2e8f0;">@if($delta>0)+@endif{{ $delta }}</td>
                </tr>
            @endforeach
        @else
            @foreach($words as $i => $word)
                <tr class="{{ $i % 2 ? 'alt' : '' }}">
                    <td>{{ $word['text'] ?? '' }}</td>
                    <td class="r">{{ $word['density'] ?? '' }}</td>
                    <td class="r">{{ $word['total'] ?? '' }}</td>
                    <td class="r">{{ $word['inText'] ?? '' }}</td>
                    <td class="r">{{ $word['inLink'] ?? '' }}</td>
                </tr>
            @endforeach
        @endif
        </tbody>
    </table>
</div>

<div class="sec">
    <div class="sec-h">{{ __('Phrases of 2 words') }}</div>
    <table class="tbl">
        <thead>
        <tr>
            <th>{{ __('Phrase') }}</th>
            <th class="r">@if($hasCompare){{ __('Your page') }}@else{{ __('Repetitions') }}@endif</th>
            @if($hasCompare)<th class="r">{{ $competitorLabel }}</th><th class="r">Δ</th>@else<th class="r">{{ __('Density') }}</th>@endif
        </tr>
        </thead>
        <tbody>
        @if($hasCompare)
            @foreach(array_slice($comparePhrases, 0, 30) as $i => $row)
                @php $delta = (int)($row['delta_count'] ?? 0); $dClass = $delta > 0 ? 'd-pos' : ($delta < 0 ? 'd-neg' : ''); @endphp
                <tr class="{{ $i % 2 ? 'alt' : '' }}">
                    <td>{{ $row['phrase'] }}</td>
                    <td class="r">{{ $row['main']['count'] ?? '—' }}</td>
                    <td class="r">{{ $row['competitor']['count'] ?? '—' }}</td>
                    <td class="r {{ $dClass }}">@if($delta>0)+@endif{{ $delta }}</td>
                </tr>
            @endforeach
        @else
            @foreach($phrases as $i => $phrase)
                <tr class="{{ $i % 2 ? 'alt' : '' }}">
                    <td>{{ trim($phrase['phrase'] ?? '') }}</td>
                    <td class="r">{{ $phrase['count'] ?? '' }}</td>
                    <td class="r">{{ $phrase['density'] ?? '' }}</td>
                </tr>
            @endforeach
        @endif
        </tbody>
    </table>
</div>

</div>
