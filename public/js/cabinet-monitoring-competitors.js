(function ($, cfg) {
    'use strict';

    if (!$ || !cfg) {
        return;
    }

    var interval;
    var removedRow;
    var activeCoach = null;
    var justAddedCompetitor = false;
    var pendingSuggestOpen = false;

    function competitorsCount() {
        return Number($('#counter-competitors').text()) || 0;
    }

    function getWorkspaceMode() {
        if (!$('#competitors-loading').hasClass('d-none')) {
            return 'loading';
        }
        if (!$('#tableBlock').hasClass('d-none')) {
            return 'table';
        }
        if (!$('#competitors-ready-state').hasClass('d-none')) {
            return 'ready';
        }
        return 'empty';
    }

    function markJustAddedCompetitor() {
        justAddedCompetitor = true;
    }

    function syncReadyCount(count) {
        $('#competitors-ready-count').text(count);
    }

    function coachStorageKey() {
        return 'cabinet_mon_competitors_coach_' + cfg.projectId;
    }

    function coachState() {
        try {
            return JSON.parse(localStorage.getItem(coachStorageKey()) || '{}');
        } catch (e) {
            return {};
        }
    }

    function coachMarkSeen(step) {
        var state = coachState();
        state[step] = true;
        localStorage.setItem(coachStorageKey(), JSON.stringify(state));
    }

    function coachWasSeen(step) {
        return !!coachState()[step];
    }

    function hideCoach() {
        if (!activeCoach) {
            return;
        }
        if (activeCoach.target) {
            activeCoach.target.classList.remove('cabinet-mon-coach-target');
            if (typeof bootstrap !== 'undefined' && bootstrap.Popover) {
                var instance = bootstrap.Popover.getInstance(activeCoach.target);
                if (instance) {
                    instance.hide();
                }
            }
        }
        activeCoach = null;
    }

    function showCoach(targetSelector, title, body, placement, step, onDismiss) {
        if (coachWasSeen(step)) {
            return;
        }
        if (typeof bootstrap === 'undefined' || !bootstrap.Popover) {
            return;
        }

        var target = document.querySelector(targetSelector);
        if (!target) {
            return;
        }

        hideCoach();

        var content =
            '<p class="cabinet-mon-coach-popover__body mb-2">' + body + '</p>' +
            '<button type="button" class="btn btn-primary btn-sm cabinet-mon-coach-dismiss">' + cfg.i18n.coachOk + '</button>';

        var pop = bootstrap.Popover.getOrCreateInstance(target, {
            title: title,
            content: content,
            html: true,
            sanitize: false,
            placement: placement || 'bottom',
            trigger: 'manual',
            container: 'body',
            customClass: 'cabinet-mon-coach-popover',
        });

        target.classList.add('cabinet-mon-coach-target');
        activeCoach = { target: target, step: step, onDismiss: onDismiss };

        try {
            target.scrollIntoView({ behavior: 'smooth', block: 'center' });
        } catch (e) {
            target.scrollIntoView(true);
        }

        setTimeout(function () {
            pop.show();
        }, 350);

        $(document).off('click.cabinetMonCoach').on('click.cabinetMonCoach', '.cabinet-mon-coach-dismiss', function () {
            var stepId = activeCoach ? activeCoach.step : step;
            var dismissCb = activeCoach ? activeCoach.onDismiss : onDismiss;
            coachMarkSeen(stepId);
            hideCoach();
            if (typeof dismissCb === 'function') {
                dismissCb();
            }
        });
    }

    function updateCompareUi(count) {
        var hasCompetitors = count > 0;
        var $compare = $('#compare-competitors-positions');

        $compare
            .toggleClass('btn-success', hasCompetitors)
            .toggleClass('btn-outline-secondary', !hasCompetitors)
            .toggleClass('cabinet-mon-competitors-compare-pulse', hasCompetitors && justAddedCompetitor && !coachWasSeen('compare'));

        var showSticky = hasCompetitors
            && justAddedCompetitor
            && getWorkspaceMode() === 'table'
            && !sessionStorage.getItem('cabinet_mon_competitors_sticky_dismissed_' + cfg.projectId);

        $('#competitors-next-cta').toggleClass('d-none', !showSticky);
    }

    function maybeShowCompareCoach() {
        if (coachWasSeen('compare') || !justAddedCompetitor) {
            return;
        }
        var target = getWorkspaceMode() === 'table' ? '#compare-competitors-sticky' : '#compare-competitors-ready';
        if ($(target).hasClass('d-none')) {
            target = '#compare-competitors-positions';
        }
        showCoach(
            target,
            cfg.i18n.coachCompareTitle,
            cfg.i18n.coachCompareBody,
            'top',
            'compare'
        );
    }

    function maybeShowPickCoach() {
        if (coachWasSeen('pick')) {
            return;
        }
        var $firstCheck = $('#table tbody tr td.cabinet-mon-competitors-col-check').first();
        if (!$firstCheck.length || $firstCheck.find('input[type="checkbox"]').length === 0) {
            showCoach(
                '#competitors-table-hint',
                cfg.i18n.coachPickTitle,
                cfg.i18n.coachPickBody,
                'bottom',
                'pick'
            );
            return;
        }
        if (!$firstCheck.attr('id')) {
            $firstCheck.attr('id', 'competitors-first-check-cell');
        }
        showCoach(
            '#competitors-first-check-cell',
            cfg.i18n.coachPickTitle,
            cfg.i18n.coachPickBody,
            'right',
            'pick'
        );
    }

    function maybeShowAnalyzeCoach() {
        var count = Number($('#counter-competitors').text()) || 0;
        if (count > 0 || coachWasSeen('analyze')) {
            return;
        }
        showCoach(
            '#start-analyse-region',
            cfg.i18n.coachAnalyzeTitle,
            cfg.i18n.coachAnalyzeBody,
            'bottom',
            'analyze'
        );
    }

    function updateSteps() {
        var mode = getWorkspaceMode();
        var count = competitorsCount();
        var activeStep = 1;

        if (mode === 'loading') {
            activeStep = 1;
        } else if (mode === 'table') {
            activeStep = count > 0 ? 3 : 2;
        } else if (mode === 'ready') {
            activeStep = 3;
        } else {
            activeStep = 1;
        }

        $('#competitors-steps .cabinet-mon-competitors-steps__item').each(function () {
            var step = Number($(this).data('competitors-step'));
            $(this).toggleClass('is-active', step === activeStep);
            $(this).toggleClass('is-done', step < activeStep);
        });
    }

    function addCompetitorsFromList(domains, onDone) {
        var cleaned = (domains || []).map(function (line) {
            return $.trim(line);
        }).filter(function (line) {
            return line.length > 0;
        });

        if (!cleaned.length) {
            return;
        }

        $.ajax({
            type: 'POST',
            dataType: 'json',
            url: cfg.routes.addCompetitors,
            data: {
                _token: cfg.csrf,
                projectId: cfg.projectId,
                domains: cleaned,
            },
            success: function (response) {
                if (response && response.urls) {
                    $.each(response.urls, function (k, domain) {
                        $("input[data-target='" + domain + "']").prop('checked', true);
                        $("td[data-target='" + domain + "']").attr('data-order', 'true');
                    });
                }
                markJustAddedCompetitor();
                getCompetitorsInfo();
                getCompetitorsArray();
                if (typeof toastr !== 'undefined') {
                    toastr.success(cfg.i18n.added);
                }
                updateSteps();
                if (Number($('#counter-competitors').text()) > 0) {
                    setTimeout(maybeShowCompareCoach, 400);
                }
                if (typeof onDone === 'function') {
                    onDone();
                }
            },
            error: function () {
                if (typeof toastr !== 'undefined') {
                    toastr.error(cfg.i18n.addError);
                }
            },
        });
    }

    function changeCellState(elem) {
        var targetBlock = $(elem);
        var url = targetBlock.attr('data-target');
        var targetInput = targetBlock.children('input').eq(0);
        var state = targetBlock.attr('data-order') === 'true';

        if (!state) {
            coachMarkSeen('pick');
            hideCoach();
            markJustAddedCompetitor();
            $.ajax({
                type: 'POST',
                dataType: 'json',
                url: cfg.routes.addCompetitor,
                data: {
                    _token: cfg.csrf,
                    url: url,
                    projectId: cfg.projectId,
                },
                success: function () {
                    targetInput.prop('checked', true);
                    targetBlock.attr('data-order', 'true');
                    targetBlock.closest('tr').addClass('cabinet-mon-competitors-row--picked');
                    getCompetitorsInfo();
                    getCompetitorsArray();
                    if (typeof toastr !== 'undefined') {
                        toastr.success(cfg.i18n.added);
                    }
                    updateSteps();
                    setTimeout(maybeShowCompareCoach, 500);
                },
                error: function () {
                    targetInput.prop('checked', false);
                    if (typeof toastr !== 'undefined') {
                        toastr.error(cfg.i18n.addError);
                    }
                },
            });
        } else if (confirm(cfg.i18n.removeConfirm + ' "' + url + '" ' + cfg.i18n.fromCompetitors)) {
            $.ajax({
                type: 'POST',
                dataType: 'json',
                url: cfg.routes.removeCompetitor,
                data: {
                    _token: cfg.csrf,
                    url: url,
                    projectId: cfg.projectId,
                },
                success: function () {
                    targetInput.prop('checked', false);
                    targetBlock.attr('data-order', 'false');
                    targetBlock.closest('tr').removeClass('cabinet-mon-competitors-row--picked');
                    getCompetitorsInfo();
                    getCompetitorsArray();
                    updateSteps();
                },
            });
        } else {
            targetInput.prop('checked', true);
        }
    }

    window.changeCellState = changeCellState;

    function setCompetitorsCount(count) {
        $('#counter-competitors').text(count);
        $('[data-mon-competitors-count]').text(count);
        syncReadyCount(count);
        updateCompareUi(count);
    }

    function renderCompetitorsChips(list) {
        var $chips = $('#competitors-chips');
        if (!list || !list.length) {
            $chips.html('<span class="cabinet-mon-competitors-panel__empty text-secondary small" id="competitors-chips-empty">' + cfg.i18n.chipsEmpty + '</span>');
            setCompetitorsCount(0);
            return;
        }

        var html = '';
        $.each(list, function (index, competitor) {
            html +=
                '<span class="cabinet-mon-competitors-chip">' +
                '  <span class="cabinet-mon-competitors-chip__domain">' + competitor.url + '</span>' +
                '  <button type="button" class="btn btn-sm remove-competitor-button cabinet-mon-competitors-chip__remove"' +
                '          data-id="' + competitor.id + '" data-name="' + competitor.url + '"' +
                '          data-bs-toggle="modal" data-bs-target="#removeCompetitor" title="' + cfg.i18n.removeConfirm + '">' +
                '    <i class="bi bi-x-lg" aria-hidden="true"></i>' +
                '  </button>' +
                '</span>';
        });
        $chips.html(html);
        setCompetitorsCount(list.length);
        syncReadyCount(list.length);
        if (list.length > 0 && getWorkspaceMode() === 'empty') {
            setWorkspaceView('ready');
        } else if (list.length === 0 && getWorkspaceMode() === 'ready') {
            setWorkspaceView('empty');
        } else {
            updateSteps();
        }
        if (list.length > 0 && justAddedCompetitor) {
            setTimeout(maybeShowCompareCoach, 450);
        }
    }

    function setWorkspaceView(mode) {
        if (mode === 'empty' && competitorsCount() > 0) {
            mode = 'ready';
        }

        $('#competitors-empty-state').toggleClass('d-none', mode !== 'empty');
        $('#competitors-ready-state').toggleClass('d-none', mode !== 'ready');
        $('#competitors-loading').toggleClass('d-none', mode !== 'loading');
        $('#tableBlock').toggleClass('d-none', mode !== 'table');
        $('#competitors-dt-bar').toggleClass('d-none', mode !== 'table');
        updateSteps();
        updateCompareUi(competitorsCount());
    }

    function wireCompetitorsDataTableBar(api) {
        var $wrapper = $(api.table().container());
        $wrapper.find('.dataTables_length').appendTo('#competitors-dt-length');
        $wrapper.find('.dataTables_filter').appendTo('#competitors-dt-filter');
    }

    function getCompetitorsInfo() {
        $.ajax({
            type: 'get',
            dataType: 'json',
            url: cfg.routes.competitorsInfo,
            success: function (response) {
                renderCompetitorsChips(response);
            },
        });
    }

    function getCompetitorsArray() {
        $.ajax({
            url: cfg.routes.competitorsArray,
            method: 'get',
            success: function (response) {
                sessionStorage.setItem('competitorsArray', JSON.stringify(response));
            },
        });
    }

    function renderRegions(val) {
        if (!val || typeof val !== 'object' || Object.keys(val).length === 0) {
            return '<span class="cabinet-mon-competitors-vis-empty">0</span>';
        }

        var region = '<div class="cabinet-mon-competitors-vis-pills">';
        $.each(val, function (lr, count) {
            region +=
                '<span class="cabinet-mon-competitors-vis-pill">' +
                '<span class="cabinet-mon-competitors-vis-pill__val">' + count + '</span>' +
                '<span class="cabinet-mon-competitors-vis-pill__city" title="' + lr + '">' + lr.split(',')[0] + '</span>' +
                '</span>';
        });
        region += '</div>';

        return region;
    }

    function formatPct(value) {
        if (value == null || value === '') {
            return '<span class="cabinet-mon-competitors-metric cabinet-mon-competitors-metric--empty">—</span>';
        }

        var num = Number(value);
        if (isNaN(num)) {
            return '<span class="cabinet-mon-competitors-metric cabinet-mon-competitors-metric--empty">—</span>';
        }

        var cls = 'cabinet-mon-competitors-metric';
        if (num >= 50) {
            cls += ' cabinet-mon-competitors-metric--good';
        } else if (num >= 15) {
            cls += ' cabinet-mon-competitors-metric--mid';
        }

        var text = num % 1 === 0 ? String(num) : num.toFixed(1);
        return '<span class="' + cls + '">' + text + '%</span>';
    }

    function formatAvg(value) {
        if (value == null || value === '') {
            return '<span class="cabinet-mon-competitors-metric cabinet-mon-competitors-metric--empty">—</span>';
        }

        var num = Number(value);
        if (isNaN(num)) {
            return '<span class="cabinet-mon-competitors-metric cabinet-mon-competitors-metric--empty">—</span>';
        }

        var cls = 'cabinet-mon-competitors-metric';
        if (num <= 10) {
            cls += ' cabinet-mon-competitors-metric--good';
        } else if (num <= 30) {
            cls += ' cabinet-mon-competitors-metric--mid';
        }

        return '<span class="' + cls + '">' + (num % 1 === 0 ? String(num) : num.toFixed(1)) + '</span>';
    }

    function formatIntersection(val) {
        if (val.intersectionCount == null) {
            return '<span class="cabinet-mon-competitors-metric cabinet-mon-competitors-metric--empty">—</span>';
        }

        var count = Number(val.intersectionCount) || 0;
        var pctNum = Number(val.intersectionPct);
        var pctHtml = '';

        if (!isNaN(pctNum)) {
            var pctText = pctNum % 1 === 0 ? String(pctNum) : pctNum.toFixed(1);
            pctHtml = '<span class="cabinet-mon-competitors-metric__pct">' + pctText + '%</span>';
        }

        return '<span class="cabinet-mon-competitors-metric cabinet-mon-competitors-metric--intersection">' +
            '<span class="cabinet-mon-competitors-metric__count">' + count + '</span>' +
            pctHtml +
            '</span>';
    }

    function refreshMethods() {
        $('.get-more-info.bi-plus-circle').off('click').on('click', function () {
            $(this).removeClass('bi-plus-circle').addClass('bi-dash-circle');
            var parent = $(this).parents('tr').eq(0);
            var targetDomain = $(this).attr('data-target');

            $.ajax({
                type: 'POST',
                dataType: 'json',
                url: cfg.routes.getCompetitorsDomain,
                data: {
                    _token: cfg.csrf,
                    projectId: cfg.projectId,
                    targetDomain: targetDomain,
                    region: $('#searchEngines').val(),
                },
                beforeSend: function () {
                    parent.after(
                        '<tr class="progress-render" data-id="' + targetDomain + '">' +
                        '   <td colspan="' + cfg.tableColCount + '" class="text-center py-3">' +
                        '       <div class="cabinet-mon-loader cabinet-mon-loader--sm">' +
                        '           <i class="fas fa-circle-notch cabinet-mon-loader__icon" aria-hidden="true"></i>' +
                        '       </div>' +
                        '   </td>' +
                        '</tr>'
                    );
                },
                success: function (response) {
                    var rows = '';
                    var yandexTh = false;
                    var googleTh = false;

                    $.each(response, function (phrase, engines) {
                        var yandex = '';
                        var google = '';

                        rows += '<tr><td>' + phrase + '</td>';

                        $.each(engines, function (engine, urls) {
                            if (engine === 'yandex') {
                                yandexTh = true;
                                $.each(urls, function (key, url) {
                                    $.each(url, function (region, link) {
                                        yandex += '<div><a href="' + link + '" target="_blank" rel="noopener noreferrer">' + link + '</a> (' + region + ')</div>';
                                    });
                                });
                            }
                            if (engine === 'google') {
                                googleTh = true;
                                $.each(urls, function (key, url) {
                                    $.each(url, function (region, link) {
                                        google += '<div><a href="' + link + '" target="_blank" rel="noopener noreferrer">' + link + '</a> (' + region + ')</div>';
                                    });
                                });
                            }
                        });
                        if (yandexTh) {
                            rows += '<td>' + yandex + '</td>';
                        }
                        if (googleTh) {
                            rows += '<td>' + google + '</td>';
                        }
                        rows += '</tr>';
                    });

                    var table =
                        '<table class="table table-hover table-bordered mb-0 custom-table">' +
                        '    <thead><tr>' +
                        '        <th style="min-width:200px;">' + cfg.i18n.phrase + '</th>';

                    if (yandexTh) {
                        table += '<th>' + cfg.i18n.yandex + '</th>';
                    }
                    if (googleTh) {
                        table += '<th>' + cfg.i18n.google + '</th>';
                    }
                    table += '</tr></thead><tbody>' + rows + '</tbody></table>';

                    $('#table').find('.progress-render[data-id="' + targetDomain + '"]').remove();
                    parent.after(
                        '<tr class="custom-render" data-id="' + targetDomain + '">' +
                        '   <td colspan="' + cfg.tableColCount + '">' + table + '</td>' +
                        '</tr>'
                    );

                    $.each($('.custom-table'), function () {
                        if (!$.fn.DataTable.isDataTable($(this))) {
                            $(this).DataTable({
                                dom: 'lBfrtip',
                                buttons: ['copy', 'csv', 'excel'],
                                language: dataTableLanguage(),
                                initComplete: function () {
                                    if (window.cabinetMonitoringSearch) {
                                        window.cabinetMonitoringSearch.dataTableInitComplete.call(this);
                                    }
                                },
                            });
                        }
                    });
                },
            });

            refreshMethods();
        });

        $('.get-more-info.bi-dash-circle').off('click').on('click', function () {
            var dataTarget = $(this).attr('data-target');
            $('#table').find('[data-id="' + dataTarget + '"]').remove();
            $(this).removeClass('bi-dash-circle').addClass('bi-plus-circle');
            refreshMethods();
        });
    }

    function parseIgnoredDomains() {
        var ignored = ($('#ignored-domains').val() || '')
            .split(/\r?\n/)
            .map(function (line) {
                return $.trim(line).toLowerCase();
            })
            .filter(function (line) {
                return line.length > 0;
            });

        (cfg.ignoredDomainsExtra || []).forEach(function (domain) {
            domain = $.trim(String(domain)).toLowerCase();
            if (domain && ignored.indexOf(domain) === -1) {
                ignored.push(domain);
            }
        });

        return ignored;
    }

    function getMaxValues() {
        var domains = [];
        var ignoredDomains = parseIgnoredDomains();
        var suggestLimit = Number(cfg.suggestLimit) || 10;

        $.each($('#table > tbody > tr > td.cabinet-mon-competitors-vis-col'), function () {
            var domain = String($(this).attr('data-action') || '').toLowerCase();
            if (
                ignoredDomains.indexOf(domain) === -1 &&
                !$(this).closest('tr').hasClass('cabinet-mon-competitors-row--own') &&
                $(this).parent('tr').eq(0).children('td').eq(0).children('input').length !== 0 &&
                !$(this).parent('tr').eq(0).children('td').eq(0).children('input').eq(0).is(':checked')
            ) {
                domains[$(this).attr('data-action')] = Number($(this).attr('data-order'));
            }
        });

        var tuples = [];
        var key;
        for (key in domains) {
            if (Object.prototype.hasOwnProperty.call(domains, key)) {
                tuples.push([key, domains[key]]);
            }
        }

        tuples.sort(function (a, b) {
            return a[1] < b[1] ? -1 : (a[1] > b[1] ? 1 : 0);
        });

        return tuples.reverse().slice(0, suggestLimit);
    }

    function openSuggestModal() {
        if (!$.fn.DataTable.isDataTable($('#table'))) {
            return false;
        }

        var modalEl = document.getElementById('competitorsModal');
        if (modalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }

        var table = $('#table').DataTable();
        table.on('draw.dt', function () {
            var competitors = getMaxValues();
            var textAreaText = '';
            var competitorsList = '';
            var i;

            for (i = 0; i < competitors.length; i++) {
                textAreaText += competitors[i][0] + '\n';
                competitorsList += '<div>' + competitors[i][0] + ': ' + competitors[i][1] + '</div>';
            }

            $('#competitors-textarea').val(textAreaText);
            $('#competitors-list').html(competitorsList);
            table.off('draw.dt');
        });

        table.order([8, 'desc']).draw();
        return true;
    }

    function renderTableRows(response) {
        var competitors = JSON.parse(sessionStorage.getItem('competitorsArray') || '[]');
        var data = JSON.parse(response.result);
        var $dateLine = $('#competitors-date-line');

        $dateLine.addClass('d-none');

        try {
            JSON.parse(response.date);
        } catch (e) {
            $('#dateOnly').text(response.date);
            $dateLine.removeClass('d-none');
        }

        $('#render-state').text(cfg.i18n.renderData);

        var tableRows = [];
        if (data !== []) {
            $.each(data, function (key, val) {
                var bool = false;
                var input = '';
                var rowClass = 'cabinet-mon-competitors-row';
                var checkAttrs = ' class="cabinet-mon-competitors-col-check" data-order="' + bool + '" data-target="' + key + '"';

                if (val.mainPage) {
                    input = '<span class="badge text-bg-secondary cabinet-mon-competitors-own-badge">' + cfg.i18n.yourWebsite + '</span>';
                    rowClass += ' cabinet-mon-competitors-row--own';
                    checkAttrs = ' class="cabinet-mon-competitors-col-check cabinet-mon-competitors-col-check--own" data-target="' + key + '"';
                } else if (competitors.indexOf(key) !== -1) {
                    input = '<input type="checkbox" class="form-check-input cabinet-mon-competitors-check" data-target="' + key + '" checked>';
                    bool = true;
                    rowClass += ' cabinet-mon-competitors-row--picked';
                    checkAttrs = ' class="cabinet-mon-competitors-col-check" data-order="true" onclick="changeCellState(this)" data-target="' + key + '"';
                } else {
                    input = '<input type="checkbox" class="form-check-input cabinet-mon-competitors-check" data-target="' + key + '">';
                    checkAttrs += ' onclick="changeCellState(this)"';
                }

                var domainHtml =
                    '<span class="cabinet-mon-competitors-domain">' + key + '</span>' +
                    '<button type="button" class="btn btn-sm cabinet-mon-competitors-expand get-more-info bi bi-plus-circle" data-target="' + key + '" aria-label="' + cfg.i18n.show + '"></button>';

                var engines = '<span class="cabinet-mon-competitors-engines">';
                $.each(val.urls, function (engine) {
                    if (engine === 'yandex') {
                        engines += '<i class="fab fa-yandex cabinet-mon-competitors-engine-icon cabinet-mon-competitors-engine-icon--yandex" aria-hidden="true"></i>';
                    }
                    if (engine === 'google') {
                        engines += '<i class="fab fa-google cabinet-mon-competitors-engine-icon cabinet-mon-competitors-engine-icon--google" aria-hidden="true"></i>';
                    }
                });
                engines += '</span>';

                var google = renderRegions(val.visibilityGoogle);
                var yandex = renderRegions(val.visibilityYandex);
                var visibilityCell = '<div class="cabinet-mon-competitors-vis">';
                var hasGoogle = val.visibilityGoogle && Object.keys(val.visibilityGoogle).length > 0;
                var hasYandex = val.visibilityYandex && Object.keys(val.visibilityYandex).length > 0;

                if (hasGoogle) {
                    visibilityCell += '<div class="cabinet-mon-competitors-vis__block"><span class="cabinet-mon-competitors-vis__label">Google</span>' + google + '</div>';
                }
                if (hasYandex) {
                    visibilityCell += '<div class="cabinet-mon-competitors-vis__block"><span class="cabinet-mon-competitors-vis__label">Yandex</span>' + yandex + '</div>';
                }
                visibilityCell += '</div>';

                var intersectionCell = formatIntersection(val);
                var top3Cell = formatPct(val.top_3);
                var top10Cell = formatPct(val.top_10);
                var top100Cell = formatPct(val.top_100);
                var avgCell = formatAvg(val.avgPosition);

                tableRows.push(
                    '<tr class="' + rowClass + '">' +
                    '    <td' + checkAttrs + '>' + input + '</td>' +
                    '    <td class="cabinet-mon-competitors-domain-col" data-order="' + key + '">' + domainHtml + '</td>' +
                    '    <td class="cabinet-mon-competitors-engines-col">' + engines + '</td>' +
                    '    <td class="cabinet-mon-competitors-metric-col" data-order="' + Number(val.intersectionCount || 0) + '">' + intersectionCell + '</td>' +
                    '    <td class="cabinet-mon-competitors-metric-col" data-order="' + Number(val.top_3 || 0) + '">' + top3Cell + '</td>' +
                    '    <td class="cabinet-mon-competitors-metric-col" data-order="' + Number(val.top_10 || 0) + '">' + top10Cell + '</td>' +
                    '    <td class="cabinet-mon-competitors-metric-col" data-order="' + Number(val.top_100 || 0) + '">' + top100Cell + '</td>' +
                    '    <td class="cabinet-mon-competitors-metric-col" data-order="' + Number(val.avgPosition || 101) + '">' + avgCell + '</td>' +
                    '    <td class="cabinet-mon-competitors-vis-col p-0" data-order="' + Number(val.visibility) + '" data-action="' + key + '">' + visibilityCell + '</td>' +
                    '</tr>'
                );
            });
        }

        if ($.fn.DataTable.isDataTable($('#table'))) {
            $('#table').DataTable().destroy();
        }

        $('#table > tbody').html(tableRows.join(''));

        $('#table').DataTable({
            dom: 'lfrt<"cabinet-mon-competitors-dt-footer"ip>',
            autoWidth: false,
            order: [[8, 'desc']],
            lengthMenu: [10, 25, 50, 100],
            pageLength: 50,
            language: dataTableLanguage(),
            columnDefs: [
                { orderable: false, targets: [0, 2] },
                { className: 'text-end', targets: [3, 4, 5, 6, 7] },
            ],
            initComplete: function () {
                wireCompetitorsDataTableBar(this.api());
                if (window.cabinetMonitoringSearch) {
                    window.cabinetMonitoringSearch.dataTableInitComplete.call(this);
                }
            },
        });

        $('#table').on('draw.dt', function () {
            refreshMethods();
        });

        setTimeout(function () {
            setWorkspaceView('table');
            $('#searchCompetitors').attr('title', '');
            refreshMethods();
            setTimeout(maybeShowPickCoach, 500);

            if (pendingSuggestOpen) {
                pendingSuggestOpen = false;
                setTimeout(function () {
                    openSuggestModal();
                }, 200);
            }
        }, 300);
    }

    function waitFinishResult(response) {
        clearInterval(interval);
        setWorkspaceView('loading');
        $('#render-state').text(cfg.i18n.inQueue);
        getCompetitorsArray();

        interval = setInterval(function () {
            $.ajax({
                url: cfg.routes.waitResult,
                method: 'POST',
                data: {
                    id: response.id,
                },
                success: function (waitResponse) {
                    if (waitResponse.state === 'ready') {
                        renderTableRows(waitResponse);
                        clearInterval(interval);
                    } else {
                        $('#render-state').text(cfg.i18n.inQueue);
                    }
                },
            });
        }, 5000);
    }

    function getCompetitors() {
        getCompetitorsArray();

        $.ajax({
            type: 'POST',
            dataType: 'json',
            url: cfg.routes.getCompetitors,
            data: {
                _token: cfg.csrf,
                projectId: cfg.projectId,
                region: $('#searchEngines').val(),
            },
            beforeSend: function () {
                if ($.fn.DataTable.isDataTable($('#table'))) {
                    $('#table').DataTable().destroy();
                }

                setWorkspaceView('loading');
                $('#table > tbody').html('');
                $('#searchCompetitors').attr('title', cfg.i18n.suggestDisabled || '');
                $('#render-state').text(cfg.i18n.inProgress);
            },
            success: function (response) {
                if (response.state === 'ready') {
                    renderTableRows(response);
                } else if (response.state === 'in process' || response.state === 'in queue') {
                    waitFinishResult(response);
                }

                if (response.newScan) {
                    var $toast = $('#toast-container');
                    $toast.removeClass('d-none');
                    $('.toast-message').html(cfg.i18n.newScanToast);
                    setTimeout(function () {
                        $toast.addClass('d-none');
                    }, 10000);
                }
            },
        });
    }

    $(document).ready(function () {
        if (typeof toastr !== 'undefined') {
            toastr.options = { preventDuplicates: true, timeOut: 4000 };
        }

        var filter = localStorage.getItem('lr_redbox_monitoring_selected_filter');

        if (filter !== null) {
            filter = JSON.parse(filter);
            $('#searchEngines option[value="' + filter.val + '"]').prop('selected', true);
        }

        $('#searchEngines').on('change', function () {
            var val = $(this).val();
            if (val !== '') {
                localStorage.setItem('lr_redbox_monitoring_selected_filter', JSON.stringify({ val: val }));
            } else {
                localStorage.removeItem('lr_redbox_monitoring_selected_filter');
            }
        });

        $('#searchCompetitors').on('click', function () {
            if (!$.fn.DataTable.isDataTable($('#table')) || getWorkspaceMode() !== 'table') {
                pendingSuggestOpen = true;
                if (typeof toastr !== 'undefined' && cfg.i18n.suggestStarting) {
                    toastr.info(cfg.i18n.suggestStarting);
                }
                $('#start-analyse-region').trigger('click');
                return;
            }

            openSuggestModal();
        });

        $('#add-competitors').on('click', function () {
            addCompetitorsFromList($('#competitors-textarea').val().split('\n'));
        });

        $('#save-competitor-manual').on('click', function () {
            addCompetitorsFromList($('#competitor-manual-input').val().split('\n'), function () {
                $('#competitor-manual-input').val('');
                var modalEl = document.getElementById('addCompetitorManualModal');
                if (modalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    bootstrap.Modal.getOrCreateInstance(modalEl).hide();
                }
            });
        });

        $('#start-analyse-region').on('click', function () {
            hideCoach();
            coachMarkSeen('analyze');
            getCompetitors();
        });

        $('#competitors-next-cta-close').on('click', function () {
            $('#competitors-next-cta').addClass('d-none');
            sessionStorage.setItem('cabinet_mon_competitors_sticky_dismissed_' + cfg.projectId, '1');
            coachMarkSeen('compare');
            hideCoach();
        });

        $('#compare-competitors-positions, #compare-competitors-sticky, #compare-competitors-ready').on('click', function () {
            coachMarkSeen('compare');
            hideCoach();
        });

        $('#competitors-run-analysis-from-ready').on('click', function () {
            $('#start-analyse-region').trigger('click');
        });

        $(document).on('click', '.remove-competitor-button', function () {
            $('#competitor-name').text($(this).attr('data-name'));
            removedRow = $(this).closest('.cabinet-mon-competitors-chip');
        });

        $('#remove-competitor').on('click', function () {
            $.ajax({
                type: 'POST',
                dataType: 'json',
                url: cfg.routes.removeCompetitor,
                data: {
                    _token: cfg.csrf,
                    url: $('#competitor-name').text(),
                    projectId: cfg.projectId,
                },
                success: function () {
                    if (removedRow && removedRow.length) {
                        removedRow.remove();
                    }
                    var count = Math.max(0, Number($('#counter-competitors').text()) - 1);
                    setCompetitorsCount(count);
                    if (count === 0) {
                        $('#competitors-chips').html(
                            '<span class="cabinet-mon-competitors-panel__empty text-secondary small" id="competitors-chips-empty">' +
                            cfg.i18n.chipsEmpty + '</span>'
                        );
                        setWorkspaceView('empty');
                    } else {
                        updateSteps();
                    }
                },
            });
        });

        var initialCount = Number(cfg.initialCompetitorsCount) || competitorsCount();
        if (initialCount > 0) {
            setWorkspaceView('ready');
        } else {
            setWorkspaceView('empty');
            setTimeout(maybeShowAnalyzeCoach, 600);
        }
        updateCompareUi(initialCount);
    });
}(window.jQuery, window.cabinetMonCompetitorsConfig));
