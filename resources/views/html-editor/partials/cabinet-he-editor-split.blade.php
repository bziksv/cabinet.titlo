@php
    $editorId = $editorId ?? 'description';
    $rows = (int) ($rows ?? 14);
    $fieldName = $fieldName ?? 'description';
    $fieldValue = $fieldValue ?? '';
    $invalid = $invalid ?? false;
@endphp

<div class="cabinet-he-split-wrap mb-3" data-he-split-wrap>
    <div class="cabinet-he-split-toolbar d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
        <span class="small text-secondary fw-semibold">{{ __('Editor layout') }}</span>
        <div class="btn-group btn-group-sm cabinet-he-layout-toggle" role="group" aria-label="{{ __('Editor layout') }}">
            <button type="button"
                    class="btn btn-outline-secondary active"
                    data-he-layout-mode="side"
                    aria-pressed="true">
                <i class="bi bi-layout-split me-1" aria-hidden="true"></i>{{ __('Side by side') }}
            </button>
            <button type="button"
                    class="btn btn-outline-secondary"
                    data-he-layout-mode="stacked"
                    aria-pressed="false">
                <i class="bi bi-layout-text-window-reverse me-1" aria-hidden="true"></i>{{ __('Code below editor') }}
            </button>
        </div>
    </div>

    <div class="cabinet-he-split row g-3 cabinet-he-split--side mb-0"
         data-he-split-editor
         data-he-editor-id="{{ $editorId }}"
         data-he-layout-storage-key="cabinet-he-editor-layout">
        <div class="cabinet-he-split-col cabinet-he-split-col--visual col-12 col-lg-6 d-flex flex-column">
            <div class="cabinet-he-pane flex-grow-1">
                <div class="cabinet-he-pane-head">{{ __('Visual editor') }}</div>
                <div class="cabinet-he-pane-body cabinet-he-editor-wrap p-0">
                    <textarea
                        name="{{ $fieldName }}"
                        id="{{ $editorId }}"
                        class="form-control border-0 rounded-0 @if($invalid) is-invalid @endif"
                        rows="{{ $rows }}"
                    >{!! $fieldValue !!}</textarea>
                </div>
            </div>
        </div>
        <div class="cabinet-he-split-col cabinet-he-split-col--code col-12 col-lg-6 d-flex flex-column">
            <div class="cabinet-he-pane flex-grow-1">
                <div class="cabinet-he-pane-head d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <span>{{ __('HTML code') }}</span>
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-he-copy-html data-he-copied-label="{{ __('HTML copied') }}">
                        <i class="bi bi-clipboard me-1" aria-hidden="true"></i>{{ __('Copy HTML') }}
                    </button>
                </div>
                <div class="cabinet-he-pane-body d-flex flex-column">
                    <textarea
                        class="form-control font-monospace cabinet-he-html-source flex-grow-1"
                        data-he-html-source
                        rows="{{ $rows }}"
                        spellcheck="false"
                        aria-label="{{ __('HTML code') }}"
                    ></textarea>
                    <p class="cabinet-he-html-meta small text-muted mb-0 mt-2" data-he-html-meta aria-live="polite"
                       data-he-html-chars-label="{{ __('chars in HTML') }}"
                       data-he-text-chars-label="{{ __('chars of visible text') }}"></p>
                </div>
            </div>
        </div>
    </div>
</div>
