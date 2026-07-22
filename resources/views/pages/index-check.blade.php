@component('component.card', [
    'title' => __('Index check'),
    'titleHtml' => e(__('Index check')) . view('partials.cabinet-module-version-badge', ['configKey' => 'cabinet-index-check'])->render(),
])
    @slot('css')
        <link rel="stylesheet" href="{{ asset('css/cabinet-index-check.css') }}?v={{ @filemtime(public_path('css/cabinet-index-check.css')) ?: time() }}">
    @endslot

    <div class="cabinet-ic-page">
        <div class="cabinet-ic-lead px-4 py-3 mb-3">
            <div class="d-flex gap-3 align-items-start">
                <span class="cabinet-ic-lead__icon" aria-hidden="true">
                    <i class="bi bi-search"></i>
                </span>
                <div>
                    <p class="mb-1 fw-semibold text-body">{{ __('Index check lead title') }}</p>
                    <p class="mb-0 small text-secondary">{{ __('Index check lead hint') }}</p>
                </div>
            </div>
        </div>

        <index-check-bulk
            step-title="{{ __('Index check step title') }}"
            urls-label="{{ __('Index check urls label') }}"
            urls-placeholder="{{ __('Index check urls placeholder') }}"
            urls-hint="{{ __('Index check urls hint') }}"
            engines-label="{{ __('Index check engines label') }}"
            yandex-label="{{ __('Index check yandex label') }}"
            google-label="{{ __('Index check google label') }}"
            google-domain-label="{{ __('Index check google domain label') }}"
            unify-www-label="{{ __('Index check unify www label') }}"
            submit-label="{{ __('Index check submit') }}"
            clear-label="{{ __('Clear') }}"
            progress-label="{{ __('Index check progress label') }}"
            results-title="{{ __('Index check results title') }}"
            export-label="{{ __('Export') }}"
            col-url="{{ __('url') }}"
            col-yandex="{{ __('Yandex') }}"
            col-google="{{ __('Google') }}"
            title-label="{{ __('Index check title label') }}"
            snippet-label="{{ __('Index check snippet label') }}"
            history-title="{{ __('Index check history title') }}"
            history-empty="{{ __('Index check history empty') }}"
            yes-label="{{ __('Yes') }}"
            no-label="{{ __('No') }}"
            err-label="{{ __('Error') }}"
            cost-hint="{{ __('Index check cost hint', ['cost' => $costPerEngine]) }}"
            confirm-title="{{ __('Index check confirm title') }}"
            confirm-urls="{{ __('Index check confirm urls', ['count' => ':count']) }}"
            confirm-engines="{{ __('Index check confirm engines', ['engines' => ':engines']) }}"
            confirm-cost="{{ __('Index check confirm cost', ['cost' => ':cost']) }}"
            confirm-remaining="{{ __('Index check confirm remaining', ['remaining' => ':remaining', 'limit' => ':limit']) }}"
            confirm-insufficient="{{ __('Index check confirm insufficient', ['needed' => ':needed', 'remaining' => ':remaining']) }}"
            confirm-cancel="{{ __('Index check confirm cancel') }}"
            search-placeholder="{{ __('Index check search placeholder') }}"
            search-empty="{{ __('Index check search empty') }}"
            stats-lines-label="{{ __('Index check url stats lines') }}"
            stats-unique-label="{{ __('Index check url stats unique') }}"
            stats-duplicates-label="{{ __('Index check url stats duplicates') }}"
            stats-to-check-label="{{ __('Index check url stats to check') }}"
            :limit="{{ $limit !== null ? (int) $limit : 'null' }}"
            :remaining="{{ $remaining !== null ? (int) $remaining : 'null' }}"
            :cost-per-engine="{{ (int) $costPerEngine }}"
            google-domains-json="{{ json_encode($googleDomains, JSON_UNESCAPED_UNICODE) }}"
            @php
                $demoIndex = \App\Support\DemoCabinet::isCurrentUser() ? \App\Support\DemoCabinet::indexCheckShowcase() : null;
                $historyPayload = collect($histories ?? [])->map(function ($row) {
                    return [
                        'id' => $row->id,
                        'url' => $row->url,
                        'created_at' => optional($row->created_at)->format('d.m.Y H:i'),
                        'yandex' => is_array($row->result) ? ($row->result['yandex'] ?? null) : null,
                        'google' => is_array($row->result) ? ($row->result['google'] ?? null) : null,
                    ];
                })->values();
            @endphp
            demo-items-json='@json($demoIndex["items"] ?? [])'
            demo-urls="{{ $demoIndex['urls'] ?? '' }}"
            histories-json='@json($historyPayload)'
        ></index-check-bulk>
    </div>
@endcomponent
