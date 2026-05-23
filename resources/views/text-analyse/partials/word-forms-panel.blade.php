@php
    $zones = [];
    if (!empty($word['wordForms']['inLink'] ?? null)) {
        $zones[] = ['title' => __('Link Zone'), 'forms' => $word['wordForms']['inLink']];
    }
    if (!empty($word['wordForms']['inText'] ?? null)) {
        $zones[] = ['title' => __('Text Area'), 'forms' => $word['wordForms']['inText']];
    }
@endphp

<div class="cabinet-ta-word-forms-panel">
    <div class="row g-3 @if(count($zones) === 1) row-cols-1 @else row-cols-1 row-cols-xl-2 @endif">
        @foreach($zones as $zone)
            <div class="col">
                <div class="cabinet-ta-word-zone">
                    <div class="cabinet-ta-word-zone__title">{{ $zone['title'] }}</div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-0 cabinet-ta-word-zone__table">
                            <thead class="table-light">
                            <tr>
                                <th scope="col" class="cabinet-ta-word-zone__col-form">{{ __('Word form') }}</th>
                                <th scope="col" class="text-end text-nowrap cabinet-ta-word-zone__col-count">{{ __('Count') }}</th>
                                <th scope="col" class="text-end text-nowrap cabinet-ta-word-zone__col-metric">TF</th>
                                <th scope="col" class="text-end text-nowrap cabinet-ta-word-zone__col-metric">IDF</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($zone['forms'] as $formEntry)
                                @php
                                    $parsedForm = \App\TextAnalyzer::parseWordFormEntry($formEntry);
                                @endphp
                                @if($parsedForm['lemma'] !== '' && $parsedForm['count'] !== null)
                                    <tr>
                                        <td class="cabinet-ta-word-zone__lemma">{{ $parsedForm['lemma'] }}</td>
                                        <td class="text-end font-monospace">{{ $parsedForm['count'] }}</td>
                                        <td class="text-end font-monospace text-secondary">{{ $parsedForm['tf'] ?? '—' }}</td>
                                        <td class="text-end font-monospace text-secondary">{{ $parsedForm['idf'] ?? '—' }}</td>
                                    </tr>
                                @endif
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
