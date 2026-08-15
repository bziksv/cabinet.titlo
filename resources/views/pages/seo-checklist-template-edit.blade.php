@component('component.card', [
    'titleHtml' => cabinet_sc_module_title_html(),
    'documentTitle' => cabinet_sc_document_title($template->title ?: __('Templates')),
])
    @slot('css')
        <link rel="stylesheet" href="{{ asset('css/cabinet-seo-checklist.css') }}?v={{ @filemtime(public_path('css/cabinet-seo-checklist.css')) ?: time() }}">
    @endslot

    <div class="cabinet-sc-page cabinet-sc-tpl-editor"
         id="cabinetScTplEditor"
         data-tpl-visual="v2"
         data-sc-hub="templates"
         data-csrf="{{ csrf_token() }}"
         data-tpl-move-url-template="{{ url('/checklist/templates/'.$template->id.'/tasks/__ID__/move') }}"
         data-tpl-stage-move-url-template="{{ url('/checklist/templates/'.$template->id.'/stages/__KEY__/move') }}"
         data-tpl-stage-update-url-template="{{ url('/checklist/templates/'.$template->id.'/stages/__KEY__') }}"
         data-tpl-stage-delete-url-template="{{ url('/checklist/templates/'.$template->id.'/stages/__KEY__/delete') }}"
         data-tpl-subtask-url-template="{{ url('/checklist/templates/'.$template->id.'/tasks/__ID__/subtasks') }}"
         data-i18n-delete-subtask="{{ e(__('Delete this subtask?')) }}"
         data-i18n-delete-stage="{{ e(__('Delete this stage?')) }}"
         data-i18n-stage-has-tasks="{{ e(__('Stage has tasks')) }}"
         data-i18n-include-report="{{ e(__('Include in SEO reports')) }}"
         data-i18n-include-report-hint="{{ e(__('Include in SEO reports hint')) }}">
        @include('pages.partials.seo-checklist-nav', [
            'scTab' => 'template',
            'scContextTemplate' => $template,
            'scMyTasksCount' => $myTasksCount ?? null,
            'scReviewCount' => $reviewCount ?? null,
            'scShowReviewTab' => $showReviewTab ?? false,
            'scUnreadNotesCount' => $unreadNotesCount ?? null,
            'scProjectsCount' => $projectsCount ?? null,
            'scTeamCount' => $teamCount ?? null,
            'scTemplatesCount' => $templatesCount ?? null,
        ])

        @php
            $tplTaskTotal = 0;
            foreach ($stages as $st) {
                $tplTaskTotal += count($st['tasks'] ?? []);
            }
            $stageCount = count($stages);
        @endphp

        <div class="cabinet-sc-tpl-topbar">
            <div class="cabinet-sc-tpl-topbar__main">
                <div>
                    <p class="cabinet-sc-tpl-topbar__eyebrow">{{ __('Template editor') }}</p>
                    <h1 class="cabinet-sc-tpl-topbar__title">{{ $template->title }}</h1>
                    <p class="cabinet-sc-tpl-topbar__meta">
                        {{ __('Used in :count projects', ['count' => (int) ($usageCount ?? 0)]) }}
                        · {{ $stageCount }} {{ __('Stages') }}
                        · {{ number_format($tplTaskTotal, 0, '', ' ') }} {{ __('Tasks') }}
                        @if($template->is_system && $readOnly)
                            · {{ __('System template is read-only') }}
                        @elseif($template->is_system && !$readOnly)
                            · {{ __('System template admin editable') }}
                        @endif
                    </p>
                </div>
                <div class="cabinet-sc-tpl-topbar__actions">
                    <form method="post" action="{{ route('pages.seo-checklist.templates.duplicate', ['templateId' => $template->id]) }}">
                        @csrf
                        <button type="submit" class="cabinet-sc-tpl-chip-btn">{{ __('Duplicate') }}</button>
                    </form>
                    @if(!$readOnly && !$template->is_system)
                        @if(($usageCount ?? 0) > 0)
                            <button type="button" class="cabinet-sc-tpl-chip-btn is-disabled" disabled>{{ __('Delete') }}</button>
                        @else
                            <form method="post" action="{{ route('pages.seo-checklist.templates.delete', ['templateId' => $template->id]) }}"
                                  onsubmit='return confirm(@json(__("Delete this template?")));'>
                                @csrf
                                <button type="submit" class="cabinet-sc-tpl-chip-btn cabinet-sc-tpl-chip-btn--danger">{{ __('Delete') }}</button>
                            </form>
                        @endif
                    @endif
                </div>
            </div>
        </div>

        @if(session('success'))
            <div class="alert alert-success py-2 px-3 small">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger py-2 px-3 small">{{ session('error') }}</div>
        @endif

        @if(!$readOnly)
            <details class="cabinet-sc-tpl-settings mb-3" data-sc-tpl-settings-shell>
                <summary class="cabinet-sc-tpl-settings__summary">
                    <span class="cabinet-sc-tpl-settings__summary-title">{{ __('Template settings') }}</span>
                    <span class="cabinet-sc-tpl-settings__summary-meta">{{ $template->title }}</span>
                    <span class="cabinet-sc-tpl-settings__summary-action">{{ __('Edit') }}</span>
                </summary>
                <form method="post" action="{{ route('pages.seo-checklist.templates.update', ['templateId' => $template->id]) }}" class="cabinet-sc-tpl-settings__form">
                    @csrf
                    <div class="cabinet-sc-tpl-settings__grid">
                        <label class="cabinet-sc-tpl-settings__field">
                            <span>{{ __('Title') }}</span>
                            <input type="text" name="title" class="form-control" required value="{{ old('title', $template->title) }}">
                        </label>
                        <label class="cabinet-sc-tpl-settings__field">
                            <span>{{ __('Description') }}</span>
                            <input type="text" name="description" class="form-control" value="{{ old('description', $template->description) }}" placeholder="{{ __('Optional') }}">
                        </label>
                    </div>
                    <div class="cabinet-sc-tpl-settings__footer">
                        <label class="cabinet-sc-tpl-settings__check" title="{{ __('Skip weekends hint') }}">
                            <input type="checkbox" name="skip_weekends" value="1" @if(old('skip_weekends', $template->skip_weekends)) checked @endif>
                            <span>
                                <strong>{{ __('Skip weekends in due dates') }}</strong>
                                <em>{{ __('Skip weekends hint') }}</em>
                            </span>
                        </label>
                        <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
                    </div>
                </form>
            </details>
        @elseif(!empty($template->skip_weekends))
            <p class="small text-secondary mb-3">{{ __('Skip weekends in due dates') }}: {{ __('Yes') }}</p>
        @endif

        <div class="cabinet-sc-tpl-layout">
            <div class="cabinet-sc-tpl-rail-slot" data-sc-tpl-rail-slot>
                <nav class="cabinet-sc-tpl-rail" data-sc-tpl-rail aria-label="{{ __('Stages') }}">
                    <div class="cabinet-sc-tpl-rail__head">{{ __('Stages') }}</div>
                    <ul class="cabinet-sc-tpl-rail__list">
                        @foreach($stages as $railStage)
                            <li>
                                <a class="cabinet-sc-tpl-rail__link"
                                   href="#tpl-stage-{{ $railStage['key'] }}"
                                   data-sc-tpl-rail-link="{{ $railStage['key'] }}">
                                    <span class="cabinet-sc-tpl-rail__name">{{ $railStage['title'] }}</span>
                                    <span class="cabinet-sc-tpl-rail__count">{{ count($railStage['tasks'] ?? []) }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </nav>
            </div>

            <div class="cabinet-sc-tpl-workspace">
        <div class="cabinet-sc-toolbar cabinet-sc-toolbar--sticky mb-3" data-sc-tpl-search-bar>
            <input type="search"
                   class="form-control form-control-sm cabinet-sc-search"
                   placeholder="{{ __('Smart search checklist') }}…"
                   data-sc-tpl-search
                   autocomplete="off"
                   aria-label="{{ __('Smart search checklist') }}">
            <span class="cabinet-sc-search-count small text-secondary" data-sc-tpl-search-count></span>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-sc-tpl-chip="important">{{ __('Important') }}</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-sc-tpl-chip="repeat">{{ __('Recurring') }}</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-sc-tpl-expand>{{ __('Expand all') }}</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-sc-tpl-collapse>{{ __('Collapse all') }}</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-sc-tpl-collapse-tasks>{{ __('Collapse tasks') }}</button>
        </div>
        <p class="cabinet-sc-empty-filter small text-secondary d-none mb-3" data-sc-tpl-empty>{{ __('No tasks match filters') }}</p>

        @if(!$readOnly && $stageCount === 0)
            <div class="cabinet-sc-empty-stages mb-3">
                <div class="cabinet-sc-empty-stages__text">
                    <strong>{{ __('No stages yet') }}</strong>
                    <span>{{ __('No stages yet hint') }}</span>
                </div>
                <form method="post" action="{{ route('pages.seo-checklist.templates.stage.skeleton', ['templateId' => $template->id]) }}">
                    @csrf
                    <button type="submit" class="btn btn-primary">{{ __('Apply SEO skeleton') }}</button>
                </form>
            </div>
        @elseif(!$readOnly && $tplTaskTotal === 0)
            <div class="alert alert-info py-2 px-3 small mb-3" role="status">
                {{ __('Empty template how to add') }}
            </div>
        @endif

        <div class="cabinet-sc-stages" data-sc-tpl-stages>
            @foreach($stages as $stageIndex => $stage)
                @php
                    $stageSearch = mb_strtolower(trim(
                        ($stage['title'] ?? '') . ' ' .
                        ($stage['lead'] ?? '') . ' ' .
                        ($stage['key'] ?? '')
                    ));
                    $isFirstStage = $stageIndex === 0;
                    $isLastStage = $stageIndex === $stageCount - 1;
                    $stageTaskCount = count($stage['tasks'] ?? []);
                @endphp
                <details class="cabinet-sc-stage" id="tpl-stage-{{ $stage['key'] }}" data-sc-tpl-stage data-stage-key="{{ $stage['key'] }}" open>
                    <summary class="cabinet-sc-stage__summary">
                        @if(!$readOnly)
                            <span class="cabinet-sc-stage__order" data-sc-tpl-stage-controls>
                                <button type="button"
                                        class="cabinet-sc-stage__order-btn"
                                        data-sc-tpl-stage-move="up"
                                        @if($isFirstStage) disabled @endif
                                        title="{{ __('Move up') }}"
                                        aria-label="{{ __('Move up') }}">↑</button>
                                <button type="button"
                                        class="cabinet-sc-stage__order-btn"
                                        data-sc-tpl-stage-move="down"
                                        @if($isLastStage) disabled @endif
                                        title="{{ __('Move down') }}"
                                        aria-label="{{ __('Move down') }}">↓</button>
                            </span>
                            <span class="cabinet-sc-stage__edit" data-sc-tpl-stage-controls>
                                <input type="text"
                                       class="cabinet-sc-stage__title-input"
                                       data-sc-tpl-stage-title
                                       value="{{ $stage['title'] }}"
                                       aria-label="{{ __('Stage title') }}">
                                <input type="text"
                                       class="cabinet-sc-stage__lead-input"
                                       data-sc-tpl-stage-lead
                                       value="{{ $stage['lead'] ?? '' }}"
                                       placeholder="{{ __('Stage lead optional') }}"
                                       aria-label="{{ __('Stage lead optional') }}">
                            </span>
                            <span class="cabinet-sc-stage__aside" data-sc-tpl-stage-controls>
                                <span class="cabinet-sc-stage__meta" data-sc-tpl-stage-meta data-total="{{ $stageTaskCount }}">{{ $stageTaskCount }}</span>
                                <button type="button"
                                        class="cabinet-sc-stage__delete"
                                        data-sc-tpl-stage-delete
                                        @if($stageTaskCount > 0) disabled title="{{ __('Stage has tasks') }}" @else title="{{ __('Delete stage') }}" @endif
                                        aria-label="{{ __('Delete stage') }}">×</button>
                            </span>
                        @else
                            <span class="cabinet-sc-stage__title-wrap">
                                <span class="cabinet-sc-stage__title">{{ $stage['title'] }}</span>
                                @if(!empty($stage['lead']))
                                    <span class="cabinet-sc-stage__lead text-secondary">{{ $stage['lead'] }}</span>
                                @endif
                            </span>
                            <span class="cabinet-sc-stage__meta" data-sc-tpl-stage-meta data-total="{{ $stageTaskCount }}">{{ $stageTaskCount }}</span>
                        @endif
                    </summary>
                    <ul class="cabinet-sc-tasks">
                        @foreach($stage['tasks'] as $taskIndex => $task)
                            @include('pages.partials.seo-checklist-template-task-row', [
                                'template' => $template,
                                'task' => $task,
                                'taskIndex' => $taskIndex,
                                'isFirstInStage' => $taskIndex === 0,
                                'isLastInStage' => $taskIndex === count($stage['tasks']) - 1,
                                'roleLabels' => $roleLabels,
                                'stageSearch' => $stageSearch,
                                'readOnly' => $readOnly,
                            ])
                        @endforeach
                        @if(!$readOnly)
                            <li class="cabinet-sc-task cabinet-sc-task--tpl cabinet-sc-task--add" data-sc-tpl-add>
                                <form method="post"
                                      action="{{ route('pages.seo-checklist.templates.task.store', ['templateId' => $template->id]) }}"
                                      class="cabinet-sc-tpl-task"
                                      data-sc-tpl-add-form>
                                    @csrf
                                    <input type="hidden" name="stage_key" value="{{ $stage['key'] }}">
                                    <div class="cabinet-sc-tpl-task__field">
                                        <span class="cabinet-sc-tpl-task__label">{{ __('New task') }}</span>
                                        <input type="text" name="title" class="form-control form-control-sm" required placeholder="{{ __('New task') }}…">
                                    </div>
                                    <div class="cabinet-sc-tpl-task__footer">
                                        <div class="cabinet-sc-tpl-task__row">
                                            <select name="role" class="form-select form-select-sm">
                                                @foreach($roleLabels as $rk => $rl)
                                                    <option value="{{ $rk }}" @if($rk === 'owner') selected @endif>{{ $rl }}</option>
                                                @endforeach
                                            </select>
                                            <select name="repeat_rule" class="form-select form-select-sm">
                                                @include('pages.partials.seo-checklist-repeat-options', ['selected' => ''])
                                            </select>
                                            <label class="cabinet-sc-tpl-task__check small mb-0">
                                                <input type="checkbox" name="is_important" value="1">
                                                {{ __('Important') }}
                                            </label>
                                        </div>
                                        <div class="cabinet-sc-tpl-task__actions">
                                            <label class="cabinet-sc-tpl-task__check small mb-0" data-tip="{{ __('Include in SEO reports hint') }}">
                                                <input type="checkbox" name="include_in_report" value="1" checked>
                                                {{ __('Include in SEO reports') }}
                                            </label>
                                            <button type="submit" class="btn btn-sm btn-primary">{{ __('Add task') }}</button>
                                        </div>
                                    </div>
                                </form>
                            </li>
                        @endif
                    </ul>
                </details>
            @endforeach

            @if(!$readOnly)
                <form method="post"
                      action="{{ route('pages.seo-checklist.templates.stage.store', ['templateId' => $template->id]) }}"
                      class="cabinet-sc-add-stage">
                    @csrf
                    <div class="cabinet-sc-add-stage__title">{{ __('Add stage') }}</div>
                    <div class="cabinet-sc-add-stage__row">
                        <input type="text" name="title" class="form-control" required placeholder="{{ __('Stage title') }}…">
                        <input type="text" name="lead" class="form-control" placeholder="{{ __('Stage lead optional') }}">
                        <button type="submit" class="btn btn-primary">{{ __('Add stage') }}</button>
                    </div>
                </form>
            @endif
        </div>
            </div>
        </div>

    </div>

    @slot('js')
        <script src="{{ asset('js/cabinet-seo-checklist-hub.js') }}?v={{ @filemtime(public_path('js/cabinet-seo-checklist-hub.js')) ?: time() }}"></script>
        <script>
            (function () {
                var root = document.getElementById('cabinetScTplEditor');
                if (!root) return;
                var settings = root.querySelector('[data-sc-tpl-settings-shell]');

                try { localStorage.removeItem('cabinetScTplVisual'); } catch (e) {}

                function collapseTasks() {
                    root.querySelectorAll('[data-sc-tpl-task].is-editing').forEach(function (li) {
                        li.classList.remove('is-editing');
                        var btn = li.querySelector('[data-sc-tpl-compact-toggle]');
                        if (btn) btn.setAttribute('aria-expanded', 'false');
                    });
                }

                function openTask(li, scroll) {
                    if (!li) return;
                    collapseTasks();
                    li.classList.add('is-editing');
                    var btn = li.querySelector('[data-sc-tpl-compact-toggle]');
                    if (btn) btn.setAttribute('aria-expanded', 'true');
                    var stage = li.closest('[data-sc-tpl-stage]');
                    if (stage && !stage.open) stage.open = true;
                    if (scroll) {
                        try { li.scrollIntoView({ block: 'nearest', behavior: 'smooth' }); } catch (e) {}
                    }
                    var input = li.querySelector('[data-sc-tpl-title-input]');
                    if (input) {
                        setTimeout(function () { try { input.focus(); input.select(); } catch (e) {} }, 30);
                    }
                }

                if (settings) {
                    if (!settings.dataset.userOpened) {
                        settings.open = false;
                    }
                    settings.addEventListener('toggle', function () {
                        if (settings.open) {
                            settings.dataset.userOpened = '1';
                        }
                    });
                }

                root.addEventListener('click', function (e) {
                    var toggle = e.target.closest('[data-sc-tpl-compact-toggle]');
                    if (toggle) {
                        e.preventDefault();
                        var li = toggle.closest('[data-sc-tpl-task]');
                        if (!li) return;
                        if (li.classList.contains('is-editing')) {
                            collapseTasks();
                        } else {
                            openTask(li, false);
                        }
                        return;
                    }
                    var collapseBtn = e.target.closest('[data-sc-tpl-collapse-tasks]');
                    if (collapseBtn) {
                        e.preventDefault();
                        collapseTasks();
                    }
                });

                root.addEventListener('input', function (e) {
                    var input = e.target.closest('[data-sc-tpl-title-input]');
                    if (!input) return;
                    var li = input.closest('[data-sc-tpl-task]');
                    var title = li ? li.querySelector('[data-sc-tpl-compact-title]') : null;
                    if (title) title.textContent = input.value || '—';
                });

                root.querySelectorAll('[data-sc-tpl-rail-link]').forEach(function (link) {
                    link.addEventListener('click', function () {
                        root.querySelectorAll('[data-sc-tpl-rail-link]').forEach(function (other) {
                            other.classList.remove('is-active');
                        });
                        link.classList.add('is-active');
                    });
                });

                if (location.hash && location.hash.indexOf('#tpl-task-') === 0) {
                    var hashLi = document.querySelector(location.hash);
                    if (hashLi) {
                        openTask(hashLi, true);
                    }
                }
            })();
        </script>
    @endslot
@endcomponent
