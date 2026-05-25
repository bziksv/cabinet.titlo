@component('component.card', [
    'title' => __('Add HTML text'),
    'titleHtml' => e(__('Add HTML text')) . view('partials.cabinet-module-version-badge', ['configKey' => 'cabinet-html-editor'])->render(),
])
    @slot('css')
        <link rel="stylesheet" href="{{ asset('css/cabinet-html-editor.css') }}?v={{ @filemtime(public_path('css/cabinet-html-editor.css')) ?: time() }}">
        <style>#header-nav-bar .cabinet-header-limits-menu tr.HtmlEditor { background: oldlace; }</style>
    @endslot

    @slot('tools')
        <a href="{{ route('HTML.editor') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>{{ __('My projects') }}
        </a>
    @endslot

    <div class="cabinet-html-editor-page cabinet-html-editor-form" data-he-lang="{{ $lang }}">
        @include('html-editor.partials.cabinet-he-nav', [
            'breadcrumbs' => [
                ['label' => __('Add HTML text')],
            ],
        ])
        <p class="cabinet-he-form-hint text-secondary mb-4">
            {{ __('Edit visually on the left — HTML code updates on the right. You can edit either side.') }}
        </p>
        <form action="{{ route('save.description') }}" method="POST">
            @csrf
            <div class="mb-3">
                <label class="form-label" for="project_id">{{ __('Project') }}</label>
                <select class="form-select" name="project_id" id="project_id" required>
                    @foreach($projects as $project)
                        <option value="{{ $project->id }}" {{ (string) old('project_id', $preselectedProjectId ?: ($projects->first()->id ?? '')) === (string) $project->id ? 'selected' : '' }}>{{ $project->project_name }}</option>
                    @endforeach
                </select>
            </div>
            @include('html-editor.partials.cabinet-he-presets')
            @include('html-editor.partials.cabinet-he-editor-split', [
                'fieldValue' => old('description'),
                'invalid' => $errors->has('description'),
            ])
            @error('description') <div class="invalid-feedback d-block mb-3">{{ $message }}</div> @enderror
            <div class="d-flex flex-wrap gap-2">
                <button type="submit" class="btn btn-secondary">{{ __('Save HTML text') }}</button>
                <a href="{{ route('HTML.editor') }}" class="btn btn-outline-secondary">{{ __('Back to projects') }}</a>
            </div>
        </form>
    </div>

    @slot('js')
        @include('partials.cabinet-html-editor-ckeditor')
        <script src="{{ asset('js/cabinet-html-editor.js') }}?v={{ @filemtime(public_path('js/cabinet-html-editor.js')) ?: time() }}"></script>
    @endslot
@endcomponent
