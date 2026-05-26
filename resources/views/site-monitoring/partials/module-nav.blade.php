<div class="card shadow-sm cabinet-sm-nav-card mb-3">
    <div class="card-header p-0">
        <ul class="nav nav-pills p-2 cabinet-sm-module-nav mb-0 flex-wrap">
            <li class="nav-item">
                <a href="{{ route('site.monitoring') }}"
                   class="nav-link{{ ($active ?? '') === 'projects' ? ' active' : '' }}">{{ __('Site monitoring tab') }}</a>
            </li>
            @if($admin ?? false)
                <li class="nav-item">
                    <a href="{{ route('site.monitoring.config') }}"
                       class="nav-link{{ ($active ?? '') === 'config' ? ' active' : '' }}">{{ __('Module administration') }}</a>
                </li>
            @endif
        </ul>
    </div>
</div>
