@component('component.card', [
    'title' => __('Counting text length'),
    'titleHtml' => e(__('Counting text length')) . view('partials.cabinet-module-version-badge', ['configKey' => 'cabinet-text-length'])->render(),
])
    @slot('css')
        <link rel="stylesheet" href="{{ asset('css/cabinet-text-length.css') }}?v={{ @filemtime(public_path('css/cabinet-text-length.css')) ?: time() }}">
        <style>
            #header-nav-bar .cabinet-header-limits-menu tr.TextLength {
                background: oldlace;
            }
        </style>
    @endslot

    <div class="cabinet-text-length-page">
        <p class="text-secondary cabinet-tl-hint mb-4">
            {{ __('Count characters, words and spaces in your text. Optional SEO fields for title, description and H1 length.') }}
        </p>

        <div class="row g-3 mb-4 cabinet-tl-kpi is-empty" aria-live="polite">
            <div class="col-6 col-lg">
                <div class="info-box shadow-sm">
                    <span class="info-box-icon text-bg-secondary">
                        <i class="bi bi-text-paragraph" aria-hidden="true"></i>
                    </span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ __('Characters with spaces') }}</span>
                        <span class="info-box-number" data-tl-kpi-chars>—</span>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg">
                <div class="info-box shadow-sm">
                    <span class="info-box-icon text-bg-primary">
                        <i class="bi bi-fonts" aria-hidden="true"></i>
                    </span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ __('Characters without spaces') }}</span>
                        <span class="info-box-number" data-tl-kpi-chars-no-sp>—</span>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg">
                <div class="info-box shadow-sm">
                    <span class="info-box-icon text-bg-success">
                        <i class="bi bi-chat-square-text" aria-hidden="true"></i>
                    </span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ __('Total words') }}</span>
                        <span class="info-box-number" data-tl-kpi-words>—</span>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg">
                <div class="info-box shadow-sm">
                    <span class="info-box-icon text-bg-info">
                        <i class="bi bi-list-ol" aria-hidden="true"></i>
                    </span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ __('Lines') }}</span>
                        <span class="info-box-number" data-tl-kpi-lines>—</span>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg">
                <div class="info-box shadow-sm">
                    <span class="info-box-icon text-bg-warning">
                        <i class="bi bi-distribute-spacing-horizontal" aria-hidden="true"></i>
                    </span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ __('Total spaces') }}</span>
                        <span class="info-box-number" data-tl-kpi-spaces>—</span>
                    </div>
                </div>
            </div>
        </div>

        <section class="mb-4" aria-labelledby="cabinet-tl-step-1-title">
            <h6 class="cabinet-tl-step-title" id="cabinet-tl-step-1-title">
                <span class="cabinet-tl-step-badge">1</span>
                <span>{{ __('Step 1') }} — {{ __('Text and SEO fields') }}</span>
            </h6>

            <div class="cabinet-tl-input-pane">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                    <label class="form-label mb-0" for="cabinet-tl-text">{{ __('Enter text') }}</label>
                    <span class="text-muted small">
                        {{ __('Characters') }}:
                        <span class="badge text-bg-light border cabinet-tl-char-badge" data-tl-char-count>0</span>
                        <span class="text-muted">/ {{ number_format((int) config('cabinet-text-length.max_chars', 38600), 0, ',', ' ') }}</span>
                    </span>
                </div>
                <textarea id="cabinet-tl-text"
                          class="form-control cabinet-tl-textarea"
                          rows="12"
                          placeholder="{{ __('Paste or type your text here') }}"></textarea>
                <p class="text-danger small mt-1 mb-0 cabinet-tl-over-limit" data-tl-over-limit hidden>
                    {{ __('Text exceeds the limit of :count characters', ['count' => number_format((int) config('cabinet-text-length.max_chars', 38600), 0, ',', ' ')]) }}
                </p>

                <p class="text-muted small mt-3 mb-2">{{ __('Optional SEO meta lengths') }}</p>
                <div class="row g-2">
                    <div class="col-md-4">
                        <label class="form-label small mb-1" for="cabinet-tl-title">{{ __('Page title (Title)') }}</label>
                        <input type="text" class="form-control form-control-sm" id="cabinet-tl-title" autocomplete="off" placeholder="{{ __('Page title meta tag') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small mb-1" for="cabinet-tl-description">{{ __('Meta description') }}</label>
                        <input type="text" class="form-control form-control-sm" id="cabinet-tl-description" autocomplete="off" placeholder="{{ __('Meta description tag') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small mb-1" for="cabinet-tl-h1">{{ __('H1 heading') }}</label>
                        <input type="text" class="form-control form-control-sm" id="cabinet-tl-h1" autocomplete="off" placeholder="{{ __('Main page heading') }}">
                    </div>
                </div>
            </div>
        </section>

        <section class="mb-4" aria-labelledby="cabinet-tl-step-2-title">
            <h6 class="cabinet-tl-step-title" id="cabinet-tl-step-2-title">
                <span class="cabinet-tl-step-badge">2</span>
                <span>{{ __('Step 2') }} — {{ __('Run calculation') }}</span>
            </h6>
            <div class="d-flex flex-wrap cabinet-tl-actions">
                <button type="button" class="btn btn-secondary click_tracking" data-click="Calculate" data-tl-calculate>
                    <i class="bi bi-calculator me-1" aria-hidden="true"></i>{{ __('Calculate') }}
                </button>
                <button type="button" class="btn btn-outline-secondary" data-tl-clear>
                    <i class="bi bi-x-lg me-1" aria-hidden="true"></i>{{ __('Clear') }}
                </button>
                <span class="text-muted small align-self-center ms-sm-2">{{ __('Ctrl+Enter — calculate') }}</span>
            </div>
        </section>

        <section class="cabinet-tl-report" aria-labelledby="cabinet-tl-step-3-title">
            <h6 class="cabinet-tl-step-title" id="cabinet-tl-step-3-title">
                <span class="cabinet-tl-step-badge">3</span>
                <span>{{ __('Step 3') }} — {{ __('Report') }}</span>
            </h6>

            <p class="alert alert-light border cabinet-tl-empty-note mb-3" role="status">
                {{ __('No data yet. Enter text and click Calculate.') }}
            </p>

            <div class="row g-3 cabinet-tl-seo-cards mb-3">
                <div class="col-md-4">
                    <div class="card shadow-sm cabinet-tl-metric-card h-100">
                        <div class="card-body">
                            <p class="small text-muted mb-1">{{ __('Title length') }}</p>
                            <p class="h4 mb-1 tabular-nums" data-tl-seo-title>—</p>
                            <p class="small mb-0" data-tl-seo-title-hint></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card shadow-sm cabinet-tl-metric-card h-100">
                        <div class="card-body">
                            <p class="small text-muted mb-1">{{ __('Description length') }}</p>
                            <p class="h4 mb-1 tabular-nums" data-tl-seo-description>—</p>
                            <p class="small mb-0" data-tl-seo-description-hint></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card shadow-sm cabinet-tl-metric-card h-100">
                        <div class="card-body">
                            <p class="small text-muted mb-1">{{ __('H1 length') }}</p>
                            <p class="h4 mb-1 tabular-nums" data-tl-seo-h1>—</p>
                            <p class="small mb-0 text-muted">{{ __('Main heading on the page') }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm cabinet-tl-extended-card mb-0">
                <div class="card-body">
                    <p class="small fw-semibold mb-3">{{ __('Text structure') }}</p>
                    <div class="row g-3">
                        <div class="col-sm-4">
                            <span class="text-muted small d-block">{{ __('Sentences') }}</span>
                            <span class="fs-5 fw-bold tabular-nums" data-tl-ext-sentences>—</span>
                        </div>
                        <div class="col-sm-4">
                            <span class="text-muted small d-block">{{ __('Paragraphs') }}</span>
                            <span class="fs-5 fw-bold tabular-nums" data-tl-ext-paragraphs>—</span>
                        </div>
                        <div class="col-sm-4">
                            <span class="text-muted small d-block">{{ __('Reading time') }}</span>
                            <span class="fs-5 fw-bold tabular-nums" data-tl-ext-reading>—</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <script type="application/json" id="cabinet-text-length-config">
        {!! json_encode([
            'maxChars' => (int) config('cabinet-text-length.max_chars', 38600),
            'titleMax' => (int) config('cabinet-text-length.seo.title_max', 60),
            'descriptionMax' => (int) config('cabinet-text-length.seo.description_max', 160),
            'withinLimitText' => __('Within recommended limit'),
            'overLimitText' => __('Over recommended limit'),
            'recommendedTitleText' => __('Recommended up to :count characters', ['count' => (int) config('cabinet-text-length.seo.title_max', 60)]),
            'recommendedDescriptionText' => __('Recommended up to :count characters', ['count' => (int) config('cabinet-text-length.seo.description_max', 160)]),
            'notFilledText' => __('Not filled'),
            'readingMinText' => __('min'),
        ], JSON_UNESCAPED_UNICODE) !!}
    </script>

    @slot('js')
        <script src="{{ asset('js/cabinet-text-length.js') }}?v={{ @filemtime(public_path('js/cabinet-text-length.js')) ?: time() }}"></script>
    @endslot
@endcomponent
