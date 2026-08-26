/**
 * История проверок — компактный вид (единственный).
 */
(function (window, $) {
    'use strict';

    var PAGE_SIZE = 25;
    var state = {
        stories: [],
        storyId: null,
        page: 1,
        q: '',
        urlQ: '',
        dateMin: '',
        dateMax: '',
        pointsMin: '',
        pointsMax: ''
    };

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function fmtNum(n) {
        return String(n).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    }

    /** Применяет компактные стили к таблицам истории (всегда). */
    function applyView() {
        document.body.setAttribute('data-ra-hist-view', 'v2');
        try {
            localStorage.removeItem('cabinet-ra-history-view');
        } catch (e) { /* ignore */ }

        var $root = $('.history');
        if ($root.length) {
            $root.attr('data-ra-hist-view', 'v2');
            $root.find('#ra-hist-classic, #history_table_wrapper').addClass('d-none');
            $root.find('#ra-hist-v2').removeClass('d-none');
        }

        $('#list-history_wrapper, #list-history').addClass('ra-hist-list--v2');
        $('#history-list-subject').addClass('ra-hist-list-title--v2');
        $('#main_history_table_wrapper, #main_history_table').addClass('ra-hist-main--v2');
        if ($.fn.DataTable && $.fn.DataTable.isDataTable('#main_history_table')) {
            try {
                $('#main_history_table').DataTable().columns.adjust();
            } catch (e) { /* ignore */ }
        }
        // убрать старый переключатель, если остался в DOM
        $('#ra-hist-ver-fabs').remove();
    }

    function formatDate(raw) {
        if (typeof window.raFormatHistoryDate === 'function') {
            return window.raFormatHistoryDate(raw);
        }
        return esc(raw);
    }

    function actionsHtml(val) {
        if (val.state === 0) {
            return typeof window.raHistoryProcessingHtml === 'function'
                ? window.raHistoryProcessingHtml()
                : '<span class="text-muted">Обрабатывается..</span>';
        }
        if (typeof window.raHistoryActionsHtml !== 'function') {
            return '';
        }
        if (val.state === -1) {
            return window.raHistoryActionsHtml(val.id, { detail: false, repeat: true, error: true });
        }
        return window.raHistoryActionsHtml(val.id);
    }

    function filterStories() {
        var q = String(state.q || '').toLowerCase().trim();
        var urlQ = String(state.urlQ || '').toLowerCase().trim();
        var dMin = state.dateMin ? parseRa(state.dateMin) : null;
        var dMax = state.dateMax ? parseRa(state.dateMax) : null;
        if (dMax) {
            dMax.setHours(23, 59, 59, 999);
        }
        var pMin = state.pointsMin !== '' && state.pointsMin != null ? Number(state.pointsMin) : null;
        var pMax = state.pointsMax !== '' && state.pointsMax != null ? Number(state.pointsMax) : null;
        if (pMin != null && isNaN(pMin)) {
            pMin = null;
        }
        if (pMax != null && isNaN(pMax)) {
            pMax = null;
        }
        return state.stories.filter(function (val) {
            if (dMin || dMax) {
                var d = parseRa(val.last_check);
                if (isNaN(d.getTime())) {
                    return false;
                }
                if (dMin && d < dMin) {
                    return false;
                }
                if (dMax && d > dMax) {
                    return false;
                }
            }
            if (pMin != null || pMax != null) {
                var pts = Number(val.points);
                if (isNaN(pts)) {
                    return false;
                }
                if (pMin != null && pts < pMin) {
                    return false;
                }
                if (pMax != null && pts > pMax) {
                    return false;
                }
            }
            if (urlQ) {
                var link = String(val.main_link || '').toLowerCase();
                if (link.indexOf(urlQ) === -1) {
                    return false;
                }
            }
            if (!q) {
                return true;
            }
            var hay = [
                val.phrase,
                val.comment,
                val.region_name,
                typeof getRegionName === 'function' ? getRegionName(val.region) : val.region
            ].join(' ').toLowerCase();
            return hay.indexOf(q) !== -1;
        });
    }

    function parseRa(raw) {
        if (typeof window.parseRaHistoryDate === 'function') {
            return window.parseRaHistoryDate(raw);
        }
        return new Date(raw);
    }

    function metricCell(value, ideal) {
        var v = value != null ? value : '—';
        var tip = ideal != null
            ? ('Факт: ' + value + '. Рекомендуется: ' + Math.round(ideal))
            : ('Факт: ' + v);
        var bg = '';
        if (ideal != null && value != null) {
            var colorFn = typeof window.getColor === 'function' ? window.getColor
                : (typeof getColor === 'function' ? getColor : null);
            if (colorFn) {
                bg = ' style="background:' + colorFn(value, Math.round(ideal)) + '"';
            }
        }
        var body = ideal != null
            ? ('<b>' + esc(v) + '</b><span class="ra-hist-v2__chip-sep">/</span>' + esc(Math.round(ideal)))
            : esc(v);
        return '<td class="ra-hist-v2__td-metric"' + bg + '><span class="ra-hist-metric" data-ra-tip="' +
            esc(tip) + '">' + body + '</span></td>';
    }

    function positionCell(val) {
        if (val.position == 0 || val.position === '0') {
            return '<td class="ra-hist-v2__td-pos"><span class="ra-hist-v2__pos ra-hist-v2__pos--out">вне ТОП-100</span></td>';
        }
        if (val.position == null || val.position === '') {
            return '<td class="ra-hist-v2__td-pos">—</td>';
        }
        return '<td class="ra-hist-v2__td-pos"><span class="ra-hist-v2__pos">' + esc(val.position) + '</span></td>';
    }

    function rowHtml(val) {
        var phrase = val.phrase;
        if (phrase == null || phrase === '') {
            phrase = 'Анализ без ключевой фразы';
        }
        var region = val.region_name || (typeof getRegionName === 'function' ? getRegionName(val.region) : (val.region || ''));
        var avg = val.average_values;
        var checked = val.calculate ? ' checked' : '';
        var url = val.main_link || '';
        var urlShort = url.length > 42 ? (url.slice(0, 40) + '…') : url;

        var metrics;
        if (avg) {
            metrics =
                metricCell(val.points, avg.points) +
                metricCell(val.coverage, avg.coverage) +
                metricCell(val.coverage_tf, avg.coverageTf) +
                metricCell(val.width, avg.width) +
                metricCell(val.density, avg.densityPercent);
        } else {
            metrics =
                metricCell(val.points, null) +
                metricCell(val.coverage, null) +
                metricCell(val.coverage_tf, null) +
                metricCell(val.width, null) +
                metricCell(val.density, null);
        }

        return (
            '<tr class="ra-hist-v2__tr" data-id="' + esc(val.id) + '">' +
            '<td class="ra-hist-v2__td-date" data-order="' + esc(val.last_check) + '">' +
            formatDate(val.last_check) +
            '</td>' +
            '<td class="ra-hist-v2__td-actions" id="history-state-v2-' + esc(val.id) + '">' + actionsHtml(val) + '</td>' +
            '<td class="ra-hist-v2__td-comment">' +
            '<input type="text" class="form-control form-control-sm history-comment ra-hist-v2__comment" ' +
            'data-target="' + esc(val.id) + '" value="' + esc(val.comment || '') + '" placeholder="—">' +
            '</td>' +
            '<td class="ra-hist-v2__td-phrase">' +
            '<span class="ra-hist-v2__phrase-text">' + esc(phrase) + '</span>' +
            '<button type="button" class="ra-hist-v2__copy" data-copy="' + esc(phrase) + '" data-ra-tip="Копировать" aria-label="Копировать">' +
            '<i class="fa fa-copy" aria-hidden="true"></i></button>' +
            '</td>' +
            '<td class="ra-hist-v2__td-region">' + esc(region) + '</td>' +
            '<td class="ra-hist-v2__td-url">' +
            (url
                ? ('<a class="ra-hist-v2__url" href="' + esc(url) + '" target="_blank" rel="noopener" data-ra-tip="' + esc(url) + '">' + esc(urlShort) + '</a>')
                : '—') +
            '</td>' +
            positionCell(val) +
            metrics +
            '<td class="ra-hist-v2__td-calc">' +
            '<div class="custom-control custom-switch custom-switch-off-danger custom-switch-on-success mb-0">' +
            '<input onclick="changeState($(this))" type="checkbox" class="custom-control-input switch" ' +
            'id="calculate-project-v2-' + esc(val.id) + '" name="noIndex" data-target="' + esc(val.id) + '"' + checked + '>' +
            '<label class="custom-control-label" for="calculate-project-v2-' + esc(val.id) + '"></label>' +
            '</div>' +
            '</td>' +
            '</tr>'
        );
    }

    function tableWrapHtml(bodyRows) {
        return (
            '<div class="ra-hist-v2__scroll">' +
            '<table class="table table-sm table-bordered table-hover ra-hist-v2__table mb-0">' +
            '<thead><tr>' +
            '<th>Дата</th>' +
            '<th>Действия</th>' +
            '<th>Коммент.</th>' +
            '<th>Фраза</th>' +
            '<th>Регион</th>' +
            '<th>URL</th>' +
            '<th>Поз.</th>' +
            '<th>Баллы</th>' +
            '<th>Покр.</th>' +
            '<th>TF</th>' +
            '<th>Шир.</th>' +
            '<th>Плотн.</th>' +
            '<th>В итог</th>' +
            '</tr></thead>' +
            '<tbody>' + bodyRows + '</tbody>' +
            '</table>' +
            '</div>'
        );
    }

    function bindRowEvents($root) {
        $root.find('.history-comment').off('change.raV2').on('change.raV2', function () {
            var $el = $(this);
            $.ajax({
                type: 'POST',
                dataType: 'json',
                url: '/edit-history-comment',
                data: {
                    id: $el.attr('data-target'),
                    comment: $el.val()
                },
                success: function () {
                    if (typeof getSuccessMessage === 'function') {
                        getSuccessMessage('Успешно изменено');
                    }
                }
            });
        });

        $root.find('.ra-hist-v2__copy').off('click.raV2').on('click.raV2', function (e) {
            e.preventDefault();
            var text = $(this).attr('data-copy') || '';
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(function () {
                    if (typeof getSuccessMessage === 'function') {
                        getSuccessMessage('Скопировано');
                    }
                });
            }
        });

        if (typeof getHistoryInfo === 'function') {
            getHistoryInfo();
        }
        if (typeof repeatScan === 'function') {
            repeatScan();
        }
        if (typeof window.initRelevanceActionTips === 'function') {
            window.initRelevanceActionTips($root[0]);
        }
    }

    function renderList() {
        var $host = $('#ra-hist-v2-list');
        if (!$host.length) {
            return;
        }
        var filtered = filterStories();
        var total = filtered.length;
        var pages = Math.max(1, Math.ceil(total / PAGE_SIZE));
        if (state.page > pages) {
            state.page = pages;
        }
        var start = (state.page - 1) * PAGE_SIZE;
        var slice = filtered.slice(start, start + PAGE_SIZE);

        if (!slice.length) {
            $host.html('<div class="ra-hist-v2__empty">Нет проверок по текущему фильтру</div>');
        } else {
            $host.html(tableWrapHtml(slice.map(rowHtml).join('')));
            bindRowEvents($host);
        }

        var from = total ? start + 1 : 0;
        var to = Math.min(start + PAGE_SIZE, total);
        $('#ra-hist-v2-info').text(
            total
                ? ('Показано ' + fmtNum(from) + '–' + fmtNum(to) + ' из ' + fmtNum(total))
                : 'Нет записей'
        );

        var $pager = $('#ra-hist-v2-pager');
        $pager.empty();
        if (pages > 1) {
            for (var i = 1; i <= pages; i++) {
                $pager.append(
                    '<button type="button" class="ra-hist-v2__page' + (i === state.page ? ' is-active' : '') +
                    '" data-page="' + i + '">' + i + '</button>'
                );
            }
        }
    }

    function ensureShell(storyId) {
        var $history = $('.history').first();
        if (!$history.length) {
            return;
        }

        if (!$history.find('#ra-hist-classic').length) {
            var $table = $history.find('#history_table');
            // wrap table + future DT wrapper siblings after init is harder — wrap table now
            if ($table.length && !$table.closest('#ra-hist-classic').length) {
                $table.wrap('<div id="ra-hist-classic" class="ra-hist-classic"></div>');
            }
        }

        if (!$history.find('#ra-hist-v2').length) {
            var exportHtml = storyId
                ? ('<div class="ra-hist-v2__exports">' +
                    '<a href="/get-file/' + esc(storyId) + '/csv" class="btn btn-secondary btn-sm">CSV</a>' +
                    '<a href="/get-file/' + esc(storyId) + '/xls" class="btn btn-secondary btn-sm">Excel</a>' +
                    '</div>')
                : '';
            var $classic = $history.find('#ra-hist-classic');
            var v2Html =
                '<div id="ra-hist-v2" class="ra-hist-v2">' +
                '<div class="ra-hist-v2__toolbar">' +
                '<div class="ra-hist-v2__toolbar-row">' +
                '<input type="search" class="form-control form-control-sm ra-hist-v2__search" ' +
                'id="ra-hist-v2-search" placeholder="Поиск: фраза, комментарий, регион" autocomplete="off">' +
                '<input type="date" class="form-control form-control-sm ra-hist-v2__date" id="ra-hist-v2-date-min" ' +
                'value="2022-03-01" aria-label="Дата от">' +
                '<input type="date" class="form-control form-control-sm ra-hist-v2__date" id="ra-hist-v2-date-max" ' +
                'aria-label="Дата до">' +
                '<div class="ra-hist-v2__points-filter" data-ra-tip="Фильтр по фактическим баллам">' +
                '<span class="ra-hist-v2__points-label">Баллы</span>' +
                '<input type="number" class="form-control form-control-sm" id="ra-hist-v2-points-min" ' +
                'placeholder="min" inputmode="numeric" aria-label="Баллы от">' +
                '<input type="number" class="form-control form-control-sm" id="ra-hist-v2-points-max" ' +
                'placeholder="max" inputmode="numeric" aria-label="Баллы до">' +
                '</div>' +
                exportHtml +
                '</div>' +
                '<div class="ra-hist-v2__toolbar-row ra-hist-v2__toolbar-row--url">' +
                '<label class="ra-hist-v2__url-label" for="ra-hist-v2-url">URL</label>' +
                '<input type="search" class="form-control form-control-sm ra-hist-v2__url-search" ' +
                'id="ra-hist-v2-url" placeholder="Фильтр по посадочной странице (URL)" autocomplete="off">' +
                '</div>' +
                '</div>' +
                '<div id="ra-hist-v2-list" class="ra-hist-v2__list"></div>' +
                '<div class="ra-hist-v2__foot">' +
                '<div id="ra-hist-v2-info" class="ra-hist-v2__info"></div>' +
                '<div id="ra-hist-v2-pager" class="ra-hist-v2__pager"></div>' +
                '</div>' +
                '</div>';
            if ($classic.length) {
                $classic.after(v2Html);
            } else {
                $history.append(v2Html);
            }
            var $max = $('#ra-hist-v2-date-max');
            if ($max.length && !$max.val()) {
                var now = new Date();
                var m = String(now.getMonth() + 1).padStart(2, '0');
                var d = String(now.getDate()).padStart(2, '0');
                $max.val(now.getFullYear() + '-' + m + '-' + d);
            }
            state.dateMin = $('#ra-hist-v2-date-min').val() || '';
            state.dateMax = $('#ra-hist-v2-date-max').val() || '';
        } else if (storyId) {
            var $ex = $history.find('.ra-hist-v2__exports');
            if ($ex.length) {
                $ex.html(
                    '<a href="/get-file/' + esc(storyId) + '/csv" class="btn btn-secondary btn-sm">CSV</a>' +
                    '<a href="/get-file/' + esc(storyId) + '/xls" class="btn btn-secondary btn-sm">Excel</a>'
                );
            }
        }

        if ($history.find('#ra-hist-v2').length && !$history.find('.ra-hist-v2__points-filter').length) {
            var $dates = $history.find('#ra-hist-v2-date-max');
            var pointsHtml =
                '<div class="ra-hist-v2__points-filter" data-ra-tip="Фильтр по фактическим баллам">' +
                '<span class="ra-hist-v2__points-label">Баллы</span>' +
                '<input type="number" class="form-control form-control-sm" id="ra-hist-v2-points-min" ' +
                'placeholder="min" inputmode="numeric" aria-label="Баллы от">' +
                '<input type="number" class="form-control form-control-sm" id="ra-hist-v2-points-max" ' +
                'placeholder="max" inputmode="numeric" aria-label="Баллы до">' +
                '</div>';
            if ($dates.length) {
                $dates.after(pointsHtml);
            } else {
                $history.find('.ra-hist-v2__toolbar').append(pointsHtml);
            }
        }

        // Миграция старого тулбара: URL отдельной строкой
        if ($history.find('#ra-hist-v2').length && !$history.find('#ra-hist-v2-url').length) {
            var $tb = $history.find('.ra-hist-v2__toolbar').first();
            if ($tb.length && !$tb.find('.ra-hist-v2__toolbar-row').length) {
                $tb.children().wrapAll('<div class="ra-hist-v2__toolbar-row"></div>');
            }
            $history.find('#ra-hist-v2-search').attr('placeholder', 'Поиск: фраза, комментарий, регион');
            $tb.append(
                '<div class="ra-hist-v2__toolbar-row ra-hist-v2__toolbar-row--url">' +
                '<label class="ra-hist-v2__url-label" for="ra-hist-v2-url">URL</label>' +
                '<input type="search" class="form-control form-control-sm ra-hist-v2__url-search" ' +
                'id="ra-hist-v2-url" placeholder="Фильтр по посадочной странице (URL)" autocomplete="off">' +
                '</div>'
            );
        }
    }

    function bindShell() {
        if (window.__raHistV2Bound) {
            return;
        }
        window.__raHistV2Bound = 1;

        var $history = $('.history').first();
        if ($history.length) {
            $history.on('input', '#ra-hist-v2-search', function () {
                state.q = $(this).val();
                state.page = 1;
                renderList();
            });

            $history.on('input', '#ra-hist-v2-url', function () {
                state.urlQ = $(this).val();
                state.page = 1;
                renderList();
            });

            $history.on('change', '#ra-hist-v2-date-min, #ra-hist-v2-date-max', function () {
                state.dateMin = $('#ra-hist-v2-date-min').val() || '';
                state.dateMax = $('#ra-hist-v2-date-max').val() || '';
                state.page = 1;
                renderList();
            });

            $history.on('input change', '#ra-hist-v2-points-min, #ra-hist-v2-points-max', function () {
                state.pointsMin = $('#ra-hist-v2-points-min').val();
                state.pointsMax = $('#ra-hist-v2-points-max').val();
                state.page = 1;
                renderList();
            });

            $history.on('click', '#ra-hist-v2-pager [data-page]', function () {
                state.page = parseInt($(this).attr('data-page'), 10) || 1;
                renderList();
            });
        }
    }

    /**
     * @param {Array} stories
     * @param {{storyId?: string|number}} opts
     */
    function render(stories, opts) {
        opts = opts || {};
        state.stories = Array.isArray(stories) ? stories.slice() : [];
        state.storyId = opts.storyId != null ? opts.storyId : state.storyId;
        state.page = 1;
        state.q = '';
        state.urlQ = '';

        ensureShell(state.storyId);
        bindShell();

        var $search = $('#ra-hist-v2-search');
        if ($search.length) {
            $search.val('');
        }
        var $url = $('#ra-hist-v2-url');
        if ($url.length) {
            $url.val('');
        }

        // After DataTable init, wrapper may be outside #ra-hist-classic — pull it in
        var $wrap = $('#history_table_wrapper');
        var $classic = $('#ra-hist-classic');
        if ($wrap.length && $classic.length && !$classic.find('#history_table_wrapper').length) {
            if ($wrap.find('#history_table').length || $classic.find('#history_table').length) {
                // if table was wrapped first then DT wrapped again, move whole wrapper into classic
                if (!$classic.has($wrap).length && $wrap.find('#history_table').length) {
                    $classic.empty().append($wrap);
                }
            }
        } else if ($classic.length && $('#history_table').length && !$classic.find('#history_table').length && !$classic.find('#history_table_wrapper').length) {
            $classic.append($('#history_table_wrapper').length ? $('#history_table_wrapper') : $('#history_table'));
        }

        renderList();
        applyView();
    }

    window.raHistoryV2 = {
        render: render,
        applyView: applyView
    };

    $(function () {
        bindShell();
        applyView();
    });
})(window, jQuery);
