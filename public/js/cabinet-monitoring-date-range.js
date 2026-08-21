(function ($, window) {
    'use strict';

    function buildRanges(i18n) {
        var ranges = {};
        ranges[i18n.last7] = [moment().subtract(6, 'days'), moment()];
        ranges[i18n.last30] = [moment().subtract(29, 'days'), moment()];
        ranges[i18n.last60] = [moment().subtract(59, 'days'), moment()];
        ranges[i18n.last90] = [moment().subtract(89, 'days'), moment()];
        ranges[i18n.last180] = [moment().subtract(179, 'days'), moment()];
        ranges[i18n.last365] = [moment().subtract(364, 'days'), moment()];
        ranges[i18n.lastMonth] = [
            moment().subtract(1, 'month').startOf('month'),
            moment().subtract(1, 'month').endOf('month'),
        ];
        return ranges;
    }

    function buildLocale(i18n) {
        return {
            format: 'DD-MM-YYYY',
            applyLabel: i18n.apply,
            cancelLabel: i18n.cancel,
            daysOfWeek: i18n.daysOfWeek,
            monthNames: i18n.monthNames,
            firstDay: 1,
        };
    }

    function appendModeRadios(container, i18n, initialMode) {
        if (container.children('.mode').length === 0) {
            var $wrap = $('<div />', { class: 'mode' });
            var $ul = $('<ul />');
            var settings = [
                { id: 'mon-dr-mode-range', value: 'range', label: i18n.modeRange },
                { id: 'mon-dr-mode-datesFind', value: 'datesFind', label: i18n.modeDatesFind },
                { id: 'mon-dr-mode-dates', value: 'dates', label: i18n.modeDates },
                { id: 'mon-dr-mode-randWeek', value: 'randWeek', label: i18n.modeRandWeek },
                { id: 'mon-dr-mode-randMonth', value: 'randMonth', label: i18n.modeRandMonth },
            ];

            settings.forEach(function (item) {
                var $radio = $('<input />', {
                    class: 'form-check-input',
                    id: item.id,
                    type: 'radio',
                    name: 'mode',
                    value: item.value,
                });
                if ((initialMode || 'range') === item.value) {
                    $radio.prop('checked', true);
                }
                var $label = $('<label />', { class: 'form-check-label', for: item.id }).text(item.label);
                var $formCheck = $('<div />', { class: 'form-check' });
                $ul.append($('<li />').html($formCheck.prepend($radio, $label)));
            });

            container.append($wrap.append($ul));
        } else if (initialMode) {
            container.children('.mode').find('input[name="mode"]').prop('checked', false);
            container.children('.mode').find('input[value="' + initialMode + '"]').prop('checked', true);
        }

        // [calendars | mode | presets | buttons]
        container.append(
            container.children('.drp-calendar.left'),
            container.children('.drp-calendar.right'),
            container.children('.mode'),
            container.children('.ranges'),
            container.children('.drp-buttons')
        );
    }

    function layoutPickerColumns(picker) {
        if (!picker || !picker.container) {
            return;
        }
        appendModeRadios(
            picker.container,
            picker.__monDrI18n || {},
            picker.__monDrMode || 'range'
        );
        if (typeof picker.move === 'function') {
            picker.move();
        }
    }

    function collectVisibleDates(picker) {
        var container = picker.container;
        var leftCalendarEl = container.find('.drp-calendar.left tbody tr');
        var rightCalendarEl = container.find('.drp-calendar.right tbody tr');
        var leftCalendarData = picker.leftCalendar.calendar;
        var rightCalendarData = picker.rightCalendar.calendar;
        var showDates = [];
        var rows;

        for (rows = 0; rows < leftCalendarData.length; rows++) {
            var leftCalendarRowEl = $(leftCalendarEl[rows]);
            $.each(leftCalendarData[rows], function (i, item) {
                var leftCalendarDaysEl = $(leftCalendarRowEl.find('td').get(i));
                if (!leftCalendarDaysEl.hasClass('off')) {
                    showDates.push({ date: item.format('YYYY-MM-DD'), el: leftCalendarDaysEl });
                }
            });

            var rightCalendarRowEl = $(rightCalendarEl[rows]);
            $.each(rightCalendarData[rows], function (i, item) {
                var rightCalendarDaysEl = $(rightCalendarRowEl.find('td').get(i));
                if (!rightCalendarDaysEl.hasClass('off')) {
                    showDates.push({ date: item.format('YYYY-MM-DD'), el: rightCalendarDaysEl });
                }
            });
        }

        return showDates;
    }

    function highlightCalendar(picker, options) {
        if (!window.axios || !options.calendarUrl || !options.projectId) {
            return;
        }

        var showDates = collectVisibleDates(picker);
        var regionId = typeof options.getRegionId === 'function' ? options.getRegionId() : options.regionId;

        window.axios.post(options.calendarUrl, {
            projectId: options.projectId,
            regionId: regionId || null,
            dates: showDates,
        }).then(function (response) {
            $.each(response.data, function (i, item) {
                var found = showDates.find(function (elem) {
                    return elem.date === item.dateOnly;
                });
                if (found && !found.el.hasClass('exist-position')) {
                    found.el.addClass('exist-position');
                }
            });
        }).catch(function () {
            if (typeof options.onCalendarError === 'function') {
                options.onCalendarError();
            }
        });
    }

    function init(options) {
        options = options || {};
        var $range = options.$el ? options.$el : $(options.selector);
        if (!$range.length || !$.fn.daterangepicker || typeof moment === 'undefined') {
            return null;
        }

        var i18n = options.i18n || {};
        var initialMode = options.mode || $range.data('mon-date-mode') || 'range';

        $range.data('mon-date-mode', initialMode);

        $range.daterangepicker({
            opens: options.opens || 'left',
            parentEl: options.parentEl || 'body',
            startDate: options.startDate || moment().subtract(30, 'days'),
            endDate: options.endDate || moment(),
            ranges: buildRanges(i18n),
            alwaysShowCalendars: true,
            showCustomRangeLabel: false,
            locale: buildLocale(i18n),
            autoUpdateInput: options.autoUpdateInput !== false,
        });

        var pickerInst = $range.data('daterangepicker');
        if (pickerInst) {
            pickerInst.__monDrI18n = i18n;
            pickerInst.__monDrMode = initialMode;
        }

        if (options.includeModeRadios !== false) {
            $range.on('show.daterangepicker', function (ev, picker) {
                picker.__monDrI18n = i18n;
                picker.__monDrMode = $range.data('mon-date-mode') || 'range';
                layoutPickerColumns(picker);
            });
        }

        $range.on('apply.daterangepicker', function (ev, picker) {
            // autoUpdateInput: false — daterangepicker сам value не пишет.
            if (options.autoUpdateInput === false) {
                var fmt = 'DD-MM-YYYY';
                $range.val(
                    picker.startDate.format(fmt) + ' - ' + picker.endDate.format(fmt)
                );
            }
            if (options.includeModeRadios !== false) {
                var mode = picker.container.find('input[name="mode"]:checked').val() || 'range';
                $range.data('mon-date-mode', mode);
            }
            if (typeof options.onApply === 'function') {
                options.onApply(picker, $range);
            }
        });

        if (options.highlightPositions !== false) {
            $range.on('updateCalendar.daterangepicker', function (ev, picker) {
                highlightCalendar(picker, options);
            });
        }

        return $range.data('daterangepicker');
    }

    function getMode($el) {
        return $el.data('mon-date-mode') || 'range';
    }

    function expandDates(val, mode) {
        var parts = (val || '').split(' - ');
        if (parts.length < 2) {
            return [];
        }

        var startDate = moment(parts[0], 'DD-MM-YYYY');
        var endDate = moment(parts[1], 'DD-MM-YYYY');
        if (!startDate.isValid() || !endDate.isValid()) {
            return [];
        }

        if (mode === 'datesFind' || mode === 'dates') {
            return [startDate.format('DD-MM-YYYY'), endDate.format('DD-MM-YYYY')];
        }

        if (mode === 'randWeek' || mode === 'randMonth') {
            var unit = mode === 'randWeek' ? 'week' : 'month';
            var dates = [];
            var cursor = startDate.clone().startOf(unit);

            while (cursor.isSameOrBefore(endDate, 'day')) {
                var periodStart = moment.max(cursor.clone(), startDate);
                var periodEnd = moment.min(cursor.clone().endOf(unit), endDate);
                var spanDays = periodEnd.diff(periodStart, 'days');
                var pick = periodStart.clone().add(Math.max(0, Math.floor(spanDays / 2)), 'days');
                dates.push(pick.format('DD-MM-YYYY'));
                cursor.add(1, unit).startOf(unit);
            }

            return dates;
        }

        var allDates = [];
        var currentDate = startDate.clone();
        while (currentDate.isSameOrBefore(endDate, 'day')) {
            allDates.push(currentDate.format('DD-MM-YYYY'));
            currentDate.add(1, 'days');
        }

        return allDates;
    }

    window.cabinetMonitoringDateRange = {
        init: init,
        getMode: getMode,
        expandDates: expandDates,
    };
}(window.jQuery, window));
