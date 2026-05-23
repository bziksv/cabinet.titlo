<nav class="app-header navbar navbar-expand bg-body cabinet-header" id="header-nav-bar">
    <div class="container-fluid">
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button" aria-label="{{ __('Menu') }}">
                    <i class="bi bi-list"></i>
                </a>
            </li>
            <li class="nav-item d-none d-md-block">
                <div class="nav-link d-flex flex-wrap align-items-center gap-1 py-2 @if(request()->routeIs('news')) active @endif">
                    <a class="text-reset text-decoration-none d-inline-flex align-items-center @if(request()->routeIs('news')) fw-semibold @endif"
                       href="{{ route('news') }}">
                        <i class="bi bi-newspaper me-1" aria-hidden="true"></i>
                        {{ __('News and updates') }}
                        @if((int) $count > 0)
                            <span class="badge text-bg-warning ms-1">{{ $count > 99 ? '99+' : $count }}</span>
                        @endif
                    </a>
                    @if(($newsCommentCount ?? 0) > 0 && !empty($newsCommentUrl))
                        <a href="{{ $newsCommentUrl }}"
                           class="badge text-bg-info text-decoration-none"
                           title="{{ $newsCommentTitle }}">
                            <i class="bi bi-chat-dots-fill me-1" aria-hidden="true"></i>{{ $newsCommentCount > 99 ? '99+' : $newsCommentCount }}
                        </a>
                    @endif
                </div>
            </li>
            <li class="nav-item d-none d-md-block">
                <a class="nav-link @if(request()->routeIs('support.*')) active @endif"
                   href="{{ route('support.index', array_filter(['status' => $supportBadgeFilter ?? null])) }}">
                    <i class="bi bi-headset me-1" aria-hidden="true"></i>
                    {{ __('Support') }}
                    @if(($supportBadgeCount ?? 0) > 0)
                        <span class="navbar-badge badge text-bg-danger ms-1" title="{{ $supportBadgeTitle ?? '' }}">
                            {{ $supportBadgeCount > 99 ? '99+' : $supportBadgeCount }}
                        </span>
                    @endif
                </a>
            </li>
        </ul>

        <ul class="navbar-nav ms-auto align-items-center">
            @auth
                <li class="nav-item d-none d-lg-block">
                    <a class="nav-link" href="{{ route('balance.index') }}">
                        <i class="bi bi-wallet2 me-1 text-success" aria-hidden="true"></i>
                        <span class="text-secondary">{{ __('Your balance') }}:</span>
                        <strong class="ms-1">{{ number_format((float) Auth::user()->balance, 0, '.', ' ') }}</strong>
                    </a>
                </li>
                @if(!empty($name))
                    <li class="nav-item d-none d-lg-block">
                        <a class="nav-link" href="{{ route('tariff.index') }}">
                            <i class="bi bi-layers me-1 text-primary" aria-hidden="true"></i>
                            <span class="text-secondary">{{ __('Your tariff') }}:</span>
                            <strong class="ms-1">{{ $name }}</strong>
                        </a>
                    </li>
                @endif
                @if(!empty($limitsStatistics))
                    <li class="nav-item dropdown">
                        <a class="nav-link" data-bs-toggle="dropdown" href="#" role="button" aria-expanded="false">
                            <i class="bi bi-pie-chart me-1" aria-hidden="true"></i>
                            {{ __('Your limits') }}
                        </a>
                        <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end cabinet-header-limits-menu">
                            <span class="dropdown-item dropdown-header">{{ __('Your limits') }}</span>
                            <div class="dropdown-divider"></div>
                            <div class="px-2 pb-2">
                                <div class="table-responsive cabinet-header-limits-menu__table-wrap">
                                    <table class="table table-sm table-hover mb-0">
                                        <thead>
                                        <tr>
                                            <th>{{ __('Module') }}</th>
                                            <th class="text-end">{{ __('Limits') }}</th>
                                            <th class="text-end">{{ __('Left') }}</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        @foreach($limitsStatistics as $key => $tariff)
                                            @if($key != 'price')
                                                <tr class="{{ $key }}">
                                                    <td>{{ $tariff['name'] }}</td>
                                                    <td class="text-end">
                                                        @if($tariff['value'] === 1000000)
                                                            {{ __('No restrictions') }}
                                                        @else
                                                            {{ $tariff['value'] }}
                                                        @endif
                                                    </td>
                                                    <td class="text-end">
                                                        @if(gettype($tariff['used']) == 'integer')
                                                            {{ $tariff['value'] - $tariff['used'] }}
                                                        @else
                                                            {{ $tariff['used'] }}
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endif
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </li>
                    <li class="nav-item cabinet-header-limits-hint is-empty d-none d-xl-block" id="cabinet-header-limits-hint">
                        <span class="nav-link py-1 text-warning-emphasis small">
                            <i class="bi bi-info-circle me-1" aria-hidden="true"></i>
                            <span id="userModuleUsed"></span>
                            <span id="userModuleLimit"></span>
                        </span>
                    </li>
                @endif
            @endauth
            <li class="nav-item">
                <a class="nav-link" href="#" data-lte-toggle="fullscreen" role="button" title="{{ __('Fullscreen') }}">
                    <i data-lte-icon="maximize" class="bi bi-arrows-fullscreen"></i>
                    <i data-lte-icon="minimize" class="bi bi-fullscreen-exit d-none"></i>
                </a>
            </li>
            <li class="nav-item">
                {!! Form::open(['class' => 'd-inline', 'method' => 'POST', 'route' => ['logout']]) !!}
                <button type="submit" class="nav-link btn btn-link border-0" title="{{ __('Logout') }}">
                    <i class="bi bi-box-arrow-right text-danger" aria-hidden="true"></i>
                </button>
                {!! Form::close() !!}
            </li>
        </ul>
    </div>
</nav>
