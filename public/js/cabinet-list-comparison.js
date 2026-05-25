/**
 * Сравнение списков — клиентская обработка (LTE4).
 */
(function (window, document) {
    'use strict';

    var STORAGE_KEY = 'cabinet-list-comparison-state';
    var root = document.querySelector('.cabinet-list-comparison-page');
    if (!root) {
        return;
    }

    var listAEl = root.querySelector('#cabinet-lc-list-a');
    var listBEl = root.querySelector('#cabinet-lc-list-b');
    var resultEl = root.querySelector('#cabinet-lc-result');
    var countAEl = root.querySelector('[data-lc-count-a]');
    var countBEl = root.querySelector('[data-lc-count-b]');
    var countResultEl = root.querySelector('[data-lc-count-result]');
    var kpiRoot = root.querySelector('.cabinet-lc-kpi');
    var kpiA = root.querySelector('[data-lc-kpi-a]');
    var kpiB = root.querySelector('[data-lc-kpi-b]');
    var kpiResult = root.querySelector('[data-lc-kpi-result]');
    var kpiOverlap = root.querySelector('[data-lc-kpi-overlap]');
    var resultBlock = root.querySelector('.cabinet-lc-result');
    var emptyNote = root.querySelector('.cabinet-lc-empty-note');
    var undoBtn = root.querySelector('[data-lc-undo]');
    var configEl = document.getElementById('cabinet-list-comparison-config');
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

    function splitLines(text) {
        return String(text).split(/[\r\n]+/);
    }

    function countNonEmptyLines(text) {
        return splitLines(text).filter(function (line) {
            return line.trim() !== '';
        }).length;
    }

    function getOptions() {
        return {
            trim: !!(root.querySelector('#cabinet-lc-opt-trim') && root.querySelector('#cabinet-lc-opt-trim').checked),
            removeEmpty: !!(root.querySelector('#cabinet-lc-opt-empty') && root.querySelector('#cabinet-lc-opt-empty').checked),
            caseInsensitive: !!(root.querySelector('#cabinet-lc-opt-ci') && root.querySelector('#cabinet-lc-opt-ci').checked),
            sortResult: !!(root.querySelector('#cabinet-lc-opt-sort') && root.querySelector('#cabinet-lc-opt-sort').checked),
        };
    }

    function normalizeLines(text, opts) {
        var lines = splitLines(text);
        if (opts.trim) {
            lines = lines.map(function (line) {
                return line.trim();
            });
        }
        if (opts.removeEmpty) {
            lines = lines.filter(function (line) {
                return line !== '';
            });
        }
        if (opts.caseInsensitive) {
            lines = lines.map(function (line) {
                return line.toLowerCase();
            });
        }
        return lines;
    }

    function arrayUnique(lines) {
        var seen = {};
        var out = [];
        lines.forEach(function (line) {
            if (!Object.prototype.hasOwnProperty.call(seen, line)) {
                seen[line] = true;
                out.push(line);
            }
        });
        return out;
    }

    function arrayDiff(a, b) {
        var setB = {};
        b.forEach(function (line) {
            setB[line] = true;
        });
        return a.filter(function (line) {
            return !setB[line];
        });
    }

    function arrayIntersect(a, b) {
        var setB = {};
        b.forEach(function (line) {
            setB[line] = true;
        });
        return a.filter(function (line) {
            return setB[line];
        });
    }

    function compareLists(firstRaw, secondRaw, mode, opts) {
        var first = normalizeLines(firstRaw, opts);
        var second = normalizeLines(secondRaw, opts);
        var overlap = arrayUnique(arrayIntersect(first, second)).filter(function (line) {
            return line !== '';
        });

        var result;
        switch (mode) {
            case 'uniqueInFirstList':
                result = arrayDiff(arrayUnique(arrayDiff(first, second)), ['']);
                break;
            case 'uniqueInSecondList':
                result = arrayDiff(arrayUnique(arrayDiff(second, first)), ['']);
                break;
            case 'union':
                result = arrayDiff(arrayUnique(first.concat(second)), ['']);
                break;
            case 'unique':
            default:
                result = arrayDiff(arrayUnique(arrayIntersect(first, second)), ['']);
                break;
        }

        if (opts.sortResult) {
            result.sort(function (a, b) {
                return a.localeCompare(b, 'ru', { sensitivity: 'base' });
            });
        }

        return {
            lines: result,
            text: result.join('\n'),
            overlap: overlap.length,
        };
    }

    function getSelectedMode() {
        var checked = root.querySelector('[data-lc-mode]:checked');
        return checked ? checked.value : 'unique';
    }

    function setMode(value) {
        var input = root.querySelector('[data-lc-mode][value="' + value + '"]');
        if (input) {
            input.checked = true;
        }
    }

    function updateCounts() {
        var a = countNonEmptyLines(listAEl.value);
        var b = countNonEmptyLines(listBEl.value);
        if (countAEl) {
            countAEl.textContent = String(a);
        }
        if (countBEl) {
            countBEl.textContent = String(b);
        }
    }

    function setKpi(a, b, resultCount, overlap) {
        if (!kpiRoot) {
            return;
        }
        kpiRoot.classList.remove('is-empty');
        if (kpiA) {
            kpiA.textContent = String(a);
        }
        if (kpiB) {
            kpiB.textContent = String(b);
        }
        if (kpiResult) {
            kpiResult.textContent = String(resultCount);
        }
        if (kpiOverlap) {
            kpiOverlap.textContent = String(overlap);
        }
    }

    function resetKpi() {
        if (kpiRoot) {
            kpiRoot.classList.add('is-empty');
        }
        ['—', '—', '—', '—'].forEach(function (mark, i) {
            var el = [kpiA, kpiB, kpiResult, kpiOverlap][i];
            if (el) {
                el.textContent = mark;
            }
        });
    }

    function flash(message, title) {
        if (window.toastr && typeof window.toastr.success === 'function') {
            window.toastr.success(message, title || '');
            return;
        }
        if (message) {
            window.alert(message);
        }
    }

    function showResult(text, overlap) {
        resultEl.value = text;
        if (countResultEl) {
            countResultEl.textContent = String(countNonEmptyLines(text));
        }
        if (resultBlock) {
            resultBlock.classList.add('is-visible');
        }
        if (emptyNote) {
            emptyNote.classList.toggle('is-visible', !text.trim());
        }
        setKpi(
            countNonEmptyLines(listAEl.value),
            countNonEmptyLines(listBEl.value),
            countNonEmptyLines(text),
            overlap
        );
    }

    function hideResult() {
        resultEl.value = '';
        if (countResultEl) {
            countResultEl.textContent = '0';
        }
        if (resultBlock) {
            resultBlock.classList.remove('is-visible');
        }
        if (emptyNote) {
            emptyNote.classList.remove('is-visible');
        }
        resetKpi();
    }

    function processLists() {
        var a = listAEl.value;
        var b = listBEl.value;
        if (!a.trim() || !b.trim()) {
            flash(config.bothListsRequired || 'Оба списка должны быть не пустыми', config.errorTitle || 'Ошибка');
            hideResult();
            return;
        }

        undoState = {
            result: resultEl.value,
            visible: resultBlock && resultBlock.classList.contains('is-visible'),
        };
        if (undoBtn) {
            undoBtn.disabled = false;
        }

        var out = compareLists(a, b, getSelectedMode(), getOptions());
        showResult(out.text, out.overlap);
        scheduleSave();
    }

    function undoLast() {
        if (!undoState) {
            return;
        }
        if (undoState.visible) {
            resultEl.value = undoState.result;
            if (countResultEl) {
                countResultEl.textContent = String(countNonEmptyLines(undoState.result));
            }
        } else {
            hideResult();
        }
        undoState = null;
        if (undoBtn) {
            undoBtn.disabled = true;
        }
    }

    function copyResult() {
        var text = resultEl.value;
        if (!text.trim()) {
            flash(config.emptyText || 'Нечего копировать', config.copyTitle || 'Копировать');
            return;
        }
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function () {
                flash(config.copiedText || 'Скопировано', config.copyTitle || 'Копировать');
            });
            return;
        }
        resultEl.focus();
        resultEl.select();
        try {
            document.execCommand('copy');
            flash(config.copiedText || 'Скопировано', config.copyTitle || 'Копировать');
        } catch (e) {
            flash(config.copyFailedText || 'Не удалось скопировать', config.copyTitle || 'Копировать');
        }
    }

    function downloadResult() {
        var text = resultEl.value;
        if (!text.trim()) {
            flash(config.emptyText || 'Нечего копировать', config.downloadTitle || 'Скачать');
            return;
        }
        var blob = new Blob([text], { type: 'text/plain;charset=utf-8' });
        var url = URL.createObjectURL(blob);
        var link = document.createElement('a');
        link.href = url;
        link.download = 'list-comparison-' + Date.now() + '.txt';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    }

    function swapLists() {
        var tmp = listAEl.value;
        listAEl.value = listBEl.value;
        listBEl.value = tmp;
        updateCounts();
        hideResult();
        undoState = null;
        if (undoBtn) {
            undoBtn.disabled = true;
        }
        scheduleSave();
    }

    function clearAll() {
        listAEl.value = '';
        listBEl.value = '';
        hideResult();
        updateCounts();
        undoState = null;
        if (undoBtn) {
            undoBtn.disabled = true;
        }
        scheduleSave();
    }

    function scheduleSave() {
        if (saveTimer) {
            window.clearTimeout(saveTimer);
        }
        saveTimer = window.setTimeout(saveState, 400);
    }

    function saveState() {
        try {
            var state = {
                listA: listAEl.value,
                listB: listBEl.value,
                mode: getSelectedMode(),
                options: getOptions(),
            };
            window.localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
        } catch (e) {
            /* ignore */
        }
    }

    function restoreState() {
        try {
            var raw = window.localStorage.getItem(STORAGE_KEY);
            if (!raw) {
                return;
            }
            var state = JSON.parse(raw);
            if (state.listA != null) {
                listAEl.value = state.listA;
            }
            if (state.listB != null) {
                listBEl.value = state.listB;
            }
            if (state.mode) {
                setMode(state.mode);
            }
            if (state.options) {
                var map = {
                    trim: '#cabinet-lc-opt-trim',
                    removeEmpty: '#cabinet-lc-opt-empty',
                    caseInsensitive: '#cabinet-lc-opt-ci',
                    sortResult: '#cabinet-lc-opt-sort',
                };
                Object.keys(map).forEach(function (key) {
                    var el = root.querySelector(map[key]);
                    if (el && typeof state.options[key] === 'boolean') {
                        el.checked = state.options[key];
                    }
                });
            }
        } catch (e) {
            /* ignore */
        }
    }

    function bindDropZone(pane, targetEl) {
        if (!pane || !targetEl) {
            return;
        }
        ['dragenter', 'dragover'].forEach(function (eventName) {
            pane.addEventListener(eventName, function (event) {
                event.preventDefault();
                pane.classList.add('cabinet-lc-dropzone--active');
            });
        });
        ['dragleave', 'drop'].forEach(function (eventName) {
            pane.addEventListener(eventName, function (event) {
                event.preventDefault();
                pane.classList.remove('cabinet-lc-dropzone--active');
            });
        });
        pane.addEventListener('drop', function (event) {
            var file = event.dataTransfer && event.dataTransfer.files && event.dataTransfer.files[0];
            if (!file) {
                return;
            }
            if (!/\.txt$/i.test(file.name)) {
                flash(config.invalidFileText || 'Поддерживаются только файлы .txt', config.fileTitle || 'Файл');
                return;
            }
            var reader = new FileReader();
            reader.onload = function () {
                targetEl.value = String(reader.result || '');
                updateCounts();
                hideResult();
                scheduleSave();
            };
            reader.readAsText(file, 'UTF-8');
        });
    }

    root.querySelectorAll('[data-lc-preset]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            setMode(btn.getAttribute('data-lc-preset') || 'unique');
            scheduleSave();
        });
    });

    root.querySelectorAll('[data-lc-mode]').forEach(function (input) {
        input.addEventListener('change', scheduleSave);
    });

    root.querySelectorAll('#cabinet-lc-opt-trim, #cabinet-lc-opt-empty, #cabinet-lc-opt-ci, #cabinet-lc-opt-sort').forEach(function (el) {
        if (el) {
            el.addEventListener('change', scheduleSave);
        }
    });

    [listAEl, listBEl].forEach(function (el) {
        if (!el) {
            return;
        }
        el.addEventListener('input', function () {
            updateCounts();
            scheduleSave();
        });
    });

    var processBtn = root.querySelector('[data-lc-process]');
    if (processBtn) {
        processBtn.addEventListener('click', processLists);
    }
    if (undoBtn) {
        undoBtn.addEventListener('click', undoLast);
    }
    var copyBtn = root.querySelector('[data-lc-copy]');
    if (copyBtn) {
        copyBtn.addEventListener('click', copyResult);
    }
    var downloadBtn = root.querySelector('[data-lc-download]');
    if (downloadBtn) {
        downloadBtn.addEventListener('click', downloadResult);
    }
    var swapBtn = root.querySelector('[data-lc-swap]');
    if (swapBtn) {
        swapBtn.addEventListener('click', swapLists);
    }
    var clearBtn = root.querySelector('[data-lc-clear]');
    if (clearBtn) {
        clearBtn.addEventListener('click', clearAll);
    }

    bindDropZone(root.querySelector('[data-lc-dropzone-a]'), listAEl);
    bindDropZone(root.querySelector('[data-lc-dropzone-b]'), listBEl);

    root.addEventListener('keydown', function (event) {
        if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') {
            event.preventDefault();
            processLists();
        }
        if ((event.ctrlKey || event.metaKey) && event.key === 'z' && undoState) {
            event.preventDefault();
            undoLast();
        }
    });

    restoreState();
    updateCounts();
    resetKpi();
})(window, document);
