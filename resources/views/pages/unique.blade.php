@component('component.card', [
    'title' => __('Highlighting unique words in the text'),
    'titleHtml' => e(__('Highlighting unique words in the text')) . view('partials.cabinet-module-version-badge', ['configKey' => 'cabinet-unique'])->render(),
])
    @slot('css')
        <link rel="stylesheet" href="{{ asset('css/cabinet-unique.css') }}?v={{ @filemtime(public_path('css/cabinet-unique.css')) ?: time() }}">
        <style>
            #header-nav-bar .cabinet-header-limits-menu tr.UniqueWords {
                background: oldlace;
            }
        </style>
    @endslot

    <div class="cabinet-unique-page">
        <p class="text-secondary cabinet-uw-hint mb-4">
            {{ __('Get a list of unique words from the list of keywords') }}.
            <a href="{{ url('/duplicates') }}" class="ms-1">{{ __('Remove duplicates') }}</a>
            — {{ __('for lowercase normalization before comparison') }}.
        </p>

        <div class="row g-3 mb-4 cabinet-uw-kpi is-empty" aria-live="polite">
            <div class="col-6 col-lg-4">
                <div class="info-box shadow-sm">
                    <span class="info-box-icon text-bg-secondary">
                        <i class="bi bi-list-ul" aria-hidden="true"></i>
                    </span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ __('Number of phrases') }}</span>
                        <span class="info-box-number" data-uw-kpi-phrases>—</span>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-4">
                <div class="info-box shadow-sm">
                    <span class="info-box-icon text-bg-primary">
                        <i class="bi bi-fonts" aria-hidden="true"></i>
                    </span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ __('Unique words') }}</span>
                        <span class="info-box-number" data-uw-kpi-words>—</span>
                    </div>
                </div>
            </div>
            <div class="col-12 col-lg-4">
                <div class="info-box shadow-sm">
                    <span class="info-box-icon text-bg-success">
                        <i class="bi bi-hash" aria-hidden="true"></i>
                    </span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ __('The sum of the number of occurrences') }}</span>
                        <span class="info-box-number" data-uw-kpi-occurrences>—</span>
                    </div>
                </div>
            </div>
        </div>

        <section class="mb-4" aria-labelledby="cabinet-uw-step-1-title">
            <h6 class="cabinet-uw-step-title" id="cabinet-uw-step-1-title">
                <span class="cabinet-uw-step-badge">1</span>
                <span>{{ __('Step 1') }} — {{ __('Keyword list') }}</span>
            </h6>
            <div class="cabinet-uw-input-pane" data-uw-dropzone>
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                    <label class="form-label mb-0" for="cabinet-uw-content">{{ __('Keyword list') }}</label>
                    <span class="text-muted small">
                        {{ __('Count phrases') }}:
                        <span class="badge text-bg-light border cabinet-uw-line-badge" data-uw-count-phrases>0</span>
                    </span>
                </div>
                <p class="text-muted small cabinet-uw-drop-hint mb-2">
                    <i class="bi bi-file-earmark-arrow-up me-1" aria-hidden="true"></i>{{ __('Drop a .txt file here or paste text below') }}
                </p>
                <textarea id="cabinet-uw-content"
                          class="form-control cabinet-uw-textarea"
                          rows="10"
                          placeholder="{{ __('Enter or paste keywords, one per line. Blank lines are ignored when counting.') }}"></textarea>
                <div class="mt-2 d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-uw-example>
                        <i class="bi bi-lightning me-1" aria-hidden="true"></i>{{ __('Fill with example') }}
                    </button>
                </div>
            </div>
        </section>

        <section class="mb-4" aria-labelledby="cabinet-uw-step-2-title">
            <h6 class="cabinet-uw-step-title" id="cabinet-uw-step-2-title">
                <span class="cabinet-uw-step-badge">2</span>
                <span>{{ __('Step 2') }} — {{ __('Run processing') }}</span>
            </h6>
            <div class="d-flex flex-wrap cabinet-uw-actions">
                <button type="button" class="btn btn-secondary click_tracking" data-click="Processing" data-uw-process>
                    <i class="bi bi-sliders me-1" aria-hidden="true"></i><span data-uw-process-label>{{ __('Processing') }}</span>
                </button>
                <button type="button" class="btn btn-outline-secondary" data-uw-undo disabled>
                    <i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i>{{ __('Undo') }}
                </button>
                <button type="button" class="btn btn-outline-secondary" data-uw-clear>
                    <i class="bi bi-x-lg me-1" aria-hidden="true"></i>{{ __('Clear') }}
                </button>
                <span class="text-muted small align-self-center ms-sm-2">{{ __('Ctrl+Enter — process, Ctrl+Z — undo') }}</span>
            </div>
        </section>

        <section class="cabinet-uw-result" aria-labelledby="cabinet-uw-step-3-title">
            <h6 class="cabinet-uw-step-title" id="cabinet-uw-step-3-title">
                <span class="cabinet-uw-step-badge">3</span>
                <span>{{ __('Step 3') }} — {{ __('Result') }}</span>
            </h6>

            <div class="card cabinet-uw-tools-card shadow-sm mb-3">
                <div class="card-body">
                    <div class="row g-2 align-items-end mb-3">
                        <div class="col-md-6 col-lg-4">
                            <label class="form-label small mb-1" for="cabinet-uw-search">{{ __('Search in table') }}</label>
                            <input type="search" class="form-control form-control-sm" id="cabinet-uw-search" placeholder="{{ __('Word or phrase fragment') }}" autocomplete="off">
                        </div>
                        <div class="col-md-6 col-lg-8">
                            <p class="text-muted small mb-0 cabinet-uw-search-hint" data-uw-search-hint></p>
                        </div>
                    </div>

                    <p class="text-muted small mb-2">{{ __('Delete lines where the number of occurrences:') }}</p>
                    <div class="row g-2 align-items-end">
                        <div class="col-sm-3 col-md-2">
                            <label class="form-label small mb-1" for="cabinet-uw-range-from">{{ __('greater than or equal to') }}</label>
                            <input type="number" min="1" class="form-control form-control-sm" id="cabinet-uw-range-from" placeholder="≥">
                        </div>
                        <div class="col-sm-3 col-md-2">
                            <label class="form-label small mb-1" for="cabinet-uw-range-to">{{ __('less than or equal to') }}</label>
                            <input type="number" min="1" class="form-control form-control-sm" id="cabinet-uw-range-to" placeholder="≤">
                        </div>
                        <div class="col-sm-6 col-md-8">
                            <button type="button" class="btn btn-outline-danger btn-sm" data-uw-range-remove>
                                <i class="bi bi-trash3 me-1" aria-hidden="true"></i>{{ __('Delete') }}
                            </button>
                        </div>
                    </div>

                    <p class="text-muted small mt-3 mb-2">{{ __('Include in report') }}:</p>
                    <div class="d-flex flex-wrap gap-3">
                        @foreach ([
                            ['col' => 0, 'label' => __('Word')],
                            ['col' => 1, 'label' => __('Word forms')],
                            ['col' => 2, 'label' => __('Number of occurrences')],
                            ['col' => 3, 'label' => __('Key phrases')],
                        ] as $vis)
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="cabinet-uw-vis-{{ $vis['col'] }}" data-uw-vis-col="{{ $vis['col'] }}" checked>
                                <label class="form-check-label" for="cabinet-uw-vis-{{ $vis['col'] }}">{{ $vis['label'] }}</label>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="d-flex flex-wrap cabinet-uw-actions mb-3">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-uw-copy-table disabled>
                    <i class="bi bi-clipboard me-1" aria-hidden="true"></i>{{ __('Copy result') }}
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-uw-download-csv disabled>
                    <i class="bi bi-download me-1" aria-hidden="true"></i>{{ __('Download file') }}
                </button>
            </div>

            <p class="alert alert-light border cabinet-uw-empty-note mb-3" role="status">
                {{ __('No data yet. Paste a keyword list and click Processing.') }}
            </p>

            <div class="table-responsive cabinet-uw-table-wrap">
                <table class="table table-sm table-bordered table-hover cabinet-uw-table mb-0">
                    <thead>
                        <tr>
                            <th scope="col" data-uw-sort="word" class="cabinet-uw-sortable">{{ __('Word') }} <span class="cabinet-uw-sort-hint">↕</span></th>
                            <th scope="col" data-uw-sort="wordForms" class="cabinet-uw-sortable">{{ __('Word forms') }} <span class="cabinet-uw-sort-hint">↕</span></th>
                            <th scope="col" data-uw-sort="count" class="cabinet-uw-sortable cabinet-uw-sort-active" data-uw-sort-dir="desc">{{ __('Number of occurrences') }} <span class="cabinet-uw-sort-hint">↓</span></th>
                            <th scope="col">{{ __('Key phrases') }}</th>
                            <th scope="col" class="cabinet-uw-col-actions"></th>
                        </tr>
                    </thead>
                    <tbody data-uw-tbody></tbody>
                </table>
            </div>

            <div class="alert alert-light border mt-3 mb-0 cabinet-uw-next-modules">
                <p class="small fw-semibold mb-2">{{ __('Next in the pipeline') }}</p>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ url('/list-comparison') }}" class="btn btn-outline-secondary btn-sm">{{ __('List comparison') }}</a>
                    <a href="{{ url('/duplicates') }}" class="btn btn-outline-secondary btn-sm">{{ __('Remove Duplicates') }}</a>
                </div>
            </div>
        </section>
    </div>

    <script type="application/json" id="cabinet-unique-config">
        {!! json_encode([
            'processUrl' => url('/unique'),
            'copiedText' => __('Copied'),
            'copyFailedText' => __('Copy failed'),
            'emptyText' => __('Nothing to copy'),
            'errorTitle' => __('Error'),
            'invalidFileText' => __('Only .txt files are supported'),
            'fileTitle' => __('File'),
            'emptyInputText' => __('The list of keywords should not be empty'),
            'processingText' => __('Processing'),
            'processingWaitText' => __('Processing…'),
            'exampleText' => "купить телефон москва\nкупить телефоны недорого\nзаказать доставку телефона\nремонт смартфона\nтелефоны samsung цена\nкупить телефон москва",
            'searchShownText' => __('Shown in table: :count of :total'),
        ], JSON_UNESCAPED_UNICODE) !!}
    </script>

    @slot('js')
        <script src="{{ asset('js/cabinet-unique.js') }}?v={{ @filemtime(public_path('js/cabinet-unique.js')) ?: time() }}"></script>
    @endslot
@endcomponent
