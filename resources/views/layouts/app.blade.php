@php use App\Session; @endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @if(config('app.env') !== 'local')
    <!-- Google Tag Manager -->
    <script>(function (w, d, s, l, i) {
            w[l] = w[l] || [];
            w[l].push({'gtm.start': new Date().getTime(), event: 'gtm.js'});
            var f = d.getElementsByTagName(s)[0],
                j = d.createElement(s), dl = l != 'dataLayer' ? '&l=' + l : '';
            j.async = true;
            j.src = 'https://www.googletagmanager.com/gtm.js?id=' + i + dl;
            f.parentNode.insertBefore(j, f);
        })(window, document, 'script', 'dataLayer', 'GTM-PS4GF7H');</script>
    <!-- End Google Tag Manager -->
    @endif
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="shortcut icon" href="{{ asset('img/favicon.svg') }}"/>
    @include('layouts.partials.document-title')
    @include('layouts.partials.lte4-head')
    <link rel="stylesheet" href="{{ asset('css/cabinet-telegram-prompt.css') }}">
    @yield('css')
    <script src="{{ asset('plugins/jquery/jquery.min.js') }}"></script>
</head>
<body class="layout-fixed sidebar-expand-lg sidebar-mini bg-body-tertiary">
<script>
    (function () {
        try {
            if (localStorage.getItem('lte.sidebar.state') === 'sidebar-collapse') {
                document.body.classList.add('sidebar-collapse');
            }
        } catch (e) { /* private mode */ }
    })();
</script>
@if(config('app.env') !== 'local')
<noscript>
    <iframe src="https://www.googletagmanager.com/ns.html?id=GTM-PS4GF7H"
            height="0" width="0" style="display:none;visibility:hidden"></iframe>
</noscript>
@endif
<div class="app-wrapper">
    @include('layouts.partials.app-header')
    @include('layouts.partials.app-sidebar')
    <main class="app-main">
        <div class="app-content pt-3 pb-3">
            <div class="container-fluid" id="app">
                @yield('content')
            </div>
        </div>
    </main>
    <footer class="app-footer" id="main-footer">
        <div class="float-end d-none d-sm-inline"></div>
        <strong>&copy; 2021&ndash;{{ date('Y') }} <a href="https://datagon.ru/" class="text-decoration-none">Датагон</a>. Все права защищены.</strong>
    </footer>
</div>

@include('layouts.partials.telegram-connect-prompt')

@include('layouts.partials.lte4-scripts')
<script src="{{ asset('js/cabinet-jquery-modal-bridge.js') }}"></script>
<script src="{{ asset('js/cabinet-bs5-shim.js') }}"></script>
<script src="{{ asset('js/cabinet-select2-defaults.js') }}"></script>
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
            $menu.removeClass('is-open').css({top: '', left: '', display: ''});
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
            var menuHeight = $menu.outerHeight() || 240;
            var top = rect.top;
            var left = rect.right + 6;
            if (left + menuWidth > window.innerWidth - 8) {
                left = Math.max(8, rect.left - menuWidth - 6);
            }
            if (top + menuHeight > window.innerHeight - 8) {
                top = Math.max(8, window.innerHeight - menuHeight - 8);
            }
            $menu.css({top: top + 'px', left: left + 'px'});
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

@if(optional(Auth::user())->statistic && ! cabinet_skip_heavy_web())
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

@if(!config('app.debug'))
<script type="text/javascript">
    (function (m, e, t, r, i, k, a) {
        m[i] = m[i] || function () { (m[i].a = m[i].a || []).push(arguments); };
        m[i].l = 1 * new Date();
        k = e.createElement(t), a = e.getElementsByTagName(t)[0], k.async = 1, k.src = r;
        a.parentNode.insertBefore(k, a);
    })(window, document, 'script', 'https://mc.yandex.ru/metrika/tag.js', 'ym');
    ym(89500732, 'init', {clickmap: true, trackLinks: true, accurateTrackBounce: true, webvisor: true});
</script>
<noscript><div><img src="https://mc.yandex.ru/watch/89500732" style="position:absolute;left:-9999px;" alt=""/></div></noscript>
@endif
</body>
</html>
