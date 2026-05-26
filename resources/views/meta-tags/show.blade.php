@component('component.card', [
    'title' => __('Meta tags histories page title', ['name' => $project->name]),
    'titleHtml' => e(__('Meta tags histories page title', ['name' => $project->name]))
        . view('partials.cabinet-module-version-badge', ['configKey' => 'cabinet-meta-tags'])->render(),
])
    @slot('css')
        <link rel="stylesheet" href="{{ asset('plugins/toastr/toastr.min.css') }}">
        <link rel="stylesheet" href="{{ asset('css/cabinet-meta-tags.css') }}?v={{ @filemtime(public_path('css/cabinet-meta-tags.css')) ?: time() }}">
    @endslot

    <div class="cabinet-mt-page cabinet-mt-histories-page"
         id="cabinet-mt-histories"
         data-msg-ideal="{{ e(__('Meta tags ideal saved')) }}"
         data-msg-deleted="{{ e(__('Meta tags snapshot deleted')) }}"
         data-msg-delete-confirm="{{ e(__('Meta tags delete snapshot confirm')) }}"
         data-msg-no-more="{{ e(__('Meta tags histories no more')) }}"
         data-msg-compare-disabled="{{ e(__('Meta tags histories compare disabled')) }}">

        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <a href="{{ route('meta-tags.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>{{ __('Meta tags histories back') }}
            </a>
            <span class="text-secondary small ms-auto">
                {{ __('Meta tags histories project card') }}: <strong>{{ $project->name }}</strong>
                · {{ __('Meta tags histories count') }}: <strong>{{ $histories->total() }}</strong>
            </span>
        </div>

        @include('meta-tags.partials.histories-how-to')

        <div class="card shadow-sm border-0 cabinet-mt-histories-card">
            <div class="card-header py-2">
                <h3 class="card-title h6 mb-0">{{ __('Histories') }}</h3>
            </div>

            @if($histories->isEmpty())
                <div class="card-body">
                    <p class="text-secondary small mb-0">{{ __('Meta tags histories empty') }}</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-sm table-hover table-striped align-middle mb-0 cabinet-mt-histories-table">
                        <thead class="table-light">
                        <tr>
                            <th class="text-nowrap">#</th>
                            <th>{{ __('Date') }}</th>
                            <th>{{ __('Time') }}</th>
                            <th>{{ __('Meta tags histories open') }}</th>
                            <th class="text-center">{{ __('Meta tags histories pages') }}</th>
                            <th class="text-center">{{ __('Meta tags histories errors') }}</th>
                            <th style="min-width: 11rem">{{ __('Meta tags histories compare with') }}</th>
                            <th class="text-center" title="{{ __('Select the story you want to follow.') }}">
                                {{ __('Meta tags histories ideal title') }}
                                <i class="bi bi-question-circle text-secondary" data-bs-toggle="tooltip" data-bs-placement="top"
                                   title="{{ __('Select the story you want to follow.') }}"></i>
                            </th>
                            <th class="text-end">{{ __('Actions') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($histories as $history)
                            <tr data-history-id="{{ $history->id }}">
                                <td class="text-muted">{{ $history->id }}</td>
                                <td class="text-nowrap">{{ $history->created_at->format('d.m.Y') }}</td>
                                <td class="text-nowrap">{{ $history->created_at->format('H:i') }}</td>
                                <td>
                                    <a class="btn btn-outline-primary btn-sm"
                                       href="{{ url('/meta-tags/history/' . $history->id) }}">
                                        <i class="bi bi-eye me-1" aria-hidden="true"></i>{{ __('Meta tags histories open') }}
                                    </a>
                                </td>
                                <td class="text-center">{{ $history->quantity }}</td>
                                <td class="text-center">
                                    @if($history->error_quantity === null)
                                        <span class="text-secondary" title="{{ __('Meta tags histories errors pending') }}">—</span>
                                    @elseif($history->error_quantity)
                                        <span class="badge text-bg-danger">{{ $history->error_quantity }}</span>
                                    @else
                                        <span class="text-secondary">0</span>
                                    @endif
                                </td>
                                <td>
                                    <select name="compare"
                                            class="form-select form-select-sm cabinet-mt-compare-select"
                                            aria-label="{{ __('Meta tags histories compare with') }}">
                                        <option value="">{{ __('Meta tags histories compare pick') }}</option>
                                        @foreach($histories as $option)
                                            @if($option->id !== $history->id)
                                                <option value="{{ $option->id }}">
                                                    {{ $option->created_at->format('d.m.Y H:i') }} (#{{ $option->id }})
                                                </option>
                                            @endif
                                        @endforeach
                                    </select>
                                </td>
                                <td class="text-center">
                                    <div class="form-check d-inline-block mb-0">
                                        <input class="form-check-input cabinet-mt-ideal-radio"
                                               type="radio"
                                               name="ideal"
                                               id="ideal-{{ $history->id }}"
                                               value="{{ $history->id }}"
                                               @if($history->ideal) checked @endif>
                                        <label class="form-check-label small" for="ideal-{{ $history->id }}">#{{ $history->id }}</label>
                                    </div>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a class="btn btn-outline-secondary"
                                           href="{{ route('meta.history.export', $history->id) }}"
                                           title="{{ __('Export') }}">
                                            <i class="bi bi-download" aria-hidden="true"></i>
                                        </a>
                                        <a class="btn btn-outline-primary compare-history disabled"
                                           href="{{ route('meta.history.compare', [$history->id, $history->id]) }}"
                                           title="{{ __('Compare') }}"
                                           aria-disabled="true">
                                            <i class="bi bi-arrow-left-right" aria-hidden="true"></i>
                                        </a>
                                        <a class="btn btn-outline-danger delete-history"
                                           href="{{ route('meta.history.delete', $history->id) }}"
                                           title="{{ __('Delete') }}">
                                            <i class="bi bi-trash" aria-hidden="true"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="card-footer d-flex flex-wrap align-items-center gap-2 py-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="lazy-load" @if(!$histories->hasMorePages()) disabled @endif>
                        <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>{{ __('Meta tags histories load more') }}
                    </button>
                    <div class="ms-auto cabinet-mt-histories-pagination">
                        {{ $histories->links() }}
                    </div>
                </div>
            @endif
        </div>
    </div>

    @slot('js')
        <script src="{{ asset('plugins/toastr/toastr.min.js') }}"></script>
        <script>
            toastr.options = { timeOut: 1500 };

            $(function () {
                var root = document.getElementById('cabinet-mt-histories');
                if (!root) {
                    return;
                }

                var msg = {
                    ideal: root.dataset.msgIdeal,
                    deleted: root.dataset.msgDeleted,
                    deleteConfirm: root.dataset.msgDeleteConfirm,
                    noMore: root.dataset.msgNoMore,
                    compareDisabled: root.dataset.msgCompareDisabled
                };

                $('[data-bs-toggle="tooltip"]').tooltip();

                var tbody = $('.cabinet-mt-histories-table tbody');

                tbody.on('change', 'input[name="ideal"]', function () {
                    $.ajax({
                        method: 'PUT',
                        url: '/meta-tags/histories/ideal/{{ $project->id }}',
                        data: {
                            _token: $('meta[name="csrf-token"]').attr('content'),
                            id: $(this).val()
                        }
                    }).done(function () {
                        toastr.success(msg.ideal);
                    });
                });

                tbody.on('click', '.delete-history', function (e) {
                    e.preventDefault();
                    if (!confirm(msg.deleteConfirm)) {
                        return;
                    }
                    var $row = $(this).closest('tr');
                    axios.delete($(this).attr('href')).then(function () {
                        $row.remove();
                        toastr.info(msg.deleted);
                    });
                });

                function updateCompareLink($row) {
                    var idCompare = $row.find('select[name="compare"]').val();
                    var $btn = $row.find('.compare-history');
                    var parts = $btn.attr('href').split('/');
                    var currentId = String($row.data('history-id'));

                    if (!idCompare || idCompare === currentId) {
                        $btn.addClass('disabled').attr('aria-disabled', 'true').attr('title', msg.compareDisabled);
                        return;
                    }

                    parts[parts.length - 1] = idCompare;
                    $btn.attr('href', parts.join('/'))
                        .removeClass('disabled')
                        .attr('aria-disabled', 'false')
                        .attr('title', '{{ __('Compare') }}');
                }

                tbody.on('change', 'select[name="compare"]', function () {
                    updateCompareLink($(this).closest('tr'));
                });

                tbody.find('tr').each(function () {
                    updateCompareLink($(this));
                });

                var LazyLoad = function () {
                    var self = $(this);
                    var pagination = $('.cabinet-mt-histories-pagination .pagination');
                    var current = pagination.find('.page-item.active, li.active');
                    var next = current.next();

                    if (next.find('a').length) {
                        self.prop('disabled', true);
                        tbody.css('cursor', 'wait');
                        $.get(next.find('a').attr('href'), function (response) {
                            var $html = $(response);
                            tbody.append($html.find('.cabinet-mt-histories-table tbody tr'));
                            var paging = $html.find('.cabinet-mt-histories-pagination .pagination, .pagination');
                            $('.cabinet-mt-histories-pagination').html(paging);
                            self.prop('disabled', false);
                            tbody.css('cursor', 'auto');
                            tbody.find('tr').each(function () {
                                updateCompareLink($(this));
                            });
                            if (!paging.find('a').length) {
                                self.prop('disabled', true);
                            }
                        });
                    } else {
                        toastr.info(msg.noMore);
                        self.prop('disabled', true);
                    }
                };

                var lazyLoadTimer;
                $('#lazy-load').on('click', function () {
                    var btn = $(this);
                    if (btn.prop('disabled')) {
                        return;
                    }
                    clearTimeout(lazyLoadTimer);
                    lazyLoadTimer = setTimeout(function () {
                        LazyLoad.call(btn);
                    }, 300);
                });
            });
        </script>
    @endslot
@endcomponent
