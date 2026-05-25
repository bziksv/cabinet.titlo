@php
    $active = $active ?? 'edit-v2';
    $clusterId = $clusterId ?? null;
@endphp
<div class="card shadow-sm cabinet-cluster-v2-nav-card mb-3">
    <div class="card-header p-0 border-bottom-0">
        <ul class="nav nav-pills p-2 cabinet-cluster-v2-module-nav mb-0 flex-wrap">
            <li class="nav-item">
                <a href="{{ route('cluster.v2') }}" class="nav-link">{{ __('Analyzer') }} <span class="badge text-bg-primary ms-1">v2</span></a>
            </li>
            <li class="nav-item">
                <a href="{{ route('cluster.projects') }}" class="nav-link">{{ __('My projects') }}</a>
            </li>
            @if($clusterId)
                <li class="nav-item">
                    <a href="{{ route('show.cluster.result', $clusterId) }}" class="nav-link{{ $active === 'result' ? ' active' : '' }}">{{ __('My project') }}</a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('edit.clusters.v2', $clusterId) }}" class="nav-link{{ $active === 'edit-v2' ? ' active' : '' }}">
                        {{ __('Hands editor v2') }}
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('edit.clusters', $clusterId) }}" class="nav-link text-secondary small">{{ __('Hands editor v1') }}</a>
                </li>
            @endif
            @if($admin ?? false)
                <li class="nav-item">
                    <a href="{{ route('cluster.configuration') }}" class="nav-link">{{ __('Module administration') }}</a>
                </li>
            @endif
            @if(($active === 'edit-v2') && isset($cluster['default_result']))
                <li class="nav-item ms-auto">
                    <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#cl-edit-reset-modal">
                        {{ __('Rolling back all changes') }}
                    </button>
                </li>
            @endif
        </ul>
    </div>
</div>
