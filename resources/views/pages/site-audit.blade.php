@component('component.card', [
    'title' => 'Аудит сайта',
    'titleHtml' => e('Аудит сайта') . view('partials.cabinet-module-version-badge', ['configKey' => 'cabinet-site-audit'])->render(),
])
    @slot('css')
        <link rel="stylesheet" href="{{ asset('css/cabinet-site-audit.css') }}?v={{ @filemtime(public_path('css/cabinet-site-audit.css')) ?: time() }}">
    @endslot

    @php
        $saPageUi = (int) config('cabinet-site-audit.page_ui', 2);
        if (!in_array($saPageUi, [1, 2], true)) {
            $saPageUi = 2;
        }
        // Аварийный откат: ?sa_ui=1 (без публичного переключателя)
        $saUiOverride = (int) request()->query('sa_ui', 0);
        if (in_array($saUiOverride, [1, 2], true)) {
            $saPageUi = $saUiOverride;
        }
    @endphp

    <div class="cabinet-sa-page cabinet-sa-page--lite cabinet-sa-page-ui--{{ $saPageUi }}" data-sa-page-ui="{{ $saPageUi }}">
        @if(session('status'))
            <div class="alert alert-success py-2">{{ session('status') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-warning py-2">{{ session('error') }}</div>
        @endif

        @include('pages.partials.site-audit-module-nav', ['active' => 'module'])

        @include('pages.partials.site-audit-launch-v' . $saPageUi)

            <div class="col-lg-7 cabinet-sa-projects-col">
                @if($saPageUi === 2)
                    @include('pages.partials.site-audit-projects-v2')
                @else
                    @include('pages.partials.site-audit-projects-v1')
                @endif
            </div>
        </div>

        @if($saPageUi === 2)
            @include('pages.partials.site-audit-history-v2')
        @else
            @include('pages.partials.site-audit-history-v1')
        @endif

        </div>{{-- /sa-step-workspace --}}
    </div>

    @slot('js')
        @include('partials.cabinet-confirm-modal')
        @if(!empty($teamAccessReady))
            @include('pages.partials.site-audit-team-create-modal')
        @endif
        <script>
            (function () {
                var pageRoot = document.querySelector('.cabinet-sa-page');
                var MODE_KEY = 'cabinet-sa-ui-mode';
                var PICKED_KEY = 'cabinet-sa-ui-mode-picked';
                var stepMode = document.getElementById('sa-step-mode');
                var stepWork = document.getElementById('sa-step-workspace');
                var backModeBtn = document.getElementById('sa-steps-back-mode');
                var modeLabel = document.getElementById('sa-steps-mode-label');
                function showWizardStep(step) {
                    if (stepMode) stepMode.hidden = step !== 'mode';
                    if (stepWork) stepWork.hidden = step !== 'workspace';
                    if (pageRoot) {
                        pageRoot.classList.toggle('cabinet-sa-page--choosing', step === 'mode');
                    }
                }

                function syncModeSwitcher(mode) {
                    document.querySelectorAll('[data-sa-switch-mode]').forEach(function (btn) {
                        var on = btn.getAttribute('data-sa-switch-mode') === mode;
                        btn.classList.toggle('is-active', on);
                        btn.setAttribute('aria-pressed', on ? 'true' : 'false');
                    });
                    document.querySelectorAll('.cabinet-sa-mode-card').forEach(function (card) {
                        var on = card.getAttribute('data-sa-pick-mode') === mode;
                        card.classList.toggle('is-selected', on);
                        card.setAttribute('aria-pressed', on ? 'true' : 'false');
                    });
                    if (modeLabel) {
                        modeLabel.textContent = mode === 'pro' ? 'Расширенный' : 'Простой';
                    }
                }

                function applySaMode(mode, opts) {
                    opts = opts || {};
                    mode = mode === 'pro' ? 'pro' : 'lite';
                    if (pageRoot) {
                        pageRoot.classList.toggle('cabinet-sa-page--lite', mode === 'lite');
                        pageRoot.classList.toggle('cabinet-sa-page--pro', mode === 'pro');
                    }
                    syncModeSwitcher(mode);
                    var domainEl = document.getElementById('sa-domain');
                    if (domainEl) {
                        var ph = domainEl.getAttribute(mode === 'lite' ? 'data-placeholder-lite' : 'data-placeholder-pro');
                        if (ph) domainEl.setAttribute('placeholder', ph.replace(/&#10;/g, '\n'));
                        domainEl.rows = mode === 'lite' ? 1 : 3;
                    }
                    if (mode === 'lite') {
                        var concEl = document.getElementById('sa-concurrency');
                        if (concEl) {
                            var liteDef = String(parseInt(concEl.getAttribute('data-lite-default') || '1', 10) || 1);
                            concEl.value = liteDef;
                        }
                    }
                    try {
                        localStorage.setItem(MODE_KEY, mode);
                        if (opts.rememberPick) {
                            localStorage.setItem(PICKED_KEY, '1');
                        }
                    } catch (e) {}
                }

                function goWorkspace(mode, remember) {
                    applySaMode(mode, { rememberPick: !!remember });
                    showWizardStep('workspace');
                    var domainEl = document.getElementById('sa-domain');
                    if (domainEl && remember) {
                        setTimeout(function () { domainEl.focus(); }, 50);
                    }
                    try {
                        document.dispatchEvent(new CustomEvent('cabinet-sa-workspace-ready', {
                            detail: { mode: mode, remember: !!remember }
                        }));
                    } catch (e) {}
                }

                function goModePick() {
                    showWizardStep('mode');
                }

                var savedMode = 'lite';
                var hasPicked = false;
                try {
                    var s = localStorage.getItem(MODE_KEY);
                    if (s === 'pro' || s === 'lite') savedMode = s;
                    hasPicked = localStorage.getItem(PICKED_KEY) === '1';
                } catch (e) {}

                // Если пришли с highlight/history — сразу рабочий экран
                var forceWorkspace = window.location.hash === '#sa-history'
                    || /[?&]highlight=/.test(window.location.search)
                    || /[?&]domain=/.test(window.location.search);

                if (hasPicked || forceWorkspace) {
                    goWorkspace(savedMode, false);
                } else {
                    applySaMode(savedMode, { rememberPick: false });
                    goModePick();
                }

                document.querySelectorAll('[data-sa-pick-mode]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        goWorkspace(btn.getAttribute('data-sa-pick-mode'), true);
                    });
                });
                document.querySelectorAll('[data-sa-switch-mode]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        goWorkspace(btn.getAttribute('data-sa-switch-mode'), true);
                    });
                });
                if (backModeBtn) {
                    backModeBtn.addEventListener('click', function () {
                        goModePick();
                    });
                }

                function syncSpeedPresets() {
                    var speedEl = document.getElementById('sa-speed');
                    var concEl = document.getElementById('sa-concurrency');
                    if (!speedEl || !concEl) return;
                    var speed = speedEl.value;
                    var conc = String(concEl.value);
                    document.querySelectorAll('[data-sa-preset]').forEach(function (btn) {
                        var on = btn.getAttribute('data-speed') === speed
                            && String(btn.getAttribute('data-concurrency')) === conc;
                        btn.classList.toggle('is-active', on);
                    });
                }
                document.querySelectorAll('[data-sa-preset]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        var speedEl = document.getElementById('sa-speed');
                        var concEl = document.getElementById('sa-concurrency');
                        if (speedEl) speedEl.value = btn.getAttribute('data-speed') || 'normal';
                        if (concEl) {
                            var c = parseInt(btn.getAttribute('data-concurrency') || '1', 10) || 1;
                            if (concEl.querySelector('option[value="' + c + '"]')) {
                                concEl.value = String(c);
                            } else if (concEl.options.length) {
                                concEl.selectedIndex = Math.min(Math.max(c - 1, 0), concEl.options.length - 1);
                            }
                        }
                        syncSpeedPresets();
                    });
                });
                var speedEl = document.getElementById('sa-speed');
                var concEl = document.getElementById('sa-concurrency');
                if (speedEl) speedEl.addEventListener('change', syncSpeedPresets);
                if (concEl) concEl.addEventListener('change', syncSpeedPresets);
                syncSpeedPresets();

                var startBtn = document.getElementById('sa-start');
                var msg = document.getElementById('sa-msg');
                var tokenMeta = document.querySelector('meta[name="csrf-token"]');
                var token = tokenMeta ? tokenMeta.getAttribute('content') : '';
                var historyTable = document.getElementById('sa-history-table');

                window.cabinetSaInitHistoryTips = function (root) {
                    if (typeof window.jQuery === 'undefined' || !window.jQuery.fn || !window.jQuery.fn.tooltip) {
                        return;
                    }
                    var $scope = window.jQuery(root || historyTable || document);
                    $scope.find('[data-toggle="tooltip"]').each(function () {
                        var $el = window.jQuery(this);
                        var prev = $el.data('bs.tooltip') || $el.data('tooltip');
                        if (prev) {
                            try { $el.tooltip('dispose'); } catch (e1) {
                                try { $el.tooltip('destroy'); } catch (e2) {}
                            }
                        }
                        $el.tooltip({
                            container: 'body',
                            placement: $el.attr('data-placement') || 'top',
                            trigger: 'hover focus',
                            delay: { show: 150, hide: 0 }
                        });
                    });
                };
                var pollTimers = {};

                function saParseIntSpaces(v) {
                    var s = String(v == null ? '' : v).replace(/[\s\u00a0\u202f]/g, '');
                    var n = parseInt(s, 10);
                    return isNaN(n) ? 0 : n;
                }

                function saFormatIntSpaces(n) {
                    n = Math.max(0, Math.floor(Number(n) || 0));
                    return String(n).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
                }

                function saBindNumSpace(inp) {
                    if (!inp || inp._saNumBound) return;
                    inp._saNumBound = true;
                    inp.addEventListener('focus', function () {
                        inp.value = String(saParseIntSpaces(inp.value) || '');
                    });
                    inp.addEventListener('blur', function () {
                        var min = saParseIntSpaces(inp.getAttribute('data-min') || '1') || 1;
                        var maxAttr = inp.getAttribute('data-max');
                        var max = maxAttr != null && maxAttr !== '' ? saParseIntSpaces(maxAttr) : 0;
                        var n = saParseIntSpaces(inp.value);
                        if (n < min) n = min;
                        if (max > 0 && n > max) n = max;
                        inp.value = saFormatIntSpaces(n);
                    });
                }

                document.querySelectorAll('.sa-num-space').forEach(saBindNumSpace);

                document.querySelectorAll('form.cabinet-sa-project__schedule').forEach(function (form) {
                    form.addEventListener('submit', function () {
                        var lim = form.querySelector('.sa-num-space[name="pages_limit"]');
                        if (lim) lim.value = String(saParseIntSpaces(lim.value) || 1);
                    });
                });

                function scrollToHistory() {
                    var el = document.getElementById('sa-history');
                    if (el && el.scrollIntoView) {
                        el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                }

                function updateRow(row, j) {
                    if (!row || !j) return;
                    if (j.queue_hint) {
                        try {
                            if (!window.__saQueueHintAt || (Date.now() - window.__saQueueHintAt) > 20000) {
                                window.__saQueueHintAt = Date.now();
                                if (window.toastr) {
                                    toastr.warning(j.queue_hint);
                                } else {
                                    console.warn(j.queue_hint);
                                }
                            }
                        } catch (e) {}
                    }
                    var statusEl = row.querySelector('[data-sa-status]');
                    if (statusEl) {
                        statusEl.textContent = j.status_label || j.status;
                        statusEl.className = 'cabinet-sa-status cabinet-sa-status--' +
                            (j.status === 'done' ? 'done' : ((j.status === 'failed' || j.status === 'cancelled') ? 'failed' : 'run'));
                    }
                    var prog = row.querySelector('[data-sa-progress]');
                    if (prog) {
                        var fetched = j.pages_fetched || 0;
                        var total = j.pages_total || 0;
                        var pct = j.progress_pct || (total > 0 ? Math.round(100 * fetched / total) : 0);
                        var st = j.status || '';
                        var isFailed = st === 'failed' || st === 'cancelled';
                        var finished = !!j.finished;
                        var indeterminate = !finished && (total < 1 || st === 'queued' || st === 'queued_wait' || st === 'discovering');
                        var fillClass, fill, label, hint;
                        if (finished && !isFailed) {
                            fillClass = 'cabinet-sa-prog__fill is-done';
                            fill = 100;
                            label = fetched + ' / ' + total;
                            hint = 'готово';
                        } else if (isFailed) {
                            fillClass = 'cabinet-sa-prog__fill is-fail';
                            fill = total > 0 ? Math.round(100 * fetched / Math.max(1, total)) : 0;
                            if (fill < 1) fill = 100;
                            label = fetched + ' / ' + total;
                            hint = st === 'cancelled' ? 'остановлен' : 'ошибка';
                        } else if (indeterminate) {
                            fillClass = 'cabinet-sa-prog__fill is-wait';
                            fill = 100;
                            label = total > 0 ? (fetched + ' / ' + total) : '…';
                            hint = (st === 'queued_wait')
                                ? 'ждёт слот'
                                : ((st === 'queued') ? 'запуск' : (st === 'discovering' ? 'сбор URL' : 'ожидание'));
                        } else {
                            fillClass = 'cabinet-sa-prog__fill is-run';
                            fill = pct;
                            label = fetched + ' / ' + total;
                            hint = st === 'aggregating' ? 'агрегация' : 'сканирование';
                        }
                        var fmtN = function (n) {
                            return String(n).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
                        };
                        var shortLabel = (total > 0 || fetched > 0) ? (fetched + '/' + total) : '…';
                        var spacedLabel = (total > 0 || fetched > 0)
                            ? (fmtN(fetched) + ' / ' + fmtN(total))
                            : '…';
                        // v2 compact mini-progress
                        if (prog.querySelector('.cabinet-sa-mini-prog') || prog.closest('.cabinet-sa-history-table')) {
                            var barHtml = '';
                            var miniFillClass = fillClass.replace('cabinet-sa-prog__fill ', '');
                            if (!finished || isFailed) {
                                barHtml = '<span class="cabinet-sa-mini-prog__bar"><i class="' + miniFillClass + '" style="width:' + fill + '%"></i></span>';
                            }
                            prog.innerHTML =
                                '<div class="cabinet-sa-mini-prog" title="' + hint + ' · ' + spacedLabel + '">' +
                                '<span class="cabinet-sa-mini-prog__n">' + spacedLabel + '</span>' + barHtml + '</div>';
                        } else {
                            // v1 table progress
                            var barClass = finished && !isFailed
                                ? 'progress-bar bg-success'
                                : (isFailed
                                    ? 'progress-bar progress-bar-striped progress-bar-animated bg-danger'
                                    : (indeterminate
                                        ? 'progress-bar progress-bar-striped progress-bar-animated bg-warning'
                                        : 'progress-bar progress-bar-striped progress-bar-animated bg-info'));
                            prog.innerHTML =
                                '<div class="progress" role="progressbar" aria-label="' + hint +
                                '" aria-valuenow="' + fill + '" aria-valuemin="0" aria-valuemax="100" title="' +
                                hint + ' · ' + fetched + '/' + total + '">' +
                                '<div class="' + barClass + '" style="width:' + fill + '%; border-radius: 0.375rem">' +
                                shortLabel + '</div></div>';
                        }
                    }
                    if (j.buckets) {
                        var sevClass = {
                            critical: 'is-critical',
                            other: 'is-other',
                            important: 'is-important',
                            warning: 'is-warning',
                            info: 'is-info'
                        };
                        var sevTitle = {
                            critical: 'Грубые',
                            other: 'Прочие',
                            important: 'Важные замечания',
                            warning: 'Предупреждения',
                            info: 'Инфо'
                        };
                        var fmtNum = function (n) {
                            return String(n).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
                        };
                        var hiddenBuckets = j.buckets_hidden || {};
                        ['critical', 'other', 'important', 'warning', 'info'].forEach(function (k) {
                            var cell = row.querySelector('[data-sa-bucket="' + k + '"]');
                            if (cell && typeof j.buckets[k] !== 'undefined') {
                                var n = parseInt(j.buckets[k], 10);
                                if (isNaN(n)) {
                                    cell.textContent = j.buckets[k];
                                    return;
                                }
                                var hid = parseInt(hiddenBuckets[k], 10);
                                if (isNaN(hid)) {
                                    hid = parseInt(cell.getAttribute('data-sa-bucket-hidden') || '0', 10) || 0;
                                }
                                if (hid < 0) {
                                    hid = 0;
                                }
                                if (hid > n) {
                                    hid = n;
                                }
                                var open = Math.max(0, n - hid);
                                var tip = (sevTitle[k] || k) + ': ' + fmtNum(n);
                                var html;
                                if (hid > 0) {
                                    tip = (sevTitle[k] || k) + ': всего ' + fmtNum(n)
                                        + ' · осталось ' + fmtNum(open)
                                        + ' · скрыто ' + fmtNum(hid)
                                        + ' (игнор или исправлено)';
                                    html = '<span class="cabinet-sa-ht-num__trio" aria-label="' + tip.replace(/"/g, '&quot;') + '">'
                                        + '<span class="cabinet-sa-ht-num__t">' + fmtNum(n) + '</span>'
                                        + '<span class="cabinet-sa-ht-num__sep" aria-hidden="true">/</span>'
                                        + '<span class="cabinet-sa-ht-num__open">' + fmtNum(open) + '</span>'
                                        + '<span class="cabinet-sa-ht-num__sep" aria-hidden="true">/</span>'
                                        + '<span class="cabinet-sa-ht-num__hid">' + fmtNum(hid) + '</span>'
                                        + '</span>'
                                        + '<span class="cabinet-sa-ht-num__legend" aria-hidden="true">все / ост. / скр.</span>';
                                    cell.classList.add('has-split');
                                } else {
                                    html = '<span class="cabinet-sa-ht-num__v">' + fmtNum(n) + '</span>';
                                    cell.classList.remove('has-split');
                                }
                                cell.innerHTML = html;
                                cell.setAttribute('data-sa-bucket-hidden', String(hid));
                                cell.classList.remove('is-critical', 'is-other', 'is-important', 'is-warning', 'is-info', 'is-zero');
                                cell.classList.add(n > 0 ? sevClass[k] : 'is-zero');
                                cell.title = tip;
                            }
                        });
                    }
                    if (j.started_at) {
                        var startedEl = row.querySelector('[data-sa-started]');
                        if (startedEl) startedEl.textContent = j.started_at;
                    }
                    var finishedEl = row.querySelector('[data-sa-finished]');
                    if (finishedEl) {
                        if (j.finished_at) {
                            finishedEl.textContent = j.finished_at;
                            finishedEl.removeAttribute('title');
                        } else if (j.finished) {
                            finishedEl.textContent = '—';
                            finishedEl.removeAttribute('title');
                        } else if (j.eta_at) {
                            var etaTip = j.eta_title || 'Ориентир конца (не точное время)';
                            finishedEl.innerHTML = '<span class="text-muted" title="' + String(etaTip).replace(/"/g, '&quot;') + '">~' + j.eta_at + '</span>';
                        } else if ((j.status || '') === 'aggregating') {
                            finishedEl.innerHTML = '<span class="text-muted" title="Конец появится после «Готово». Сейчас финальный этап.">идёт…</span>';
                        } else {
                            finishedEl.innerHTML = '<span class="text-muted" title="Слишком рано для оценки">идёт…</span>';
                        }
                    }
                    if (j.finished) {
                        row.setAttribute('data-finished', '1');
                        row.classList.remove('cabinet-sa-row--active');
                        var actions = row.querySelector('.cabinet-sa-row-actions');
                        if (actions) {
                            // Убрать «Стоп» — иначе !querySelector('form') блокирует «Повторить»
                            actions.querySelectorAll('form').forEach(function (form) {
                                var action = (form.getAttribute('action') || '');
                                var btn = form.querySelector('button');
                                var isStop = action.indexOf('/cancel') !== -1
                                    || (btn && /Стоп/.test(btn.textContent || ''));
                                if (isStop) {
                                    form.remove();
                                }
                            });
                            if (!actions.querySelector('form[action*="/repeat"]')) {
                                var domainEl = row.querySelector('[data-sa-domain]') || row.querySelector('td.fw-medium');
                                var domain = (domainEl && domainEl.textContent) ? domainEl.textContent.trim() : 'проекта';
                                domain = String(domain).trim() || 'проекта';
                                if (j.can_resume && !actions.querySelector('form[action*="/continue"]')) {
                                    var cont = document.createElement('form');
                                    cont.method = 'POST';
                                    cont.action = '{{ url('site-audit/crawl') }}/' + j.id + '/continue';
                                    cont.className = 'd-inline';
                                    cont.setAttribute('data-cabinet-confirm',
                                        'Возобновить проверку #' + j.id + ' с ' + (j.pages_fetched || 0) +
                                        ' URL? Уже скачанные страницы сохранятся.');
                                    cont.setAttribute('data-cabinet-confirm-title', 'Возобновить проверку');
                                    cont.setAttribute('data-cabinet-confirm-ok', 'Возобновить');
                                    cont.innerHTML =
                                        '<input type="hidden" name="_token" value="' + token + '">' +
                                        '<button type="submit" class="btn btn-sm btn-outline-primary cabinet-sa-icon-btn"' +
                                        ' data-toggle="tooltip" data-placement="top"' +
                                        ' title="Возобновить проверку" aria-label="Возобновить проверку">' +
                                        '<i class="bi bi-play-fill" aria-hidden="true"></i></button>';
                                    actions.appendChild(cont);
                                }
                                var repeat = document.createElement('form');
                                repeat.method = 'POST';
                                repeat.action = '{{ url('site-audit/crawl') }}/' + j.id + '/repeat';
                                repeat.className = 'd-inline';
                                repeat.setAttribute('data-cabinet-confirm',
                                    'Повторить проверку для ' + domain + ' с теми же настройками? Начнётся новая проверка с нуля.');
                                repeat.setAttribute('data-cabinet-confirm-title', 'Новая проверка');
                                repeat.setAttribute('data-cabinet-confirm-ok', 'Повторить');
                                repeat.innerHTML =
                                    '<input type="hidden" name="_token" value="' + token + '">' +
                                    '<button type="submit" class="btn btn-sm btn-outline-secondary cabinet-sa-icon-btn"' +
                                    ' data-toggle="tooltip" data-placement="top"' +
                                    ' title="Повторить с нуля" aria-label="Повторить с нуля">' +
                                    '<i class="bi bi-arrow-clockwise" aria-hidden="true"></i></button>';
                                actions.appendChild(repeat);

                                var del = document.createElement('form');
                                del.method = 'POST';
                                del.action = '{{ url('site-audit/crawl') }}/' + j.id;
                                del.className = 'd-inline';
                                del.setAttribute('data-cabinet-confirm', 'Удалить проверку #' + j.id + '?');
                                del.setAttribute('data-cabinet-confirm-title', 'Удаление проверки');
                                del.setAttribute('data-cabinet-confirm-ok', 'Удалить');
                                del.setAttribute('data-cabinet-confirm-danger', '1');
                                del.innerHTML =
                                    '<input type="hidden" name="_token" value="' + token + '">' +
                                    '<input type="hidden" name="_method" value="DELETE">' +
                                    '<button type="submit" class="btn btn-sm btn-outline-danger cabinet-sa-icon-btn"' +
                                    ' data-toggle="tooltip" data-placement="top"' +
                                    ' title="Удалить проверку" aria-label="Удалить проверку">' +
                                    '<i class="bi bi-trash" aria-hidden="true"></i></button>';
                                actions.appendChild(del);
                                if (typeof window.cabinetSaInitHistoryTips === 'function') {
                                    window.cabinetSaInitHistoryTips(actions);
                                }
                            }
                        }
                    }
                }

                function pollRow(row) {
                    var id = row.getAttribute('data-crawl-id');
                    var url = row.getAttribute('data-status-url');
                    if (!id || !url || row.getAttribute('data-finished') === '1') return;
                    if (pollTimers[id]) return;

                    function tick() {
                        if (row.getAttribute('data-finished') === '1') {
                            delete pollTimers[id];
                            return;
                        }
                        fetch(url, { headers: { 'Accept': 'application/json' } })
                            .then(function (r) { return r.json(); })
                            .then(function (j) {
                                updateRow(row, j);
                                if (j.finished) {
                                    delete pollTimers[id];
                                    return;
                                }
                                pollTimers[id] = setTimeout(tick, 2000);
                            })
                            .catch(function () {
                                pollTimers[id] = setTimeout(tick, 4000);
                            });
                    }
                    pollTimers[id] = setTimeout(tick, 800);
                }

                function pollActiveRows() {
                    if (!historyTable) return;
                    historyTable.querySelectorAll('[data-crawl-id][data-finished="0"]').forEach(pollRow);
                }

                function bindCancelForms(root) {
                    (root || document).querySelectorAll('form[data-sa-cancel-crawl]').forEach(function (form) {
                        if (form.getAttribute('data-sa-cancel-bound') === '1') return;
                        form.setAttribute('data-sa-cancel-bound', '1');
                        form.addEventListener('submit', function (e) {
                            e.preventDefault();
                            var row = form.closest('tr');
                            var btn = form.querySelector('button');
                            if (btn) btn.disabled = true;
                            fetch(form.action, {
                                method: 'POST',
                                headers: {
                                    'Accept': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'X-CSRF-TOKEN': token
                                },
                                body: new FormData(form)
                            }).then(function (r) {
                                return r.json().then(function (j) { return { ok: r.ok, j: j }; });
                            }).then(function (x) {
                                if (!x.ok) {
                                    if (btn) btn.disabled = false;
                                    alert((x.j && x.j.message) ? x.j.message : 'Не удалось остановить');
                                    return;
                                }
                                if (row) {
                                    updateRow(row, x.j);
                                }
                            }).catch(function (err) {
                                if (btn) btn.disabled = false;
                                alert(String(err));
                            });
                        });
                    });
                }
                bindCancelForms(document);

                var historyIndexUrl = '{{ route('pages.site-audit') }}';
                var historyLoadSeq = 0;

                function historyPublicUrl(domain, page) {
                    var u = new URL(historyIndexUrl, window.location.origin);
                    domain = String(domain || '').trim();
                    if (domain) u.searchParams.set('domain', domain);
                    page = parseInt(page, 10) || 1;
                    if (page > 1) u.searchParams.set('page', String(page));
                    return u.pathname + u.search + '#sa-history';
                }

                function historyFetchUrl(domain, page) {
                    var u = new URL(historyIndexUrl, window.location.origin);
                    domain = String(domain || '').trim();
                    if (domain) u.searchParams.set('domain', domain);
                    page = parseInt(page, 10) || 1;
                    if (page > 1) u.searchParams.set('page', String(page));
                    u.searchParams.set('partial', 'history');
                    return u.toString();
                }

                function afterHistoryReplace(focusSearch) {
                    historyTable = document.getElementById('sa-history-table');
                    Object.keys(pollTimers).forEach(function (id) {
                        clearTimeout(pollTimers[id]);
                        delete pollTimers[id];
                    });
                    bindCancelForms(document.getElementById('sa-history'));
                    pollActiveRows();
                    if (typeof window.cabinetSaInitHistoryTips === 'function') {
                        window.cabinetSaInitHistoryTips(historyTable);
                    }
                    if (focusSearch) {
                        var inp = document.getElementById('sa-history-domain');
                        if (inp) {
                            inp.focus();
                            try {
                                var len = inp.value.length;
                                inp.setSelectionRange(len, len);
                            } catch (e) {}
                        }
                    }
                }

                function loadHistoryPartial(opts) {
                    opts = opts || {};
                    var domain = opts.domain != null
                        ? String(opts.domain)
                        : String((document.getElementById('sa-history-domain') || {}).value || '');
                    domain = domain.trim();
                    var page = opts.page || 1;
                    var seq = ++historyLoadSeq;
                    var section = document.getElementById('sa-history');
                    if (section) section.classList.add('is-loading');

                    fetch(historyFetchUrl(domain, page), {
                        headers: {
                            'Accept': 'text/html',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        credentials: 'same-origin'
                    }).then(function (r) {
                        if (!r.ok) throw new Error('HTTP ' + r.status);
                        return r.text();
                    }).then(function (html) {
                        if (seq !== historyLoadSeq) return;
                        var wrap = document.createElement('div');
                        wrap.innerHTML = String(html || '').trim();
                        var next = wrap.querySelector('#sa-history') || wrap.firstElementChild;
                        var cur = document.getElementById('sa-history');
                        if (!cur || !next) throw new Error('empty history');
                        cur.replaceWith(next);
                        if (window.history && window.history.replaceState) {
                            window.history.replaceState(null, '', historyPublicUrl(domain, page));
                        }
                        afterHistoryReplace(opts.focusSearch !== false);
                    }).catch(function () {
                        if (seq !== historyLoadSeq) return;
                        window.location = historyPublicUrl(domain, page);
                    }).then(function () {
                        var s = document.getElementById('sa-history');
                        if (s) s.classList.remove('is-loading');
                    });
                }

                if (pageRoot) {
                    pageRoot.addEventListener('submit', function (e) {
                        var form = e.target;
                        if (!form || !form.matches || !form.matches('[data-sa-history-ajax]')) return;
                        e.preventDefault();
                        loadHistoryPartial({ page: 1, focusSearch: true });
                    });
                    pageRoot.addEventListener('click', function (e) {
                        var clearBtn = e.target && e.target.closest
                            ? e.target.closest('[data-sa-history-clear]')
                            : null;
                        if (clearBtn) {
                            e.preventDefault();
                            var inp = document.getElementById('sa-history-domain');
                            if (inp) inp.value = '';
                            loadHistoryPartial({ domain: '', page: 1, focusSearch: true });
                            return;
                        }
                        var pagerLink = e.target && e.target.closest
                            ? e.target.closest('#sa-history .cabinet-sa-history__pager a, #sa-history .pagination a')
                            : null;
                        if (!pagerLink) return;
                        e.preventDefault();
                        try {
                            var href = pagerLink.getAttribute('href') || '';
                            var u = new URL(href, window.location.origin);
                            var domain = u.searchParams.get('domain') || '';
                            var page = parseInt(u.searchParams.get('page') || '1', 10) || 1;
                            loadHistoryPartial({ domain: domain, page: page, focusSearch: false });
                        } catch (err) {
                            window.location = pagerLink.href;
                        }
                    });
                }

                if (startBtn) {
                    startBtn.addEventListener('click', function () {
                        startBtn.disabled = true;
                        msg.textContent = 'Запуск…';
                        var pagesOnlyEl = document.getElementById('sa-pages-only');
                        var concEl = document.getElementById('sa-concurrency');
                        var isLite = pageRoot && pageRoot.classList.contains('cabinet-sa-page--lite');
                        var concurrency = (concEl && concEl.value) ? concEl.value : '1';
                        if (isLite) {
                            var liteDef = parseInt((concEl && concEl.getAttribute('data-lite-default')) || '1', 10);
                            concurrency = String(Math.max(1, liteDef || 1));
                        }
                        var body = {
                            domain: document.getElementById('sa-domain').value,
                            seed_urls: document.getElementById('sa-seeds').value,
                            pages_only: pagesOnlyEl && pagesOnlyEl.checked ? '1' : '0',
                            extra_hosts: (document.getElementById('sa-extra-hosts') || {}).value || '',
                            virtual_robots: document.getElementById('sa-robots').value,
                            crawl_speed: document.getElementById('sa-speed').value,
                            concurrency: concurrency,
                            unify_www: true,
                            force_https: true,
                            strip_trailing_slash: false,
                            check_broken_links: true
                        };
                        var limitEl = document.getElementById('sa-limit');
                        if (limitEl && limitEl.value) body.pages_limit = String(saParseIntSpaces(limitEl.value) || '');

                        fetch('{{ route('pages.site-audit.start') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': token,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify(body)
                        }).then(function (r) {
                            return r.json().then(function (j) { return { ok: r.ok, j: j }; });
                        }).then(function (x) {
                            if (x.ok) {
                                msg.textContent = (x.j && x.j.message) ? x.j.message : 'Запущено';
                                var base = (x.j && x.j.redirect) ? x.j.redirect : '{{ route('pages.site-audit') }}';
                                var q = (x.j && x.j.crawl_id) ? ('?highlight=' + x.j.crawl_id) : '';
                                window.location = base + q + '#sa-history';
                                return;
                            }
                            msg.textContent = (x.j && x.j.message) ? x.j.message : 'Ошибка';
                            startBtn.disabled = false;
                        }).catch(function (e) {
                            msg.textContent = String(e);
                            startBtn.disabled = false;
                        });
                    });
                }

                pollActiveRows();
                if (typeof window.cabinetSaInitHistoryTips === 'function') {
                    window.cabinetSaInitHistoryTips(historyTable);
                }

                if (window.location.hash === '#sa-history' || /[?&]highlight=/.test(window.location.search) || /[?&]domain=/.test(window.location.search)) {
                    setTimeout(scrollToHistory, 100);
                    var m = window.location.search.match(/[?&]highlight=(\d+)/);
                    if (m) {
                        var hi = historyTable && historyTable.querySelector('[data-crawl-id="' + m[1] + '"]');
                        if (hi) hi.classList.add('table-active');
                    }
                }
            })();
        </script>
        <script src="{{ asset('js/cabinet-site-audit-tour.js') }}?v={{ @filemtime(public_path('js/cabinet-site-audit-tour.js')) ?: time() }}"></script>
    @endslot
@endcomponent
