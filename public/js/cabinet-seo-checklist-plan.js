(function () {
    var root = document.getElementById('cabinetSeoChecklistPlan');
    if (!root) return;

    var csrf = root.getAttribute('data-csrf') || '';
    var statusTpl = root.getAttribute('data-status-url-template') || '';
    var noteTpl = root.getAttribute('data-note-url-template') || '';
    var subtaskTpl = root.getAttribute('data-subtask-url-template') || '';
    var subtaskReorderTpl = root.getAttribute('data-subtask-reorder-url-template') || '';
    var updateTpl = root.getAttribute('data-update-url-template') || '';
    var markReadUrl = root.getAttribute('data-mark-read-url') || '';
    var markUnreadUrl = root.getAttribute('data-mark-unread-url') || '';
    var timerStartTpl = root.getAttribute('data-timer-start-url-template') || '';
    var timerStopTpl = root.getAttribute('data-timer-stop-url-template') || '';
    var labelStart = root.getAttribute('data-i18n-timer-start') || 'Start timer';
    var labelStop = root.getAttribute('data-i18n-timer-stop') || 'Stop timer';
    var labelStartShort = root.getAttribute('data-i18n-timer-start-short') || 'Start';
    var labelStopShort = root.getAttribute('data-i18n-timer-stop-short') || 'Stop';
    var commentRequired = root.getAttribute('data-i18n-comment-required') || 'Comment required';
    var chooseStatusLabel = root.getAttribute('data-i18n-choose-status') || 'Choose task status';
    var waitingReviewLabel = root.getAttribute('data-i18n-waiting-review') || 'Waiting for review';
    var labelMarkRead = root.getAttribute('data-i18n-mark-read') || 'Mark as read';
    var labelMarkUnread = root.getAttribute('data-i18n-mark-unread') || 'Mark note unread';
    var labelMarkUnreadShort = root.getAttribute('data-i18n-mark-unread-short') || 'Unread';
    var labelUnread = root.getAttribute('data-i18n-unread') || 'Unread';
    var labelSendReviewFirst = root.getAttribute('data-i18n-send-review-first')
        || 'Send the task for review first';
    var labelCloseSubsFirst = root.getAttribute('data-i18n-close-subs-first')
        || 'Close open checklist items first';

    function formatCount(n) {
        n = Math.max(0, parseInt(n, 10) || 0);
        return String(n).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    }

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

    function urlFor(tpl, projectId, itemId) {
        return String(tpl || '')
            .replace('__PROJECT__', String(projectId))
            .replace('__ID__', String(itemId));
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
            return r.text().then(function (text) {
                var data = null;
                try { data = text ? JSON.parse(text) : null; } catch (e) { data = null; }
                return { ok: r.ok && data && data.ok, data: data, status: r.status };
            });
        });
    }

    function itemDisplaySeconds(itemEl) {
        var base = parseInt(itemEl.getAttribute('data-time-spent') || '0', 10) || 0;
        if (itemEl.getAttribute('data-timer-running') !== '1') return base;
        var started = parseStartedAt(itemEl.getAttribute('data-timer-started-at'));
        if (!started) return base;
        return base + Math.max(0, Math.floor((Date.now() - started) / 1000));
    }

    function timerTargets(itemEl) {
        if (!itemEl) return { timeEl: null, btn: null };
        if (itemEl.hasAttribute('data-sc-plan-sub')) {
            return {
                timeEl: itemEl.querySelector('[data-sc-time]'),
                btn: itemEl.querySelector('[data-sc-timer]'),
            };
        }
        var row = itemEl.querySelector('.cabinet-sc-plan__row');
        var scope = row || itemEl;
        return {
            timeEl: scope.querySelector('[data-sc-time]'),
            btn: scope.querySelector('[data-sc-timer]'),
        };
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

        var targets = timerTargets(itemEl);
        if (targets.timeEl) {
            targets.timeEl.classList.toggle('is-running', running);
            targets.timeEl.textContent = formatDuration(
                typeof state.display_seconds === 'number' ? state.display_seconds : itemDisplaySeconds(itemEl)
            );
        }

        if (targets.btn) {
            targets.btn.textContent = running ? labelStopShort : labelStartShort;
            targets.btn.setAttribute('data-tip', running ? labelStop : labelStart);
            targets.btn.classList.toggle('btn-danger', running);
            targets.btn.classList.toggle('btn-outline-success', !running);
        }
    }

    function syncFromActive(active) {
        root.querySelectorAll('[data-sc-plan-item], [data-sc-plan-sub]').forEach(function (itemEl) {
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

    function refreshGroupCounts() {
        root.querySelectorAll('[data-sc-plan-group]').forEach(function (group) {
            var n = group.querySelectorAll('[data-sc-plan-item]').length;
            var countEl = group.querySelector('[data-sc-plan-count]');
            if (countEl) countEl.textContent = formatCount(n);
            group.style.display = n === 0 ? 'none' : '';
            var key = group.getAttribute('data-sc-plan-group');
            var railCount = key
                ? root.querySelector('[data-sc-plan-rail-link="' + key + '"] .cabinet-sc-plan-rail__count')
                : null;
            if (railCount) railCount.textContent = formatCount(n);
            var railLink = key
                ? root.querySelector('[data-sc-plan-rail-link="' + key + '"]')
                : null;
            if (railLink) {
                var railLi = railLink.closest('li');
                if (railLi) railLi.hidden = n === 0;
            }
        });
    }

    function refreshSubCounts(subsRoot) {
        if (!subsRoot) return;
        var all = subsRoot.querySelectorAll('[data-sc-plan-sub]').length;
        var open = subsRoot.querySelectorAll('[data-sc-plan-sub]:not(.is-done)').length;
        var meta = subsRoot.querySelector('[data-sc-plan-subs-count]');
        if (meta) meta.textContent = open + '/' + all;
        var parent = subsRoot.closest('[data-sc-plan-item]');
        syncSubsCloseHint(parent);
    }

    function syncSubsCloseHint(itemEl) {
        if (!itemEl || itemEl.hasAttribute('data-sc-plan-sub')) return;
        var hint = itemEl.querySelector('[data-sc-subs-close-hint]');
        if (!hint) return;
        var status = itemEl.getAttribute('data-status') || '';
        var open = itemEl.querySelectorAll('[data-sc-plan-sub]:not(.is-done)').length;
        var show = status === 'review' && open > 0;
        hint.classList.toggle('d-none', !show);
        if (show) {
            hint.textContent = labelCloseSubsFirst.replace(':count', String(open));
        }
    }

    function clearStatusBlock(itemEl) {
        if (!itemEl) return;
        itemEl.classList.remove('is-status-blocked');
        itemEl.querySelectorAll('[data-sc-plan-sub].is-need-close').forEach(function (sub) {
            sub.classList.remove('is-need-close');
        });
        var box = itemEl.querySelector('[data-sc-status-block]');
        if (box) {
            box.hidden = true;
            box.textContent = '';
        }
        if (itemEl._scBlockTimer) {
            clearTimeout(itemEl._scBlockTimer);
            itemEl._scBlockTimer = null;
        }
    }

    function ensureSubsVisible(itemEl) {
        var block = itemEl.querySelector('[data-sc-plan-subs]');
        if (!block) return;
        block.classList.remove('d-none', 'is-empty');
        var list = block.querySelector('[data-sc-plan-subs-list]');
        if (list) list.hidden = false;
        var head = block.querySelector('[data-sc-plan-subs-head]');
        if (head) head.classList.remove('d-none');
    }

    function showStatusBlock(itemEl, message, openSubs) {
        if (!itemEl) return;
        clearStatusBlock(itemEl);
        ensureSubsVisible(itemEl);
        itemEl.classList.add('is-status-blocked');
        var nodes = openSubs || itemEl.querySelectorAll('[data-sc-plan-sub]:not(.is-done)');
        Array.prototype.forEach.call(nodes, function (sub) {
            sub.classList.add('is-need-close');
        });
        var box = itemEl.querySelector('[data-sc-status-block]');
        if (!box) {
            box = document.createElement('div');
            box.className = 'cabinet-sc-plan__block-msg';
            box.setAttribute('data-sc-status-block', '');
            box.setAttribute('role', 'alert');
            var shell = itemEl.querySelector('.cabinet-sc-plan__shell') || itemEl;
            var subs = itemEl.querySelector('[data-sc-plan-subs]');
            if (subs && subs.parentNode) {
                subs.parentNode.insertBefore(box, subs);
            } else {
                shell.appendChild(box);
            }
        }
        box.innerHTML = '<i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>' +
            '<span>' + String(message || '').replace(/</g, '&lt;') + '</span>';
        box.hidden = false;
        syncSubsCloseHint(itemEl);
        try {
            var first = nodes[0] || box;
            first.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        } catch (e) {}
        itemEl._scBlockTimer = setTimeout(function () {
            itemEl.classList.remove('is-status-blocked');
            itemEl.querySelectorAll('[data-sc-plan-sub].is-need-close').forEach(function (sub) {
                sub.classList.remove('is-need-close');
            });
        }, 7000);
    }

    function removeItem(itemEl) {
        var group = itemEl.closest('[data-sc-plan-group]');
        itemEl.parentNode.removeChild(itemEl);
        if (group) refreshGroupCounts();
    }

    function syncParentClosedOptions(itemEl, status) {
        if (!itemEl || itemEl.hasAttribute('data-sc-plan-sub')) return;
        var select = null;
        var row = itemEl.querySelector('.cabinet-sc-plan__row');
        select = row ? row.querySelector('[data-sc-status]') : itemEl.querySelector('[data-sc-status]');
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

    function applyAuditMeta(itemEl, audit) {
        if (!itemEl || !audit) return;
        var box = itemEl.querySelector('[data-sc-audit]');
        if (!box) {
            // в плане audit лежит в info-панели; создаём/находим контейнер
            var info = itemEl.querySelector('[data-sc-info]');
            if (info) {
                box = info.querySelector('[data-sc-audit]');
            }
        }
        if (!box) return;
        var created = box.querySelector('[data-sc-audit-created]');
        var status = box.querySelector('[data-sc-audit-status]');
        var done = box.querySelector('[data-sc-audit-done]');
        if (!status) {
            status = document.createElement('span');
            status.setAttribute('data-sc-audit-status', '');
            if (done) box.insertBefore(status, done);
            else box.appendChild(status);
        }
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

    function applyStatusUi(itemEl, status) {
        if (!status) return;
        var isSub = itemEl.hasAttribute('data-sc-plan-sub');
        itemEl.setAttribute('data-status', status);
        itemEl.classList.toggle('is-doing', !isSub && (status === 'doing' || status === 'rework'));
        itemEl.classList.toggle('is-review', status === 'review');
        itemEl.classList.toggle('is-done', status === 'done' || status === 'skip');

        var title = itemEl.querySelector(isSub ? '[data-sc-title]' : '.cabinet-sc-plan__task');
        if (title) {
            title.classList.toggle('is-review-text', status === 'review');
            title.classList.toggle('is-done-text', status === 'done' || status === 'skip');
        }
        var hint = isSub
            ? itemEl.querySelector('[data-sc-review-hint]')
            : itemEl.querySelector('.cabinet-sc-plan__meta [data-sc-review-hint]');
        if (hint) hint.hidden = status !== 'review';

        var select = null;
        if (isSub) {
            select = itemEl.querySelector('.cabinet-sc-subtask__status') || itemEl.querySelector('[data-sc-status]');
        } else {
            var row = itemEl.querySelector('.cabinet-sc-plan__row');
            select = row ? row.querySelector('[data-sc-status]') : itemEl.querySelector('[data-sc-status]');
        }
        if (select && select.value !== status) select.value = status;

        if (isSub) {
            var done = itemEl.querySelector('[data-sc-plan-sub-done]');
            if (done) done.checked = status === 'done' || status === 'skip';
            if (status === 'done' || status === 'skip') {
                itemEl.classList.remove('is-need-close');
            }
            var parentItem = itemEl.closest('[data-sc-plan-item]');
            refreshSubCounts(itemEl.closest('.cabinet-sc-plan__subs') || (parentItem && parentItem.querySelector('[data-sc-plan-subs]')));
            if (parentItem) {
                var stillOpen = parentItem.querySelectorAll('[data-sc-plan-sub]:not(.is-done)');
                if (!stillOpen.length) {
                    clearStatusBlock(parentItem);
                } else {
                    var blockBox = parentItem.querySelector('[data-sc-status-block]');
                    if (blockBox && !blockBox.hidden) {
                        var span = blockBox.querySelector('span');
                        var msg = labelCloseSubsFirst.replace(':count', String(stillOpen.length));
                        if (span) span.textContent = msg;
                        else blockBox.textContent = msg;
                    }
                }
            }
        } else {
            syncParentClosedOptions(itemEl, status);
            syncSubsCloseHint(itemEl);
        }
    }

    function askStatusAfterStop(itemEl) {
        var select = itemEl.querySelector('[data-sc-status]');
        if (!select) return;
        var isSub = itemEl.hasAttribute('data-sc-plan-sub');
        var canApprove = isSub
            ? itemEl.getAttribute('data-can-close') === '1'
            : (itemEl.getAttribute('data-can-approve') || root.getAttribute('data-can-approve')) === '1';
        var options = [];
        var defaultValue = 'rework';
        var hasRework = false;
        Array.prototype.forEach.call(select.options, function (opt) {
            if (opt.disabled || !opt.value || opt.value === 'todo') return;
            if ((opt.value === 'done' || opt.value === 'skip') && !canApprove) return;
            if (!isSub && (opt.value === 'done' || opt.value === 'skip')
                && itemEl.getAttribute('data-status') !== 'review') return;
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
                    setStatus(itemEl, value, note);
                },
            });
            return;
        }
        setStatus(itemEl, defaultValue);
    }

    function findPlanRow(id) {
        if (!id) return null;
        return root.querySelector('[data-sc-plan-sub][data-id="' + id + '"]')
            || root.querySelector('[data-sc-plan-item][data-id="' + id + '"]');
    }

    function toggleTimer(itemEl) {
        var id = itemEl.getAttribute('data-id');
        var projectId = itemEl.getAttribute('data-project-id');
        var running = itemEl.getAttribute('data-timer-running') === '1';
        var url = urlFor(running ? timerStopTpl : timerStartTpl, projectId, id);
        itemEl.classList.add('is-busy');
        postJson(url, {}).then(function (result) {
            itemEl.classList.remove('is-busy');
            if (!result.ok) {
                alert((result.data && result.data.message) || 'Error');
                return;
            }
            if (result.data.stopped_item) {
                var prev = findPlanRow(result.data.stopped_item.id);
                if (prev) applyTimerUi(prev, result.data.stopped_item);
            }
            if (result.data.item) {
                applyTimerUi(itemEl, result.data.item);
                if (result.data.item.status) applyStatusUi(itemEl, result.data.item.status);
            }
            if (Object.prototype.hasOwnProperty.call(result.data, 'active')) {
                if (result.data.active && window.cabinetSeoChecklistUpsertHeaderTimer) {
                    window.cabinetSeoChecklistUpsertHeaderTimer(result.data.active);
                } else if (!result.data.active && window.cabinetSeoChecklistRemoveHeaderTimer) {
                    window.cabinetSeoChecklistRemoveHeaderTimer();
                }
                syncFromActive(result.data.active || null);
            }
            if (running) {
                askStatusAfterStop(itemEl);
            }
        }).catch(function () {
            itemEl.classList.remove('is-busy');
            alert('Error');
        });
    }

    function setStatus(itemEl, status, noteFromModal) {
        var id = itemEl.getAttribute('data-id');
        var projectId = itemEl.getAttribute('data-project-id');
        var isSub = itemEl.hasAttribute('data-sc-plan-sub');
        var current = itemEl.getAttribute('data-status') || 'todo';

        if (!isSub && (status === 'done' || status === 'skip')) {
            var selectBlock = itemEl.querySelector('.cabinet-sc-plan__row [data-sc-status]')
                || itemEl.querySelector('[data-sc-status]');
            if (current !== 'review') {
                if (selectBlock) selectBlock.value = current;
                showStatusBlock(itemEl, labelSendReviewFirst);
                return;
            }
            var openSubs = itemEl.querySelectorAll('[data-sc-plan-sub]:not(.is-done)');
            if (openSubs.length) {
                if (selectBlock) selectBlock.value = current;
                showStatusBlock(
                    itemEl,
                    labelCloseSubsFirst.replace(':count', String(openSubs.length)),
                    openSubs
                );
                return;
            }
        }

        clearStatusBlock(itemEl);

        var payload = { status: status };
        if (status === 'skip' || status === 'blocked' || status === 'clarify') {
            var note = noteFromModal;
            if (note === undefined) {
                note = window.prompt(commentRequired);
                if (note === null) {
                    var select = itemEl.querySelector('[data-sc-status]');
                    if (select) select.value = itemEl.getAttribute('data-status') || 'todo';
                    return;
                }
            }
            note = String(note || '').trim();
            if (!note) {
                var selectEmpty = itemEl.querySelector('[data-sc-status]');
                if (selectEmpty) selectEmpty.value = itemEl.getAttribute('data-status') || 'todo';
                return;
            }
            payload.note = note;
        }

        itemEl.classList.add('is-busy');
        postJson(urlFor(statusTpl, projectId, id), payload).then(function (result) {
            itemEl.classList.remove('is-busy');
            if (!result.ok) {
                var selectFail = itemEl.querySelector('[data-sc-status]');
                if (selectFail) selectFail.value = itemEl.getAttribute('data-status') || 'todo';
                var doneFail = itemEl.querySelector('[data-sc-plan-sub-done]');
                if (doneFail) {
                    var prev = itemEl.getAttribute('data-status') || 'todo';
                    doneFail.checked = prev === 'done' || prev === 'skip';
                }
                var failMsg = (result.data && result.data.message) || 'Error';
                if (!isSub && (status === 'done' || status === 'skip')) {
                    var openFail = itemEl.querySelectorAll('[data-sc-plan-sub]:not(.is-done)');
                    showStatusBlock(itemEl, failMsg, openFail.length ? openFail : null);
                } else {
                    alert(failMsg);
                }
                return;
            }
            clearStatusBlock(itemEl);
            var next = result.data.item.status;
            applyStatusUi(itemEl, next);
            if (result.data.item.audit) {
                applyAuditMeta(itemEl, result.data.item.audit);
            }
            if (result.data.item.time_spent_seconds !== undefined || result.data.item.timer_running !== undefined) {
                applyTimerUi(itemEl, result.data.item);
            }
            if (Object.prototype.hasOwnProperty.call(result.data, 'active')) {
                if (result.data.active && window.cabinetSeoChecklistUpsertHeaderTimer) {
                    window.cabinetSeoChecklistUpsertHeaderTimer(result.data.active);
                } else if (!result.data.active && window.cabinetSeoChecklistRemoveHeaderTimer) {
                    window.cabinetSeoChecklistRemoveHeaderTimer();
                }
                syncFromActive(result.data.active || null);
            }
            // закрытые основные задачи остаются в DOM для фильтра «Выполненные»
            if (!itemEl.hasAttribute('data-sc-plan-sub') && (next === 'done' || next === 'skip')) {
                try {
                    root.dispatchEvent(new CustomEvent('cabinet-sc-plan-filters-refresh'));
                } catch (e) {}
            }
        }).catch(function () {
            itemEl.classList.remove('is-busy');
            var select = itemEl.querySelector('[data-sc-status]');
            if (select) select.value = itemEl.getAttribute('data-status') || 'todo';
            alert('Error');
        });
    }

    function parseIdList(raw) {
        return String(raw || '')
            .split(',')
            .map(function (v) { return parseInt(v, 10) || 0; })
            .filter(function (v) { return v > 0; });
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
            if (next < 1) {
                el.setAttribute('hidden', 'hidden');
            } else {
                el.removeAttribute('hidden');
            }
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
        if (!countEl) {
            countEl = document.createElement('span');
            countEl.className = 'cabinet-sc-plan__side-btn-count';
            countEl.setAttribute('data-sc-notes-count', '');
            btn.appendChild(countEl);
        }
        countEl.classList.remove('is-empty', 'is-unread', 'is-read');
        if (totalCount < 1) {
            countEl.textContent = '';
            countEl.classList.add('is-empty');
            try {
                root.dispatchEvent(new CustomEvent('cabinet-sc-plan-notes-changed'));
            } catch (e) {}
            return;
        }
        if (unreadCount > 0) {
            countEl.textContent = String(unreadCount);
            countEl.classList.add('is-unread');
        } else {
            countEl.textContent = String(totalCount);
            countEl.classList.add('is-read');
        }
        try {
            root.dispatchEvent(new CustomEvent('cabinet-sc-plan-notes-changed'));
        } catch (e) {}
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

    function ensureNoteSide(li) {
        var side = li.querySelector('[data-sc-note-side]');
        if (side) return side;
        side = document.createElement('div');
        side.className = 'cabinet-sc-notes-list__side';
        side.setAttribute('data-sc-note-side', '');
        li.appendChild(side);
        return side;
    }

    function setNoteSideAction(li, mode) {
        if (!li || li.getAttribute('data-note-own') === '1') return;
        var side = ensureNoteSide(li);
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
                var time = meta.querySelector('.text-secondary');
                if (time && time.nextSibling) meta.insertBefore(badge, time.nextSibling);
                else meta.appendChild(badge);
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

    function bumpNotesCount(itemEl) {
        if (!itemEl) return;
        var total = (parseInt(itemEl.getAttribute('data-notes-count') || '0', 10) || 0) + 1;
        var unread = parseInt(itemEl.getAttribute('data-unread-notes-count') || '0', 10) || 0;
        setNotesBadge(itemEl, total, unread);
    }

    function buildPlanSubStatusOptions(parentItem) {
        var statusOpts = '';
        var sample = parentItem.querySelector('.cabinet-sc-plan__row [data-sc-status]')
            || parentItem.querySelector('[data-sc-status]')
            || root.querySelector('[data-sc-status]');
        if (!sample) return statusOpts;
        Array.prototype.forEach.call(sample.options, function (opt) {
            if (!opt.value) return;
            statusOpts += '<option value="' + String(opt.value).replace(/"/g, '&quot;') + '"' +
                (opt.value === 'todo' ? ' selected' : '') +
                '>' + String(opt.textContent || '').replace(/</g, '&lt;') + '</option>';
        });
        return statusOpts;
    }

    function appendPlanSubtask(parentItem, itemData) {
        var block = parentItem.querySelector('[data-sc-plan-subs]');
        var list = parentItem.querySelector('[data-sc-plan-subs-list]');
        if (!list && block) {
            list = document.createElement('ul');
            list.className = 'cabinet-sc-plan__subs-list cabinet-sc-subtasks';
            list.setAttribute('data-sc-plan-subs-list', '');
            var form = block.querySelector('.cabinet-sc-plan__sub-form');
            if (form) block.insertBefore(list, form);
            else block.appendChild(list);
        }
        if (!list) return null;

        var li = document.createElement('li');
        li.className = 'cabinet-sc-subtask cabinet-sc-plan__sub';
        li.setAttribute('data-sc-plan-sub', '');
        li.setAttribute('data-id', String(itemData.id));
        li.setAttribute('data-project-id', parentItem.getAttribute('data-project-id') || '');
        li.setAttribute('data-status', itemData.status || 'todo');
        li.setAttribute('data-can-close', '1');
        li.setAttribute('data-time-spent', '0');
        li.setAttribute('data-timer-running', '0');
        li.setAttribute('data-timer-started-at', '');

        var statusOpts = buildPlanSubStatusOptions(parentItem);
        var dragLabel = root.getAttribute('data-i18n-drag-reorder') || 'Drag to reorder';
        li.innerHTML =
            '<span class="cabinet-sc-sub-drag" data-sc-sub-drag draggable="true" aria-label="' +
            String(dragLabel).replace(/"/g, '&quot;') + '">⋮⋮</span>' +
            '<label class="cabinet-sc-check cabinet-sc-check--sub">' +
            '<input type="checkbox" data-sc-plan-sub-done></label>' +
            '<div class="cabinet-sc-subtask__body">' +
            '<span class="cabinet-sc-subtask__title" data-sc-title></span></div>' +
            '<div class="cabinet-sc-subtask__controls">' +
            '<span class="cabinet-sc-review-hint" data-sc-review-hint hidden>' +
            String(waitingReviewLabel).replace(/</g, '&lt;') +
            '</span>' +
            '<div class="cabinet-sc-subtask__time">' +
            '<span class="cabinet-sc-time cabinet-sc-time--sub" data-sc-time>0:00</span>' +
            '<button type="button" class="btn btn-sm btn-outline-success cabinet-sc-subtask__timer" data-sc-timer data-tip="' +
            String(labelStart).replace(/"/g, '&quot;') + '">' +
            String(labelStartShort).replace(/</g, '&lt;') + '</button></div>' +
            '<select class="form-select form-select-sm cabinet-sc-subtask__status" data-sc-status aria-label="Status">' +
            statusOpts + '</select>' +
            '<label class="cabinet-sc-report-flag cabinet-sc-report-flag--sub" data-tip="' +
            String(root.getAttribute('data-i18n-include-report') || 'In reports').replace(/"/g, '&quot;') + '">' +
            '<input type="checkbox" class="visually-hidden" data-sc-include-report value="1"' +
            (itemData.include_in_report ? ' checked' : '') + '>' +
            '<i class="bi bi-file-earmark-bar-graph" aria-hidden="true"></i>' +
            '<span class="visually-hidden">' +
            String(root.getAttribute('data-i18n-include-report') || 'In reports').replace(/</g, '&lt;') +
            '</span></label></div>' +
            '<p class="cabinet-sc-task__audit cabinet-sc-task__audit--sub" data-sc-audit hidden>' +
            '<span data-sc-audit-created hidden></span>' +
            '<span data-sc-audit-status hidden></span>' +
            '<span data-sc-audit-done hidden></span></p>';

        var titleEl = li.querySelector('[data-sc-title]');
        if (titleEl) titleEl.textContent = itemData.title || '';
        var statusSelect = li.querySelector('[data-sc-status]');
        if (statusSelect && itemData.status) statusSelect.value = itemData.status;

        list.appendChild(li);
        var head = block ? block.querySelector('[data-sc-plan-subs-head]') : null;
        if (head) head.classList.remove('d-none');
        list.hidden = false;
        if (block) block.classList.remove('is-empty');
        refreshSubCounts(block);
        return li;
    }

    function submitPlanSubtask(parentItem) {
        if (!parentItem || !subtaskTpl || parentItem.classList.contains('is-busy')) return;
        var input = parentItem.querySelector('[data-sc-plan-subtask-title]');
        var addBtn = parentItem.querySelector('[data-sc-plan-subtask-add]');
        if (!input || !addBtn) return;
        var title = String(input.value || '').trim();
        if (!title) {
            input.focus();
            return;
        }
        var projectId = parentItem.getAttribute('data-project-id');
        var itemId = parentItem.getAttribute('data-id');
        if (!projectId || !itemId) return;
        var includeCb = parentItem.querySelector('[data-sc-plan-subtask-include-report]');
        var includeInReport = !!(includeCb && includeCb.checked);
        addBtn.disabled = true;
        parentItem.classList.add('is-busy');
        postJson(urlFor(subtaskTpl, projectId, itemId), {
            title: title,
            include_in_report: includeInReport ? 1 : 0,
        })
            .then(function (result) {
                addBtn.disabled = false;
                parentItem.classList.remove('is-busy');
                if (!result.ok) {
                    alert((result.data && result.data.message) || 'Error');
                    return;
                }
                appendPlanSubtask(parentItem, (result.data && result.data.item) || {});
                input.value = '';
                if (includeCb) includeCb.checked = false;
                input.focus();
            })
            .catch(function () {
                addBtn.disabled = false;
                parentItem.classList.remove('is-busy');
            });
    }

    root.addEventListener('click', function (e) {
        var timerBtn = e.target.closest('[data-sc-timer]');
        if (timerBtn) {
            e.preventDefault();
            var item = timerBtn.closest('[data-sc-plan-sub], [data-sc-plan-item]');
            if (item && !item.classList.contains('is-busy')) toggleTimer(item);
            return;
        }

        var markNotesReadBtn = e.target.closest('[data-sc-mark-notes-read]');
        if (markNotesReadBtn) {
            e.preventDefault();
            var markItem = markNotesReadBtn.closest('[data-sc-plan-item]');
            if (markItem) markItemNotesRead(markItem);
            return;
        }

        var markOneNoteBtn = e.target.closest('[data-sc-mark-note-read]');
        if (markOneNoteBtn) {
            e.preventDefault();
            var oneItem = markOneNoteBtn.closest('[data-sc-plan-item]');
            var noteId = parseInt(markOneNoteBtn.getAttribute('data-note-id') || '0', 10) || 0;
            if (oneItem && noteId > 0) markItemNotesRead(oneItem, [noteId]);
            return;
        }

        var markOneUnreadBtn = e.target.closest('[data-sc-mark-note-unread]');
        if (markOneUnreadBtn) {
            e.preventDefault();
            var unreadItem = markOneUnreadBtn.closest('[data-sc-plan-item]');
            var unreadNoteId = parseInt(markOneUnreadBtn.getAttribute('data-note-id') || '0', 10) || 0;
            if (unreadItem && unreadNoteId > 0) markItemNotesUnread(unreadItem, [unreadNoteId]);
            return;
        }

        var toggleNotes = e.target.closest('[data-sc-toggle-notes]');
        if (toggleNotes) {
            e.preventDefault();
            var notesItem = toggleNotes.closest('[data-sc-plan-item]');
            if (!notesItem) return;
            var notesBox = notesItem.querySelector('[data-sc-notes]');
            if (!notesBox) return;
            var willOpen = notesBox.classList.contains('d-none');
            notesBox.classList.toggle('d-none', !willOpen);
            toggleNotes.classList.toggle('is-open', willOpen);
            toggleNotes.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
            return;
        }

        var toggleInfo = e.target.closest('[data-sc-toggle-info]');
        if (toggleInfo) {
            e.preventDefault();
            var infoItem = toggleInfo.closest('[data-sc-plan-item]');
            if (!infoItem) return;
            var infoBox = infoItem.querySelector('[data-sc-info]');
            if (!infoBox) return;
            var infoOpen = infoBox.classList.contains('d-none');
            infoBox.classList.toggle('d-none', !infoOpen);
            toggleInfo.classList.toggle('is-open', infoOpen);
            toggleInfo.setAttribute('aria-expanded', infoOpen ? 'true' : 'false');
            return;
        }

        var toggleSubForm = e.target.closest('[data-sc-toggle-sub-form]');
        if (toggleSubForm) {
            e.preventDefault();
            var subItem = toggleSubForm.closest('[data-sc-plan-item]');
            if (!subItem) return;
            var block = subItem.querySelector('[data-sc-plan-subs]');
            var form = subItem.querySelector('[data-sc-plan-sub-form]');
            if (!form) return;
            var opening = form.classList.contains('d-none');
            if (opening) {
                if (block) {
                    block.classList.remove('d-none');
                }
                form.classList.remove('d-none');
                toggleSubForm.classList.add('is-open');
                toggleSubForm.setAttribute('aria-expanded', 'true');
                var input = form.querySelector('[data-sc-plan-subtask-title]');
                if (input) {
                    window.setTimeout(function () { input.focus(); }, 0);
                }
            } else {
                form.classList.add('d-none');
                toggleSubForm.classList.remove('is-open');
                toggleSubForm.setAttribute('aria-expanded', 'false');
                if (block && block.classList.contains('is-empty')
                    && !block.querySelector('[data-sc-plan-sub]')) {
                    block.classList.add('d-none');
                }
            }
            return;
        }

        var addSubBtn = e.target.closest('[data-sc-plan-subtask-add]');
        if (addSubBtn) {
            e.preventDefault();
            submitPlanSubtask(addSubBtn.closest('[data-sc-plan-item]'));
            return;
        }

        var saveNote = e.target.closest('[data-sc-note-save]');
        if (!saveNote || !noteTpl) return;
        e.preventDefault();
        var noteItem = saveNote.closest('[data-sc-plan-item]');
        if (!noteItem || noteItem.classList.contains('is-busy')) return;
        var noteBody = noteItem.querySelector('[data-sc-note-body]');
        var notesList = noteItem.querySelector('[data-sc-notes-list]');
        if (!noteBody || !notesList) return;
        var body = String(noteBody.value || '').trim();
        if (!body) return;
        var projectId = noteItem.getAttribute('data-project-id');
        var itemId = noteItem.getAttribute('data-id');
        if (!projectId || !itemId) return;
        saveNote.disabled = true;
        postJson(urlFor(noteTpl, projectId, itemId), { body: body })
            .then(function (result) {
                saveNote.disabled = false;
                if (!result.ok) {
                    alert((result.data && result.data.message) || 'Error');
                    return;
                }
                var li = document.createElement('li');
                var author = String((result.data.note && result.data.note.author) || '').replace(/</g, '&lt;');
                var created = String((result.data.note && result.data.note.created_at) || '');
                var bodyHtml = (result.data.note && result.data.note.body_html)
                    ? String(result.data.note.body_html)
                    : String((result.data.note && result.data.note.body) || '').replace(/</g, '&lt;');
                li.innerHTML = '<div class="cabinet-sc-notes-list__meta">' +
                    (author ? '<strong class="cabinet-sc-notes-list__author">' + author + '</strong> ' : '') +
                    '<span class="text-secondary small">' + created + '</span></div>' +
                    '<div class="cabinet-sc-notes-list__body">' + bodyHtml + '</div>';
                notesList.insertBefore(li, notesList.firstChild);
                noteBody.value = '';
                bumpNotesCount(noteItem);
            })
            .catch(function () {
                saveNote.disabled = false;
            });
    });

    root.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter') return;
        var input = e.target.closest('[data-sc-plan-subtask-title]');
        if (!input) return;
        e.preventDefault();
        submitPlanSubtask(input.closest('[data-sc-plan-item]'));
    });

    root.addEventListener('change', function (e) {
        var includeReport = e.target.closest('[data-sc-include-report]');
        if (includeReport && updateTpl) {
            var reportItem = includeReport.closest('[data-sc-plan-sub], [data-sc-plan-item]');
            if (!reportItem || reportItem.classList.contains('is-busy')) return;
            var projectId = reportItem.getAttribute('data-project-id');
            var itemId = reportItem.getAttribute('data-id');
            if (!projectId || !itemId) return;
            includeReport.disabled = true;
            postJson(urlFor(updateTpl, projectId, itemId), {
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
            return;
        }

        var select = e.target.closest('[data-sc-status]');
        if (select) {
            var item = select.closest('[data-sc-plan-sub], [data-sc-plan-item]');
            if (!item || item.classList.contains('is-busy')) return;
            setStatus(item, select.value);
            return;
        }

        var subDone = e.target.closest('[data-sc-plan-sub-done]');
        if (!subDone) return;
        var sub = subDone.closest('[data-sc-plan-sub]');
        if (!sub || sub.classList.contains('is-busy')) return;
        var next = subDone.checked ? 'done' : 'todo';
        setStatus(sub, next);
    });

    (function initPlanFilters() {
        var projectSelect = root.querySelector('[data-sc-plan-project]');
        var emptyEl = root.querySelector('[data-sc-plan-filter-empty]');
        var presetBtns = Array.prototype.slice.call(root.querySelectorAll('[data-sc-plan-preset]'));
        var searchToggle = root.querySelector('[data-sc-plan-search-toggle]');
        var searchInput = root.querySelector('[data-sc-plan-search]');
        var searchPanel = root.querySelector('[data-sc-plan-search-panel]');
        var filtersRoot = root.querySelector('[data-sc-plan-filters]');
        if (!projectSelect && !presetBtns.length && !searchInput) return;

        var activePresets = [];
        var activeRailChip = null; // { stage: string, chip: string }
        var currentSearch = '';

        function normalizeSearch(value) {
            return String(value || '').toLowerCase().replace(/\s+/g, ' ').trim();
        }

        function itemTitleText(item) {
            var domain = item.querySelector('.cabinet-sc-plan__domain');
            var title = item.querySelector('.cabinet-sc-plan__task, [data-sc-title]');
            var parts = [];
            if (domain) parts.push(domain.textContent || '');
            if (title) parts.push(title.textContent || '');
            return normalizeSearch(parts.join(' '));
        }

        function subTitleText(sub) {
            var title = sub.querySelector('[data-sc-title]');
            return normalizeSearch(title ? title.textContent : '');
        }

        function setSearchOpen(open) {
            if (!searchToggle || !searchInput || !searchPanel) return;
            searchPanel.hidden = !open;
            searchToggle.classList.toggle('is-active', open);
            searchToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (filtersRoot) {
                filtersRoot.classList.toggle('is-search-open', open);
                filtersRoot.classList.toggle('has-search-query', !!currentSearch);
            }
            if (open) {
                try { searchInput.focus(); searchInput.select(); } catch (e) {}
            } else if (!currentSearch) {
                searchInput.value = '';
            }
            try {
                window.dispatchEvent(new Event('resize'));
            } catch (e) { /* ignore */ }
        }

        function isSearchOpen() {
            return !!(searchPanel && !searchPanel.hidden);
        }

        function readQuery() {
            try {
                var params = new URLSearchParams(window.location.search);
                var project = params.get('project') || '';
                var preset = params.get('preset') || '';
                var chip = params.get('chip') || '';
                var chipStage = params.get('chip_stage') || '';
                var q = params.get('q') || '';
                if (projectSelect && project) {
                    projectSelect.value = project;
                }
                if (q && searchInput) {
                    currentSearch = normalizeSearch(q);
                    searchInput.value = q;
                    setSearchOpen(true);
                }
                if (preset && preset !== 'all') {
                    activePresets = preset.split(',').map(function (p) {
                        return String(p || '').trim();
                    }).filter(Boolean);
                    if (activePresets.indexOf('done') !== -1) {
                        activePresets = ['done'];
                    } else if (activePresets.indexOf('review') !== -1) {
                        activePresets = ['review'];
                    } else {
                        activePresets = activePresets.filter(function (p, i, arr) {
                            return arr.indexOf(p) === i;
                        });
                        if (activePresets.indexOf('no-review') !== -1 && activePresets.indexOf('review') !== -1) {
                            activePresets = activePresets.filter(function (p) { return p !== 'review'; });
                        }
                    }
                }
                if (chip && chipStage) {
                    activeRailChip = { stage: chipStage, chip: chip };
                }
            } catch (e) {}
        }

        function writeQuery() {
            try {
                if (!window.history || !window.history.replaceState) return;
                var params = new URLSearchParams(window.location.search);
                var project = projectSelect ? String(projectSelect.value || '') : '';
                if (project) params.set('project', project);
                else params.delete('project');
                if (activePresets.length) params.set('preset', activePresets.join(','));
                else params.delete('preset');
                if (currentSearch) params.set('q', currentSearch);
                else params.delete('q');
                if (activeRailChip && activeRailChip.chip && activeRailChip.stage) {
                    params.set('chip', activeRailChip.chip);
                    params.set('chip_stage', activeRailChip.stage);
                } else {
                    params.delete('chip');
                    params.delete('chip_stage');
                }
                var q = params.toString();
                window.history.replaceState({}, '', window.location.pathname + (q ? '?' + q : '') + window.location.hash);
            } catch (e) {}
        }

        function syncPresetButtons() {
            var hasFacet = activePresets.length > 0;
            presetBtns.forEach(function (btn) {
                var key = btn.getAttribute('data-sc-plan-preset');
                var on = key === 'all' ? !hasFacet : activePresets.indexOf(key) !== -1;
                btn.classList.toggle('active', on);
                btn.setAttribute('aria-pressed', on ? 'true' : 'false');
            });
        }

        function syncRailChips() {
            root.querySelectorAll('[data-sc-plan-rail-chip]').forEach(function (btn) {
                var chip = btn.getAttribute('data-sc-plan-rail-chip');
                var stage = btn.getAttribute('data-sc-plan-rail-chip-stage');
                var on = !!(activeRailChip
                    && activeRailChip.chip === chip
                    && activeRailChip.stage === stage);
                btn.classList.toggle('is-active', on);
                btn.setAttribute('aria-pressed', on ? 'true' : 'false');
            });
        }

        function itemMatchesRailChip(item, chip) {
            if (!chip) return true;
            var status = String(item.getAttribute('data-status') || '');
            if (status === 'blocked') status = 'clarify';
            if (chip === 'doing' || chip === 'rework' || chip === 'clarify' || chip === 'review') {
                return status === chip;
            }
            if (chip === 'done') {
                return status === 'done' || status === 'skip';
            }
            if (chip === 'overdue') return item.getAttribute('data-overdue') === '1';
            if (chip === 'due_soon') {
                return item.getAttribute('data-due-soon') === '1'
                    || item.getAttribute('data-overdue') === '1';
            }
            if (chip === 'later') return item.getAttribute('data-later') === '1';
            return true;
        }

        function applyFilters() {
            var projectId = projectSelect ? String(projectSelect.value || '') : '';
            var hasPreset = activePresets.length > 0;
            var search = currentSearch;
            var visibleTotal = 0;

            syncPresetButtons();
            syncRailChips();

            root.querySelectorAll('[data-sc-plan-group]').forEach(function (group) {
                var visibleInGroup = 0;
                var groupKey = group.getAttribute('data-sc-plan-group');
                group.querySelectorAll('[data-sc-plan-item]').forEach(function (item) {
                    var show = true;
                    if (projectId && String(item.getAttribute('data-project-id') || '') !== projectId) {
                        show = false;
                    }
                    if (show && activeRailChip) {
                        if (activeRailChip.stage !== groupKey) {
                            show = false;
                        } else {
                            show = itemMatchesRailChip(item, activeRailChip.chip);
                        }
                    }
                    if (show && hasPreset) {
                        var status = String(item.getAttribute('data-status') || '');
                        var isDone = status === 'done' || status === 'skip';
                        var isReview = status === 'review';
                        var wantDone = activePresets.indexOf('done') !== -1;
                        var wantReview = activePresets.indexOf('review') !== -1;
                        var hideReview = activePresets.indexOf('no-review') !== -1;
                        if (wantDone) {
                            if (!isDone) show = false;
                        } else if (wantReview) {
                            if (!isReview) show = false;
                        } else if (isDone) {
                            show = false;
                        }
                        var important = item.getAttribute('data-important') === '1';
                        var overdue = item.getAttribute('data-overdue') === '1';
                        var dueSoon = item.getAttribute('data-due-soon') === '1';
                        var unreadNotes = (parseInt(item.getAttribute('data-unread-notes-count') || '0', 10) || 0) > 0;
                        var dueFilters = [];
                        var facetMode = !wantDone && !wantReview;
                        if (facetMode && activePresets.indexOf('overdue') !== -1) dueFilters.push('overdue');
                        if (facetMode && activePresets.indexOf('due-soon') !== -1) dueFilters.push('due-soon');
                        if (show && facetMode && hideReview && isReview) {
                            show = false;
                        }
                        if (show && facetMode && activePresets.indexOf('important') !== -1 && !important) {
                            show = false;
                        }
                        if (show && facetMode && activePresets.indexOf('unread-notes') !== -1 && !unreadNotes) {
                            show = false;
                        }
                        if (show && dueFilters.length) {
                            var dueOk = false;
                            if (dueFilters.indexOf('overdue') !== -1 && overdue) dueOk = true;
                            if (dueFilters.indexOf('due-soon') !== -1 && (dueSoon || overdue)) dueOk = true;
                            if (!dueOk) show = false;
                        }
                    } else if (show) {
                        // «Все» — без закрытых; закрытые только через пресет «Выполненные»
                        var st = String(item.getAttribute('data-status') || '');
                        if (st === 'done' || st === 'skip') show = false;
                    }

                    var parentMatched = false;
                    var anySubMatched = false;
                    if (show && search) {
                        parentMatched = itemTitleText(item).indexOf(search) !== -1;
                        item.querySelectorAll('[data-sc-plan-sub]').forEach(function (sub) {
                            if (subTitleText(sub).indexOf(search) !== -1) anySubMatched = true;
                        });
                        if (!parentMatched && !anySubMatched) show = false;
                    }

                    item.classList.toggle('is-filter-hidden', !show);
                    item.hidden = !show;
                    var hideReviewSubs = activePresets.indexOf('no-review') !== -1
                        && activePresets.indexOf('review') === -1
                        && activePresets.indexOf('done') === -1;
                    item.querySelectorAll('[data-sc-plan-sub]').forEach(function (sub) {
                        var hideSub = hideReviewSubs
                            && String(sub.getAttribute('data-status') || '') === 'review';
                        if (!hideSub && show && search && !parentMatched) {
                            hideSub = subTitleText(sub).indexOf(search) === -1;
                        }
                        sub.classList.toggle('is-filter-hidden', hideSub);
                        sub.hidden = hideSub;
                    });
                    if (show && search && anySubMatched) {
                        var subsBlock = item.querySelector('[data-sc-plan-subs]');
                        if (subsBlock) {
                            subsBlock.classList.remove('d-none', 'is-empty');
                            var list = subsBlock.querySelector('[data-sc-plan-subs-list]');
                            if (list) list.hidden = false;
                            var head = subsBlock.querySelector('[data-sc-plan-subs-head]');
                            if (head) head.classList.remove('d-none');
                        }
                    }
                    if (show) visibleInGroup += 1;
                });
                var countEl = group.querySelector('[data-sc-plan-count]');
                if (countEl) countEl.textContent = formatCount(visibleInGroup);
                group.classList.toggle('is-filter-hidden', visibleInGroup === 0);
                group.hidden = visibleInGroup === 0;
                visibleTotal += visibleInGroup;
                var key = group.getAttribute('data-sc-plan-group');
                var railLink = key
                    ? root.querySelector('[data-sc-plan-rail-link="' + key + '"]')
                    : null;
                if (railLink) {
                    var railCount = railLink.querySelector('.cabinet-sc-plan-rail__count');
                    if (railCount) railCount.textContent = formatCount(visibleInGroup);
                    var railLi = railLink.closest('li');
                    if (railLi) {
                        railLi.hidden = visibleInGroup === 0;
                        railLi.classList.toggle('is-filter-hidden', visibleInGroup === 0);
                    }
                }
            });

            if (emptyEl) {
                emptyEl.classList.toggle('is-filter-hidden', visibleTotal > 0);
                emptyEl.hidden = visibleTotal > 0;
            }
            writeQuery();
        }

        function togglePreset(key) {
            if (!key || key === 'all') {
                activePresets = [];
                activeRailChip = null;
                applyFilters();
                return;
            }
            var idx = activePresets.indexOf(key);
            if (idx === -1) {
                if (key === 'done' || key === 'review') {
                    // отдельные режимы: только выполненные / только на проверку
                    activePresets = [key];
                    activeRailChip = null;
                } else {
                    activePresets = activePresets.filter(function (p) {
                        return p !== 'done' && p !== 'review';
                    });
                    if (key === 'no-review') {
                        activePresets = activePresets.filter(function (p) { return p !== 'review'; });
                    }
                    activePresets.push(key);
                }
            } else {
                activePresets.splice(idx, 1);
            }
            applyFilters();
        }

        function toggleRailChip(stage, chip) {
            if (!stage || !chip) return;
            if (activeRailChip
                && activeRailChip.stage === stage
                && activeRailChip.chip === chip) {
                activeRailChip = null;
            } else {
                activeRailChip = { stage: stage, chip: chip };
            }
            applyFilters();
            var section = root.querySelector('[data-sc-plan-group="' + stage + '"]');
            if (section && !section.hidden) {
                try {
                    section.scrollIntoView({ behavior: 'smooth', block: 'start' });
                } catch (e) {
                    section.scrollIntoView(true);
                }
            }
        }

        readQuery();
        if (projectSelect) {
            if (window.jQuery) {
                window.jQuery(projectSelect).on('change', applyFilters);
            } else {
                projectSelect.addEventListener('change', applyFilters);
            }
        }
        if (searchToggle && searchInput && searchPanel) {
            searchToggle.addEventListener('click', function () {
                var open = !isSearchOpen();
                if (!open && currentSearch) {
                    currentSearch = '';
                    searchInput.value = '';
                    if (filtersRoot) filtersRoot.classList.remove('has-search-query');
                    setSearchOpen(false);
                    applyFilters();
                    return;
                }
                setSearchOpen(open);
                if (!open) applyFilters();
            });
            searchInput.addEventListener('input', function () {
                currentSearch = normalizeSearch(searchInput.value);
                if (filtersRoot) filtersRoot.classList.toggle('has-search-query', !!currentSearch);
                applyFilters();
            });
            searchInput.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') {
                    e.preventDefault();
                    currentSearch = '';
                    searchInput.value = '';
                    if (filtersRoot) filtersRoot.classList.remove('has-search-query');
                    setSearchOpen(false);
                    applyFilters();
                }
            });
        }
        presetBtns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                togglePreset(btn.getAttribute('data-sc-plan-preset'));
            });
        });
        root.querySelectorAll('[data-sc-plan-rail-chip]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                toggleRailChip(
                    btn.getAttribute('data-sc-plan-rail-chip-stage'),
                    btn.getAttribute('data-sc-plan-rail-chip')
                );
            });
        });
        root.addEventListener('cabinet-sc-plan-notes-changed', function () {
            applyFilters();
        });
        root.addEventListener('cabinet-sc-plan-filters-refresh', function () {
            applyFilters();
        });
        applyFilters();
    })();

    window.setInterval(function () {
        root.querySelectorAll('[data-sc-plan-item][data-timer-running="1"], [data-sc-plan-sub][data-timer-running="1"]').forEach(function (itemEl) {
            var targets = timerTargets(itemEl);
            if (targets.timeEl) targets.timeEl.textContent = formatDuration(itemDisplaySeconds(itemEl));
        });
    }, 1000);

    // —— V2: плавающая рейка «Этапы» (скролл у window или .app-main) ——
    (function initPlanRailPin() {
        var slot = root.querySelector('[data-sc-plan-rail-slot]');
        var rail = root.querySelector('[data-sc-plan-rail]');
        var layout = root.querySelector('.cabinet-sc-plan-layout');
        if (!slot || !rail || !layout) return;

        var topGap = 12;
        var bottomGap = 16;
        var ticking = false;
        var mobileMq = window.matchMedia('(max-width: 900px)');
        var mainEl = document.querySelector('.app-main');

        function unpin() {
            rail.classList.remove('is-pinned');
            rail.style.top = '';
            rail.style.left = '';
            rail.style.width = '';
            rail.style.height = '';
            rail.style.maxHeight = '';
            slot.style.height = '';
            slot.style.maxHeight = '';
        }

        function viewportMetrics() {
            var mainScrolls = !!(mainEl && mainEl.scrollHeight > mainEl.clientHeight + 5);
            if (mainScrolls) {
                var mr = mainEl.getBoundingClientRect();
                return {
                    stickLine: mr.top + topGap,
                    viewH: mainEl.clientHeight || mr.height,
                    minTop: mr.top + 8
                };
            }
            return {
                stickLine: topGap,
                viewH: window.innerHeight || document.documentElement.clientHeight || 800,
                minTop: 8
            };
        }

        function contentHeight(maxH) {
            var head = rail.querySelector('.cabinet-sc-plan-rail__head');
            var list = rail.querySelector('.cabinet-sc-plan-rail__list');
            var h = 20;
            if (head) h += head.offsetHeight || 0;
            if (list) h += list.scrollHeight || list.offsetHeight || 0;
            return Math.min(Math.max(h, 120), maxH);
        }

        function sync() {
            if (!root.classList.contains('cabinet-sc-plan-v2') || mobileMq.matches) {
                unpin();
                return;
            }

            var metrics = viewportMetrics();
            var maxH = Math.max(160, Math.floor(metrics.viewH - topGap - bottomGap));
            var layoutRect = layout.getBoundingClientRect();
            var slotRect = slot.getBoundingClientRect();
            var stickLine = metrics.stickLine;

            slot.style.maxHeight = maxH + 'px';

            if (layoutRect.bottom <= stickLine + 40) {
                unpin();
                slot.style.maxHeight = maxH + 'px';
                return;
            }

            if (slotRect.top > stickLine + 1) {
                if (rail.classList.contains('is-pinned')) {
                    unpin();
                    slot.style.maxHeight = maxH + 'px';
                }
                rail.style.maxHeight = maxH + 'px';
                return;
            }

            var naturalH = contentHeight(maxH);
            var pinTop = stickLine;
            var maxTop = layoutRect.bottom - bottomGap - naturalH;
            if (maxTop < pinTop) {
                pinTop = Math.max(metrics.minTop, maxTop);
            }

            slot.style.height = naturalH + 'px';
            slotRect = slot.getBoundingClientRect();

            rail.classList.add('is-pinned');
            rail.style.left = Math.round(slotRect.left) + 'px';
            rail.style.width = Math.round(slotRect.width) + 'px';
            rail.style.top = Math.round(pinTop) + 'px';
            rail.style.height = Math.round(naturalH) + 'px';
            rail.style.maxHeight = Math.round(naturalH) + 'px';
        }

        function requestSync() {
            if (ticking) return;
            ticking = true;
            window.requestAnimationFrame(function () {
                ticking = false;
                sync();
            });
        }

        function syncActiveLink() {
            var links = Array.prototype.slice.call(rail.querySelectorAll('[data-sc-plan-rail-link]'));
            if (!links.length) return;
            var best = null;
            var bestTop = -Infinity;
            links.forEach(function (link) {
                var key = link.getAttribute('data-sc-plan-rail-link');
                var section = root.querySelector('[data-sc-plan-group="' + key + '"]');
                if (!section || section.classList.contains('is-filter-hidden')) return;
                var top = section.getBoundingClientRect().top;
                if (top <= 140 && top > bestTop) {
                    bestTop = top;
                    best = link;
                }
            });
            links.forEach(function (link) {
                link.classList.toggle('is-active', link === best);
            });
        }

        function onScroll() {
            requestSync();
            syncActiveLink();
        }

        if (mainEl) {
            mainEl.addEventListener('scroll', onScroll, { passive: true });
        }
        window.addEventListener('scroll', onScroll, { passive: true, capture: true });
        window.addEventListener('resize', requestSync);
        window.addEventListener('cabinet-sc-plan-visual', function () {
            requestSync();
            syncActiveLink();
        });
        if (mobileMq.addEventListener) {
            mobileMq.addEventListener('change', requestSync);
        } else if (mobileMq.addListener) {
            mobileMq.addListener(requestSync);
        }

        rail.querySelectorAll('[data-sc-plan-rail-link]').forEach(function (link) {
            link.addEventListener('click', function () {
                rail.querySelectorAll('[data-sc-plan-rail-link]').forEach(function (other) {
                    other.classList.remove('is-active');
                });
                link.classList.add('is-active');
            });
        });

        requestSync();
        window.setTimeout(requestSync, 50);
        window.setTimeout(requestSync, 300);
    })();

    // —— V2: липкая полоса фильтров (CSS sticky ломается из‑за .app-main overflow) ——
    (function initPlanFiltersPin() {
        var filters = root.querySelector('[data-sc-plan-filters]');
        var workspace = root.querySelector('.cabinet-sc-plan-workspace');
        if (!filters || !workspace) return;

        var topGap = 10;
        var ticking = false;
        var mobileMq = window.matchMedia('(max-width: 900px)');
        var mainEl = document.querySelector('.app-main');
        var spacer = document.createElement('div');
        spacer.setAttribute('data-sc-plan-filters-spacer', '');
        spacer.style.display = 'none';
        filters.parentNode.insertBefore(spacer, filters);

        function unpin() {
            filters.classList.remove('is-pinned');
            filters.style.top = '';
            filters.style.left = '';
            filters.style.width = '';
            filters.style.maxWidth = '';
            filters.style.boxSizing = '';
            spacer.style.display = 'none';
            spacer.style.height = '';
        }

        function stickLine() {
            var mainScrolls = !!(mainEl && mainEl.scrollHeight > mainEl.clientHeight + 5);
            if (mainScrolls) {
                return mainEl.getBoundingClientRect().top + topGap;
            }
            return topGap;
        }

        function sync() {
            if (!root.classList.contains('cabinet-sc-plan-v2')) {
                unpin();
                return;
            }
            var line = stickLine();
            var workspaceRect = workspace.getBoundingClientRect();
            var refTop = filters.classList.contains('is-pinned')
                ? spacer.getBoundingClientRect().top
                : filters.getBoundingClientRect().top;

            if (workspaceRect.bottom <= line + 60) {
                unpin();
                return;
            }

            if (refTop > line + 1) {
                unpin();
                return;
            }

            var width = workspaceRect.width;
            var left = filters.classList.contains('is-pinned')
                ? spacer.getBoundingClientRect().left
                : workspaceRect.left;
            var height = filters.offsetHeight;

            spacer.style.display = 'block';
            spacer.style.height = height + 'px';
            filters.classList.add('is-pinned');
            filters.style.left = Math.round(left) + 'px';
            filters.style.width = Math.round(width) + 'px';
            filters.style.maxWidth = Math.round(width) + 'px';
            filters.style.top = Math.round(line) + 'px';
            filters.style.boxSizing = 'border-box';
        }

        function requestSync() {
            if (ticking) return;
            ticking = true;
            window.requestAnimationFrame(function () {
                ticking = false;
                sync();
            });
        }

        if (mainEl) {
            mainEl.addEventListener('scroll', requestSync, { passive: true });
        }
        window.addEventListener('scroll', requestSync, { passive: true, capture: true });
        window.addEventListener('resize', requestSync);
        window.addEventListener('cabinet-sc-plan-visual', requestSync);
        if (mobileMq.addEventListener) {
            mobileMq.addEventListener('change', requestSync);
        } else if (mobileMq.addListener) {
            mobileMq.addListener(requestSync);
        }

        requestSync();
        window.setTimeout(requestSync, 80);
    })();

    // Подсказки чипов этапов: fixed, чтобы не резало overflow рейла
    (function initPlanRailChipTips() {
        var tipEl = null;
        var activeChip = null;

        function ensureTip() {
            if (tipEl) return tipEl;
            tipEl = document.createElement('div');
            tipEl.className = 'cabinet-sc-plan-rail-tip';
            tipEl.hidden = true;
            document.body.appendChild(tipEl);
            return tipEl;
        }

        function hideTip() {
            activeChip = null;
            if (tipEl) tipEl.hidden = true;
        }

        function placeTip(chip) {
            var text = String(chip.getAttribute('data-tip') || '').trim();
            if (!text) {
                hideTip();
                return;
            }
            var el = ensureTip();
            el.textContent = text;
            el.hidden = false;
            activeChip = chip;
            var rect = chip.getBoundingClientRect();
            var pad = 8;
            var tipW = el.offsetWidth || 160;
            var tipH = el.offsetHeight || 36;
            var left = rect.right + pad;
            var top = rect.top + (rect.height / 2) - (tipH / 2);
            if (left + tipW > window.innerWidth - pad) {
                left = Math.max(pad, rect.left - tipW - pad);
            }
            if (top < pad) top = pad;
            if (top + tipH > window.innerHeight - pad) {
                top = Math.max(pad, window.innerHeight - tipH - pad);
            }
            el.style.left = Math.round(left) + 'px';
            el.style.top = Math.round(top) + 'px';
        }

        root.addEventListener('mouseover', function (e) {
            var chip = e.target.closest('[data-sc-plan-rail-chip][data-tip]');
            if (!chip || !root.contains(chip)) return;
            placeTip(chip);
        });
        root.addEventListener('mouseout', function (e) {
            var chip = e.target.closest('[data-sc-plan-rail-chip][data-tip]');
            if (!chip || !activeChip) return;
            var to = e.relatedTarget;
            if (to && chip.contains(to)) return;
            if (activeChip === chip) hideTip();
        });
        root.addEventListener('focusin', function (e) {
            var chip = e.target.closest('[data-sc-plan-rail-chip][data-tip]');
            if (chip) placeTip(chip);
        });
        root.addEventListener('focusout', function (e) {
            var chip = e.target.closest('[data-sc-plan-rail-chip][data-tip]');
            if (chip && activeChip === chip) hideTip();
        });
        window.addEventListener('scroll', hideTip, true);
        window.addEventListener('resize', hideTip);
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
            return Array.prototype.slice.call(list.querySelectorAll(':scope > [data-sc-plan-sub]'));
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
            var parentItem = li.closest('[data-sc-plan-item]');
            if (!parentItem) return;
            var projectId = parentItem.getAttribute('data-project-id') || li.getAttribute('data-project-id');
            var parentId = parentItem.getAttribute('data-id');
            var ids = idsOf(list);
            if (!projectId || !parentId || ids.length < 1) return;
            if (ids.join(',') === (originIds || []).join(',')) {
                li.classList.remove('is-dragging');
                return;
            }
            dragSaving = true;
            li.classList.add('is-busy');
            postJson(urlFor(subtaskReorderTpl, projectId, parentId), {
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
            var li = handle.closest('[data-sc-plan-sub]');
            var list = li && li.closest('[data-sc-plan-subs-list]');
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
            var over = e.target.closest('[data-sc-plan-sub]');
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
