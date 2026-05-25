@component('component.card', [
    'title' => __('Remove Duplicates'),
    'titleHtml' => e(__('Remove Duplicates')) . view('partials.cabinet-module-version-badge', ['configKey' => 'cabinet-duplicates'])->render(),
])
    @slot('css')
        <link rel="stylesheet" href="{{ asset('css/cabinet-duplicates.css') }}?v={{ @filemtime(public_path('css/cabinet-duplicates.css')) ?: time() }}">
        <style>
            #header-nav-bar .cabinet-header-limits-menu tr.RemoveDublicate {
                background: oldlace;
            }
        </style>
    @endslot

    @php
        $dupTip = function ($text) {
            return ' <i class="bi bi-question-circle text-muted cabinet-dup-tip" data-bs-toggle="tooltip" data-bs-placement="top" title="' . e($text) . '" aria-hidden="true"></i>';
        };
    @endphp

    <div class="cabinet-duplicates-page">
        <p class="text-secondary cabinet-dup-hint mb-4">
            {{ __('For example, you have a list of pages with each new line or a list of keywords where you need to remove duplicates.') }}
        </p>

        <div class="row g-3 mb-4 cabinet-dup-kpi is-empty" aria-live="polite">
            <div class="col-6 col-lg-3">
                <div class="info-box shadow-sm">
                    <span class="info-box-icon text-bg-secondary">
                        <i class="bi bi-list-ul" aria-hidden="true"></i>
                    </span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ __('Lines before') }}</span>
                        <span class="info-box-number" data-dup-before>—</span>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="info-box shadow-sm">
                    <span class="info-box-icon text-bg-primary">
                        <i class="bi bi-check2-square" aria-hidden="true"></i>
                    </span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ __('After processing') }}</span>
                        <span class="info-box-number" data-dup-after>—</span>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="info-box shadow-sm">
                    <span class="info-box-icon text-bg-danger">
                        <i class="bi bi-trash3" aria-hidden="true"></i>
                    </span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ __('Duplicates removed') }}</span>
                        <span class="info-box-number" data-dup-dup-removed>—</span>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="info-box shadow-sm">
                    <span class="info-box-icon text-bg-warning">
                        <i class="bi bi-dash-lg" aria-hidden="true"></i>
                    </span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ __('Empty lines removed') }}</span>
                        <span class="info-box-number" data-dup-empty-removed>—</span>
                    </div>
                </div>
            </div>
        </div>

        <section class="cabinet-dup-step mb-4" aria-labelledby="cabinet-dup-step-1-title">
            <h6 class="cabinet-dup-step-title" id="cabinet-dup-step-1-title">
                <span class="cabinet-dup-step-badge">1</span>
                <span>{{ __('Step 1') }} — {{ __('Paste your list') }}</span>
            </h6>

            <div class="cabinet-dup-presets mb-3">
                <span class="text-muted small me-2">{{ __('Presets') }}:</span>
                <div class="btn-group btn-group-sm flex-wrap">
                    <button type="button" class="btn btn-outline-secondary" data-dup-preset="dedup-only">
                        {{ __('Duplicates only') }}
                    </button>
                    <button type="button" class="btn btn-outline-secondary" data-dup-preset="seo">
                        {{ __('SEO keyword list') }}
                    </button>
                </div>
            </div>

            <div class="cabinet-dup-editor" data-dup-dropzone>
                <div class="cabinet-dup-main-pane">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                        <label class="form-label mb-0" for="cabinet-dup-text" data-dup-main-label>{{ __('Your text') }}</label>
                        <span class="text-muted small">
                            {{ __('Count phrases') }}:
                            <span class="badge text-bg-light border cabinet-dup-line-badge" data-dup-line-count>0</span>
                        </span>
                    </div>
                    <p class="text-muted small cabinet-dup-drop-hint mb-2">
                        <i class="bi bi-file-earmark-arrow-up me-1" aria-hidden="true"></i>{{ __('Drop a .txt file here or paste text below') }}
                    </p>
                    <textarea id="cabinet-dup-text"
                              class="form-control cabinet-dup-textarea"
                              rows="12"
                              placeholder="{{ __('Enter or paste keywords, one per line. Blank lines are ignored when counting.') }}"></textarea>
                </div>

                <div class="form-check form-switch cabinet-dup-split-toggle mt-3 mb-0">
                    <input class="form-check-input" type="checkbox" role="switch" id="cabinet-dup-split-toggle">
                    <label class="form-check-label" for="cabinet-dup-split-toggle">{{ __('Compare before and after') }}</label>
                </div>

                <div class="cabinet-dup-split-pane cabinet-dup-split-pane--before d-none mt-3">
                    <label class="form-label" for="cabinet-dup-before-view">{{ __('Before processing') }}</label>
                    <textarea id="cabinet-dup-before-view"
                              class="form-control cabinet-dup-textarea cabinet-dup-textarea--readonly"
                              rows="8"
                              readonly
                              tabindex="-1"
                              aria-readonly="true"
                              placeholder="{{ __('Snapshot appears here after you run processing') }}"></textarea>
                </div>
            </div>
        </section>

        <section class="cabinet-dup-step mb-4" aria-labelledby="cabinet-dup-step-2-title">
            <h6 class="cabinet-dup-step-title" id="cabinet-dup-step-2-title">
                <span class="cabinet-dup-step-badge">2</span>
                <span>{{ __('Step 2') }} — {{ __('Processing options') }}</span>
            </h6>

            <div class="card cabinet-dup-options-card shadow-sm mb-0">
                <div class="card-header py-2">
                    <div class="d-flex flex-wrap justify-content-end align-items-center gap-2">
                        <div class="btn-group btn-group-sm flex-wrap">
                            <button type="button" class="btn btn-outline-secondary" data-dup-select-all>
                                {{ __('Select all') }}
                            </button>
                            <button type="button" class="btn btn-outline-secondary" data-dup-deselect-all>
                                {{ __('Deselect all') }}
                            </button>
                            <button type="button" class="btn btn-outline-secondary" data-dup-reset-options>
                                {{ __('Defaults') }}
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="cabinet-dup-opt-extra-space" data-dup-option value="removeExtraSpace">
                                <label class="form-check-label" for="cabinet-dup-opt-extra-space">{!! __('remove duplicate spaces between words') . $dupTip(__('Collapses repeated spaces inside each line.')) !!}</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="cabinet-dup-opt-trim" data-dup-option value="trim">
                                <label class="form-check-label" for="cabinet-dup-opt-trim">{!! __('remove spaces and tabs at the beginning and end of the line') . $dupTip(__('Trims each line separately.')) !!}</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="cabinet-dup-opt-tabs" data-dup-option value="replaceTabWithSpace">
                                <label class="form-check-label" for="cabinet-dup-opt-tabs">{!! __('replace tabs with spaces') . $dupTip(__('Replaces tab characters with spaces.')) !!}</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="cabinet-dup-opt-empty" data-dup-option value="removeEmptyRows">
                                <label class="form-check-label" for="cabinet-dup-opt-empty">{!! __('remove blank lines') . $dupTip(__('Removes lines that contain only whitespace.')) !!}</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="cabinet-dup-opt-lower" data-dup-option value="lowerCase">
                                <label class="form-check-label" for="cabinet-dup-opt-lower">{!! __('convert to lowercase') . $dupTip(__('Useful before deduplication of keywords.')) !!}</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="cabinet-dup-opt-sort" data-dup-option value="sortAlphabetically">
                                <label class="form-check-label" for="cabinet-dup-opt-sort">{!! __('Sort A to Z') . $dupTip(__('Sorts non-empty lines alphabetically after other steps.')) !!}</label>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="cabinet-dup-opt-dedup" data-dup-option value="removeDuplicates" checked>
                                <label class="form-check-label" for="cabinet-dup-opt-dedup">{!! __('remove duplicates') . $dupTip(__('Keeps the first occurrence of each line.')) !!}</label>
                            </div>
                            <div class="form-check ms-4">
                                <input class="form-check-input" type="checkbox" id="cabinet-dup-opt-dedup-ci">
                                <label class="form-check-label" for="cabinet-dup-opt-dedup-ci">{!! __('Case-insensitive deduplication') . $dupTip(__('Treats "Key" and "key" as the same line.')) !!}</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="cabinet-dup-opt-yo" data-dup-option value="replaceUmlaut">
                                <label class="form-check-label" for="cabinet-dup-opt-yo">{!! __('replace e') . $dupTip(__('Replaces both "ё" and "Ё" with "е".')) !!}</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input"
                                       type="checkbox"
                                       id="cabinet-dup-opt-start"
                                       data-dup-option
                                       data-dup-char-toggle="cabinet-dup-start-chars"
                                       value="removeStartingChars">
                                <label class="form-check-label" for="cabinet-dup-opt-start">{!! __('remove characters at the beginning of a word') . $dupTip(__('Characters from the field below, e.g. + - !')) !!}</label>
                            </div>
                            <div class="cabinet-dup-char-field is-disabled" data-dup-char-field="cabinet-dup-start-chars">
                                <input type="text"
                                       class="form-control form-control-sm"
                                       id="cabinet-dup-start-chars"
                                       value="+-!"
                                       disabled
                                       placeholder="{{ __('remove characters at the beginning of a word') }}: +-!">
                            </div>
                            <div class="form-check">
                                <input class="form-check-input"
                                       type="checkbox"
                                       id="cabinet-dup-opt-end"
                                       data-dup-option
                                       data-dup-char-toggle="cabinet-dup-end-chars"
                                       value="removeEndingChars">
                                <label class="form-check-label" for="cabinet-dup-opt-end">{!! __('remove characters at the end of a word') . $dupTip(__('Characters from the field below, e.g. . ! ?')) !!}</label>
                            </div>
                            <div class="cabinet-dup-char-field is-disabled" data-dup-char-field="cabinet-dup-end-chars">
                                <input type="text"
                                       class="form-control form-control-sm"
                                       id="cabinet-dup-end-chars"
                                       value=".!?"
                                       disabled
                                       placeholder="{{ __('remove characters at the end of a word') }}: .!?">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="cabinet-dup-step" aria-labelledby="cabinet-dup-step-3-title">
            <h6 class="cabinet-dup-step-title" id="cabinet-dup-step-3-title">
                <span class="cabinet-dup-step-badge">3</span>
                <span>{{ __('Step 3') }} — {{ __('Run processing') }}</span>
            </h6>

            <div class="d-flex flex-wrap cabinet-dup-actions">
                <button type="button" class="btn btn-secondary click_tracking" data-click="Remove duplicates" data-dup-process>
                    <i class="bi bi-funnel me-1" aria-hidden="true"></i>{{ __('Remove duplicates') }}
                </button>
                <button type="button" class="btn btn-outline-secondary" data-dup-undo disabled>
                    <i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i>{{ __('Undo') }}
                </button>
                <button type="button" class="btn btn-outline-secondary click_tracking" data-click="Copy" data-dup-copy>
                    <i class="bi bi-clipboard me-1" aria-hidden="true"></i>{{ __('Copy') }}
                </button>
                <button type="button" class="btn btn-outline-secondary click_tracking" data-click="Clear" data-dup-clear>
                    <i class="bi bi-x-lg me-1" aria-hidden="true"></i>{{ __('Clear') }}
                </button>
                <span class="text-muted small align-self-center ms-sm-2">{{ __('Ctrl+Enter — process, Ctrl+Z — undo') }}</span>
            </div>
        </section>
    </div>

    <script type="application/json" id="cabinet-duplicates-config">
        {!! json_encode([
            'copiedText' => __('Copied'),
            'copyTitle' => __('Copy'),
            'emptyText' => __('Nothing to copy'),
            'copyFailedText' => __('Copy failed'),
            'mainLabelYourText' => __('Your text'),
            'mainLabelProcessed' => __('Processed list'),
            'invalidFileText' => __('Only .txt files are supported'),
            'fileTitle' => __('File'),
        ], JSON_UNESCAPED_UNICODE) !!}
    </script>

    @slot('js')
        <script src="{{ asset('js/cabinet-duplicates.js') }}?v={{ @filemtime(public_path('js/cabinet-duplicates.js')) ?: time() }}"></script>
    @endslot
@endcomponent
