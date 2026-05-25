/**
 * Удаление дубликатов — клиентская обработка (LTE4).
 */
(function (window, document) {
    'use strict';

    var STORAGE_KEY = 'cabinet-duplicates-state';
    var root = document.querySelector('.cabinet-duplicates-page');
    if (!root) {
        return;
    }

    var textEl = root.querySelector('#cabinet-dup-text');
    var beforeViewEl = root.querySelector('#cabinet-dup-before-view');
    var lineCountEl = root.querySelector('[data-dup-line-count]');
    var kpiRoot = root.querySelector('.cabinet-dup-kpi');
    var kpiBefore = root.querySelector('[data-dup-before]');
    var kpiAfter = root.querySelector('[data-dup-after]');
    var kpiDupRemoved = root.querySelector('[data-dup-dup-removed]');
    var kpiEmptyRemoved = root.querySelector('[data-dup-empty-removed]');
    var startCharsEl = root.querySelector('#cabinet-dup-start-chars');
    var endCharsEl = root.querySelector('#cabinet-dup-end-chars');
    var splitToggleEl = root.querySelector('#cabinet-dup-split-toggle');
    var undoBtn = root.querySelector('[data-dup-undo]');
    var beforePaneEl = root.querySelector('.cabinet-dup-split-pane--before');
    var mainLabelEl = root.querySelector('[data-dup-main-label]');
    var dropZoneEl = root.querySelector('[data-dup-dropzone]');
    var configEl = document.getElementById('cabinet-duplicates-config');
    var config = {};
    var undoState = null;
    var saveTimer = null;

    if (configEl && configEl.textContent) {
        try {
            config = JSON.parse(configEl.textContent);
        } catch (e) {
            config = {};
        }
    }

    function escapeRegExp(value) {
        return String(value).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    function splitLines(text) {
        return String(text).split(/[\r\n]+/);
    }

    function countNonEmptyLines(text) {
        return splitLines(text).filter(function (line) {
            return line.trim() !== '';
        }).length;
    }

    function countEmptyLines(text) {
        return splitLines(text).filter(function (line) {
            return line.trim() === '';
        }).length;
    }

    function isCaseInsensitiveDedup() {
        var el = root.querySelector('#cabinet-dup-opt-dedup-ci');
        return el && el.checked;
    }

    function isSortEnabled() {
        var el = root.querySelector('#cabinet-dup-opt-sort');
        return el && el.checked;
    }

    function getSelectedOptions() {
        var order = [
            'removeExtraSpace',
            'trim',
            'replaceTabWithSpace',
            'removeEmptyRows',
            'lowerCase',
            'removeDuplicates',
            'replaceUmlaut',
            'removeStartingChars',
            'removeEndingChars',
            'sortAlphabetically',
        ];
        var selected = {};

        root.querySelectorAll('[data-dup-option]').forEach(function (input) {
            if (input.checked) {
                selected[input.value] = true;
            }
        });

        return order.filter(function (name) {
            return selected[name];
        });
    }

    function updateLineCount() {
        if (lineCountEl) {
            lineCountEl.textContent = String(countNonEmptyLines(textEl.value));
        }
    }

    function updateCharFieldsState() {
        root.querySelectorAll('[data-dup-char-toggle]').forEach(function (input) {
            var targetId = input.getAttribute('data-dup-char-toggle');
            var field = root.querySelector('[data-dup-char-field="' + targetId + '"]');
            var charInput = root.querySelector('#' + targetId);
            if (!field || !charInput) {
                return;
            }
            var enabled = input.checked;
            field.classList.toggle('is-disabled', !enabled);
            charInput.disabled = !enabled;
        });
    }

    function updateUndoButton() {
        if (undoBtn) {
            undoBtn.disabled = !undoState;
        }
    }

    function updateSplitLayout() {
        var on = splitToggleEl && splitToggleEl.checked;
        if (beforePaneEl) {
            beforePaneEl.classList.toggle('d-none', !on);
        }
        root.classList.toggle('cabinet-dup-split-active', !!on);
        if (mainLabelEl) {
            var hasSnapshot = beforeViewEl && beforeViewEl.value.trim() !== '';
            if (on && hasSnapshot) {
                mainLabelEl.textContent = config.mainLabelProcessed || 'Processed list';
            } else {
                mainLabelEl.textContent = config.mainLabelYourText || 'Your text';
            }
        }
    }

    function setKpi(before, after, dupRemoved, emptyRemoved) {
        if (!kpiRoot) {
            return;
        }
        kpiRoot.classList.remove('is-empty');
        if (kpiBefore) {
            kpiBefore.textContent = String(before);
        }
        if (kpiAfter) {
            kpiAfter.textContent = String(after);
        }
        if (kpiDupRemoved) {
            kpiDupRemoved.textContent = String(dupRemoved);
        }
        if (kpiEmptyRemoved) {
            kpiEmptyRemoved.textContent = String(emptyRemoved);
        }
    }

    function resetKpi() {
        if (kpiRoot) {
            kpiRoot.classList.add('is-empty');
        }
        ['—', '—', '—', '—'].forEach(function (mark, i) {
            var el = [kpiBefore, kpiAfter, kpiDupRemoved, kpiEmptyRemoved][i];
            if (el) {
                el.textContent = mark;
            }
        });
    }

    function processors(metrics) {
        return {
            removeExtraSpace: function (text) {
                return text.replace(/ +/gm, ' ');
            },
            trim: function (text) {
                return splitLines(text).map(function (line) {
                    return line.trim();
                }).join('\n');
            },
            replaceTabWithSpace: function (text) {
                return text.replace(/[ \t]/gm, ' ');
            },
            removeEmptyRows: function (text) {
                var lines = splitLines(text);
                var filtered = lines.filter(function (line) {
                    return line.trim() !== '';
                });
                metrics.emptyRemoved += Math.max(0, lines.length - filtered.length);
                return filtered.join('\n');
            },
            lowerCase: function (text) {
                return text.toLowerCase();
            },
            removeStartingChars: function (text) {
                var chars = startCharsEl ? startCharsEl.value : '';
                if (!chars) {
                    return text;
                }
                var pattern = '^[' + escapeRegExp(chars) + ']| [' + escapeRegExp(chars) + ']+';
                return text.replace(new RegExp(pattern, 'gm'), ' ');
            },
            removeEndingChars: function (text) {
                var chars = endCharsEl ? endCharsEl.value : '';
                if (!chars) {
                    return text;
                }
                var pattern = '[' + escapeRegExp(chars) + ']+[ \t]|[' + escapeRegExp(chars) + ']$';
                return text.replace(new RegExp(pattern, 'gm'), ' ');
            },
            removeDuplicates: function (text) {
                var lines = splitLines(text);
                var seen = {};
                var unique = [];
                var caseInsensitive = isCaseInsensitiveDedup();

                lines.forEach(function (line) {
                    var key = caseInsensitive ? line.toLowerCase() : line;
                    if (!Object.prototype.hasOwnProperty.call(seen, key)) {
                        seen[key] = true;
                        unique.push(line);
                    } else {
                        metrics.dupRemoved += 1;
                    }
                });

                return unique.join('\n');
            },
            replaceUmlaut: function (text) {
                return text.replace(/[ёЁ]/g, 'е');
            },
            sortAlphabetically: function (text) {
                if (!isSortEnabled()) {
                    return text;
                }
                var lines = splitLines(text).filter(function (line) {
                    return line.trim() !== '';
                });
                lines.sort(function (a, b) {
                    return a.localeCompare(b, 'ru', { sensitivity: 'base' });
                });
                return lines.join('\n');
            },
        };
    }

    function processText() {
        var beforeText = textEl.value;
        var before = countNonEmptyLines(beforeText);
        var text = beforeText;
        var metrics = { dupRemoved: 0, emptyRemoved: 0 };
        var ops = processors(metrics);
        var selected = getSelectedOptions();

        undoState = {
            text: beforeText,
            beforeView: beforeViewEl ? beforeViewEl.value : '',
        };
        updateUndoButton();

        if (beforeViewEl && splitToggleEl && splitToggleEl.checked) {
            beforeViewEl.value = beforeText;
        }

        selected.forEach(function (name) {
            if (typeof ops[name] === 'function') {
                text = ops[name](text);
            }
        });

        textEl.value = text;
        var after = countNonEmptyLines(text);
        updateLineCount();
        setKpi(before, after, metrics.dupRemoved, metrics.emptyRemoved);
        updateSplitLayout();
        scheduleSave();
    }

    function undoLast() {
        if (!undoState) {
            return;
        }
        textEl.value = undoState.text;
        if (beforeViewEl) {
            beforeViewEl.value = undoState.beforeView;
        }
        undoState = null;
        updateUndoButton();
        updateLineCount();
        resetKpi();
        scheduleSave();
    }

    function copyResult() {
        var text = textEl.value;
        if (!text) {
            flash(config.emptyText || 'Nothing to copy', config.copyTitle || 'Copy');
            return;
        }

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function () {
                flash(config.copiedText || 'Copied', config.copyTitle || 'Copy');
            }).catch(function () {
                fallbackCopy(text);
            });
            return;
        }

        fallbackCopy(text);
    }

    function fallbackCopy(text) {
        textEl.focus();
        textEl.select();
        try {
            document.execCommand('copy');
            flash(config.copiedText || 'Copied', config.copyTitle || 'Copy');
        } catch (e) {
            flash(config.copyFailedText || 'Copy failed', config.copyTitle || 'Copy');
        }
    }

    function clearForm() {
        textEl.value = '';
        if (beforeViewEl) {
            beforeViewEl.value = '';
        }
        undoState = null;
        updateUndoButton();
        updateLineCount();
        resetKpi();
        scheduleSave();
    }

    function setAllOptions(checked) {
        root.querySelectorAll('[data-dup-option]').forEach(function (input) {
            input.checked = checked;
        });
        updateCharFieldsState();
        scheduleSave();
    }

    function resetDefaults() {
        root.querySelectorAll('[data-dup-option]').forEach(function (input) {
            input.checked = input.defaultChecked;
        });
        if (startCharsEl) {
            startCharsEl.value = startCharsEl.defaultValue || '';
        }
        if (endCharsEl) {
            endCharsEl.value = endCharsEl.defaultValue || '';
        }
        updateCharFieldsState();
        scheduleSave();
    }

    function applyPreset(preset) {
        var map = {
            'dedup-only': {
                removeExtraSpace: false,
                trim: false,
                replaceTabWithSpace: false,
                removeEmptyRows: false,
                lowerCase: false,
                removeDuplicates: true,
                replaceUmlaut: false,
                removeStartingChars: false,
                removeEndingChars: false,
                sortAlphabetically: false,
                caseInsensitiveDedup: false,
            },
            seo: {
                removeExtraSpace: false,
                trim: true,
                replaceTabWithSpace: false,
                removeEmptyRows: true,
                lowerCase: true,
                removeDuplicates: true,
                replaceUmlaut: true,
                removeStartingChars: false,
                removeEndingChars: false,
                sortAlphabetically: true,
                caseInsensitiveDedup: true,
            },
        };

        var presetMap = map[preset];
        if (!presetMap) {
            return;
        }

        root.querySelectorAll('[data-dup-option]').forEach(function (input) {
            if (Object.prototype.hasOwnProperty.call(presetMap, input.value)) {
                input.checked = !!presetMap[input.value];
            }
        });

        var ciEl = root.querySelector('#cabinet-dup-opt-dedup-ci');
        if (ciEl && Object.prototype.hasOwnProperty.call(presetMap, 'caseInsensitiveDedup')) {
            ciEl.checked = !!presetMap.caseInsensitiveDedup;
        }

        updateCharFieldsState();
        scheduleSave();
    }

    function readState() {
        return {
            text: textEl.value,
            startChars: startCharsEl ? startCharsEl.value : '',
            endChars: endCharsEl ? endCharsEl.value : '',
            split: splitToggleEl ? splitToggleEl.checked : false,
            options: {},
            caseInsensitiveDedup: isCaseInsensitiveDedup(),
        };
    }

    function collectOptionsState(state) {
        root.querySelectorAll('[data-dup-option]').forEach(function (input) {
            state.options[input.value] = input.checked;
        });
    }

    function scheduleSave() {
        if (saveTimer) {
            window.clearTimeout(saveTimer);
        }
        saveTimer = window.setTimeout(saveState, 400);
    }

    function saveState() {
        try {
            var state = readState();
            collectOptionsState(state);
            window.localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
        } catch (e) {
            /* ignore quota / private mode */
        }
    }

    function restoreState() {
        try {
            var raw = window.localStorage.getItem(STORAGE_KEY);
            if (!raw) {
                return;
            }
            var state = JSON.parse(raw);
            if (state.text !== undefined) {
                textEl.value = state.text;
            }
            if (startCharsEl && state.startChars !== undefined) {
                startCharsEl.value = state.startChars;
            }
            if (endCharsEl && state.endChars !== undefined) {
                endCharsEl.value = state.endChars;
            }
            if (splitToggleEl && state.split) {
                splitToggleEl.checked = true;
            }
            if (state.options) {
                root.querySelectorAll('[data-dup-option]').forEach(function (input) {
                    if (Object.prototype.hasOwnProperty.call(state.options, input.value)) {
                        input.checked = !!state.options[input.value];
                    }
                });
            }
            var ciEl = root.querySelector('#cabinet-dup-opt-dedup-ci');
            if (ciEl && state.caseInsensitiveDedup !== undefined) {
                ciEl.checked = !!state.caseInsensitiveDedup;
            }
        } catch (e) {
            /* ignore corrupt storage */
        }
    }

    function readTextFile(file) {
        if (!file) {
            return;
        }
        var isText = /^text\//.test(file.type || '') || /\.txt$/i.test(file.name || '');
        if (!isText) {
            flash(config.invalidFileText || 'Only .txt files are supported', config.fileTitle || 'File');
            return;
        }
        var reader = new FileReader();
        reader.onload = function () {
            textEl.value = String(reader.result || '');
            undoState = null;
            updateUndoButton();
            updateLineCount();
            resetKpi();
            scheduleSave();
        };
        reader.readAsText(file, 'UTF-8');
    }

    function bindDropZone() {
        if (!dropZoneEl) {
            return;
        }

        ['dragenter', 'dragover'].forEach(function (eventName) {
            dropZoneEl.addEventListener(eventName, function (event) {
                event.preventDefault();
                dropZoneEl.classList.add('cabinet-dup-dropzone--active');
            });
        });

        ['dragleave', 'drop'].forEach(function (eventName) {
            dropZoneEl.addEventListener(eventName, function (event) {
                event.preventDefault();
                dropZoneEl.classList.remove('cabinet-dup-dropzone--active');
            });
        });

        dropZoneEl.addEventListener('drop', function (event) {
            var files = event.dataTransfer && event.dataTransfer.files;
            if (files && files[0]) {
                readTextFile(files[0]);
            }
        });
    }

    function initTooltips() {
        if (window.bootstrap && window.bootstrap.Tooltip) {
            root.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
                new window.bootstrap.Tooltip(el);
            });
        }
    }

    var processBtn = root.querySelector('[data-dup-process]');
    if (processBtn) {
        processBtn.addEventListener('click', processText);
    }
    var copyBtn = root.querySelector('[data-dup-copy]');
    if (copyBtn) {
        copyBtn.addEventListener('click', copyResult);
    }
    var clearBtn = root.querySelector('[data-dup-clear]');
    if (clearBtn) {
        clearBtn.addEventListener('click', clearForm);
    }
    var selectAllBtn = root.querySelector('[data-dup-select-all]');
    if (selectAllBtn) {
        selectAllBtn.addEventListener('click', function () {
            setAllOptions(true);
        });
    }
    var deselectAllBtn = root.querySelector('[data-dup-deselect-all]');
    if (deselectAllBtn) {
        deselectAllBtn.addEventListener('click', function () {
            setAllOptions(false);
        });
    }
    var resetBtn = root.querySelector('[data-dup-reset-options]');
    if (resetBtn) {
        resetBtn.addEventListener('click', resetDefaults);
    }
    if (undoBtn) {
        undoBtn.addEventListener('click', undoLast);
    }

    root.querySelectorAll('[data-dup-preset]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            applyPreset(btn.getAttribute('data-dup-preset'));
        });
    });

    textEl.addEventListener('input', function () {
        updateLineCount();
        scheduleSave();
    });

    root.querySelectorAll('[data-dup-char-toggle]').forEach(function (input) {
        input.addEventListener('change', function () {
            updateCharFieldsState();
            scheduleSave();
        });
    });

    root.querySelectorAll('[data-dup-option]').forEach(function (input) {
        input.addEventListener('change', scheduleSave);
    });

    if (startCharsEl) {
        startCharsEl.addEventListener('input', scheduleSave);
    }
    if (endCharsEl) {
        endCharsEl.addEventListener('input', scheduleSave);
    }

    var ciCheckbox = root.querySelector('#cabinet-dup-opt-dedup-ci');
    if (ciCheckbox) {
        ciCheckbox.addEventListener('change', scheduleSave);
    }

    if (splitToggleEl) {
        splitToggleEl.addEventListener('change', function () {
            updateSplitLayout();
            scheduleSave();
        });
    }

    root.addEventListener('keydown', function (event) {
        if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') {
            event.preventDefault();
            processText();
        }
        if ((event.ctrlKey || event.metaKey) && event.key === 'z' && undoState) {
            event.preventDefault();
            undoLast();
        }
    });

    bindDropZone();
    restoreState();
    updateSplitLayout();
    updateLineCount();
    updateCharFieldsState();
    updateUndoButton();
    initTooltips();
})(window, document);
