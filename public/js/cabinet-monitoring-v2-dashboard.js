/**
 * Дашборд мониторинга v2 — один главный график (SER) + KPI (Topvisor).
 */
(function (window) {
    'use strict';

    const cfg = window.cabinetMonV2Config;
    const COLORS = {
        accent: 'rgba(98, 125, 152, 0.88)',
        accentLight: 'rgba(98, 125, 152, 0.45)',
        accentPale: 'rgba(98, 125, 152, 0.2)',
        buckets: [
            'rgba(155, 44, 44, 0.75)',
            'rgba(196, 120, 74, 0.75)',
            'rgba(180, 160, 90, 0.75)',
            'rgba(98, 125, 152, 0.75)',
            'rgba(45, 106, 79, 0.8)',
        ],
    };

    let mainChart = null;
    let lastFiltered = false;
    let lastRows = [];
    let chartMode = 'leaders';
    let dashMetric = 'top10';
    let trendLoading = false;
    let lastChartSig = '';
    let trendLoaderProgressTimer = null;
    let trendRevealTimer = null;
    let trendLoadSeq = 0;

    function chartSettings() {
        if (window.cabinetMonV2ChartSettings) {
            return window.cabinetMonV2ChartSettings.get();
        }
        return { periodDays: 90, range: 'weeks', metric: 'top', seriesPreset: '10' };
    }

    function chartSignature() {
        if (chartMode === 'trend') {
            const s = chartSettings();
            return 'trend|' + s.periodDays + '|' + s.range;
        }
        return chartMode + '|' + dashMetric;
    }

    function truncateLabel(label) {
        const s = String(label || '');
        return s.length > 28 ? s.slice(0, 26) + '…' : s;
    }

    function topNum(value) {
        if (value == null || value === '') {
            return null;
        }
        const n = parseFloat(String(value).replace('%', '').replace(',', '.'));
        return Number.isNaN(n) ? null : n;
    }

    function middleNum(value) {
        if (value == null || value === '') {
            return null;
        }
        const n = parseFloat(String(value).replace(',', '.'));
        return Number.isNaN(n) ? null : n;
    }

    function avgField(rows, field) {
        let sum = 0;
        let n = 0;
        rows.forEach(function (row) {
            const v = topNum(row[field]);
            if (v !== null && v >= 0) {
                sum += v;
                n += 1;
            }
        });
        return n ? Math.round((sum / n) * 10) / 10 : null;
    }

    function avgMiddle(rows) {
        let sum = 0;
        let n = 0;
        rows.forEach(function (row) {
            const v = middleNum(row.middle);
            if (v !== null && v > 0) {
                sum += v;
                n += 1;
            }
        });
        return n ? Math.round((sum / n) * 10) / 10 : null;
    }

    function sumWords(rows) {
        let sum = 0;
        rows.forEach(function (row) {
            sum += parseInt(row.words, 10) || 0;
        });
        return sum;
    }

    function countWeak(rows, threshold) {
        let c = 0;
        rows.forEach(function (row) {
            const v = topNum(row.top10);
            if (v !== null && v < threshold) {
                c += 1;
            }
        });
        return c;
    }

    function distributionBuckets(rows) {
        const labels = (cfg.i18n.dashBuckets || []).slice(0, 5);
        const counts = [0, 0, 0, 0, 0];
        rows.forEach(function (row) {
            const v = topNum(row.top10);
            if (v === null) {
                return;
            }
            if (v < 20) {
                counts[0] += 1;
            } else if (v < 40) {
                counts[1] += 1;
            } else if (v < 60) {
                counts[2] += 1;
            } else if (v < 80) {
                counts[3] += 1;
            } else {
                counts[4] += 1;
            }
        });
        return { labels: labels, counts: counts };
    }

    function topProjects(rows, limit, metric) {
        const m = metric || dashMetric;
        return rows
            .map(function (row) {
                let value = null;
                if (m === 'middle') {
                    value = middleNum(row.middle);
                } else if (m === 'top30') {
                    value = topNum(row.top30);
                } else {
                    value = topNum(row.top10);
                }
                return {
                    label: row.url || row.name || String(row.id),
                    value: value,
                };
            })
            .filter(function (item) {
                return item.value !== null;
            })
            .sort(function (a, b) {
                if (m === 'middle') {
                    return a.value - b.value;
                }
                return b.value - a.value;
            })
            .slice(0, limit);
    }

    function destroyMain() {
        if (mainChart) {
            mainChart.destroy();
            mainChart = null;
        }
        lastChartSig = '';
    }

    function updateMainChartInPlace(rows) {
        if (!mainChart) {
            return false;
        }

        if (chartMode === 'distribution') {
            const dist = distributionBuckets(rows);
            mainChart.data.labels = dist.labels;
            mainChart.data.datasets[0].data = dist.counts;
            mainChart.update('none');
            return true;
        }

        if (chartMode === 'portfolio') {
            const fields = ['top3', 'top5', 'top10', 'top30'];
            mainChart.data.datasets[0].data = fields.map(function (f) {
                return avgField(rows, f) || 0;
            });
            mainChart.update('none');
            return true;
        }

        if (chartMode === 'trend') {
            return false;
        }

        const metric = dashMetric;
        const leaders = topProjects(rows, 12, metric);
        const isMiddle = metric === 'middle';
        let chartLabel = cfg.i18n.top + '10';
        if (metric === 'top30') {
            chartLabel = cfg.i18n.top + '30';
        } else if (isMiddle) {
            chartLabel = cfg.i18n.position || 'Position';
        }

        mainChart.data.labels = leaders.map(function (l) {
            return truncateLabel(l.label);
        });
        mainChart.data.datasets[0].label = chartLabel;
        mainChart.data.datasets[0].data = leaders.map(function (l) {
            return l.value;
        });
        if (mainChart.options.scales && mainChart.options.scales.x) {
            if (isMiddle) {
                delete mainChart.options.scales.x.max;
            } else {
                mainChart.options.scales.x.max = 100;
            }
        }
        mainChart.update('none');
        return true;
    }

    function renderMainChart(rows) {
        const canvas = document.getElementById('cabinet-mon-v2-chart-main');
        if (!canvas || typeof window.Chart === 'undefined') {
            return;
        }

        destroyMain();
        const ctx = canvas.getContext('2d');

        if (chartMode === 'distribution') {
            const dist = distributionBuckets(rows);
            mainChart = new window.Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: dist.labels,
                    datasets: [
                        {
                            data: dist.counts,
                            backgroundColor: COLORS.buckets,
                            borderWidth: 1,
                            borderColor: '#fff',
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } },
                    },
                },
            });
            lastChartSig = chartSignature();
            return;
        }

        if (chartMode === 'trend') {
            fetchPortfolioTop10Trend(rows);
            return;
        }

        if (chartMode === 'portfolio') {
            const labels = ['TOP3', 'TOP5', 'TOP10', 'TOP30'];
            const fields = ['top3', 'top5', 'top10', 'top30'];
            const values = fields.map(function (f) {
                return avgField(rows, f) || 0;
            });
            mainChart = new window.Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: cfg.i18n.dashAvgLabel,
                            data: values,
                            backgroundColor: [COLORS.accentPale, COLORS.accentLight, COLORS.accent, COLORS.accent],
                            borderColor: COLORS.accent,
                            borderWidth: 1,
                            borderRadius: 6,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: {
                            max: 100,
                            ticks: { callback: function (v) { return v + '%'; } },
                        },
                    },
                },
            });
            lastChartSig = chartSignature();
            return;
        }

        const metric = dashMetric;
        const leaders = topProjects(rows, 12, metric);
        const isMiddle = metric === 'middle';
        let chartLabel = cfg.i18n.top + '10';
        if (metric === 'top30') {
            chartLabel = cfg.i18n.top + '30';
        } else if (isMiddle) {
            chartLabel = cfg.i18n.position || 'Position';
        }

        mainChart = new window.Chart(ctx, {
            type: 'bar',
            data: {
                labels: leaders.map(function (l) {
                    return truncateLabel(l.label);
                }),
                datasets: [
                    {
                        label: chartLabel,
                        data: leaders.map(function (l) {
                            return l.value;
                        }),
                        backgroundColor: COLORS.accent,
                        borderRadius: 4,
                    },
                ],
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: isMiddle
                        ? { ticks: {} }
                        : { max: 100, ticks: { callback: function (v) { return v + '%'; } } },
                },
            },
        });
        lastChartSig = chartSignature();
    }

    function updateChartSmart(rows) {
        if (chartMode === 'trend') {
            fetchPortfolioTop10Trend(rows);
            return;
        }
        const sig = chartSignature();
        if (mainChart && sig === lastChartSig && updateMainChartInPlace(rows)) {
            return;
        }
        renderMainChart(rows);
    }

    function updateStats(rows, filtered) {
        const $root = $('#cabinet-mon-v2-dashboard');
        if (!$root.length) {
            return;
        }

        const avg10 = avgField(rows, 'top10');
        const avgMid = avgMiddle(rows);
        $root.find('[data-dash="projects"]').text(rows.length);
        $root.find('[data-dash="avgTop10"]').text(avg10 !== null ? avg10 + '%' : '—');
        $root.find('[data-dash="avgMiddle"]').text(avgMid !== null ? String(avgMid) : '—');
        $root.find('[data-dash="words"]').text(sumWords(rows).toLocaleString('ru-RU'));
        $root.find('[data-dash="weak"]').text(countWeak(rows, 30));

        const $hint = $('#cabinet-mon-v2-dash-hint');
        if ($hint.length) {
            $hint.text(filtered ? cfg.i18n.dashHintFiltered : cfg.i18n.dashHintAll);
        }
    }

    function syncMetricTabsVisibility() {
        const $metric = $('#cabinet-mon-v2-dash-metric');
        if ($metric.length) {
            $metric.toggleClass('d-none', chartMode !== 'leaders');
        }
    }

    function clearTrendLoaderProgressTimer() {
        if (trendLoaderProgressTimer) {
            window.clearInterval(trendLoaderProgressTimer);
            trendLoaderProgressTimer = null;
        }
    }

    function clearTrendRevealTimer() {
        if (trendRevealTimer) {
            window.clearTimeout(trendRevealTimer);
            trendRevealTimer = null;
        }
    }

    function setTrendLoaderBar(percent) {
        const bar = document.querySelector('[data-trend-loader-bar]');
        if (bar) {
            bar.style.width = Math.max(0, Math.min(100, percent)) + '%';
        }
    }

    function showTrendLoader(projectCount) {
        const loader = document.getElementById('cabinet-mon-v2-trend-loader');
        const build = document.getElementById('cabinet-mon-v2-trend-build');
        const detail = document.querySelector('[data-trend-loader-detail]');
        const $panel = $('.cabinet-mon-v2-portfolio__chart-panel');
        if (!loader) {
            return;
        }
        hideTrendBuildProgress();
        destroyMain();
        $panel.addClass('cabinet-mon-v2-portfolio__chart-panel--loading');
        loader.removeAttribute('hidden');
        loader.setAttribute('aria-busy', 'true');
        if (build) {
            build.setAttribute('hidden', '');
        }
        setTrendLoaderBar(4);
        clearTrendLoaderProgressTimer();
        trendLoaderProgressTimer = window.setInterval(function () {
            const bar = document.querySelector('[data-trend-loader-bar]');
            if (!bar) {
                return;
            }
            const current = parseFloat(bar.style.width) || 4;
            if (current >= 88) {
                return;
            }
            setTrendLoaderBar(current + 3 + Math.random() * 5);
        }, 450);

        const s = chartSettings();
        const n = projectCount || 0;
        if (detail && cfg.i18n && cfg.i18n.portfolioTrendLoadingDetail) {
            detail.textContent = cfg.i18n.portfolioTrendLoadingDetail
                .replace(':projects', String(n))
                .replace(':days', String(s.periodDays));
        }

        const $hint = $('#cabinet-mon-v2-dash-hint');
        if ($hint.length && cfg.i18n && cfg.i18n.portfolioTrendLoading) {
            $hint.text(cfg.i18n.portfolioTrendLoading);
        }

        if (typeof toastr !== 'undefined' && cfg.i18n && cfg.i18n.portfolioTrendLoadingTitle) {
            toastr.info(cfg.i18n.portfolioTrendLoadingTitle, '', {
                timeOut: 6000,
                progressBar: true,
                positionClass: 'toast-top-right',
            });
        }
    }

    function hideTrendLoader() {
        const loader = document.getElementById('cabinet-mon-v2-trend-loader');
        const $panel = $('.cabinet-mon-v2-portfolio__chart-panel');
        clearTrendLoaderProgressTimer();
        setTrendLoaderBar(100);
        if (loader) {
            loader.setAttribute('aria-busy', 'false');
            window.setTimeout(function () {
                loader.setAttribute('hidden', '');
                setTrendLoaderBar(0);
            }, 280);
        }
        $panel.removeClass('cabinet-mon-v2-portfolio__chart-panel--loading');
    }

    function showTrendBuildProgress(current, total) {
        const build = document.getElementById('cabinet-mon-v2-trend-build');
        const bar = document.querySelector('[data-trend-build-bar]');
        const text = document.querySelector('[data-trend-build-text]');
        if (!build || !total) {
            return;
        }
        build.removeAttribute('hidden');
        const pct = Math.round((current / total) * 100);
        if (bar) {
            bar.style.width = pct + '%';
        }
        if (text && cfg.i18n && cfg.i18n.portfolioTrendBuilding) {
            text.textContent = cfg.i18n.portfolioTrendBuilding
                .replace(':current', String(current))
                .replace(':total', String(total));
        }
    }

    function hideTrendBuildProgress() {
        const build = document.getElementById('cabinet-mon-v2-trend-build');
        const bar = document.querySelector('[data-trend-build-bar]');
        if (build) {
            build.setAttribute('hidden', '');
        }
        if (bar) {
            bar.style.width = '0';
        }
    }

    function fetchPortfolioTop10Trend(rows) {
        const url = cfg.portfolioTrendUrl;
        if (!url || typeof $ === 'undefined') {
            return;
        }
        const ids = (rows || lastRows || []).map(function (row) {
            return row.id;
        });
        const loadSeq = ++trendLoadSeq;
        trendLoading = true;
        clearTrendRevealTimer();
        showTrendLoader(ids.length);

        const s = chartSettings();

        return $.ajax({
            method: 'POST',
            url: url,
            timeout: 180000,
            traditional: true,
            data: {
                _token: cfg.csrf,
                days: s.periodDays,
                range: s.range,
                project_ids: ids,
            },
        })
            .done(function (data) {
                if (loadSeq !== trendLoadSeq || chartMode !== 'trend') {
                    return;
                }
                hideTrendLoader();
                if (data && data.error) {
                    renderTrendChart({ labels: [], values: [], empty: true });
                    if (typeof toastr !== 'undefined' && data.message) {
                        toastr.warning(data.message);
                    }
                    return;
                }
                renderTrendChart(data);
            })
            .fail(function (xhr) {
                if (loadSeq !== trendLoadSeq) {
                    return;
                }
                hideTrendLoader();
                hideTrendBuildProgress();
                destroyMain();
                if (typeof toastr !== 'undefined') {
                    const msg =
                        (xhr.responseJSON && xhr.responseJSON.message) ||
                        cfg.i18n.portfolioTrendError ||
                        cfg.i18n.loadError;
                    toastr.error(msg);
                }
                const $hint = $('#cabinet-mon-v2-dash-hint');
                if ($hint.length && cfg.i18n && cfg.i18n.portfolioTrendError) {
                    $hint.text(cfg.i18n.portfolioTrendError);
                }
            })
            .always(function () {
                if (loadSeq === trendLoadSeq) {
                    trendLoading = false;
                }
            });
    }

    function revealTrendPointsStepwise(chart, allLabels, allValues, stepMs) {
        clearTrendRevealTimer();
        if (!chart || allLabels.length <= 1) {
            hideTrendBuildProgress();
            return;
        }
        const total = allLabels.length;
        showTrendBuildProgress(1, total);
        let i = 1;
        const tick = function () {
            if (!mainChart || mainChart !== chart || chartMode !== 'trend') {
                clearTrendRevealTimer();
                hideTrendBuildProgress();
                return;
            }
            if (i >= allLabels.length) {
                hideTrendBuildProgress();
                return;
            }
            chart.data.labels.push(allLabels[i]);
            chart.data.datasets[0].data.push(allValues[i]);
            chart.update('active');
            showTrendBuildProgress(i + 1, total);
            i += 1;
            if (i < allLabels.length) {
                trendRevealTimer = window.setTimeout(tick, stepMs);
            } else {
                trendRevealTimer = window.setTimeout(hideTrendBuildProgress, 400);
            }
        };
        trendRevealTimer = window.setTimeout(tick, stepMs);
    }

    function renderTrendChart(data) {
        const canvas = document.getElementById('cabinet-mon-v2-chart-main');
        if (!canvas || typeof window.Chart === 'undefined') {
            return;
        }

        destroyMain();
        const ctx = canvas.getContext('2d');
        const allLabels = (data && data.labels) || [];
        const allValues = (data && data.values) || [];
        const $hint = $('#cabinet-mon-v2-dash-hint');

        if (!allLabels.length || !allValues.length) {
            if ($hint.length && cfg.i18n && cfg.i18n.portfolioTrendEmpty) {
                $hint.text(cfg.i18n.portfolioTrendEmpty);
            }
            return;
        }

        const stepwise = allLabels.length > 4;
        const labels = stepwise ? [allLabels[0]] : allLabels;
        const values = stepwise ? [allValues[0]] : allValues;
        hideTrendBuildProgress();

        mainChart = new window.Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: (cfg.i18n && cfg.i18n.portfolioTrendLabel) || 'Средний ТОП-10',
                        data: values,
                        borderColor: COLORS.accent,
                        backgroundColor: COLORS.accentPale,
                        borderWidth: 3,
                        pointRadius: 3,
                        pointHoverRadius: 5,
                        pointBackgroundColor: '#fff',
                        pointBorderColor: COLORS.accent,
                        pointBorderWidth: 2,
                        tension: 0.2,
                        fill: true,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                animation: {
                    duration: stepwise ? 320 : 700,
                    easing: 'easeOutQuart',
                },
                plugins: {
                    legend: { display: true, position: 'top' },
                },
                scales: {
                    y: {
                        min: 0,
                        max: 100,
                        ticks: { callback: function (v) { return v + '%'; } },
                    },
                    x: {
                        ticks: { maxRotation: 45, minRotation: 0 },
                    },
                },
            },
        });
        lastChartSig = chartSignature();

        if (stepwise) {
            revealTrendPointsStepwise(mainChart, allLabels, allValues, 55);
        }

        if ($hint.length && cfg.i18n && cfg.i18n.portfolioTrendHint) {
            const projectsUsed = (data && data.projects_used) || (data && data.projects) || 0;
            const withHistory =
                (data && data.projects_with_history) != null
                    ? data.projects_with_history
                    : projectsUsed;
            const s = chartSettings();
            let hint = cfg.i18n.portfolioTrendHint
                .replace(':days', String(s.periodDays))
                .replace(':projects', String(projectsUsed))
                .replace(':with_history', String(withHistory));
            if (data && data.interpolation === 'nearest' && cfg.i18n.portfolioTrendInterpolated) {
                hint += ' ' + cfg.i18n.portfolioTrendInterpolated;
            }
            if (data && data.projects_capped && cfg.i18n.portfolioTrendCapped) {
                hint +=
                    ' ' +
                    cfg.i18n.portfolioTrendCapped.replace(
                        ':limit',
                        String(data.projects_requested || projectsUsed)
                    );
            }
            $hint.text(hint);
        }
    }

    function bindControls() {
        const $dash = $('#cabinet-mon-v2-dashboard');
        $dash.find('[data-dash-chart]').on('click', function () {
            const next = $(this).attr('data-dash-chart');
            if (!next || next === chartMode) {
                return;
            }
            chartMode = next;
            $dash.find('[data-dash-chart]').removeClass('active');
            $(this).addClass('active');
            syncMetricTabsVisibility();
            if (chartMode !== 'trend') {
                trendLoadSeq += 1;
                clearTrendLoaderProgressTimer();
                clearTrendRevealTimer();
                hideTrendLoader();
                hideTrendBuildProgress();
            }
            if (chartMode === 'trend') {
                fetchPortfolioTop10Trend(lastRows);
            } else if (lastRows.length) {
                renderMainChart(lastRows);
            }
        });

        $(document).on('cabinet-mon-v2-chart-settings-changed', function () {
            if (window.cabinetMonV2ChartSettings) {
                window.cabinetMonV2ChartSettings.syncForm();
            }
            if (chartMode === 'trend') {
                fetchPortfolioTop10Trend(lastRows);
            }
        });

        $dash.find('[data-dash-metric]').on('click', function () {
            const next = $(this).attr('data-dash-metric');
            if (!next || next === dashMetric) {
                return;
            }
            dashMetric = next;
            $dash.find('[data-dash-metric]').removeClass('active');
            $(this).addClass('active');
            if (lastRows.length && chartMode === 'leaders') {
                renderMainChart(lastRows);
            }
        });

        syncMetricTabsVisibility();
    }

    function render(rows, filtered) {
        if (!$('#cabinet-mon-v2-dashboard').length) {
            return;
        }

        lastFiltered = !!filtered;
        lastRows = rows || [];
        updateStats(lastRows, lastFiltered);

        if (!lastRows.length) {
            destroyMain();
            return;
        }

        updateChartSmart(lastRows);
    }

    function destroy() {
        trendLoadSeq += 1;
        clearTrendLoaderProgressTimer();
        clearTrendRevealTimer();
        hideTrendLoader();
        hideTrendBuildProgress();
        destroyMain();
    }

    const PORTFOLIO_STORAGE_KEY = 'cabinet-mon-v2-portfolio-visible';

    function setPortfolioVisible(visible) {
        const $section = $('#cabinet-mon-v2-dashboard');
        const $btn = $('#cabinet-mon-v2-portfolio-toggle');
        if (!$section.length || !$btn.length) {
            return;
        }
        $section.toggleClass('cabinet-mon-v2-portfolio--collapsed', !visible);
        $btn.attr('aria-expanded', visible ? 'true' : 'false');
        const label = visible
            ? (cfg && cfg.i18n && cfg.i18n.portfolioHide) || 'Скрыть портфель'
            : (cfg && cfg.i18n && cfg.i18n.portfolioShow) || 'Показать портфель';
        $btn.find('.cabinet-mon-v2-portfolio-toggle-label').text(label);
        try {
            localStorage.setItem(PORTFOLIO_STORAGE_KEY, visible ? '1' : '0');
        } catch (e) {
            /* ignore */
        }
        if (visible && mainChart) {
            window.setTimeout(function () {
                mainChart.resize();
            }, 100);
        }
    }

    function initPortfolioToggle() {
        const $btn = $('#cabinet-mon-v2-portfolio-toggle');
        if (!$btn.length) {
            return;
        }
        let visible = true;
        try {
            const stored = localStorage.getItem(PORTFOLIO_STORAGE_KEY);
            if (stored === '0') {
                visible = false;
            }
        } catch (e) {
            /* ignore */
        }
        setPortfolioVisible(visible);
        $btn.on('click', function () {
            const collapsed = $('#cabinet-mon-v2-dashboard').hasClass(
                'cabinet-mon-v2-portfolio--collapsed'
            );
            setPortfolioVisible(collapsed);
        });
    }

    bindControls();
    initPortfolioToggle();

    window.cabinetMonV2Dashboard = {
        render: render,
        destroy: destroy,
    };
})(window);
