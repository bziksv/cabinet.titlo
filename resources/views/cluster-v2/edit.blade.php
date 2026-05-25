@component('component.card', [
    'title' => __('Hands editor v2'),
    'titleHtml' => e(__('Hands editor v2'))
        . view('partials.cabinet-module-version-badge', ['configKey' => 'cabinet-cluster'])->render(),
])
    @slot('css')
        <link rel="stylesheet" href="{{ asset('plugins/keyword-generator/css/font-awesome-4.7.0/css/font-awesome.css') }}">
        <link rel="stylesheet" href="{{ asset('plugins/toastr/toastr.css') }}">
        <link rel="stylesheet" href="{{ asset('plugins/select2/css/select2.min.css') }}">
        <link rel="stylesheet" href="{{ asset('plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css') }}">
        <link rel="stylesheet" href="{{ asset('css/cabinet-cluster-v2.css') }}?v={{ @filemtime(public_path('css/cabinet-cluster-v2.css')) ?: time() }}">
    @endslot

    <div class="cabinet-cluster-v2-page cabinet-cluster-edit-v2" id="cabinet-cluster-edit-v2-root">
        @include('cluster-v2.partials.edit-nav', [
            'active' => 'edit-v2',
            'clusterId' => $cluster['id'],
            'admin' => $admin,
            'cluster' => $cluster,
        ])

        <div class="alert alert-info border-0 shadow-sm mb-3" role="status">
            <strong>Ручное редактирование v2.</strong>
            Слева — оглавление групп (релевантность, объединение). Фразы можно перетаскивать за ⋮⋮ в другую группу, в таблицу или на название группы слева.
            <a href="{{ route('edit.clusters', $cluster['id']) }}" class="alert-link ms-1">{{ __('Hands editor v1') }}</a>
            — полный drag-and-drop с рабочей областью.
        </div>

        <div class="row g-3 cabinet-cluster-edit-v2__layout">
            <div class="col-lg-3 cabinet-cluster-edit-v2__sidebar-col">
                @include('cluster-v2.partials.edit-groups-sidebar', compact('groups', 'singles'))
            </div>
            <div class="col-lg-9 cabinet-cluster-edit-v2__main">

        <div class="card shadow-sm mb-3">
            <div class="card-body py-2">
                <div class="row g-2 align-items-center text-sm">
                    <div class="col-md-3">
                        <span class="text-muted">{{ __('Number of phrases') }}:</span>
                        <strong id="cl-edit-count-phrases">{{ $cluster['count_phrases'] }}</strong>
                    </div>
                    <div class="col-md-3">
                        <span class="text-muted">{{ __('Number of clusters') }}:</span>
                        <strong id="cl-edit-count-clusters">{{ $cluster['count_clusters'] }}</strong>
                    </div>
                    <div class="col-md-3">
                        <span class="text-muted">{{ __('Region') }}:</span>
                        {{ \App\Common::getRegionName($cluster['request']['region']) }}
                    </div>
                    <div class="col-md-3 text-md-end">
                        <a href="{{ route('show.cluster.result', $cluster['id']) }}" class="btn btn-outline-secondary btn-sm">
                            {{ __('My project') }}
                        </a>
                        <a href="{{ route('download.cluster.result', ['cluster' => $cluster['id'], 'type' => 'xls']) }}" class="btn btn-outline-secondary btn-sm" target="_blank">XLS</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-3">
            <div class="card-body py-2">
                <div class="row g-2 align-items-center">
                    <div class="col-md-6">
                        <input type="search" class="form-control form-control-sm" id="cl-edit-search"
                               placeholder="Поиск по фразе, кластеру или URL…" autocomplete="off">
                    </div>
                    <div class="col-md-6 d-flex flex-wrap gap-2 align-items-center justify-content-md-end">
                        <span class="text-muted small d-none" id="cl-edit-search-hint"></span>
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" id="cl-edit-only-singles">
                            <label class="form-check-label small" for="cl-edit-only-singles">{{ __('Unallocated words') }}</label>
                        </div>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="cl-edit-reset-filter">{{ __('Reset') }}</button>
                    </div>
                </div>
            </div>
        </div>

        @if(count($singles))
            <div class="card shadow-sm mb-3 border-warning cabinet-cluster-edit-v2__singles" id="cl-edit-singles-block">
                <div class="card-header bg-warning-subtle d-flex justify-content-between align-items-center py-2">
                    <h3 class="card-title h6 mb-0">{{ __('Unallocated words') }}</h3>
                    <span class="badge text-bg-warning">{{ count($singles) }}</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0 cabinet-cluster-edit-v2__table">
                        <thead class="table-light">
                        <tr>
                            <th style="width:28px" aria-label="Перетащить"></th>
                            <th>{{ __('Key phrases') }}</th>
                            <th class="text-end" style="width:120px">{{ __('Base') }} / {{ __('Phrasal') }} / {{ __('Target') }}</th>
                            <th style="width:220px">{{ __('Relevance') }}</th>
                            <th style="width:260px">{{ __('Move to cluster') }}</th>
                        </tr>
                        </thead>
                        <tbody class="cl-edit-sortable-tbody" data-target-group="__single__">
                        @foreach($singles as $row)
                            @include('cluster-v2.partials.edit-phrase-row', [
                                'row' => $row,
                                'fromGroup' => $row['from'],
                                'groupNames' => $groupNames,
                                'isSingle' => true,
                            ])
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <div id="cl-edit-groups">
            @foreach($groups as $group)
                @php($groupSlug = 'cl-edit-group-' . md5($group['name']))
                <div class="card shadow-sm mb-3 cabinet-cluster-edit-v2__group" id="{{ $groupSlug }}" data-group="{{ $group['name'] }}">
                    <div class="card-header py-2">
                        <div class="row g-2 align-items-center">
                            <div class="col-lg-5">
                                <input type="text" class="form-control form-control-sm cl-edit-rename"
                                       value="{{ $group['name'] }}"
                                       data-old-name="{{ $group['name'] }}"
                                       aria-label="{{ __('Change the name') }}">
                            </div>
                            <div class="col-lg-7 d-flex flex-wrap gap-2 justify-content-lg-end align-items-center small text-muted">
                                <span>{{ __('number of phrases: ') }}<strong class="cl-edit-group-count">{{ count($group['phrases']) }}</strong></span>
                                <span class="cl-edit-group-freq">{{ $group['totals']['based'] }} / {{ $group['totals']['phrased'] }} / {{ $group['totals']['target'] }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0 cabinet-cluster-edit-v2__table">
                            <thead class="table-light">
                            <tr>
                                <th style="width:28px" aria-label="Перетащить"></th>
                                <th>{{ __('Key phrases') }}</th>
                                <th class="text-end" style="width:120px">{{ __('Base') }} / {{ __('Phrasal') }} / {{ __('Target') }}</th>
                                <th style="width:220px">{{ __('Relevance') }}</th>
                                <th style="width:260px">{{ __('Move to cluster') }}</th>
                            </tr>
                            </thead>
                            <tbody class="cl-edit-sortable-tbody" data-target-group="{{ $group['name'] }}">
                            @foreach($group['phrases'] as $row)
                                @include('cluster-v2.partials.edit-phrase-row', [
                                    'row' => $row,
                                    'fromGroup' => $group['name'],
                                    'groupNames' => $groupNames,
                                    'isSingle' => false,
                                ])
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        </div>

            </div>
        </div>

        <div class="modal fade" id="cl-edit-new-group-modal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ __('Adding a new group') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                    </div>
                    <div class="modal-body">
                        <label class="form-label" for="cl-edit-new-group-name">{{ __('Name of the new group') }}</label>
                        <input type="text" class="form-control" id="cl-edit-new-group-name" autocomplete="off">
                        <div class="form-text">{{ __('The name must be unique') }}</div>
                        <input type="hidden" id="cl-edit-new-group-phrase" value="">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                        <button type="button" class="btn btn-primary" id="cl-edit-new-group-save">{{ __('Add') }}</button>
                    </div>
                </div>
            </div>
        </div>

        @isset($cluster['default_result'])
            <div class="modal fade" id="cl-edit-reset-modal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">{{ __('Rolling back all changes') }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            {{ __('You can roll back the scan results to the initial state.') }}
                            <p class="text-danger mb-0 mt-2">{{ __('This action cannot be undone.') }}</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                            <button type="button" class="btn btn-danger" id="cl-edit-reset-confirm">{{ __('Roll back changes') }}</button>
                        </div>
                    </div>
                </div>
            </div>
        @endisset
    </div>

    @slot('js')
        <script src="{{ asset('plugins/toastr/toastr.min.js') }}"></script>
        <script>
            window.clusterEditV2 = {
                clusterId: {{ (int) $cluster['id'] }},
                groupNames: @json($groupNames),
                routes: {
                    move: @json(route('edit.cluster')),
                    rename: @json(route('change.group.name')),
                    newGroup: @json(route('confirmation.new.cluster')),
                    checkName: @json(route('check.group.name')),
                    reset: @json(route('reset.all.cluster.changes')),
                },
                csrf: @json(csrf_token()),
                i18n: {
                    saved: @json(__('Successfully')),
                    error: @json(__('Error')),
                    moving: @json('Сохранение…'),
                    chooseCluster: @json('— выберите кластер —'),
                    unallocated: @json(__('Unallocated words')),
                    newCluster: @json('— новый кластер —'),
                    emptyName: @json(__('The group name cannot be empty and contain numbers')),
                    renameError: @json(__('A group with the same name already exists or the name contains numbers')),
                    noResults: @json('Ничего не найдено'),
                    searching: @json('Поиск…'),
                    typeToSearch: @json('Введите текст для поиска'),
                    mergeOk: @json('Группы объединены'),
                },
            };
        </script>
        <script src="{{ asset('plugins/sortable/sortable.min.js') }}"></script>
        <script src="{{ asset('plugins/select2/js/select2.full.min.js') }}"></script>
        <script src="{{ asset('js/cabinet-select2-defaults.js') }}"></script>
        <script src="{{ asset('js/cabinet-cluster-edit-v2.js') }}?v={{ @filemtime(public_path('js/cabinet-cluster-edit-v2.js')) ?: time() }}"></script>
    @endslot
@endcomponent
