(function () {
    var csrfMeta = document.querySelector('meta[name="csrf-token"]');
    var csrf = csrfMeta ? csrfMeta.getAttribute('content') : '';

    function postJson(url, payload) {
        return fetch(url, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify(payload || {}),
        }).then(function (r) {
            return r.json().then(function (data) {
                return { ok: r.ok && data && data.ok, data: data };
            });
        });
    }

    function noteRoot(el) {
        return el && el.closest ? el.closest('[data-sc-note]') : null;
    }

    function openEditor(root) {
        var view = root.querySelector('[data-sc-note-view]');
        var editor = root.querySelector('[data-sc-note-editor]');
        var raw = root.querySelector('[data-sc-note-raw]');
        var field = root.querySelector('[data-sc-note-editor-body]');
        if (!editor || !field) return;
        field.value = raw ? raw.value : '';
        if (view) view.classList.add('d-none');
        editor.classList.remove('d-none');
        field.focus();
    }

    function closeEditor(root) {
        var view = root.querySelector('[data-sc-note-view]');
        var editor = root.querySelector('[data-sc-note-editor]');
        if (view) view.classList.remove('d-none');
        if (editor) editor.classList.add('d-none');
    }

    function dropNoteCount(root) {
        var item = root.closest('[data-sc-task], [data-sc-plan-item], [data-id]');
        if (!item || !item.hasAttribute('data-notes-count')) return;
        var total = Math.max(0, (parseInt(item.getAttribute('data-notes-count') || '0', 10) || 0) - 1);
        var unread = parseInt(item.getAttribute('data-unread-notes-count') || '0', 10) || 0;
        if (root.hasAttribute('data-sc-note-unread')) {
            unread = Math.max(0, unread - 1);
        }
        item.setAttribute('data-notes-count', String(total));
        item.setAttribute('data-unread-notes-count', String(unread));
        var btn = item.querySelector('[data-sc-toggle-notes]');
        if (!btn) return;
        var countEl = btn.querySelector('[data-sc-notes-count]');
        btn.classList.toggle('has-notes', total > 0);
        btn.classList.toggle('has-unread', unread > 0);
        if (!countEl) return;
        countEl.classList.remove('is-empty', 'is-unread', 'is-read');
        if (total < 1) {
            countEl.textContent = '';
            countEl.classList.add('is-empty');
            return;
        }
        if (unread > 0) {
            countEl.textContent = String(unread);
            countEl.classList.add('is-unread');
        } else {
            countEl.textContent = String(total);
            countEl.classList.add('is-read');
        }
    }

    document.addEventListener('click', function (e) {
        var historyBtn = e.target.closest('[data-sc-note-history-toggle]');
        if (historyBtn) {
            var list = historyBtn.parentElement && historyBtn.parentElement.querySelector('[data-sc-note-history]');
            if (!list) return;
            var open = list.classList.contains('d-none');
            list.classList.toggle('d-none', !open);
            historyBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
            return;
        }

        var editBtn = e.target.closest('[data-sc-note-edit]');
        if (editBtn) {
            var editRoot = noteRoot(editBtn);
            if (editRoot) openEditor(editRoot);
            return;
        }

        var cancelBtn = e.target.closest('[data-sc-note-editor-cancel]');
        if (cancelBtn) {
            var cancelRoot = noteRoot(cancelBtn);
            if (cancelRoot) closeEditor(cancelRoot);
            return;
        }

        var saveBtn = e.target.closest('[data-sc-note-editor-save]');
        if (saveBtn) {
            var saveRoot = noteRoot(saveBtn);
            if (!saveRoot || saveBtn.disabled) return;
            var url = saveRoot.getAttribute('data-note-update-url');
            var field = saveRoot.querySelector('[data-sc-note-editor-body]');
            if (!url || !field) return;
            saveBtn.disabled = true;
            postJson(url, { body: String(field.value || '').trim() })
                .then(function (result) {
                    saveBtn.disabled = false;
                    if (!result.ok || !result.data.html) {
                        window.alert((result.data && result.data.message) || 'Error');
                        return;
                    }
                    saveRoot.outerHTML = result.data.html;
                })
                .catch(function () {
                    saveBtn.disabled = false;
                });
            return;
        }

        var deleteBtn = e.target.closest('[data-sc-note-delete]');
        if (!deleteBtn) return;
        var deleteRoot = noteRoot(deleteBtn);
        if (!deleteRoot) return;
        var deleteUrl = deleteRoot.getAttribute('data-note-delete-url');
        if (!deleteUrl) return;

        function runDelete() {
            deleteBtn.disabled = true;
            postJson(deleteUrl, {})
                .then(function (result) {
                    deleteBtn.disabled = false;
                    if (!result.ok) {
                        window.alert((result.data && result.data.message) || 'Error');
                        return;
                    }
                    if (!deleteRoot.classList.contains('cabinet-sc-subtask__comment')) {
                        dropNoteCount(deleteRoot);
                    }
                    deleteRoot.remove();
                })
                .catch(function () {
                    deleteBtn.disabled = false;
                });
        }

        if (!window.CabinetConfirm || typeof window.CabinetConfirm.show !== 'function') {
            return;
        }
        window.CabinetConfirm.show({
            title: deleteBtn.getAttribute('data-confirm-title') || '',
            text: deleteBtn.getAttribute('data-confirm-text') || '',
            okLabel: deleteBtn.getAttribute('data-confirm-ok') || '',
            danger: true,
            onConfirm: runDelete,
        });
    });
})();
