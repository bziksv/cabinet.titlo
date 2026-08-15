@component('component.card', [
    'titleHtml' => cabinet_sc_module_title_html(),
    'documentTitle' => cabinet_sc_document_title(__('Templates')),
])
    @slot('css')
        <link rel="stylesheet" href="{{ asset('css/cabinet-seo-checklist.css') }}?v={{ @filemtime(public_path('css/cabinet-seo-checklist.css')) ?: time() }}">
    @endslot

    <div class="cabinet-sc-page" data-sc-hub="templates">
        @include('pages.partials.seo-checklist-nav', [
            'scTab' => 'templates',
            'scMyTasksCount' => $myTasksCount ?? null,
            'scReviewCount' => $reviewCount ?? null,
            'scShowReviewTab' => $showReviewTab ?? false,
            'scUnreadNotesCount' => $unreadNotesCount ?? null,
            'scProjectsCount' => $projectsCount ?? null,
            'scTeamCount' => $teamCount ?? null,
            'scTemplatesCount' => $templates->count(),
        ])

        @if(session('success'))
            <div class="alert alert-success py-2 px-3 small">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger py-2 px-3 small">{{ session('error') }}</div>
        @endif

        <form method="post" action="{{ route('pages.seo-checklist.templates.store') }}" class="cabinet-sc-create-tpl mb-3">
            @csrf
            <div class="cabinet-sc-create-tpl__head">
                <div class="cabinet-sc-create-tpl__title">{{ __('Create template') }}</div>
                <p class="cabinet-sc-create-tpl__hint">{{ __('SEO checklist create template hint') }}</p>
            </div>
            <div class="cabinet-sc-create-tpl__name">
                <label class="cabinet-sc-create-tpl__label" for="sc-tpl-title">{{ __('New template name') }}</label>
                <input id="sc-tpl-title" type="text" name="title" class="form-control" required
                       placeholder="{{ __('New template name') }}" value="{{ old('title') }}">
            </div>
            <div class="cabinet-sc-create-tpl__presets" role="radiogroup" aria-label="{{ __('Template preset') }}">
                <label class="cabinet-sc-create-tpl__preset">
                    <input type="radio" name="preset" value="skeleton" @if(old('preset', 'skeleton') === 'skeleton') checked @endif>
                    <span class="cabinet-sc-create-tpl__preset-card">
                        <strong>{{ __('SEO skeleton preset') }}</strong>
                        <span>{{ __('SEO skeleton preset hint') }}</span>
                    </span>
                </label>
                <label class="cabinet-sc-create-tpl__preset">
                    <input type="radio" name="preset" value="empty" @if(old('preset') === 'empty') checked @endif>
                    <span class="cabinet-sc-create-tpl__preset-card">
                        <strong>{{ __('Empty template') }}</strong>
                        <span>{{ __('Empty template preset hint') }}</span>
                    </span>
                </label>
                <label class="cabinet-sc-create-tpl__preset">
                    <input type="radio" name="preset" value="clone" @if(old('preset') === 'clone' || old('source_id')) checked @endif>
                    <span class="cabinet-sc-create-tpl__preset-card">
                        <strong>{{ __('Clone from template') }}</strong>
                        <span>{{ __('Clone template preset hint') }}</span>
                        <select name="source_id" class="form-select form-select-sm mt-2" data-sc-clone-source>
                            <option value="">{{ __('Choose template') }}…</option>
                            @foreach($templates as $tpl)
                                <option value="{{ $tpl->id }}" @if((string) old('source_id') === (string) $tpl->id) selected @endif>
                                    {{ $tpl->title }}
                                </option>
                            @endforeach
                        </select>
                    </span>
                </label>
            </div>
            <div class="cabinet-sc-create-tpl__actions">
                <button type="submit" class="btn btn-primary">{{ __('Create') }}</button>
            </div>
        </form>

        <div class="cabinet-sc-grid">
            @foreach($templates as $tpl)
                <div class="cabinet-sc-card cabinet-sc-card--template">
                    <a href="{{ route('pages.seo-checklist.templates.edit', ['templateId' => $tpl->id]) }}" class="cabinet-sc-card__link">
                        <div class="cabinet-sc-card__top">
                            <strong class="cabinet-sc-card__domain">{{ $tpl->title }}</strong>
                            @if($tpl->is_system)
                                <span class="cabinet-sc-role cabinet-sc-role--any">{{ __('System') }}</span>
                            @else
                                <span class="cabinet-sc-role cabinet-sc-role--owner">{{ __('Custom') }}</span>
                            @endif
                        </div>
                        <div class="cabinet-sc-card__meta text-secondary small">
                            {{ $tpl->tasks_count }} {{ __('tasks') }}
                            · {{ __('Used in :count projects', ['count' => (int) ($tpl->projects_count ?? 0)]) }}
                            @if($tpl->description)
                                · {{ \Illuminate\Support\Str::limit($tpl->description, 60) }}
                            @endif
                        </div>
                    </a>
                    <div class="cabinet-sc-card__actions">
                        <button type="button"
                                class="btn btn-sm btn-outline-secondary"
                                data-sc-set-default-template="{{ $tpl->id }}"
                                data-label-default="{{ __('Default template') }}"
                                data-label-make-default="{{ __('Make default template') }}">
                            <span data-sc-default-label>{{ __('Make default template') }}</span>
                        </button>
                        <form method="post" action="{{ route('pages.seo-checklist.templates.duplicate', ['templateId' => $tpl->id]) }}">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-secondary">{{ __('Duplicate') }}</button>
                        </form>
                        <a href="{{ route('pages.seo-checklist.templates.edit', ['templateId' => $tpl->id]) }}" class="btn btn-sm btn-outline-primary">{{ __('Open') }}</a>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    @slot('js')
        <script src="{{ asset('js/cabinet-seo-checklist-hub.js') }}?v={{ @filemtime(public_path('js/cabinet-seo-checklist-hub.js')) ?: time() }}"></script>
    @endslot
@endcomponent
