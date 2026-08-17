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
        <table class="table table-sm table-striped table-hover align-middle mb-0 cabinet-ta-zipf-table cabinet-ta-sortable-table">
            <thead class="table-light">
            <tr>
                <th class="cabinet-ta-th-sort text-end" style="width:3rem" data-sort-col="0" data-sort-type="num" scope="col" tabindex="0" role="columnheader" aria-sort="none">#</th>
                <th class="cabinet-ta-th-sort" data-sort-col="1" data-sort-type="str" scope="col" tabindex="0" role="columnheader" aria-sort="none">{{ __('Word') }}</th>
                <th class="cabinet-ta-th-sort text-end" data-sort-col="2" data-sort-type="num" scope="col" tabindex="0" role="columnheader" aria-sort="none">{{ $hasCompare ? __('Your page') : __('Actual values') }}</th>
                <th class="cabinet-ta-th-sort text-end" data-sort-col="3" data-sort-type="num" scope="col" tabindex="0" role="columnheader" aria-sort="none">{{ __('Ideal values') }}</th>
                @if($hasCompare)
                    <th class="cabinet-ta-th-sort text-end" data-sort-col="4" data-sort-type="num" scope="col" tabindex="0" role="columnheader" aria-sort="none">{{ $competitorLabel }}</th>
                    <th class="cabinet-ta-th-sort text-end" data-sort-col="5" data-sort-type="num" scope="col" tabindex="0" role="columnheader" aria-sort="none">Δ</th>
                @else
                    <th class="cabinet-ta-th-sort text-end" data-sort-col="4" data-sort-type="num" scope="col" tabindex="0" role="columnheader" aria-sort="none">Δ</th>
                @endif
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
                    <td class="text-end font-monospace text-secondary" data-order="{{ (int) $row['rank'] }}">{{ $row['rank'] }}</td>
                    <td data-order="{{ $row['word'] }}">
                        <span class="d-inline-flex flex-wrap align-items-baseline gap-1">
                            <strong class="cabinet-ta-exclude-term">{{ $row['word'] }}</strong>
                            @include('text-analyse.partials.add-to-exclude-btn', [
                                'term' => $row['word'],
                                'isPublicView' => $isPublicView ?? false,
                            ])
                        </span>
                    </td>
                    <td class="text-end font-monospace" data-order="{{ (int) $row['actual'] }}">{{ $row['actual'] }}</td>
                    <td class="text-end font-monospace text-secondary" data-order="{{ (int) $row['ideal'] }}">{{ $row['ideal'] }}</td>
                    @if($hasCompare)
                        <td class="text-end font-monospace" data-order="{{ $compActual !== null ? (int) $compActual : '' }}">{{ $compActual !== null ? $compActual : '—' }}</td>
                    @endif
                    <td class="text-end font-monospace {{ $deltaClass }}" data-order="{{ $delta }}">
                        @if($delta > 0)+@endif{{ $delta }}
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif
