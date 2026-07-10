(function (window, document) {
    'use strict';

    var root = document.querySelector('.cabinet-esenin-page--public');
    if (!root) {
        return;
    }

    var configEl = document.getElementById('cabinet-esenin-public-config');
    var config = {};
    if (configEl && configEl.textContent) {
        try {
            config = JSON.parse(configEl.textContent);
        } catch (e) {
            config = {};
        }
    }

    var result = config.result || {};
    var modes = config.modes || {};
    var activeBlock = 'risk';

    var scoreNav = root.querySelector('[data-esenin-score-nav]');
    var highlightEl = root.querySelector('[data-esenin-highlight]');
    var statsEl = root.querySelector('[data-esenin-stats]');
    var paramsEl = root.querySelector('[data-esenin-params]');
    var panelTitleEl = root.querySelector('[data-esenin-panel-title]');
    var legendEl = root.querySelector('[data-esenin-legend]');
    var frequencyListsEl = root.querySelector('[data-esenin-frequency-lists]');

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function formatNumber(value) {
        return String(value).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    }

    function levelClass(score) {
        if (score >= 13) return 'text-bg-danger';
        if (score >= 8) return 'text-bg-warning';
        if (score >= 5) return 'text-bg-info';
        return 'text-bg-success';
    }

    var blockLegends = {
        risk: 'Цветом показаны все найденные проблемы. Красный «!» у фрагмента — наведите курсор, чтобы прочитать, что не так и что сделать.',
        frequency: 'Фиолетовым — частые слова и фразы. Справа таблица «Слова» и «Словосочетания». Наведите на «!» — подробности.',
        style: 'Зелёным — возможные стилистические проблемы, жёлтым — почти наверняка стоит править. Наведите на «!» — как лучше переписать.',
        keywords: 'Синим — повторяющиеся SEO-фразы. Наведите на «!» — как разбавить текст.',
        readability: 'Зелёным — слишком длинные слова. Наведите на «!» — как упростить.'
    };

    var tipPopoverEl = null;

    function initMarkTooltips(container) {
        if (!container) return;

        if (!tipPopoverEl) {
            tipPopoverEl = document.createElement('div');
            tipPopoverEl.className = 'esenin-tip-popover';
            tipPopoverEl.hidden = true;
            document.body.appendChild(tipPopoverEl);
        }

        container.querySelectorAll('[data-esenin-tip]').forEach(function (mark) {
            mark.addEventListener('mouseenter', function () {
                var text = mark.getAttribute('data-esenin-tip') || '';
                if (!text) return;
                tipPopoverEl.textContent = text;
                tipPopoverEl.hidden = false;
                tipPopoverEl.classList.add('is-visible');
                var rect = mark.getBoundingClientRect();
                tipPopoverEl.style.top = (rect.top + window.scrollY - tipPopoverEl.offsetHeight - 8) + 'px';
                tipPopoverEl.style.left = Math.max(8, rect.left + window.scrollX) + 'px';
            });
            mark.addEventListener('mouseleave', function () {
                tipPopoverEl.classList.remove('is-visible');
                tipPopoverEl.hidden = true;
            });
        });
    }

    function blockParams(block) {
        if (block === 'risk') return result.params || [];
        if (result.blocks && result.blocks[block]) return result.blocks[block].params || [];
        return [];
    }

    function blockScore(block) {
        if (block === 'risk') return Number(result.risk || 0);
        if (result.blocks && result.blocks[block]) return Number(result.blocks[block].score || 0);
        return 0;
    }

    function renderFrequencyLists() {
        if (!frequencyListsEl) return;
        var lists = result.frequency_lists || {};
        var words = lists.words || [];
        var phrases = lists.phrases || [];

        function renderTable(rows, type) {
            if (!rows.length) return '<p class="small text-secondary mb-0">Нет данных</p>';
            var html = '<div class="table-responsive"><table class="table table-sm mb-0"><thead><tr>' +
                '<th>' + (type === 'words' ? 'Слово' : 'Фраза') + '</th>' +
                '<th class="text-end">Кол-во</th><th class="text-end">%</th></tr></thead><tbody>';
            rows.forEach(function (row) {
                var label = type === 'words' ? row.word : row.phrase;
                html += '<tr><td class="small">' + escapeHtml(label) + '</td>' +
                    '<td class="text-end small">' + escapeHtml(String(row.count)) + '</td>' +
                    '<td class="text-end small">' + escapeHtml(String(row.percent)) + '</td></tr>';
            });
            return html + '</tbody></table></div>';
        }

        var wordsPanel = frequencyListsEl.querySelector('[data-esenin-frequency-panel="words"]');
        var phrasesPanel = frequencyListsEl.querySelector('[data-esenin-frequency-panel="phrases"]');
        if (wordsPanel) wordsPanel.innerHTML = renderTable(words, 'words');
        if (phrasesPanel) phrasesPanel.innerHTML = renderTable(phrases, 'phrases');

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

    function renderBlock(block) {
        activeBlock = block;

        if (scoreNav) {
            scoreNav.querySelectorAll('[data-esenin-block]').forEach(function (btn) {
                btn.classList.toggle('active', btn.getAttribute('data-esenin-block') === block);
            });
        }

        if (highlightEl) {
            highlightEl.innerHTML = (result.highlights && result.highlights[block]) || result.highlighted_html || '';
            initMarkTooltips(highlightEl);
        }

        if (legendEl) {
            legendEl.textContent = blockLegends[block] || blockLegends.risk;
            legendEl.classList.remove('d-none');
        }

        if (panelTitleEl) {
            panelTitleEl.textContent = block === 'risk' ? 'Общий риск' : (modes[block] || block);
        }

        if (paramsEl) {
            paramsEl.innerHTML = '';
            blockParams(block).forEach(function (item) {
                var tr = document.createElement('tr');
                tr.innerHTML =
                    '<td class="small">' + escapeHtml(item.name || '') + '</td>' +
                    '<td class="text-end small">' + escapeHtml(String(item.value !== undefined && item.value !== null ? item.value : '—')) + '</td>' +
                    '<td class="text-end"><span class="badge text-bg-light text-dark">' + escapeHtml(String(item.score || 0)) + '</span></td>';
                paramsEl.appendChild(tr);
            });
        }

        if (frequencyListsEl) {
            if (block === 'frequency') {
                frequencyListsEl.classList.remove('d-none');
                renderFrequencyLists();
            } else {
                frequencyListsEl.classList.add('d-none');
            }
        }
    }

    function renderResult() {
        if (scoreNav) {
            scoreNav.innerHTML = '';
            var navBlocks = [{ id: 'risk', label: modes.risk || 'Общий риск' }];
            (result.details || []).forEach(function (item) {
                navBlocks.push({ id: item.block, label: item.label || item.block });
            });
            navBlocks.forEach(function (item) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'cabinet-esenin-score-btn';
                btn.setAttribute('data-esenin-block', item.id);
                var score = blockScore(item.id);
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
    }

    renderResult();
}(window, document));
