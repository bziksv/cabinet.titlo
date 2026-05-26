@extends('layouts.app')

@section('title', __('Main projects'))

@section('css')
    <link rel="stylesheet" href="{{ asset('css/cabinet-main-projects.css') }}">
@endsection

@section('content')
    <div class="cabinet-main-projects-page">
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
            <div>
                <h2 class="h4 mb-2 d-flex flex-wrap align-items-center gap-1">
                    <i class="bi bi-box-seam text-primary" aria-hidden="true"></i>
                    <span>{{ __('Menu modules') }}</span>
                    @include('partials.cabinet-module-version-badge', ['configKey' => 'cabinet-main-projects'])
                </h2>
                <p class="text-secondary small mb-0" style="max-width: 42rem;">
                    {{ __('These entries define tiles on the home page and items in the sidebar menu: title, link, icon, roles, and sort order.') }}
                </p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="#cabinet-mp-visit-stats" class="btn btn-sm btn-outline-info">
                    <i class="bi bi-bar-chart-line me-1"></i>{{ __('Visit statistics') }}
                </a>
                <a href="{{ route('main-projects.create') }}" class="btn btn-sm btn-primary">
                    <i class="bi bi-plus-lg me-1"></i>{{ __('Create new') }}
                </a>
            </div>
        </div>

        <div class="row g-3 mb-3 cabinet-mp-stat">
            <div class="col-12 col-md-4 d-flex">
                <div class="info-box mb-0 flex-fill">
                    <span class="info-box-icon text-bg-primary shadow-sm"><i class="bi bi-list-ol"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ __('Total modules') }}</span>
                        <span class="info-box-number">{{ $stats['total'] }}</span>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4 d-flex">
                <div class="info-box mb-0 flex-fill">
                    <span class="info-box-icon text-bg-success shadow-sm"><i class="bi bi-eye"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ __('Visible on home') }}</span>
                        <span class="info-box-number">{{ $stats['visible'] }}</span>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4 d-flex">
                <div class="info-box mb-0 flex-fill">
                    <span class="info-box-icon text-bg-info shadow-sm"><i class="bi bi-bar-chart"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ __('With visit statistics') }}</span>
                        <span class="info-box-number">{{ $stats['with_statistics'] }}</span>
                    </div>
                </div>
            </div>
        </div>

        @include('main-projects.partials.visit-statistics-summary', [
            'moduleStats' => $moduleStats,
            'visitTotals' => $visitTotals,
            'showUserStatistics' => !empty($showUserStatistics),
        ])

        <div class="card shadow-sm mt-3">
            <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                <h3 class="card-title h6 mb-0">{{ __('Module list') }}</h3>
                <div class="input-group input-group-sm" style="max-width: 16rem;">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="search"
                           class="form-control"
                           id="cabinet-mp-search"
                           placeholder="{{ __('Search by title') }}…"
                           autocomplete="off">
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped align-middle mb-0" id="cabinet-mp-table">
                        <thead class="table-light">
                        <tr>
                            <th scope="col" class="text-nowrap">{{ __('Order') }}</th>
                            <th scope="col">{{ __('Module') }}</th>
                            <th scope="col">{{ __('Description') }}</th>
                            <th scope="col">{{ __('Link') }}</th>
                            <th scope="col">{{ __('Access') }}</th>
                            <th scope="col" class="text-center">{{ __('Home') }}</th>
                            <th scope="col" class="text-center">{{ __('Stats') }}</th>
                            <th scope="col" class="text-end">{{ __('Actions') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($projects as $project)
                            @php
                                $link = localize_cabinet_url($project->link);
                                $roles = is_array($project->access) ? $project->access : [];
                                $color = $project->color && preg_match('/^#[0-9A-Fa-f]{6}$/', $project->color)
                                    ? $project->color : '#0d6efd';
                            @endphp
                            <tr data-mp-title="{{ __($project->title) }}">
                                <td class="text-nowrap">
                                    <span class="badge text-bg-secondary">{{ $project->position }}</span>
                                    <span class="text-secondary small d-block">#{{ $project->id }}</span>
                                </td>
                                <td class="cabinet-mp-module-cell">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="cabinet-mp-icon-preview"
                                              style="background: {{ $color }};"
                                              aria-hidden="true">
                                            {!! $project->icon !!}
                                        </span>
                                        <div class="min-w-0">
                                            <div class="fw-semibold text-break">{{ __($project->title) }}</div>
                                            <code class="small text-secondary">{{ $project->title }}</code>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="cabinet-mp-desc small text-secondary d-block">
                                        {{ $project->description ? __($project->description) : '—' }}
                                    </span>
                                </td>
                                <td>
                                    <a href="{{ $link }}"
                                       class="cabinet-mp-link small d-block"
                                       target="_blank"
                                       rel="noopener noreferrer">{{ $link }}</a>
                                </td>
                                <td class="cabinet-mp-roles">
                                    @forelse($roles as $role)
                                        <span class="badge text-bg-light text-body border me-1 mb-1">{{ $role }}</span>
                                    @empty
                                        <span class="text-secondary small">{{ __('All roles') }}</span>
                                    @endforelse
                                </td>
                                <td class="text-center">
                                    @if($project->show)
                                        <span class="badge text-bg-success">{{ __('Yes') }}</span>
                                    @else
                                        <span class="badge text-bg-secondary">{{ __('No') }}</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if(!empty($project->controller))
                                        <span class="badge text-bg-info" title="{{ __('Statistics enabled') }}">ON</span>
                                    @else
                                        <span class="text-secondary">—</span>
                                    @endif
                                </td>
                                <td class="text-end text-nowrap">
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('main-projects.edit', $project->id) }}"
                                           class="btn btn-outline-primary"
                                           title="{{ __('Edit') }}">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        @if(!empty($project->controller))
                                            <a href="{{ route('main-projects.statistics', $project->id) }}"
                                               class="btn btn-outline-info"
                                               target="_blank"
                                               rel="noopener"
                                               title="{{ __('Statistics') }}">
                                                <i class="bi bi-bar-chart"></i>
                                            </a>
                                        @endif
                                        <button type="button"
                                                class="btn btn-outline-danger cabinet-mp-delete"
                                                data-id="{{ $project->id }}"
                                                data-title="{{ __($project->title) }}"
                                                title="{{ __('Delete') }}">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-secondary py-5">
                                    {{ __('No modules yet.') }}
                                    <a href="{{ route('main-projects.create') }}">{{ __('Create the first one') }}</a>
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('js')
    <script>
        (function () {
            var search = document.getElementById('cabinet-mp-search');
            if (search) {
                search.addEventListener('input', function () {
                    var q = search.value.trim().toLowerCase();
                    document.querySelectorAll('#cabinet-mp-table tbody tr[data-mp-title]').forEach(function (row) {
                        var title = (row.getAttribute('data-mp-title') || '').toLowerCase();
                        row.style.display = q === '' || title.indexOf(q) !== -1 ? '' : 'none';
                    });
                });
            }

            document.querySelectorAll('.cabinet-mp-delete').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var id = btn.getAttribute('data-id');
                    var title = btn.getAttribute('data-title') || '';
                    if (!window.confirm(@json(__('Delete this module?')) + (title ? ' «' + title + '»' : ''))) {
                        return;
                    }
                    var url = @json(url('/main-projects/__ID__')).replace('__ID__', id);
                    var token = document.querySelector('meta[name="csrf-token"]');
                    fetch(url, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': token ? token.getAttribute('content') : '',
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({_method: 'DELETE'}),
                    }).then(function (response) {
                        if (response.ok) {
                            btn.closest('tr').remove();
                        }
                    });
                });
            });
        })();
    </script>
@endsection
