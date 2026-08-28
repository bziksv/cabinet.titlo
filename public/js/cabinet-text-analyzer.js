/**
 * Анализ текста — результаты (таблицы, спиральное облако, Chart.js).
 * jQCloud намеренно не используем: блокирует main thread (зависание UI).
 */
(function ($, window, document) {
    'use strict';

    function releaseUiLock() {
        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('overflow');
        document.body.style.removeProperty('padding-right');
        document.body.style.removeProperty('pointer-events');
        document.documentElement.style.removeProperty('pointer-events');
        document.querySelectorAll('.modal-backdrop, div.dtr-modal, div.dtr-modal-background, .card > .overlay, .overlay-wrapper > .overlay').forEach(function (el) {
            el.remove();
        });
        if (typeof window.cabinetReleaseUiLock === 'function') {
            window.cabinetReleaseUiLock();
        }
        $('.cabinet-text-analyzer-page .dataTables_processing').remove();
    }

    function startUiLockWatchdog() {
        var ticks = 0;
        var timer = window.setInterval(function () {
            releaseUiLock();
            if (++ticks >= 40) {
                window.clearInterval(timer);
            }
        }, 250);
    }

    function initCloudTooltips() {
        if (window._cabinetTaCloudTipsBound) {
            return;
        }
        window._cabinetTaCloudTipsBound = true;
        var $tip = $('#cabinet-ta-cloud-tooltip');
        if (!$tip.length) {
            $tip = $('<div id="cabinet-ta-cloud-tooltip" class="cabinet-ta-cloud-tooltip" role="tooltip"></div>').appendTo('body');
        }
        var selector = '.cabinet-text-analyzer-page .cabinet-ta-spiral-cloud__word[data-tip], .cabinet-text-analyzer-page .cabinet-ta-tag-cloud__word[title]';
        $(document)
            .on('mouseenter', selector, function (e) {
                var title = $(this).attr('data-tip') || $(this).attr('title');
                if (!title) {
                    return;
                }
                $tip.text(title).addClass('is-visible').css({left: e.pageX + 14, top: e.pageY + 14});
            })
            .on('mousemove', selector, function (e) {
                if (!$tip.hasClass('is-visible')) {
                    return;
                }
                $tip.css({left: e.pageX + 14, top: e.pageY + 14});
            })
            .on('mouseleave', selector, function () {
                $tip.removeClass('is-visible');
            });
    }

    function boxesOverlap(a, b, pad) {
        pad = pad || 5;
        return !(
            a.right + pad <= b.left ||
            a.left >= b.right + pad ||
            a.bottom + pad <= b.top ||
            a.top >= b.bottom + pad
        );
    }

    function weightClass(ratio) {
        var bucket = Math.max(1, Math.min(10, Math.round(ratio * 9) + 1));
        return 'cabinet-ta-spiral-cloud__word--w' + bucket;
    }

    function readJsonScript(id, fallback) {
        var el = document.getElementById(id);
        if (!el || !el.textContent) {
            return fallback;
        }
        try {
            return JSON.parse(el.textContent);
        } catch (e) {
            console.error('cabinet-text-analyzer: parse #' + id, e);
            return fallback;
        }
    }

    function cloudFromArray(array) {
        if (!array) {
            return [];
        }
        var list = Array.isArray(array) ? array : Object.keys(array).map(function (key) {
            return array[key];
        });
        return list.filter(function (item) {
            return item && typeof item === 'object' && item.text;
        });
    }

    function normalizeCloudWords(words, limit, repetitionsLabel) {
        if (!words.length) {
            return [];
        }
        var sorted = words.slice().sort(function (a, b) {
            return (b.weight || 0) - (a.weight || 0);
        }).slice(0, limit);
        var maxW = sorted[0].weight || 1;
        var minW = sorted[sorted.length - 1].weight || 1;
        return sorted.map(function (word) {
            var count = word.weight || 1;
            var scaled = maxW > minW
                ? Math.round(((count - minW) / (maxW - minW)) * 99) + 1
                : 50;
            var tip = String(word.text) + ' — ' + repetitionsLabel + ': ' + count;
            return {
                text: String(word.text),
                weight: count,
                scaled: scaled,
                tip: tip
            };
        });
    }

    function CloudRenderer(cfg) {
        this.hostSelector = cfg.hostSelector;
        this.getData = cfg.getData;
        this.wordLimit = cfg.wordLimit || 80;
        this.emptyLabel = cfg.emptyLabel || '';
        this.repetitionsLabel = cfg.repetitionsLabel || '';
        this.painted = false;
    }

    CloudRenderer.prototype.paintSpiralCloud = function ($host, words) {
        $host.empty().removeClass('jqcloud cabinet-ta-tag-cloud-host').addClass('cabinet-ta-spiral-cloud--ready');
        if (!words.length) {
            $host.append($('<p class="text-secondary small mb-0 text-center py-5"></p>').text(this.emptyLabel));
            return;
        }

        var hostW = Math.max(260, Math.floor($host.innerWidth() || $host.width() || 320));
        var hostH = Math.max(320, Math.floor($host.innerHeight() || $host.height() || 400));
        var maxWeight = words[0].weight || 1;
        var placed = [];
        var edgePad = 18;
        var $wrap = $('<div class="cabinet-ta-spiral-cloud"></div>').css({
            width: hostW + 'px',
            height: hostH + 'px'
        });
        $host.append($wrap);

        words.forEach(function (word) {
            var ratio = maxWeight > 0 ? (word.weight || 1) / maxWeight : 1;
            var len = String(word.text).length;
            var sizePx = Math.round(11 + ratio * 24 - Math.min(7, Math.max(0, len - 7) * 0.35));
            var $el = $('<span class="cabinet-ta-spiral-cloud__word"></span>')
                .addClass(weightClass(ratio))
                .text(word.text)
                .css('font-size', sizePx + 'px')
                .attr('data-tip', word.tip);

            $wrap.append($el);
            var w = $el.outerWidth();
            var h = $el.outerHeight();
            var cx = hostW / 2;
            var cy = hostH / 2;
            var angle = 0;
            var radius = 0;
            var step = 0.42;
            var placedOk = false;
            var i;

            for (i = 0; i < 500; i++) {
                var x = cx + radius * Math.cos(angle);
                var y = cy + radius * Math.sin(angle);
                var box = {
                    left: x - w / 2,
                    top: y - h / 2,
                    right: x + w / 2,
                    bottom: y + h / 2
                };

                if (box.left < edgePad || box.top < edgePad || box.right > hostW - edgePad || box.bottom > hostH - edgePad) {
                    angle += step;
                    radius += 0.75;
                    continue;
                }

                var collision = false;
                var j;
                for (j = 0; j < placed.length; j++) {
                    if (boxesOverlap(box, placed[j], 4)) {
                        collision = true;
                        break;
                    }
                }

                if (!collision) {
                    $el.css({left: x + 'px', top: y + 'px'});
                    placed.push(box);
                    placedOk = true;
                    break;
                }

                angle += step;
                radius += 0.75;
            }

            if (!placedOk) {
                $el.remove();
            }
        });
    };

    CloudRenderer.prototype.paint = function (force) {
        if (this.painted && !force) {
            return true;
        }
        var $host = $(this.hostSelector);
        if (!$host.length) {
            return false;
        }

        var words = normalizeCloudWords(
            cloudFromArray(this.getData()),
            this.wordLimit,
            this.repetitionsLabel
        );

        if (!words.length) {
            $host.empty().removeClass('jqcloud cabinet-ta-spiral-cloud--ready cabinet-ta-tag-cloud-host');
            $host.append($('<p class="text-secondary small mb-0 text-center py-5"></p>').text(this.emptyLabel));
            this.painted = true;
            return true;
        }

        var maxWeight = words[0].weight || 1;

        try {
            this.paintSpiralCloud($host, words);
        } catch (e) {
            console.error('cabinet-text-analyzer: spiral cloud', e);
            $host.empty().removeClass('cabinet-ta-spiral-cloud--ready').addClass('cabinet-ta-tag-cloud-host');
            var $wrap = $('<div class="cabinet-ta-tag-cloud"></div>');
            words.forEach(function (word) {
                var ratio = maxWeight > 0 ? (word.weight || 1) / maxWeight : 1;
                var sizeRem = (0.78 + ratio * 1.55).toFixed(2);
                $wrap.append(
                    $('<span class="cabinet-ta-tag-cloud__word"></span>')
                        .text(word.text)
                        .css('font-size', sizeRem + 'rem')
                        .attr('title', word.tip)
                );
            });
            $host.append($wrap);
        }

        this.painted = true;
        return true;
    };

    function ZipfChart(cfg) {
        this.canvasId = cfg.canvasId;
        this.graph = cfg.graph || [];
        this.graphCompetitor = cfg.graphCompetitor || [];
        this.compare = !!cfg.compare;
        this.labels = cfg.labels || {};
        this.instance = null;
        this.resizeBound = false;
    }

    ZipfChart.prototype.destroy = function () {
        if (this.instance) {
            this.instance.destroy();
            this.instance = null;
        }
    };

    ZipfChart.prototype.buildIdeal = function (baseY, count) {
        var points = [];
        var r;
        for (r = 1; r <= count; r++) {
            points.push({x: r, y: Math.max(1, Math.round(baseY / r))});
        }
        return points;
    };

    ZipfChart.prototype.labelAt = function (rank) {
        var i;
        for (i = 0; i < this.graph.length; i++) {
            if (this.graph[i].x === rank && this.graph[i].label) {
                return this.graph[i].label;
            }
        }
        return null;
    };

    ZipfChart.prototype.render = function () {
        this.destroy();
        var canvas = document.getElementById(this.canvasId);
        if (!canvas || typeof Chart === 'undefined' || !this.graph.length || typeof this.graph[0].y === 'undefined') {
            return;
        }
        var graph = this.graph;
        var rankMax = graph.length;
        if (this.compare && this.graphCompetitor.length) {
            rankMax = Math.max(rankMax, this.graphCompetitor.length);
        }
        var baseY = graph[0].y;
        var actualLabel = this.labels.actual || 'Actual';
        var idealLabel = this.labels.ideal || 'Ideal';
        var competitorLabel = this.labels.competitor || 'Competitor';
        var xAxisLabel = this.labels.xAxis || this.labels.rank || 'Word density';
        var self = this;
        var datasets = [
            {
                label: actualLabel,
                data: graph.map(function (point) {
                    return {x: point.x, y: point.y};
                }),
                borderColor: '#1d4ed8',
                backgroundColor: 'rgba(29, 78, 216, 0.12)',
                pointBackgroundColor: '#1d4ed8',
                pointBorderColor: '#fff',
                pointBorderWidth: 1,
                borderWidth: 2.5,
                pointRadius: 4,
                pointHoverRadius: 6,
                tension: 0.15,
                fill: false
            }
        ];

        if (this.compare && this.graphCompetitor.length) {
            datasets.push({
                label: competitorLabel,
                data: this.graphCompetitor.map(function (point) {
                    return {x: point.x, y: point.y};
                }),
                borderColor: '#ca8a04',
                backgroundColor: 'rgba(202, 138, 4, 0.1)',
                pointBackgroundColor: '#ca8a04',
                pointBorderColor: '#fff',
                pointBorderWidth: 1,
                borderWidth: 2.5,
                pointRadius: 4,
                pointHoverRadius: 6,
                tension: 0.15,
                fill: false
            });
        }

        datasets.push({
            label: idealLabel,
            data: this.buildIdeal(baseY, graph.length),
            borderColor: '#ea580c',
            backgroundColor: 'rgba(234, 88, 12, 0.08)',
            pointBackgroundColor: '#ea580c',
            pointBorderColor: '#fff',
            pointBorderWidth: 1,
            borderWidth: 2,
            borderDash: [7, 5],
            pointRadius: 3,
            pointHoverRadius: 5,
            tension: 0.15,
            fill: false
        });

        this.instance = new Chart(canvas.getContext('2d'), {
            type: 'line',
            data: {
                datasets: datasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: false,
                interaction: {mode: 'nearest', intersect: false},
                plugins: {
                    legend: {display: true, position: 'top', align: 'end'},
                    tooltip: {
                        callbacks: {
                            title: function (items) {
                                if (!items.length) {
                                    return '';
                                }
                                var item = items[0];
                                var point;
                                if (item.datasetIndex === 0) {
                                    point = graph[item.dataIndex];
                                } else if (self.compare && self.graphCompetitor.length && item.datasetIndex === 1) {
                                    point = self.graphCompetitor[item.dataIndex];
                                } else {
                                    return idealLabel;
                                }
                                return point && point.label ? point.label : '#' + item.parsed.x;
                            },
                            label: function (ctx) {
                                var name = actualLabel;
                                var idealIndex = self.compare && self.graphCompetitor.length ? 2 : 1;
                                if (ctx.datasetIndex === 1 && self.compare && self.graphCompetitor.length) {
                                    name = competitorLabel;
                                } else if (ctx.datasetIndex === idealIndex) {
                                    name = idealLabel;
                                }
                                return name + ': ' + ctx.parsed.y;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        type: 'linear',
                        min: 1,
                        max: rankMax,
                        title: {display: true, text: xAxisLabel},
                        grid: {color: 'rgba(0, 0, 0, 0.06)'},
                        ticks: {
                            stepSize: 1,
                            autoSkip: true,
                            maxRotation: 55,
                            minRotation: 55,
                            font: {size: 10},
                            callback: function (value) {
                                if (Math.floor(value) !== value) {
                                    return '';
                                }
                                var label = self.labelAt(value);
                                return label ? (label.length > 12 ? label.substring(0, 11) + '…' : label) : '';
                            }
                        }
                    },
                    y: {
                        beginAtZero: false,
                        grid: {color: 'rgba(0, 0, 0, 0.06)'}
                    }
                }
            }
        });

        if (!this.resizeBound) {
            this.resizeBound = true;
            var timer;
            $(window).on('resize.cabinetTaChart', function () {
                clearTimeout(timer);
                timer = setTimeout(function () {
                    if (self.instance) {
                        self.instance.resize();
                    }
                }, 150);
            });
        }
    };

    function collapseWordRows() {
        $('#totalTable tr.cabinet-ta-word-detail-row').addClass('d-none');
        $('#totalTable tbody tr.cabinet-ta-word-row').removeClass('is-expanded');
        $('#totalTable .cabinet-ta-word-toggle').attr('aria-expanded', 'false')
            .find('.cabinet-ta-word-toggle__icon')
            .removeClass('bi-chevron-up').addClass('bi-chevron-down');
    }

    function updateTableCount($input) {
        var tableSel = $input.data('table');
        var rowSel = $input.data('row') || 'tbody tr';
        var $table = $(tableSel);
        var visible = $table.find(rowSel + ':not(.d-none)').length;
        var total = $table.find(rowSel).length;
        var $count = $input.closest('.cabinet-ta-dt-card').find('.cabinet-ta-table-count');
        if (!$count.length) {
            return;
        }
        $count.text(visible === total ? total : visible + ' / ' + total);
    }

    function filterTable($input) {
        var q = ($input.val() || '').toLowerCase().trim();
        var tableSel = $input.data('table');
        var rowSel = $input.data('row') || 'tbody tr';
        var $table = $(tableSel);

        $table.find(rowSel).each(function () {
            var $row = $(this);
            var match = !q || $row.text().toLowerCase().indexOf(q) !== -1;
            $row.toggleClass('d-none', !match);
            if (!match && $row.hasClass('cabinet-ta-word-row')) {
                var $detail = $row.next('tr.cabinet-ta-word-detail-row');
                if ($detail.length) {
                    $detail.addClass('d-none');
                }
                $row.removeClass('is-expanded');
            }
        });

        updateTableCount($input);
    }

    function parseSortValue(raw, preferNum) {
        if (raw === undefined || raw === null) {
            return { empty: true, num: 0, str: '' };
        }
        var s = String(raw).replace(/\u00a0/g, ' ').trim();
        if (s === '' || s === '—' || s === '–' || s === '-') {
            return { empty: true, num: 0, str: '' };
        }
        var normalized = s.replace(/\s+/g, '').replace(',', '.').replace(/^\+/, '');
        var num = Number(normalized);
        var isNum = normalized !== '' && !isNaN(num) && /^-?\d+(\.\d+)?$/.test(normalized);
        if (preferNum || isNum) {
            return { empty: !isNum, num: isNum ? num : 0, str: s.toLowerCase() };
        }
        return { empty: false, num: 0, str: s.toLowerCase() };
    }

    function cellSortValue($td, preferNum) {
        if (!$td || !$td.length) {
            return { empty: true, num: 0, str: '' };
        }
        var order = $td.attr('data-order');
        if (order !== undefined && order !== null && String(order).length) {
            return parseSortValue(order, preferNum);
        }
        return parseSortValue($td.text(), preferNum);
    }

    function compareSortValues(a, b, dir) {
        if (a.empty && b.empty) {
            return 0;
        }
        if (a.empty) {
            return 1;
        }
        if (b.empty) {
            return -1;
        }
        var cmp = a.str.localeCompare(b.str, 'ru', { numeric: true, sensitivity: 'base' });
        return dir === 'asc' ? cmp : -cmp;
    }

    function collectSortGroups($tbody) {
        var groups = [];
        $tbody.children('tr').each(function () {
            var $tr = $(this);
            if ($tr.hasClass('cabinet-ta-word-detail-row')) {
                if (groups.length) {
                    groups[groups.length - 1].detail = $tr;
                }
                return;
            }
            if ($tr.children('td[colspan]').length && $tr.find('.cabinet-ta-exclude-term').length === 0) {
                groups.push({ row: $tr, detail: null, sticky: true });
                return;
            }
            groups.push({ row: $tr, detail: null, sticky: false });
        });
        return groups;
    }

    function sortTableByColumn($table, colIndex, dir, preferNum) {
        var $tbody = $table.children('tbody');
        if (!$tbody.length) {
            return;
        }
        var groups = collectSortGroups($tbody);
        var sticky = groups.filter(function (g) { return g.sticky; });
        var sortable = groups.filter(function (g) { return !g.sticky; });

        sortable.sort(function (ga, gb) {
            var va = cellSortValue(ga.row.children('td').eq(colIndex), preferNum);
            var vb = cellSortValue(gb.row.children('td').eq(colIndex), preferNum);
            if (preferNum) {
                if (va.empty && vb.empty) return 0;
                if (va.empty) return 1;
                if (vb.empty) return -1;
                var cmp = va.num - vb.num;
                if (cmp !== 0) {
                    return dir === 'asc' ? cmp : -cmp;
                }
                return dir === 'asc'
                    ? va.str.localeCompare(vb.str, 'ru', { numeric: true, sensitivity: 'base' })
                    : vb.str.localeCompare(va.str, 'ru', { numeric: true, sensitivity: 'base' });
            }
            return compareSortValues(va, vb, dir);
        });

        sticky.concat(sortable).forEach(function (g) {
            $tbody.append(g.row);
            if (g.detail && g.detail.length) {
                $tbody.append(g.detail);
            }
        });
    }

    function setSortHeaderState($table, $activeTh, dir) {
        $table.find('thead .cabinet-ta-th-sort').each(function () {
            var $th = $(this);
            var active = $th[0] === $activeTh[0];
            $th.removeClass('is-sorted-asc is-sorted-desc');
            $th.attr('aria-sort', active ? (dir === 'asc' ? 'ascending' : 'descending') : 'none');
            if (active) {
                $th.addClass(dir === 'asc' ? 'is-sorted-asc' : 'is-sorted-desc');
            }
            var $icon = $th.children('.cabinet-ta-th-sort__icon');
            if (!$icon.length) {
                return;
            }
            $icon
                .removeClass('bi-arrow-down-up bi-sort-up bi-sort-down')
                .addClass(active ? (dir === 'asc' ? 'bi-sort-up' : 'bi-sort-down') : 'bi-arrow-down-up');
        });
    }

    function initTableSort() {
        $('.cabinet-text-analyzer-page table.cabinet-ta-sortable-table').each(function () {
            var $table = $(this);
            if ($table.data('cabinetTaSortBound')) {
                return;
            }
            $table.data('cabinetTaSortBound', true);
            $table.data('cabinetTaSort', { col: null, dir: null });

            $table.find('thead .cabinet-ta-th-sort').each(function () {
                var $th = $(this);
                if (!$th.children('.cabinet-ta-th-sort__icon').length) {
                    $th.append('<i class="bi bi-arrow-down-up cabinet-ta-th-sort__icon" aria-hidden="true"></i>');
                }
            });

            $table.on('click.cabinetTaSort', 'thead .cabinet-ta-th-sort', function (e) {
                e.preventDefault();
                var $th = $(this);
                var col = parseInt($th.attr('data-sort-col'), 10);
                if (isNaN(col)) {
                    return;
                }
                var preferNum = ($th.attr('data-sort-type') || '') === 'num';
                var state = $table.data('cabinetTaSort') || { col: null, dir: null };
                var dir;
                if (state.col === col) {
                    dir = state.dir === 'asc' ? 'desc' : 'asc';
                } else {
                    dir = preferNum ? 'desc' : 'asc';
                }
                sortTableByColumn($table, col, dir, preferNum);
                $table.data('cabinetTaSort', { col: col, dir: dir });
                setSortHeaderState($table, $th, dir);
            });

            $table.on('keydown.cabinetTaSort', 'thead .cabinet-ta-th-sort', function (e) {
                if (e.key !== 'Enter' && e.key !== ' ') {
                    return;
                }
                e.preventDefault();
                $(this).trigger('click');
            });
        });
    }

    function initWordFormsToggle(wordForms) {
        $('#totalTable').on('click', '.cabinet-ta-word-toggle', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var $btn = $(this);
            var $row = $btn.closest('tr.cabinet-ta-word-row');
            if ($row.hasClass('d-none')) {
                return;
            }
            var wordId = $row.attr('data-cabinet-ta-word-id');
            var panelHtml = (wordForms || {})[wordId] || '';
            if (!panelHtml) {
                return;
            }

            var $detail = $row.next('tr.cabinet-ta-word-detail-row');
            if ($detail.length) {
                var open = !$detail.hasClass('d-none');
                collapseWordRows();
                if (!open) {
                    $detail.removeClass('d-none');
                    $row.addClass('is-expanded');
                    $btn.attr('aria-expanded', 'true')
                        .find('.cabinet-ta-word-toggle__icon')
                        .removeClass('bi-chevron-down').addClass('bi-chevron-up');
                }
                return;
            }

            collapseWordRows();
            $(
                '<tr class="cabinet-ta-word-detail-row">' +
                '<td colspan="5"><div class="cabinet-ta-word-detail__cell">' + panelHtml + '</div></td>' +
                '</tr>'
            ).insertAfter($row);
            $row.addClass('is-expanded');
            $btn.attr('aria-expanded', 'true')
                .find('.cabinet-ta-word-toggle__icon')
                .removeClass('bi-chevron-down').addClass('bi-chevron-up');
        });
    }

    function initTableSearch() {
        $('.cabinet-ta-table-search').each(function () {
            updateTableCount($(this));
        }).on('input', function () {
            filterTable($(this));
        });
    }

    function initResultTables(cfg) {
        initWordFormsToggle(cfg.wordForms || {});
        initTableSearch();
        initTableSort();
    }

    function initAllClouds(clouds) {
        clouds.forEach(function (item, index) {
            if (!item || !item.renderer) {
                return;
            }
            window.setTimeout(function () {
                item.renderer.paint(true);
            }, index * 40);
        });
    }

    function buildCloudRenderers(cfg, payload) {
        var cloudDefs = [
            {suffix: 'text', key: 'text'},
            {suffix: 'links', key: 'links'},
            {suffix: 'both', key: 'both'}
        ];
        var clouds = [];
        cloudDefs.forEach(function (zone) {
            clouds.push({
                hostSelector: '#cabinet-ta-cloud-' + zone.suffix + '-host',
                getData: function () { return (payload.clouds || {})[zone.key]; }
            });
            if (payload.compare) {
                clouds.push({
                    hostSelector: '#cabinet-ta-cloud-' + zone.suffix + '-competitor-host',
                    getData: function () { return (payload.cloudsCompetitor || {})[zone.key]; }
                });
            }
        });
        return clouds.map(function (item) {
            item.renderer = new CloudRenderer({
                hostSelector: item.hostSelector,
                getData: item.getData,
                wordLimit: cfg.cloudWordLimit || 80,
                emptyLabel: cfg.emptyLabel || '',
                repetitionsLabel: cfg.repetitionsLabel || ''
            });
            return item;
        });
    }

    function initResultsHeavyVisuals(cfg, payload) {
        var $chartWrap = $('.cabinet-ta-chart-wrap');
        $chartWrap.removeClass('cabinet-ta-chart-wrap--loading');

        try {
            (new ZipfChart({
                canvasId: 'cabinet-ta-zipf-chart',
                graph: payload.graph || [],
                graphCompetitor: payload.graphCompetitor || [],
                compare: !!payload.compare,
                labels: cfg.chartLabels || {}
            })).render();
        } catch (e) {
            console.error('cabinet-text-analyzer: chart', e);
        }

        try {
            initAllClouds(buildCloudRenderers(cfg, payload));
            initCloudTooltips();
        } catch (e) {
            console.error('cabinet-text-analyzer: cloud', e);
        }

        releaseUiLock();
    }

    function scheduleResultsHeavyInit(cfg, payload) {
        var $chartWrap = $('.cabinet-ta-chart-wrap');
        if ($chartWrap.length) {
            $chartWrap.addClass('cabinet-ta-chart-wrap--loading');
        }

        var run = function () {
            initResultsHeavyVisuals(cfg, payload);
        };

        if (typeof window.requestAnimationFrame === 'function') {
            window.requestAnimationFrame(function () {
                window.requestAnimationFrame(function () {
                    window.setTimeout(run, 0);
                });
            });
        } else {
            window.setTimeout(run, 0);
        }
    }

    function initResults(cfg) {
        releaseUiLock();

        var payload = readJsonScript('cabinet-ta-payload', {
            compare: false,
            clouds: {text: [], links: [], both: []},
            cloudsCompetitor: {},
            graph: [],
            graphCompetitor: []
        });
        var wordForms = readJsonScript('cabinet-ta-word-forms', {});

        try {
            if (!payload.compare) {
                initResultTables({wordForms: wordForms});
            } else {
                initTableSearch();
            }
        } catch (e) {
            console.error('cabinet-text-analyzer: tables', e);
        }

        scheduleResultsHeavyInit(cfg, payload);

        if (cfg.scrollToResults) {
            var $results = $('.cabinet-ta-results');
            if ($results.length) {
                window.requestAnimationFrame(function () {
                    var top = $results.offset().top - 80;
                    window.scrollTo(0, top > 0 ? top : 0);
                });
            }
        }
    }

    function initPublicShare() {
        var $root = $('#cabinet-ta-public-share');
        if (!$root.length) {
            return;
        }

        var $url = $('#cabinet-ta-public-share-url');
        var $expires = $('#cabinet-ta-public-share-expires');
        var $copy = $('#cabinet-ta-public-share-copy');
        var $create = $('#cabinet-ta-public-share-create');
        var $revoke = $('#cabinet-ta-public-share-revoke');
        var token = $('meta[name="csrf-token"]').attr('content');
        var labels = {
            create: $create.text().trim(),
            refresh: window.cabinetTaShareLabels && window.cabinetTaShareLabels.refresh
                ? window.cabinetTaShareLabels.refresh
                : $create.text().trim(),
            validUntil: window.cabinetTaShareLabels && window.cabinetTaShareLabels.validUntil
                ? window.cabinetTaShareLabels.validUntil
                : ''
        };

        $copy.on('click', function () {
            var input = $url.get(0);
            if (!input || !input.value) {
                return;
            }
            input.select();
            input.setSelectionRange(0, input.value.length);
            try {
                document.execCommand('copy');
            } catch (e) {
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(input.value);
                }
            }
            var copiedLabel = window.cabinetTaShareLabels && window.cabinetTaShareLabels.copied
                ? window.cabinetTaShareLabels.copied
                : '';
            if (copiedLabel) {
                $copy.addClass('is-copied');
                var prevHtml = $copy.html();
                $copy.html('<i class="bi bi-check2"></i><span class="d-none d-md-inline ms-1">' + copiedLabel + '</span>');
                window.setTimeout(function () {
                    $copy.removeClass('is-copied').html(prevHtml);
                }, 1600);
            }
        });

        $create.on('click', function () {
            $create.prop('disabled', true);
            $.ajax({
                type: 'POST',
                url: $root.data('create-url'),
                data: { _token: token }
            }).done(function (response) {
                if (response && response.success) {
                    $url.val(response.url);
                    var expiresText = (labels.validUntil ? labels.validUntil + ' ' : '') + (response.expires_at || '');
                    $expires.text(expiresText).removeClass('d-none text-bg-secondary').addClass('text-bg-success');
                    $copy.add($revoke).prop('disabled', false);
                    $create.html('<i class="bi bi-link-45deg me-1"></i>' + labels.refresh);
                }
            }).always(function () {
                $create.prop('disabled', false);
            });
        });

        $revoke.on('click', function () {
            if (!window.confirm(window.cabinetTaShareLabels && window.cabinetTaShareLabels.revokeConfirm
                ? window.cabinetTaShareLabels.revokeConfirm
                : '')) {
                return;
            }
            $revoke.prop('disabled', true);
            $.ajax({
                type: 'POST',
                url: $root.data('revoke-url'),
                data: { _token: token }
            }).done(function (response) {
                if (response && response.success) {
                    $url.val('');
                    $expires.text('').addClass('d-none').removeClass('text-bg-success').addClass('text-bg-secondary');
                    $copy.add($revoke).prop('disabled', true);
                    $create.html('<i class="bi bi-link-45deg me-1"></i>' + labels.create);
                }
            }).always(function () {
                $revoke.prop('disabled', false);
            });
        });
    }

    function parseExcludeLines(text) {
        return String(text || '')
            .replace(/\r\n/g, '\n')
            .split('\n')
            .map(function (line) {
                return line.trim();
            })
            .filter(Boolean);
    }

    function setExcludeWordsEnabled(on) {
        var input = document.getElementById('removeWords');
        var $panel = $('#cabinet-ta-list-words');
        var $textarea = $('#listWords');
        if (!input || !$panel.length || !$textarea.length) {
            return;
        }
        var wantOn = !!on;
        if (wantOn !== input.checked) {
            input.click();
        }
        $panel.toggleClass('d-none', !wantOn);
        $textarea.prop('required', wantOn);
    }

    function addWordToExcludeList(word) {
        word = String(word || '').trim();
        if (!word) {
            return false;
        }
        setExcludeWordsEnabled(true);

        var $textarea = $('#listWords');
        if (!$textarea.length) {
            return false;
        }
        var lines = parseExcludeLines($textarea.val());
        var lower = word.toLowerCase();
        var exists = lines.some(function (line) {
            return line.toLowerCase() === lower;
        });
        if (!exists) {
            lines.push(word);
            $textarea.val(lines.join('\n'));
        }
        return !exists;
    }

    function isTextAnalyzerPublicView() {
        var cfg = window.cabinetTextAnalyzerConfig || {};
        return !!cfg.isPublicView;
    }

    function handleExcludeListClick(btn) {
        var word = btn.getAttribute('data-word') || '';
        if (!word) {
            var termEl = btn.closest('td');
            if (termEl) {
                var label = termEl.querySelector('.cabinet-ta-exclude-term');
                word = label ? label.textContent : '';
            }
        }
        var added = addWordToExcludeList(String(word || '').trim());
        btn.classList.add(added ? 'cabinet-ta-add-exclude--added' : 'cabinet-ta-add-exclude--exists');
        window.setTimeout(function () {
            btn.classList.remove('cabinet-ta-add-exclude--added', 'cabinet-ta-add-exclude--exists');
        }, 1400);
    }

    function bindExcludeListClickEarly() {
        if (window._cabinetTaExcludeCaptureBound) {
            return;
        }
        window._cabinetTaExcludeCaptureBound = true;
        document.addEventListener('click', function (e) {
            if (isTextAnalyzerPublicView()) {
                return;
            }
            var target = e.target;
            if (!target || !target.closest) {
                return;
            }
            var btn = target.closest('.cabinet-ta-add-exclude');
            if (!btn || !btn.closest('.cabinet-text-analyzer-page')) {
                return;
            }
            e.preventDefault();
            e.stopPropagation();
            handleExcludeListClick(btn);
        }, true);
    }

    function syncUniquenessUi(forceOn) {
        var $sw = $('#switchCheckUniqueness');
        var on = forceOn === true ? true : $sw.is(':checked');
        if (forceOn === true && !$sw.is(':checked')) {
            $sw.prop('checked', true);
            on = true;
        }
        $('#cabinet-ta-uniqueness-panel').toggleClass('d-none', !on);
        scheduleCostEstimate();
    }

    var costEstimateTimer = null;

    function pluralLimit(n, $box) {
        var abs = Math.abs(n) % 100;
        var last = abs % 10;
        var one = ($box && $box.attr('data-unit-one')) || 'лимит';
        var few = ($box && $box.attr('data-unit-few')) || 'лимита';
        var many = ($box && $box.attr('data-unit-many')) || 'лимитов';
        if (abs > 10 && abs < 20) {
            return many;
        }
        if (last === 1) {
            return one;
        }
        if (last >= 2 && last <= 4) {
            return few;
        }
        return many;
    }

    function currentFormMode() {
        if ($('#cabinet-ta-mode-batch').hasClass('active')) {
            return 'batch';
        }
        if ($('#cabinet-ta-mode-url').hasClass('active')) {
            return 'url';
        }
        return 'text';
    }

    function scheduleCostEstimate() {
        if (costEstimateTimer) {
            window.clearTimeout(costEstimateTimer);
        }
        costEstimateTimer = window.setTimeout(updateCostEstimate, 280);
    }

    function updateCostEstimate() {
        var card = document.getElementById('cabinetTaFormCard');
        var $box = $('#cabinet-ta-cost-summary');
        if (!card || !$box.length) {
            return;
        }
        var estimateUrl = card.getAttribute('data-estimate-url');
        if (!estimateUrl) {
            return;
        }
        var mode = currentFormMode();
        var uniqOn = $('#switchCheckUniqueness').is(':checked');
        var eseninOn = $('#switchCheckEsenin').is(':checked');
        var compareOn = $('#switchCompareCompetitor').is(':checked');
        var batchCount = 0;
        if (mode === 'batch') {
            var max = parseInt(card.getAttribute('data-batch-max') || '20', 10) || 20;
            batchCount = parseBatchItems($('#cabinet-ta-batch-input').val(), max).length;
        }
        if (!uniqOn && !eseninOn && mode !== 'batch') {
            // всё равно показываем списание анализатора, если включены доп. проверки или пакет
        }
        var show = uniqOn || eseninOn || mode === 'batch' || compareOn;
        $box.toggleClass('d-none', !show && mode === 'text' && !uniqOn && !eseninOn);
        if (!show && !compareOn) {
            $box.addClass('d-none');
        }

        var text = '';
        if (mode === 'text') {
            text = (getAnalyzerTextForEstimate() || '').trim();
        }

        $.ajax({
            type: 'POST',
            url: estimateUrl,
            data: {
                _token: card.getAttribute('data-csrf'),
                type: mode === 'url' || mode === 'batch' ? 'url' : 'text',
                text: text,
                checkUniqueness: uniqOn ? 1 : 0,
                checkEsenin: eseninOn ? 1 : 0,
                compareCompetitor: compareOn ? 1 : 0,
                batch_count: batchCount,
            },
            dataType: 'json',
        }).done(function (data) {
            if (!data || !data.ok) {
                return;
            }
            var uniqCost = data.uniqueness || 0;
            var eseninCost = data.esenin || 0;
            var analyzerCost = data.analyzer || 0;
            $('#cabinet-ta-uniq-cost-value').text(
                uniqOn ? (String(uniqCost) + ' ' + pluralLimit(uniqCost, $box)) : '—'
            );
            $('#cabinet-ta-uniq-cost-hint').text(
                uniqOn
                    ? ((data.uniqueness_approx ? ($box.attr('data-approx') + '. ') : '') + ($box.attr('data-probe-hint') || ''))
                    : ''
            );
            if (eseninOn) {
                $('#cabinet-ta-esenin-cost-value').text(String(eseninCost) + ' ' + pluralLimit(eseninCost, $box));
            }
            var lines = [];
            lines.push($box.attr('data-label-analyzer') + ': <strong>' + analyzerCost + '</strong> ' + pluralLimit(analyzerCost, $box));
            if (uniqOn) {
                lines.push($box.attr('data-label-uniqueness') + ': <strong>' + uniqCost + '</strong> ' + pluralLimit(uniqCost, $box)
                    + (data.uniqueness_approx ? ' <span class="text-muted">(' + $box.attr('data-approx') + ')</span>' : ''));
            }
            if (eseninOn) {
                lines.push($box.attr('data-label-esenin') + ': <strong>' + eseninCost + '</strong> ' + pluralLimit(eseninCost, $box));
            }
            var totalNote = [];
            if (analyzerCost) {
                totalNote.push('анализ ' + analyzerCost);
            }
            if (uniqCost) {
                totalNote.push('уник. ' + uniqCost);
            }
            if (eseninCost) {
                totalNote.push('есенин ' + eseninCost);
            }
            $('#cabinet-ta-cost-summary-list').html(lines.map(function (l) {
                return '<li>' + l + '</li>';
            }).join(''));
            if (uniqOn || eseninOn || compareOn || mode === 'batch') {
                $box.removeClass('d-none');
            }
        });
    }

    function fillExcludeFromUrl() {
        var url = ($('#cabinet-ta-url').val() || '').trim();
        var $ex = $('#cabinet-ta-exclude-domain');
        if (!$ex.length) {
            return;
        }
        // В режиме URL подставляем полный адрес страницы для прямой сверки
        if (currentFormMode() === 'url' && url && !$ex.val()) {
            $ex.val(url.match(/^https?:\/\//i) ? url : ('https://' + url));
        }
    }

    function parseBatchItems(raw, max) {
        var text = String(raw || '').replace(/\r\n/g, '\n').trim();
        if (!text) {
            return [];
        }
        var blocks = text.indexOf('\n---\n') !== -1
            ? text.split(/\n---\n/)
            : text.split('\n');
        var items = [];
        blocks.forEach(function (block) {
            block = String(block || '').trim();
            if (!block) {
                return;
            }
            var firstLine = block.split('\n')[0].trim();
            var looksUrl = /^https?:\/\//i.test(firstLine) || (/^[a-z0-9.-]+\.[a-z]{2,}/i.test(firstLine) && block.indexOf(' ') === -1);
            if (looksUrl && block.indexOf('\n') === -1) {
                items.push({ type: 'url', url: firstLine, label: firstLine });
            } else if (looksUrl && block.split('\n').every(function (l) {
                l = l.trim();
                return !l || /^https?:\/\//i.test(l) || /^[a-z0-9.-]+\.[a-z]{2,}(\/|$)/i.test(l);
            })) {
                block.split('\n').forEach(function (line) {
                    line = line.trim();
                    if (line) {
                        items.push({ type: 'url', url: line, label: line });
                    }
                });
            } else {
                items.push({
                    type: 'text',
                    textarea: block,
                    label: block.slice(0, 80) + (block.length > 80 ? '…' : ''),
                });
            }
        });
        return items.slice(0, max || 20);
    }

    function runBatch() {
        var card = document.getElementById('cabinetTaFormCard');
        if (!card) {
            return;
        }
        var max = parseInt(card.getAttribute('data-batch-max') || '20', 10) || 20;
        var items = parseBatchItems($('#cabinet-ta-batch-input').val(), max);
        var $status = $('#cabinet-ta-form-status');
        var $prog = $('#cabinet-ta-batch-progress');
        var $progText = $('#cabinet-ta-batch-progress-text');
        var $wrap = $('#cabinet-ta-batch-results-wrap');
        var $tbody = $('#cabinet-ta-batch-results tbody');
        if (!items.length) {
            $status.text('Добавьте URL или тексты для пакетной проверки').addClass('text-danger');
            return;
        }
        syncUniquenessUi(true);
        $tbody.empty();
        $wrap.removeClass('d-none');
        $prog.removeClass('d-none');
        $('#cabinet-ta-progress').removeClass('d-none');
        $status.removeClass('text-danger').text('');
        $('#cabinet-ta-batch-run').prop('disabled', true);

        var idx = 0;
        function next() {
            if (idx >= items.length) {
                $progText.text('Готово: ' + items.length);
                $('#cabinet-ta-progress-title').text('Пакет готов');
                $('#cabinet-ta-progress-sub').text('Обработано: ' + items.length);
                $('#cabinet-ta-batch-run').prop('disabled', false);
                return;
            }
            var item = items[idx];
            $progText.text('Проверка ' + (idx + 1) + ' / ' + items.length + '…');
            $('#cabinet-ta-progress-title').text('Пакетная проверка…');
            $('#cabinet-ta-progress-sub').text('Элемент ' + (idx + 1) + ' из ' + items.length);
            var body = {
                _token: card.getAttribute('data-csrf'),
                type: item.type,
                url: item.url || '',
                textarea: item.textarea || '',
                label: item.label || '',
                checkUniqueness: $('#switchCheckUniqueness').is(':checked') ? 1 : 0,
                saveUniqueness: $('#cabinet-ta-save-uniqueness').is(':checked') ? 1 : 0,
                checkEsenin: $('#switchCheckEsenin').is(':checked') ? 1 : 0,
                excludeOwnDomain: $('#cabinet-ta-exclude-domain').val() || '',
                noIndex: $('#switchNoindex').is(':checked') ? 1 : 0,
                hiddenText: $('#switchAltAndTitle').is(':checked') ? 1 : 0,
                conjunctionsPrepositionsPronouns: $('#switchConjunctionsPrepositionsPronouns').is(':checked') ? 1 : 0,
                removeWords: $('#removeWords').is(':checked') ? 1 : 0,
                listWords: $('#listWords').val() || '',
            };
            $.ajax({
                type: 'POST',
                url: card.getAttribute('data-batch-url'),
                data: body,
                dataType: 'json',
            }).done(function (data) {
                var tr = $('<tr></tr>');
                tr.append($('<td></td>').text((data && data.label) || item.label || '—'));
                var g = (data && data.general) || {};
                tr.append($('<td></td>').text(g.countWordsAll != null ? g.countWordsAll : '—'));
                tr.append($('<td></td>').text(g.countStopWords != null ? g.countStopWords : '—'));
                var pct = data && data.uniqueness_pct != null ? data.uniqueness_pct + '%' : (data && data.message) || '—';
                tr.append($('<td></td>').text(pct));
                var $btn = $('<button type="button" class="btn btn-xs btn-outline-primary">Детали</button>');
                if (data && data.uniqueness && !data.uniqueness.error) {
                    $btn.on('click', function () {
                        renderUniquenessPanel(data.uniqueness);
                        var el = document.getElementById('cabinet-ta-uniq-history-panel');
                        if (el) {
                            el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        }
                    });
                } else {
                    $btn.prop('disabled', true);
                }
                tr.append($('<td></td>').append($btn));
                $tbody.append(tr);
            }).fail(function (xhr) {
                var msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Ошибка';
                var tr = $('<tr></tr>');
                tr.append($('<td></td>').text(item.label || '—'));
                tr.append($('<td colspan="3"></td>').text(msg));
                tr.append($('<td></td>'));
                $tbody.append(tr);
            }).always(function () {
                idx += 1;
                next();
            });
        }
        next();
    }

    function renderUniquenessPanel(u) {
        var $panel = $('#cabinet-ta-uniq-history-panel');
        if (!$panel.length || !u) {
            return;
        }
        $panel.removeClass('d-none').empty();
        var pct = u.uniqueness_pct != null ? u.uniqueness_pct + '%' : '—';
        var html = '<div class="card shadow-sm mb-3 cabinet-ta-uniqueness cabinet-ta-esenin-like"><div class="card-header py-2"><h3 class="card-title h6 mb-0">Уникальность: '
            + pct
            + '</h3></div><div class="card-body">';
        if (u.error) {
            html += '<div class="alert alert-warning mb-0">' + (u.message || 'Ошибка') + '</div>';
        } else {
            html += '<div class="row g-3">';
            html += '<div class="col-lg-8"><div class="cabinet-esenin-text-view card shadow-sm mb-0"><div class="card-body">';
            html += '<div class="cabinet-esenin-legend small text-secondary mb-3">Цветом отмечены неуникальные фрагменты.</div>';
            html += '<div class="cabinet-esenin-text-view__content cabinet-esenin-text-view__content--readonly">';
            if (u.highlighted_html) {
                html += u.highlighted_html;
            } else if (u.text) {
                html += escapeHtml(u.text).replace(/\n/g, '<br>');
            } else {
                html += '<span class="text-muted">Текст проверки не сохранён в этой записи. Перепроверьте текст, чтобы увидеть подсветку.</span>';
            }
            html += '</div></div></div></div>';
            html += '<div class="col-lg-4"><h6 class="fw-semibold mb-2">Источники</h6>';
            html += '<ul class="list-unstyled mb-0">';
            (u.sources || []).forEach(function (s) {
                html += '<li class="mb-2 pb-2 border-bottom"><div class="small fw-semibold">'
                    + (s.overlap_pct != null ? s.overlap_pct : 0) + '%</div>';
                if (s.url) {
                    html += '<a class="small text-break" href="' + s.url + '" target="_blank" rel="noopener">' + s.url + '</a>';
                }
                html += '</li>';
            });
            html += '</ul></div></div>';
        }
        html += '</div></div>';
        $panel.html(html);
        initMarkTips($panel.get(0));
    }

    function escapeHtml(str) {
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function initUniquenessHistory() {
        var card = document.querySelector('[data-cabinet-saved-checks]') || document.getElementById('cabinetTaFormCard');
        if (!card) {
            return;
        }
        var base = card.getAttribute('data-history-url');
        var csrf = card.getAttribute('data-csrf');
        if (!base) {
            return;
        }
        $(document).on('click', '.cabinet-ta-uniq-history-open', function () {
            var id = $(this).closest('tr').attr('data-id');
            if (!id) {
                return;
            }
            // Сначала пробуем полный снимок анализатора (слова / Zipf / галки).
            window.location.href = base + '/' + id + '/open';
        });
        $(document).on('click', '.cabinet-ta-uniq-history-del', function () {
            var $tr = $(this).closest('tr');
            var id = $tr.attr('data-id');
            if (!id || !window.confirm('Удалить сохранённую проверку?')) {
                return;
            }
            $.ajax({
                type: 'DELETE',
                url: base + '/' + id,
                headers: { 'X-CSRF-TOKEN': csrf },
            }).done(function (resp) {
                $tr.remove();
                var $body = $('[data-cabinet-saved-checks-body]');
                if ($body.length && !$body.find('tr[data-id]').length) {
                    $body.html('<tr data-cabinet-saved-checks-empty><td colspan="7" class="text-secondary text-center py-3">Пока нет сохранённых проверок.</td></tr>');
                }
                var $count = $('[data-cabinet-saved-checks-count]');
                if ($count.length && resp && resp.saved_count != null) {
                    var limitTxt = $count.text().split('/')[1];
                    $count.text(resp.saved_count + (limitTxt ? ' /' + limitTxt : ''));
                }
            });
        });
    }

    function initForm(cfg) {
        if (cfg.isPublicView) {
            return;
        }

        function setMode(mode) {
            var isBatch = mode === 'batch';
            $('#cabinet-ta-type').val(isBatch ? 'url' : mode);
            $('#cabinet-ta-mode-text').toggleClass('active', mode === 'text');
            $('#cabinet-ta-mode-url').toggleClass('active', mode === 'url');
            $('#cabinet-ta-mode-batch').toggleClass('active', isBatch);
            $('#cabinet-ta-panel-text').toggleClass('d-none', mode !== 'text');
            $('#cabinet-ta-panel-url').toggleClass('d-none', mode !== 'url');
            $('#cabinet-ta-panel-batch').toggleClass('d-none', !isBatch);
            $('#cabinet-ta-submit').toggleClass('d-none', isBatch);
            $('#cabinet-ta-batch-run').toggleClass('d-none', !isBatch);
            $('#switchCompareCompetitor').closest('.cabinet-ta-switch-row').toggleClass('d-none', isBatch);
            $('#cabinet-ta-competitor-url').toggleClass('d-none', isBatch || !$('#switchCompareCompetitor').is(':checked'));
            if (isBatch) {
                syncUniquenessUi(true);
            }
            scheduleCostEstimate();
        }

        $('#cabinet-ta-mode-text').on('click', function () { setMode('text'); });
        $('#cabinet-ta-mode-url').on('click', function () { setMode('url'); });
        $('#cabinet-ta-mode-batch').on('click', function () { setMode('batch'); });

        $('#removeWords').on('change', function () {
            setExcludeWordsEnabled($(this).is(':checked'));
        });

        $('#switchCompareCompetitor').on('change', function () {
            var on = $(this).is(':checked');
            $('#cabinet-ta-competitor-url').toggleClass('d-none', !on);
            $('#cabinet-ta-competitor-url-input').prop('required', on);
            scheduleCostEstimate();
        });

        $('#switchCheckUniqueness').on('change', function () {
            syncUniquenessUi();
        });
        $('#switchCheckEsenin').on('change', function () {
            $('#cabinet-ta-esenin-panel').toggleClass('d-none', !$(this).is(':checked'));
            scheduleCostEstimate();
        });
        $('#cabinet-ta-url').on('change blur', fillExcludeFromUrl);
        $('#cabinet-ta-textarea').on('input', scheduleCostEstimate);
        $('#cabinet-ta-batch-input').on('input', scheduleCostEstimate);
        $('#cabinet-ta-batch-run').on('click', runBatch);
        $('#cabinet-ta-form').on('submit', function () {
            if ($('#switchCheckUniqueness').is(':checked')) {
                fillExcludeFromUrl();
            }
            var $prog = $('#cabinet-ta-progress');
            var $btn = $('#cabinet-ta-submit');
            var uniqOn = $('#switchCheckUniqueness').is(':checked');
            var eseninOn = $('#switchCheckEsenin').is(':checked');
            var title = 'Анализ текста…';
            var sub = 'Собираем статистику, подождите';
            if (uniqOn && eseninOn) {
                title = 'Анализ + уникальность + Есенин…';
                sub = 'Это может занять минуту и больше';
            } else if (uniqOn) {
                title = 'Анализ и проверка уникальности…';
                sub = 'Ищем совпадения фрагментов, подождите';
            } else if (eseninOn) {
                title = 'Анализ и проверка Есенин…';
                sub = 'Считаем риск и метрики, подождите';
            }
            $('#cabinet-ta-progress-title').text(title);
            $('#cabinet-ta-progress-sub').text(sub);
            $prog.removeClass('d-none');
            $btn.prop('disabled', true).addClass('disabled');
            $('#cabinet-ta-form-status').removeClass('text-danger').text('Идёт проверка…');
            if ($('#cabinet-ta-progress-bar').length) {
                var w = 28;
                window.clearInterval(window._cabinetTaProgressTimer);
                window._cabinetTaProgressTimer = window.setInterval(function () {
                    w = Math.min(92, w + Math.random() * 4);
                    $('#cabinet-ta-progress-bar').css('width', w + '%').attr('aria-valuenow', String(Math.round(w)));
                }, 700);
            }
        });

        syncUniquenessUi();
        scheduleCostEstimate();
        initUniquenessHistory();
        initVisualTextEditor();
        initCombinedUniqEseninPanel();
        initMarkTips(document.querySelector('.cabinet-text-analyzer-page'));

        if (cfg.initialUrl && !cfg.hasResponse) {
            setMode('url');
            window.setTimeout(function () {
                $('#cabinet-ta-submit').trigger('click');
            }, 600);
        }
    }

    function initVisualTextEditor() {
        var ta = document.getElementById('cabinet-ta-textarea');
        if (!ta) {
            return;
        }

        function syncEstimate() {
            scheduleCostEstimate();
        }

        if (typeof window.jQuery === 'undefined' || !window.jQuery.fn.ckeditor || typeof window.CKEDITOR === 'undefined') {
            $(ta).on('input', syncEstimate);
            return;
        }

        window.jQuery(ta).ckeditor({
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

        var editor = window.CKEDITOR.instances['cabinet-ta-textarea'];
        if (!editor) {
            $(ta).on('input', syncEstimate);
            return;
        }

        editor.on('change', syncEstimate);
        editor.on('paste', function () {
            setTimeout(syncEstimate, 0);
        });

        $('#cabinet-ta-form').on('submit', function () {
            if (editor && editor.updateElement) {
                editor.updateElement();
            }
        });

        window.cabinetTaCkEditor = editor;
    }

    function getAnalyzerTextForEstimate() {
        if (window.cabinetTaCkEditor && window.cabinetTaCkEditor.getData) {
            return window.cabinetTaCkEditor.getData() || '';
        }
        return ($('#cabinet-ta-textarea').val() || '');
    }

    function initCombinedUniqEseninPanel() {
        var root = document.querySelector('[data-cabinet-ta-combined]');
        if (!root) {
            return;
        }

        var uniqData = parseJsonScript('cabinet-ta-uniq-highlight');
        var eseninHighlights = parseJsonScript('cabinet-ta-esenin-highlights') || {};
        var eseninMeta = parseJsonScript('cabinet-ta-esenin-meta') || {};
        var highlightEl = root.querySelector('[data-combined-highlight]');
        var legendEl = root.querySelector('[data-combined-legend]');
        var footerEl = root.querySelector('[data-combined-footer]');
        var titleEl = root.querySelector('[data-combined-side-title]');
        var sideUniq = root.querySelector('[data-combined-side="uniqueness"]');
        var sideEsenin = root.querySelector('[data-combined-side="esenin"]');
        var buttons = root.querySelectorAll('[data-combined-tab]');
        var dirty = false;

        function plainFromEditable() {
            if (!highlightEl) {
                return '';
            }
            var clone = highlightEl.cloneNode(true);
            clone.querySelectorAll('mark').forEach(function (mark) {
                var icon = mark.querySelector('.esenin-mark__icon');
                if (icon) {
                    icon.remove();
                }
                mark.replaceWith(document.createTextNode(mark.textContent || ''));
            });
            var blocks = [];
            Array.prototype.forEach.call(clone.childNodes, function (node) {
                if (node.nodeType === 3) {
                    var t = (node.textContent || '').replace(/\u00a0/g, ' ').trim();
                    if (t) {
                        blocks.push(t);
                    }
                    return;
                }
                if (node.nodeType !== 1) {
                    return;
                }
                var tag = (node.tagName || '').toLowerCase();
                if (tag === 'br') {
                    return;
                }
                var inner = node.cloneNode(true);
                inner.querySelectorAll('br').forEach(function (br) {
                    br.replaceWith(document.createTextNode(' '));
                });
                var txt = (inner.innerText || inner.textContent || '').replace(/\u00a0/g, ' ').replace(/\n+/g, ' ').trim();
                if (txt) {
                    blocks.push(txt);
                }
            });
            if (blocks.length) {
                return blocks.join('\n\n').trim();
            }
            return (clone.innerText || clone.textContent || '').replace(/\u00a0/g, ' ').trim();
        }

        function syncTextarea() {
            if (!highlightEl) {
                return;
            }
            // Сохраняем HTML из редактора результатов (с возможными правками)
            var html = highlightEl.innerHTML || '';
            // убрать иконки ! из mark перед сохранением в форму
            var tmp = document.createElement('div');
            tmp.innerHTML = html;
            tmp.querySelectorAll('.esenin-mark__icon').forEach(function (el) {
                el.remove();
            });
            // снять подсветку mark, оставить содержимое + структуру
            tmp.querySelectorAll('mark').forEach(function (mark) {
                var frag = document.createDocumentFragment();
                while (mark.firstChild) {
                    frag.appendChild(mark.firstChild);
                }
                mark.replaceWith(frag);
            });
            var cleanHtml = tmp.innerHTML || '';
            var $ta = $('#cabinet-ta-textarea');
            if ($ta.length) {
                $ta.val(cleanHtml);
            }
            if (window.cabinetTaCkEditor && window.cabinetTaCkEditor.setData) {
                window.cabinetTaCkEditor.setData(cleanHtml);
            }
        }

        function setTab(tab) {
            buttons.forEach(function (b) {
                var on = b.getAttribute('data-combined-tab') === tab;
                b.classList.toggle('active', on);
                b.setAttribute('aria-pressed', on ? 'true' : 'false');
            });

            var isUniq = tab === 'uniqueness';
            if (sideUniq) {
                sideUniq.classList.toggle('d-none', !isUniq);
            }
            if (sideEsenin) {
                sideEsenin.classList.toggle('d-none', isUniq);
            }

            if (titleEl) {
                if (isUniq) {
                    titleEl.textContent = 'Уникальность';
                } else {
                    var activeBtn = root.querySelector('[data-combined-tab="' + tab + '"] .cabinet-esenin-score-btn__title');
                    titleEl.textContent = activeBtn ? activeBtn.textContent : tab;
                }
            }

            if (dirty) {
                // после правок не перетираем текст подсветкой другого блока
                if (legendEl) {
                    legendEl.textContent = isUniq
                        ? (uniqData.legend || '')
                        : (eseninMeta.legend || '');
                }
                if (footerEl) {
                    footerEl.textContent = isUniq
                        ? (uniqData.footer || '')
                        : (eseninMeta.stats_footer || '');
                }
                return;
            }

            if (!highlightEl) {
                return;
            }

            if (isUniq) {
                highlightEl.innerHTML = (uniqData && uniqData.html) || highlightEl.innerHTML;
                if (legendEl) {
                    legendEl.textContent = (uniqData && uniqData.legend) || '';
                }
                if (footerEl) {
                    footerEl.textContent = (uniqData && uniqData.footer) || '';
                }
            } else {
                highlightEl.innerHTML = eseninHighlights[tab]
                    || eseninHighlights.risk
                    || eseninMeta.fallback
                    || highlightEl.innerHTML;
                if (legendEl) {
                    legendEl.textContent = eseninMeta.legend || '';
                }
                if (footerEl) {
                    footerEl.textContent = eseninMeta.stats_footer || '';
                }
            }
            initMarkTips(highlightEl);
        }

        function findEseninMarkFromNode(node) {
            while (node && node !== highlightEl) {
                if (node.nodeType === 1 && node.classList && node.classList.contains('esenin-mark')) {
                    return node;
                }
                node = node.parentNode;
            }
            return null;
        }

        function unwrapMarkElement(mark) {
            if (!mark || !mark.parentNode) {
                return null;
            }
            var icon = mark.querySelector('.esenin-mark__icon');
            if (icon) {
                icon.remove();
            }
            var parent = mark.parentNode;
            var last = null;
            while (mark.firstChild) {
                last = mark.firstChild;
                parent.insertBefore(last, mark);
            }
            parent.removeChild(mark);
            return last;
        }

        function getTextOffsetInHighlight(root, targetNode, targetOffset) {
            var walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
                acceptNode: function (node) {
                    if (node.parentElement && node.parentElement.classList.contains('esenin-mark__icon')) {
                        return NodeFilter.FILTER_REJECT;
                    }
                    return NodeFilter.FILTER_ACCEPT;
                }
            });
            var total = 0;
            var node;
            while ((node = walker.nextNode())) {
                if (node === targetNode) {
                    return total + targetOffset;
                }
                total += (node.nodeValue || '').length;
            }
            return total;
        }

        function setTextOffsetInHighlight(root, offset) {
            var walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, null);
            var total = 0;
            var node;
            while ((node = walker.nextNode())) {
                if (node.parentElement && node.parentElement.classList.contains('esenin-mark__icon')) {
                    continue;
                }
                var len = (node.nodeValue || '').length;
                if (total + len >= offset) {
                    var range = document.createRange();
                    range.setStart(node, Math.max(0, offset - total));
                    range.collapse(true);
                    var sel = window.getSelection();
                    sel.removeAllRanges();
                    sel.addRange(range);
                    return;
                }
                total += len;
            }
            var endRange = document.createRange();
            endRange.selectNodeContents(root);
            endRange.collapse(false);
            var endSel = window.getSelection();
            endSel.removeAllRanges();
            endSel.addRange(endRange);
        }

        function hideMarkTip() {
            var tipEl = document.getElementById('cabinet-ta-mark-tip');
            if (tipEl) {
                tipEl.classList.remove('is-visible');
            }
        }

        function unwrapMarksTouchingSelection() {
            if (!highlightEl) {
                return false;
            }
            var sel = window.getSelection();
            if (!sel || sel.rangeCount === 0) {
                return false;
            }
            var range = sel.getRangeAt(0);
            var startNode = range.startContainer;
            if (startNode.nodeType === 3) {
                /* ok */
            } else if (!highlightEl.contains(startNode) && startNode !== highlightEl) {
                return false;
            }

            var marks = [];
            var seen = {};
            function addMark(mark) {
                if (!mark || seen[mark]) {
                    return;
                }
                seen[mark] = true;
                marks.push(mark);
            }

            addMark(findEseninMarkFromNode(range.startContainer));
            addMark(findEseninMarkFromNode(range.endContainer));

            if (!range.collapsed) {
                highlightEl.querySelectorAll('mark.esenin-mark').forEach(function (mark) {
                    try {
                        if (range.intersectsNode(mark)) {
                            addMark(mark);
                        }
                    } catch (e) {
                        /* ignore */
                    }
                });
            }

            if (range.collapsed && marks.length === 0) {
                var container = range.startContainer;
                var offset = range.startOffset;
                if (container.nodeType === 1) {
                    var before = container.childNodes[offset - 1];
                    var after = container.childNodes[offset];
                    if (before && before.nodeType === 1 && before.classList && before.classList.contains('esenin-mark')) {
                        addMark(before);
                    }
                    if (after && after.nodeType === 1 && after.classList && after.classList.contains('esenin-mark')) {
                        addMark(after);
                    }
                }
            }

            if (!marks.length) {
                return false;
            }

            var caretOffset = getTextOffsetInHighlight(highlightEl, range.startContainer, range.startOffset);
            marks.forEach(unwrapMarkElement);
            highlightEl.querySelectorAll('.esenin-mark__icon').forEach(function (icon) {
                if (!icon.closest('mark.esenin-mark')) {
                    icon.remove();
                }
            });
            setTextOffsetInHighlight(highlightEl, caretOffset);
            hideMarkTip();
            return true;
        }

        function shouldUnwrapMarksForKey(event) {
            if (!event) {
                return false;
            }
            if (event.type === 'beforeinput') {
                var inputType = event.inputType || '';
                if (inputType.indexOf('history') === 0) {
                    return false;
                }
                return true;
            }
            if (event.type !== 'keydown') {
                return false;
            }
            var key = event.key || '';
            if (!key || key === 'Shift' || key === 'Control' || key === 'Alt' || key === 'Meta'
                || key === 'CapsLock' || key === 'Escape' || key === 'Tab'
                || key.indexOf('Arrow') === 0 || key === 'Home' || key === 'End'
                || key === 'PageUp' || key === 'PageDown') {
                return false;
            }
            if ((event.ctrlKey || event.metaKey) && !event.altKey) {
                var lower = key.toLowerCase();
                return lower === 'v' || lower === 'x' || lower === 'backspace';
            }
            return true;
        }

        function prepareHighlightEdit(event) {
            if (!shouldUnwrapMarksForKey(event)) {
                return;
            }
            unwrapMarksTouchingSelection();
        }

        function hardenHighlightMarkIcons(container) {
            if (!container) {
                return;
            }
            container.querySelectorAll('.esenin-mark__icon').forEach(function (icon) {
                icon.setAttribute('contenteditable', 'false');
                icon.setAttribute('aria-hidden', 'true');
            });
        }

        buttons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                setTab(btn.getAttribute('data-combined-tab') || 'uniqueness');
            });
        });

        if (highlightEl) {
            highlightEl.addEventListener('beforeinput', prepareHighlightEdit);
            highlightEl.addEventListener('keydown', prepareHighlightEdit, true);
            highlightEl.addEventListener('paste', function () {
                unwrapMarksTouchingSelection();
            }, true);
            highlightEl.addEventListener('input', function () {
                dirty = true;
                syncTextarea();
            });
            highlightEl.addEventListener('blur', syncTextarea);
            highlightEl.addEventListener('focus', function () {
                setTimeout(function () {
                    hardenHighlightMarkIcons(highlightEl);
                    initMarkTips(highlightEl);
                }, 0);
            });
        }

        initMarkTips(highlightEl || root);
    }

    function parseJsonScript(id) {
        var node = document.getElementById(id);
        if (!node) {
            return null;
        }
        try {
            return JSON.parse(node.textContent || 'null');
        } catch (e) {
            return null;
        }
    }

    function initUniquenessResultsPanel() {
        // legacy no-op: combined panel replaces separate uniq/esenin grids
    }

    function initEseninResultsPanel() {
        // legacy no-op
    }

    function initMarkTips(scope) {
        var container = scope || document;
        if (!container || !container.querySelectorAll) {
            return;
        }
        var tipEl = document.getElementById('cabinet-ta-mark-tip');
        if (!tipEl) {
            tipEl = document.createElement('div');
            tipEl.id = 'cabinet-ta-mark-tip';
            tipEl.className = 'esenin-tip-popover';
            document.body.appendChild(tipEl);
        }
        function hideTip() {
            tipEl.classList.remove('is-visible');
        }
        function showTip(mark, text) {
            if (!text) {
                hideTip();
                return;
            }
            tipEl.textContent = text;
            tipEl.classList.add('is-visible');
            var rect = mark.getBoundingClientRect();
            var top = window.scrollY + rect.top - tipEl.offsetHeight - 8;
            var left = window.scrollX + rect.left;
            tipEl.style.top = Math.max(8, top) + 'px';
            tipEl.style.left = Math.max(8, left) + 'px';
        }
        container.querySelectorAll('[data-esenin-tip]').forEach(function (mark) {
            if (mark.getAttribute('data-ta-tip-bound') === '1') {
                return;
            }
            mark.setAttribute('data-ta-tip-bound', '1');
            mark.addEventListener('mouseenter', function () {
                showTip(mark, mark.getAttribute('data-esenin-tip') || '');
            });
            mark.addEventListener('mouseleave', hideTip);
            mark.addEventListener('click', function (ev) {
                var url = mark.getAttribute('data-uniq-url');
                if (url) {
                    ev.preventDefault();
                    window.open(url, '_blank', 'noopener,noreferrer');
                }
            });
        });
    }

    bindExcludeListClickEarly();

    $(function () {
        releaseUiLock();
        var cfg = window.cabinetTextAnalyzerConfig || {};
        initForm(cfg);
        initPublicShare();
        if (cfg.hasResponse) {
            try {
                initResults(cfg);
            } catch (e) {
                console.error('cabinet-text-analyzer', e);
                releaseUiLock();
            }
        }
    });

    window.addEventListener('pagehide', releaseUiLock);
    window.addEventListener('pageshow', releaseUiLock);

})(jQuery, window, document);
