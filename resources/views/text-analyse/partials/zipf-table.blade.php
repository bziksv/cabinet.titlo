@php
    use App\Support\TextAnalyzerPdfBranding;

    $graph = $graph ?? [];
    $competitorGraph = $competitorGraph ?? [];
    $hasCompare = !empty($hasCompare);
    $competitorLabel = $competitorLabel ?? ($hasCompare ? \App\TextAnalyzer::competitorLabel($competitorUrl ?? '') : __('Competitor'));
    $zipfRows = TextAnalyzerPdfBranding::zipfTableRows($graph);
    $zipfCompByWord = [];
    if ($hasCompare) {
        foreach (TextAnalyzerPdfBranding::zipfTableRows($competitorGraph) as $zr) {
            $zipfCompByWord[$zr['word']] = $zr['actual'];
        }
    }
@endphp
@if(!empty($zipfRows))
    <p class="text-secondary small mb-2 mt-3">
        {{ __('Word density') }} — {{ __('Actual values') }} / {{ __('Ideal values') }}
        @if($hasCompare)
            <span class="text-muted">· {{ __('Compare with competitor') }}</span>
        @endif
    </p>
    <div class="table-responsive cabinet-ta-table-wrap">
        <table class="table table-sm table-striped table-hover align-middle mb-0 cabinet-ta-zipf-table">
            <thead class="table-light">
            <tr>
                <th class="text-end" style="width:3rem">#</th>
                <th>{{ __('Word') }}</th>
                <th class="text-end">{{ $hasCompare ? __('Your page') : __('Actual values') }}</th>
                <th class="text-end">{{ __('Ideal values') }}</th>
                @if($hasCompare)
                    <th class="text-end">{{ $competitorLabel }}</th>
                @endif
                <th class="text-end">Δ</th>
            </tr>
            </thead>
            <tbody>
            @foreach($zipfRows as $row)
                @php
                    $compActual = $hasCompare ? ($zipfCompByWord[$row['word']] ?? null) : null;
                    $delta = (int) $row['delta'];
                    $deltaClass = $delta > 0 ? 'text-success' : ($delta < 0 ? 'text-warning' : '');
                @endphp
                <tr>
                    <td class="text-end font-monospace text-secondary">{{ $row['rank'] }}</td>
                    <td><strong>{{ $row['word'] }}</strong></td>
                    <td class="text-end font-monospace">{{ $row['actual'] }}</td>
                    <td class="text-end font-monospace text-secondary">{{ $row['ideal'] }}</td>
                    @if($hasCompare)
                        <td class="text-end font-monospace">{{ $compActual !== null ? $compActual : '—' }}</td>
                    @endif
                    <td class="text-end font-monospace {{ $deltaClass }}">
                        @if($delta > 0)+@endif{{ $delta }}
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif
