/**
 * Общие настройки графиков monitoring-v2 (портфель + регионы).
 */
(function (window, $) {
    'use strict';

    const STORAGE_KEY = 'cabinet-mon-v2-chart-settings';
    const cfg = window.cabinetMonV2Config || {};

    const SERIES_PRESET_TOPS = {
        '10': [10],
        '351020100': [3, 5, 10, 20, 100],
        '35102050100': [3, 5, 10, 20, 50, 100],
        all: null,
    };

    const DEFAULTS = {
        periodDays: 90,
        range: 'weeks',
        metric: 'top',
        seriesPreset: '10',
    };

    function normalize(raw) {
        const out = Object.assign({}, DEFAULTS);
        if (!raw || typeof raw !== 'object') {
            return out;
        }
        const days = parseInt(raw.periodDays, 10);
        if ([30, 60, 90, 180, 366].indexOf(days) >= 0) {
            out.periodDays = days;
        }
        if (['days', 'weeks', 'month'].indexOf(raw.range) >= 0) {
            out.range = raw.range;
        }
        if (['top', 'position'].indexOf(raw.metric) >= 0) {
            out.metric = raw.metric;
        }
        let preset = raw.seriesPreset;
        if (preset === '51020') {
            preset = '351020100';
        }
        if (Object.prototype.hasOwnProperty.call(SERIES_PRESET_TOPS, preset)) {
            out.seriesPreset = preset;
        }
        return out;
    }

    function presetTopNumbers(preset) {
        if (preset === '51020') {
            return SERIES_PRESET_TOPS['351020100'];
        }
        if (Object.prototype.hasOwnProperty.call(SERIES_PRESET_TOPS, preset)) {
            return SERIES_PRESET_TOPS[preset];
        }
        return SERIES_PRESET_TOPS['10'];
    }

    function load() {
        try {
            const raw = localStorage.getItem(STORAGE_KEY);
            if (raw) {
                return normalize(JSON.parse(raw));
            }
        } catch (e) {
            /* ignore */
        }
        return normalize(null);
    }

    let state = load();

    function save(next) {
        state = normalize(next);
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
        } catch (e) {
            /* ignore */
        }
        $(document).trigger('cabinet-mon-v2-chart-settings-changed', [state]);
    }

    function dateRangeParam() {
        if (typeof moment === 'undefined') {
            return '';
        }
        const end = moment();
        const start = moment().subtract(state.periodDays, 'days');
        return start.format('DD-MM-YYYY') + ' - ' + end.format('DD-MM-YYYY');
    }

    function syncForm($root) {
        const $scope = $root && $root.length ? $root : $(document);
        $scope.find('[data-chart-setting="periodDays"]').each(function () {
            const v = parseInt($(this).val(), 10);
            $(this).toggleClass('active', v === state.periodDays);
        });
        $scope.find('#cabinet-mon-v2-chart-period').val(String(state.periodDays));
        $scope.find('#cabinet-mon-v2-chart-range').val(state.range);
        $scope.find('#cabinet-mon-v2-chart-metric').val(state.metric);
        $scope.find('[data-chart-setting="seriesPreset"]').each(function () {
            $(this).toggleClass('active', $(this).data('series-preset') === state.seriesPreset);
        });
    }

    function bindForm() {
        const $menu = $('#cabinet-mon-v2-chart-settings-menu');
        if (!$menu.length || $menu.data('bound')) {
            return;
        }
        $menu.data('bound', 1);

        function readSeriesPreset() {
            const $active = $menu.find('[data-chart-setting="seriesPreset"].active');
            return $active.data('series-preset') || '10';
        }

        $menu.on('change', '#cabinet-mon-v2-chart-period, #cabinet-mon-v2-chart-range, #cabinet-mon-v2-chart-metric', function () {
            save({
                periodDays: parseInt($('#cabinet-mon-v2-chart-period').val(), 10),
                range: $('#cabinet-mon-v2-chart-range').val(),
                metric: $('#cabinet-mon-v2-chart-metric').val(),
                seriesPreset: readSeriesPreset(),
            });
        });

        $menu.on('click', '[data-chart-setting="seriesPreset"]', function () {
            const preset = $(this).data('series-preset');
            $menu.find('[data-chart-setting="seriesPreset"]').removeClass('active');
            $(this).addClass('active');
            save({
                periodDays: state.periodDays,
                range: state.range,
                metric: state.metric,
                seriesPreset: preset,
            });
        });

        syncForm($menu);
    }

    $(function () {
        bindForm();
    });

    window.cabinetMonV2ChartSettings = {
        defaults: DEFAULTS,
        get: function () {
            return Object.assign({}, state);
        },
        set: save,
        dateRangeParam: dateRangeParam,
        syncForm: syncForm,
        presetTopNumbers: presetTopNumbers,
    };
})(window, window.jQuery);
