@component('component.card', [
    'title' => __('List comparison'),
    'titleHtml' => e(__('List comparison')) . view('partials.cabinet-module-version-badge', ['configKey' => 'cabinet-list-comparison'])->render(),
])
    @slot('css')
        <link rel="stylesheet" href="{{ asset('css/cabinet-list-comparison.css') }}?v={{ @filemtime(public_path('css/cabinet-list-comparison.css')) ?: time() }}">
        <style>
            #header-nav-bar .cabinet-header-limits-menu tr.ListComparison {
                background: oldlace;
            }
        </style>
    @endslot

    @php
        $lcTip = function ($text) {
            return ' <i class="bi bi-question-circle text-muted cabinet-lc-tip" data-bs-toggle="tooltip" data-bs-placement="top" title="' . e($text) . '" aria-hidden="true"></i>';
        };
    @endphp

    <div class="cabinet-list-comparison-page">
        <p class="text-secondary cabinet-lc-hint mb-4">
            {{ __('Compare the lists of keywords and get a common unique list.') }}
            <a href="{{ url('/duplicates') }}" class="ms-1">{{ __('Remove duplicates') }}</a>
            — {{ __('for lowercase normalization before comparison') }}.
        </p>

        <div class="row g-3 mb-4 cabinet-lc-kpi is-empty" aria-live="polite">
            <div class="col-6 col-lg-3">
                <div class="info-box shadow-sm">
                    <span class="info-box-icon text-bg-secondary">
                        <i class="bi bi-1-circle" aria-hidden="true"></i>
                    </span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ __('First list') }}</span>
                        <span class="info-box-number" data-lc-kpi-a>—</span>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="info-box shadow-sm">
                    <span class="info-box-icon text-bg-primary">
                        <i class="bi bi-2-circle" aria-hidden="true"></i>
                    </span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ __('Second list') }}</span>
                        <span class="info-box-number" data-lc-kpi-b>—</span>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="info-box shadow-sm">
                    <span class="info-box-icon text-bg-success">
                        <i class="bi bi-check2-square" aria-hidden="true"></i>
                    </span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ __('Comparison result') }}</span>
                        <span class="info-box-number" data-lc-kpi-result>—</span>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="info-box shadow-sm">
                    <span class="info-box-icon text-bg-info">
                        <i class="bi bi-intersect" aria-hidden="true"></i>
                    </span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ __('Intersection') }}</span>
                        <span class="info-box-number" data-lc-kpi-overlap>—</span>
                    </div>
                </div>
            </div>
        </div>

        <section class="mb-4" aria-labelledby="cabinet-lc-step-1-title">
            <h6 class="cabinet-lc-step-title" id="cabinet-lc-step-1-title">
                <span class="cabinet-lc-step-badge">1</span>
                <span>{{ __('Step 1') }} — {{ __('Two lists') }}</span>
            </h6>
            <div class="row g-3">
                <div class="col-lg-6">
                    <div class="cabinet-lc-list-pane" data-lc-dropzone-a>
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                            <label class="form-label mb-0" for="cabinet-lc-list-a">{{ __('First list') }}</label>
                            <span class="text-muted small">
                                {{ __('Count phrases') }}:
                                <span class="badge text-bg-light border cabinet-lc-line-badge" data-lc-count-a>0</span>
                            </span>
                        </div>
                        <p class="text-muted small cabinet-lc-drop-hint mb-2">
                            <i class="bi bi-file-earmark-arrow-up me-1" aria-hidden="true"></i>{{ __('Drop a .txt file here or paste text below') }}
                        </p>
                        <textarea id="cabinet-lc-list-a"
                                  class="form-control cabinet-lc-textarea"
                                  rows="10"
                                  placeholder="{{ __('Enter or paste keywords, one per line. Blank lines are ignored when counting.') }}"></textarea>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="cabinet-lc-list-pane" data-lc-dropzone-b>
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                            <label class="form-label mb-0" for="cabinet-lc-list-b">{{ __('Second list') }}</label>
                            <span class="text-muted small">
                                {{ __('Count phrases') }}:
                                <span class="badge text-bg-light border cabinet-lc-line-badge" data-lc-count-b>0</span>
                            </span>
                        </div>
                        <p class="text-muted small cabinet-lc-drop-hint mb-2">
                            <i class="bi bi-file-earmark-arrow-up me-1" aria-hidden="true"></i>{{ __('Drop a .txt file here or paste text below') }}
                        </p>
                        <textarea id="cabinet-lc-list-b"
                                  class="form-control cabinet-lc-textarea"
                                  rows="10"
                                  placeholder="{{ __('Enter or paste keywords, one per line. Blank lines are ignored when counting.') }}"></textarea>
                    </div>
                </div>
            </div>
            <div class="mt-3">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-lc-swap>
                    <i class="bi bi-arrow-left-right me-1" aria-hidden="true"></i>{{ __('Swap lists') }}
                </button>
            </div>
        </section>

        <section class="mb-4" aria-labelledby="cabinet-lc-step-2-title">
            <h6 class="cabinet-lc-step-title" id="cabinet-lc-step-2-title">
                <span class="cabinet-lc-step-badge">2</span>
                <span>{{ __('Step 2') }} — {{ __('Comparison type:') }}</span>
            </h6>

            <div class="cabinet-lc-presets mb-3">
                <span class="text-muted small me-2">{{ __('Presets') }}:</span>
                <div class="btn-group btn-group-sm flex-wrap">
                    <button type="button" class="btn btn-outline-secondary" data-lc-preset="unique">{{ __('Intersection') }}</button>
                    <button type="button" class="btn btn-outline-secondary" data-lc-preset="uniqueInFirstList">{{ __('Only in list A') }}</button>
                    <button type="button" class="btn btn-outline-secondary" data-lc-preset="uniqueInSecondList">{{ __('Only in list B') }}</button>
                    <button type="button" class="btn btn-outline-secondary" data-lc-preset="union">{{ __('Union') }}</button>
                </div>
            </div>

            <div class="card cabinet-lc-options-card shadow-sm">
                <div class="card-body">
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="cabinet-lc-mode" id="cabinet-lc-mode-unique" data-lc-mode value="unique" checked>
                                <label class="form-check-label" for="cabinet-lc-mode-unique">
                                    {!! __('Intersection') . $lcTip(__('a list of keywords that were found in both the first and second list (intersection)')) !!}
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="cabinet-lc-mode" id="cabinet-lc-mode-first" data-lc-mode value="uniqueInFirstList">
                                <label class="form-check-label" for="cabinet-lc-mode-first">
                                    {!! __('Only in list A') . $lcTip(__('a list of keywords that are in the first list, but not in the second')) !!}
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="cabinet-lc-mode" id="cabinet-lc-mode-second" data-lc-mode value="uniqueInSecondList">
                                <label class="form-check-label" for="cabinet-lc-mode-second">
                                    {!! __('Only in list B') . $lcTip(__('a list of keywords that are in the second list, but not in the first')) !!}
                                </label>
                            </div>
                            <div class="form-check mb-0">
                                <input class="form-check-input" type="radio" name="cabinet-lc-mode" id="cabinet-lc-mode-union" data-lc-mode value="union">
                                <label class="form-check-label" for="cabinet-lc-mode-union">
                                    {!! __('Union') . $lcTip(__('a list of keywords that were found in any of the lists (combining)')) !!}
                                </label>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <p class="text-muted small mb-2">{{ __('Processing options') }}</p>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="cabinet-lc-opt-trim" checked>
                                <label class="form-check-label" for="cabinet-lc-opt-trim">{!! __('Trim lines') . $lcTip(__('Trims each line separately.')) !!}</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="cabinet-lc-opt-empty" checked>
                                <label class="form-check-label" for="cabinet-lc-opt-empty">{!! __('remove blank lines') . $lcTip(__('Removes lines that contain only whitespace.')) !!}</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="cabinet-lc-opt-ci">
                                <label class="form-check-label" for="cabinet-lc-opt-ci">{!! __('Case-insensitive comparison') . $lcTip(__('Treats "Key" and "key" as the same line.')) !!}</label>
                            </div>
                            <div class="form-check mb-0">
                                <input class="form-check-input" type="checkbox" id="cabinet-lc-opt-sort">
                                <label class="form-check-label" for="cabinet-lc-opt-sort">{!! __('Sort A to Z') . $lcTip(__('Sorts result lines alphabetically.')) !!}</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="mb-4" aria-labelledby="cabinet-lc-step-3-title">
            <h6 class="cabinet-lc-step-title" id="cabinet-lc-step-3-title">
                <span class="cabinet-lc-step-badge">3</span>
                <span>{{ __('Step 3') }} — {{ __('Run processing') }}</span>
            </h6>
            <div class="d-flex flex-wrap cabinet-lc-actions">
                <button type="button" class="btn btn-secondary click_tracking" data-click="Processing" data-lc-process>
                    <i class="bi bi-sliders me-1" aria-hidden="true"></i>{{ __('Processing') }}
                </button>
                <button type="button" class="btn btn-outline-secondary" data-lc-undo disabled>
                    <i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i>{{ __('Undo') }}
                </button>
                <button type="button" class="btn btn-outline-secondary click_tracking" data-click="Clear" data-lc-clear>
                    <i class="bi bi-x-lg me-1" aria-hidden="true"></i>{{ __('Clear') }}
                </button>
                <span class="text-muted small align-self-center ms-sm-2">{{ __('Ctrl+Enter — process, Ctrl+Z — undo') }}</span>
            </div>
        </section>

        <section class="cabinet-lc-result" aria-labelledby="cabinet-lc-step-4-title">
            <h6 class="cabinet-lc-step-title" id="cabinet-lc-step-4-title">
                <span class="cabinet-lc-step-badge">4</span>
                <span>{{ __('Step 4') }} — {{ __('Comparison result') }}</span>
            </h6>
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                <label class="form-label mb-0" for="cabinet-lc-result">{{ __('Comparison result') }}</label>
                <span class="text-muted small">
                    {{ __('Count phrases') }}:
                    <span class="badge text-bg-light border cabinet-lc-line-badge" data-lc-count-result>0</span>
                </span>
            </div>
            <p class="alert alert-light border cabinet-lc-empty-note mb-3" role="status">
                {{ __('No matching lines for the selected mode. Check case sensitivity and trim options.') }}
            </p>
            <textarea id="cabinet-lc-result"
                      class="form-control cabinet-lc-textarea cabinet-lc-textarea--readonly"
                      rows="10"
                      readonly
                      tabindex="-1"
                      aria-readonly="true"></textarea>
            <div class="d-flex flex-wrap cabinet-lc-actions mt-3">
                <button type="button" class="btn btn-outline-secondary click_tracking" data-click="Copy" data-lc-copy>
                    <i class="bi bi-clipboard me-1" aria-hidden="true"></i>{{ __('Copy result') }}
                </button>
                <button type="button" class="btn btn-outline-secondary click_tracking" data-click="Download" data-lc-download>
                    <i class="bi bi-download me-1" aria-hidden="true"></i>{{ __('Download file') }}
                </button>
            </div>
        </section>
    </div>

    <script type="application/json" id="cabinet-list-comparison-config">
        {!! json_encode([
            'copiedText' => __('Copied'),
            'copyTitle' => __('Copy'),
            'emptyText' => __('Nothing to copy'),
            'copyFailedText' => __('Copy failed'),
            'bothListsRequired' => __('Both lists should not be empty'),
            'errorTitle' => __('Error'),
            'invalidFileText' => __('Only .txt files are supported'),
            'fileTitle' => __('File'),
            'downloadTitle' => __('Download file'),
        ], JSON_UNESCAPED_UNICODE) !!}
    </script>

    @slot('js')
        <script src="{{ asset('js/cabinet-list-comparison.js') }}?v={{ @filemtime(public_path('js/cabinet-list-comparison.js')) ?: time() }}"></script>
    @endslot
@endcomponent
