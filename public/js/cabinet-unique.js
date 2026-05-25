/**
 * Выделение уникальных слов — LTE4.
 */
(function (window, document) {
    'use strict';

    var STORAGE_KEY = 'cabinet-unique-state';
    var root = document.querySelector('.cabinet-unique-page');
    if (!root) {
        return;
    }

    var contentEl = root.querySelector('#cabinet-uw-content');
    var countPhrasesEl = root.querySelector('[data-uw-count-phrases]');
    var kpiRoot = root.querySelector('.cabinet-uw-kpi');
    var kpiPhrases = root.querySelector('[data-uw-kpi-phrases]');
    var kpiWords = root.querySelector('[data-uw-kpi-words]');
    var kpiOccurrences = root.querySelector('[data-uw-kpi-occurrences]');
    var tbody = root.querySelector('[data-uw-tbody]');
    var rangeFromEl = root.querySelector('#cabinet-uw-range-from');
    var rangeToEl = root.querySelector('#cabinet-uw-range-to');
    var searchEl = root.querySelector('#cabinet-uw-search');
    var searchHintEl = root.querySelector('[data-uw-search-hint]');
    var copyBtn = root.querySelector('[data-uw-copy-table]');
    var downloadBtn = root.querySelector('[data-uw-download-csv]');
    var processBtn = root.querySelector('[data-uw-process]');
    var processLabelEl = root.querySelector('[data-uw-process-label]');
    var undoBtn = root.querySelector('[data-uw-undo]');
    var exampleBtn = root.querySelector('[data-uw-example]');
    var dropzone = root.querySelector('[data-uw-dropzone]');
    var configEl = document.getElementById('cabinet-unique-config');
    var config = {};
    var allRows = [];
    var baseMetrics = null;
    var sortKey = 'count';
    var sortDir = 'desc';
    var visibleCols = { 0: true, 1: true, 2: true, 3: true };
    var searchQuery = '';
    var undoState = null;
    var saveTimer = null;

    if (configEl && configEl.textContent) {
        try {
            config = JSON.parse(configEl.textContent);
        } catch (e) {
            config = {};
        }
    }

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function countNonEmptyLines(text) {
        return String(text).split(/[\r\n]+/).filter(function (line) {
            return line.trim() !== '';
        }).length;
    }

    function formatNum(n) {
        return String(n).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    }

    function updatePhraseCount() {
        if (countPhrasesEl) {
            countPhrasesEl.textContent = String(countNonEmptyLines(contentEl ? contentEl.value : ''));
        }
    }

    function recalcKpi() {
        var totalOcc = allRows.reduce(function (sum, row) {
            return sum + row.count;
        }, 0);
        updateKpi({
            phrases: baseMetrics ? baseMetrics.phrases : countNonEmptyLines(contentEl ? contentEl.value : ''),
            uniqueWords: allRows.length,
            totalOccurrences: totalOcc,
        });
    }

    function updateKpi(metrics) {
        if (!metrics) {
            if (kpiRoot) {
                kpiRoot.classList.add('is-empty');
            }
            if (kpiPhrases) kpiPhrases.textContent = '—';
            if (kpiWords) kpiWords.textContent = '—';
            if (kpiOccurrences) kpiOccurrences.textContent = '—';
            return;
        }
        if (kpiRoot) {
            kpiRoot.classList.remove('is-empty');
        }
        if (kpiPhrases) kpiPhrases.textContent = formatNum(metrics.phrases || 0);
        if (kpiWords) kpiWords.textContent = formatNum(metrics.uniqueWords || 0);
        if (kpiOccurrences) kpiOccurrences.textContent = formatNum(metrics.totalOccurrences || 0);
    }

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function highlightText(text, query) {
        var safe = escapeHtml(text);
        if (!query) {
            return safe;
        }
        var q = query.toLowerCase();
        var lower = String(text).toLowerCase();
        var idx = lower.indexOf(q);
        if (idx === -1) {
            return safe;
        }
        return (
            escapeHtml(text.slice(0, idx)) +
            '<mark class="cabinet-uw-mark">' +
            escapeHtml(text.slice(idx, idx + query.length)) +
            '</mark>' +
            escapeHtml(text.slice(idx + query.length))
        );
    }

    function compareRows(a, b) {
        var av = a[sortKey];
        var bv = b[sortKey];
        if (sortKey === 'count') {
            av = Number(av);
            bv = Number(bv);
            return sortDir === 'asc' ? av - bv : bv - av;
        }
        av = String(av).toLowerCase();
        bv = String(bv).toLowerCase();
        if (av < bv) return sortDir === 'asc' ? -1 : 1;
        if (av > bv) return sortDir === 'asc' ? 1 : -1;
        return 0;
    }

    function rowMatchesSearch(row, query) {
        if (!query) {
            return true;
        }
        var q = query.toLowerCase();
        return (
            row.word.toLowerCase().indexOf(q) !== -1 ||
            row.wordForms.toLowerCase().indexOf(q) !== -1 ||
            row.keyPhrases.toLowerCase().indexOf(q) !== -1 ||
            String(row.count).indexOf(q) !== -1
        );
    }

    function getDisplayRows() {
        var q = searchQuery.trim().toLowerCase();
        return allRows.filter(function (row) {
            return rowMatchesSearch(row, q);
        }).sort(compareRows);
    }

    function updateSearchHint(shown, total) {
        if (!searchHintEl) {
            return;
        }
        if (!searchQuery.trim()) {
            searchHintEl.textContent = '';
            return;
        }
        var tpl = config.searchShownText || 'Показано: :count из :total';
        searchHintEl.textContent = tpl.replace(':count', String(shown)).replace(':total', String(total));
    }

    function updateSortHeaders() {
        root.querySelectorAll('[data-uw-sort]').forEach(function (th) {
            th.classList.remove('cabinet-uw-sort-active');
            var hint = th.querySelector('.cabinet-uw-sort-hint');
            if (hint) {
                hint.textContent = '↕';
            }
            if (th.getAttribute('data-uw-sort') === sortKey) {
                th.classList.add('cabinet-uw-sort-active');
                if (hint) {
                    hint.textContent = sortDir === 'asc' ? '↑' : '↓';
                }
            }
        });
    }

    function renderTable() {
        if (!tbody) {
            return;
        }
        var sorted = getDisplayRows();
        var q = searchQuery.trim();
        var html = sorted.map(function (row) {
            function cell(col, inner, extraClass) {
                var cls = extraClass || '';
                if (!visibleCols[col]) {
                    cls = (cls ? cls + ' ' : '') + 'cabinet-uw-col-hidden';
                }
                return '<td data-col="' + col + '"' + (cls ? ' class="' + cls + '"' : '') + '>' + inner + '</td>';
            }
            return (
                '<tr data-row-key="' + escapeHtml(row.word) + '">' +
                cell(0, highlightText(row.word, q)) +
                cell(1, highlightText(row.wordForms, q)) +
                cell(2, formatNum(row.count), 'text-end') +
                cell(3, '<pre class="cabinet-uw-phrases-preview">' + highlightText(row.keyPhrases, q) + '</pre>', 'cabinet-uw-phrases-cell') +
                '<td class="cabinet-uw-col-actions"><button type="button" class="btn btn-outline-danger btn-sm" data-uw-remove-row title="Удалить"><i class="bi bi-trash3" aria-hidden="true"></i></button></td>' +
                '</tr>'
            );
        }).join('');
        tbody.innerHTML = html;

        root.querySelectorAll('thead th[data-uw-sort]').forEach(function (th, i) {
            th.setAttribute('data-col', String(i));
            th.classList.toggle('cabinet-uw-col-hidden', !visibleCols[i]);
        });

        var hasRows = allRows.length > 0;
        root.classList.toggle('cabinet-uw-has-result', hasRows);
        if (copyBtn) copyBtn.disabled = !hasRows;
        if (downloadBtn) downloadBtn.disabled = !hasRows;
        updateSortHeaders();
        updateSearchHint(sorted.length, allRows.length);
    }

    function pushUndo() {
        undoState = {
            rows: allRows.map(function (row) {
                return {
                    word: row.word,
                    wordForms: row.wordForms,
                    count: row.count,
                    keyPhrases: row.keyPhrases,
                };
            }),
            rangeFrom: rangeFromEl ? rangeFromEl.value : '',
            rangeTo: rangeToEl ? rangeToEl.value : '',
        };
        if (undoBtn) {
            undoBtn.disabled = false;
        }
    }

    function undoLast() {
        if (!undoState) {
            return;
        }
        allRows = undoState.rows;
        if (rangeFromEl) rangeFromEl.value = undoState.rangeFrom;
        if (rangeToEl) rangeToEl.value = undoState.rangeTo;
        undoState = null;
        if (undoBtn) {
            undoBtn.disabled = true;
        }
        recalcKpi();
        renderTable();
    }

    function setRows(newRows, metrics) {
        allRows = newRows || [];
        baseMetrics = metrics;
        undoState = null;
        if (undoBtn) {
            undoBtn.disabled = true;
        }
        recalcKpi();
        renderTable();
        scheduleSave();
    }

    function getVisibleExportRows() {
        var sorted = getDisplayRows();
        return sorted.map(function (row) {
            var out = [];
            if (visibleCols[0]) out.push(row.word);
            if (visibleCols[1]) out.push(row.wordForms);
            if (visibleCols[2]) out.push(String(row.count));
            if (visibleCols[3]) out.push(row.keyPhrases.replace(/\n/g, '; '));
            return out;
        });
    }

    function copyTable() {
        var data = getVisibleExportRows();
        if (!data.length) {
            return;
        }
        var text = data.map(function (row) {
            return row.join('\t');
        }).join('\n');
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function () {
                if (window.toastr) {
                    toastr.success(config.copiedText || 'Скопировано');
                }
            }).catch(function () {
                if (window.toastr) {
                    toastr.error(config.copyFailedText || 'Не удалось скопировать');
                }
            });
        }
    }

    function downloadCsv() {
        var data = getVisibleExportRows();
        if (!data.length) {
            return;
        }
        var lines = data.map(function (row) {
            return row.map(function (cell) {
                var s = String(cell);
                if (/[",\n]/.test(s)) {
                    return '"' + s.replace(/"/g, '""') + '"';
                }
                return s;
            }).join(',');
        });
        var blob = new Blob(['\ufeff' + lines.join('\n')], { type: 'text/csv;charset=utf-8' });
        var a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'unikalnye-slova.csv';
        a.click();
        URL.revokeObjectURL(a.href);
    }

    function applyRangeFilter() {
        var from = rangeFromEl && rangeFromEl.value ? parseInt(rangeFromEl.value, 10) : 0;
        var to = rangeToEl && rangeToEl.value ? parseInt(rangeToEl.value, 10) : 0;
        if (from <= 0 && to <= 0) {
            return;
        }
        pushUndo();
        if (from > 0) {
            allRows = allRows.filter(function (row) {
                return row.count < from;
            });
        }
        if (to > 0) {
            allRows = allRows.filter(function (row) {
                return row.count > to;
            });
        }
        recalcKpi();
        renderTable();
    }

    function fillExample() {
        if (!contentEl || !config.exampleText) {
            return;
        }
        contentEl.value = config.exampleText;
        updatePhraseCount();
        scheduleSave();
    }

    function scheduleSave() {
        if (saveTimer) {
            clearTimeout(saveTimer);
        }
        saveTimer = setTimeout(function () {
            try {
                window.localStorage.setItem(STORAGE_KEY, JSON.stringify({
                    content: contentEl ? contentEl.value : '',
                }));
            } catch (e) {
                /* ignore */
            }
        }, 300);
    }

    function restoreState() {
        try {
            var raw = window.localStorage.getItem(STORAGE_KEY);
            if (!raw || !contentEl) {
                return;
            }
            var state = JSON.parse(raw);
            if (state.content) {
                contentEl.value = state.content;
                updatePhraseCount();
            }
        } catch (e) {
            /* ignore */
        }
    }

    function readTxtFile(file, onLoad) {
        if (!file || !/\.txt$/i.test(file.name)) {
            if (window.toastr) {
                toastr.warning(config.invalidFileText || 'Поддерживаются только файлы .txt');
            }
            return;
        }
        var reader = new FileReader();
        reader.onload = function () {
            onLoad(String(reader.result || ''));
        };
        reader.readAsText(file, 'UTF-8');
    }

    function setProcessing(active) {
        root.classList.toggle('cabinet-uw-processing', active);
        if (processBtn) {
            processBtn.disabled = active;
        }
        if (processLabelEl) {
            processLabelEl.textContent = active
                ? (config.processingWaitText || 'Обработка…')
                : (config.processingText || 'Обработать');
        }
    }

    function process() {
        if (!contentEl) {
            return;
        }
        var content = contentEl.value;
        if (!content.trim()) {
            if (window.toastr) {
                toastr.warning(config.emptyInputText || 'Список ключевых фраз не может быть пустым');
            }
            return;
        }

        setProcessing(true);

        fetch(config.processUrl || '/unique', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ content: content }),
            credentials: 'same-origin',
        })
            .then(function (res) {
                if (!res.ok) {
                    throw new Error('HTTP ' + res.status);
                }
                return res.json();
            })
            .then(function (data) {
                if (searchEl) {
                    searchEl.value = '';
                    searchQuery = '';
                }
                if (rangeFromEl) rangeFromEl.value = '';
                if (rangeToEl) rangeToEl.value = '';
                setRows(data.rows || [], data.metrics || null);
            })
            .catch(function () {
                if (window.toastr) {
                    toastr.error(config.errorTitle || 'Ошибка');
                }
            })
            .finally(function () {
                setProcessing(false);
            });
    }

    function clearAll() {
        if (contentEl) {
            contentEl.value = '';
        }
        allRows = [];
        baseMetrics = null;
        searchQuery = '';
        if (searchEl) searchEl.value = '';
        if (rangeFromEl) rangeFromEl.value = '';
        if (rangeToEl) rangeToEl.value = '';
        undoState = null;
        if (undoBtn) undoBtn.disabled = true;
        updatePhraseCount();
        updateKpi(null);
        renderTable();
        root.classList.remove('cabinet-uw-has-result');
        try {
            window.localStorage.removeItem(STORAGE_KEY);
        } catch (e) {
            /* ignore */
        }
    }

    if (contentEl) {
        contentEl.addEventListener('input', function () {
            updatePhraseCount();
            scheduleSave();
        });
    }

    if (processBtn) {
        processBtn.addEventListener('click', process);
    }

    var clearBtn = root.querySelector('[data-uw-clear]');
    if (clearBtn) {
        clearBtn.addEventListener('click', clearAll);
    }

    if (undoBtn) {
        undoBtn.addEventListener('click', undoLast);
    }

    if (exampleBtn) {
        exampleBtn.addEventListener('click', fillExample);
    }

    var rangeRemoveBtn = root.querySelector('[data-uw-range-remove]');
    if (rangeRemoveBtn) {
        rangeRemoveBtn.addEventListener('click', applyRangeFilter);
    }

    if (searchEl) {
        searchEl.addEventListener('input', function () {
            searchQuery = searchEl.value;
            renderTable();
        });
    }

    if (copyBtn) {
        copyBtn.addEventListener('click', copyTable);
    }
    if (downloadBtn) {
        downloadBtn.addEventListener('click', downloadCsv);
    }

    root.querySelectorAll('[data-uw-vis-col]').forEach(function (input) {
        input.addEventListener('change', function () {
            var col = input.getAttribute('data-uw-vis-col');
            if (col !== null) {
                visibleCols[col] = input.checked;
                renderTable();
            }
        });
    });

    root.querySelectorAll('[data-uw-sort]').forEach(function (th) {
        th.addEventListener('click', function () {
            var key = th.getAttribute('data-uw-sort');
            if (!key) {
                return;
            }
            if (sortKey === key) {
                sortDir = sortDir === 'asc' ? 'desc' : 'asc';
            } else {
                sortKey = key;
                sortDir = key === 'count' ? 'desc' : 'asc';
            }
            renderTable();
        });
    });

    if (tbody) {
        tbody.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-uw-remove-row]');
            if (!btn) {
                return;
            }
            var tr = btn.closest('tr');
            if (!tr) {
                return;
            }
            pushUndo();
            var key = tr.getAttribute('data-row-key');
            allRows = allRows.filter(function (row) {
                return row.word !== key;
            });
            recalcKpi();
            renderTable();
        });
    }

    if (dropzone) {
        ['dragenter', 'dragover'].forEach(function (ev) {
            dropzone.addEventListener(ev, function (e) {
                e.preventDefault();
                dropzone.classList.add('cabinet-uw-dropzone--active');
            });
        });
        ['dragleave', 'drop'].forEach(function (ev) {
            dropzone.addEventListener(ev, function (e) {
                e.preventDefault();
                dropzone.classList.remove('cabinet-uw-dropzone--active');
            });
        });
        dropzone.addEventListener('drop', function (e) {
            var file = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
            readTxtFile(file, function (text) {
                if (contentEl) {
                    contentEl.value = text;
                    updatePhraseCount();
                    scheduleSave();
                }
            });
        });
    }

    document.addEventListener('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
            if (root.contains(document.activeElement)) {
                e.preventDefault();
                process();
            }
        }
        if ((e.ctrlKey || e.metaKey) && e.key === 'z' && undoState) {
            if (root.contains(document.activeElement)) {
                e.preventDefault();
                undoLast();
            }
        }
    });

    restoreState();
    updatePhraseCount();
    renderTable();
})(window, document);
