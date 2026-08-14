{{-- Цели Метрики по факту успеха (не по клику кнопки). Pending до ack — иначе flash сгорает без hit. --}}
@if(config('app.env') !== 'local')
    @php
        $ymCounter = 89500732;
        $ymRegistered = session()->pull('ym_registered') || (bool) session('ym_pending_registered');
        $ymVerified = session()->pull('ym_verified') || session()->pull('verified') || (bool) session('ym_pending_verified');
        if ($ymRegistered && session('ym_pending_registered')) {
            $regAttempts = (int) session('ym_registered_fire_attempts', 0) + 1;
            session(['ym_registered_fire_attempts' => $regAttempts]);
            if ($regAttempts >= 5) {
                session()->forget(['ym_pending_registered', 'ym_registered_fire_attempts']);
            }
        }
        if ($ymVerified && session('ym_pending_verified')) {
            $verAttempts = (int) session('ym_verified_fire_attempts', 0) + 1;
            session(['ym_verified_fire_attempts' => $verAttempts]);
            if ($verAttempts >= 5) {
                session()->forget(['ym_pending_verified', 'ym_verified_fire_attempts']);
            }
        }
        $ymAckUrl = route('ym.goal.ack');
    @endphp
    @if($ymRegistered || $ymVerified)
        <script type="text/javascript">
            (function () {
                var counter = {{ $ymCounter }};
                var ackUrl = @json($ymAckUrl);
                var csrf = document.querySelector('meta[name="csrf-token"]');
                var csrfToken = csrf ? csrf.getAttribute('content') : '';

                function ack(goal) {
                    try {
                        var fd = new FormData();
                        fd.append('_token', csrfToken);
                        fd.append('goal', goal);
                        if (navigator.sendBeacon) {
                            navigator.sendBeacon(ackUrl, fd);
                            return;
                        }
                    } catch (e) { /* ignore */ }
                    try {
                        var xhr = new XMLHttpRequest();
                        xhr.open('POST', ackUrl, true);
                        xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken);
                        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                        var body = new FormData();
                        body.append('_token', csrfToken);
                        body.append('goal', goal);
                        xhr.send(body);
                    } catch (e2) { /* ignore */ }
                }

                function reach(goal, ackName) {
                    var tries = 0;
                    var sent = false;
                    function fire() {
                        if (sent || typeof ym !== 'function') {
                            return sent;
                        }
                        sent = true;
                        try {
                            ym(counter, 'reachGoal', goal, {}, function () {
                                if (ackName) {
                                    ack(ackName);
                                }
                            });
                        } catch (e) {
                            ym(counter, 'reachGoal', goal);
                            if (ackName) {
                                ack(ackName);
                            }
                        }
                        return true;
                    }
                    if (fire()) {
                        return;
                    }
                    var timer = setInterval(function () {
                        if (fire() || ++tries > 40) {
                            clearInterval(timer);
                        }
                    }, 50);
                }
                @if($ymRegistered)
                reach('novaja_registracija_1231', 'registered');
                (window._tmr = window._tmr || []).push({ type: 'reachGoal', id: 3787377, goal: 'registracija' });
                @endif
                @if($ymVerified)
                reach('verifikacija_po_majlu_1628', 'verified');
                (window._tmr = window._tmr || []).push({ type: 'reachGoal', id: 3787377, goal: 'verifikacija' });
                if (window._tmr && typeof window._tmr.push === 'function') {
                    window._tmr.push({ type: 'reachGoal', id: 3340935, goal: 'Verifikacija170523' });
                }
                @endif
            })();
        </script>
    @endif
@endif
