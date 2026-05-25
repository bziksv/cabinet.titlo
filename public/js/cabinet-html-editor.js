/**
 * HTML-редактор — список проектов, поиск, split CKEditor/HTML, пресеты.
 */
(function (window, document) {
    'use strict';

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

    function plainTextLength(html) {
        var div = document.createElement('div');
        div.innerHTML = html || '';
        return (div.textContent || '').replace(/\s+/g, ' ').trim().length;
    }

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function initProjectTabs(root) {
        var tabs = root.querySelectorAll('[data-he-project-tab]');
        var panels = root.querySelectorAll('[data-he-project-panel]');
        if (!tabs.length || !panels.length) {
            return null;
        }

        function activate(projectId) {
            tabs.forEach(function (tab) {
                var active = tab.getAttribute('data-he-project-tab') === String(projectId);
                tab.classList.toggle('active', active);
                tab.setAttribute('aria-selected', active ? 'true' : 'false');
            });
            panels.forEach(function (panel) {
                var show = panel.getAttribute('data-he-project-panel') === String(projectId);
                panel.hidden = !show;
            });
        }

        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                activate(tab.getAttribute('data-he-project-tab'));
            });
        });

        var initial = root.querySelector('[data-he-project-tab].active:not([hidden])')
            || root.querySelector('[data-he-project-tab]:not([hidden])');
        var params = new URLSearchParams(window.location.search);
        var urlProjectId = params.get('project');
        if (urlProjectId && root.querySelector('[data-he-project-tab="' + urlProjectId + '"]')) {
            activate(urlProjectId);
        } else if (initial) {
            if (!initial.classList.contains('active')) {
                initial.classList.add('active');
            }
            activate(initial.getAttribute('data-he-project-tab'));
        }

        return activate;
    }

    function initSearch(root, activateProject) {
        var projectInput = root.querySelector('[data-he-search-projects]');
        var textInput = root.querySelector('[data-he-search-texts]');
        if (!projectInput && !textInput) {
            return;
        }

        var tabs = root.querySelectorAll('[data-he-project-tab]');
        var rows = root.querySelectorAll('[data-he-text-row]');
        var emptyNote = root.querySelector('[data-he-search-empty]');
        var layout = root.querySelector('.cabinet-he-layout');

        function applyFilters() {
            var projectQ = projectInput ? projectInput.value.trim().toLowerCase() : '';
            var textQ = textInput ? textInput.value.trim().toLowerCase() : '';
            var projectsWithTextMatch = {};
            var anyVisibleTab = false;
            var anyVisibleRow = false;

            rows.forEach(function (row) {
                var haystack = row.getAttribute('data-he-text-search') || '';
                var textMatch = !textQ || haystack.indexOf(textQ) !== -1;
                row.hidden = !textMatch;
                if (textMatch) {
                    anyVisibleRow = true;
                    projectsWithTextMatch[row.getAttribute('data-he-text-project')] = true;
                }
            });

            tabs.forEach(function (tab) {
                var name = tab.getAttribute('data-he-project-name') || '';
                var note = tab.getAttribute('data-he-project-note') || '';
                var projectId = tab.getAttribute('data-he-project-tab');
                var projectMatch = !projectQ || name.indexOf(projectQ) !== -1 || note.indexOf(projectQ) !== -1;
                var textMatch = !textQ || !!projectsWithTextMatch[projectId];
                var show = projectMatch && textMatch;
                tab.hidden = !show;
                if (show) {
                    anyVisibleTab = true;
                }
            });

            if (typeof activateProject === 'function') {
                var activeVisible = root.querySelector('[data-he-project-tab].active:not([hidden])');
                if (!activeVisible) {
                    var firstVisible = root.querySelector('[data-he-project-tab]:not([hidden])');
                    if (firstVisible) {
                        activateProject(firstVisible.getAttribute('data-he-project-tab'));
                    }
                }
            }

            if (emptyNote) {
                var showEmpty = (projectQ || textQ) && (!anyVisibleTab || (textQ && !anyVisibleRow));
                emptyNote.classList.toggle('d-none', !showEmpty);
            }

            if (layout) {
                layout.classList.toggle('cabinet-he-search-no-results', (projectQ || textQ) && !anyVisibleTab);
            }
        }

        if (projectInput) {
            projectInput.addEventListener('input', debounce(applyFilters, 120));
        }
        if (textInput) {
            textInput.addEventListener('input', debounce(applyFilters, 120));
        }
    }

    function initPresets(root, editorApi) {
        var wrap = root.querySelector('[data-he-presets]');
        if (!wrap || !editorApi) {
            return;
        }

        var jsonEl = wrap.querySelector('[data-he-presets-json]');
        if (!jsonEl) {
            return;
        }

        var payload;
        try {
            payload = JSON.parse(jsonEl.textContent || '{}');
        } catch (e) {
            return;
        }

        var presets = payload.presets || [];
        var builtinList = wrap.querySelector('[data-he-preset-list="builtin"]');
        var userList = wrap.querySelector('[data-he-preset-list="user"]');
        var userWrap = wrap.querySelector('[data-he-user-presets-wrap]');
        var userEmpty = wrap.querySelector('[data-he-user-presets-empty]');
        var storeUrl = wrap.getAttribute('data-he-presets-store-url');
        var destroyTemplate = wrap.getAttribute('data-he-presets-destroy-url') || '';
        var confirmReplace = wrap.getAttribute('data-he-presets-confirm') || 'Replace current content?';
        var confirmAppend = wrap.getAttribute('data-he-presets-confirm-append') || 'Append preset?';
        var savedLabel = wrap.getAttribute('data-he-presets-saved') || 'Saved';
        var deletedLabel = wrap.getAttribute('data-he-presets-deleted') || 'Deleted';
        var deleteConfirmLabel = wrap.getAttribute('data-he-presets-delete-confirm') || 'Delete preset';

        function userPresets() {
            return presets.filter(function (item) {
                return !item.builtin;
            });
        }

        function builtinPresets() {
            return presets.filter(function (item) {
                return item.builtin;
            });
        }

        function insertPreset(html, append) {
            var current = editorApi.getHtml();
            if (!append && current.trim() && !window.confirm(confirmReplace)) {
                return;
            }
            if (append && current.trim() && !window.confirm(confirmAppend)) {
                return;
            }
            editorApi.setHtml(append ? current + html : html);
        }

        function updateSaveLimitUi() {
            var max = payload.max_user_presets || 20;
            var count = userPresets().length;
            payload.user_preset_count = count;
            payload.can_save_preset = count < max;
        }

        function createPresetButton(preset, deletable) {
            var group = document.createElement('div');
            group.className = 'cabinet-he-preset-item';

            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn btn-outline-secondary btn-sm';
            btn.textContent = preset.name;
            btn.addEventListener('click', function (event) {
                insertPreset(preset.html, event.shiftKey);
            });
            group.appendChild(btn);

            if (deletable) {
                var del = document.createElement('button');
                del.type = 'button';
                del.className = 'btn btn-outline-danger btn-sm cabinet-he-preset-delete';
                del.title = 'Delete';
                del.innerHTML = '<i class="bi bi-trash3" aria-hidden="true"></i>';
                del.addEventListener('click', function (event) {
                    event.stopPropagation();
                    var numericId = String(preset.id).replace(/^user:/, '');
                    if (!window.confirm(deleteConfirmLabel + ' «' + preset.name + '»?')) {
                        return;
                    }
                    fetch(destroyTemplate.replace('__ID__', numericId), {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken(),
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    }).then(function (response) {
                        if (!response.ok) {
                            return response.json().then(function (data) {
                                throw new Error(data.message || 'delete failed');
                            });
                        }
                        presets = presets.filter(function (item) {
                            return item.id !== preset.id;
                        });
                        updateSaveLimitUi();
                        renderLists();
                    }).catch(function (error) {
                        window.alert(error.message || 'Error');
                    });
                });
                group.appendChild(del);
            }

            return group;
        }

        function renderLists() {
            if (builtinList) {
                builtinList.innerHTML = '';
                builtinPresets().forEach(function (preset) {
                    builtinList.appendChild(createPresetButton(preset, false));
                });
            }

            if (userList) {
                userList.innerHTML = '';
                userPresets().forEach(function (preset) {
                    userList.appendChild(createPresetButton(preset, true));
                });
            }

            var hasUser = userPresets().length > 0;
            if (userWrap) {
                userWrap.hidden = !hasUser;
            }
            if (userEmpty) {
                userEmpty.hidden = hasUser;
            }
        }

        renderLists();

        var saveModal = root.querySelector('#cabinet-he-save-preset-modal');
        var saveBtn = root.querySelector('[data-he-preset-save-submit]');
        var nameInput = root.querySelector('#cabinet-he-preset-name');
        var saveError = root.querySelector('[data-he-preset-save-error]');
        var nameRequiredLabel = wrap.getAttribute('data-he-preset-name-required') || 'Enter preset name';

        if (saveBtn && storeUrl) {
            saveBtn.addEventListener('click', function () {
                var name = nameInput ? nameInput.value.trim() : '';
                if (!name) {
                    if (saveError) {
                        saveError.textContent = nameRequiredLabel;
                        saveError.classList.remove('d-none');
                    }
                    return;
                }

                saveBtn.disabled = true;
                fetch(storeUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        name: name,
                        html: editorApi.getHtml(),
                    }),
                }).then(function (response) {
                    return response.json().catch(function () {
                        return {};
                    }).then(function (data) {
                        if (!response.ok) {
                            throw new Error(data.message || 'save failed');
                        }
                        return data;
                    });
                }).then(function (data) {
                    if (data.preset) {
                        presets.push(data.preset);
                        updateSaveLimitUi();
                        renderLists();
                    }
                    if (nameInput) {
                        nameInput.value = '';
                    }
                    if (saveError) {
                        saveError.classList.add('d-none');
                    }
                    if (saveModal && window.bootstrap && window.bootstrap.Modal) {
                        window.bootstrap.Modal.getOrCreateInstance(saveModal).hide();
                    }
                }).catch(function (error) {
                    if (saveError) {
                        saveError.textContent = error.message || 'Error';
                        saveError.classList.remove('d-none');
                    }
                }).finally(function () {
                    saveBtn.disabled = false;
                });
            });

            if (nameInput) {
                nameInput.addEventListener('keydown', function (event) {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        saveBtn.click();
                    }
                });
            }
        }
    }

    function initSplitLayout(split, editor) {
        var wrap = split.closest('[data-he-split-wrap]');
        if (!wrap) {
            return;
        }

        var storageKey = split.getAttribute('data-he-layout-storage-key') || 'cabinet-he-editor-layout';
        var buttons = wrap.querySelectorAll('[data-he-layout-mode]');
        var heights = { side: 360, stacked: 440 };

        function applyLayout(mode) {
            var stacked = mode === 'stacked';
            split.classList.toggle('cabinet-he-split--stacked', stacked);
            split.classList.toggle('cabinet-he-split--side', !stacked);
            buttons.forEach(function (btn) {
                var active = btn.getAttribute('data-he-layout-mode') === mode;
                btn.classList.toggle('active', active);
                btn.setAttribute('aria-pressed', active ? 'true' : 'false');
            });
            if (editor && typeof editor.resize === 'function') {
                editor.resize('100%', stacked ? heights.stacked : heights.side);
            }
            try {
                window.localStorage.setItem(storageKey, mode);
            } catch (e) {
                // ignore
            }
        }

        var saved = 'side';
        try {
            saved = window.localStorage.getItem(storageKey) || 'side';
        } catch (e) {
            saved = 'side';
        }
        if (saved !== 'stacked') {
            saved = 'side';
        }
        applyLayout(saved);

        buttons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                applyLayout(btn.getAttribute('data-he-layout-mode') || 'side');
            });
        });
    }

    function initSplitEditor(root) {
        var split = root.querySelector('[data-he-split-editor]');
        if (!split) {
            return;
        }

        if (typeof window.jQuery === 'undefined' || !window.jQuery.fn.ckeditor || typeof window.CKEDITOR === 'undefined') {
            return;
        }

        var editorId = split.getAttribute('data-he-editor-id') || 'description';
        var lang = root.getAttribute('data-he-lang') || 'ru';
        var sourceEl = split.querySelector('[data-he-html-source]');
        var metaEl = split.querySelector('[data-he-html-meta]');
        var copyBtn = split.querySelector('[data-he-copy-html]');
        var $textarea = window.jQuery('#' + editorId);

        if (!sourceEl || !$textarea.length) {
            return;
        }

        $textarea.ckeditor({
            language: lang,
            height: 360,
        });

        var editor = window.CKEDITOR.instances[editorId];
        if (!editor) {
            return;
        }

        var syncingFromSource = false;

        function updateMeta(html) {
            if (!metaEl) {
                return;
            }
            var htmlLen = (html || '').length;
            var textLen = plainTextLength(html);
            metaEl.textContent = htmlLen.toLocaleString('ru-RU') + ' ' + (metaEl.getAttribute('data-he-html-chars-label') || 'chars HTML')
                + ' · ' + textLen.toLocaleString('ru-RU') + ' ' + (metaEl.getAttribute('data-he-text-chars-label') || 'chars text');
        }

        function syncToSource() {
            if (syncingFromSource) {
                return;
            }
            var html = editor.getData();
            sourceEl.value = html;
            updateMeta(html);
        }

        var editorApi = {
            getHtml: function () {
                return editor.getData();
            },
            setHtml: function (html) {
                editor.setData(html);
                syncToSource();
            },
        };

        editor.on('instanceReady', function () {
            syncToSource();
            initSplitLayout(split, editor);
            initPresets(root, editorApi);
            initPublicShare(root, editorApi);
        });
        editor.on('change', syncToSource);
        editor.on('mode', syncToSource);
        editor.on('blur', syncToSource);

        var syncToEditor = debounce(function () {
            syncingFromSource = true;
            editor.setData(sourceEl.value);
            syncingFromSource = false;
            updateMeta(sourceEl.value);
        }, 400);

        sourceEl.addEventListener('input', syncToEditor);

        if (copyBtn) {
            copyBtn.addEventListener('click', function () {
                var html = sourceEl.value || editor.getData();
                if (!html) {
                    return;
                }

                function markCopied() {
                    var original = copyBtn.innerHTML;
                    copyBtn.disabled = true;
                    copyBtn.innerHTML = '<i class="bi bi-check-lg me-1" aria-hidden="true"></i>' + (copyBtn.getAttribute('data-he-copied-label') || 'Copied');
                    setTimeout(function () {
                        copyBtn.disabled = false;
                        copyBtn.innerHTML = original;
                    }, 2000);
                }

                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(html).then(markCopied).catch(function () {
                        sourceEl.select();
                        document.execCommand('copy');
                        markCopied();
                    });
                } else {
                    sourceEl.select();
                    document.execCommand('copy');
                    markCopied();
                }
            });
        }

        var form = root.querySelector('form');
        if (form) {
            form.addEventListener('submit', function () {
                if (editor.mode === 'source') {
                    editor.setMode('wysiwyg');
                }
                editor.updateElement();
            });
        }
    }

    function initPublicShare(root, editorApi) {
        var panel = root.querySelector('#cabinet-he-public-share');
        if (!panel || !editorApi) {
            return;
        }

        var descriptionId = panel.getAttribute('data-description-id');
        var createUrl = panel.getAttribute('data-create-url');
        var revokeUrl = panel.getAttribute('data-revoke-url');
        var revokeConfirm = panel.getAttribute('data-revoke-confirm') || '';
        var copiedLabel = panel.getAttribute('data-copied-label') || 'Copied';
        var validUntilLabel = panel.getAttribute('data-valid-until-label') || '';
        var createLabelText = panel.getAttribute('data-create-label') || 'Create public link';
        var refreshLabelText = panel.getAttribute('data-refresh-label') || 'Refresh public link';
        var urlInput = panel.querySelector('#cabinet-he-public-share-url');
        var expiresBadge = panel.querySelector('#cabinet-he-public-share-expires');
        var copyBtn = panel.querySelector('#cabinet-he-public-share-copy');
        var createBtn = panel.querySelector('#cabinet-he-public-share-create');
        var revokeBtn = panel.querySelector('#cabinet-he-public-share-revoke');

        function setCreateButtonLabel(isRefresh) {
            if (!createBtn) {
                return;
            }
            var label = isRefresh ? refreshLabelText : createLabelText;
            createBtn.innerHTML = '<i class="bi bi-link-45deg me-1" aria-hidden="true"></i>' + label;
        }

        function setControlsEnabled(hasLink) {
            if (copyBtn) {
                copyBtn.disabled = !hasLink;
            }
            if (revokeBtn) {
                revokeBtn.disabled = !hasLink;
            }
        }

        if (copyBtn && urlInput) {
            copyBtn.addEventListener('click', function () {
                if (!urlInput.value) {
                    return;
                }
                urlInput.select();
                urlInput.setSelectionRange(0, urlInput.value.length);
                var done = function () {
                    var original = copyBtn.innerHTML;
                    copyBtn.innerHTML = '<i class="bi bi-check2 me-1" aria-hidden="true"></i>' + copiedLabel;
                    setTimeout(function () {
                        copyBtn.innerHTML = original;
                    }, 1600);
                };
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(urlInput.value).then(done).catch(function () {
                        document.execCommand('copy');
                        done();
                    });
                } else {
                    document.execCommand('copy');
                    done();
                }
            });
        }

        if (createBtn && createUrl && descriptionId) {
            createBtn.addEventListener('click', function () {
                createBtn.disabled = true;
                fetch(createUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        description_id: parseInt(descriptionId, 10),
                        html: editorApi.getHtml(),
                    }),
                }).then(function (response) {
                    return response.json().catch(function () {
                        return {};
                    }).then(function (data) {
                        if (!response.ok || !data.success) {
                            throw new Error(data.message || 'Error');
                        }
                        if (urlInput) {
                            urlInput.value = data.url || '';
                        }
                        if (expiresBadge) {
                            expiresBadge.textContent = (validUntilLabel ? validUntilLabel + ' ' : '') + (data.expires_at || '');
                            expiresBadge.classList.remove('d-none', 'text-bg-secondary');
                            expiresBadge.classList.add('text-bg-success');
                        }
                        setControlsEnabled(true);
                        setCreateButtonLabel(true);
                    });
                }).catch(function (error) {
                    window.alert(error.message || 'Error');
                }).finally(function () {
                    createBtn.disabled = false;
                });
            });
        }

        if (revokeBtn && revokeUrl && descriptionId) {
            revokeBtn.addEventListener('click', function () {
                if (!window.confirm(revokeConfirm)) {
                    return;
                }
                revokeBtn.disabled = true;
                fetch(revokeUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        description_id: parseInt(descriptionId, 10),
                    }),
                }).then(function (response) {
                    return response.json().catch(function () {
                        return {};
                    }).then(function (data) {
                        if (!response.ok || !data.success) {
                            throw new Error(data.message || 'Error');
                        }
                        if (urlInput) {
                            urlInput.value = '';
                        }
                        if (expiresBadge) {
                            expiresBadge.textContent = '';
                            expiresBadge.classList.add('d-none');
                        }
                        setControlsEnabled(false);
                        setCreateButtonLabel(false);
                    });
                }).catch(function (error) {
                    window.alert(error.message || 'Error');
                }).finally(function () {
                    revokeBtn.disabled = false;
                });
            });
        }
    }

    document.querySelectorAll('.cabinet-html-editor-page').forEach(function (root) {
        if (window.bootstrap && window.bootstrap.Tooltip) {
            root.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
                new window.bootstrap.Tooltip(el);
            });
        }

        var activateProject = initProjectTabs(root);
        initSearch(root, activateProject);
        initSplitEditor(root);
    });
}(window, document));
