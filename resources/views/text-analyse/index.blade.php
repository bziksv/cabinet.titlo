@php $cabinetTaPageVersion = config('cabinet-text-analyzer.version', '1.0'); @endphp
@component('component.card', [
    'title' => __('Text Analyse'),
    'titleHtml' => e(__('Text Analyse')) . view('text-analyse.partials.version-badge', ['version' => $cabinetTaPageVersion])->render(),
])
    @slot('css')
        <link rel="stylesheet" href="{{ asset('css/cabinet-text-analyzer.css') }}?v={{ @filemtime(public_path('css/cabinet-text-analyzer.css')) ?: time() }}">
        <link rel="stylesheet" href="{{ asset('css/cabinet-esenin-text-check.css') }}?v={{ @filemtime(public_path('css/cabinet-esenin-text-check.css')) ?: time() }}">
    @endslot

    <div class="cabinet-text-analyzer-page">
        <p class="text-secondary small cabinet-ta-intro mb-3">
            {{ __('Word statistics, Zipf distribution, phrase analysis, uniqueness check and word clouds for page text or URL.') }}
        </p>

        @include('text-analyse.partials.form', [
            'request' => $request ?? [],
            'url' => $url ?? null,
            'canSaveUniquenessHistory' => $canSaveUniquenessHistory ?? false,
            'uniquenessLimit' => $uniquenessLimit ?? null,
            'uniquenessRemaining' => $uniquenessRemaining ?? null,
            'canCheckEsenin' => $canCheckEsenin ?? false,
            'eseninRemaining' => $eseninRemaining ?? null,
            'eseninLimit' => $eseninLimit ?? null,
            'batchMax' => $batchMax ?? 20,
        ])

        @isset($response)
            @include('text-analyse.partials.results', [
                'response' => $response,
                'request' => $request ?? [],
                'publicShare' => $publicShare ?? null,
            ])
        @endisset

        @include('partials.text-saved-checks', [
            'canSaveUniquenessHistory' => $canSaveUniquenessHistory ?? false,
            'uniquenessHistories' => $uniquenessHistories ?? [],
            'uniquenessHistoryCount' => $uniquenessHistoryCount ?? 0,
            'uniquenessHistoryLimit' => $uniquenessHistoryLimit ?? 0,
            'historyBaseUrl' => url('/text-analyzer/uniqueness-history'),
            'historyModule' => 'text-analyzer',
        ])
    </div>

    @slot('js')
        <script src="{{ asset('plugins/chart.js/3.9.1/chart.js') }}"></script>
        @include('partials.cabinet-html-editor-ckeditor')
        @include('text-analyse.partials.scripts')
    @endslot
@endcomponent
