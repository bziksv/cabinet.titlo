@component('component.card', [
    'title' => __('Edit HTML text'),
    'titleHtml' => e(__('Edit HTML text')) . ' · ' . e($project->project_name ?? '') . view('partials.cabinet-module-version-badge', ['configKey' => 'cabinet-html-editor'])->render(),
])
    @slot('css')
        <link rel="stylesheet" href="{{ asset('css/cabinet-html-editor.css') }}?v={{ @filemtime(public_path('css/cabinet-html-editor.css')) ?: time() }}">
        <style>#header-nav-bar .cabinet-header-limits-menu tr.HtmlEditor { background: oldlace; }</style>
    @endslot

    @slot('tools')
        <a href="{{ route('HTML.editor') }}?project={{ $project->project_id }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>{{ __('My projects') }}
        </a>
    @endslot

    <div class="cabinet-html-editor-page cabinet-html-editor-form" data-he-lang="{{ $lang }}">
        <p class="cabinet-he-edit-trail small text-muted mb-3">
            <a href="{{ route('HTML.editor') }}" class="text-muted text-decoration-none">{{ __('HTML editor') }}</a>
            <span aria-hidden="true"> › </span>
            <a href="{{ route('HTML.editor') }}?project={{ $project->project_id }}" class="text-muted text-decoration-none">{{ $project->project_name ?? '—' }}</a>
        </p>

        <form action="{{ route('save.edit.description') }}" method="POST">
            @csrf
            <input type="hidden" name="description_id" value="{{ $project->id }}">

            <details class="cabinet-he-fold mb-3">
                <summary class="cabinet-he-fold-summary">{{ __('HTML presets') }}</summary>
                <div class="cabinet-he-fold-body">
                    @include('html-editor.partials.cabinet-he-presets', ['compact' => true])
                </div>
            </details>

            <details class="cabinet-he-fold mb-3">
                <summary class="cabinet-he-fold-summary">{{ __('Public link without registration') }}</summary>
                <div class="cabinet-he-fold-body">
                    @include('html-editor.partials.cabinet-he-public-share', [
                        'descriptionId' => $project->id,
                        'publicShare' => $publicShare ?? null,
                        'compact' => true,
                    ])
                </div>
            </details>

            @include('html-editor.partials.cabinet-he-editor-split', [
                'fieldValue' => old('description', $project->description),
                'invalid' => $errors->has('description'),
            ])
            @error('description') <div class="invalid-feedback d-block mb-3">{{ $message }}</div> @enderror
            <div class="d-flex flex-wrap gap-2 mt-2">
                <button type="submit" class="btn btn-secondary">{{ __('Save HTML text') }}</button>
            </div>
        </form>
    </div>

    @slot('js')
        @include('partials.cabinet-html-editor-ckeditor')
        <script src="{{ asset('js/cabinet-html-editor.js') }}?v={{ @filemtime(public_path('js/cabinet-html-editor.js')) ?: time() }}"></script>
    @endslot
@endcomponent
