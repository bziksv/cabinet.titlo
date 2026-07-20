@php use App\Session; @endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="shortcut icon" href="{{ asset('img/favicon.svg') }}"/>
    @include('layouts.partials.document-title')
    @include('layouts.partials.lte4-head')
    <link rel="stylesheet" href="{{ asset('css/cabinet-telegram-prompt.css') }}">
    <link rel="stylesheet" href="{{ asset('css/cabinet-monitoring-schedule-prompt.css') }}">
    <link rel="stylesheet" href="{{ asset('css/cabinet-app-footer.css') }}?v={{ @filemtime(public_path('css/cabinet-app-footer.css')) ?: time() }}">
    <link rel="stylesheet" href="{{ asset('css/cabinet-demo-banner.css') }}?v={{ @filemtime(public_path('css/cabinet-demo-banner.css')) ?: time() }}">
    @yield('css')
    <script src="{{ asset('plugins/jquery/jquery.min.js') }}"></script>
</head>
<body class="layout-fixed sidebar-expand-lg sidebar-mini bg-body-tertiary @if(\App\Support\DemoCabinet::isCurrentUser()) cabinet-demo-readonly @endif"
      @if(\App\Support\DemoCabinet::isCurrentUser())
          data-demo-cabinet="1"
          data-demo-readonly-allow='@json(\App\Support\DemoCabinet::readonlyPostPathPrefixes())'
      @endif>
<script>
    (function () {
        try {
            if (localStorage.getItem('lte.sidebar.state') === 'sidebar-collapse') {
                document.body.classList.add('sidebar-collapse');
            }
        } catch (e) { /* private mode */ }
    })();
</script>
<div class="app-wrapper">
    @include('layouts.partials.app-header')
    @include('layouts.partials.app-sidebar')
    <main class="app-main">
        @include('layouts.partials.demo-cabinet-banner')
        <div class="app-content pt-3 pb-3">
            <div class="container-fluid" id="app">
                @if(session('demo_cabinet_error'))
                    <div class="alert alert-danger">{{ session('demo_cabinet_error') }}</div>
                @endif
                @if(session('demo_cabinet_welcome'))
                    <div class="alert alert-info">
                        Вы в демо-кабинете. Сейчас открыта <strong>история анализа релевантности</strong> —
                        откройте проект <em>demo-shop.ru</em> и снимок проверки, чтобы увидеть полный отчёт.
                        Также есть готовые результаты в
                        <a href="{{ url('/search-suggestions') }}">подсказках</a>,
                        <a href="{{ url('/domain-records') }}">записях домена</a>,
                        <a href="{{ url('/site-types') }}">типах сайтов</a>,
                        <a href="{{ url('/phrase-commerce') }}">гео и коммерции</a>.
                        Форма «Анализатор» пустая намеренно — в демо запуски отключены.
                    </div>
                @endif
                @yield('content')
            </div>
        </div>
    </main>
    <footer class="app-footer" id="main-footer">
        <div class="cabinet-app-footer-inner d-flex flex-column flex-lg-row align-items-start align-items-lg-end justify-content-between gap-3 w-100">
            <div class="cabinet-app-footer-copy small mb-0">
                <strong>&copy; 2021&ndash;{{ date('Y') }} <a href="https://titlo.ru/" class="text-decoration-none">Титло</a>. Все права защищены.</strong>
            </div>
            @include('layouts.partials.app-footer-legal')
        </div>
    </footer>
</div>

@include('layouts.partials.telegram-connect-prompt')

@php
    $isMonitoringPositionsModule = request()->is(
        'monitoring',
        'monitoring/*',
        'monitoring-v2',
        'monitoring-v2/*'
    );
    $showMonitoringSchedulePaidPrompt = $isMonitoringPositionsModule
        && auth()->check()
        && auth()->user()->shouldShowMonitoringSchedulePaidPrompt();
@endphp
@if($showMonitoringSchedulePaidPrompt)
    @include('monitoring.partials.schedule-paid-prompt', ['showMonitoringSchedulePaidPrompt' => true])
@endif

@include('layouts.partials.lte4-scripts')
<script src="{{ asset('js/cabinet-modal-queue.js') }}?v={{ @filemtime(public_path('js/cabinet-modal-queue.js')) ?: time() }}"></script>
<script src="{{ asset('js/cabinet-jquery-modal-bridge.js') }}"></script>
<script src="{{ asset('js/cabinet-bs5-shim.js') }}"></script>
<script src="{{ asset('js/cabinet-select2-defaults.js') }}?v={{ @filemtime(public_path('js/cabinet-select2-defaults.js')) ?: time() }}"></script>
<script src="{{ asset('js/cabinet-lte3-widgets.js') }}"></script>

@if(request()->route()->parameter('statistic_project_id') !== null)
    <script>
        const tracking_project_id = "{{ request()->route()->parameter('statistic_project_id') }}";
        $(document).on('click', '.click_tracking', function () {
            $.ajax({
                type: 'post',
                url: "{{ route('click.tracking') }}",
                data: {
                    _token: $('meta[name="csrf-token"]').attr('content'),
                    button_text: $(this).attr('data-click'),
                    url: location.href,
                    project_id: tracking_project_id
                }
            });
        });
    </script>
@endif

<script>
    (function ($) {
        function cabinetAdminGearMenu() {
            return $('#cabinet-admin-gear-menu');
        }

        function closeCabinetAdminGear() {
            var $menu = cabinetAdminGearMenu();
            $menu.removeClass('is-open').css({top: '', left: '', display: '', maxHeight: ''});
            var $home = $menu.data('gear-home');
            if ($home && $home.length) {
                $menu.appendTo($home);
            }
            $('.cabinet-admin-gear.is-open').removeClass('is-open')
                .find('.cabinet-admin-gear__toggle').attr('aria-expanded', 'false');
        }

        function positionCabinetAdminGearMenu($gear) {
            var $btn = $gear.find('.cabinet-admin-gear__toggle');
            var $menu = cabinetAdminGearMenu();
            if (!$menu.length) {
                return;
            }
            $menu.data('gear-home', $gear);
            if (!$menu.parent().is('body')) {
                $menu.appendTo(document.body);
            }
            $menu.addClass('is-open');
            var rect = $btn[0].getBoundingClientRect();
            var menuWidth = $menu.outerWidth() || 272;
            var maxMenuHeight = Math.min(window.innerHeight * 0.75, 576);
            var menuHeight = Math.min($menu.outerHeight() || 240, maxMenuHeight);
            var top = rect.top;
            var left = rect.right + 6;
            if (left + menuWidth > window.innerWidth - 8) {
                left = Math.max(8, rect.left - menuWidth - 6);
            }
            if (top + menuHeight > window.innerHeight - 8) {
                top = Math.max(8, window.innerHeight - menuHeight - 8);
            }
            $menu.css({top: top + 'px', left: left + 'px', maxHeight: maxMenuHeight + 'px'});
        }
        $(document).on('click', '.cabinet-admin-gear__toggle', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var $gear = $(this).closest('.cabinet-admin-gear');
            var wasOpen = $gear.hasClass('is-open');
            closeCabinetAdminGear();
            if (!wasOpen) {
                $gear.addClass('is-open');
                $(this).attr('aria-expanded', 'true');
                positionCabinetAdminGearMenu($gear);
            }
        });
        $(document).on('click', function (e) {
            if (!$(e.target).closest('.cabinet-admin-gear').length) {
                closeCabinetAdminGear();
            }
        });
        $(window).on('resize scroll', function () {
            var $open = $('.cabinet-admin-gear.is-open');
            if ($open.length) {
                positionCabinetAdminGearMenu($open);
            }
        });
    })(jQuery);
</script>

<script>
    $(document).ready(function () {
        var ytApiLoading = false;
        var ytApiQueue = [];

        function playModuleVideo($el) {
            if ($el.find('video.module-video-selfhosted, iframe').length) {
                return;
            }

            var localSrc = $el.attr('data-local');
            if (localSrc) {
                $el.html(
                    '<video class="w-100 module-video-selfhosted" controls autoplay playsinline src="'
                    + localSrc.replace(/"/g, '&quot;') + '"></video>'
                );
                return;
            }

            var videoId = $el.attr('data-id');
            var elementId = $el.attr('id') || 'video-course';
            if (!videoId) {
                return;
            }

            function startYoutube() {
                if (typeof YT === 'undefined' || typeof YT.Player === 'undefined') {
                    return;
                }
                new YT.Player(elementId, {
                    videoId: videoId,
                    playerVars: {autoplay: 1},
                    events: {
                        onReady: function (event) {
                            event.target.playVideo();
                        }
                    }
                });
            }

            if (typeof YT !== 'undefined' && typeof YT.Player !== 'undefined') {
                startYoutube();
                return;
            }

            ytApiQueue.push(startYoutube);
            if (ytApiLoading) {
                return;
            }
            ytApiLoading = true;
            window.onYouTubeIframeAPIReady = function () {
                ytApiLoading = false;
                var q = ytApiQueue.slice();
                ytApiQueue = [];
                q.forEach(function (fn) { fn(); });
            };
            var tag = document.createElement('script');
            tag.src = 'https://www.youtube.com/iframe_api';
            document.head.appendChild(tag);
        }

        $(document).on('click', '#video-course, .video-course', function (e) {
            // Клик по плееру — нативная пауза/seek; не пересоздавать <video> (сброс в 0:00).
            if ($(e.target).closest('video.module-video-selfhosted, iframe').length) {
                return;
            }
            playModuleVideo($(this));
        });
    });
</script>

@unless(request()->path() == 'utm-marks' || request()->path() == 'all-projects')
    @if(config('app.env') === 'local')
        <script>window.__DISABLE_LARAVEL_ECHO__ = true;</script>
        <script src="{{ asset('js/echo-disable-pre.js') }}"></script>
    @else
        <script>
            window.__LARAVEL_ECHO_KEY__ = @json(config('broadcasting.connections.pusher.key'));
            window.__LARAVEL_ECHO_PORT__ = @json(env('LARAVEL_WEBSOCKETS_PORT', 6001));
            window.__LARAVEL_ECHO_TLS__ = @json(filter_var(env('PUSHER_APP_TLS', true), FILTER_VALIDATE_BOOLEAN));
        </script>
    @endif
    @if(config('app.env') === 'local')
        <script>window.cabinetPleaseWaitMessage = @json(__('Cabinet please wait message'));</script>
        <script src="{{ asset('js/app.js') }}?v=no-ws-{{ @filemtime(public_path('js/app.js')) }}"></script>
        @include('partials.cabinet-please-wait-override')
    @else
        <script>window.cabinetPleaseWaitMessage = @json(__('Cabinet please wait message'));</script>
        <script src="{{ mix('js/app.js') }}"></script>
        @include('partials.cabinet-please-wait-override')
    @endif
@endunless

@include('partials.cabinet-layout-scripts')

@if(optional(Auth::user())->statistic && ! cabinet_skip_heavy_web() && ! \App\Support\DemoCabinet::isCurrentUser())
    <script>
        let secondsTrackingRedbox = 0;
        let timeTrackingRedboxInterval = startTracking();
        $(window).bind('focus', function () {
            clearInterval(timeTrackingRedboxInterval);
            timeTrackingRedboxInterval = startTracking();
        });
        $(window).bind('blur', function () {
            clearInterval(timeTrackingRedboxInterval);
            updateStatistics(secondsTrackingRedbox);
        });
        window.onbeforeunload = function () {
            clearInterval(timeTrackingRedboxInterval);
            updateStatistics(secondsTrackingRedbox);
        };
        function startTracking() {
            return setInterval(() => {
                secondsTrackingRedbox += 1;
                if (secondsTrackingRedbox === 300) {
                    updateStatistics(secondsTrackingRedbox);
                }
            }, 1000);
        }
        function updateStatistics() {
            $.ajax({
                url: "{{ route('update.statistics') }}",
                method: 'POST',
                data: {
                    seconds: secondsTrackingRedbox,
                    controllerAction: "{{ $controllerAction }}",
                    _token: $('meta[name="csrf-token"]').attr('content'),
                },
            });
            secondsTrackingRedbox = 0;
        }
    </script>
@endif

@if(config('app.env') !== 'local')
<script src="{{ asset('js/demo.js') }}"></script>
@endif
@if(\App\Support\DemoCabinet::isCurrentUser())
<script src="{{ asset('js/cabinet-demo-readonly.js') }}?v={{ @filemtime(public_path('js/cabinet-demo-readonly.js')) ?: time() }}"></script>
@endif
@yield('js')

<span class="click_tracking another_action"></span>

<script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
<script nomodule src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.js"></script>
<script>
    $(document).ready(function () {
        $('.cabinet-sidebar-search__input').on('input', function () {
            var search = $(this).val().toLowerCase();
            $('.cabinet-sidebar-menu .search-link').each(function () {
                var text = $(this).find('.module-name').text().toLowerCase();
                $(this).closest('.nav-item').toggle(text.indexOf(search) !== -1);
            });
            if (search !== '') {
                $('.cabinet-sidebar-menu li.folder').each(function () {
                    $(this).addClass('menu-open');
                    $(this).children('.nav-treeview').show();
                });
            }
        });
    });
</script>
@include('flash::message')

@include('layouts.partials.yandex-metrika')
</body>
</html>
