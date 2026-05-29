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
    let trendLoaderElapsedTimer = null;
    let trendRevealTimer = null;
    let trendLoadSeq = 0;
    const TREND_LOCAL_PREFIX = 'cabinetMonV2Trend:';

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

    function clearTrendLoaderElapsedTimer() {
        if (trendLoaderElapsedTimer) {
            window.clearInterval(trendLoaderElapsedTimer);
            trendLoaderElapsedTimer = null;
        }
    }

    function trendLoaderStartedAt() {
        if (!window._cabinetMonV2TrendLoadStarted) {
            window._cabinetMonV2TrendLoadStarted = performance.now();
        }
        return window._cabinetMonV2TrendLoadStarted;
    }

    function trendLoaderElapsedSec() {
        const started = trendLoaderStartedAt();
        return Math.max(0, Math.round((performance.now() - started) / 1000));
    }

    function resetTrendLoaderClock() {
        window._cabinetMonV2TrendLoadStarted = performance.now();
    }

    function estimateTrendLoadSec(projectCount, periodDays) {
        const n = Math.max(1, projectCount || 1);
        const days = periodDays || 90;
        return Math.min(180, Math.max(30, Math.round(12 + n * 0.85 + days * 0.12)));
    }

    function updateTrendLoaderDots(percent) {
        const dots = document.querySelectorAll('.cabinet-mon-v2-trend-loader__dot');
        if (!dots.length) {
            return;
        }
        const activeCount = Math.max(0, Math.min(dots.length, Math.ceil((percent / 100) * dots.length)));
        dots.forEach(function (dot, index) {
            dot.classList.toggle('cabinet-mon-v2-trend-loader__dot--on', index < activeCount);
        });
    }

    function startTrendLoaderProgressEstimate(etaSec) {
        clearTrendLoaderProgressTimer();
        const eta = Math.max(30, etaSec || 90);
        window._cabinetMonV2TrendEtaSec = eta;
        trendLoaderProgressTimer = window.setInterval(function () {
            const elapsed = trendLoaderElapsedSec();
            const pct = Math.min(94, Math.round((elapsed / eta) * 100));
            setTrendLoaderBar(pct);
            updateTrendLoaderDots(pct);
            const ctx = window._cabinetMonV2TrendStageCtx || {};
            ctx.percent = pct;
            ctx.eta = eta;
            window._cabinetMonV2TrendStageCtx = ctx;
            setTrendLoaderStage(window._cabinetMonV2TrendStageKey || 'db', ctx);
        }, 400);
    }

    function setTrendLoaderStage(stageKey, context) {
        const stageEl = document.querySelector('[data-trend-loader-stage]');
        const elapsedEl = document.querySelector('[data-trend-loader-elapsed]');
        const track = document.querySelector('[data-trend-loader-track]');
        const i18n = cfg.i18n || {};
        let text = '';
        const ctx = context || {};
        const elapsed = trendLoaderElapsedSec();
        const eta = ctx.eta || window._cabinetMonV2TrendEtaSec || 90;
        const percent = ctx.percent != null ? ctx.percent : 0;

        if (stageKey === 'db' && i18n.portfolioTrendStageDbSingle) {
            text = i18n.portfolioTrendStageDbSingle
                .replace(':total', String(ctx.total || 0))
                .replace(':percent', String(percent))
                .replace(':elapsed', String(elapsed))
                .replace(':eta', String(eta));
        } else if (stageKey === 'agg' && i18n.portfolioTrendStageAgg) {
            text = i18n.portfolioTrendStageAgg
                .replace(':done', String(ctx.done || 0))
                .replace(':total', String(ctx.total || 0))
                .replace(':elapsed', String(elapsed));
        } else if (stageKey === 'chart' && i18n.portfolioTrendStageChart) {
            text = i18n.portfolioTrendStageChart.replace(':elapsed', String(elapsed));
        } else if (stageKey === 'cache' && i18n.portfolioTrendStageCache) {
            text = i18n.portfolioTrendStageCache.replace(':elapsed', String(elapsed));
        } else if (i18n.portfolioTrendStageWait) {
            text = i18n.portfolioTrendStageWait.replace(':elapsed', String(elapsed));
        }

        if (stageEl) {
            stageEl.textContent = text;
        }
        if (elapsedEl) {
            if (stageKey === 'db') {
                elapsedEl.textContent = percent + '% · ' + elapsed + ' / ~' + eta + ' с';
            } else {
                elapsedEl.textContent = elapsed + ' с';
            }
        }
        if (track) {
            track.setAttribute('aria-valuenow', String(percent));
            track.setAttribute('aria-valuemin', '0');
            track.setAttribute('aria-valuemax', '100');
        }
    }

    function startTrendLoaderElapsedTicker() {
        clearTrendLoaderElapsedTimer();
        trendLoaderElapsedTimer = window.setInterval(function () {
            setTrendLoaderStage(window._cabinetMonV2TrendStageKey || 'wait', window._cabinetMonV2TrendStageCtx || {});
        }, 1000);
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

    function showTrendLoader(projectCount, periodDays) {
        const loader = document.getElementById('cabinet-mon-v2-trend-loader');
        const build = document.getElementById('cabinet-mon-v2-trend-build');
        const detail = document.querySelector('[data-trend-loader-detail]');
        const $panel = $('.cabinet-mon-v2-portfolio__chart-panel');
        if (!loader) {
            return;
        }
        const s = chartSettings();
        const n = projectCount || 0;
        const etaSec = estimateTrendLoadSec(n, periodDays != null ? periodDays : s.periodDays);

        resetTrendLoaderClock();
        window._cabinetMonV2TrendStageKey = 'db';
        window._cabinetMonV2TrendStageCtx = { total: n, eta: etaSec, percent: 0 };
        hideTrendBuildProgress();
        destroyMain();
        $panel.addClass('cabinet-mon-v2-portfolio__chart-panel--loading');
        loader.removeAttribute('hidden');
        loader.setAttribute('aria-busy', 'true');
        if (build) {
            build.setAttribute('hidden', '');
        }
        setTrendLoaderBar(2);
        updateTrendLoaderDots(0);
        setTrendLoaderStage('db', window._cabinetMonV2TrendStageCtx);
        startTrendLoaderElapsedTicker();
        startTrendLoaderProgressEstimate(etaSec);
        if (detail && cfg.i18n && cfg.i18n.portfolioTrendLoadingDetail) {
            detail.textContent = cfg.i18n.portfolioTrendLoadingDetail
                .replace(':projects', String(n))
                .replace(':days', String(s.periodDays))
                .replace(':eta', String(etaSec));
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
        clearTrendLoaderElapsedTimer();
        window._cabinetMonV2TrendProgressCap = 88;
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

    function trendDebug(level, message, context) {
        if (typeof window.cabinetMonV2DebugLine === 'function') {
            window.cabinetMonV2DebugLine(level, message, context || {});
        }
    }

    function trendPostData(extra) {
        const data = Object.assign({ _token: cfg.csrf }, extra || {});
        if (cfg.adminDebug && cfg.debugSessionId) {
            data.debug_session = cfg.debugSessionId;
        }
        return data;
    }

    function trendLabelTimestamp(label) {
        const parts = String(label).split('.');
        if (parts.length !== 3) {
            return 0;
        }
        const d = new Date(parseInt(parts[2], 10), parseInt(parts[1], 10) - 1, parseInt(parts[0], 10));
        const ts = d.getTime();

        return isNaN(ts) ? 0 : ts;
    }

    function trendValueAtLabelWithNearest(sparse, label) {
        if (sparse && Object.prototype.hasOwnProperty.call(sparse, label)) {
            return sparse[label];
        }
        const targetTs = trendLabelTimestamp(label);
        if (!targetTs) {
            return null;
        }
        let prevVal = null;
        let prevTs = null;
        let nextVal = null;
        let nextTs = null;
        Object.keys(sparse || {}).forEach(function (l) {
            const ts = trendLabelTimestamp(l);
            if (!ts) {
                return;
            }
            if (ts <= targetTs && (prevTs === null || ts > prevTs)) {
                prevTs = ts;
                prevVal = sparse[l];
            }
            if (ts >= targetTs && (nextTs === null || ts < nextTs)) {
                nextTs = ts;
                nextVal = sparse[l];
            }
        });
        if (prevVal !== null && nextVal !== null) {
            const dPrev = targetTs - prevTs;
            const dNext = nextTs - targetTs;

            return dPrev <= dNext ? prevVal : nextVal;
        }
        if (prevVal !== null) {
            return prevVal;
        }
        if (nextVal !== null) {
            return nextVal;
        }

        return null;
    }

    function aggregateTrendPerProject(perProject, projectsTotal) {
        const pids = Object.keys(perProject || {});
        const projectsWithHistory = pids.length;
        if (!projectsWithHistory) {
            return { labels: [], values: [], empty: true, projects_with_history: 0 };
        }
        const labelSet = {};
        pids.forEach(function (pid) {
            Object.keys(perProject[pid] || {}).forEach(function (label) {
                labelSet[label] = true;
            });
        });
        const labels = Object.keys(labelSet).sort(function (a, b) {
            return trendLabelTimestamp(a) - trendLabelTimestamp(b);
        });
        const values = labels.map(function (label) {
            let sum = 0;
            pids.forEach(function (pid) {
                const v = trendValueAtLabelWithNearest(perProject[pid], label);
                if (v !== null) {
                    sum += v;
                }
            });

            return Math.round((sum / projectsWithHistory) * 100) / 100;
        });

        return {
            labels: labels,
            values: values,
            empty: false,
            projects: projectsTotal,
            projects_with_history: projectsWithHistory,
        };
    }

    function setTrendLoaderProgress(done, total) {
        if (!total) {
            return;
        }
        const pct = Math.max(8, Math.min(92, Math.round((done / total) * 92)));
        window._cabinetMonV2TrendProgressCap = pct + 4;
        setTrendLoaderBar(pct);
    }

    function trendStorageSignature(ids, settings) {
        const sorted = ids
            .slice()
            .map(function (id) {
                return String(id);
            })
            .sort();
        return settings.periodDays + '|' + settings.range + '|' + sorted.join(',');
    }

    function readLocalTrend(signature) {
        try {
            const raw = localStorage.getItem(TREND_LOCAL_PREFIX + signature);
            if (!raw) {
                return null;
            }
            const parsed = JSON.parse(raw);
            if (!parsed || !parsed.labels || !parsed.labels.length) {
                return null;
            }
            return parsed;
        } catch (e) {
            return null;
        }
    }

    function writeLocalTrend(signature, data) {
        if (!data || !data.labels || !data.labels.length) {
            return;
        }
        try {
            localStorage.setItem(
                TREND_LOCAL_PREFIX + signature,
                JSON.stringify({
                    labels: data.labels,
                    values: data.values,
                    days: data.days,
                    range: data.range,
                    projects: data.projects,
                    projects_used: data.projects_used,
                    projects_with_history: data.projects_with_history,
                    built_at: data.built_at,
                    cached_until: data.cached_until,
                    interpolation: data.interpolation,
                })
            );
        } catch (e) {
            /* quota */
        }
    }

    function formatTrendBuiltAt(iso) {
        if (!iso) {
            return '—';
        }
        const d = new Date(iso);
        if (isNaN(d.getTime())) {
            return String(iso);
        }
        const pad = function (n) {
            return n < 10 ? '0' + n : String(n);
        };
        return (
            pad(d.getDate()) +
            '.' +
            pad(d.getMonth() + 1) +
            '.' +
            d.getFullYear() +
            ' ' +
            pad(d.getHours()) +
            ':' +
            pad(d.getMinutes())
        );
    }

    function isTrendDataStale(data) {
        if (!data) {
            return true;
        }
        const now = Date.now();
        if (data.cached_until) {
            const until = Date.parse(data.cached_until);
            if (!isNaN(until) && now > until) {
                return true;
            }
        }
        const staleHours = cfg.trendStaleHours > 0 ? cfg.trendStaleHours : 24;
        if (data.built_at) {
            const built = Date.parse(data.built_at);
            if (!isNaN(built) && now - built > staleHours * 3600 * 1000) {
                return true;
            }
        }
        return false;
    }

    function updateTrendRefreshButton(stale, hasChart) {
        const $btn = $('#cabinet-mon-v2-trend-refresh');
        if (!$btn.length) {
            return;
        }
        if (chartMode !== 'trend' || !hasChart) {
            $btn.addClass('d-none');
            return;
        }
        $btn.removeClass('d-none');
        $btn.toggleClass('btn-warning', !!stale);
        $btn.toggleClass('btn-outline-secondary', !stale);
    }

    function fetchTrendAjax(postData) {
        return $.ajax({
            method: 'POST',
            url: cfg.portfolioTrendUrl,
            timeout: 300000,
            traditional: true,
            data: trendPostData(postData),
        });
    }

    function fetchPortfolioTop10TrendFail(xhr, loadSeq, started) {
        if (loadSeq !== trendLoadSeq) {
            return;
        }
        trendDebug('error', 'ajax.trend.fail', {
            ms: Math.round(performance.now() - started),
            status: xhr && xhr.status,
        });
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
        if (loadSeq === trendLoadSeq) {
            trendLoading = false;
        }
    }

    function fetchPortfolioTop10Trend(rows, options) {
        const url = cfg.portfolioTrendUrl;
        if (!url || typeof $ === 'undefined') {
            return;
        }
        options = options || {};
        const forceRefresh = !!options.forceRefresh;
        const ids = (rows || lastRows || []).map(function (row) {
            return row.id;
        });
        const s = chartSettings();
        const signature = trendStorageSignature(ids, s);

        if (!forceRefresh) {
            const local = readLocalTrend(signature);
            if (local) {
                const stale = isTrendDataStale(local);
                trendDebug('info', 'trend.local', { stale: stale, built_at: local.built_at });
                renderTrendChart(
                    Object.assign({}, local, {
                        from_cache: true,
                        stale: stale,
                    })
                );
                if (!stale) {
                    return;
                }
            }
        }

        const loadSeq = ++trendLoadSeq;
        trendLoading = true;
        clearTrendRevealTimer();
        showTrendLoader(ids.length, s.periodDays);

        const started = performance.now();

        trendDebug('info', 'ajax.trend.start', {
            projects: ids.length,
            days: s.periodDays,
            range: s.range,
            refresh: forceRefresh,
        });

        return fetchTrendAjax({
            days: s.periodDays,
            range: s.range,
            project_ids: ids,
            refresh: forceRefresh ? 1 : 0,
        })
            .done(function (data) {
                if (loadSeq !== trendLoadSeq || chartMode !== 'trend') {
                    return;
                }
                trendDebug('info', 'ajax.trend.done', {
                    ms: Math.round(performance.now() - started),
                    points: data && data.labels ? data.labels.length : 0,
                    empty: !!(data && data.empty),
                    from_cache: data && data.from_cache,
                    build_ms: data && data.build_ms,
                });
                setTrendLoaderBar(100);
                updateTrendLoaderDots(100);
                if (data && data.from_cache) {
                    window._cabinetMonV2TrendStageKey = 'cache';
                    setTrendLoaderStage('cache', { percent: 100 });
                } else {
                    window._cabinetMonV2TrendStageKey = 'chart';
                    setTrendLoaderStage('chart', { percent: 100 });
                }
                hideTrendLoader();
                if (data && data.error) {
                    renderTrendChart({ labels: [], values: [], empty: true });
                    updateTrendRefreshButton(false, false);
                    if (typeof toastr !== 'undefined' && data.message) {
                        toastr.warning(data.message);
                    }
                    return;
                }
                writeLocalTrend(signature, data);
                renderTrendChart(data);
            })
            .fail(function (xhr) {
                fetchPortfolioTop10TrendFail(xhr, loadSeq, started);
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
            updateTrendRefreshButton(false, false);
            hideTrendBuildProgress();
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

        const stale = !!(data && data.stale) || isTrendDataStale(data);
        updateTrendRefreshButton(stale, true);

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
            if (stale && cfg.i18n.portfolioTrendStale) {
                hint = cfg.i18n.portfolioTrendStale.replace(
                    ':built_at',
                    formatTrendBuiltAt(data && data.built_at)
                );
            } else if (data && data.from_cache && cfg.i18n.portfolioTrendCached) {
                hint +=
                    ' ' +
                    cfg.i18n.portfolioTrendCached.replace(
                        ':built_at',
                        formatTrendBuiltAt(data && data.built_at)
                    );
            }
            if (!stale && data && data.interpolation === 'nearest' && cfg.i18n.portfolioTrendInterpolated) {
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
                updateTrendRefreshButton(false, false);
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

        $('#cabinet-mon-v2-trend-refresh').on('click', function () {
            if (chartMode !== 'trend' || trendLoading) {
                return;
            }
            fetchPortfolioTop10Trend(lastRows, { forceRefresh: true });
        });
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
