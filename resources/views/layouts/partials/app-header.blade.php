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
            <li class="nav-item d-none d-md-block">
                <a class="nav-link @if(request()->routeIs('ideas.*')) active @endif"
                   href="{{ route('ideas.index') }}"
                   title="{{ __('Suggest an idea / Vote') }}">
                    <i class="bi bi-lightbulb me-1" aria-hidden="true"></i>
                    {{ __('Ideas') }}
                    @if(($ideasModerationCount ?? 0) > 0)
                        <span class="navbar-badge badge text-bg-warning ms-1" title="{{ __('Awaiting moderation') }}">
                            {{ $ideasModerationCount > 99 ? '99+' : $ideasModerationCount }}
                        </span>
                    @endif
                </a>
            </li>
            @if(!empty($seoChecklistNavVisible))
                @php
                    $scDueCount = (int) ($seoChecklistDueCount ?? 0);
                    $scOverdueCount = (int) ($seoChecklistOverdueCount ?? 0);
                    $scReviewCount = (int) ($seoChecklistReviewCount ?? 0);
                    $scUnreadNotes = (int) ($seoChecklistUnreadNotesCount ?? 0);
                    $scDueTip = $scOverdueCount > 0
                        ? __('SEO checklist header due tip overdue', [
                            'overdue' => $scOverdueCount,
                            'total' => $scDueCount,
                        ])
                        : __('SEO checklist header due tip soon', ['total' => $scDueCount]);
                    $scReviewTip = __('SEO checklist header review tip', ['total' => $scReviewCount]);
                    $scUnreadTip = __('SEO checklist header unread tip');
                @endphp
                <li class="nav-item d-none d-md-block">
                    <div class="nav-link cabinet-header-sc-nav @if(request()->routeIs('pages.seo-checklist*')) active @endif">
                        <a class="cabinet-header-sc-nav__label text-reset text-decoration-none @if(request()->routeIs('pages.seo-checklist*')) fw-semibold @endif"
                           href="{{ route('pages.seo-checklist') }}">
                            <i class="bi bi-clipboard-check me-1" aria-hidden="true"></i>
                            {{ $seoChecklistModuleTitle ?? __('SEO Checklist') }}
                        </a>
                        @if($scDueCount > 0)
                            <a href="{{ route('pages.seo-checklist.my-tasks') }}"
                               class="navbar-badge badge @if($scOverdueCount > 0) text-bg-danger @else text-bg-warning @endif text-decoration-none cabinet-header-tip"
                               data-sc-due-header-count
                               data-tip="{{ $scDueTip }}"
                               aria-label="{{ $scDueTip }}">
                                {{ $scDueCount > 99 ? '99+' : $scDueCount }}
                            </a>
                        @endif
                        <a href="{{ route('pages.seo-checklist.review') }}"
                           class="navbar-badge badge text-bg-primary text-decoration-none cabinet-header-tip cabinet-header-sc-review"
                           data-sc-review-header-count
                           data-tip="{{ $scReviewTip }}"
                           aria-label="{{ $scReviewTip }}"
                           @if($scReviewCount < 1) hidden @endif>
                            {{ $scReviewCount > 99 ? '99+' : $scReviewCount }}
                        </a>
                        <a href="{{ route('pages.seo-checklist.chronicle', ['view' => 'unread']) }}"
                           class="navbar-badge badge text-bg-warning text-decoration-none cabinet-header-tip"
                           data-sc-unread-header-count
                           data-tip="{{ $scUnreadTip }}"
                           aria-label="{{ $scUnreadTip }}"
                           @if($scUnreadNotes < 1) hidden @endif>
                            {{ $scUnreadNotes > 99 ? '99+' : $scUnreadNotes }}
                        </a>
                    </div>
                </li>
            @endif
            @if(!empty($seoChecklistActiveTimer))
                <li class="nav-item d-none d-md-block cabinet-sc-header-timer-item"
                    id="cabinet-sc-header-timer"
                    data-sc-header-timer
                    data-started-at="{{ $seoChecklistActiveTimer['started_at'] }}"
                    data-base-seconds="{{ (int) $seoChecklistActiveTimer['time_spent_seconds'] }}"
                    data-stop-url="{{ $seoChecklistActiveTimer['stop_url'] }}"
                    data-csrf="{{ csrf_token() }}">
                    <div class="cabinet-sc-header-timer" role="status">
                        <a href="{{ $seoChecklistActiveTimer['url'] }}"
                           class="cabinet-sc-header-timer__link"
                           title="{{ $seoChecklistActiveTimer['title'] }}">
                            <i class="bi bi-stopwatch" aria-hidden="true"></i>
                            <span class="cabinet-sc-header-timer__domain">{{ $seoChecklistActiveTimer['domain'] }}</span>
                            <span class="cabinet-sc-header-timer__sep" aria-hidden="true">·</span>
                            <span class="cabinet-sc-header-timer__title">{{ \Illuminate\Support\Str::limit($seoChecklistActiveTimer['title'], 24) }}</span>
                            <span class="cabinet-sc-header-timer__elapsed" data-sc-header-elapsed>
                                {{ \App\Services\SeoChecklist\SeoChecklistService::formatDuration((int) $seoChecklistActiveTimer['display_seconds']) }}
                            </span>
                        </a>
                        <button type="button"
                                class="cabinet-sc-header-timer__stop"
                                data-sc-header-timer-stop
                                title="{{ __('Stop timer') }}">
                            {{ __('Timer stop') }}
                        </button>
                    </div>
                </li>
            @endif
        </ul>

        <ul class="navbar-nav ms-auto align-items-center">
            @auth
                <li class="nav-item d-none d-lg-block">
                    <a class="nav-link" href="{{ route('balance.index') }}">
                        <i class="bi bi-wallet2 me-1 text-success" aria-hidden="true"></i>
                        <span class="text-secondary">{{ __('Total balance') }}:</span>
                        <strong class="ms-1">{{ number_format(Auth::user()->totalBalanceAmount(), 0, '', ' ') }}</strong>
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
                @if(!empty($headerModuleLimit))
                    @php
                        $headerLimitFmt = static function ($n) {
                            return number_format((int) $n, 0, '', ' ');
                        };
                        $headerPrimaryShort = $headerModuleLimit['code'] === 'TextAnalyzer'
                            ? (string) __('Text analyzer header short')
                            : (string) __('Left');
                        $headerHasSecondary = !empty($headerModuleSecondary);
                        $headerSecondaryMetric = 'used';
                        $headerSecondaryValue = 0;
                        $headerSecondaryShort = '';
                        $headerSecondaryAria = '';
                        if ($headerHasSecondary) {
                            $headerSecondaryMetric = $headerModuleSecondary['metric'] ?? 'used';
                            $headerSecondaryValue = $headerSecondaryMetric === 'left'
                                ? (int) ($headerModuleSecondary['left'] ?? 0)
                                : (int) ($headerModuleSecondary['used'] ?? 0);
                            $headerSecondaryShort = (string) ($headerModuleSecondary['short_label']
                                ?? $headerModuleSecondary['label']
                                ?? '');
                            $headerSecondaryAria = ($headerModuleSecondary['label'] ?? $headerSecondaryShort)
                                . ': ' . $headerLimitFmt($headerSecondaryValue)
                                . ' ' . __('from') . ' ' . $headerLimitFmt($headerModuleSecondary['limit']);
                        }
                        $headerPrimaryAria = $headerModuleLimit['name'];
                        if ($headerModuleLimit['unlimited']) {
                            $headerPrimaryAria .= ': ' . __('No restrictions');
                        } elseif ($headerModuleLimit['exhausted']) {
                            $headerPrimaryAria .= ': ' . __('Your limits are exhausted this month');
                        } else {
                            $headerPrimaryAria .= ': ' . __('Left') . ' '
                                . $headerLimitFmt($headerModuleLimit['left']) . ' '
                                . __('from') . ' ' . $headerLimitFmt($headerModuleLimit['limit']);
                        }
                    @endphp
                    <li class="nav-item d-none d-xl-block cabinet-header-module-limits @if($headerHasSecondary) cabinet-header-module-limits--pair @endif">
                        <span class="nav-link cabinet-header-module-limits__row">
                            <span class="cabinet-header-limit-chip @if(!empty($headerModuleLimit['exhausted'])) is-exhausted @endif"
                                  id="cabinet-header-module-limit"
                                  data-limit-code="{{ $headerModuleLimit['code'] }}"
                                  aria-label="{{ $headerPrimaryAria }}">
                                <i class="bi bi-pie-chart" aria-hidden="true"></i>
                                @if($headerModuleLimit['unlimited'])
                                    <span class="cabinet-header-limit-chip__label">{{ $headerModuleLimit['name'] }}</span>
                                    <strong class="ms-1">{{ __('No restrictions') }}</strong>
                                @elseif($headerModuleLimit['exhausted'])
                                    <span class="cabinet-header-limit-chip__label">{{ $headerPrimaryShort }}</span>
                                    <strong class="ms-1">0</strong>
                                    <span class="cabinet-header-limit-chip__cap">/ {{ $headerLimitFmt($headerModuleLimit['limit']) }}</span>
                                @else
                                    <span class="cabinet-header-limit-chip__label">{{ $headerPrimaryShort }}</span>
                                    <strong class="ms-1">{{ $headerLimitFmt($headerModuleLimit['left']) }}</strong>
                                    <span class="cabinet-header-limit-chip__cap">/ {{ $headerLimitFmt($headerModuleLimit['limit']) }}</span>
                                @endif
                            </span>
                            @if($headerHasSecondary)
                                <span class="cabinet-header-module-limits__sep" aria-hidden="true"></span>
                                <span class="cabinet-header-limit-chip @if(!empty($headerModuleSecondary['exhausted'])) is-exhausted @endif"
                                      id="cabinet-header-module-secondary"
                                      data-limit-code="{{ $headerModuleSecondary['code'] }}"
                                      aria-label="{{ $headerSecondaryAria }}">
                                    <i class="bi {{ ($headerSecondaryMetric === 'left') ? 'bi-shield-check' : 'bi-folder2-open' }}" aria-hidden="true"></i>
                                    <span class="cabinet-header-limit-chip__label">{{ $headerSecondaryShort }}</span>
                                    <strong class="ms-1" id="cabinet-header-module-secondary-used">{{ $headerLimitFmt($headerSecondaryValue) }}</strong>
                                    <span class="cabinet-header-limit-chip__cap">/ {{ $headerLimitFmt($headerModuleSecondary['limit']) }}</span>
                                </span>
                            @endif
                        </span>
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
                                <div class="cabinet-header-limits-menu__table-wrap">
                                    <table class="table table-sm table-hover mb-0">
                                        <thead>
                                        <tr>
                                            <th scope="col">{{ __('Module') }}</th>
                                            <th scope="col" class="text-end">{{ __('Limits') }}</th>
                                            <th scope="col" class="text-end">{{ __('Left') }}</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        @foreach($limitsStatistics as $key => $tariff)
                                            @if($key != 'price')
                                                <tr class="{{ $key }}">
                                                    <td>{{ $tariff['name'] }}</td>
                                                    @php
                                                        $limitVal = $tariff['value'];
                                                        $usedVal = $tariff['used'];
                                                        $isUnlimited = ((int) $limitVal === 1000000);
                                                    @endphp
                                                    <td class="text-end">
                                                        @if($isUnlimited)
                                                            {{ __('No restrictions') }}
                                                        @else
                                                            {{ number_format((int) $limitVal, 0, '', ' ') }}
                                                        @endif
                                                    </td>
                                                    <td class="text-end">
                                                        @if($isUnlimited)
                                                            —
                                                        @elseif(is_int($usedVal))
                                                            {{ number_format(max(0, (int) $limitVal - (int) $usedVal), 0, '', ' ') }}
                                                        @else
                                                            {{ $usedVal }}
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
                        <span class="nav-link text-warning-emphasis">
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
