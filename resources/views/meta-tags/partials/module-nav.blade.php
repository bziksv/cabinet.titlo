@php
    $active = $active ?? 'module';
@endphp
@if(auth()->check() && auth()->user()->hasAnyRole(['Super Admin', 'admin']))
    <div class="card shadow-sm cabinet-mt-nav-card mb-3">
        <div class="card-header p-0 border-bottom-0">
            <ul class="nav nav-pills p-2 cabinet-mt-module-nav mb-0 flex-wrap">
                <li class="nav-item">
                    <a href="{{ route('meta-tags.index') }}"
                       class="nav-link{{ $active === 'module' ? ' active' : '' }}">{{ __('Meta tags') }}</a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('meta-tags.settings') }}"
                       class="nav-link{{ $active === 'settings' ? ' active' : '' }}">{{ __('Module administration') }}</a>
                </li>
            </ul>
        </div>
    </div>
@endif
