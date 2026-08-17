(function () {
    'use strict';

    var root = document.getElementById('cabinetSsPage');
    if (!root) {
        return;
    }

    var collectUrl = root.getAttribute('data-collect-url');
    var exportUrl = root.getAttribute('data-export-url');
    var historyBase = root.getAttribute('data-history-url');
    var regionsUrl = root.getAttribute('data-regions-url');
    var csrf = root.getAttribute('data-csrf');
    var canSave = root.getAttribute('data-can-save') === '1';

    var form = document.getElementById('cabinetSsForm');
    var submitBtn = document.getElementById('cabinetSsSubmit');
    var clearBtn = document.getElementById('cabinetSsClear');
    var statusEl = document.getElementById('cabinetSsStatus');
    var progressEl = document.getElementById('cabinetSsProgress');
    var progressTitle = document.getElementById('cabinetSsProgressTitle');
    var progressSub = document.getElementById('cabinetSsProgressSub');
    var progressTime = document.getElementById('cabinetSsProgressTime');
    var progressBar = document.getElementById('cabinetSsProgressBar');
    var resultsWrap = document.getElementById('cabinetSsResultsWrap');
    var resultsBody = document.querySelector('#cabinetSsResults tbody');
    var resultsMeta = document.getElementById('cabinetSsResultsMeta');
    var exportBtn = document.getElementById('cabinetSsExport');
    var copySuggestsBtn = document.getElementById('cabinetSsCopySuggests');
    var regionSelect = document.getElementById('cabinetSsYandexLr');
    var regionWrap = document.getElementById('cabinetSsYandexRegionWrap');
    var googleDomainWrap = document.getElementById('cabinetSsGoogleDomainWrap');
    var googleCountryWrap = document.getElementById('cabinetSsGoogleCountryWrap');
    var googleDomainSelect = document.getElementById('cabinetSsGoogleDomain');
    var googleCountrySelect = document.getElementById('cabinetSsGoogleGl');
    var engineYandex = document.getElementById('engine_yandex');
    var engineGoogle = document.getElementById('engine_google');

    var lastResults = [];
    var progressTimer = null;
    var progressStartedAt = 0;
    var progressExpectedMs = 15000;
    var collectInFlight = false;
    var titleFlashTimer = null;
    var pageTitleBase = document.title;
    var stopPresets = {};
    try {
        stopPresets = JSON.parse(root.getAttribute('data-stop-presets') || '{}') || {};
    } catch (e) {
        stopPresets = {};
    }
    var i18nNotify = {
        keep: root.getAttribute('data-i18n-keep') || '',
        doneTitle: root.getAttribute('data-i18n-notify-done-title') || 'Подсказки собраны',
        doneBody: root.getAttribute('data-i18n-notify-done-body') || 'Готово: :count подсказок за :time',
        errorTitle: root.getAttribute('data-i18n-notify-error-title') || 'Сбор подсказок не удался',
        errorBody: root.getAttribute('data-i18n-notify-error-body') || 'Откройте вкладку и посмотрите сообщение об ошибке.',
    };

    function initTooltip(el) {
        if (!el || !window.bootstrap || !window.bootstrap.Tooltip) {
            return;
        }
        var tip = el.getAttribute('data-ss-tip');
        if (!tip) {
            return;
        }
        var existing = window.bootstrap.Tooltip.getInstance(el);
        if (existing) {
            existing.dispose();
        }
        new window.bootstrap.Tooltip(el, {
            container: 'body',
            trigger: 'hover focus',
            placement: el.getAttribute('data-bs-placement') || 'top',
            customClass: 'cabinet-ss-tooltip',
            title: tip,
        });
    }

    function initTooltips(scope) {
        if (!window.bootstrap || !window.bootstrap.Tooltip) {
            return;
        }
        (scope || root).querySelectorAll('[data-ss-tip]').forEach(initTooltip);
    }

    function hideTooltip(el) {
        if (!el || !window.bootstrap || !window.bootstrap.Tooltip) {
            return;
        }
        var tip = window.bootstrap.Tooltip.getInstance(el);
        if (tip) {
            tip.hide();
        }
    }

    function initRegionSelect() {
        if (!regionSelect || typeof window.jQuery === 'undefined' || typeof window.jQuery.fn.select2 !== 'function') {
            return;
        }
        var $region = window.jQuery(regionSelect);
        if ($region.hasClass('select2-hidden-accessible')) {
            $region.select2('destroy');
        }
        $region.select2({
            theme: 'bootstrap4',
            placeholder: $region.data('placeholder') || 'Найти город или регион',
            allowClear: false,
            minimumInputLength: 0,
            width: '100%',
            dropdownParent: window.jQuery(document.body),
            language: {
                inputTooShort: function () {
                    return 'Введите название города или региона';
                },
                noResults: function () {
                    return 'Ничего не найдено';
                },
                searching: function () {
                    return 'Поиск…';
                },
            },
            ajax: {
                delay: 250,
                url: regionsUrl,
                dataType: 'json',
                data: function (params) {
                    return {
                        q: params.term || '',
                        limit: 25,
                    };
                },
                processResults: function (data) {
                    return {
                        results: window.jQuery.map(data.results || [], function (item) {
                            return {
                                id: item.id,
                                text: item.text || item.name,
                                name: item.name,
                            };
                        }),
                    };
                },
            },
        });
    }

    function initGoogleCountrySelect() {
        if (!googleCountrySelect || typeof window.jQuery === 'undefined' || typeof window.jQuery.fn.select2 !== 'function') {
            return;
        }
        var $country = window.jQuery(googleCountrySelect);
        if ($country.hasClass('select2-hidden-accessible')) {
            $country.select2('destroy');
        }
        $country.select2({
            theme: 'bootstrap4',
            placeholder: $country.data('placeholder') || 'Страна Google',
            allowClear: false,
            width: '100%',
            dropdownParent: window.jQuery(document.body),
        });
    }

    function setSelectVisible(el, visible) {
        if (!el) {
            return;
        }
        el.classList.toggle('d-none', !visible);
        el.style.display = visible ? '' : 'none';
    }

    function syncEngineGeoFields() {
        var yandexOn = !!(engineYandex && engineYandex.checked);
        var googleOn = !!(engineGoogle && engineGoogle.checked);
        setSelectVisible(regionWrap, yandexOn);
        setSelectVisible(googleDomainWrap, googleOn);
        setSelectVisible(googleCountryWrap, googleOn);
    }

    function applyDomainDefaults() {
        if (!googleDomainSelect || !googleCountrySelect) {
            return;
        }
        var opt = googleDomainSelect.options[googleDomainSelect.selectedIndex];
        if (!opt) {
            return;
        }
        var gl = opt.getAttribute('data-gl') || '';
        if (!gl) {
            return;
        }
        if (window.jQuery && window.jQuery(googleCountrySelect).hasClass('select2-hidden-accessible')) {
            window.jQuery(googleCountrySelect).val(gl).trigger('change');
        } else {
            googleCountrySelect.value = gl;
        }
    }

    function currentGoogleHl() {
        if (!googleCountrySelect) {
            return 'ru';
        }
        var opt = googleCountrySelect.options[googleCountrySelect.selectedIndex];
        return (opt && opt.getAttribute('data-hl')) || 'ru';
    }

    initRegionSelect();
    initGoogleCountrySelect();
    syncEngineGeoFields();
    if (engineYandex) {
        engineYandex.addEventListener('change', syncEngineGeoFields);
    }
    if (engineGoogle) {
        engineGoogle.addEventListener('change', syncEngineGeoFields);
    }
    if (googleDomainSelect) {
        googleDomainSelect.addEventListener('change', applyDomainDefaults);
    }

    var QUICK_PRESETS = {
        basic: {
            modes: { phrase: true, space: false, en: false, ru: false, digits: false },
            presets: { local: false, shopping: false, questions: false, reviews: false },
            depth: 1,
        },
        alphabet: {
            modes: { phrase: true, space: true, en: true, ru: true, digits: true },
            presets: { local: false, shopping: false, questions: false, reviews: false },
            depth: 1,
        },
        commerce: {
            modes: { phrase: true, space: true, en: false, ru: false, digits: false },
            presets: { local: true, shopping: true, questions: false, reviews: false },
            depth: 1,
        },
        questions: {
            modes: { phrase: true, space: false, en: false, ru: false, digits: false },
            presets: { local: false, shopping: false, questions: true, reviews: true },
            depth: 1,
        },
        max: {
            modes: { phrase: true, space: true, en: true, ru: true, digits: true },
            presets: { local: true, shopping: true, questions: true, reviews: true },
            depth: 2,
        },
    };

    function setCheckbox(id, on) {
        var el = document.getElementById(id);
        if (el) {
            el.checked = !!on;
        }
    }

    function applyQuickPreset(name) {
        var cfg = QUICK_PRESETS[name];
        if (!cfg) {
            return;
        }
        setCheckbox('mode_phrase', cfg.modes.phrase);
        setCheckbox('mode_space', cfg.modes.space);
        setCheckbox('mode_en', cfg.modes.en);
        setCheckbox('mode_ru', cfg.modes.ru);
        setCheckbox('mode_digits', cfg.modes.digits);
        setCheckbox('preset_local', cfg.presets.local);
        setCheckbox('preset_shopping', cfg.presets.shopping);
        setCheckbox('preset_questions', cfg.presets.questions);
        setCheckbox('preset_reviews', cfg.presets.reviews);
        var depthEl = document.getElementById('cabinetSsDepth');
        if (depthEl) {
            depthEl.value = String(cfg.depth || 1);
        }
        document.querySelectorAll('.cabinet-ss-quick__btn').forEach(function (btn) {
            btn.classList.toggle('is-active', btn.getAttribute('data-preset') === name);
        });
        setStatus('Пресет: ' + (document.querySelector('.cabinet-ss-quick__btn[data-preset="' + name + '"]') || {}).textContent || name, 'ok');
    }

    var quickWrap = document.getElementById('cabinetSsQuickPresets');
    if (quickWrap) {
        quickWrap.addEventListener('click', function (e) {
            var btn = e.target.closest('.cabinet-ss-quick__btn');
            if (!btn) {
                return;
            }
            hideTooltip(btn);
            applyQuickPreset(btn.getAttribute('data-preset'));
        });
    }

    function parseStopWords(raw) {
        return String(raw || '')
            .split(/[\r\n,;]+/u)
            .map(function (s) { return s.trim(); })
            .filter(Boolean);
    }

    function stopWordKey(w) {
        return String(w || '').toLowerCase().replace(/\s+/g, ' ');
    }

    function writeStopWords(list) {
        var el = document.getElementById('cabinetSsStop');
        if (!el) {
            return;
        }
        el.value = list.join('\n');
    }

    function mergeStopPreset(key) {
        var el = document.getElementById('cabinetSsStop');
        if (!el) {
            return;
        }
        if (key === 'clear') {
            el.value = '';
            document.querySelectorAll('.cabinet-ss-stop-presets__btn').forEach(function (b) {
                b.classList.remove('is-active');
            });
            setStatus('Стоп-слова очищены', 'ok');
            return;
        }
        var add = stopPresets[key];
        if (!Array.isArray(add) || !add.length) {
            return;
        }
        var seen = {};
        var out = [];
        parseStopWords(el.value).concat(add).forEach(function (w) {
            var k = stopWordKey(w);
            if (!k || seen[k]) {
                return;
            }
            seen[k] = true;
            out.push(w);
        });
        writeStopWords(out);
        var btn = document.querySelector('.cabinet-ss-stop-presets__btn[data-stop-preset="' + key + '"]');
        if (btn) {
            btn.classList.add('is-active');
        }
        setStatus('Стоп-слова: +' + add.length + ' («' + ((btn && btn.textContent) || key).trim() + '»)', 'ok');
    }

    var stopPresetWrap = document.getElementById('cabinetSsStopPresets');
    if (stopPresetWrap) {
        stopPresetWrap.addEventListener('click', function (e) {
            var btn = e.target.closest('.cabinet-ss-stop-presets__btn');
            if (!btn) {
                return;
            }
            hideTooltip(btn);
            mergeStopPreset(btn.getAttribute('data-stop-preset'));
        });
    }

    function updateHeaderRemaining(left) {
        var header = document.getElementById('cabinet-header-module-limit');
        if (!header || left == null) {
            return;
        }
        var strong = header.querySelector('strong.ms-1');
        if (strong) {
            strong.textContent = left;
        }
        if (Number(left) <= 0) {
            var link = header.querySelector('.nav-link');
            if (link) {
                link.classList.add('text-danger');
                link.classList.remove('text-warning-emphasis');
            }
        }
    }

    function updateHeaderSaved(used) {
        var el = document.getElementById('cabinet-header-module-secondary-used');
        if (!el || used == null) {
            return;
        }
        el.textContent = used;
        var wrap = document.getElementById('cabinet-header-module-secondary');
        if (!wrap) {
            return;
        }
        var link = wrap.querySelector('.nav-link');
        var limitAttr = root.getAttribute('data-history-limit');
        var limit = limitAttr !== '' && limitAttr != null ? Number(limitAttr) : null;
        if (link && limit != null && !isNaN(limit) && Number(used) >= limit) {
            link.classList.add('text-danger');
            link.classList.remove('text-warning-emphasis');
        } else if (link) {
            link.classList.remove('text-danger');
            link.classList.add('text-warning-emphasis');
        }
    }

    function updateCounters(data) {
        if (data.remaining != null) {
            updateHeaderRemaining(data.remaining);
            root.setAttribute('data-remaining', String(data.remaining));
        }
        if (data.saved_count != null) {
            updateHeaderSaved(data.saved_count);
            root.setAttribute('data-saved-count', String(data.saved_count));
        }
    }

    var TYPE_LABELS = {
        exact: 'точное',
        append: 'дополнение',
        contains: 'вхождение',
        reorder: 'перестановка',
        prefix: 'в начале',
        suggest: 'подсказка',
        'точное': 'точное',
        'дополнение': 'дополнение',
        'вхождение': 'вхождение',
        'перестановка': 'перестановка',
        'в начале': 'в начале',
        'подсказка': 'подсказка',
    };

    function typeLabel(t) {
        if (!t) return '';
        return TYPE_LABELS[t] || t;
    }

    function copyText(text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(text);
        }
        return new Promise(function (resolve, reject) {
            var ta = document.createElement('textarea');
            ta.value = text;
            ta.setAttribute('readonly', '');
            ta.style.position = 'fixed';
            ta.style.left = '-9999px';
            document.body.appendChild(ta);
            ta.select();
            try {
                if (document.execCommand('copy')) {
                    resolve();
                } else {
                    reject(new Error('copy failed'));
                }
            } catch (err) {
                reject(err);
            } finally {
                document.body.removeChild(ta);
            }
        });
    }

    function setStatus(text, kind) {
        statusEl.textContent = text || '';
        statusEl.className = 'small ml-2' + (kind ? ' is-' + kind : '');
    }

    function stopTitleFlash() {
        if (titleFlashTimer) {
            clearInterval(titleFlashTimer);
            titleFlashTimer = null;
        }
        document.title = pageTitleBase;
    }

    function startTitleFlash(message) {
        stopTitleFlash();
        var on = true;
        document.title = message;
        titleFlashTimer = setInterval(function () {
            on = !on;
            document.title = on ? message : pageTitleBase;
        }, 1200);
    }

    function ensureNotifyPermission() {
        if (typeof Notification === 'undefined') {
            return;
        }
        if (Notification.permission === 'default') {
            try {
                Notification.requestPermission();
            } catch (e) {
                // ignore
            }
        }
    }

    function showDesktopNotify(title, body) {
        if (typeof Notification === 'undefined' || Notification.permission !== 'granted') {
            return;
        }
        try {
            var n = new Notification(title, {
                body: body,
                icon: '/img/favicon.svg',
                tag: 'cabinet-search-suggestions',
            });
            n.onclick = function () {
                try {
                    window.focus();
                } catch (e) {
                    // ignore
                }
                n.close();
            };
            setTimeout(function () {
                try {
                    n.close();
                } catch (e) {
                    // ignore
                }
            }, 12000);
        } catch (e) {
            // ignore
        }
    }

    function notifyCollectFinished(ok, count, elapsed) {
        var title = ok ? i18nNotify.doneTitle : i18nNotify.errorTitle;
        var body = ok
            ? i18nNotify.doneBody
                .replace(':count', String(count != null ? count : 0))
                .replace(':time', String(elapsed || '0:00'))
            : i18nNotify.errorBody;

        // Всегда мигаем title, если вкладка в фоне; иначе разово меняем на секунду.
        if (document.hidden) {
            startTitleFlash(ok ? '✓ ' + title : '✗ ' + title);
            showDesktopNotify(title, body);
        } else {
            var prev = document.title;
            document.title = (ok ? '✓ ' : '✗ ') + title;
            setTimeout(function () {
                if (!titleFlashTimer) {
                    document.title = prev;
                }
            }, 2500);
        }
    }

    window.addEventListener('focus', stopTitleFlash);
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) {
            stopTitleFlash();
        }
    });
    window.addEventListener('beforeunload', function (e) {
        if (!collectInFlight) {
            return;
        }
        e.preventDefault();
        e.returnValue = '';
    });

    function formatElapsed(ms) {
        var sec = Math.max(0, Math.floor(ms / 1000));
        var m = Math.floor(sec / 60);
        var s = sec % 60;
        return m + ':' + (s < 10 ? '0' : '') + s;
    }

    function countSeeds(raw) {
        return String(raw || '')
            .split(/\r\n|\r|\n/)
            .map(function (s) { return s.trim(); })
            .filter(Boolean).length;
    }

    function estimateVariants() {
        var n = 0;
        if (checked('mode_phrase')) n += 1;
        if (checked('mode_space')) n += 1;
        if (checked('mode_en')) n += 26;
        if (checked('mode_ru')) n += 33;
        if (checked('mode_digits')) n += 10;
        if (checked('preset_local')) n += parseInt(root.getAttribute('data-preset-local') || '5', 10) || 5;
        if (checked('preset_shopping')) n += parseInt(root.getAttribute('data-preset-shopping') || '6', 10) || 6;
        if (checked('preset_questions')) n += parseInt(root.getAttribute('data-preset-questions') || '7', 10) || 7;
        if (checked('preset_reviews')) n += parseInt(root.getAttribute('data-preset-reviews') || '3', 10) || 3;
        return Math.max(1, n);
    }

    function estimateRequests() {
        var seeds = countSeeds(document.getElementById('cabinetSsSeeds').value);
        var engines = (checked('engine_yandex') ? 1 : 0) + (checked('engine_google') ? 1 : 0);
        var depth = parseInt((document.getElementById('cabinetSsDepth') || {}).value || '1', 10) || 1;
        var variants = estimateVariants();
        var level1 = Math.max(1, seeds) * Math.max(1, engines) * variants;
        // Глубина 2+ кормит найденные подсказки обратно в очередь — грубый множитель.
        var depthFactor = depth <= 1 ? 1 : (depth === 2 ? 4.5 : 10);
        return Math.max(1, Math.round(level1 * depthFactor));
    }

    function setProgressVisible(on) {
        if (!progressEl) {
            return;
        }
        progressEl.classList.toggle('d-none', !on);
        if (!on && progressTimer) {
            clearInterval(progressTimer);
            progressTimer = null;
        }
    }

    function paintProgress(pct, title, sub) {
        var value = Math.max(0, Math.min(100, pct));
        if (progressBar) {
            progressBar.style.width = value + '%';
            progressBar.setAttribute('aria-valuenow', String(value));
        }
        if (progressTitle && title) {
            progressTitle.textContent = title;
        }
        if (progressSub && sub != null) {
            progressSub.textContent = sub;
        }
        if (progressTime) {
            progressTime.textContent = formatElapsed(Date.now() - progressStartedAt);
        }
    }

    function progressWaitHint(elapsed, estimatedReqs) {
        var depth = parseInt((document.getElementById('cabinetSsDepth') || {}).value || '1', 10) || 1;
        var hasAlpha = checked('mode_en') || checked('mode_ru') || checked('mode_digits');
        if (elapsed < 15000) {
            return '≈ ' + estimatedReqs + ' запросов · идёт сбор, подождите';
        }
        if (elapsed < 45000) {
            if (depth >= 2 && hasAlpha) {
                return 'Ещё собираем — при алфавите и глубине ' + depth + ' это нормально';
            }
            if (depth >= 2) {
                return 'Ещё собираем — при глубине ' + depth + ' это нормально';
            }
            if (hasAlpha) {
                return 'Ещё собираем — алфавит и много ключей дают длинный прогон';
            }
            if (estimatedReqs >= 200) {
                return 'Ещё собираем — большой список запросов, это нормально';
            }
            return 'Ещё собираем, подождите';
        }
        return 'Долгий прогон · сервер всё ещё опрашивает подсказки';
    }

    function startProgress(estimatedReqs) {
        var pauseMs = parseInt(root.getAttribute('data-pause-ms') || '80', 10) || 80;
        // pause + сеть ≈ 150–220 мс на запрос; при «Максимум» это легко минуты.
        progressExpectedMs = Math.max(6000, Math.round(estimatedReqs * (pauseMs + 140)));
        progressStartedAt = Date.now();
        setProgressVisible(true);
        paintProgress(
            4,
            'Сбор подсказок…',
            '≈ ' + estimatedReqs + ' запросов · при полном наборе это может занять 1–2 минуты'
        );
        if (progressTimer) {
            clearInterval(progressTimer);
        }
        progressTimer = setInterval(function () {
            var elapsed = Date.now() - progressStartedAt;
            // Асимптота к 92%: полоска живёт всё время запроса, не «зависает» на 0.
            var pct = Math.min(92, Math.round(100 * (1 - Math.exp(-elapsed / (progressExpectedMs * 0.55)))));
            paintProgress(Math.max(4, pct), 'Сбор подсказок…', progressWaitHint(elapsed, estimatedReqs));
        }, 400);
    }

    function finishProgress(ok) {
        if (progressTimer) {
            clearInterval(progressTimer);
            progressTimer = null;
        }
        paintProgress(100, ok ? 'Готово' : 'Ошибка', ok ? 'Результаты ниже' : '');
        setTimeout(function () {
            setProgressVisible(false);
        }, ok ? 400 : 900);
    }

    function checked(id) {
        var el = document.getElementById(id);
        return !!(el && el.checked);
    }

    function payload() {
        return {
            seeds: document.getElementById('cabinetSsSeeds').value,
            stop_words: document.getElementById('cabinetSsStop').value,
            mode_phrase: checked('mode_phrase'),
            mode_space: checked('mode_space'),
            mode_en: checked('mode_en'),
            mode_ru: checked('mode_ru'),
            mode_digits: checked('mode_digits'),
            preset_local: checked('preset_local'),
            preset_shopping: checked('preset_shopping'),
            preset_questions: checked('preset_questions'),
            preset_reviews: checked('preset_reviews'),
            depth: document.getElementById('cabinetSsDepth').value,
            yandex: checked('engine_yandex'),
            google: checked('engine_google'),
            yandex_lr: regionSelect
                ? (window.jQuery && window.jQuery(regionSelect).hasClass('select2-hidden-accessible')
                    ? window.jQuery(regionSelect).val()
                    : regionSelect.value)
                : '213',
            google_domain: googleDomainSelect ? googleDomainSelect.value : 'google.ru',
            google_gl: googleCountrySelect
                ? (window.jQuery && window.jQuery(googleCountrySelect).hasClass('select2-hidden-accessible')
                    ? window.jQuery(googleCountrySelect).val()
                    : googleCountrySelect.value)
                : 'ru',
            google_hl: currentGoogleHl(),
            save: canSave && checked('cabinetSsSave'),
        };
    }

    function renderResults(rows) {
        lastResults = Array.isArray(rows) ? rows : [];
        resultsBody.innerHTML = '';
        lastResults.forEach(function (row) {
            var tr = document.createElement('tr');
            tr.innerHTML =
                '<td>' + escapeHtml(row.seed || '') + '</td>' +
                '<td>' + escapeHtml(row.suggest || '') + '</td>' +
                '<td>' + escapeHtml(row.engine || '') + '</td>' +
                '<td>' + escapeHtml(String(row.level || '')) + '</td>' +
                '<td>' + escapeHtml(String(row.words || '')) + '</td>' +
                '<td>' + escapeHtml(typeLabel(row.type)) + '</td>';
            resultsBody.appendChild(tr);
        });
        resultsMeta.textContent = ' — ' + lastResults.length;
        resultsWrap.classList.toggle('d-none', lastResults.length === 0);
    }

    function escapeHtml(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        hideTooltip(submitBtn);
        submitBtn.disabled = true;
        collectInFlight = true;
        setStatus('Сбор…', 'busy');
        ensureNotifyPermission();
        startProgress(estimateRequests());

        fetch(collectUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(payload()),
        })
            .then(function (r) {
                return r.json().then(function (data) {
                    return { ok: r.ok, status: r.status, data: data };
                });
            })
            .then(function (res) {
                collectInFlight = false;
                submitBtn.disabled = false;
                var elapsed = formatElapsed(Date.now() - progressStartedAt);
                if (!res.ok) {
                    finishProgress(false);
                    setStatus((res.data && res.data.message) || 'Ошибка', 'error');
                    if (res.data) {
                        updateCounters(res.data);
                    }
                    notifyCollectFinished(false, 0, elapsed);
                    return;
                }
                finishProgress(true);
                renderResults(res.data.results || []);
                updateCounters(res.data);
                var reqs = res.data.requests != null ? res.data.requests : null;
                var count = (res.data.results || []).length;
                var msg = 'Готово: ' + count + ' подсказок, списано ' + (res.data.cost || 0);
                if (reqs != null) {
                    msg += ', запросов ' + reqs;
                }
                msg += ' · ' + elapsed;
                if (res.data.history_warning) {
                    msg += '. ' + res.data.history_warning;
                    setStatus(msg, 'error');
                } else {
                    setStatus(msg, 'ok');
                }
                notifyCollectFinished(true, count, elapsed);
            })
            .catch(function () {
                collectInFlight = false;
                submitBtn.disabled = false;
                finishProgress(false);
                setStatus('Сеть или сервер недоступны', 'error');
                notifyCollectFinished(false, 0, formatElapsed(Date.now() - progressStartedAt));
            });
    });

    clearBtn.addEventListener('click', function () {
        hideTooltip(clearBtn);
        document.getElementById('cabinetSsSeeds').value = '';
        document.getElementById('cabinetSsStop').value = '';
        renderResults([]);
        setStatus('');
    });

    exportBtn.addEventListener('click', function () {
        hideTooltip(exportBtn);
        if (!lastResults.length) {
            return;
        }
        var f = document.createElement('form');
        f.method = 'POST';
        f.action = exportUrl;
        f.style.display = 'none';
        var token = document.createElement('input');
        token.name = '_token';
        token.value = csrf;
        f.appendChild(token);
        var input = document.createElement('input');
        input.name = 'results';
        input.value = JSON.stringify(lastResults);
        f.appendChild(input);
        document.body.appendChild(f);
        f.submit();
        document.body.removeChild(f);
    });

    if (copySuggestsBtn) {
        copySuggestsBtn.addEventListener('click', function () {
            hideTooltip(copySuggestsBtn);
            if (!lastResults.length) {
                setStatus('Нет подсказок для копирования', 'error');
                return;
            }
            var lines = [];
            var seen = {};
            lastResults.forEach(function (row) {
                var s = String(row.suggest || '').trim();
                if (!s) return;
                var key = s.toLowerCase();
                if (seen[key]) return;
                seen[key] = true;
                lines.push(s);
            });
            copyText(lines.join('\n')).then(function () {
                setStatus('Скопировано подсказок: ' + lines.length, 'ok');
            }).catch(function () {
                setStatus('Не удалось скопировать в буфер', 'error');
            });
        });
    }

    root.addEventListener('click', function (e) {
        var openBtn = e.target.closest('.cabinet-ss-history-open');
        var delBtn = e.target.closest('.cabinet-ss-history-del');
        if (openBtn) {
            hideTooltip(openBtn);
        }
        if (delBtn) {
            hideTooltip(delBtn);
        }
        var tr = e.target.closest('tr[data-id]');
        if (!tr) {
            return;
        }
        var id = tr.getAttribute('data-id');

        if (openBtn) {
            setStatus('Загрузка истории…', 'busy');
            fetch(historyBase + '/' + id, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data.ok) {
                        setStatus(data.message || 'Не найдено', 'error');
                        return;
                    }
                    var params = data.item.params || {};
                    if (params.seeds) {
                        document.getElementById('cabinetSsSeeds').value = (params.seeds || []).join('\n');
                    }
                    if (params.stop_words) {
                        document.getElementById('cabinetSsStop').value = (params.stop_words || []).join('\n');
                    }
                    renderResults(data.item.results || []);
                    setStatus('История #' + id + ' открыта', 'ok');
                    resultsWrap.scrollIntoView({ behavior: 'smooth', block: 'start' });
                })
                .catch(function () {
                    setStatus('Не удалось открыть историю', 'error');
                });
        }

        if (delBtn) {
            if (!window.confirm('Удалить сохранённую проверку?')) {
                return;
            }
            fetch(historyBase + '/' + id, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.ok) {
                        tr.parentNode.removeChild(tr);
                        updateCounters(data);
                        setStatus('Удалено', 'ok');
                    }
                });
        }
    });

    (function tryOpenHistoryFromUrl() {
        var match = window.location.search.match(/(?:\?|&)history=(\d+)/);
        if (!match || !historyBase) {
            return;
        }
        var id = match[1];
        var btn = document.querySelector('tr[data-id="' + id + '"] .cabinet-ss-history-open');
        if (btn) {
            btn.click();
            return;
        }
        setStatus('Загрузка истории…', 'busy');
        fetch(historyBase + '/' + id, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.ok) {
                    setStatus(data.message || 'Не найдено', 'error');
                    return;
                }
                var params = data.item.params || {};
                if (params.seeds) {
                    document.getElementById('cabinetSsSeeds').value = (params.seeds || []).join('\n');
                }
                if (params.stop_words) {
                    document.getElementById('cabinetSsStop').value = (params.stop_words || []).join('\n');
                }
                renderResults(data.item.results || []);
                setStatus('История #' + id + ' открыта', 'ok');
                if (resultsWrap) {
                    resultsWrap.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            })
            .catch(function () {
                setStatus('Не удалось открыть историю', 'error');
            });
    })();

    initTooltips(root);
})();
