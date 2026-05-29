/**
 * /monitoring/{id} — пресеты серий % в ТОП (как monitoring-v2).
 */
(function (global) {
    'use strict';

    var STORAGE_KEY = 'cabinet-mon-show-top-preset';

    var PRESET_TOPS = {
        '1': [1],
        '3': [3],
        '10': [10],
        '35102030100': [3, 5, 10, 20, 30, 100],
        all: null,
    };

    var TOP_COLORS = {
        1: '#e03131',
        3: '#f76707',
        5: '#2f9e44',
        10: '#1971c2',
        20: '#9c36b5',
        30: '#c92a2a',
        50: '#e67700',
        100: '#495057',
    };

    function topNumberFromLabel(label) {
        var m = String(label || '').match(/(?:топ|top)[-\s]*(\d+)/i);
        if (!m) {
            return null;
        }
        var n = parseInt(m[1], 10);
        return Number.isNaN(n) ? null : n;
    }

    function presetTopNumbers(preset) {
        if (Object.prototype.hasOwnProperty.call(PRESET_TOPS, preset)) {
            return PRESET_TOPS[preset];
        }
        return PRESET_TOPS['10'];
    }

    function getPreset() {
        try {
            var raw = localStorage.getItem(STORAGE_KEY);
            if (raw === '35102050100') {
                raw = 'all';
            }
            if (raw && Object.prototype.hasOwnProperty.call(PRESET_TOPS, raw)) {
                return raw;
            }
        } catch (e) {
            /* ignore */
        }
        return '10';
    }

    function setPreset(preset) {
        if (!Object.prototype.hasOwnProperty.call(PRESET_TOPS, preset)) {
            return;
        }
        try {
            localStorage.setItem(STORAGE_KEY, preset);
        } catch (e) {
            /* ignore */
        }
    }

    function datasetMatchesPreset(label, preset) {
        var n = topNumberFromLabel(label);
        if (n == null) {
            return false;
        }
        var allowed = presetTopNumbers(preset);
        if (allowed === null) {
            return true;
        }
        return allowed.indexOf(n) >= 0;
    }

    function styleTopDataset(ds, index, forceDistinct) {
        var n = topNumberFromLabel(ds.label);
        var color;
        if (forceDistinct) {
            color = global.cabinetMonitoringChartScales
                ? global.cabinetMonitoringChartScales.lineColor(index)
                : '#1864ab';
        } else {
            color =
                (n != null && TOP_COLORS[n]) ||
                (global.cabinetMonitoringChartScales
                    ? global.cabinetMonitoringChartScales.lineColor(index)
                    : '#1864ab');
        }
        ds.borderColor = color;
        ds.backgroundColor = color;
        ds.pointBackgroundColor = color;
        ds.pointBorderColor = '#ffffff';
        ds.borderWidth = 2.5;
        ds.hidden = false;
        ds.spanGaps = true;
        if (ds.pointRadius == null) {
            ds.pointRadius = 3;
        }
        if (ds.pointHoverRadius == null) {
            ds.pointHoverRadius = 5;
        }
        ds.pointBorderWidth = 1.5;
        return ds;
    }

    function applyTopPreset(chartData, preset) {
        if (!chartData || !chartData.datasets) {
            return chartData;
        }
        var datasets = chartData.datasets.filter(function (ds) {
            return datasetMatchesPreset(ds.label, preset);
        });
        var forceDistinct =
            datasets.length > 1 ||
            datasets.some(function (ds) {
                return String(ds.label || '').indexOf(' · ') >= 0;
            });
        datasets.forEach(function (ds, i) {
            styleTopDataset(ds, i, forceDistinct);
        });
        var out = {
            labels: chartData.labels || [],
            datasets: datasets,
        };
        if (forceDistinct && global.cabinetMonitoringChartScales) {
            global.cabinetMonitoringChartScales.applyDistinctLineColors(out);
        }
        return out;
    }

    function syncPresetButtons($root, preset) {
        $root.find('[data-mon-top-preset]').each(function () {
            $(this).toggleClass('active', $(this).data('mon-top-preset') === preset);
        });
    }

    function wirePresets($root, onChange) {
        if (!$root.length) {
            return;
        }
        var preset = getPreset();
        syncPresetButtons($root, preset);

        $root.on('click', '[data-mon-top-preset]', function () {
            var next = $(this).data('mon-top-preset');
            setPreset(next);
            syncPresetButtons($root, next);
            if (typeof onChange === 'function') {
                onChange(next);
            }
        });

        return preset;
    }

    function regionsTopPresetNumber() {
        var preset = getPreset();
        if (preset === '1' || preset === '3' || preset === '10') {
            return parseInt(preset, 10);
        }
        return 10;
    }

    global.cabinetMonitoringShowCharts = {
        getPreset: getPreset,
        setPreset: setPreset,
        applyTopPreset: applyTopPreset,
        wirePresets: wirePresets,
        presetTopNumbers: presetTopNumbers,
        regionsTopPresetNumber: regionsTopPresetNumber,
    };
})(window);
