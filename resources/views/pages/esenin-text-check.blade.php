@component('component.card', [
    'title' => __('Esenin text check'),
    'titleHtml' => e(__('Esenin text check')) . view('partials.cabinet-module-version-badge', ['configKey' => 'cabinet-esenin-text-check'])->render(),
])
    @slot('css')
        @include('partials.cabinet-html-editor-codemirror', ['part' => 'css'])
        <link rel="stylesheet" href="{{ asset('css/cabinet-esenin-text-check.css') }}?v={{ @filemtime(public_path('css/cabinet-esenin-text-check.css')) ?: time() }}">
    @endslot

    <div class="cabinet-esenin-page">
        <div class="cabinet-esenin-lead px-4 py-3 mb-3">
            <div class="d-flex gap-3 align-items-start">
                <span class="cabinet-esenin-lead__icon" aria-hidden="true">
                    <i class="bi bi-shield-check"></i>
                </span>
                <div>
                    <p class="mb-1 fw-semibold text-body">{{ __('Esenin text check lead title') }}</p>
                    <p class="mb-0 small text-secondary">{{ __('Esenin text check lead hint') }}</p>
                </div>
            </div>
        </div>

        <div class="cabinet-esenin-input" data-esenin-input>
            <div class="mb-3">
                <div class="d-flex flex-wrap align-items-end justify-content-between gap-2 mb-1">
                    <label class="form-label fw-semibold mb-0" for="cabinet-esenin-task-name">{{ __('Esenin text check task name label') }}</label>
                    <div class="dropdown" data-esenin-sessions-wrap>
                        <button type="button"
                                class="btn btn-sm btn-outline-secondary dropdown-toggle"
                                data-esenin-sessions-toggle
                                data-bs-toggle="dropdown"
                                aria-expanded="false">
                            {{ __('Esenin text check my tasks') }}
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end cabinet-esenin-sessions-menu" data-esenin-sessions-menu>
                            <li><span class="dropdown-item-text small text-secondary">{{ __('Esenin text check sessions loading') }}</span></li>
                        </ul>
                    </div>
                </div>
                <input type="text"
                       id="cabinet-esenin-task-name"
                       class="form-control"
                       maxlength="120"
                       data-esenin-task-name
                       placeholder="{{ __('Esenin text check task name placeholder') }}">
                <p class="small text-secondary mb-0 mt-1">{{ __('Esenin text check sessions hint') }}</p>
            </div>

            <ul class="nav nav-tabs mb-3 cabinet-esenin-tabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" type="button" data-esenin-source="text" aria-selected="true">
                        {{ __('Esenin text check tab text') }}
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" type="button" data-esenin-source="url" aria-selected="false">
                        {{ __('Esenin text check tab url') }}
                    </button>
                </li>
            </ul>

            <div class="cabinet-esenin-panel" data-esenin-panel="text">
                <label class="form-label fw-semibold mb-2">{{ __('Esenin text check text label') }}</label>
                @include('pages.partials.esenin-text-editor', ['maxChars' => $maxChars])
            </div>

            <div class="cabinet-esenin-panel d-none" data-esenin-panel="url">
                <label class="form-label fw-semibold" for="cabinet-esenin-url">{{ __('Esenin text check url label') }}</label>
                <input type="url" id="cabinet-esenin-url" class="form-control" placeholder="https://example.com/page/">
                <label class="form-label fw-semibold mt-3" for="cabinet-esenin-tbclass">{{ __('Esenin text check tbclass label') }}</label>
                <input type="text" id="cabinet-esenin-tbclass" class="form-control" placeholder=".content">
                <p class="small text-secondary mt-2 mb-0">{{ __('Esenin text check tbclass hint') }}</p>
            </div>

            <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mt-3">
                <p class="small text-secondary mb-0">{{ __('Esenin text check cost hint', ['cost' => $costPerCheck]) }}</p>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary" data-esenin-clear>{{ __('Clear') }}</button>
                    <button type="button" class="btn btn-primary" data-esenin-submit>{{ __('Esenin text check submit') }}</button>
                </div>
            </div>
        </div>

        <div class="cabinet-esenin-results d-none mt-4" data-esenin-results>
            <div class="cabinet-esenin-session-bar card shadow-sm mb-3">
                <div class="card-body py-2 px-3">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <div class="d-flex flex-wrap align-items-center gap-2 flex-grow-1">
                            <span class="small text-secondary d-none" data-esenin-session-label>{{ __('Esenin text check versions label') }}:</span>
                            <div class="cabinet-esenin-version-tabs d-none" data-esenin-version-tabs role="tablist" aria-label="{{ __('Esenin text check versions label') }}"></div>
                            <span class="small text-muted ms-auto ms-md-0" data-esenin-autosave-status aria-live="polite"></span>
                        </div>
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <button type="button" class="btn btn-sm btn-primary d-none" data-esenin-recheck>{{ __('Esenin text check recheck') }}</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="alert alert-warning py-2 px-3 small mb-3 d-none" data-esenin-stale-banner role="status">
                {{ __('Esenin text check stale banner') }}
            </div>

            <div class="row g-3">
                <div class="col-lg-2">
                    <div class="cabinet-esenin-score-nav" data-esenin-score-nav></div>
                </div>
                <div class="col-lg-7">
                    <div class="cabinet-esenin-text-view card shadow-sm">
                        <div class="card-body">
                            <div class="cabinet-esenin-text-view__wrap">
                                <div class="cabinet-esenin-legend small text-secondary mb-3 d-none" data-esenin-legend></div>
                                <div class="cabinet-esenin-text-view__content cabinet-esenin-text-view__content--editable"
                                     data-esenin-highlight
                                     contenteditable="true"
                                     spellcheck="true"
                                     aria-label="{{ __('Esenin text check edit label') }}"></div>
                                <div class="small text-secondary mt-3" data-esenin-stats></div>
                            </div>
                        </div>
                    </div>
                    @include('pages.partials.esenin-public-share')
                </div>
                <div class="col-lg-3">
                    <div class="card shadow-sm h-100">
                        <div class="card-body d-flex flex-column">
                            <h6 class="fw-semibold mb-2" data-esenin-panel-title>{{ __('Esenin text check params title') }}</h6>
                            <div class="table-responsive">
                                <table class="table table-sm mb-0">
                                    <tbody data-esenin-params></tbody>
                                </table>
                            </div>
                            <div class="cabinet-esenin-frequency-lists mt-3 d-none flex-grow-1" data-esenin-frequency-lists>
                                <ul class="nav nav-pills nav-fill mb-2 cabinet-esenin-frequency-tabs" role="tablist">
                                    <li class="nav-item" role="presentation">
                                        <button type="button" class="nav-link active py-1 px-2" data-esenin-frequency-tab="words">{{ __('Esenin text check words tab') }}</button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button type="button" class="nav-link py-1 px-2" data-esenin-frequency-tab="phrases">{{ __('Esenin text check phrases tab') }}</button>
                                    </li>
                                </ul>
                                <div class="cabinet-esenin-frequency-panel" data-esenin-frequency-panel="words"></div>
                                <div class="cabinet-esenin-frequency-panel d-none" data-esenin-frequency-panel="phrases"></div>
                            </div>
                            <div class="cabinet-esenin-hints mt-3 d-none" data-esenin-hints>
                                <h6 class="small fw-semibold text-uppercase text-secondary mb-2">{{ __('Esenin text check hints title') }}</h6>
                                <div class="cabinet-esenin-hints__body small" data-esenin-hints-body></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="cabinet-esenin-empty text-secondary small mt-4" data-esenin-empty>
            {{ __('Esenin text check empty state') }}
        </div>
    </div>

    <script type="application/json" id="cabinet-esenin-config">{!! json_encode([
        'maxChars' => $maxChars,
        'costPerCheck' => $costPerCheck,
        'limit' => $limit,
        'remaining' => $remaining,
        'modes' => $modes,
        'maxVersions' => $maxVersions,
        'autosaveDebounceMs' => $autosaveDebounceMs,
        'sessionsAvailable' => $sessionsAvailable,
        'publicShareAvailable' => $publicShareAvailable,
        'urls' => [
            'save' => route('pages.esenin-text-check.save'),
            'session' => url('/esenin-text-check/sessions'),
            'sessions' => url('/esenin-text-check/sessions'),
            'version' => url('/esenin-text-check/sessions'),
            'shareCreate' => route('pages.esenin-text-check.public.share.create'),
            'shareRevoke' => route('pages.esenin-text-check.public.share.revoke'),
        ],
        'shareLabels' => [
            'create' => __('Create public link'),
            'refresh' => __('Refresh public link'),
            'validUntil' => __('Valid until'),
            'copied' => __('Copied'),
            'revokeConfirm' => __('Revoke public link confirm'),
            'staleHint' => __('Esenin text check share stale hint'),
        ],
    ], JSON_UNESCAPED_UNICODE) !!}</script>

    @slot('js')
        @include('partials.cabinet-html-editor-ckeditor')
        @include('partials.cabinet-html-editor-codemirror', ['part' => 'js'])
        <script src="{{ asset('js/cabinet-esenin-text-check.js') }}?v={{ @filemtime(public_path('js/cabinet-esenin-text-check.js')) ?: time() }}"></script>
    @endslot
@endcomponent
