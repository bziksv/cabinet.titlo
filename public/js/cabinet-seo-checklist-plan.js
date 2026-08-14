(function () {
    var root = document.getElementById('cabinetSeoChecklistPlan');
    if (!root) return;

    var csrf = root.getAttribute('data-csrf') || '';
    var statusTpl = root.getAttribute('data-status-url-template') || '';
    var noteTpl = root.getAttribute('data-note-url-template') || '';
    var timerStartTpl = root.getAttribute('data-timer-start-url-template') || '';
    var timerStopTpl = root.getAttribute('data-timer-stop-url-template') || '';
    var labelStart = root.getAttribute('data-i18n-timer-start') || 'Start timer';
    var labelStop = root.getAttribute('data-i18n-timer-stop') || 'Stop timer';
    var labelStartShort = root.getAttribute('data-i18n-timer-start-short') || 'Start';
    var labelStopShort = root.getAttribute('data-i18n-timer-stop-short') || 'Stop';
    var commentRequired = root.getAttribute('data-i18n-comment-required') || 'Comment required';
    var chooseStatusLabel = root.getAttribute('data-i18n-choose-status') || 'Choose task status';

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
            if (countEl) countEl.textContent = String(n);
            group.style.display = n === 0 ? 'none' : '';
        });
    }

    function refreshSubCounts(subsRoot) {
        if (!subsRoot) return;
        var all = subsRoot.querySelectorAll('[data-sc-plan-sub]').length;
        var open = subsRoot.querySelectorAll('[data-sc-plan-sub]:not(.is-done)').length;
        var meta = subsRoot.querySelector('[data-sc-plan-subs-count]');
        if (meta) meta.textContent = open + '/' + all;
    }

    function removeItem(itemEl) {
        var group = itemEl.closest('[data-sc-plan-group]');
        itemEl.parentNode.removeChild(itemEl);
        if (group) refreshGroupCounts();
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
        var hint = itemEl.querySelector('[data-sc-review-hint]');
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
            refreshSubCounts(itemEl.closest('.cabinet-sc-plan__subs'));
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
                alert((result.data && result.data.message) || 'Error');
                return;
            }
            var next = result.data.item.status;
            applyStatusUi(itemEl, next);
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
            // закрытые основные задачи убираем из плана; пункты чеклиста остаются
            if (!itemEl.hasAttribute('data-sc-plan-sub') && (next === 'done' || next === 'skip')) {
                removeItem(itemEl);
            }
        }).catch(function () {
            itemEl.classList.remove('is-busy');
            var select = itemEl.querySelector('[data-sc-status]');
            if (select) select.value = itemEl.getAttribute('data-status') || 'todo';
            alert('Error');
        });
    }

    function bumpNotesCount(itemEl) {
        if (!itemEl) return;
        var btn = itemEl.querySelector('[data-sc-toggle-notes]');
        if (!btn) return;
        var countEl = btn.querySelector('[data-sc-notes-count]');
        var n = countEl ? (parseInt(countEl.textContent || '0', 10) || 0) + 1 : 1;
        if (!countEl) {
            countEl = document.createElement('span');
            countEl.className = 'cabinet-sc-plan__notes-count';
            countEl.setAttribute('data-sc-notes-count', '');
            btn.appendChild(countEl);
        }
        countEl.textContent = String(n);
    }

    root.addEventListener('click', function (e) {
        var timerBtn = e.target.closest('[data-sc-timer]');
        if (timerBtn) {
            e.preventDefault();
            var item = timerBtn.closest('[data-sc-plan-sub], [data-sc-plan-item]');
            if (item && !item.classList.contains('is-busy')) toggleTimer(item);
            return;
        }

        var toggleNotes = e.target.closest('[data-sc-toggle-notes]');
        if (toggleNotes) {
            e.preventDefault();
            var notesItem = toggleNotes.closest('[data-sc-plan-item]');
            if (!notesItem) return;
            var notesBox = notesItem.querySelector('[data-sc-notes]');
            if (notesBox) notesBox.classList.toggle('d-none');
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

    root.addEventListener('change', function (e) {
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
        if (!projectSelect && !presetBtns.length) return;

        var activePresets = [];

        function readQuery() {
            try {
                var params = new URLSearchParams(window.location.search);
                var project = params.get('project') || '';
                var preset = params.get('preset') || '';
                if (projectSelect && project) {
                    projectSelect.value = project;
                }
                if (preset && preset !== 'all') {
                    activePresets = preset.split(',').map(function (p) {
                        return String(p || '').trim();
                    }).filter(Boolean);
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

        function applyFilters() {
            var projectId = projectSelect ? String(projectSelect.value || '') : '';
            var hasPreset = activePresets.length > 0;
            var visibleTotal = 0;

            syncPresetButtons();

            root.querySelectorAll('[data-sc-plan-group]').forEach(function (group) {
                var visibleInGroup = 0;
                group.querySelectorAll('[data-sc-plan-item]').forEach(function (item) {
                    var show = true;
                    if (projectId && String(item.getAttribute('data-project-id') || '') !== projectId) {
                        show = false;
                    }
                    if (show && hasPreset) {
                        var important = item.getAttribute('data-important') === '1';
                        var overdue = item.getAttribute('data-overdue') === '1';
                        var dueSoon = item.getAttribute('data-due-soon') === '1';
                        var dueFilters = [];
                        if (activePresets.indexOf('overdue') !== -1) dueFilters.push('overdue');
                        if (activePresets.indexOf('due-soon') !== -1) dueFilters.push('due-soon');
                        if (activePresets.indexOf('important') !== -1 && !important) {
                            show = false;
                        }
                        if (show && dueFilters.length) {
                            var dueOk = false;
                            if (dueFilters.indexOf('overdue') !== -1 && overdue) dueOk = true;
                            if (dueFilters.indexOf('due-soon') !== -1 && (dueSoon || overdue)) dueOk = true;
                            if (!dueOk) show = false;
                        }
                    }
                    item.classList.toggle('is-filter-hidden', !show);
                    item.hidden = !show;
                    if (show) visibleInGroup += 1;
                });
                var countEl = group.querySelector('[data-sc-plan-count]');
                if (countEl) countEl.textContent = String(visibleInGroup);
                group.classList.toggle('is-filter-hidden', visibleInGroup === 0);
                group.hidden = visibleInGroup === 0;
                visibleTotal += visibleInGroup;
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
                applyFilters();
                return;
            }
            var idx = activePresets.indexOf(key);
            if (idx === -1) activePresets.push(key);
            else activePresets.splice(idx, 1);
            applyFilters();
        }

        readQuery();
        if (projectSelect) {
            if (window.jQuery) {
                window.jQuery(projectSelect).on('change', applyFilters);
            } else {
                projectSelect.addEventListener('change', applyFilters);
            }
        }
        presetBtns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                togglePreset(btn.getAttribute('data-sc-plan-preset'));
            });
        });
        applyFilters();
    })();

    window.setInterval(function () {
        root.querySelectorAll('[data-sc-plan-item][data-timer-running="1"], [data-sc-plan-sub][data-timer-running="1"]').forEach(function (itemEl) {
            var targets = timerTargets(itemEl);
            if (targets.timeEl) targets.timeEl.textContent = formatDuration(itemDisplaySeconds(itemEl));
        });
    }, 1000);
})();
