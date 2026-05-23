/**
 * Анализ текста — результаты (DataTables, jQCloud, Chart.js).
 * Конфиг: window.cabinetTextAnalyzerConfig (из Blade).
 */
(function ($, window, document) {
    'use strict';

    function releaseUiLock() {
        if (typeof window.cabinetReleaseUiLock === 'function') {
            window.cabinetReleaseUiLock();
        }
        $('.cabinet-text-analyzer-page .dataTables_processing').remove();
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
        return words.slice()
            .sort(function (a, b) {
                return (b.weight || 0) - (a.weight || 0);
            })
            .slice(0, limit)
            .map(function (word) {
                var count = word.weight || 1;
                return {
                    text: String(word.text),
                    weight: count,
                    html: {
                        title: String(word.text) + ' — ' + repetitionsLabel + ': ' + count
                    }
                };
            });
    }

    function CloudRenderer(cfg) {
        this.hostSelector = cfg.hostSelector;
        this.getData = cfg.getData;
        this.wordLimit = cfg.wordLimit || 80;
        this.emptyLabel = cfg.emptyLabel || '';
        this.repetitionsLabel = cfg.repetitionsLabel || '';
        this.token = 0;
        this.resizeObserver = null;
    }

    CloudRenderer.prototype.destroy = function () {
        if (this.resizeObserver) {
            this.resizeObserver.disconnect();
            this.resizeObserver = null;
        }
    };

    CloudRenderer.prototype.paint = function () {
        var self = this;
        if (typeof $.fn.jQCloud !== 'function') {
            return false;
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
        var renderToken = ++this.token;
        var $pane = $host.closest('.tab-pane');

        if (!words.length) {
            $host.empty().removeClass('jqcloud');
            $host.append($('<p class="text-secondary small mb-0 text-center py-5"></p>').text(this.emptyLabel));
            return true;
        }

        function run(attempt) {
            if (renderToken !== self.token) {
                return;
            }
            if ($pane.length && !$pane.hasClass('active') && !$pane.hasClass('show')) {
                return;
            }
            var width = Math.floor($host.innerWidth());
            var height = Math.floor($host.innerHeight());
            if ((width < 40 || height < 40) && attempt < 40) {
                window.setTimeout(function () {
                    run(attempt + 1);
                }, 75);
                return;
            }
            if (width < 40 || height < 40) {
                width = 640;
                height = 350;
            }
            $host.empty().removeClass('jqcloud');
            try {
                $host.jQCloud(words, {
                    width: width,
                    height: height,
                    removeOverflowing: false,
                    delayedMode: false,
                    shape: 'rectangular'
                });
            } catch (e) {
                console.error('cabinet-text-analyzer: jQCloud', e);
            }
        }

        run(0);
        return true;
    };

    CloudRenderer.prototype.bindResize = function () {
        var self = this;
        var node = document.querySelector(this.hostSelector);
        if (!node || typeof ResizeObserver === 'undefined') {
            return;
        }
        if (this.resizeObserver) {
            this.resizeObserver.disconnect();
        }
        this.resizeObserver = new ResizeObserver(function () {
            if (node.closest('.tab-pane.active, .tab-pane.show')) {
                self.paint();
            }
        });
        this.resizeObserver.observe(node);
    };

    function ZipfChart(cfg) {
        this.canvasId = cfg.canvasId;
        this.graph = cfg.graph || [];
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
        var baseY = graph[0].y;
        var actualLabel = this.labels.actual || 'Actual';
        var idealLabel = this.labels.ideal || 'Ideal';
        var rankLabel = this.labels.rank || 'Rank';
        var self = this;

        this.instance = new Chart(canvas.getContext('2d'), {
            type: 'line',
            data: {
                datasets: [
                    {
                        label: actualLabel,
                        data: graph.map(function (point) {
                            return {x: point.x, y: point.y};
                        }),
                        borderColor: '#627d98',
                        backgroundColor: 'rgba(98, 125, 152, 0.15)',
                        pointBackgroundColor: '#627d98',
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        tension: 0.15,
                        fill: false
                    },
                    {
                        label: idealLabel,
                        data: this.buildIdeal(baseY, graph.length),
                        borderColor: '#5a8fa8',
                        backgroundColor: 'rgba(90, 143, 168, 0.08)',
                        pointBackgroundColor: '#5a8fa8',
                        pointRadius: 3,
                        pointHoverRadius: 5,
                        tension: 0.15,
                        fill: false
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: {duration: 300},
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
                                if (item.datasetIndex !== 0) {
                                    return idealLabel;
                                }
                                var point = graph[item.dataIndex];
                                return point && point.label ? point.label : '#' + item.parsed.x;
                            },
                            label: function (ctx) {
                                var name = ctx.datasetIndex === 0 ? actualLabel : idealLabel;
                                return name + ': ' + ctx.parsed.y;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        type: 'linear',
                        min: 1,
                        max: graph.length,
                        title: {display: true, text: rankLabel},
                        grid: {color: 'rgba(0, 0, 0, 0.06)'},
                        ticks: {
                            stepSize: 1,
                            autoSkip: false,
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

    function collapseWordRows(table) {
        if (!table) {
            return;
        }
        table.rows().every(function () {
            if (this.child.isShown()) {
                this.child.hide();
            }
        });
        $('#totalTable tbody tr.cabinet-ta-word-row').removeClass('is-expanded');
        $('#totalTable .cabinet-ta-word-toggle').attr('aria-expanded', 'false')
            .find('.cabinet-ta-word-toggle__icon')
            .removeClass('bi-chevron-up').addClass('bi-chevron-down');
    }

    function initDataTables(cfg) {
        if (!$.fn.DataTable) {
            return null;
        }
        var dtDom = '<"row align-items-center g-2 cabinet-ta-dt-top px-2 pt-2"<"col-sm-auto"l><"col-sm ms-sm-auto"f>>rt<"row align-items-center g-2 cabinet-ta-dt-bottom px-2 pb-2"<"col-sm-auto"i><"col-sm"p>>';
        var dtLang = cfg.dtLang || {};
        var base = {
            dom: dtDom,
            pageLength: 25,
            lengthMenu: [25, 50, 100, 250],
            pagingType: 'simple_numbers',
            autoWidth: false,
            processing: false,
            responsive: false,
            language: dtLang
        };

        ['#totalTable', '#phrasesTable'].forEach(function (sel) {
            if ($.fn.DataTable.isDataTable(sel)) {
                $(sel).DataTable().destroy();
            }
        });

        var totalTable = $('#totalTable').DataTable($.extend({}, base, {
            order: [[2, 'desc']],
            drawCallback: function () {
                collapseWordRows(totalTable);
            }
        }));

        totalTable.on('order.dt page.dt search.dt', function () {
            collapseWordRows(totalTable);
        });

        $('#phrasesTable').DataTable($.extend({}, base, {
            order: [[1, 'desc']]
        }));

        $('#totalTable').on('click', '.cabinet-ta-word-toggle', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var $btn = $(this);
            var $row = $btn.closest('tr.cabinet-ta-word-row');
            var wordId = $row.attr('data-cabinet-ta-word-id');
            var dtRow = totalTable.row($row);
            var panelHtml = (cfg.wordForms || {})[wordId] || '';
            if (!dtRow.length || !panelHtml) {
                return;
            }
            if (dtRow.child.isShown()) {
                dtRow.child.hide();
                $row.removeClass('is-expanded');
                $btn.find('.cabinet-ta-word-toggle__icon').removeClass('bi-chevron-up').addClass('bi-chevron-down');
                $btn.attr('aria-expanded', 'false');
                return;
            }
            collapseWordRows(totalTable);
            dtRow.child('<div class="cabinet-ta-word-detail__cell">' + panelHtml + '</div>').show();
            $row.addClass('is-expanded');
            $btn.find('.cabinet-ta-word-toggle__icon').removeClass('bi-chevron-down').addClass('bi-chevron-up');
            $btn.attr('aria-expanded', 'true');
        });

        return totalTable;
    }

    function initCloudTabs(clouds, activeSelector) {
        var map = {};
        clouds.forEach(function (cloud) {
            map[cloud.tabSelector] = cloud;
        });

        function render(selector) {
            var item = map[selector];
            if (item && item.renderer) {
                item.renderer.paint();
            }
        }

        document.querySelectorAll('#cabinet-ta-cloud-tabs [data-bs-toggle="tab"]').forEach(function (tab) {
            tab.addEventListener('shown.bs.tab', function (event) {
                window.setTimeout(function () {
                    render(event.target.getAttribute('data-bs-target'));
                }, 30);
            });
        });

        window.setTimeout(function () {
            render(activeSelector || '#cabinet-ta-cloud-text');
        }, 100);

        if (typeof IntersectionObserver !== 'undefined') {
            var host = document.querySelector('#cabinet-ta-cloud-text-host');
            if (host) {
                var obs = new IntersectionObserver(function (entries) {
                    entries.forEach(function (entry) {
                        if (entry.isIntersecting) {
                            render('#cabinet-ta-cloud-text');
                        }
                    });
                }, {threshold: 0.1});
                obs.observe(host);
            }
        }
    }

    function initResults(cfg) {
        releaseUiLock();

        var payload = readJsonScript('cabinet-ta-payload', {clouds: {text: [], links: [], both: []}, graph: []});
        var wordForms = readJsonScript('cabinet-ta-word-forms', {});

        try {
            (new ZipfChart({
                canvasId: 'cabinet-ta-zipf-chart',
                graph: payload.graph || [],
                labels: cfg.chartLabels || {}
            })).render();
        } catch (e) {
            console.error('cabinet-text-analyzer: chart', e);
        }

        var clouds = [
            {
                tabSelector: '#cabinet-ta-cloud-text',
                hostSelector: '#cabinet-ta-cloud-text-host',
                getData: function () { return (payload.clouds || {}).text; }
            },
            {
                tabSelector: '#cabinet-ta-cloud-links',
                hostSelector: '#cabinet-ta-cloud-links-host',
                getData: function () { return (payload.clouds || {}).links; }
            },
            {
                tabSelector: '#cabinet-ta-cloud-both',
                hostSelector: '#cabinet-ta-cloud-both-host',
                getData: function () { return (payload.clouds || {}).both; }
            }
        ].map(function (item) {
            var renderer = new CloudRenderer({
                hostSelector: item.hostSelector,
                getData: item.getData,
                wordLimit: cfg.cloudWordLimit || 80,
                emptyLabel: cfg.emptyLabel || '',
                repetitionsLabel: cfg.repetitionsLabel || ''
            });
            renderer.bindResize();
            item.renderer = renderer;
            return item;
        });

        try {
            initCloudTabs(clouds, '#cabinet-ta-cloud-text');
        } catch (e) {
            console.error('cabinet-text-analyzer: cloud', e);
        }

        try {
            initDataTables($.extend({}, cfg, {wordForms: wordForms}));
        } catch (e) {
            console.error('cabinet-text-analyzer: datatables', e);
        }

        if (cfg.scrollToResults) {
            var $results = $('.cabinet-ta-results');
            if ($results.length) {
                window.setTimeout(function () {
                    var top = $results.offset().top - 80;
                    $('html, body').scrollTop(top > 0 ? top : 0);
                }, 80);
            }
        }

        releaseUiLock();
    }

    function initForm(cfg) {
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

        if (cfg.initialUrl && !cfg.hasResponse) {
            setMode('url');
            window.setTimeout(function () {
                $('#cabinet-ta-submit').trigger('click');
            }, 600);
        }
    }

    $(function () {
        releaseUiLock();
        var cfg = window.cabinetTextAnalyzerConfig || {};
        initForm(cfg);
        if (cfg.hasResponse) {
            window.requestAnimationFrame(function () {
                try {
                    initResults(cfg);
                } catch (e) {
                    console.error('cabinet-text-analyzer', e);
                    releaseUiLock();
                }
            });
        }
    });

    window.addEventListener('pagehide', releaseUiLock);
    window.addEventListener('pageshow', releaseUiLock);

})(jQuery, window, document);
