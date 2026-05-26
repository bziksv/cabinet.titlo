@extends('layouts.app')

@section('title', __('Roles and permissions'))

@section('css')
    <link rel="stylesheet" href="{{ asset('plugins/toastr/toastr.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/cabinet-manage-access.css') }}">
@endsection

@section('content')
    @php
        $rolesCount = $roles->count();
        $permissionsCount = $permissions->count();
    @endphp

    <div class="cabinet-manage-access-page">
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
            <div>
                <h2 class="h4 mb-2 d-flex flex-wrap align-items-center gap-1">
                    <i class="bi bi-shield-lock text-primary" aria-hidden="true"></i>
                    <span>{{ __('Roles and permissions') }}</span>
                    @include('partials.cabinet-module-version-badge', ['configKey' => 'cabinet-manage-access'])
                </h2>
                <p class="text-secondary small mb-0">{{ __('Assign cabinet permissions to roles. Users get access through their role.') }}</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('users.index') }}" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-people me-1"></i>{{ __('Users') }}
                </a>
                <a href="{{ route('main-projects.index') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-grid me-1"></i>{{ __('Menu modules') }}
                </a>
            </div>
        </div>

        <div class="row g-2 g-md-3 mb-3">
            <div class="col-6 col-md-4">
                <div class="info-box mb-0">
                    <span class="info-box-icon text-bg-primary shadow-sm"><i class="bi bi-person-badge"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ __('Roles') }}</span>
                        <span class="info-box-number">{{ $rolesCount }}</span>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-4">
                <div class="info-box mb-0">
                    <span class="info-box-icon text-bg-info shadow-sm"><i class="bi bi-key"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ __('Permissions') }}</span>
                        <span class="info-box-number">{{ $permissionsCount }}</span>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="info-box mb-0">
                    <span class="info-box-icon text-bg-secondary shadow-sm"><i class="bi bi-code-slash"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ __('In code') }}</span>
                        <span class="info-box-number small">@@can / middleware</span>
                    </div>
                </div>
            </div>
        </div>

        @include('manage-access.partials.guide')

        <div class="row g-3 align-items-start">
            <div class="col-lg-4">
                <div class="card shadow-sm h-100">
                    <div class="card-header py-2 d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <h3 class="card-title h6 mb-0">
                            <i class="bi bi-person-badge me-1"></i>{{ __('Roles') }}
                        </h3>
                        <button type="button" class="btn btn-sm btn-primary add-item" data-type="role">
                            <i class="bi bi-plus-lg me-1"></i>{{ __('Add role') }}
                        </button>
                    </div>
                    <div class="card-body cabinet-ma-roles-wrap">
                        @include('manage-access.partials.roles-panel')
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card shadow-sm h-100" id="permission">
                    <div class="card-header py-2 d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <h3 class="card-title h6 mb-0">
                            <i class="bi bi-key me-1"></i>{{ __('Permissions') }}
                        </h3>
                        <button type="button" class="btn btn-sm btn-primary add-item" data-type="permission">
                            <i class="bi bi-plus-lg me-1"></i>{{ __('Add permission') }}
                        </button>
                    </div>
                    <div class="card-body">
                        <p class="text-secondary small mb-3">
                            <i class="bi bi-grip-vertical me-1"></i>{{ __('Drag onto a role on the left. Grouped by area of the cabinet.') }}
                        </p>
                        @include('manage-access.partials.permissions-panel')
                    </div>
                </div>
            </div>

            <div class="col-lg-3">
                @include('manage-access.partials.users-sidebar', ['userStats' => $userStats])
            </div>
        </div>

        <div class="card shadow-sm mt-3">
            <div class="card-header py-2">
                <button class="btn btn-link text-decoration-none p-0 fw-semibold text-body collapsed"
                        type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#cabinet-ma-can-examples"
                        aria-expanded="false">
                    <i class="bi bi-code-square me-1"></i>{{ __('Usage in templates (can directive)') }}
                </button>
            </div>
            <div id="cabinet-ma-can-examples" class="collapse">
                <div class="card-body small">
                    @verbatim
                    <pre class="mb-3 bg-body-secondary p-2 rounded"><code>@can('Users')
    …
@endcan</code></pre>
                    @endverbatim
                    <p class="text-secondary mb-2">{{ __('Example checks (only if permission exists):') }}</p>
                    <div class="d-flex flex-wrap gap-2">
                        @can('Users')
                            <span class="badge text-bg-success">Users ✓</span>
                        @else
                            <span class="badge text-bg-light text-body border">Users —</span>
                        @endcan
                        @can('Main projects')
                            <span class="badge text-bg-success">Main projects ✓</span>
                        @else
                            <span class="badge text-bg-light text-body border">Main projects —</span>
                        @endcan
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('js')
    <script src="{{ asset('plugins/jquery-ui/jquery-ui.min.js') }}"></script>
    <script src="{{ asset('plugins/toastr/toastr.min.js') }}"></script>
    @include('manage-access.partials.scripts')
@endsection
