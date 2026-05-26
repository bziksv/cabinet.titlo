@component('component.card', [
    'title' => __('New project'),
    'titleHtml' => e(__('New project')) . view('partials.cabinet-module-version-badge', ['configKey' => 'cabinet-html-editor'])->render(),
])
    @slot('css')
        <link rel="stylesheet" href="{{ asset('css/cabinet-html-editor.css') }}?v={{ @filemtime(public_path('css/cabinet-html-editor.css')) ?: time() }}">
        @include('partials.cabinet-html-editor-codemirror', ['part' => 'css'])
        <style>#header-nav-bar .cabinet-header-limits-menu tr.HtmlEditor { background: oldlace; }</style>
    @endslot

    @slot('tools')
        @if($showButton)
            <a href="{{ route('HTML.editor') }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>{{ __('My projects') }}
            </a>
        @endif
    @endslot

    <div class="cabinet-html-editor-page cabinet-html-editor-form" data-he-lang="{{ $lang }}">
        @if($showButton)
            @include('html-editor.partials.cabinet-he-nav', [
                'breadcrumbs' => [
                    ['label' => __('New project')],
                ],
            ])
        @endif
        <p class="cabinet-he-form-hint text-secondary mb-4">
            {{ __('Name the project and write the first HTML text. You can add more texts later.') }}
        </p>
        <p class="cabinet-he-form-hint text-secondary mb-4">
            {{ __('Edit visually on the left — HTML code updates on the right. You can edit either side.') }}
        </p>
        <form action="{{ route('store.project') }}" method="POST" id="cabinet-he-create-project">
            @csrf
            <div class="mb-3">
                <label class="form-label" for="project_name">{{ __('Project name') }}</label>
                <input type="text" name="project_name" id="project_name" class="form-control @error('project_name') is-invalid @enderror"
                       placeholder="{{ __('For example: Landing summer sale') }}"
                       value="{{ old('project_name', $request['project_name'] ?? '') }}">
                @error('project_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="mb-3">
                <label class="form-label" for="short_description">
                    {{ __('Short description') }}
                    <i class="bi bi-question-circle text-muted ms-1" data-bs-toggle="tooltip"
                       title="{{ __('You can leave this field empty, it will be generated automatically') }}" aria-hidden="true"></i>
                </label>
                <input type="text" name="short_description" id="short_description" class="form-control @error('short_description') is-invalid @enderror"
                       placeholder="{{ __('Optional note for yourself') }}"
                       value="{{ old('short_description', $request['short_description'] ?? '') }}">
                @error('short_description') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <p class="form-label fw-semibold mb-2">{{ __('First HTML text') }}</p>
            @include('html-editor.partials.cabinet-he-presets')
            @include('html-editor.partials.cabinet-he-editor-split', [
                'fieldValue' => old('description', $request['description'] ?? ''),
                'invalid' => $errors->has('description'),
            ])
            @error('description') <div class="invalid-feedback d-block mb-3">{{ $message }}</div> @enderror
            <div class="d-flex flex-wrap gap-2">
                <button type="submit" class="btn btn-secondary click_tracking" data-click="Save project">
                    <i class="bi bi-check-lg me-1" aria-hidden="true"></i>{{ __('Create project and save text') }}
                </button>
                @if($showButton)
                    <a href="{{ route('HTML.editor') }}" class="btn btn-outline-secondary">{{ __('Back to projects') }}</a>
                @endif
            </div>
        </form>
    </div>

    @slot('js')
        @include('partials.cabinet-html-editor-ckeditor')
        @include('partials.cabinet-html-editor-codemirror', ['part' => 'js'])
        <script src="{{ asset('js/cabinet-html-editor.js') }}?v={{ @filemtime(public_path('js/cabinet-html-editor.js')) ?: time() }}"></script>
    @endslot
@endcomponent
