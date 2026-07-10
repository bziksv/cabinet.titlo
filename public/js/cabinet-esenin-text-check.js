(function (window, document) {
    'use strict';

    var root = document.querySelector('.cabinet-esenin-page');
    if (!root) {
        return;
    }

    var configEl = document.getElementById('cabinet-esenin-config');
    var config = {};
    if (configEl && configEl.textContent) {
        try {
            config = JSON.parse(configEl.textContent);
        } catch (e) {
            config = {};
        }
    }

    function labelFromAttr(el, attr, fallback) {
        if (!el) {
            return fallback;
        }
        var value = (el.getAttribute(attr) || '').trim();
        if (!value || value.indexOf('Esenin text check ') === 0) {
            return fallback;
        }
        return value;
    }

    var maxChars = config.maxChars || 20000;
    var editorRoot = root.querySelector('[data-esenin-editor]');
    var editorHostInput = root.querySelector('[data-esenin-editor-host-input]');
    var editorHostResults = root.querySelector('[data-esenin-editor-host-results]');
    var inputWrap = root.querySelector('[data-esenin-input]');
    var textEl = root.querySelector('#cabinet-esenin-text');
    var plainEl = root.querySelector('#cabinet-esenin-plain');
    var htmlSourceEl = root.querySelector('[data-esenin-html-source]');
    var htmlSourceFullEl = root.querySelector('[data-esenin-html-source-full]');
    var urlEl = root.querySelector('#cabinet-esenin-url');
    var tbclassEl = root.querySelector('#cabinet-esenin-tbclass');
    var charCountEl = root.querySelector('[data-esenin-char-count]');
    var htmlMetaEl = root.querySelector('[data-esenin-html-meta]');
    var overLimitEl = root.querySelector('[data-esenin-over-limit]');
    var submitBtn = root.querySelector('[data-esenin-submit]');
    var clearBtn = root.querySelector('[data-esenin-clear]');
    var resultsWrap = root.querySelector('[data-esenin-results]');
    var emptyState = root.querySelector('[data-esenin-empty]');
    var scoreNav = root.querySelector('[data-esenin-score-nav]');
    var highlightEl = root.querySelector('[data-esenin-highlight]');
    var statsEl = root.querySelector('[data-esenin-stats]');
    var paramsEl = root.querySelector('[data-esenin-params]');
    var panelTitleEl = root.querySelector('[data-esenin-panel-title]');
    var legendEl = root.querySelector('[data-esenin-legend]');
    var frequencyListsEl = root.querySelector('[data-esenin-frequency-lists]');

    var modes = config.modes || {};
    var charsTextLabel = labelFromAttr(charCountEl, 'data-label-text', 'симв. текста');
    var charsHtmlLabel = labelFromAttr(htmlMetaEl, 'data-label-html', 'симв. HTML');
    var maxVersions = config.maxVersions || 3;
    var autosaveDebounceMs = config.autosaveDebounceMs || 2500;
    var saveUrl = (config.urls && config.urls.save) || '/esenin-text-check/save';
    var sessionBaseUrl = (config.urls && config.urls.session) || '/esenin-text-check/sessions';
    var sessionsListUrl = (config.urls && config.urls.sessions) || sessionBaseUrl;
    var SESSION_STORAGE_KEY = 'cabinet_esenin_last_session_id';

    var taskNameEl = root.querySelector('[data-esenin-task-name]');
    var versionTabsEl = root.querySelector('[data-esenin-version-tabs]');
    var sessionLabelEl = root.querySelector('[data-esenin-session-label]');
    var sessionsMenuEl = root.querySelector('[data-esenin-sessions-menu]');
    var sessionsWrapEl = root.querySelector('[data-esenin-sessions-wrap]');
    var hintsEl = root.querySelector('[data-esenin-hints]');
    var hintsBodyEl = root.querySelector('[data-esenin-hints-body]');
    var autosaveStatusEl = root.querySelector('[data-esenin-autosave-status]');
    var staleBannerEl = root.querySelector('[data-esenin-stale-banner]');
    var providersBarEl = root.querySelector('[data-esenin-providers-bar]');
    var recheckBtn = root.querySelector('[data-esenin-recheck]');
    var sharePanelEl = root.querySelector('[data-esenin-public-share]');
    var shareUrlEl = root.querySelector('[data-esenin-share-url]');
    var shareCopyEl = root.querySelector('[data-esenin-share-copy]');
    var shareCreateEl = root.querySelector('[data-esenin-share-create]');
    var shareRevokeEl = root.querySelector('[data-esenin-share-revoke]');
    var shareExpiresEl = root.querySelector('[data-esenin-share-expires]');
    var shareTtlEl = root.querySelector('[data-esenin-share-ttl]');
    var shareUnavailableEl = root.querySelector('[data-esenin-share-unavailable]');

    var sessionId = null;
    var sessionVersions = [];
    var activeVersionId = null;
    var resultsStale = false;
    var autosaveTimer = null;
    var autosaveInFlight = false;
    var suppressAutosave = false;
    var suppressHighlightSync = false;
    var sessionsAvailable = config.sessionsAvailable !== false;
    var publicShareAvailable = config.publicShareAvailable !== false;
    var analyzerVersion = Number(config.analyzerVersion || 1);
    var shareLabels = config.shareLabels || {};

    var activeSource = 'text';
    var activeEditorView = 'split';
    var lastResult = null;
    var activeBlock = 'risk';
    var ckEditor = null;
    var codeMirrorSplit = null;
    var codeMirrorFull = null;
    var syncingFromSource = false;
    var syncingEditors = false;
    var syncToVisualTimer = null;

    function debounce(fn, ms) {
        var timer;
        return function () {
            var args = arguments;
            var ctx = this;
            clearTimeout(timer);
            timer = setTimeout(function () {
                fn.apply(ctx, args);
            }, ms);
        };
    }

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function postJson(url, payload) {
        if (typeof window.axios !== 'undefined') {
            return window.axios.post(url, payload, {
                headers: {
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest'
                }
            }).then(function (response) {
                return response.data;
            });
        }

        return new Promise(function (resolve, reject) {
            if (typeof window.jQuery === 'undefined') {
                reject(new Error('HTTP client unavailable'));
                return;
            }

            window.jQuery.ajax({
                url: url,
                method: 'POST',
                data: Object.assign({ _token: csrfToken() }, payload || {}),
                dataType: 'json'
            }).done(resolve).fail(function (xhr) {
                reject(xhr);
            });
        });
    }

    function getJson(url) {
        if (typeof window.axios !== 'undefined') {
            return window.axios.get(url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function (response) {
                return response.data;
            });
        }

        return new Promise(function (resolve, reject) {
            if (typeof window.jQuery === 'undefined') {
                reject(new Error('HTTP client unavailable'));
                return;
            }

            window.jQuery.getJSON(url).done(resolve).fail(function (xhr) {
                reject(xhr);
            });
        });
    }

    function taskNameValue() {
        return taskNameEl ? taskNameEl.value.trim() : '';
    }

    function setAutosaveStatus(text, kind) {
        if (!autosaveStatusEl) {
            return;
        }
        autosaveStatusEl.textContent = text || '';
        autosaveStatusEl.classList.remove('text-success', 'text-danger', 'text-muted', 'text-warning');
        if (kind) {
            autosaveStatusEl.classList.add('text-' + kind);
        }
    }

    function isResultOutdated(result) {
        if (!result) {
            return false;
        }
        var saved = Number(result.analyzer_version || 0);
        return saved > 0 && saved < analyzerVersion;
    }

    function updateStaleBanner() {
        if (staleBannerEl) {
            staleBannerEl.classList.toggle('d-none', !resultsStale || !lastResult);
        }
        if (recheckBtn) {
            recheckBtn.classList.toggle('d-none', !lastResult);
        }
    }

    function renderProvidersBar(result) {
        if (!config.showProvidersBar || !providersBarEl || !result) {
            return;
        }

        var providers = result.providers || {};
        var parts = [];
        var lt = providers.languagetool || {};
        var tg = providers.turgenev || {};
        var oc = providers.opencorpora || {};
        var learn = providers.learning || {};

        if (lt.ok) {
            parts.push('LanguageTool: ' + (lt.matches || 0) + ' замеч.');
        } else if (lt.error && lt.error !== 'skipped' && lt.error !== 'disabled') {
            parts.push('LanguageTool: недоступен');
        }

        if (tg.ok) {
            parts.push('Тургенев: риск ' + (tg.risk != null ? tg.risk : '—'));
            if (tg.report_url) {
                parts.push('<a href="' + escapeHtml(tg.report_url) + '" target="_blank" rel="noopener">полный отчёт</a>');
            }
        } else if (tg.error && tg.error !== 'skipped' && tg.error !== 'disabled') {
            parts.push('Тургенев: ' + escapeHtml(String(tg.error)));
        }

        if (oc.ok && oc.unknown) {
            parts.push('OpenCorpora: ' + oc.unknown + ' не в словаре');
        }

        if (learn.recorded) {
            parts.push('в словарь-кандидаты: +' + learn.recorded);
        }

        if (parts.length === 0) {
            providersBarEl.classList.add('d-none');
            providersBarEl.innerHTML = '';
            return;
        }

        providersBarEl.classList.remove('d-none');
        providersBarEl.innerHTML = parts.join(' · ');
    }

    function markResultsStale() {
        if (!lastResult || resultsStale) {
            updateStaleBanner();
            return;
        }
        resultsStale = true;
        updateStaleBanner();
        updateSharePanelStale();
    }

    function onTextChanged() {
        updateCharCount();
        if (suppressAutosave || activeSource === 'url') {
            return;
        }
        if (lastResult) {
            markResultsStale();
        }
        scheduleAutosave();
    }

    function extractJsonMessage(err, fallback) {
        if (err && err.response && err.response.data && err.response.data.message) {
            return err.response.data.message;
        }
        if (err && err.responseJSON && err.responseJSON.message) {
            return err.responseJSON.message;
        }
        if (err && err.message) {
            return err.message;
        }
        return fallback;
    }

    function scheduleAutosave() {
        if (suppressAutosave || activeSource === 'url') {
            return;
        }
        if (!sessionsAvailable) {
            setAutosaveStatus('Автосохранение недоступно — выполните миграцию БД', 'warning');
            return;
        }
        if (autosaveTimer) {
            clearTimeout(autosaveTimer);
        }
        setAutosaveStatus('Сохранение…', 'muted');
        autosaveTimer = setTimeout(runAutosave, autosaveDebounceMs);
    }

    function buildSavePayload() {
        var payload = {
            session_id: sessionId,
            name: taskNameValue(),
            source: activeSource
        };

        if (activeSource === 'url') {
            payload.url = urlEl ? urlEl.value.trim() : '';
            payload.tbclass = tbclassEl ? tbclassEl.value.trim() : '';
            payload.text = payload.url;
        } else {
            payload.text = activeEditorView === 'plain' ? getPlainContent() : getHtmlContent();
        }

        return payload;
    }

    function runAutosave() {
        autosaveTimer = null;
        if (activeSource === 'url') {
            setAutosaveStatus('', '');
            return;
        }

        var payload = buildSavePayload();
        if (!payload.text || !String(payload.text).trim()) {
            setAutosaveStatus('', '');
            return;
        }

        autosaveInFlight = true;
        postJson(saveUrl, payload).then(function (data) {
            if (data && data.ok) {
                applySessionPayload(data, false);
                setAutosaveStatus('Сохранено', 'success');
            } else {
                setAutosaveStatus(data && data.message ? data.message : 'Не удалось сохранить', 'danger');
            }
        }).catch(function (err) {
            setAutosaveStatus(extractJsonMessage(err, 'Не удалось сохранить'), 'danger');
        }).finally(function () {
            autosaveInFlight = false;
        });
    }

    function applySessionPayload(data, restoreEditors) {
        if (!data) {
            return;
        }

        sessionId = data.session_id || sessionId;
        sessionVersions = Array.isArray(data.versions) ? data.versions : sessionVersions;

        if (sessionId) {
            persistSessionUrl(sessionId);
            try {
                window.localStorage.setItem(SESSION_STORAGE_KEY, String(sessionId));
            } catch (e) {
                /* ignore */
            }
        }

        if (taskNameEl && data.name) {
            taskNameEl.value = data.name;
        }

        renderVersionTabs(data.active_version ? data.active_version.id : activeVersionId);

        refreshSessionsMenu();

        if (restoreEditors && data.active_version && data.active_version.text !== undefined) {
            suppressAutosave = true;
            suppressHighlightSync = true;
            syncAllEditorsFromHtml(data.active_version.text || '');
            suppressHighlightSync = false;
            suppressAutosave = false;
            updateCharCount();

            if (data.active_version.result) {
                renderResult(data.active_version.result);
                resultsStale = isResultOutdated(data.active_version.result);
                updateStaleBanner();
            }
        }
    }

    function renderVersionTabs(activeId) {
        if (!versionTabsEl) {
            return;
        }

        activeVersionId = activeId || (sessionVersions[0] ? sessionVersions[0].id : null);
        versionTabsEl.innerHTML = '';

        if (!sessionId || !sessionVersions.length) {
            versionTabsEl.classList.add('d-none');
            if (sessionLabelEl) {
                sessionLabelEl.classList.add('d-none');
            }
            return;
        }

        versionTabsEl.classList.remove('d-none');
        if (sessionLabelEl) {
            sessionLabelEl.classList.remove('d-none');
        }

        sessionVersions.slice().reverse().forEach(function (version, index) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn btn-sm cabinet-esenin-version-btn' + (version.id === activeVersionId ? ' active' : '');
            btn.setAttribute('data-esenin-version-id', String(version.id));
            var label = 'v' + (index + 1);
            if (version.created_at_label) {
                label += ' · ' + version.created_at_label;
            }
            if (version.is_check) {
                label += ' ✓';
            }
            btn.textContent = label;
            btn.title = version.is_check ? 'Версия после проверки' : 'Автосохранение';
            btn.addEventListener('click', function () {
                loadVersion(version.id);
            });
            versionTabsEl.appendChild(btn);
        });
    }

    function loadVersion(versionId) {
        if (!sessionId || !versionId) {
            return;
        }

        setAutosaveStatus('Загрузка…', 'muted');
        getJson(sessionBaseUrl + '/' + sessionId + '/versions/' + versionId).then(function (data) {
            if (!data || !data.ok || !data.version) {
                setAutosaveStatus('Не удалось загрузить', 'danger');
                return;
            }

            suppressAutosave = true;
            suppressHighlightSync = true;
            syncAllEditorsFromHtml(data.version.text || '');
            suppressHighlightSync = false;
            suppressAutosave = false;
            updateCharCount();

            if (data.version.result) {
                renderResult(data.version.result);
                resultsStale = false;
            } else if (lastResult) {
                resultsStale = true;
            }

            activeVersionId = data.version.id;
            renderVersionTabs(activeVersionId);
            updateStaleBanner();
            setAutosaveStatus('Загружено', 'success');
        }).catch(function () {
            setAutosaveStatus('Не удалось загрузить', 'danger');
        });
    }

    function unwrapHighlightMarks(container) {
        var clone = container.cloneNode(true);
        clone.querySelectorAll('mark.esenin-mark').forEach(function (mark) {
            var icon = mark.querySelector('.esenin-mark__icon');
            if (icon) {
                icon.remove();
            }
            var parent = mark.parentNode;
            while (mark.firstChild) {
                parent.insertBefore(mark.firstChild, mark);
            }
            parent.removeChild(mark);
        });
        return clone.innerHTML;
    }

    function syncFromHighlightEdit() {
        if (!highlightEl || suppressHighlightSync) {
            return;
        }
        suppressAutosave = true;
        syncAllEditorsFromHtml(unwrapHighlightMarks(highlightEl));
        suppressAutosave = false;
        markResultsStale();
        updateSharePanelStale();
    }

    function resetSessionState() {
        sessionId = null;
        sessionVersions = [];
        activeVersionId = null;
        resultsStale = false;
        if (autosaveTimer) {
            clearTimeout(autosaveTimer);
            autosaveTimer = null;
        }
        setAutosaveStatus('', '');
        renderVersionTabs(null);
        updateStaleBanner();
        if (taskNameEl) {
            taskNameEl.value = '';
        }
        try {
            window.localStorage.removeItem(SESSION_STORAGE_KEY);
        } catch (e) {
            /* ignore */
        }
        if (window.history && window.URL) {
            try {
                var url = new URL(window.location.href);
                url.searchParams.delete('session');
                window.history.replaceState({}, '', url);
            } catch (e2) {
                /* ignore */
            }
        }
    }

    function persistSessionUrl(id) {
        if (!id || !window.history || !window.URL) {
            return;
        }
        try {
            var url = new URL(window.location.href);
            url.searchParams.set('session', String(id));
            window.history.replaceState({}, '', url);
        } catch (e) {
            /* ignore */
        }
    }

    function loadSession(id, restoreEditors) {
        if (!id) {
            return;
        }

        getJson(sessionBaseUrl + '/' + id).then(function (data) {
            if (!data || !data.ok) {
                return;
            }
            applySessionPayload(data, restoreEditors !== false);
            if (data.source) {
                setSource(data.source);
            }
            if (data.source === 'url') {
                if (urlEl && data.source_url) {
                    urlEl.value = data.source_url;
                }
                if (tbclassEl && data.tbclass) {
                    tbclassEl.value = data.tbclass;
                }
            }
        }).catch(function () {
            /* ignore */
        });
    }

    function renderSessionsMenu(sessions) {
        if (!sessionsMenuEl) {
            return;
        }

        sessionsMenuEl.innerHTML = '';
        if (!sessions || !sessions.length) {
            var emptyItem = document.createElement('li');
            emptyItem.innerHTML = '<span class="dropdown-item-text small text-secondary">Нет сохранённых заданий</span>';
            sessionsMenuEl.appendChild(emptyItem);
            return;
        }

        sessions.forEach(function (session) {
            var item = document.createElement('li');
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'dropdown-item small' + (sessionId === session.id ? ' active' : '');
            var label = session.name || ('Задание #' + session.id);
            if (session.updated_at_label) {
                label += ' · ' + session.updated_at_label;
            }
            btn.textContent = label;
            btn.addEventListener('click', function () {
                loadSession(session.id, true);
            });
            item.appendChild(btn);
            sessionsMenuEl.appendChild(item);
        });
    }

    function refreshSessionsMenu() {
        if (!sessionsAvailable || !sessionsMenuEl) {
            if (sessionsWrapEl) {
                sessionsWrapEl.classList.add('d-none');
            }
            return;
        }

        if (sessionsWrapEl) {
            sessionsWrapEl.classList.remove('d-none');
        }

        getJson(sessionsListUrl).then(function (data) {
            if (data && data.ok) {
                renderSessionsMenu(data.sessions || []);
            }
        }).catch(function () {
            renderSessionsMenu([]);
        });
    }

    function tryResumeSessionFromUrl() {
        var match = window.location.search.match(/(?:\?|&)session=(\d+)/);
        if (!match) {
            return;
        }

        var id = parseInt(match[1], 10);
        if (id) {
            loadSession(id, true);
        }
    }

    function formatNumber(value) {
        return String(value).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    }

    function plainTextLength(html) {
        var div = document.createElement('div');
        div.innerHTML = html || '';
        return (div.textContent || div.innerText || '').replace(/\s+/g, ' ').trim().length;
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function showError(message) {
        if (window.toastr) {
            window.toastr.error(message);
            return;
        }
        window.alert(message);
    }

    function readCodeMirrorValue(cm, fallbackEl) {
        if (cm) {
            return cm.getValue();
        }
        return fallbackEl ? fallbackEl.value : '';
    }

    function writeCodeMirrorValue(cm, fallbackEl, html) {
        html = html || '';
        if (cm) {
            if (cm.getValue() === html) {
                return;
            }
            var scrollInfo = cm.getScrollInfo();
            var cursor = cm.getCursor();
            syncingEditors = true;
            cm.setValue(html);
            cm.setCursor(cursor);
            cm.scrollTo(scrollInfo.left, scrollInfo.top);
            syncingEditors = false;
            return;
        }
        if (fallbackEl) {
            fallbackEl.value = html;
        }
    }

    function cancelSyncToVisual() {
        if (syncToVisualTimer !== null) {
            clearTimeout(syncToVisualTimer);
            syncToVisualTimer = null;
        }
    }

    function syncFromCkEditor() {
        if (!ckEditor || syncingFromSource) {
            return;
        }
        cancelSyncToVisual();
        var html = ckEditor.getData();
        writeCodeMirrorValue(codeMirrorSplit, htmlSourceEl, html);
        writeCodeMirrorValue(codeMirrorFull, htmlSourceFullEl, html);
        syncPlainFromEditors();
        onTextChanged();
    }

    function syncFromCodeMirror(sourceCm, fallbackEl) {
        if (syncingEditors || syncingFromSource) {
            return;
        }
        var html = readCodeMirrorValue(sourceCm, fallbackEl);
        syncingFromSource = true;
        if (ckEditor) {
            ckEditor.setData(html);
        }
        if (sourceCm === codeMirrorSplit) {
            writeCodeMirrorValue(codeMirrorFull, htmlSourceFullEl, html);
        } else if (sourceCm === codeMirrorFull) {
            writeCodeMirrorValue(codeMirrorSplit, htmlSourceEl, html);
        }
        syncPlainFromEditors();
        syncingFromSource = false;
        onTextChanged();
    }

    function scheduleSyncToVisual(sourceCm, fallbackEl) {
        if (!sourceCm && !fallbackEl) {
            return;
        }
        cancelSyncToVisual();
        syncToVisualTimer = setTimeout(function () {
            syncToVisualTimer = null;
            syncFromCodeMirror(sourceCm, fallbackEl);
        }, 300);
    }

    function getHtmlContent() {
        if (activeEditorView === 'plain') {
            var plain = plainEl ? plainEl.value : '';
            if (!plain.trim()) {
                return '';
            }
            return plain
                .split(/\n{2,}/)
                .map(function (paragraph) {
                    return '<p>' + escapeHtml(paragraph.trim()).replace(/\n/g, '<br>') + '</p>';
                })
                .join('');
        }

        if (activeEditorView === 'html') {
            return readCodeMirrorValue(codeMirrorFull, htmlSourceFullEl);
        }

        var ckHtml = ckEditor ? ckEditor.getData() : '';
        var splitHtml = readCodeMirrorValue(codeMirrorSplit, htmlSourceEl);
        if (!isEditorHtmlEmpty(ckHtml)) {
            return ckHtml;
        }
        if (splitHtml && splitHtml.trim()) {
            return splitHtml;
        }

        if (ckEditor) {
            return ckHtml;
        }

        return splitHtml;
    }

    function getPlainContent() {
        if (activeEditorView === 'plain') {
            return plainEl ? plainEl.value : '';
        }
        return plainTextLength(getHtmlContent()) > 0
            ? (function () {
                var div = document.createElement('div');
                div.innerHTML = getHtmlContent();
                return (div.textContent || div.innerText || '').trim();
            }())
            : '';
    }

    function syncAllEditorsFromHtml(html) {
        if (syncingEditors) {
            return;
        }
        cancelSyncToVisual();
        syncingEditors = true;
        html = html || '';

        if (ckEditor && ckEditor.getData() !== html) {
            syncingFromSource = true;
            ckEditor.setData(html);
            syncingFromSource = false;
        }

        writeCodeMirrorValue(codeMirrorSplit, htmlSourceEl, html);
        writeCodeMirrorValue(codeMirrorFull, htmlSourceFullEl, html);

        if (plainEl) {
            var div = document.createElement('div');
            div.innerHTML = html;
            plainEl.value = (div.textContent || div.innerText || '').trim();
        }

        syncingEditors = false;
        onTextChanged();
    }

    function syncPlainFromEditors() {
        if (!plainEl || activeEditorView === 'plain') {
            return;
        }
        var div = document.createElement('div');
        div.innerHTML = getHtmlContent();
        plainEl.value = (div.textContent || div.innerText || '').trim();
    }

    function updateCharCount() {
        if (!charCountEl) {
            return;
        }

        var textLen = activeEditorView === 'plain'
            ? (plainEl ? plainEl.value.length : 0)
            : plainTextLength(getHtmlContent());
        var htmlLen = activeEditorView === 'plain' ? 0 : getHtmlContent().length;

        charCountEl.textContent = formatNumber(textLen) + ' / ' + formatNumber(maxChars) + ' ' + charsTextLabel;

        if (htmlMetaEl) {
            if (activeEditorView === 'plain' || htmlLen === 0) {
                htmlMetaEl.textContent = '';
            } else {
                htmlMetaEl.textContent = '· ' + formatNumber(htmlLen) + ' ' + charsHtmlLabel;
            }
        }

        if (overLimitEl) {
            overLimitEl.classList.toggle('d-none', textLen <= maxChars);
        }
    }

    function setSource(source) {
        activeSource = source;
        root.querySelectorAll('[data-esenin-source]').forEach(function (btn) {
            var isActive = btn.getAttribute('data-esenin-source') === source;
            btn.classList.toggle('active', isActive);
            btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });
        root.querySelectorAll('[data-esenin-panel]').forEach(function (panel) {
            panel.classList.toggle('d-none', panel.getAttribute('data-esenin-panel') !== source);
        });
    }

    function setEditorView(view) {
        activeEditorView = view;
        cancelSyncToVisual();
        editorRoot.querySelectorAll('[data-esenin-editor-view]').forEach(function (btn) {
            var isActive = btn.getAttribute('data-esenin-editor-view') === view;
            btn.classList.toggle('active', isActive);
            btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });
        editorRoot.querySelectorAll('[data-esenin-editor-panel]').forEach(function (panel) {
            panel.classList.toggle('d-none', panel.getAttribute('data-esenin-editor-panel') !== view);
        });

        if (view === 'html') {
            writeCodeMirrorValue(codeMirrorFull, htmlSourceFullEl, getHtmlContent());
        } else if (view === 'plain') {
            syncPlainFromEditors();
        } else if (view === 'split') {
            syncAllEditorsFromHtml(getHtmlContent());
        }

        refreshCodeMirrors();
        updateCharCount();
    }

    function initCodeMirror(textarea) {
        if (!textarea || typeof window.CodeMirror === 'undefined') {
            return null;
        }

        return window.CodeMirror.fromTextArea(textarea, {
            mode: 'htmlmixed',
            theme: 'neo',
            lineNumbers: true,
            lineWrapping: true,
            indentUnit: 2,
            tabSize: 2,
            indentWithTabs: false,
            matchBrackets: true,
            autoCloseBrackets: true,
            autoCloseTags: true,
            matchTags: { bothTags: true },
            foldGutter: true,
            gutters: ['CodeMirror-linenumbers', 'CodeMirror-foldgutter'],
            styleActiveLine: true,
            autoRefresh: true,
        });
    }

    function refreshCodeMirror(cm, minHeight) {
        if (!cm) {
            return;
        }
        var pane = cm.getWrapperElement().closest('.cabinet-esenin-pane-body, .cabinet-esenin-code-editor-wrap');
        var height = pane ? Math.max(minHeight || 280, pane.clientHeight - 12) : (minHeight || 320);
        cm.setSize('100%', height);
        cm.refresh();
    }

    function refreshCodeMirrors() {
        refreshCodeMirror(codeMirrorSplit, 280);
        refreshCodeMirror(codeMirrorFull, 360);
        if (ckEditor && typeof ckEditor.resize === 'function') {
            ckEditor.resize('100%', activeEditorView === 'split' ? 320 : 360);
        }
    }

    function initSplitLayout(split) {
        var wrap = split.closest('[data-esenin-split-wrap]');
        if (!wrap) {
            return;
        }

        var storageKey = split.getAttribute('data-esenin-layout-storage-key') || 'cabinet-esenin-editor-layout';
        var buttons = wrap.querySelectorAll('[data-esenin-layout-mode]');
        var saved = 'side';

        try {
            saved = window.localStorage.getItem(storageKey) || 'side';
        } catch (e) {
            saved = 'side';
        }
        if (saved !== 'stacked') {
            saved = 'side';
        }

        function applyLayout(mode) {
            var stacked = mode === 'stacked';
            split.classList.toggle('cabinet-esenin-split--stacked', stacked);
            split.classList.toggle('cabinet-esenin-split--side', !stacked);
            buttons.forEach(function (btn) {
                var active = btn.getAttribute('data-esenin-layout-mode') === mode;
                btn.classList.toggle('active', active);
                btn.setAttribute('aria-pressed', active ? 'true' : 'false');
            });
            refreshCodeMirrors();
            try {
                window.localStorage.setItem(storageKey, mode);
            } catch (e) {
                /* ignore */
            }
        }

        applyLayout(saved);

        buttons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                applyLayout(btn.getAttribute('data-esenin-layout-mode') || 'side');
            });
        });
    }

    function initCopyButton(btn, getHtml) {
        if (!btn || typeof getHtml !== 'function') {
            return;
        }

        btn.addEventListener('click', function () {
            var html = getHtml();
            if (!html) {
                return;
            }

            function markCopied() {
                var original = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<i class="bi bi-check-lg me-1" aria-hidden="true"></i>' + (btn.getAttribute('data-esenin-copied-label') || 'Скопировано');
                setTimeout(function () {
                    btn.disabled = false;
                    btn.innerHTML = original;
                }, 2000);
            }

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(html).then(markCopied).catch(function () {
                    markCopied();
                });
            }
        });
    }

    function isEditorHtmlEmpty(html) {
        var div = document.createElement('div');
        div.innerHTML = html || '';
        return !(div.textContent || div.innerText || '').replace(/\u00a0/g, ' ').trim();
    }

    function initEditorKeyboardGuard() {
        document.addEventListener('keydown', function (e) {
            if (!(e.ctrlKey || e.metaKey) || (e.key !== 'a' && e.keyCode !== 65)) {
                return;
            }

            if (ckEditor && ckEditor.focusManager && ckEditor.focusManager.hasFocus) {
                e.preventDefault();
                e.stopImmediatePropagation();
                ckEditor.execCommand('selectAll');
                return;
            }

            if (codeMirrorSplit && codeMirrorSplit.hasFocus()) {
                e.stopImmediatePropagation();
                codeMirrorSplit.execCommand('selectAll');
                return;
            }

            if (codeMirrorFull && codeMirrorFull.hasFocus()) {
                e.stopImmediatePropagation();
                codeMirrorFull.execCommand('selectAll');
                return;
            }

            if (plainEl && document.activeElement === plainEl) {
                e.stopImmediatePropagation();
                plainEl.select();
            }
        }, true);
    }

    var tipPopoverEl = null;
    var tipShowTimer = null;
    var tipHideTimer = null;

    function hideMarkTip() {
        if (tipShowTimer) {
            clearTimeout(tipShowTimer);
            tipShowTimer = null;
        }
        if (tipPopoverEl) {
            tipPopoverEl.classList.remove('is-visible');
            tipPopoverEl.hidden = true;
        }
    }

    function positionMarkTip(mark) {
        if (!tipPopoverEl || !mark) {
            return;
        }
        var rect = mark.getBoundingClientRect();
        var top = rect.top + window.scrollY - tipPopoverEl.offsetHeight - 8;
        var left = rect.left + window.scrollX + (rect.width / 2) - (tipPopoverEl.offsetWidth / 2);
        if (top < window.scrollY + 8) {
            top = rect.bottom + window.scrollY + 8;
        }
        left = Math.max(8, Math.min(left, window.scrollX + document.documentElement.clientWidth - tipPopoverEl.offsetWidth - 8));
        tipPopoverEl.style.top = top + 'px';
        tipPopoverEl.style.left = left + 'px';
    }

    function showHintPanel(mark) {
        if (!hintsEl || !hintsBodyEl || !mark) {
            return;
        }

        var text = mark.getAttribute('data-esenin-tip') || '';
        if (!text) {
            return;
        }

        var fragment = (mark.textContent || '').replace(/!+$/g, '').trim();
        hintsBodyEl.innerHTML =
            '<p class="fw-semibold mb-1">' + escapeHtml(fragment || 'Фрагмент') + '</p>' +
            '<p class="mb-0 text-secondary">' + escapeHtml(text) + '</p>';
        hintsEl.classList.remove('d-none');
    }

    function hideHintPanel() {
        if (hintsEl) {
            hintsEl.classList.add('d-none');
        }
        if (hintsBodyEl) {
            hintsBodyEl.innerHTML = '';
        }
    }

    function initMarkTooltips(container) {
        if (!container) {
            return;
        }

        if (!tipPopoverEl) {
            tipPopoverEl = document.createElement('div');
            tipPopoverEl.className = 'esenin-tip-popover';
            tipPopoverEl.hidden = true;
            tipPopoverEl.setAttribute('role', 'tooltip');
            document.body.appendChild(tipPopoverEl);
        }

        hideMarkTip();

        container.querySelectorAll('[data-esenin-tip]').forEach(function (mark) {
            mark.addEventListener('mouseenter', function () {
                if (tipHideTimer) {
                    clearTimeout(tipHideTimer);
                    tipHideTimer = null;
                }
                var text = mark.getAttribute('data-esenin-tip') || '';
                if (!text) {
                    return;
                }
                tipShowTimer = setTimeout(function () {
                    tipPopoverEl.textContent = text;
                    tipPopoverEl.hidden = false;
                    tipPopoverEl.classList.add('is-visible');
                    positionMarkTip(mark);
                }, 120);
            });

            mark.addEventListener('mouseleave', function () {
                if (tipShowTimer) {
                    clearTimeout(tipShowTimer);
                    tipShowTimer = null;
                }
                tipHideTimer = setTimeout(hideMarkTip, 80);
            });

            mark.addEventListener('click', function (event) {
                event.preventDefault();
                showHintPanel(mark);
            });
        });
    }

    function initEditor() {
        initEditorKeyboardGuard();

        if (!editorRoot || !textEl) {
            return;
        }

        codeMirrorSplit = initCodeMirror(htmlSourceEl);
        codeMirrorFull = initCodeMirror(htmlSourceFullEl);

        if (typeof window.jQuery !== 'undefined' && window.jQuery.fn.ckeditor && typeof window.CKEDITOR !== 'undefined') {
            window.jQuery(textEl).ckeditor({
                language: 'ru',
                height: 320,
                toolbar: [
                    { name: 'basicstyles', items: ['Bold', 'Italic', 'Underline', 'Strike'] },
                    { name: 'paragraph', items: ['NumberedList', 'BulletedList', '-', 'Outdent', 'Indent'] },
                    { name: 'links', items: ['Link', 'Unlink'] },
                    { name: 'insert', items: ['Table', 'HorizontalRule'] },
                    { name: 'styles', items: ['Format'] },
                ],
            });

            ckEditor = window.CKEDITOR.instances['cabinet-esenin-text'];

            if (ckEditor) {
                ckEditor.on('instanceReady', function () {
                    syncAllEditorsFromHtml(ckEditor.getData());
                    var split = editorRoot.querySelector('[data-esenin-split-editor]');
                    if (split) {
                        initSplitLayout(split);
                    }
                    refreshCodeMirrors();
                });

                ckEditor.on('change', syncFromCkEditor);

                ckEditor.on('paste', function () {
                    setTimeout(syncFromCkEditor, 0);
                });

                ckEditor.on('contentDom', function () {
                    var editable = ckEditor.editable();
                    if (!editable) {
                        return;
                    }

                    editable.attachListener(editable, 'keydown', function (evt) {
                        var domEvent = evt.data.$;
                        var key = evt.data.getKey();
                        var isSelectAll = (domEvent.ctrlKey || domEvent.metaKey) && (key === 65 || domEvent.keyCode === 65);
                        var isDelete = key === 8 || key === 46;

                        if (isSelectAll) {
                            evt.stop();
                            cancelSyncToVisual();
                            ckEditor.execCommand('selectAll');
                            return false;
                        }

                        if (isDelete) {
                            cancelSyncToVisual();
                        }
                    }, null, null, 1);

                    editable.attachListener(editable, 'keyup', function (evt) {
                        var key = evt.data.getKey();
                        if (key === 8 || key === 46) {
                            if (isEditorHtmlEmpty(ckEditor.getData())) {
                                syncingFromSource = true;
                                ckEditor.setData('');
                                syncingFromSource = false;
                            }
                            syncFromCkEditor();
                        }
                    }, null, null, 999);
                });
            }
        }

        if (codeMirrorSplit) {
            codeMirrorSplit.on('change', function () {
                if (syncingEditors) {
                    return;
                }
                scheduleSyncToVisual(codeMirrorSplit, htmlSourceEl);
            });
            codeMirrorSplit.on('blur', function () {
                syncFromCodeMirror(codeMirrorSplit, htmlSourceEl);
            });
        } else if (htmlSourceEl) {
            htmlSourceEl.addEventListener('input', function () {
                if (syncingEditors) {
                    return;
                }
                scheduleSyncToVisual(null, htmlSourceEl);
            });
            htmlSourceEl.addEventListener('blur', function () {
                syncFromCodeMirror(null, htmlSourceEl);
            });
        }

        if (codeMirrorFull) {
            codeMirrorFull.on('change', function () {
                if (syncingEditors || activeEditorView !== 'html') {
                    return;
                }
                scheduleSyncToVisual(codeMirrorFull, htmlSourceFullEl);
            });
            codeMirrorFull.on('blur', function () {
                if (activeEditorView === 'html') {
                    syncFromCodeMirror(codeMirrorFull, htmlSourceFullEl);
                }
            });
        } else if (htmlSourceFullEl) {
            htmlSourceFullEl.addEventListener('input', function () {
                if (activeEditorView === 'html') {
                    scheduleSyncToVisual(null, htmlSourceFullEl);
                }
            });
            htmlSourceFullEl.addEventListener('blur', function () {
                if (activeEditorView === 'html') {
                    syncFromCodeMirror(null, htmlSourceFullEl);
                }
            });
        }

        if (plainEl) {
            plainEl.addEventListener('input', onTextChanged);
        }

        editorRoot.querySelectorAll('[data-esenin-editor-view]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var nextView = btn.getAttribute('data-esenin-editor-view') || 'split';
                if (nextView !== activeEditorView) {
                    if (activeEditorView !== 'plain' && nextView === 'plain') {
                        syncPlainFromEditors();
                    } else if (activeEditorView === 'plain' && nextView !== 'plain') {
                        syncAllEditorsFromHtml(getHtmlContent());
                    } else if (nextView === 'html') {
                        writeCodeMirrorValue(codeMirrorFull, htmlSourceFullEl, getHtmlContent());
                    }
                }
                setEditorView(nextView);
            });
        });

        initCopyButton(editorRoot.querySelector('[data-esenin-copy-html]'), function () {
            return readCodeMirrorValue(codeMirrorSplit, htmlSourceEl) || (ckEditor ? ckEditor.getData() : '');
        });
        initCopyButton(editorRoot.querySelector('[data-esenin-copy-html-full]'), function () {
            return readCodeMirrorValue(codeMirrorFull, htmlSourceFullEl);
        });

        window.addEventListener('resize', debounce(refreshCodeMirrors, 150));
    }

    function levelClass(score) {
        if (score >= 13) {
            return 'text-bg-danger';
        }
        if (score >= 8) {
            return 'text-bg-warning';
        }
        if (score >= 5) {
            return 'text-bg-info';
        }
        return 'text-bg-success';
    }

    var blockLegends = {
        risk: 'Цветом показаны все найденные проблемы. Красный «!» у фрагмента — наведите или нажмите, чтобы прочитать подсказку.',
        frequency: 'Фиолетовым — частые слова и фразы. Справа таблица «Слова» и «Словосочетания». Наведите на «!» — подробности.',
        style: 'Зелёным — возможные стилистические проблемы, жёлтым — почти наверняка стоит править. Нажмите на фрагмент — подсказка справа.',
        keywords: 'Синим — повторяющиеся SEO-фразы (3+ слова). Наведите на «!» — как разбавить текст.',
        formality: 'Фиолетовым — стоп-слова, светлее — общие «пустые» слова. Наведите на «!» — что изменить.',
        readability: 'Бирюзовым — длинные предложения, зелёным — длинные слова. Нажмите фрагмент — подсказка справа.'
    };

    function renderFrequencyLists(result) {
        if (!frequencyListsEl) {
            return;
        }

        var lists = result.frequency_lists || {};
        var words = lists.words || [];
        var phrases = lists.phrases || [];
        var wordsPanel = frequencyListsEl.querySelector('[data-esenin-frequency-panel="words"]');
        var phrasesPanel = frequencyListsEl.querySelector('[data-esenin-frequency-panel="phrases"]');

        function renderTable(rows, type) {
            if (!rows.length) {
                return '<p class="small text-secondary mb-0">Нет данных</p>';
            }

            var html = '<div class="table-responsive"><table class="table table-sm table-hover mb-0 cabinet-esenin-frequency-table"><thead><tr>' +
                '<th>' + (type === 'words' ? 'Слово' : 'Фраза') + '</th>' +
                '<th class="text-end">Кол-во</th><th class="text-end">%</th></tr></thead><tbody>';

            rows.forEach(function (row) {
                var label = type === 'words' ? row.word : row.phrase;
                var flagged = row.flagged ? ' cabinet-esenin-frequency-table__row--flagged' : '';
                html += '<tr class="cabinet-esenin-frequency-table__row' + flagged + '">' +
                    '<td class="small">' + escapeHtml(label) +
                    (row.flagged ? ' <span class="badge text-bg-danger rounded-pill esenin-flag-badge">!</span>' : '') +
                    '</td>' +
                    '<td class="text-end small">' + escapeHtml(String(row.count)) + '</td>' +
                    '<td class="text-end small">' + escapeHtml(String(row.percent)) + '</td></tr>';
            });

            return html + '</tbody></table></div>';
        }

        if (wordsPanel) {
            wordsPanel.innerHTML = renderTable(words, 'words');
        }
        if (phrasesPanel) {
            phrasesPanel.innerHTML = renderTable(phrases, 'phrases');
        }

        frequencyListsEl.querySelectorAll('[data-esenin-frequency-tab]').forEach(function (btn) {
            btn.onclick = function () {
                var tab = btn.getAttribute('data-esenin-frequency-tab') || 'words';
                frequencyListsEl.querySelectorAll('[data-esenin-frequency-tab]').forEach(function (item) {
                    item.classList.toggle('active', item === btn);
                });
                frequencyListsEl.querySelectorAll('[data-esenin-frequency-panel]').forEach(function (panel) {
                    panel.classList.toggle('d-none', panel.getAttribute('data-esenin-frequency-panel') !== tab);
                });
            };
        });
    }

    function renderLegend(block) {
        if (!legendEl) {
            return;
        }
        legendEl.textContent = blockLegends[block] || blockLegends.risk;
        legendEl.classList.remove('d-none');
    }

    function updateSharePanel(share) {
        if (!sharePanelEl) {
            return;
        }

        if (!lastResult) {
            sharePanelEl.classList.add('d-none');
            return;
        }

        sharePanelEl.classList.remove('d-none');
        share = share || {};

        var backendOn = publicShareAvailable
            && share.available !== false
            && sharePanelEl.getAttribute('data-feature-available') !== '0';

        if (shareUnavailableEl) {
            shareUnavailableEl.classList.toggle('d-none', backendOn);
        }

        if (!backendOn) {
            if (shareCreateEl) shareCreateEl.disabled = true;
            if (shareCopyEl) shareCopyEl.disabled = true;
            if (shareRevokeEl) shareRevokeEl.disabled = true;
            return;
        }

        var stale = resultsStale || !!share.stale;
        var hasLink = !!share.url && !stale;

        if (shareTtlEl && share.ttl_days !== undefined && share.ttl_days !== null) {
            shareTtlEl.value = String(share.ttl_days);
        }

        if (shareUrlEl) {
            shareUrlEl.value = hasLink ? share.url : '';
        }
        if (shareCopyEl) {
            shareCopyEl.disabled = !hasLink;
        }
        if (shareRevokeEl) {
            shareRevokeEl.disabled = !hasLink;
        }
        if (shareCreateEl) {
            shareCreateEl.disabled = stale;
            shareCreateEl.innerHTML = '<i class="bi bi-link-45deg me-1" aria-hidden="true"></i>' +
                (hasLink ? escapeHtml(shareLabels.refresh || 'Обновить публичную ссылку') : escapeHtml(shareLabels.create || 'Создать публичную ссылку'));
        }
        if (shareExpiresEl) {
            if (hasLink && (share.expires_label || share.expires_at)) {
                var label = share.expires_label
                    || ((shareLabels.validUntil || 'Действует до') + ' ' + share.expires_at);
                shareExpiresEl.textContent = label;
                shareExpiresEl.classList.remove('d-none', 'text-bg-secondary');
                shareExpiresEl.classList.add('text-bg-success');
            } else {
                shareExpiresEl.classList.add('d-none');
                shareExpiresEl.classList.remove('text-bg-success');
                shareExpiresEl.classList.add('text-bg-secondary');
            }
        }
    }

    function updateSharePanelStale() {
        if (!sharePanelEl || !lastResult) {
            return;
        }
        if (resultsStale) {
            updateSharePanel({ available: true, stale: true });
        }
    }

    function buildSharePayload() {
        return {
            session_id: sessionId,
            name: taskNameValue(),
            text: getHtmlContent(),
            result: lastResult,
            result_json: JSON.stringify(lastResult || {}),
            ttl_days: shareTtlEl ? shareTtlEl.value : 30
        };
    }

    function initPublicShare() {
        if (!sharePanelEl) {
            return;
        }

        var createUrl = (config.urls && config.urls.shareCreate) || sharePanelEl.getAttribute('data-create-url');
        var revokeUrl = (config.urls && config.urls.shareRevoke) || sharePanelEl.getAttribute('data-revoke-url');

        if (shareCopyEl && shareUrlEl) {
            shareCopyEl.addEventListener('click', function () {
                if (!shareUrlEl.value) {
                    return;
                }
                shareUrlEl.select();
                shareUrlEl.setSelectionRange(0, shareUrlEl.value.length);
                try {
                    document.execCommand('copy');
                } catch (e) {
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(shareUrlEl.value);
                    }
                }
                if (shareLabels.copied) {
                    var prevHtml = shareCopyEl.innerHTML;
                    shareCopyEl.innerHTML = '<i class="bi bi-check2" aria-hidden="true"></i>';
                    setTimeout(function () {
                        shareCopyEl.innerHTML = prevHtml;
                    }, 1600);
                }
            });
        }

        if (shareCreateEl) {
            shareCreateEl.addEventListener('click', function () {
                if (!lastResult || resultsStale) {
                    return;
                }
                shareCreateEl.disabled = true;
                postJson(createUrl, buildSharePayload()).then(function (response) {
                    if (response && response.success) {
                        updateSharePanel({
                            available: true,
                            url: response.url,
                            expires_at: response.expires_at,
                            expires_label: response.expires_label,
                            ttl_days: response.ttl_days,
                            stale: false
                        });
                    } else if (response && response.message) {
                        showError(response.message);
                    }
                }).catch(function (err) {
                    showError(extractJsonMessage(err, 'Не удалось создать ссылку'));
                }).finally(function () {
                    shareCreateEl.disabled = resultsStale;
                });
            });
        }

        if (shareRevokeEl) {
            shareRevokeEl.addEventListener('click', function () {
                if (!window.confirm(shareLabels.revokeConfirm || 'Отозвать публичную ссылку?')) {
                    return;
                }
                shareRevokeEl.disabled = true;
                postJson(revokeUrl, { session_id: sessionId }).then(function (response) {
                    if (response && response.success) {
                        updateSharePanel({ available: true, stale: resultsStale });
                    }
                }).finally(function () {
                    shareRevokeEl.disabled = true;
                });
            });
        }
    }

    function refreshEditorLayout() {
        refreshCodeMirrors();
        if (ckEditor && typeof ckEditor.resize === 'function') {
            setTimeout(function () {
                ckEditor.resize('100%', 280);
            }, 60);
        }
    }

    function relocateEditor(toResults) {
        if (!editorRoot) {
            return;
        }

        var targetBody = toResults && editorHostResults
            ? editorHostResults.querySelector('.card-body')
            : null;
        var targetHost = toResults
            ? (targetBody || editorHostResults || editorHostInput)
            : (editorHostInput || root);

        if (!targetHost || editorRoot.parentElement === targetHost) {
            if (editorHostResults) {
                editorHostResults.classList.toggle('d-none', !toResults);
            }
            if (inputWrap) {
                inputWrap.classList.toggle('cabinet-esenin-input--has-results', !!toResults);
            }
            return;
        }

        targetHost.appendChild(editorRoot);

        if (editorHostResults) {
            editorHostResults.classList.toggle('d-none', !toResults);
        }
        if (inputWrap) {
            inputWrap.classList.toggle('cabinet-esenin-input--has-results', !!toResults);
        }

        refreshEditorLayout();
    }

    function updateCkeditorFloatVisibility() {
        document.body.classList.remove('esenin-hide-ck-float');
    }

    function clearResults() {
        lastResult = null;
        activeBlock = 'risk';
        resultsStale = false;
        if (resultsWrap) {
            resultsWrap.classList.add('d-none');
        }
        if (emptyState) {
            emptyState.classList.remove('d-none');
        }
        if (scoreNav) {
            scoreNav.innerHTML = '';
        }
        if (highlightEl) {
            highlightEl.innerHTML = '';
        }
        if (paramsEl) {
            paramsEl.innerHTML = '';
        }
        if (legendEl) {
            legendEl.classList.add('d-none');
            legendEl.textContent = '';
        }
        hideHintPanel();
        if (frequencyListsEl) {
            frequencyListsEl.classList.add('d-none');
        }
        if (statsEl) {
            statsEl.textContent = '';
        }
        updateSharePanel(null);
        updateStaleBanner();
        if (providersBarEl) {
            providersBarEl.classList.add('d-none');
            providersBarEl.innerHTML = '';
        }
        relocateEditor(false);
        updateCkeditorFloatVisibility();
    }

    function blockParams(result, block) {
        if (block === 'risk') {
            return result.params || [];
        }
        if (result.blocks && result.blocks[block]) {
            return result.blocks[block].params || [];
        }
        return [];
    }

    function blockScore(result, block) {
        if (block === 'risk') {
            return Number(result.risk || 0);
        }
        if (result.blocks && result.blocks[block]) {
            return Number(result.blocks[block].score || 0);
        }
        return 0;
    }

    function renderBlock(block) {
        if (!lastResult) {
            return;
        }
        activeBlock = block;

        if (scoreNav) {
            scoreNav.querySelectorAll('[data-esenin-block]').forEach(function (btn) {
                btn.classList.toggle('active', btn.getAttribute('data-esenin-block') === block);
            });
        }

        if (highlightEl) {
            suppressHighlightSync = true;
            highlightEl.innerHTML = (lastResult.highlights && lastResult.highlights[block]) || lastResult.highlighted_html || '';
            highlightEl.setAttribute('contenteditable', 'true');
            suppressHighlightSync = false;
            initMarkTooltips(highlightEl);
        }

        if (panelTitleEl) {
            panelTitleEl.textContent = block === 'risk'
                ? 'Общий риск'
                : (modes[block] || block);
        }

        if (paramsEl) {
            paramsEl.innerHTML = '';
            blockParams(lastResult, block).forEach(function (item) {
                var tr = document.createElement('tr');
                var displayValue = item.value !== undefined && item.value !== null ? item.value : '—';
                var displayScore = item.score !== undefined && item.score !== null ? item.score : 0;
                tr.innerHTML =
                    '<td class="small">' + escapeHtml(item.name || '') + '</td>' +
                    '<td class="text-end small">' + escapeHtml(String(displayValue)) + '</td>' +
                    '<td class="text-end"><span class="badge ' + (item.score > 0 ? 'text-bg-secondary' : 'text-bg-light text-dark') + '">' +
                    escapeHtml(String(displayScore)) + '</span></td>';
                paramsEl.appendChild(tr);
            });
        }

        if (frequencyListsEl) {
            if (block === 'frequency') {
                frequencyListsEl.classList.remove('d-none');
                renderFrequencyLists(lastResult);
            } else {
                frequencyListsEl.classList.add('d-none');
            }
        }

        if (hintsEl) {
            if (block === 'style' || block === 'readability') {
                if (!hintsBodyEl || !hintsBodyEl.innerHTML) {
                    hintsEl.classList.add('d-none');
                }
            } else {
                hideHintPanel();
            }
        }

        renderLegend(block);
    }

    function renderResult(result, share) {
        lastResult = result;
        resultsStale = false;
        updateStaleBanner();
        if (emptyState) {
            emptyState.classList.add('d-none');
        }
        if (resultsWrap) {
            resultsWrap.classList.remove('d-none');
        }

        if (scoreNav) {
            scoreNav.innerHTML = '';
            var navBlocks = [{ id: 'risk', label: modes.risk || 'Общий риск' }];
            (result.details || []).forEach(function (item) {
                navBlocks.push({ id: item.block, label: item.label || item.block, sum: item.sum });
            });

            navBlocks.forEach(function (item) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'cabinet-esenin-score-btn';
                btn.setAttribute('data-esenin-block', item.id);
                var score = blockScore(result, item.id);
                btn.innerHTML =
                    '<span class="cabinet-esenin-score-btn__title">' + escapeHtml(item.label) + '</span>' +
                    '<span class="cabinet-esenin-score-btn__value">' +
                        '<span>' + (item.id === 'risk' ? escapeHtml(result.level || '') : '') + '</span>' +
                        '<span class="badge cabinet-esenin-score-btn__badge ' + levelClass(score) + '">' + score + '</span>' +
                    '</span>';
                btn.addEventListener('click', function () {
                    renderBlock(item.id);
                });
                scoreNav.appendChild(btn);
            });
        }

        if (statsEl && result.stats) {
            statsEl.textContent = 'Символов: ' + formatNumber(result.stats.chars || 0) +
                ', без пробелов: ' + formatNumber(result.stats.chars_no_spaces || 0) +
                ', слов: ' + formatNumber(result.stats.words || 0);
        }

        renderBlock('risk');
        renderProvidersBar(result);
        updateSharePanel(share || { available: publicShareAvailable, stale: false });
        relocateEditor(true);
        updateCkeditorFloatVisibility();
    }

    function runCheck() {
        var payload = {
            ajax: 1,
            source: activeSource,
            mode: 'risk',
            session_id: sessionId,
            name: taskNameValue()
        };

        if (activeSource === 'url') {
            payload.url = urlEl ? urlEl.value.trim() : '';
            payload.tbclass = tbclassEl ? tbclassEl.value.trim() : '';
            if (!payload.url) {
                showError('Укажите URL страницы');
                return;
            }
        } else {
            var textContent = activeEditorView === 'plain' ? getPlainContent() : getHtmlContent();
            if (!textContent.trim()) {
                showError('Введите текст для проверки');
                return;
            }
            var textLen = activeEditorView === 'plain'
                ? textContent.length
                : plainTextLength(textContent);
            if (textLen > maxChars) {
                showError('Превышен лимит символов текста');
                return;
            }
            payload.text = textContent;
            payload.format = activeEditorView === 'plain' ? 'plain' : 'html';
        }

        root.classList.add('is-loading');
        var submitBtnOriginal = submitBtn ? submitBtn.innerHTML : '';
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Проверяем…';
        }

        function finishRequest() {
            root.classList.remove('is-loading');
            if (submitBtn) {
                submitBtn.disabled = false;
                if (submitBtnOriginal) {
                    submitBtn.innerHTML = submitBtnOriginal;
                }
            }
        }

        function handleResponse(data) {
            data = data || {};
            if (!data.ok) {
                showError(data.message || 'Ошибка проверки');
                return;
            }
            renderResult(data.result || {}, data.share);
            if (data.session) {
                applySessionPayload(data.session, false);
                setAutosaveStatus('Сохранено', 'success');
            }
        }

        function handleFailure(xhr) {
            var message = 'Не удалось выполнить проверку';
            if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
                message = xhr.responseJSON.message;
            } else if (xhr && xhr.response && xhr.response.data && xhr.response.data.message) {
                message = xhr.response.data.message;
            } else if (xhr && xhr.responseText) {
                try {
                    var parsed = JSON.parse(xhr.responseText);
                    if (parsed.message) {
                        message = parsed.message;
                    }
                } catch (e) {
                    /* ignore */
                }
            } else if (xhr && xhr.message) {
                message = xhr.message;
            }
            showError(message);
        }

        if (typeof window.axios !== 'undefined') {
            postJson('/esenin-text-check?ajax=1', payload)
                .then(function (data) {
                    handleResponse(data);
                })
                .catch(function (error) {
                    handleFailure(error && error.response ? error.response : error);
                })
                .finally(finishRequest);
            return;
        }

        if (typeof window.jQuery !== 'undefined') {
            window.jQuery.ajax({
                url: '/esenin-text-check?ajax=1',
                method: 'POST',
                data: payload,
                dataType: 'json'
            }).done(handleResponse).fail(handleFailure).always(finishRequest);
            return;
        }

        finishRequest();
        showError('Не загрузились скрипты страницы. Обновите страницу.');
    }

    root.querySelectorAll('[data-esenin-source]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            setSource(btn.getAttribute('data-esenin-source') || 'text');
        });
    });

    if (submitBtn) {
        submitBtn.addEventListener('click', runCheck);
    }

    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            cancelSyncToVisual();
            syncAllEditorsFromHtml('');
            if (plainEl) {
                plainEl.value = '';
            }
            updateCharCount();
            clearResults();
            resetSessionState();
        });
    }

    if (highlightEl) {
        highlightEl.addEventListener('input', debounce(syncFromHighlightEdit, 300));
        highlightEl.addEventListener('focus', updateCkeditorFloatVisibility);
    }

    if (recheckBtn) {
        recheckBtn.addEventListener('click', runCheck);
    }

    if (taskNameEl) {
        taskNameEl.addEventListener('input', debounce(function () {
            if (sessionId) {
                scheduleAutosave();
            }
        }, 600));
    }

    if (sessionsWrapEl) {
        sessionsWrapEl.addEventListener('show.bs.dropdown', function () {
            refreshSessionsMenu();
        });
        sessionsWrapEl.addEventListener('click', function (event) {
            if (event.target.closest('[data-esenin-sessions-toggle]')) {
                refreshSessionsMenu();
            }
        }, true);
    }

    initEditor();
    initPublicShare();
    updateCharCount();
    updateCkeditorFloatVisibility();
    refreshSessionsMenu();

    window.addEventListener('scroll', debounce(updateCkeditorFloatVisibility, 100), { passive: true });
    tryResumeSessionFromUrl();
}(window, document));
