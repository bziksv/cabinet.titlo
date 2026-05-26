@php
    $active = $active ?? 'analyzer';
    $clusterId = $clusterId ?? null;
@endphp
<div class="card shadow-sm cabinet-cluster-nav-card mb-3">
    <div class="card-header p-0 border-bottom-0">
        <ul class="nav nav-pills p-2 cabinet-cluster-module-nav mb-0 flex-wrap">
            <li class="nav-item">
                <a href="{{ route('cluster') }}"
                   class="nav-link{{ $active === 'analyzer' ? ' active' : '' }}">{{ __('Analyzer') }}</a>
            </li>
            <li class="nav-item">
                <a href="{{ route('cluster.projects') }}"
                   class="nav-link{{ $active === 'projects' ? ' active' : '' }}">{{ __('Projects') }}</a>
            </li>
            @if($clusterId)
                <li class="nav-item">
                    <a href="{{ route('show.cluster.result', $clusterId) }}"
                       class="nav-link{{ $active === 'result' ? ' active' : '' }}">{{ __('Project') }}</a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('edit.clusters', $clusterId) }}"
                       class="nav-link{{ $active === 'edit' ? ' active' : '' }}">{{ __('Hands editor') }}</a>
                </li>
            @endif
            @if($admin ?? false)
                <li class="nav-item">
                    <a href="{{ route('cluster.configuration') }}"
                       class="nav-link{{ $active === 'config' ? ' active' : '' }}">{{ __('Module administration') }}</a>
                </li>
            @endif
            @if($active === 'edit' && isset($cluster['default_result']))
                <li class="nav-item ms-auto">
                    <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#cl-edit-reset-modal">
                        {{ __('Rolling back all changes') }}
                    </button>
                </li>
            @endif
        </ul>
    </div>
</div>
