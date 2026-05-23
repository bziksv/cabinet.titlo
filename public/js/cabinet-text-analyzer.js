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

    function initResults(cfg) {
        releaseUiLock();
        startUiLockWatchdog();

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
        clouds = clouds.map(function (item) {
            item.renderer = new CloudRenderer({
                hostSelector: item.hostSelector,
                getData: item.getData,
                wordLimit: cfg.cloudWordLimit || 80,
                emptyLabel: cfg.emptyLabel || '',
                repetitionsLabel: cfg.repetitionsLabel || ''
            });
            return item;
        });

        try {
            initAllClouds(clouds);
            initCloudTooltips();
        } catch (e) {
            console.error('cabinet-text-analyzer: cloud', e);
        }

        if (cfg.scrollToResults) {
            var $results = $('.cabinet-ta-results');
            if ($results.length) {
                window.requestAnimationFrame(function () {
                    var top = $results.offset().top - 80;
                    window.scrollTo(0, top > 0 ? top : 0);
                });
            }
        }

        releaseUiLock();
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

    function initForm(cfg) {
        if (cfg.isPublicView) {
            return;
        }

        function setMode(mode) {
            $('#cabinet-ta-type').val(mode);
            $('#cabinet-ta-mode-text').toggleClass('active', mode === 'text');
            $('#cabinet-ta-mode-url').toggleClass('active', mode === 'url');
            $('#cabinet-ta-panel-text').toggleClass('d-none', mode !== 'text');
            $('#cabinet-ta-panel-url').toggleClass('d-none', mode !== 'url');
        }

        $('#cabinet-ta-mode-text').on('click', function () { setMode('text'); });
        $('#cabinet-ta-mode-url').on('click', function () { setMode('url'); });

        $('#removeWords').on('change', function () {
            var on = $(this).is(':checked');
            $('#cabinet-ta-list-words').toggleClass('d-none', !on);
            $('#listWords').prop('required', on);
        });

        $('#switchCompareCompetitor').on('change', function () {
            var on = $(this).is(':checked');
            $('#cabinet-ta-competitor-url').toggleClass('d-none', !on);
            $('#cabinet-ta-competitor-url-input').prop('required', on);
        });

        if (cfg.initialUrl && !cfg.hasResponse) {
            setMode('url');
            window.setTimeout(function () {
                $('#cabinet-ta-submit').trigger('click');
            }, 600);
        }
    }

    $(function () {
        releaseUiLock();
        startUiLockWatchdog();
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
