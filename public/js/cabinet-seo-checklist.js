(function () {
    var root = document.getElementById('cabinetSeoChecklist');
    if (!root) return;

    var csrf = root.getAttribute('data-csrf') || '';
    var statusTpl = root.getAttribute('data-status-url-template') || '';
    var noteTpl = root.getAttribute('data-note-url-template') || '';
    var subtaskTpl = root.getAttribute('data-subtask-url-template') || '';
    var subtaskReorderTpl = root.getAttribute('data-subtask-reorder-url-template') || '';
    var updateTpl = root.getAttribute('data-update-url-template') || '';
    var deleteTpl = root.getAttribute('data-delete-url-template') || '';
    var timerStartTpl = root.getAttribute('data-timer-start-url-template') || '';
    var timerStopTpl = root.getAttribute('data-timer-stop-url-template') || '';
    var timeTpl = root.getAttribute('data-time-url-template') || '';
    var labelStart = root.getAttribute('data-i18n-timer-start') || 'Start timer';
    var labelStop = root.getAttribute('data-i18n-timer-stop') || 'Stop timer';
    var labelStartShort = root.getAttribute('data-i18n-timer-start-short') || 'Start';
    var labelStopShort = root.getAttribute('data-i18n-timer-stop-short') || 'Stop';
    var labelTimeByDay = root.getAttribute('data-i18n-time-by-day') || 'Time by day';
    var labelNoTime = root.getAttribute('data-i18n-no-time-logged') || 'No time logged yet';
    var commentRequired = root.getAttribute('data-i18n-comment-required') || 'Comment required';
    var chooseStatusLabel = root.getAttribute('data-i18n-choose-status') || 'Choose task status';
    var deleteConfirm = root.getAttribute('data-i18n-delete-confirm') || 'Delete?';
    var deleteSubConfirm = root.getAttribute('data-i18n-delete-sub-confirm') || deleteConfirm;
    var markReadUrl = root.getAttribute('data-mark-read-url') || '';
    var markUnreadUrl = root.getAttribute('data-mark-unread-url') || '';
    var labelMarkRead = root.getAttribute('data-i18n-mark-read') || 'Mark as read';
    var labelMarkUnread = root.getAttribute('data-i18n-mark-unread') || 'Mark note unread';
    var labelMarkUnreadShort = root.getAttribute('data-i18n-mark-unread-short') || 'Unread';
    var labelUnread = root.getAttribute('data-i18n-unread') || 'Unread';
    var pendingDeleteEl = null;

    function formatDuration(total) {
        if (window.cabinetSeoChecklistFormatDuration) {
            return window.cabinetSeoChecklistFormatDuration(total);
        }
        total = Math.max(0, Math.floor(total || 0));
        var h = Math.floor(total / 3600);
        var m = Math.floor((total % 3600) / 60);
        var s = total % 60;
        if (h > 0) {
            return h + ':' + String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
        }
        return m + ':' + String(s).padStart(2, '0');
    }

    function parseStartedAt(iso) {
        if (!iso) return null;
        var t = Date.parse(iso);
        return Number.isFinite(t) ? t : null;
    }

    function itemDisplaySeconds(itemEl) {
        var base = parseInt(itemEl.getAttribute('data-time-spent') || '0', 10) || 0;
        if (itemEl.getAttribute('data-timer-running') !== '1') return base;
        var started = parseStartedAt(itemEl.getAttribute('data-timer-started-at'));
        if (!started) return base;
        return base + Math.max(0, Math.floor((Date.now() - started) / 1000));
    }

    function findItemEl(id) {
        return root.querySelector('[data-sc-item][data-id="' + id + '"], [data-sc-subitem][data-id="' + id + '"]');
    }

    function itemTimeEl(itemEl) {
        if (!itemEl) return null;
        if (itemEl.hasAttribute('data-sc-subitem')) {
            return itemEl.querySelector('.cabinet-sc-subtask__time [data-sc-time]');
        }
        return itemEl.querySelector('.cabinet-sc-plan__meta [data-sc-time]')
            || itemEl.querySelector('.cabinet-sc-task__actions [data-sc-time]');
    }

    function itemTimerBtn(itemEl) {
        if (!itemEl) return null;
        if (itemEl.hasAttribute('data-sc-subitem')) {
            return itemEl.querySelector('.cabinet-sc-subtask__controls [data-sc-timer]')
                || itemEl.querySelector('.cabinet-sc-subtask__time [data-sc-timer]');
        }
        return itemEl.querySelector('.cabinet-sc-task__actions [data-sc-timer]')
            || itemEl.querySelector('.cabinet-sc-plan__actions [data-sc-timer]');
    }

    function applyTimerUi(itemEl, state) {
        if (!itemEl || !state) return;
        if (typeof state.time_spent_seconds === 'number') {
            itemEl.setAttribute('data-time-spent', String(state.time_spent_seconds));
        }
        var running = !!state.timer_running;
        itemEl.setAttribute('data-timer-running', running ? '1' : '0');
        itemEl.setAttribute('data-timer-started-at', running && state.timer_started_at ? state.timer_started_at : '');
        itemEl.classList.toggle('is-timing', running);

        var timeEl = itemTimeEl(itemEl);
        if (timeEl) {
            timeEl.classList.toggle('is-running', running);
            timeEl.textContent = formatDuration(
                typeof state.display_seconds === 'number' ? state.display_seconds : itemDisplaySeconds(itemEl)
            );
        }

        var btn = itemTimerBtn(itemEl);
        if (btn) {
            btn.textContent = running ? labelStopShort : labelStartShort;
            btn.setAttribute('data-tip', running ? labelStop : labelStart);
            if (btn.hasAttribute('title')) {
                btn.title = running ? labelStop : labelStart;
            }
            btn.classList.toggle('btn-danger', running);
            btn.classList.toggle('btn-outline-success', !running);
        }
    }

    function tickAllTimers() {
        root.querySelectorAll('[data-sc-item][data-timer-running="1"], [data-sc-subitem][data-timer-running="1"]').forEach(function (itemEl) {
            var timeEl = itemTimeEl(itemEl);
            if (timeEl) timeEl.textContent = formatDuration(itemDisplaySeconds(itemEl));
        });
    }

    function syncPageFromActive(active) {
        root.querySelectorAll('[data-sc-item], [data-sc-subitem]').forEach(function (itemEl) {
            var id = parseInt(itemEl.getAttribute('data-id') || '0', 10);
            if (active && id === active.item_id) {
                applyTimerUi(itemEl, {
                    time_spent_seconds: active.time_spent_seconds,
                    display_seconds: active.display_seconds,
                    timer_running: true,
                    timer_started_at: active.started_at,
                });
            } else if (itemEl.getAttribute('data-timer-running') === '1') {
                applyTimerUi(itemEl, {
                    time_spent_seconds: parseInt(itemEl.getAttribute('data-time-spent') || '0', 10) || 0,
                    display_seconds: parseInt(itemEl.getAttribute('data-time-spent') || '0', 10) || 0,
                    timer_running: false,
                    timer_started_at: null,
                });
            }
        });
    }

    var labelOnlyCreatorClose = root.getAttribute('data-i18n-only-creator-close')
        || 'Only the creator, PM or auditor can mark as done';

    function askStatusAfterStop(itemEl) {
        var select = itemEl.querySelector('[data-sc-status]');
        if (!select) return;
        var isSub = itemEl.hasAttribute('data-sc-subitem');
        var canClose = isSub
            ? itemEl.getAttribute('data-can-close') === '1'
            : (itemEl.getAttribute('data-can-approve') || root.getAttribute('data-can-approve')) === '1';
        var options = [];
        var defaultValue = 'rework';
        var hasRework = false;
        Array.prototype.forEach.call(select.options, function (opt) {
            if (opt.disabled || !opt.value || opt.value === 'todo') return;
            if ((opt.value === 'done' || opt.value === 'skip') && !canClose) return;
            options.push({ value: opt.value, label: String(opt.textContent || '').trim() });
            if (opt.value === 'rework') hasRework = true;
        });
        if (!options.length) return;
        if (!hasRework) defaultValue = options[0].value;

        if (typeof window.cabinetSeoChecklistAskStatus === 'function') {
            window.cabinetSeoChecklistAskStatus({
                options: options,
                defaultValue: defaultValue,
                onSelect: function (value, note) {
                    setStatus(itemEl, value, note ? { note: note } : undefined);
                },
            });
            return;
        }
        setStatus(itemEl, defaultValue);
    }

    function toggleTimer(itemEl) {
        var id = itemEl.getAttribute('data-id');
        var running = itemEl.getAttribute('data-timer-running') === '1';
        var url = urlFor(running ? timerStopTpl : timerStartTpl, id);
        itemEl.classList.add('is-busy');
        postJson(url, {})
            .then(function (result) {
                itemEl.classList.remove('is-busy');
                if (!result.ok) {
                    alert((result.data && result.data.message) || 'Error');
                    return;
                }
                if (result.data.stopped_item) {
                    var prev = findItemEl(result.data.stopped_item.id);
                    if (prev) applyTimerUi(prev, result.data.stopped_item);
                }
                if (result.data.item) {
                    applyTimerUi(itemEl, result.data.item);
                    if (result.data.item.status) applyItemUi(itemEl, result.data.item.status);
                }
                if (result.data.active) {
                    syncPageFromActive(result.data.active);
                    updateHeaderFromActive(result.data.active);
                } else {
                    syncPageFromActive(null);
                    if (window.cabinetSeoChecklistRemoveHeaderTimer) {
                        window.cabinetSeoChecklistRemoveHeaderTimer();
                    }
                }
                updateProgress(result.data.progress);
                refreshStageCompletion();
                if (running) {
                    askStatusAfterStop(itemEl);
                }
            })
            .catch(function () {
                itemEl.classList.remove('is-busy');
            });
    }

    function updateHeaderFromActive(active) {
        if (!active) {
            if (window.cabinetSeoChecklistRemoveHeaderTimer) {
                window.cabinetSeoChecklistRemoveHeaderTimer();
            }
            return;
        }
        var el = document.querySelector('[data-sc-header-timer]');
        if (!el) {
            // Header chip appears after full reload; force soft insert
            var nav = document.querySelector('#header-nav-bar .navbar-nav');
            if (!nav) return;
            el = document.createElement('li');
            el.className = 'nav-item d-none d-md-block cabinet-sc-header-timer-item';
            el.setAttribute('data-sc-header-timer', '');
            el.id = 'cabinet-sc-header-timer';
            el.innerHTML = ''
                + '<div class="cabinet-sc-header-timer" role="status">'
                + '<a class="cabinet-sc-header-timer__link" href="#">'
                + '<i class="bi bi-stopwatch" aria-hidden="true"></i>'
                + '<span class="cabinet-sc-header-timer__domain"></span>'
                + '<span class="cabinet-sc-header-timer__sep" aria-hidden="true">·</span>'
                + '<span class="cabinet-sc-header-timer__title"></span>'
                + '<span class="cabinet-sc-header-timer__elapsed" data-sc-header-elapsed></span>'
                + '</a>'
                + '<button type="button" class="cabinet-sc-header-timer__stop" data-sc-header-timer-stop>'
                + (root.getAttribute('data-i18n-timer-stop-short') || 'Стоп')
                + '</button></div>';
            nav.appendChild(el);
        }
        el.setAttribute('data-started-at', active.started_at || '');
        el.setAttribute('data-base-seconds', String(active.time_spent_seconds || 0));
        el.setAttribute('data-stop-url', active.stop_url || root.getAttribute('data-timer-stop-active-url') || '');
        el.setAttribute('data-csrf', csrf);
        var link = el.querySelector('.cabinet-sc-header-timer__link');
        if (link) link.setAttribute('href', active.url || '#');
        var domain = el.querySelector('.cabinet-sc-header-timer__domain');
        if (domain) domain.textContent = active.domain || '';
        var title = el.querySelector('.cabinet-sc-header-timer__title');
        if (title) title.textContent = (active.title || '').slice(0, 28);
        var elapsed = el.querySelector('[data-sc-header-elapsed]');
        if (elapsed) elapsed.textContent = formatDuration(active.display_seconds || 0);
        if (el.getAttribute('data-bound') !== '1' && window.cabinetSeoChecklistFormatDuration) {
            // re-bind stop via cloning to avoid double listeners — simplest: reload stop handler
            var stopBtn = el.querySelector('[data-sc-header-timer-stop]');
            if (stopBtn && stopBtn.getAttribute('data-page-bound') !== '1') {
                stopBtn.setAttribute('data-page-bound', '1');
                stopBtn.addEventListener('click', function () {
                    var url = el.getAttribute('data-stop-url');
                    if (!url) return;
                    stopBtn.disabled = true;
                    postJson(url, {}).then(function (result) {
                        if (!result.ok) {
                            stopBtn.disabled = false;
                            alert((result.data && result.data.message) || 'Error');
                            return;
                        }
                        if (window.cabinetSeoChecklistRemoveHeaderTimer) {
                            window.cabinetSeoChecklistRemoveHeaderTimer();
                        }
                        if (result.data && result.data.item) {
                            var target = root.querySelector('[data-sc-item][data-id="' + result.data.item.id + '"]');
                            if (target) applyTimerUi(target, result.data.item);
                        }
                        syncPageFromActive(null);
                    }).catch(function () {
                        stopBtn.disabled = false;
                    });
                });
            }
            el.setAttribute('data-bound', '1');
            window.setInterval(function () {
                var out = el.querySelector('[data-sc-header-elapsed]');
                var base = parseInt(el.getAttribute('data-base-seconds') || '0', 10) || 0;
                var started = parseStartedAt(el.getAttribute('data-started-at'));
                var sec = started ? base + Math.max(0, Math.floor((Date.now() - started) / 1000)) : base;
                if (out) out.textContent = formatDuration(sec);
            }, 1000);
        }
    }

    function urlFor(tpl, id) {
        return String(tpl || '').replace('__ID__', String(id));
    }

    function postJson(url, payload) {
        return fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify(payload || {}),
        }).then(function (r) {
            return r.json().then(function (data) {
                return { ok: r.ok && data && data.ok, status: r.status, data: data };
            });
        });
    }

    function parseIdList(raw) {
        return String(raw || '')
            .split(',')
            .map(function (id) { return parseInt(id, 10) || 0; })
            .filter(function (id) { return id > 0; });
    }

    function syncUnreadNavCount(delta) {
        delta = parseInt(delta, 10) || 0;
        if (!delta) return;
        ['[data-sc-unread-nav-count]', '[data-sc-unread-header-count]'].forEach(function (sel) {
            var el = document.querySelector(sel);
            if (!el) return;
            var cur = parseInt(el.textContent || '0', 10) || 0;
            var next = Math.max(0, cur + delta);
            el.textContent = next > 99 ? '99+' : String(next);
            if (next < 1) el.setAttribute('hidden', 'hidden');
            else el.removeAttribute('hidden');
        });
    }

    function setNotesBadge(itemEl, totalCount, unreadCount) {
        if (!itemEl) return;
        var btn = itemEl.querySelector('[data-sc-toggle-notes]');
        if (!btn) return;
        var countEl = btn.querySelector('[data-sc-notes-count]');
        totalCount = Math.max(0, parseInt(totalCount, 10) || 0);
        unreadCount = Math.max(0, parseInt(unreadCount, 10) || 0);
        itemEl.setAttribute('data-notes-count', String(totalCount));
        itemEl.setAttribute('data-unread-notes-count', String(unreadCount));
        btn.classList.toggle('has-notes', totalCount > 0);
        btn.classList.toggle('has-unread', unreadCount > 0);
        if (!countEl) return;
        countEl.classList.remove('is-empty', 'is-unread', 'is-read');
        if (totalCount < 1) {
            countEl.textContent = '';
            countEl.classList.add('is-empty');
            return;
        }
        if (unreadCount > 0) {
            countEl.textContent = String(unreadCount);
            countEl.classList.add('is-unread');
        } else {
            countEl.textContent = String(totalCount);
            countEl.classList.add('is-read');
        }
    }

    function makeNoteSideBtn(noteId, mode) {
        var isRead = mode === 'read';
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'cabinet-sc-plan__side-btn';
        btn.setAttribute(isRead ? 'data-sc-mark-note-read' : 'data-sc-mark-note-unread', '');
        btn.setAttribute('data-note-id', String(noteId));
        btn.setAttribute('aria-label', isRead ? labelMarkRead : labelMarkUnread);
        var icon = document.createElement('i');
        icon.className = isRead ? 'bi bi-flag' : 'bi bi-arrow-counterclockwise';
        icon.setAttribute('aria-hidden', 'true');
        var label = document.createElement('span');
        label.className = 'cabinet-sc-plan__side-btn-label';
        label.textContent = isRead ? labelMarkRead : labelMarkUnreadShort;
        btn.appendChild(icon);
        btn.appendChild(label);
        return btn;
    }

    function setNoteSideAction(li, mode) {
        if (!li || li.getAttribute('data-note-own') === '1') return;
        var side = li.querySelector('[data-sc-note-side]');
        if (!side) {
            side = document.createElement('div');
            side.className = 'cabinet-sc-notes-list__side';
            side.setAttribute('data-sc-note-side', '');
            li.appendChild(side);
        }
        side.innerHTML = '';
        side.appendChild(makeNoteSideBtn(li.getAttribute('data-note-id'), mode));
    }

    function markItemNotesRead(itemEl, onlyIds) {
        if (!itemEl || !markReadUrl) return;
        var allIds = parseIdList(itemEl.getAttribute('data-unread-note-ids'));
        var ids = Array.isArray(onlyIds) && onlyIds.length
            ? onlyIds.filter(function (id) { return allIds.indexOf(id) !== -1; })
            : allIds;
        if (!ids.length) return;
        var unreadBefore = ids.length;
        var remain = allIds.filter(function (id) { return ids.indexOf(id) === -1; });
        itemEl.setAttribute('data-unread-note-ids', remain.join(','));
        var total = parseInt(itemEl.getAttribute('data-notes-count') || '0', 10) || 0;
        setNotesBadge(itemEl, total, remain.length);
        var unreadBar = itemEl.querySelector('[data-sc-notes-unread-bar]');
        if (unreadBar) unreadBar.classList.toggle('d-none', remain.length < 1);
        ids.forEach(function (id) {
            var li = itemEl.querySelector('.cabinet-sc-notes-list li[data-note-id="' + id + '"]');
            if (!li) return;
            li.classList.remove('is-unread');
            li.removeAttribute('data-sc-note-unread');
            var badge = li.querySelector('.cabinet-sc-notes-list__unread-badge');
            if (badge) badge.remove();
            setNoteSideAction(li, 'unread');
        });
        syncUnreadNavCount(-unreadBefore);
        postJson(markReadUrl, { note_ids: ids }).catch(function () {});
        if (activeFilters.indexOf('unread-notes') !== -1) applyFilters();
    }

    function markItemNotesUnread(itemEl, noteIds) {
        if (!itemEl || !markUnreadUrl) return;
        var ids = Array.isArray(noteIds)
            ? noteIds.map(function (id) { return parseInt(id, 10) || 0; }).filter(function (id) { return id > 0; })
            : [];
        if (!ids.length) return;
        var allIds = parseIdList(itemEl.getAttribute('data-unread-note-ids'));
        var added = 0;
        ids.forEach(function (id) {
            if (allIds.indexOf(id) !== -1) return;
            allIds.push(id);
            added += 1;
            var li = itemEl.querySelector('.cabinet-sc-notes-list li[data-note-id="' + id + '"]');
            if (!li) return;
            li.classList.add('is-unread');
            li.setAttribute('data-sc-note-unread', '1');
            var meta = li.querySelector('.cabinet-sc-notes-list__meta');
            if (meta && !meta.querySelector('.cabinet-sc-notes-list__unread-badge')) {
                var badge = document.createElement('span');
                badge.className = 'cabinet-sc-notes-list__unread-badge';
                badge.textContent = labelUnread;
                meta.appendChild(badge);
            }
            setNoteSideAction(li, 'read');
        });
        if (!added) return;
        itemEl.setAttribute('data-unread-note-ids', allIds.join(','));
        var total = parseInt(itemEl.getAttribute('data-notes-count') || '0', 10) || 0;
        setNotesBadge(itemEl, total, allIds.length);
        var unreadBar = itemEl.querySelector('[data-sc-notes-unread-bar]');
        if (unreadBar) unreadBar.classList.remove('d-none');
        syncUnreadNavCount(added);
        postJson(markUnreadUrl, { note_ids: ids }).catch(function () {});
    }

    function getJson(url) {
        return fetch(url, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        }).then(function (r) {
            return r.json().then(function (data) {
                return { ok: r.ok && data && data.ok !== false, status: r.status, data: data };
            });
        });
    }

    function loadTimeBreakdown(itemEl) {
        var panel = itemEl.querySelector('[data-sc-time-panel]');
        var list = itemEl.querySelector('[data-sc-time-list]');
        var totalEl = itemEl.querySelector('[data-sc-time-total]');
        if (!panel || !list || !timeTpl) return;
        var id = itemEl.getAttribute('data-id');
        list.innerHTML = '<li class="text-secondary small">…</li>';
        getJson(urlFor(timeTpl, id)).then(function (result) {
            if (!result.ok) {
                list.innerHTML = '<li class="text-secondary small">' + labelNoTime + '</li>';
                return;
            }
            var days = (result.data && result.data.days) || [];
            if (totalEl) {
                totalEl.textContent = (result.data && result.data.total_formatted)
                    ? (labelTimeByDay + ': ' + result.data.total_formatted)
                    : '';
            }
            if (!days.length) {
                list.innerHTML = '<li class="text-secondary small">' + labelNoTime + '</li>';
                return;
            }
            list.innerHTML = days.map(function (day) {
                return '<li><span>' + String(day.label || day.date || '') + '</span><strong>' +
                    String(day.formatted || '') + '</strong></li>';
            }).join('');
        }).catch(function () {
            list.innerHTML = '<li class="text-secondary small">' + labelNoTime + '</li>';
        });
    }

    function updateProgress(progress) {
        if (!progress) return;
        var label = root.querySelector('[data-sc-progress-label]');
        var pctEl = root.querySelector('[data-sc-progress-pct]');
        var bar = root.querySelector('[data-sc-progress-bar]');
        var pct = progress.total > 0 ? Math.round(100 * progress.done / progress.total) : 0;
        if (label) label.textContent = progress.done + '/' + progress.total;
        if (pctEl) pctEl.textContent = pct + '%';
        if (bar) bar.style.width = pct + '%';
    }

    function syncParentClosedOptions(itemEl, status) {
        if (!itemEl || itemEl.hasAttribute('data-sc-subitem')) return;
        var select = itemEl.querySelector('.cabinet-sc-task__main [data-sc-status]')
            || itemEl.querySelector('[data-sc-status]');
        if (!select) return;
        var canApprove = (itemEl.getAttribute('data-can-approve') || root.getAttribute('data-can-approve')) === '1';
        Array.prototype.forEach.call(select.options, function (opt) {
            if (opt.value !== 'done' && opt.value !== 'skip') return;
            if (status === opt.value) {
                opt.hidden = false;
                opt.disabled = false;
                return;
            }
            var allow = canApprove && status === 'review';
            opt.hidden = !allow;
            opt.disabled = !allow;
        });
    }

    function applyItemUi(itemEl, status) {
        itemEl.setAttribute('data-status', status);
        itemEl.classList.toggle('is-done', status === 'done' || status === 'skip');
        itemEl.classList.toggle('is-review', status === 'review');
        var isSub = itemEl.hasAttribute('data-sc-subitem');
        var checkbox;
        var select;
        var titleEl;
        if (isSub) {
            checkbox = itemEl.querySelector('.cabinet-sc-check--sub > [data-sc-done]');
            select = itemEl.querySelector('[data-sc-status]');
            titleEl = itemEl.querySelector('[data-sc-title]');
        } else {
            checkbox = itemEl.querySelector('.cabinet-sc-plan__meta [data-sc-done]')
                || itemEl.querySelector('.cabinet-sc-task__main .cabinet-sc-check > [data-sc-done]');
            select = itemEl.querySelector('.cabinet-sc-plan__actions [data-sc-status]')
                || itemEl.querySelector('.cabinet-sc-task__main [data-sc-status]');
            titleEl = itemEl.querySelector('.cabinet-sc-plan__task[data-sc-title]')
                || itemEl.querySelector('.cabinet-sc-task__main [data-sc-title]');
        }
        if (checkbox) checkbox.checked = status === 'done' || status === 'skip';
        if (select && select.value !== status) select.value = status;
        if (titleEl) {
            titleEl.classList.toggle('is-done-text', status === 'done' || status === 'skip');
            titleEl.classList.toggle('is-review-text', status === 'review');
        }
        var hint = isSub
            ? itemEl.querySelector('[data-sc-review-hint]')
            : itemEl.querySelector('.cabinet-sc-plan__meta [data-sc-review-hint], .cabinet-sc-plan__row > [data-sc-review-hint]');
        if (hint) hint.hidden = status !== 'review';
        if (!isSub) {
            syncParentClosedOptions(itemEl, status);
        }
    }

    function applyAuditMeta(itemEl, audit) {
        if (!itemEl || !audit) return;
        var box = itemEl.hasAttribute('data-sc-subitem')
            ? itemEl.querySelector(':scope > [data-sc-audit], [data-sc-audit]')
            : itemEl.querySelector('.cabinet-sc-plan__info [data-sc-audit]');
        if (!box) return;
        var created = box.querySelector('[data-sc-audit-created]');
        var status = box.querySelector('[data-sc-audit-status]');
        var done = box.querySelector('[data-sc-audit-done]');
        if (created) {
            if (audit.created_label) {
                created.textContent = audit.created_label;
                created.hidden = false;
            } else {
                created.textContent = '';
                created.hidden = true;
            }
        }
        if (status) {
            if (audit.status_label) {
                status.textContent = audit.status_label;
                status.hidden = false;
            } else {
                status.textContent = '';
                status.hidden = true;
            }
        }
        if (done) {
            if (audit.done_label) {
                done.textContent = audit.done_label;
                done.hidden = false;
            } else {
                done.textContent = '';
                done.hidden = true;
            }
        }
        var hasCreated = !!(created && !created.hidden && created.textContent);
        var hasStatus = !!(status && !status.hidden && status.textContent);
        var hasDone = !!(done && !done.hidden && done.textContent);
        box.hidden = !hasCreated && !hasStatus && !hasDone;
    }

    function setStatus(itemEl, status, extras) {
        var id = itemEl.getAttribute('data-id');
        var isSub = itemEl.hasAttribute('data-sc-subitem');
        var current = itemEl.getAttribute('data-status') || 'todo';
        var labelSendReviewFirst = root.getAttribute('data-i18n-send-review-first')
            || 'Send the task for review first';
        var labelCloseSubsFirst = root.getAttribute('data-i18n-close-subs-first')
            || 'Close open checklist items first';

        if (!isSub && (status === 'done' || status === 'skip')) {
            if (current !== 'review') {
                applyItemUi(itemEl, current);
                alert(labelSendReviewFirst);
                return;
            }
            var openSubs = itemEl.querySelectorAll('[data-sc-subitem]:not(.is-done)');
            if (openSubs.length) {
                applyItemUi(itemEl, current);
                Array.prototype.forEach.call(openSubs, function (sub) {
                    sub.classList.add('is-need-close');
                });
                itemEl.classList.add('is-status-blocked');
                alert(labelCloseSubsFirst.replace(':count', String(openSubs.length)));
                setTimeout(function () {
                    itemEl.classList.remove('is-status-blocked');
                    itemEl.querySelectorAll('.is-need-close').forEach(function (s) {
                        s.classList.remove('is-need-close');
                    });
                }, 5000);
                return;
            }
        }

        var payload = { status: status };
        if (status === 'skip' || status === 'blocked' || status === 'clarify') {
            var note = extras && extras.note !== undefined ? extras.note : undefined;
            if (note === undefined) {
                note = window.prompt(commentRequired);
                if (note === null) {
                    applyItemUi(itemEl, itemEl.getAttribute('data-status') || 'todo');
                    return;
                }
            }
            note = String(note || '').trim();
            if (!note) {
                applyItemUi(itemEl, itemEl.getAttribute('data-status') || 'todo');
                return;
            }
            payload.note = note;
        }

        itemEl.classList.add('is-busy');
        postJson(urlFor(statusTpl, id), payload)
            .then(function (result) {
                itemEl.classList.remove('is-busy');
                if (!result.ok) {
                    applyItemUi(itemEl, itemEl.getAttribute('data-status') || 'todo');
                    alert((result.data && result.data.message) || 'Error');
                    return;
                }
                applyItemUi(itemEl, result.data.item.status);
                if (result.data.item.audit) {
                    applyAuditMeta(itemEl, result.data.item.audit);
                }
                if (result.data.item.time_spent_seconds !== undefined || result.data.item.timer_running !== undefined) {
                    applyTimerUi(itemEl, result.data.item);
                }
                if (result.data.active === null && window.cabinetSeoChecklistRemoveHeaderTimer) {
                    // status done may have stopped timer
                    var stillMine = itemEl.getAttribute('data-timer-running') === '1';
                    if (!stillMine) {
                        /* header may still show another task */
                    }
                }
                if (Object.prototype.hasOwnProperty.call(result.data, 'active')) {
                    if (result.data.active) updateHeaderFromActive(result.data.active);
                    else if (!document.querySelector('[data-sc-item][data-timer-running="1"]')
                        && window.cabinetSeoChecklistRemoveHeaderTimer) {
                        window.cabinetSeoChecklistRemoveHeaderTimer();
                    }
                }
                updateProgress(result.data.progress);
                refreshStageCompletion();
                applyFilters();
            })
            .catch(function () {
                itemEl.classList.remove('is-busy');
                applyItemUi(itemEl, itemEl.getAttribute('data-status') || 'todo');
            });
    }

    var myRoles = String(root.getAttribute('data-my-roles') || '')
        .split(',')
        .map(function (r) { return r.trim(); })
        .filter(Boolean);

    /** Активные фильтры (AND). Пусто / ['all'] = все задачи. */
    var activeFilters = [];
    var currentSearch = '';
    var prefsKey = 'seoChecklistShowPrefs:' + (root.getAttribute('data-project-id') || '0');

    function loadPrefs() {
        try {
            return JSON.parse(sessionStorage.getItem(prefsKey) || '{}') || {};
        } catch (e) {
            return {};
        }
    }

    function normalizeFilters(raw) {
        if (Array.isArray(raw)) {
            return raw.map(String).filter(function (f) { return f && f !== 'all'; });
        }
        if (typeof raw === 'string' && raw && raw !== 'all') {
            return [raw];
        }
        return [];
    }

    function savePrefs() {
        try {
            sessionStorage.setItem(prefsKey, JSON.stringify({
                filters: activeFilters.slice(),
                filter: activeFilters[0] || 'all', // legacy
                search: currentSearch,
            }));
        } catch (e) { /* ignore */ }
    }

    function isMineRole(role) {
        return myRoles.indexOf(role) !== -1
            || role === 'shared'
            || role === 'any'
            || (myRoles.indexOf('owner') !== -1 && role === 'owner')
            || (myRoles.indexOf('pm') !== -1 && role === 'pm');
    }

    function refreshStageCompletion() {
        root.querySelectorAll('[data-sc-stage]').forEach(function (stage) {
            var items = stage.querySelectorAll('[data-sc-item]');
            var total = items.length;
            var done = 0;
            items.forEach(function (item) {
                var status = item.getAttribute('data-status') || 'todo';
                if (status === 'done' || status === 'skip') done++;
            });
            var complete = total > 0 && done >= total;
            stage.setAttribute('data-complete', complete ? '1' : '0');
            var meta = stage.querySelector('.cabinet-sc-stage__meta');
            var bar = stage.querySelector('.cabinet-sc-stage__bar > span');
            var pct = total > 0 ? Math.round(100 * done / total) : 0;
            if (meta) meta.textContent = done + '/' + total + ' · ' + pct + '%';
            if (bar) bar.style.width = pct + '%';
            var key = stage.getAttribute('data-sc-stage-key');
            if (key) {
                var nav = root.querySelector('[data-sc-stage-jump="' + key + '"]');
                if (nav) {
                    nav.classList.toggle('is-complete', complete);
                    var navMeta = nav.querySelector('.cabinet-sc-stage-nav__meta');
                    if (navMeta) navMeta.textContent = pct + '%';
                }
            }
        });
    }

    function applyFilters() {
        var filters = activeFilters.slice();
        var hasFacet = filters.length > 0;

        root.querySelectorAll('[data-sc-filter]').forEach(function (btn) {
            var key = btn.getAttribute('data-sc-filter');
            var on = key === 'all' ? !hasFacet : filters.indexOf(key) !== -1;
            btn.classList.toggle('active', on);
            btn.setAttribute('aria-pressed', on ? 'true' : 'false');
        });

        root.querySelectorAll('[data-sc-item]').forEach(function (item) {
            var status = item.getAttribute('data-status') || 'todo';
            var important = item.getAttribute('data-important') === '1';
            var role = item.getAttribute('data-role') || 'any';
            var open = status === 'todo' || status === 'doing' || status === 'rework'
                || status === 'blocked' || status === 'clarify' || status === 'review';
            var overdue = item.getAttribute('data-overdue') === '1';
            var dueSoon = item.getAttribute('data-due-soon') === '1';
            var show = true;

            if (hasFacet) {
                var wantDone = filters.indexOf('done') !== -1;
                var wantReview = filters.indexOf('review') !== -1;
                var hideReview = filters.indexOf('no-review') !== -1;
                var isDone = status === 'done' || status === 'skip';
                var isReview = status === 'review';

                if (filters.indexOf('open') !== -1 && !open) show = false;
                if (show && wantDone && !isDone) show = false;
                if (show && wantReview && !isReview) show = false;
                if (show && !wantDone && !wantReview && hideReview && isReview) show = false;
                if (show && filters.indexOf('unread-notes') !== -1) {
                    var unreadNotes = (parseInt(item.getAttribute('data-unread-notes-count') || '0', 10) || 0) > 0;
                    if (!unreadNotes) show = false;
                }
                if (show && filters.indexOf('important') !== -1 && !important) show = false;
                if (show && filters.indexOf('mine') !== -1 && !isMineRole(role)) show = false;

                var dueFilters = [];
                if (filters.indexOf('overdue') !== -1) dueFilters.push('overdue');
                if (filters.indexOf('due-soon') !== -1) dueFilters.push('due-soon');
                if (show && dueFilters.length) {
                    var dueOk = false;
                    if (dueFilters.indexOf('overdue') !== -1 && overdue) dueOk = true;
                    if (dueFilters.indexOf('due-soon') !== -1 && (dueSoon || overdue)) dueOk = true;
                    if (!dueOk) show = false;
                }

                var roleFilters = filters.filter(function (f) { return f.indexOf('role:') === 0; });
                if (show && roleFilters.length) {
                    var roleOk = roleFilters.some(function (f) {
                        return role === f.slice(5);
                    });
                    if (!roleOk) show = false;
                }
            }

            if (show && currentSearch) {
                var hay = item.getAttribute('data-search') || '';
                var matcher = window.cabinetSeoChecklistSearch && window.cabinetSeoChecklistSearch.smartMatch;
                if (matcher) {
                    if (!matcher(hay, currentSearch)) show = false;
                } else if (hay.indexOf(currentSearch) === -1) {
                    show = false;
                }
            }

            item.classList.toggle('is-hidden-filter', !show);
        });

        root.querySelectorAll('[data-sc-stage]').forEach(function (stage) {
            var visible = stage.querySelectorAll('[data-sc-item]:not(.is-hidden-filter)').length;
            stage.classList.toggle('is-empty-filter', visible === 0);
        });
        savePrefs();
    }

    function toggleFilter(key) {
        key = String(key || '');
        if (!key || key === 'all') {
            activeFilters = [];
            applyFilters();
            return;
        }
        var idx = activeFilters.indexOf(key);
        if (idx === -1) {
            if (key === 'done' || key === 'review') {
                activeFilters = activeFilters.filter(function (f) {
                    return f !== 'done' && f !== 'review';
                });
            }
            if (key === 'no-review') {
                activeFilters = activeFilters.filter(function (f) { return f !== 'review'; });
            }
            if (key === 'review') {
                activeFilters = activeFilters.filter(function (f) { return f !== 'no-review'; });
            }
            activeFilters.push(key);
        } else {
            activeFilters.splice(idx, 1);
        }
        applyFilters();
    }

    root.querySelectorAll('[data-sc-filter]').forEach(function (btn) {
        btn.setAttribute('aria-pressed', 'false');
        btn.addEventListener('click', function () {
            toggleFilter(btn.getAttribute('data-sc-filter'));
        });
    });

    var taskSearch = root.querySelector('[data-sc-task-search]');
    var filtersRoot = root.querySelector('[data-sc-show-filters]');

    if (taskSearch) {
        taskSearch.addEventListener('input', function () {
            currentSearch = String(taskSearch.value || '').toLowerCase().trim();
            if (filtersRoot) {
                filtersRoot.classList.toggle('has-search-query', !!currentSearch);
            }
            applyFilters();
            if (currentSearch) {
                root.querySelectorAll('[data-sc-stage]:not(.is-empty-filter)').forEach(function (stage) {
                    stage.open = true;
                });
            }
        });
    }

    var expandBtn = root.querySelector('[data-sc-stages-expand]');
    var collapseBtn = root.querySelector('[data-sc-stages-collapse]');

    if (expandBtn) {
        expandBtn.addEventListener('click', function () {
            root.querySelectorAll('[data-sc-stage]').forEach(function (stage) {
                stage.open = true;
            });
        });
    }
    if (collapseBtn) {
        collapseBtn.addEventListener('click', function () {
            root.querySelectorAll('[data-sc-stage]').forEach(function (stage) {
                stage.open = false;
            });
        });
    }

    root.querySelectorAll('[data-sc-stage-jump]').forEach(function (link) {
        link.addEventListener('click', function (e) {
            var key = link.getAttribute('data-sc-stage-jump');
            var stage = root.querySelector('[data-sc-stage-key="' + key + '"]');
            if (!stage) return;
            e.preventDefault();
            stage.classList.remove('is-empty-filter');
            stage.open = true;
            stage.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });

    function jumpToNextOpenTask() {
        activeFilters = ['open'];
        applyFilters();
        var first = root.querySelector('[data-sc-item]:not(.is-hidden-filter)');
        if (!first) return;
        var stage = first.closest('[data-sc-stage]');
        if (stage) {
            stage.open = true;
            stage.classList.remove('is-empty-filter');
        }
        first.scrollIntoView({ behavior: 'smooth', block: 'center' });
        first.classList.add('is-flash');
        setTimeout(function () { first.classList.remove('is-flash'); }, 1600);
    }

    var continueBtn = root.querySelector('[data-sc-continue]');
    if (continueBtn) {
        continueBtn.addEventListener('click', jumpToNextOpenTask);
    }

    var prefs = loadPrefs();
    if (prefs.search && taskSearch) {
        currentSearch = String(prefs.search);
        taskSearch.value = prefs.search;
    }
    if (prefs.filters) {
        activeFilters = normalizeFilters(prefs.filters);
    } else if (prefs.filter) {
        activeFilters = normalizeFilters(prefs.filter);
    } else {
        activeFilters = ['open'];
    }
    applyFilters();

    function focusHashTarget() {
        var hash = String(window.location.hash || '');
        var match = hash.match(/^#sc-item-(\d+)$/);
        var id = match ? match[1] : '';
        if (!id) {
            try {
                var params = new URLSearchParams(window.location.search || '');
                id = String(params.get('focus') || '');
            } catch (e) {
                id = '';
            }
        }
        if (!id) return;

        var target = root.querySelector('#sc-item-' + id)
            || root.querySelector('[data-sc-item][data-id="' + id + '"]');
        if (!target) return false;

        // Сброс фильтров/поиска, если задача скрыта — иначе «непонятно где искать»
        activeFilters = [];
        currentSearch = '';
        if (taskSearch) taskSearch.value = '';
        root.querySelectorAll('[data-sc-filter]').forEach(function (btn) {
            var key = btn.getAttribute('data-sc-filter');
            btn.classList.toggle('active', key === 'all');
            btn.setAttribute('aria-pressed', key === 'all' ? 'true' : 'false');
        });
        applyFilters();

        var stage = target.closest('[data-sc-stage]');
        if (stage) {
            stage.open = true;
            stage.classList.remove('is-empty-filter');
        }

        var titleEl = target.querySelector('[data-sc-title]');
        var titleText = titleEl ? String(titleEl.textContent || '').trim() : '';
        showFocusBanner(titleText);

        function paint() {
            target.scrollIntoView({ behavior: 'smooth', block: 'center' });
            target.classList.add('is-flash', 'is-hash-target');
        }
        window.setTimeout(paint, 60);
        window.setTimeout(paint, 320);
        window.setTimeout(function () {
            target.classList.remove('is-flash');
        }, 3200);
        window.setTimeout(function () {
            target.classList.remove('is-hash-target');
            hideFocusBanner();
        }, 12000);
        return true;
    }

    function showFocusBanner(titleText) {
        var box = root.querySelector('[data-sc-focus-banner]');
        if (!box) {
            box = document.createElement('div');
            box.className = 'cabinet-sc-focus-banner';
            box.setAttribute('data-sc-focus-banner', '');
            box.innerHTML = '<strong></strong><span></span><button type="button" class="cabinet-sc-focus-banner__close" aria-label="OK">×</button>';
            root.insertBefore(box, root.firstChild);
            box.querySelector('.cabinet-sc-focus-banner__close').addEventListener('click', hideFocusBanner);
        }
        var strong = box.querySelector('strong');
        var span = box.querySelector('span');
        if (strong) strong.textContent = root.getAttribute('data-i18n-focus-banner') || 'Пункт из хроники';
        if (span) span.textContent = titleText || '';
        box.hidden = false;
    }

    function hideFocusBanner() {
        var box = root.querySelector('[data-sc-focus-banner]');
        if (box) box.hidden = true;
    }

    focusHashTarget();
    window.addEventListener('hashchange', focusHashTarget);

    function openDeleteItemModal(el) {
        pendingDeleteEl = el;
        var modalEl = document.getElementById('cabinetScDeleteItemModal');
        var isSub = el.hasAttribute('data-sc-subitem');
        var titleEl = el.querySelector('[data-sc-title]');
        var titleText = titleEl ? String(titleEl.textContent || '').trim() : '';
        if (modalEl) {
            var lead = modalEl.querySelector('[data-sc-delete-item-lead]');
            var titleOut = modalEl.querySelector('[data-sc-delete-item-title]');
            if (lead) lead.textContent = isSub ? deleteSubConfirm : deleteConfirm;
            if (titleOut) titleOut.textContent = titleText;
            if (window.bootstrap && bootstrap.Modal) {
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
                return;
            }
            if (window.jQuery) {
                window.jQuery(modalEl).modal('show');
                return;
            }
        }
        // fallback, если модалки нет
        if (window.confirm(isSub ? deleteSubConfirm : deleteConfirm)) {
            performDeleteItem(el);
        } else {
            pendingDeleteEl = null;
        }
    }

    function hideDeleteItemModal() {
        var modalEl = document.getElementById('cabinetScDeleteItemModal');
        if (!modalEl) return;
        if (window.bootstrap && bootstrap.Modal) {
            var inst = bootstrap.Modal.getInstance(modalEl);
            if (inst) inst.hide();
        } else if (window.jQuery) {
            window.jQuery(modalEl).modal('hide');
        }
    }

    function performDeleteItem(el) {
        var id = el.getAttribute('data-id');
        if (!id || !deleteTpl) return;
        el.classList.add('is-busy');
        postJson(urlFor(deleteTpl, id), {})
            .then(function (result) {
                el.classList.remove('is-busy');
                if (!result.ok) {
                    alert((result.data && result.data.message) || 'Error');
                    return;
                }
                if (el.hasAttribute('data-sc-item')) {
                    el.remove();
                    updateProgress(result.data && result.data.progress);
                    applyFilters();
                } else {
                    var parentTask = el.closest('[data-sc-item]');
                    el.remove();
                    if (parentTask) {
                        var countEl = parentTask.querySelector('[data-sc-subtasks-count]');
                        var list = parentTask.querySelector('[data-sc-subtasks]');
                        if (countEl && list) {
                            countEl.textContent = String(list.querySelectorAll('[data-sc-subitem]').length);
                        }
                    }
                }
            })
            .catch(function () {
                el.classList.remove('is-busy');
            });
    }

    function deleteItem(el) {
        if (!el || !el.getAttribute('data-id') || !deleteTpl) return;
        openDeleteItemModal(el);
    }

    (function initDeleteItemModal() {
        var modalEl = document.getElementById('cabinetScDeleteItemModal');
        if (!modalEl) return;
        var confirmBtn = modalEl.querySelector('[data-sc-delete-item-confirm]');
        if (!confirmBtn) return;
        confirmBtn.addEventListener('click', function () {
            var el = pendingDeleteEl;
            pendingDeleteEl = null;
            hideDeleteItemModal();
            if (el) performDeleteItem(el);
        });
        modalEl.addEventListener('hidden.bs.modal', function () {
            pendingDeleteEl = null;
        });
        if (window.jQuery) {
            window.jQuery(modalEl).on('hidden.bs.modal', function () {
                pendingDeleteEl = null;
            });
        }
    })();

    // Делегирование: крестик/корзина всегда ловятся, даже если bindRow не навесился
    root.addEventListener('click', function (e) {
        var markNotesReadBtn = e.target.closest('[data-sc-mark-notes-read]');
        if (markNotesReadBtn && root.contains(markNotesReadBtn)) {
            e.preventDefault();
            var markItem = markNotesReadBtn.closest('[data-sc-item]');
            if (markItem) markItemNotesRead(markItem);
            return;
        }
        var markOneNoteBtn = e.target.closest('[data-sc-mark-note-read]');
        if (markOneNoteBtn && root.contains(markOneNoteBtn)) {
            e.preventDefault();
            var oneItem = markOneNoteBtn.closest('[data-sc-item]');
            var noteId = parseInt(markOneNoteBtn.getAttribute('data-note-id') || '0', 10) || 0;
            if (oneItem && noteId > 0) markItemNotesRead(oneItem, [noteId]);
            return;
        }
        var markOneUnreadBtn = e.target.closest('[data-sc-mark-note-unread]');
        if (markOneUnreadBtn && root.contains(markOneUnreadBtn)) {
            e.preventDefault();
            var unreadItem = markOneUnreadBtn.closest('[data-sc-item]');
            var unreadNoteId = parseInt(markOneUnreadBtn.getAttribute('data-note-id') || '0', 10) || 0;
            if (unreadItem && unreadNoteId > 0) markItemNotesUnread(unreadItem, [unreadNoteId]);
            return;
        }

        var delBtn = e.target.closest('[data-sc-delete]');
        if (!delBtn || !root.contains(delBtn)) return;
        // кнопка удаления проекта — другой обработчик
        if (delBtn.hasAttribute('data-sc-delete-project')) return;
        e.preventDefault();
        e.stopPropagation();
        var el = delBtn.closest('[data-sc-subitem], [data-sc-item]');
        if (el) deleteItem(el);
    });

    function startInlineEdit(hostEl, opts) {
        if (!hostEl || hostEl.classList.contains('is-editing') || hostEl.disabled) return;
        opts = opts || {};
        var multiline = !!opts.multiline;
        var allowEmpty = !!opts.allowEmpty;
        var emptyLabel = opts.emptyLabel || '';
        var current = hostEl.getAttribute('data-raw-value');
        if (current === null || current === undefined) {
            current = hostEl.classList.contains('is-empty') ? '' : String(hostEl.textContent || '').trim();
        }
        hostEl.classList.add('is-editing');
        hostEl.setAttribute('data-raw-value', current);
        var field = document.createElement(multiline ? 'textarea' : 'input');
        if (!multiline) field.type = 'text';
        field.className = 'form-control form-control-sm';
        if (multiline) {
            field.rows = 2;
        }
        field.value = current;
        hostEl.textContent = '';
        hostEl.appendChild(field);
        field.focus();
        if (field.select) field.select();

        var saved = false;
        function restore(text, isEmpty) {
            hostEl.classList.remove('is-editing');
            hostEl.textContent = isEmpty && emptyLabel ? emptyLabel : text;
            hostEl.classList.toggle('is-empty', !!isEmpty);
            hostEl.setAttribute('data-raw-value', text || '');
        }

        function save() {
            if (saved) return;
            saved = true;
            var next = field.value.trim();
            if (!allowEmpty && !next) {
                restore(current, !current && !!emptyLabel);
                return;
            }
            if (next === current) {
                restore(current, !current && !!emptyLabel);
                return;
            }
            var payload = {};
            payload[opts.field || 'title'] = next;
            postJson(urlFor(updateTpl, opts.itemId), payload)
                .then(function (result) {
                    if (!result.ok) {
                        restore(current, !current && !!emptyLabel);
                        alert((result.data && result.data.message) || 'Error');
                        return;
                    }
                    var value = result.data.item[opts.field || 'title'];
                    if (opts.field === 'help') {
                        value = value || '';
                        restore(value, !value);
                    } else {
                        restore(value || next, false);
                    }
                    if (typeof opts.onSaved === 'function') opts.onSaved(result.data.item);
                })
                .catch(function () {
                    restore(current, !current && !!emptyLabel);
                });
        }

        field.addEventListener('keydown', function (ev) {
            if (ev.key === 'Enter' && !multiline) {
                ev.preventDefault();
                field.blur();
            }
            if (ev.key === 'Escape') {
                saved = true;
                restore(current, !current && !!emptyLabel);
            }
        });
        field.addEventListener('blur', save);
    }

    function bindTitleEdit(itemEl) {
        var titleEl = itemEl.hasAttribute('data-sc-subitem')
            ? itemEl.querySelector('.cabinet-sc-subtask__title[data-sc-title]')
            : itemEl.querySelector('.cabinet-sc-plan__task[data-sc-title], .cabinet-sc-task__main [data-sc-title]');
        if (!titleEl || !updateTpl || titleEl.disabled) return;
        titleEl.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            startInlineEdit(titleEl, {
                itemId: itemEl.getAttribute('data-id'),
                field: 'title',
                allowEmpty: false,
            });
        });
    }

    function bindHelpEdit(itemEl) {
        var helpEl = itemEl.querySelector('[data-sc-help]');
        if (!helpEl || !updateTpl) return;
        var emptyLabel = root.getAttribute('data-i18n-add-description') || 'Add description';
        if (!helpEl.getAttribute('data-raw-value')) {
            helpEl.setAttribute('data-raw-value', helpEl.classList.contains('is-empty') ? '' : String(helpEl.textContent || '').trim());
        }
        helpEl.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            startInlineEdit(helpEl, {
                itemId: itemEl.getAttribute('data-id'),
                field: 'help',
                multiline: true,
                allowEmpty: true,
                emptyLabel: emptyLabel,
            });
        });
        helpEl.addEventListener('keydown', function (e) {
            // Space/Enter открывают редактирование только с фокуса на самом <p>,
            // не из textarea внутри (иначе пробел в описании глотается).
            if (helpEl.classList.contains('is-editing')) return;
            if (e.target !== helpEl) return;
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                helpEl.click();
            }
        });
    }

    function bindRow(el, isSub) {
        applyItemUi(el, el.getAttribute('data-status') || 'todo');

        var checkbox = isSub
            ? el.querySelector('.cabinet-sc-check--sub > [data-sc-done], .cabinet-sc-check--sub [data-sc-done]')
            : el.querySelector('.cabinet-sc-plan__meta [data-sc-done], .cabinet-sc-task__main .cabinet-sc-check > [data-sc-done]');
        if (checkbox) {
            checkbox.addEventListener('change', function () {
                if (checkbox.checked) {
                    if (isSub) {
                        if (el.getAttribute('data-can-close') !== '1') {
                            checkbox.checked = false;
                            alert(labelOnlyCreatorClose);
                            return;
                        }
                        setStatus(el, 'done');
                        return;
                    }
                    var cur = el.getAttribute('data-status') || 'todo';
                    var canApprove = root.getAttribute('data-can-approve') === '1';
                    setStatus(el, (cur === 'review' && canApprove) ? 'done' : 'review');
                } else {
                    setStatus(el, 'todo');
                }
            });
        }

        var statusSelect = isSub
            ? el.querySelector('.cabinet-sc-subtask__controls [data-sc-status], [data-sc-status]')
            : el.querySelector('.cabinet-sc-plan__actions [data-sc-status], .cabinet-sc-task__main [data-sc-status]');
        if (statusSelect) {
            statusSelect.addEventListener('change', function () {
                setStatus(el, statusSelect.value);
            });
        }

        var includeReport = isSub
            ? el.querySelector('.cabinet-sc-subtask__controls [data-sc-include-report], [data-sc-include-report]')
            : el.querySelector('.cabinet-sc-plan__meta [data-sc-include-report]');
        if (includeReport && updateTpl) {
            includeReport.addEventListener('change', function () {
                includeReport.disabled = true;
                postJson(urlFor(updateTpl, el.getAttribute('data-id')), {
                    include_in_report: includeReport.checked ? 1 : 0,
                })
                    .then(function (result) {
                        includeReport.disabled = false;
                        if (!result.ok) {
                            includeReport.checked = !includeReport.checked;
                            alert((result.data && result.data.message) || 'Error');
                        }
                    })
                    .catch(function () {
                        includeReport.disabled = false;
                        includeReport.checked = !includeReport.checked;
                    });
            });
        }

        var timerBtn = itemTimerBtn(el);
        if (timerBtn) {
            timerBtn.addEventListener('click', function () {
                toggleTimer(el);
            });
        }

        if (!isSub) {
            bindHelpEdit(el);

            var toggleNotes = el.querySelector('[data-sc-toggle-notes]');
            var notesBox = el.querySelector('[data-sc-notes]');
            if (toggleNotes && notesBox) {
                toggleNotes.addEventListener('click', function () {
                    var opening = notesBox.classList.contains('d-none');
                    notesBox.classList.toggle('d-none');
                    toggleNotes.classList.toggle('is-open', opening);
                    toggleNotes.setAttribute('aria-expanded', opening ? 'true' : 'false');
                });
            }

            var toggleInfo = el.querySelector('[data-sc-toggle-info]');
            var infoBox = el.querySelector('[data-sc-info]');
            if (toggleInfo && infoBox) {
                toggleInfo.addEventListener('click', function () {
                    var opening = infoBox.classList.contains('d-none');
                    infoBox.classList.toggle('d-none', !opening);
                    toggleInfo.classList.toggle('is-open', opening);
                    toggleInfo.setAttribute('aria-expanded', opening ? 'true' : 'false');
                });
            }

            var toggleSubForm = el.querySelector('[data-sc-toggle-sub-form]');
            var subForm = el.querySelector('[data-sc-sub-form]');
            var subsBlock = el.querySelector('[data-sc-subtasks-block]');
            if (toggleSubForm && subForm) {
                toggleSubForm.addEventListener('click', function () {
                    var opening = subForm.classList.contains('d-none');
                    if (opening && subsBlock) {
                        subsBlock.classList.remove('d-none', 'is-empty');
                    }
                    subForm.classList.toggle('d-none', !opening);
                    toggleSubForm.classList.toggle('is-open', opening);
                    toggleSubForm.setAttribute('aria-expanded', opening ? 'true' : 'false');
                    if (opening) {
                        var input = subForm.querySelector('[data-sc-subtask-title]');
                        if (input) input.focus();
                    } else if (subsBlock && !subsBlock.querySelector('[data-sc-subitem]')) {
                        subsBlock.classList.add('d-none', 'is-empty');
                    }
                });
            }

            var toggleTime = el.querySelector('[data-sc-toggle-time]');
            var timePanel = el.querySelector('[data-sc-time-panel]');
            if (toggleTime && timePanel) {
                toggleTime.addEventListener('click', function () {
                    var opening = timePanel.classList.contains('d-none');
                    timePanel.classList.toggle('d-none');
                    if (opening) loadTimeBreakdown(el);
                });
            }

            var saveNote = el.querySelector('[data-sc-note-save]');
            var noteBody = el.querySelector('[data-sc-note-body]');
            var notesList = el.querySelector('[data-sc-notes-list]');
            if (saveNote && noteBody && notesList) {
                saveNote.addEventListener('click', function () {
                    var body = noteBody.value.trim();
                    if (!body) return;
                    saveNote.disabled = true;
                    postJson(urlFor(noteTpl, el.getAttribute('data-id')), { body: body })
                        .then(function (result) {
                            saveNote.disabled = false;
                            if (!result.ok) {
                                alert((result.data && result.data.message) || 'Error');
                                return;
                            }
                            var li = document.createElement('li');
                            var noteId = parseInt((result.data.note && result.data.note.id) || 0, 10) || 0;
                            var author = String((result.data.note && result.data.note.author) || '').replace(/</g, '&lt;');
                            var created = String((result.data.note && result.data.note.created_at) || '');
                            var bodyHtml = (result.data.note && result.data.note.body_html)
                                ? String(result.data.note.body_html)
                                : String((result.data.note && result.data.note.body) || '').replace(/</g, '&lt;');
                            if (noteId > 0) li.setAttribute('data-note-id', String(noteId));
                            li.setAttribute('data-note-own', '1');
                            li.innerHTML = '<div class="cabinet-sc-notes-list__main">' +
                                '<div class="cabinet-sc-notes-list__meta">' +
                                (author ? '<strong class="cabinet-sc-notes-list__author">' + author + '</strong> ' : '') +
                                '<span class="text-secondary small">' + created + '</span></div>' +
                                '<div class="cabinet-sc-notes-list__body">' + bodyHtml + '</div></div>';
                            notesList.insertBefore(li, notesList.firstChild);
                            noteBody.value = '';
                            var total = (parseInt(el.getAttribute('data-notes-count') || '0', 10) || 0) + 1;
                            var unread = parseInt(el.getAttribute('data-unread-notes-count') || '0', 10) || 0;
                            setNotesBadge(el, total, unread);
                        })
                        .catch(function () {
                            saveNote.disabled = false;
                        });
                });
            }

            var addSub = el.querySelector('[data-sc-subtask-add]');
            var subTitle = el.querySelector('[data-sc-subtask-title]');
            function refreshSubCount() {
                var countEl = el.querySelector('[data-sc-subtasks-count]');
                var list = el.querySelector('[data-sc-subtasks]');
                var head = el.querySelector('[data-sc-subtasks-head]');
                var block = el.querySelector('[data-sc-subtasks-block]');
                if (!list) return;
                var all = list.querySelectorAll('[data-sc-subitem]').length;
                var open = list.querySelectorAll('[data-sc-subitem]:not(.is-done)').length;
                if (countEl) countEl.textContent = open + '/' + all;
                if (head) head.classList.toggle('d-none', all < 1);
                list.hidden = all < 1;
                if (block) {
                    block.classList.toggle('is-empty', all < 1);
                    if (all > 0) block.classList.remove('d-none');
                }
            }
            function submitSubtask() {
                var title = subTitle.value.trim();
                if (!title) return;
                var includeCb = el.querySelector('[data-sc-subtask-include-report]');
                var includeInReport = includeCb ? !!includeCb.checked : false;
                addSub.disabled = true;
                postJson(urlFor(subtaskTpl, el.getAttribute('data-id')), {
                    title: title,
                    include_in_report: includeInReport ? 1 : 0,
                })
                    .then(function (result) {
                        addSub.disabled = false;
                        if (!result.ok) {
                            alert((result.data && result.data.message) || 'Error');
                            return;
                        }
                        var block = el.querySelector('[data-sc-subtasks-block]');
                        var list = el.querySelector('[data-sc-subtasks]');
                        if (!list) {
                            list = document.createElement('ul');
                            list.className = 'cabinet-sc-plan__subs-list cabinet-sc-subtasks';
                            list.setAttribute('data-sc-subtasks', '');
                            if (block) {
                                var form = block.querySelector('.cabinet-sc-subtask-form');
                                if (form) block.insertBefore(list, form);
                                else block.appendChild(list);
                            } else {
                                el.appendChild(list);
                            }
                        }
                        if (block) {
                            block.classList.remove('d-none', 'is-empty');
                        }
                        list.hidden = false;
                        var li = document.createElement('li');
                        li.className = 'cabinet-sc-subtask cabinet-sc-plan__sub';
                        li.setAttribute('data-sc-subitem', '');
                        li.setAttribute('data-id', result.data.item.id);
                        li.setAttribute('data-status', result.data.item.status || 'todo');
                        li.setAttribute('data-created-by', String(result.data.item.created_by || root.getAttribute('data-auth-id') || '0'));
                        li.setAttribute('data-can-close', '1');
                        li.setAttribute('data-time-spent', '0');
                        li.setAttribute('data-timer-running', '0');
                        li.setAttribute('data-timer-started-at', '');
                        var statusOpts = '';
                        var statusValue = result.data.item.status || 'todo';
                        var canCloseNew = true;
                        try {
                            var statusMap = JSON.parse(root.getAttribute('data-status-options') || '{}');
                            if (statusMap && typeof statusMap === 'object') {
                                Object.keys(statusMap).forEach(function (key) {
                                    if ((key === 'done' || key === 'skip') && !canCloseNew && key !== statusValue) return;
                                    statusOpts += '<option value="' + key + '"' +
                                        (key === statusValue ? ' selected' : '') +
                                        '>' + String(statusMap[key]).replace(/</g, '&lt;') + '</option>';
                                });
                            }
                        } catch (e) {
                            statusOpts = '';
                        }
                        if (!statusOpts) {
                            var sample = el.querySelector('.cabinet-sc-plan__actions [data-sc-status]')
                                || el.querySelector('[data-sc-status]')
                                || root.querySelector('[data-sc-status]');
                            if (sample) {
                                Array.prototype.forEach.call(sample.options, function (opt) {
                                    if (!opt.value) return;
                                    statusOpts += '<option value="' + opt.value + '"' +
                                        (opt.value === statusValue ? ' selected' : '') +
                                        '>' + String(opt.textContent || '').replace(/</g, '&lt;') + '</option>';
                                });
                            }
                        }
                        var startLabel = labelStartShort || 'Start';
                        var startTip = labelStart || 'Start timer';
                        var reportLabel = root.getAttribute('data-i18n-include-report') || 'In reports';
                        var reportChecked = result.data.item.include_in_report ? ' checked' : '';
                        var dragLabel = root.getAttribute('data-i18n-drag-reorder') || 'Drag to reorder';
                        li.innerHTML = '<span class="cabinet-sc-sub-drag" data-sc-sub-drag draggable="true" aria-label="' +
                            String(dragLabel).replace(/"/g, '&quot;') + '">⋮⋮</span>' +
                            '<label class="cabinet-sc-check cabinet-sc-check--sub">' +
                            '<input type="checkbox" data-sc-done></label>' +
                            '<div class="cabinet-sc-subtask__body">' +
                            '<button type="button" class="cabinet-sc-subtask__title" data-sc-title></button></div>' +
                            '<div class="cabinet-sc-subtask__controls">' +
                            '<span class="cabinet-sc-review-hint" data-sc-review-hint hidden>' +
                            String(root.getAttribute('data-i18n-waiting-review') || 'Waiting for review').replace(/</g, '&lt;') +
                            '</span>' +
                            '<div class="cabinet-sc-subtask__time">' +
                            '<span class="cabinet-sc-time cabinet-sc-time--sub" data-sc-time>0:00</span>' +
                            '<button type="button" class="btn btn-sm btn-outline-success cabinet-sc-subtask__timer" data-sc-timer data-tip="' +
                            String(startTip).replace(/"/g, '&quot;') + '">' + String(startLabel).replace(/</g, '&lt;') + '</button></div>' +
                            '<select class="form-select form-select-sm cabinet-sc-subtask__status" data-sc-status aria-label="Status">' +
                            statusOpts + '</select>' +
                            '<label class="cabinet-sc-report-flag cabinet-sc-report-flag--sub" data-tip="' +
                            String(reportLabel).replace(/"/g, '&quot;') + '">' +
                            '<input type="checkbox" class="visually-hidden" data-sc-include-report value="1"' + reportChecked + '>' +
                            '<i class="bi bi-file-earmark-bar-graph" aria-hidden="true"></i>' +
                            '<span class="visually-hidden">' + String(reportLabel).replace(/</g, '&lt;') + '</span></label>' +
                            '<button type="button" class="btn btn-link btn-sm text-danger p-0 cabinet-sc-subtask__delete" data-sc-delete data-tip="Delete" aria-label="Delete">×</button>' +
                            '</div>' +
                            '<p class="cabinet-sc-task__audit cabinet-sc-task__audit--sub" data-sc-audit hidden>' +
                            '<span data-sc-audit-created hidden></span>' +
                            '<span data-sc-audit-status hidden></span>' +
                            '<span data-sc-audit-done hidden></span></p>';
                        var titleBtn = li.querySelector('[data-sc-title]');
                        titleBtn.textContent = result.data.item.title;
                        titleBtn.setAttribute('data-tip', root.getAttribute('data-i18n-click-to-edit') || 'Click to edit');
                        var statusSelect = li.querySelector('[data-sc-status]');
                        if (statusSelect && statusValue) {
                            statusSelect.value = statusValue;
                        }
                        if (result.data.item.audit) {
                            applyAuditMeta(li, result.data.item.audit);
                        }
                        list.appendChild(li);
                        bindRow(li, true);
                        subTitle.value = '';
                        if (includeCb) includeCb.checked = false;
                        refreshSubCount();
                        subTitle.focus();
                    })
                    .catch(function () {
                        addSub.disabled = false;
                    });
            }
            if (addSub && subTitle) {
                addSub.addEventListener('click', submitSubtask);
                subTitle.addEventListener('keydown', function (ev) {
                    if (ev.key === 'Enter') {
                        ev.preventDefault();
                        submitSubtask();
                    }
                });
            }
        }

        bindTitleEdit(el);

        // удаление — через делегирование на root ([data-sc-delete])
    }

    root.querySelectorAll('[data-sc-item]').forEach(function (itemEl) {
        bindRow(itemEl, false);
    });
    root.querySelectorAll('[data-sc-subitem]').forEach(function (subEl) {
        bindRow(subEl, true);
    });

    window.setInterval(tickAllTimers, 1000);

    window.cabinetSeoChecklistOnTimerStopped = function (data) {
        if (data && data.item) {
            var target = root.querySelector('[data-sc-item][data-id="' + data.item.id + '"]');
            if (target) applyTimerUi(target, data.item);
        }
        syncPageFromActive(null);
    };

    (function initShowStickyChrome() {
        if (!root.classList.contains('cabinet-sc-show-v2')) return;
        var filters = root.querySelector('[data-sc-show-filters]');
        var rail = root.querySelector('[data-sc-show-rail]');
        if (!filters && !rail) return;

        var filtersPlaceholder = null;
        var railPlaceholder = null;
        var pinnedFilters = false;
        var pinnedRail = false;
        var filtersTop = 0;
        var railTop = 0;

        function measure() {
            if (filters && !pinnedFilters) {
                filtersTop = filters.getBoundingClientRect().top + window.pageYOffset;
            }
            if (rail && !pinnedRail) {
                railTop = rail.getBoundingClientRect().top + window.pageYOffset;
            }
        }

        function pinFilters() {
            if (!filters || pinnedFilters) return;
            var rect = filters.getBoundingClientRect();
            filtersPlaceholder = document.createElement('div');
            filtersPlaceholder.style.height = rect.height + 'px';
            filtersPlaceholder.style.margin = getComputedStyle(filters).margin;
            filters.parentNode.insertBefore(filtersPlaceholder, filters);
            filters.classList.add('is-pinned');
            filters.style.width = rect.width + 'px';
            filters.style.left = rect.left + 'px';
            filters.style.top = '0.75rem';
            pinnedFilters = true;
        }

        function unpinFilters() {
            if (!filters || !pinnedFilters) return;
            filters.classList.remove('is-pinned');
            filters.style.width = '';
            filters.style.left = '';
            filters.style.top = '';
            if (filtersPlaceholder && filtersPlaceholder.parentNode) {
                filtersPlaceholder.parentNode.removeChild(filtersPlaceholder);
            }
            filtersPlaceholder = null;
            pinnedFilters = false;
        }

        function pinRail() {
            if (!rail || pinnedRail) return;
            var rect = rail.getBoundingClientRect();
            railPlaceholder = document.createElement('div');
            railPlaceholder.style.height = rect.height + 'px';
            rail.parentNode.insertBefore(railPlaceholder, rail);
            rail.classList.add('is-pinned');
            rail.style.width = rect.width + 'px';
            rail.style.left = rect.left + 'px';
            rail.style.top = '0.75rem';
            pinnedRail = true;
        }

        function unpinRail() {
            if (!rail || !pinnedRail) return;
            rail.classList.remove('is-pinned');
            rail.style.width = '';
            rail.style.left = '';
            rail.style.top = '';
            if (railPlaceholder && railPlaceholder.parentNode) {
                railPlaceholder.parentNode.removeChild(railPlaceholder);
            }
            railPlaceholder = null;
            pinnedRail = false;
        }

        function onScroll() {
            var y = window.pageYOffset || document.documentElement.scrollTop || 0;
            if (filters) {
                if (!pinnedFilters && y + 12 > filtersTop) pinFilters();
                else if (pinnedFilters && y + 12 <= filtersTop) unpinFilters();
            }
            if (rail && window.matchMedia('(min-width: 1101px)').matches) {
                if (!pinnedRail && y + 12 > railTop) pinRail();
                else if (pinnedRail && y + 12 <= railTop) unpinRail();
            } else {
                unpinRail();
            }
        }

        measure();
        window.addEventListener('scroll', onScroll, { passive: true });
        window.addEventListener('resize', function () {
            unpinFilters();
            unpinRail();
            measure();
            onScroll();
        });
        onScroll();
    })();

    // —— Checklist subitems: drag-and-drop reorder ——
    (function initSubtaskDrag() {
        if (!subtaskReorderTpl) return;

        var dragLi = null;
        var dragList = null;
        var dragOriginNext = null;
        var dragOriginIds = [];
        var dragSaving = false;

        function subItems(list) {
            return Array.prototype.slice.call(list.querySelectorAll(':scope > [data-sc-subitem]'));
        }

        function idsOf(list) {
            return subItems(list).map(function (li) {
                return String(li.getAttribute('data-id') || '');
            }).filter(Boolean);
        }

        function restoreDrag(li, list, originNext) {
            if (!li || !list) return;
            if (originNext && originNext.parentNode === list) {
                list.insertBefore(li, originNext);
            } else {
                list.appendChild(li);
            }
        }

        function saveOrder(li, list, originNext, originIds) {
            if (!li || !list || dragSaving) return;
            var parentItem = li.closest('[data-sc-item]');
            if (!parentItem) return;
            var parentId = parentItem.getAttribute('data-id');
            var ids = idsOf(list);
            if (!parentId || ids.length < 1) return;
            if (ids.join(',') === (originIds || []).join(',')) {
                li.classList.remove('is-dragging');
                return;
            }
            dragSaving = true;
            li.classList.add('is-busy');
            postJson(urlFor(subtaskReorderTpl, parentId), {
                ordered_ids: ids.map(function (x) { return parseInt(x, 10); }),
            })
                .then(function (result) {
                    li.classList.remove('is-busy', 'is-dragging');
                    dragSaving = false;
                    if (!result.ok) {
                        restoreDrag(li, list, originNext);
                        alert((result.data && result.data.message) || 'Error');
                    }
                })
                .catch(function () {
                    li.classList.remove('is-busy', 'is-dragging');
                    dragSaving = false;
                    restoreDrag(li, list, originNext);
                    alert('Error');
                });
        }

        root.addEventListener('dragstart', function (e) {
            var handle = e.target.closest('[data-sc-sub-drag]');
            if (!handle || !root.contains(handle)) return;
            var li = handle.closest('[data-sc-subitem]');
            var list = li && li.closest('[data-sc-subtasks]');
            if (!li || !list) return;
            dragLi = li;
            dragList = list;
            dragOriginNext = li.nextSibling;
            dragOriginIds = idsOf(list);
            li.classList.add('is-dragging');
            try {
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', String(li.getAttribute('data-id') || ''));
            } catch (err) { /* ignore */ }
        });

        root.addEventListener('dragover', function (e) {
            if (!dragLi || !dragList) return;
            var over = e.target.closest('[data-sc-subitem]');
            if (over && over.parentNode !== dragList) return;
            if (!over && !dragList.contains(e.target)) return;
            e.preventDefault();
            try { e.dataTransfer.dropEffect = 'move'; } catch (err) { /* ignore */ }
            if (!over || over === dragLi) return;

            root.querySelectorAll('.cabinet-sc-subtask.is-drag-over').forEach(function (el) {
                if (el !== over) el.classList.remove('is-drag-over');
            });
            over.classList.add('is-drag-over');

            var rect = over.getBoundingClientRect();
            var before = (e.clientY - rect.top) < rect.height / 2;
            if (before) {
                if (dragLi.nextSibling !== over) dragList.insertBefore(dragLi, over);
            } else if (over.nextSibling !== dragLi) {
                dragList.insertBefore(dragLi, over.nextSibling);
            }
        });

        root.addEventListener('drop', function (e) {
            if (!dragLi) return;
            e.preventDefault();
        });

        root.addEventListener('dragend', function () {
            root.querySelectorAll('.cabinet-sc-subtask.is-drag-over').forEach(function (el) {
                el.classList.remove('is-drag-over');
            });
            var li = dragLi;
            var list = dragList;
            var originNext = dragOriginNext;
            var originIds = dragOriginIds.slice();
            dragLi = null;
            dragList = null;
            dragOriginNext = null;
            dragOriginIds = [];
            if (!li || dragSaving) return;
            saveOrder(li, list, originNext, originIds);
        });
    })();
})();
