@component('component.card', [
    'title' => __('HTML editor'),
    'titleHtml' => e(__('HTML editor')) . view('partials.cabinet-module-version-badge', ['configKey' => 'cabinet-html-editor'])->render(),
])
    @slot('css')
        <link rel="stylesheet" href="{{ asset('css/cabinet-html-editor.css') }}?v={{ @filemtime(public_path('css/cabinet-html-editor.css')) ?: time() }}">
        <style>
            #header-nav-bar .cabinet-header-limits-menu tr.HtmlEditor {
                background: oldlace;
            }
        </style>
    @endslot

    @php
        $maxProjects = (int) config('cabinet-html-editor.limits.max_projects', 20);
        $maxTexts = (int) config('cabinet-html-editor.limits.max_texts_per_project', 30);
    @endphp

    <div class="cabinet-html-editor-page">
        <div class="alert alert-light border cabinet-he-howto mb-3" role="note">
            <p class="fw-semibold mb-2">{{ __('How the HTML editor works') }}</p>
            <ul class="mb-0 ps-3 small text-secondary cabinet-he-features-list">
                <li>{{ __('HTML editor feature split') }}</li>
                <li>{{ __('HTML editor feature projects') }}</li>
                <li>{{ __('HTML editor feature presets') }}</li>
                <li>{{ __('HTML editor feature public share') }}</li>
                <li>{{ __('HTML editor feature search') }}</li>
            </ul>
        </div>

        <p class="cabinet-he-summary-bar text-muted mb-4 px-1">
            {{ __('Projects') }}: <strong class="text-body">{{ $projectCount }}</strong> / {{ $maxProjects }}
            · {{ __('HTML texts') }}: <strong class="text-body">{{ $textCount }}</strong>
            · {{ __('Editor') }}: CKEditor
        </p>

        @if($projectCount === 0)
            <div class="alert alert-light border cabinet-he-empty text-center py-5 mb-0" role="status">
                <i class="bi bi-folder2-open display-6 text-muted d-block mb-3" aria-hidden="true"></i>
                <p class="fw-semibold mb-1">{{ __('No projects yet') }}</p>
                <p class="text-muted small mb-3">{{ __('Create a project and write the first text in the visual editor.') }}</p>
                <a href="{{ route('create.project') }}" class="btn btn-secondary">{{ __('Create a project') }}</a>
            </div>
        @else
            <div class="cabinet-he-search-toolbar row g-2 mb-3" data-he-search-toolbar>
                <div class="col-md-6">
                    <label class="form-label small text-secondary mb-1" for="cabinet-he-search-projects">{{ __('Search projects') }}</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="bi bi-folder2-open" aria-hidden="true"></i></span>
                        <input type="search" class="form-control" id="cabinet-he-search-projects" data-he-search-projects placeholder="{{ __('Project name or note') }}" autocomplete="off">
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label small text-secondary mb-1" for="cabinet-he-search-texts">{{ __('Search texts') }}</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="bi bi-file-text" aria-hidden="true"></i></span>
                        <input type="search" class="form-control" id="cabinet-he-search-texts" data-he-search-texts placeholder="{{ __('Text content in any project') }}" autocomplete="off">
                    </div>
                </div>
                <div class="col-12">
                    <p class="small text-muted mb-0 d-none" data-he-search-empty role="status">{{ __('Nothing found. Try another query.') }}</p>
                </div>
            </div>

            <div class="row g-3 cabinet-he-layout">
                <div class="col-lg-4">
                    <div class="cabinet-he-sidebar h-100">
                        <div class="cabinet-he-sidebar-head d-flex flex-wrap align-items-center justify-content-between gap-2">
                            <span class="fw-semibold small text-uppercase text-secondary">{{ __('Project list') }}</span>
                            <a href="{{ route('create.project') }}" class="btn btn-secondary btn-sm click_tracking" data-click="Create a project">
                                <i class="bi bi-plus-lg" aria-hidden="true"></i>
                                <span class="d-none d-sm-inline ms-1">{{ __('New') }}</span>
                            </a>
                        </div>
                        <div class="cabinet-he-project-nav" role="tablist" aria-label="{{ __('Project list') }}">
                            @foreach($projects as $project)
                                <button type="button"
                                        class="cabinet-he-project-tab list-group-item-action {{ $loop->first ? 'active' : '' }}"
                                        role="tab"
                                        id="cabinet-he-tab-{{ $project->id }}"
                                        data-he-project-tab="{{ $project->id }}"
                                        data-he-project-name="{{ mb_strtolower($project->project_name) }}"
                                        data-he-project-note="{{ mb_strtolower($project->short_description ?? '') }}"
                                        aria-controls="cabinet-he-panel-{{ $project->id }}"
                                        aria-selected="{{ $loop->first ? 'true' : 'false' }}">
                                    <span class="cabinet-he-project-tab-name">{{ $project->project_name }}</span>
                                    <span class="badge text-bg-light border flex-shrink-0">{{ count($project->descriptions) }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="col-lg-8">
                    @foreach($projects as $project)
                        @php
                            $textsInProject = $project->descriptions;
                            $textsCount = count($textsInProject);
                        @endphp
                        <div class="cabinet-he-detail cabinet-he-project-panel"
                             id="cabinet-he-panel-{{ $project->id }}"
                             role="tabpanel"
                             aria-labelledby="cabinet-he-tab-{{ $project->id }}"
                             data-he-project-panel="{{ $project->id }}"
                             @if(!$loop->first) hidden @endif>

                            <div class="cabinet-he-detail-head">
                                <p class="cabinet-he-detail-kicker mb-0">{{ __('Selected project') }}</p>
                                <dl class="cabinet-he-meta-grid">
                                    <dt>{{ __('Project name') }}</dt>
                                    <dd>{{ $project->project_name }}</dd>
                                    <dt>{{ __('Short description') }}</dt>
                                    <dd>{{ $project->short_description ?: '—' }}</dd>
                                    <dt>{{ __('HTML texts') }}</dt>
                                    <dd>{{ $textsCount }} / {{ $maxTexts }}</dd>
                                </dl>
                            </div>

                            <div class="d-flex flex-wrap cabinet-he-detail-actions">
                                <a href="{{ route('create.description', ['project_id' => $project->id]) }}"
                                   class="btn btn-primary btn-sm click_tracking"
                                   data-click="Add text to the project"
                                   @if($textsCount >= $maxTexts) aria-disabled="true" tabindex="-1" onclick="return false;" @endif>
                                    <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>{{ __('Add HTML text') }}
                                </a>
                                <a href="{{ route('edit.project', $project->id) }}" class="btn btn-outline-secondary btn-sm click_tracking" data-click="Edit project">
                                    <i class="bi bi-pencil me-1" aria-hidden="true"></i>{{ __('Rename project') }}
                                </a>
                                <button type="button" class="btn btn-outline-danger btn-sm ms-auto" data-bs-toggle="modal" data-bs-target="#cabinet-he-delete-project-{{ $project->id }}">
                                    <i class="bi bi-trash3 me-1" aria-hidden="true"></i>{{ __('Delete a project') }}
                                </button>
                            </div>

                            <p class="cabinet-he-texts-head mb-0">{{ __('HTML texts in this project') }}</p>

                            @if($textsCount === 0)
                                <div class="cabinet-he-empty-detail">
                                    <p class="mb-2">{{ __('No texts in this project yet.') }}</p>
                                    <a href="{{ route('create.description', ['project_id' => $project->id]) }}" class="btn btn-outline-primary btn-sm">{{ __('Add HTML text') }}</a>
                                </div>
                            @else
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover cabinet-he-texts-table mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th scope="col" class="cabinet-he-col-num">#</th>
                                                <th scope="col">{{ __('Text preview') }}</th>
                                                <th scope="col" class="cabinet-he-col-chars text-end">{{ __('Characters') }}</th>
                                                <th scope="col" class="cabinet-he-col-actions text-end">{{ __('Actions') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($textsInProject as $index => $description)
                                                @php
                                                    $plain = trim(preg_replace('/\s+/u', ' ', strip_tags($description->description)));
                                                    $excerpt = $plain !== '' ? \Illuminate\Support\Str::limit($plain, 90) : __('Empty text');
                                                    $chars = mb_strlen($plain);
                                                @endphp
                                                <tr data-he-text-row data-he-text-project="{{ $project->id }}" data-he-text-search="{{ mb_strtolower($plain . ' ' . $project->project_name . ' ' . ($project->short_description ?? '')) }}">
                                                    <td class="text-muted tabular-nums">{{ $index + 1 }}</td>
                                                    <td>
                                                        <a href="{{ route('edit.description', $description->id) }}" class="cabinet-he-text-link click_tracking" data-click="Edit description">
                                                            {{ $excerpt }}
                                                        </a>
                                                    </td>
                                                    <td class="text-end tabular-nums text-muted">{{ number_format($chars, 0, ',', ' ') }}</td>
                                                    <td class="text-end text-nowrap">
                                                        <a href="{{ route('edit.description', $description->id) }}" class="btn btn-outline-secondary btn-sm py-0 px-2" title="{{ __('Edit HTML text') }}">
                                                            <i class="bi bi-pencil-square" aria-hidden="true"></i>
                                                        </a>
                                                        <button type="button" class="btn btn-outline-danger btn-sm py-0 px-2 ms-1" data-bs-toggle="modal" data-bs-target="#cabinet-he-delete-text-{{ $description->id }}" title="{{ __('Delete HTML text') }}">
                                                            <i class="bi bi-trash3" aria-hidden="true"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>

                        <div class="modal fade" id="cabinet-he-delete-project-{{ $project->id }}" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-body">
                                        <p class="mb-1">{{ __('Delete project') }} <strong>{{ $project->project_name }}</strong>?</p>
                                        <p class="text-muted small mb-0">{{ __('All texts in this project will be deleted.') }}</p>
                                    </div>
                                    <div class="modal-footer">
                                        <a href="{{ route('delete.project', $project->id) }}" class="btn btn-danger">{{ __('Delete a project') }}</a>
                                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        @foreach($textsInProject as $description)
                            <div class="modal fade" id="cabinet-he-delete-text-{{ $description->id }}" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-body">
                                            <p class="mb-1">{{ __('Delete HTML text') }}?</p>
                                            <p class="text-muted small mb-0">{{ __('This action cannot be undone.') }}</p>
                                        </div>
                                        <div class="modal-footer">
                                            <form action="{{ route('delete.description', $description->id) }}" method="POST" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-danger">{{ __('Delete a text') }}</button>
                                            </form>
                                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    @slot('js')
        <script src="{{ asset('js/cabinet-html-editor.js') }}?v={{ @filemtime(public_path('js/cabinet-html-editor.js')) ?: time() }}"></script>
    @endslot
@endcomponent
