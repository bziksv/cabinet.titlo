@component('component.card', [
    'title' => __('Edit a project'),
    'titleHtml' => e(__('Edit a project')) . view('partials.cabinet-module-version-badge', ['configKey' => 'cabinet-html-editor'])->render(),
])
    @slot('css')
        <link rel="stylesheet" href="{{ asset('css/cabinet-html-editor.css') }}?v={{ @filemtime(public_path('css/cabinet-html-editor.css')) ?: time() }}">
        <style>#header-nav-bar .cabinet-header-limits-menu tr.HtmlEditor { background: oldlace; }</style>
    @endslot

    @slot('tools')
        <a href="{{ route('HTML.editor') }}?project={{ $project->id }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>{{ __('My projects') }}
        </a>
    @endslot

    <div class="cabinet-html-editor-form">
        @include('html-editor.partials.cabinet-he-nav', [
            'backUrl' => route('HTML.editor') . '?project=' . $project->id,
            'breadcrumbs' => [
                ['label' => $project->project_name, 'url' => route('HTML.editor') . '?project=' . $project->id],
                ['label' => __('Edit a project')],
            ],
        ])
        <form action="{{ route('save.edit.project') }}" method="POST">
            @csrf
            <input type="hidden" name="project_id" value="{{ $project->id }}">
            <div class="mb-3">
                <label class="form-label" for="project_name">{{ __('Project name') }}</label>
                <input type="text" name="project_name" id="project_name" class="form-control @error('project_name') is-invalid @enderror"
                       value="{{ old('project_name', $project->project_name) }}">
                @error('project_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="mb-3">
                <label class="form-label" for="short_description">{{ __('Short description') }}</label>
                <input type="text" name="short_description" id="short_description" class="form-control @error('short_description') is-invalid @enderror"
                       value="{{ old('short_description', $project->short_description) }}">
                @error('short_description') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="d-flex flex-wrap gap-2">
                <button type="submit" class="btn btn-secondary">{{ __('Save the project') }}</button>
                <a href="{{ route('HTML.editor') }}" class="btn btn-outline-secondary">{{ __('Back to projects') }}</a>
            </div>
        </form>
    </div>
@endcomponent
