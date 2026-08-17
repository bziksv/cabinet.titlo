<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $snapshot['cover']['title'] ?? $project->domain }}</title>
    <link rel="stylesheet" href="{{ asset('css/cabinet-seo-reports.css') }}?v={{ @filemtime(public_path('css/cabinet-seo-reports.css')) ?: time() }}">
</head>
<body class="cabinet-sr-public {{ !empty($presentation) ? 'cabinet-sr-public--present' : '' }} {{ !empty($lite) ? 'cabinet-sr-public--lite' : '' }} {{ !empty($darkTheme) ? 'cabinet-sr-public--dark' : '' }}">
    <div class="cabinet-sr-public__wrap">
        @if(session('success'))
            <div class="cabinet-sr-public__flash">{{ session('success') }}</div>
        @endif
        @include('pages.partials.seo-reports-report-body', [
            'project' => $project,
            'report' => $report,
            'snapshot' => $snapshot,
            'sections' => collect($sections)->map(function ($s) {
                $s['enabled'] = true;
                $s['client_visible'] = true;
                return $s;
            })->all(),
            'isPublicView' => true,
        ])
        @if(empty($presentation) && $report->status === 'ready')
            <form class="cabinet-sr-public__approve" method="post"
                  action="{{ route('seo-reports.public.approve', ['token' => $report->public_token]) }}">
                @csrf
                <button type="submit" class="btn btn-primary btn-sm">{{ __('Approve report') }}</button>
                <span class="small text-secondary">{{ __('Confirm you reviewed this SEO report') }}</span>
            </form>
        @elseif($report->status === 'approved_by_client')
            <p class="cabinet-sr-public__approved">{{ __('Report approved by client') }}
                @if($report->approved_at)
                    · {{ $report->approved_at->format('d.m.Y H:i') }}
                @endif
            </p>
        @endif
        <p class="cabinet-sr-public__footer">
            {{ __('Powered by Titlo') }}
            @if(empty($presentation) && !empty($report->public_token))
                · <a href="{{ route('seo-reports.public.present', ['token' => $report->public_token]) }}">{{ __('Presentation mode') }}</a>
                @if(empty($lite))
                    · <a href="{{ route('seo-reports.public', ['token' => $report->public_token]) }}?lite=1">{{ __('Lite client dashboard') }}</a>
                @else
                    · <a href="{{ route('seo-reports.public', ['token' => $report->public_token]) }}">{{ __('Full report') }}</a>
                @endif
            @endif
        </p>
    </div>
    <script src="{{ asset('plugins/chart.js/3.9.1/chart.js') }}"></script>
    <script src="{{ asset('js/cabinet-seo-reports-charts.js') }}?v={{ @filemtime(public_path('js/cabinet-seo-reports-charts.js')) ?: time() }}"></script>
    <script src="{{ asset('js/cabinet-seo-reports-toc.js') }}?v={{ @filemtime(public_path('js/cabinet-seo-reports-toc.js')) ?: time() }}"></script>
    <script>
        (function () {
            var reactUrl = @json(route('seo-reports.public.react', ['token' => $report->public_token]));
            var labels = {
                question: @json(__('Ask a question')),
                clarify: @json(__('Need clarification')),
                sent: @json(__('SEO report react sent')),
                fail: @json(__('SEO report react fail')),
                sending: @json(__('Sending…'))
            };

            function setStatus(wrap, text, ok) {
                var status = wrap.querySelector('[data-sr-react-status]');
                if (!status) return;
                status.hidden = !text;
                status.textContent = text || '';
                status.classList.toggle('is-ok', !!ok);
                status.classList.toggle('is-fail', !ok && !!text);
            }

            function sendReaction(wrap, type, text, btn) {
                var section = wrap.getAttribute('data-sr-react-section') || '';
                var body = new FormData();
                body.append('section', section);
                body.append('type', type);
                body.append('text', text || '');
                body.append('_token', @json(csrf_token()));
                setStatus(wrap, labels.sending, true);
                if (btn) btn.disabled = true;
                fetch(reactUrl, { method: 'POST', body: body, credentials: 'same-origin' })
                    .then(function (r) { return r.json().then(function (j) { return { ok: r.ok && j && j.ok, j: j }; }); })
                    .then(function (res) {
                        if (btn) btn.disabled = false;
                        if (res.ok) {
                            wrap.querySelectorAll('[data-sr-react]').forEach(function (b) {
                                b.classList.toggle('is-active', b === btn || (type === 'like' && b.getAttribute('data-sr-react') === 'like'));
                            });
                            if (btn) btn.classList.add('is-active');
                            var form = wrap.querySelector('[data-sr-react-form]');
                            if (form) form.hidden = true;
                            setStatus(wrap, labels.sent, true);
                        } else {
                            setStatus(wrap, labels.fail, false);
                        }
                    })
                    .catch(function () {
                        if (btn) btn.disabled = false;
                        setStatus(wrap, labels.fail, false);
                    });
            }

            document.querySelectorAll('[data-sr-react-section]').forEach(function (wrap) {
                var form = wrap.querySelector('[data-sr-react-form]');
                var formLabel = wrap.querySelector('[data-sr-react-form-label]');
                var textarea = wrap.querySelector('[data-sr-react-text]');
                var pendingType = null;
                var pendingBtn = null;

                wrap.querySelectorAll('[data-sr-react]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        var type = btn.getAttribute('data-sr-react') || '';
                        if (type === 'like') {
                            if (form) form.hidden = true;
                            sendReaction(wrap, 'like', '', btn);
                            return;
                        }
                        pendingType = type;
                        pendingBtn = btn;
                        if (formLabel) formLabel.textContent = labels[type] || '';
                        if (textarea) {
                            textarea.value = '';
                            textarea.focus();
                        }
                        if (form) form.hidden = false;
                        setStatus(wrap, '', true);
                    });
                });

                var sendBtn = wrap.querySelector('[data-sr-react-send]');
                var cancelBtn = wrap.querySelector('[data-sr-react-cancel]');
                if (sendBtn) {
                    sendBtn.addEventListener('click', function () {
                        if (!pendingType) return;
                        sendReaction(wrap, pendingType, textarea ? textarea.value : '', pendingBtn);
                        pendingType = null;
                        pendingBtn = null;
                    });
                }
                if (cancelBtn) {
                    cancelBtn.addEventListener('click', function () {
                        if (form) form.hidden = true;
                        pendingType = null;
                        pendingBtn = null;
                        setStatus(wrap, '', true);
                    });
                }
            });
        })();
    </script>
</body>
</html>
