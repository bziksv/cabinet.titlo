@component('component.card', [
    'title' => __('Meta tags'),
    'titleHtml' => e(__('Meta tags')) . view('partials.cabinet-module-version-badge', ['configKey' => 'cabinet-meta-tags'])->render(),
])
    @slot('css')
        <link rel="stylesheet" type="text/css" href="{{ asset('plugins/common/css/datatable.css') }}"/>
        <link rel="stylesheet" href="{{ asset('css/cabinet-meta-tags.css') }}?v={{ @filemtime(public_path('css/cabinet-meta-tags.css')) ?: time() }}">
    @endslot

    <div class="cabinet-mt-page cabinet-mt-settings-page">
        @include('meta-tags.partials.module-nav', ['active' => 'settings'])

        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        <div class="row g-3 align-items-start">
            <div class="col-xl-8">
                <div class="card shadow-sm border-0 mb-3">
                    <div class="card-header py-2">
                        <h3 class="card-title h6 mb-0">{{ __('Meta tags admin steps title') }}</h3>
                    </div>
                    <div class="card-body">
                        <p class="text-secondary small">{{ __('Meta tags index lead') }}</p>
                        <ol class="mb-0 ps-3">
                            @foreach([
                                ['title' => __('Meta tags step 1 title'), 'hint' => __('Meta tags step 1 hint')],
                                ['title' => __('Meta tags step 2 title'), 'hint' => __('Meta tags step 2 hint')],
                                ['title' => __('Meta tags step 3 title'), 'hint' => __('Meta tags step 3 hint')],
                            ] as $step)
                                <li class="mb-2">
                                    <strong>{{ $step['title'] }}</strong>
                                    <p class="text-secondary small mb-0">{{ $step['hint'] }}</p>
                                </li>
                            @endforeach
                        </ol>
                    </div>
                </div>

                <div class="card shadow-sm border-0">
                    <div class="card-header py-2">
                        <h3 class="card-title h6 mb-0">{{ __('Settings') }}</h3>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('meta-tags.settings') }}" method="post" class="row g-3">
                            @csrf
                            <div class="col-lg-6">
                                <label class="form-label" for="delete_records">
                                    {{ __('Meta tags settings retention') }}
                                </label>
                                <input type="number"
                                       name="delete_records"
                                       id="delete_records"
                                       min="0"
                                       class="form-control form-control-sm"
                                       value="{{ old('delete_records', $delete_records) }}">
                                <p class="form-text small mb-0">{{ __('Meta tags settings retention hint') }}</p>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="bi bi-save me-1" aria-hidden="true"></i>{{ __('Update') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="info-box shadow-sm mb-3">
                    <span class="info-box-icon text-bg-primary">
                        <i class="bi bi-folder2-open" aria-hidden="true"></i>
                    </span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ __('Meta tags stat projects') }}</span>
                        <span class="info-box-number">{{ number_format($stats['projects_total'] ?? 0, 0, ',', ' ') }}</span>
                        <span class="info-box-text">{{ __('Meta tags stat active projects') }}: {{ number_format($stats['projects_active'] ?? 0, 0, ',', ' ') }}</span>
                    </div>
                </div>
                <div class="info-box shadow-sm mb-3">
                    <span class="info-box-icon text-bg-info">
                        <i class="bi bi-link-45deg" aria-hidden="true"></i>
                    </span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ __('Meta tags stat pages') }}</span>
                        <span class="info-box-number">{{ number_format($stats['pages_total'] ?? 0, 0, ',', ' ') }}</span>
                        <span class="info-box-text">{{ __('Meta tags stat snapshots 7d') }}: {{ number_format($stats['snapshots_7d'] ?? 0, 0, ',', ' ') }}</span>
                    </div>
                </div>
                <div class="info-box shadow-sm">
                    <span class="info-box-icon text-bg-secondary">
                        <i class="bi bi-people" aria-hidden="true"></i>
                    </span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ __('Meta tags stat users') }}</span>
                        <span class="info-box-number">{{ number_format($stats['users_with_projects'] ?? 0, 0, ',', ' ') }}</span>
                        <span class="info-box-text">{{ __('Users with Telegram') }}: {{ number_format($stats['users_telegram'] ?? 0, 0, ',', ' ') }}</span>
                    </div>
                </div>
            </div>
        </div>

        @include('meta-tags.partials.admin-registry', ['registry' => $registry])
    </div>

    @slot('js')
        <script src="{{ asset('plugins/datatables/jquery.dataTables.min.js') }}"></script>
        @include('layouts.partials.vendor-datatables-js', ['bundle' => 'rb-min'])
        <script src="{{ asset('plugins/datatables/buttons/buttons.min.js') }}"></script>
        <script src="{{ asset('plugins/datatables/search.js') }}"></script>
        <script>
            $(document).ready(function () {
                var $table = $('#cabinet-mt-registry-table');
                if (!$table.length) return;

                var registryTable = $table.DataTable({
                    dom: '<"row align-items-center g-2 cabinet-mt-dt-controls"<"col-sm-auto"l><"col-sm-auto ms-auto"f>>rt<"row align-items-center g-2 cabinet-mt-dt-footer"<"col-sm-auto"i><"col-sm-auto ms-auto"p>>',
                    autoWidth: false,
                    pageLength: 25,
                    order: [[0, 'asc'], [3, 'asc']],
                    language: {
                        paginate: { first: '«', last: '»', next: '»', previous: '«' },
                    },
                    oLanguage: {
                        sSearch: @json(__('Search') . ':'),
                        sLengthMenu: @json(__('show') . ' _MENU_ ' . __('records')),
                        sEmptyTable: @json(__('No records')),
                        sInfo: @json(__('Showing') . ' ' . __('from') . ' _START_ ' . __('to') . ' _END_ ' . __('of') . ' _TOTAL_ ' . __('entries')),
                    },
                });

                if (typeof search === 'function') {
                    search(registryTable);
                }

                if (window.location.hash === '#cabinet-mt-admin-registry') {
                    var el = document.getElementById('cabinet-mt-admin-registry');
                    if (el) {
                        el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                }
            });
        </script>
    @endslot
@endcomponent
