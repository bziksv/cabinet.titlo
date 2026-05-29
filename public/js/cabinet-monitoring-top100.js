(function ($, cfg) {
    'use strict';

    if (!$ || !cfg) {
        return;
    }

    var colorArray = getColors();
    var activeFilter = 'URL';

    function setWorkspaceView(mode) {
        $('#top100-empty-state').toggleClass('d-none', mode !== 'empty');
        $('#progress').toggleClass('d-none', mode !== 'loading');
        $('#top100-result-wrap').toggleClass('d-none', mode !== 'result');
    }

    function getColors() {
        return [
            'rgba(151, 186, 229, 1)',
            'rgba(214, 2, 86, 1)',
            'rgba(0, 69, 255, 0.6)',
            'rgba(239, 50, 223, 0.6)',
            'rgba(6, 136, 165, 0.6)',
            'rgba(214, 96, 110, 1)',
            'rgba(246, 223, 78, 1)',
            'rgba(220, 51, 10, 0.6)',
            'rgba(1, 253, 215, 1)',
            'rgba(1, 79, 66, 0.6)',
            'rgba(204, 118, 32, 0.6)',
            'rgba(255, 89, 0, 1)',
            'rgba(73, 28, 1, 0.6)',
            'rgba(154, 205, 50, 1)',
            'rgba(121, 25, 6, 1)',
            'rgb(17, 255, 0)',
            'rgba(214, 2, 86, 0.6)',
            'rgba(19,212,224, 1)',
            'rgba(239, 50, 223, 1)',
            'rgba(255,89,0,0.6)',
            'rgba(244, 139, 200, 1)',
            'rgba(87, 64, 64, 0.6)',
            'rgba(163, 209, 234, 0.6)',
            'rgba(232,194,90,0.6)',
            'rgba(252, 194, 243, 1)',
            'rgba(255, 0, 0, 1)',
            'rgba(0, 255, 0, 1)',
            'rgba(0, 0, 255, 1)',
            'rgba(255, 255, 0, 1)',
            'rgba(0, 255, 255, 1)',
            'rgba(255, 0, 255, 1)',
            'rgba(255, 128, 0, 1)',
            'rgba(128, 0, 255, 1)',
            'rgba(0, 128, 255, 1)',
            'rgba(255, 204, 204, 1)',
            'rgba(204, 255, 204, 1)',
            'rgba(200, 200, 200, 1)',
            'rgba(100, 100, 100, 1)',
            'rgba(93, 65, 87, 1)',
            'rgba(177, 122, 61, 1)',
            'rgba(42, 157, 143, 1)',
        ];
    }

    function errorMessage(message) {
        $('#top100-toast-error').removeClass('d-none');
        $('#toast-message').text(message);
        setTimeout(function () {
            $('#top100-toast-error').addClass('d-none');
        }, 5000);
    }

    function successMessage(message) {
        $('#top100-toast-success').removeClass('d-none');
        $('.toast-message-success').text(message);
        setTimeout(function () {
            $('#top100-toast-success').addClass('d-none');
        }, 3000);
    }

    function getDates() {
        return window.cabinetMonitoringDateRange.expandDates(
            $('#date-range').val(),
            window.cabinetMonitoringDateRange.getMode($('#date-range'))
        );
    }

    function disableElements() {
        $('#select-my-competitors, #select-my-project, #top, #filter, #analyse, #top100-empty-analyse').prop('disabled', true);
        $('.change-filter-name').prop('disabled', true);
        $('#select-my-project').attr('data-action', 'color').text(cfg.i18n.selectProject);
        $('#select-my-competitors').attr('data-action', 'color').text(cfg.i18n.selectCompetitors);
    }

    function enableElements() {
        $('#select-my-project, #select-my-competitors, #top, #filter, #analyse, #top100-empty-analyse').prop('disabled', false);
        $('.change-filter-name').prop('disabled', false);
        selectProject();
    }

    function generateKanbanCard(items, date) {
        var kanbanItems = '';
        var top = $('#top').val();

        $.each(items.reverse(), function (k, v) {
            var url;
            if (activeFilter === 'URL') {
                url = v.url;
            } else {
                url = new URL(v.url).origin;
            }

            var hide = v.position > top ? 'hide-element' : '';
            var origin;
            try {
                origin = new URL(v.url).origin;
            } catch (e) {
                origin = v.url;
            }

            kanbanItems +=
                '<div class="kanban-item cabinet-mon-top100-item border-bottom ' + hide + '" data-index="' + v.position + '" data-bs-toggle="tooltip" data-bs-placement="top" title="' + v.url + '">' +
                '    <div class="site-position">' + v.position + '</div>' +
                '    <div class="fixed-lines" data-url="' + v.url + '" data-domain="' + origin + '">' + url + '</div>' +
                '    <div class="dropdown cabinet-mon-top100-item__menu">' +
                '        <button type="button" class="btn btn-link btn-sm text-secondary py-0" data-bs-toggle="dropdown" aria-expanded="false">' +
                '            <i class="bi bi-three-dots-vertical" aria-hidden="true"></i>' +
                '        </button>' +
                '        <div class="dropdown-menu dropdown-menu-end">' +
                '            <a class="dropdown-item" href="' + v.url + '" target="_blank" rel="noopener noreferrer">' + cfg.i18n.openSite + '</a>' +
                '            <a class="dropdown-item" href="' + cfg.routes.textAnalyzerRedirect + '/' + v.url.replaceAll('/', 'abc') + '" target="_blank" rel="noopener noreferrer">' + cfg.i18n.analyse + '</a>' +
                '            <button type="button" class="dropdown-item copy" data-target="' + v.url + '">' + cfg.i18n.copyUrl + '</button>' +
                '            <button type="button" class="dropdown-item copy" data-target="' + origin + '">' + cfg.i18n.copyDomain + '</button>' +
                '            <button type="button" class="dropdown-item set-relationships" data-target="' + v.url + '">' + cfg.i18n.viewPositions + '</button>' +
                '        </div>' +
                '    </div>' +
                '</div>';
        });

        return '<div class="kanban-card cabinet-mon-top100-column card border">' +
            '    <div class="card-header py-2 cabinet-mon-top100-column__head">' +
            '        <span class="cabinet-mon-top100-column__pos">#</span>' +
            '        <span class="cabinet-mon-top100-column__date">' + date + '</span>' +
            '    </div>' +
            '    <div class="card-body p-0">' + kanbanItems + '</div>' +
            '</div>';
    }

    function sendAjaxRequest(word, date) {
        var currentDate = moment(date, 'DD-MM-YYYY').format('YYYY-MM-DD');

        return new Promise(function (resolve) {
            $.ajax({
                url: cfg.routes.getTopSites,
                type: 'POST',
                data: {
                    _token: cfg.csrf,
                    word: word,
                    date: currentDate,
                    region: $('#searchEngines').val(),
                },
                success: function (response) {
                    if (response.length === 100) {
                        $('#result').append(generateKanbanCard(response, date));
                    }
                    resolve();
                },
                error: function (error) {
                    console.error('top100 request error:', error);
                    resolve();
                },
            });
        });
    }

    async function processWordAndDates(word, dates) {
        var counter = 1;
        var date;
        for (date of dates) {
            await sendAjaxRequest(word, date);
            $('#analysed-days').text(counter);
            counter++;
        }

        maxTop();
        changeVisual();
        filter();
        $('.cabinet-mon-top100-item__menu .dropdown').show();
    }

    function randomInteger(min, max) {
        return Math.floor(min + Math.random() * (max + 1 - min));
    }

    function drawConnect(from, to, color, id, extra) {
        function createConnection() {
            return $('<div />').addClass('connection ' + id).css('background', color);
        }

        var $from = $(from);
        var $to = $(to);
        var $main = $('#result');
        var mainTop = $main.offset().top;
        var mainLeft = $main.offset().left;
        var mainHeight = $main.outerHeight();
        var fromLeft = $from.offset().left + $from.outerWidth() - mainLeft;
        var toLeft = $to.offset().left - mainLeft;
        var fromTop = ($from.offset().top + $from.outerHeight() / 2 - mainTop) - 20;
        var toTop = ($to.offset().top + $to.outerHeight() / 2 - mainTop) - 20;
        var width = toLeft - fromLeft;
        var height = toTop - fromTop;
        var position = $from.children('.site-position').html().trim() - $to.children('.site-position').html().trim();

        if (position === 0) {
            position = '';
        } else if (position >= 1) {
            position = '&nbsp;+' + position;
        } else {
            position = '&nbsp;' + position;
        }

        if (extra) {
            fromTop += 30;
        }

        var w1 = Math.round(Math.abs(width / 2));
        var w2 = width - w1;

        createConnection()
            .css({ left: fromLeft + 'px', top: fromTop + 'px', width: w1 + 'px' })
            .html(position)
            .appendTo($main);

        var $c = createConnection()
            .css({ left: fromLeft + w1 + 'px', height: Math.abs(height) })
            .appendTo($main);

        if (height === 0) {
            $c.css('top', fromTop + 'px');
        } else if (height >= 0) {
            $c.css('top', fromTop + 'px');
        } else {
            $c.css('bottom', mainHeight - fromTop - 2 + 'px');
        }

        createConnection()
            .css({ left: fromLeft + w1 + 'px', top: fromTop + height + 'px', width: w2 })
            .appendTo($main);
    }

    function getElements(domain) {
        var elements = [];
        $.each($('.cabinet-mon-top100-column'), function (key, value) {
            elements.push($($(value).find(".fixed-lines[data-domain='" + domain + "']")).parent());
        });
        return elements;
    }

    function changeActions(element, id) {
        var $element = $(element);
        var $menu = $element.find('.dropdown-menu').first();

        if ($menu.find('.dropdown-item.remove-relationships').length === 0) {
            $menu.append(
                '<button type="button" class="dropdown-item remove-relationships" data-id="' + id + '">' + cfg.i18n.deleteLink + '</button>'
            );
        }
        $menu.find('.dropdown-item.set-relationships').remove();
    }

    function bindRemoveRelationships() {
        $('.remove-relationships').off('click').on('click', function () {
            var relId = $(this).attr('data-id');
            $('.' + relId).remove();

            var $parent = $(this).parent();
            $(this).remove();
            $parent.append(
                '<button type="button" class="dropdown-item set-relationships">' + cfg.i18n.viewPositions + '</button>'
            );

            if ($parent.closest('.kanban-item').hasClass('competitor-domain')) {
                var domain = $parent.closest('.kanban-item').find('.fixed-lines').data('domain');
                $('.fixed-lines[data-domain="' + domain + '"]').closest('.kanban-item').removeClass('competitor-domain');
            }

            setRelationShips();
            setRelationShipsFromLink();
        });
    }

    function setRelationShips() {
        $('.set-relationships').off('click').on('click', function () {
            if (colorArray.length === 0) {
                colorArray = getColors();
            }
            var color = colorArray.shift();
            var targetUrl = $(this).closest('.kanban-item').find('.fixed-lines').attr('data-domain');
            var id = randomInteger(0, 90000000);
            var elements = getElements(targetUrl);
            var will = [];
            var i;
            var j;
            var k;

            for (i = 0; i < elements.length - 1; i++) {
                for (j = 0; j < elements[i].length; j++) {
                    for (k = 0; k < elements[i + 1].length; k++) {
                        var extra = will.includes(elements[i][j]);
                        drawConnect(elements[i][j], elements[i + 1][k], color, id, extra);
                        changeActions(elements[i][j], id);
                        changeActions(elements[i + 1][k], id);
                        will.push(elements[i][j]);
                    }
                }
            }

            var last = elements.length - 1;
            for (i = 0; i < elements[last].length; i++) {
                changeActions(elements[last][i], id);
            }

            bindRemoveRelationships();
            $(this).remove();
        });
    }

    function setRelationShipsFromLink() {
        $('.fixed-lines').off('click').on('click', function () {
            var targetElement = $(this);
            var $item = targetElement.closest('.kanban-item');
            var $remove = $item.find('.remove-relationships').first();

            if ($remove.length > 0) {
                $remove.trigger('click');
                return;
            }

            if (colorArray.length === 0) {
                colorArray = getColors();
            }
            var color = colorArray.shift();
            var targetUrl = $(this).attr('data-domain');
            var id = randomInteger(0, 90000000);
            var find = false;
            var elements = getElements(targetUrl);
            var will = [];
            var i;
            var j;
            var k;

            for (i = 0; i < elements.length - 1; i++) {
                for (j = 0; j < elements[i].length; j++) {
                    for (k = 0; k < elements[i + 1].length; k++) {
                        var extra = will.includes(elements[i][j]);
                        find = true;
                        drawConnect(elements[i][j], elements[i + 1][k], color, id, extra);
                        changeActions(elements[i][j], id);
                        changeActions(elements[i + 1][k], id);
                        will.push(elements[i][j]);
                    }
                }
            }

            var last = elements.length - 1;
            for (i = 0; i < elements[last].length; i++) {
                changeActions(elements[last][i], id);
            }

            if (!find) {
                errorMessage(cfg.i18n.noMatches);
            } else {
                bindRemoveRelationships();
            }
        });
    }

    function filter() {
        $('#filter').off('input').on('input', function () {
            var filterValue = $('#filter').val().trim().toLowerCase();

            if (filterValue !== '') {
                $.each($('.fixed-lines'), function () {
                    var $parent = $(this).closest('.kanban-item');
                    if ($(this).text().toLowerCase().indexOf(filterValue) === -1) {
                        $parent.addClass('hide-element');
                    } else {
                        $parent.removeClass('hide-element');
                    }
                });
            } else {
                $('.kanban-item.hide-element').removeClass('hide-element');
            }

            var domains = [];
            var ids = [];
            $('.dropdown-item.remove-relationships').each(function () {
                var relId = $(this).attr('data-id');
                if (ids.indexOf(relId) === -1) {
                    ids.push(relId);
                    domains.push($(this).closest('.kanban-item').find('.fixed-lines').attr('data-domain'));
                    $(this).trigger('click');
                }
            });

            $.each(domains, function (key, value) {
                $(".fixed-lines[data-domain='" + value + "']").first().trigger('click');
            });
        });
    }

    function maxTop() {
        $('#top').off('change').on('change', function () {
            var top = $('#top').val();
            $('[data-index].kanban-item').each(function () {
                $(this).toggle(parseInt($(this).attr('data-index'), 10) <= top);
            });
            $('.remove-relationships').trigger('click');
        });
    }

    function changeVisual() {
        $('.change-filter-name').off('click').on('click', function () {
            $('.change-filter-name').removeClass('active');
            $(this).addClass('active');

            activeFilter = $(this).attr('data-action') === 'domain' ? 'domain' : 'URL';
            $('#filter-target').text(activeFilter === 'URL' ? 'URL' : $(this).text().trim());

            $.each($('.fixed-lines'), function () {
                $(this).text(activeFilter === 'URL' ? $(this).attr('data-url') : $(this).attr('data-domain'));
            });
        });
    }

    function selectProject() {
        $('#select-my-project').off('click').on('click', function () {
            if ($(this).attr('data-action') === 'color') {
                var find = false;
                var target = $(this).attr('data-target');

                $('.fixed-lines').each(function () {
                    if ($(this).text().toLowerCase().indexOf(target) !== -1) {
                        $(this).closest('.kanban-item').addClass('color-domain');
                        find = true;
                    }
                });

                if (find) {
                    $(this).attr('data-action', 'uncolor').text(cfg.i18n.removeProject);
                    if ($('.color-domain').first().find('.set-relationships').length > 0) {
                        $('.color-domain').first().find('.fixed-lines').trigger('click');
                    }
                } else {
                    errorMessage(cfg.i18n.domainNotFound);
                }
            } else {
                if ($('.color-domain').first().find('.remove-relationships').length > 0) {
                    $('.color-domain').first().find('.fixed-lines').trigger('click');
                }
                $('.color-domain').removeClass('color-domain');
                $(this).attr('data-action', 'color').text(cfg.i18n.selectProject);
            }
        });

        $('#select-my-competitors').off('click').on('click', function () {
            var array = cfg.competitors || [];
            var notFound = [];

            if ($(this).attr('data-action') === 'color') {
                $(this).attr('data-action', 'uncolor').text(cfg.i18n.removeCompetitors);

                $.each(array, function (key, competitor) {
                    var domain = 'https://' + competitor.url;
                    var $fixedLines = $(".fixed-lines[data-domain='" + domain + "']");

                    if ($fixedLines.length > 0) {
                        var $element = $fixedLines.first();
                        if ($element.closest('.kanban-item').find('.set-relationships').length > 0) {
                            $element.trigger('click');
                            $fixedLines.each(function () {
                                $(this).closest('.kanban-item').addClass('competitor-domain');
                            });
                        }
                    } else {
                        notFound.push(competitor.url);
                    }
                });

                if (notFound.length > 0) {
                    errorMessage(cfg.i18n.domainsNotFound + ': ' + notFound.join(', '));
                }
            } else {
                $(this).attr('data-action', 'color').text(cfg.i18n.selectCompetitors);

                $.each(array, function (key, competitor) {
                    var domain = 'https://' + competitor.url;
                    var $fixedLines = $(".fixed-lines[data-domain='" + domain + "']");
                    if ($fixedLines.length > 0) {
                        var $element = $fixedLines.first();
                        if ($element.closest('.kanban-item').find('.remove-relationships').length > 0) {
                            $element.trigger('click');
                        }
                    }
                });
            }
        });
    }

    async function startAnalyse(words, dates) {
        var word;
        for (word of words) {
            await processWordAndDates(word, dates);
        }

        setTimeout(function () {
            setWorkspaceView('result');
            enableElements();
        }, 2000);

        $('[data-bs-toggle="tooltip"]').tooltip();

        $('.copy').off('click').on('click', function () {
            var tempInput = document.createElement('input');
            tempInput.value = $(this).attr('data-target');
            document.body.appendChild(tempInput);
            tempInput.select();
            document.execCommand('copy');
            document.body.removeChild(tempInput);
            successMessage(cfg.i18n.copied);
        });

        setRelationShips();
        setRelationShipsFromLink();
    }

    $(document).ready(function () {
        $('#words-select').select2({
            width: '100%',
            minimumResultsForSearch: 8,
            dropdownCssClass: 'cabinet-mon-top100-select2',
        });

        setWorkspaceView('empty');

        if (window.cabinetMonitoringDateRange) {
            window.cabinetMonitoringDateRange.init({
                $el: $('#date-range'),
                projectId: cfg.projectId,
                calendarUrl: cfg.routes.calendarPositions,
                getRegionId: function () {
                    return $('#searchEngines').val() || null;
                },
                startDate: moment().subtract(3, 'days'),
                endDate: moment(),
                i18n: cfg.dateRangeI18n,
                onCalendarError: function () {
                    errorMessage(cfg.i18n.calendarError);
                },
            });
        }

        $('#analyse, #top100-empty-analyse').on('click', function () {
            var words = [$('#words-select').val()];

            if (!words[0]) {
                errorMessage(cfg.i18n.selectPhrase);
                return;
            }

            disableElements();
            $('#result').html('');
            $('#filter').val('');
            setWorkspaceView('loading');

            var days = getDates();
            $('#total-days').text(days.length);
            $('#analysed-days').text('0');
            startAnalyse(words, days);
        });
    });
}(window.jQuery, window.cabinetMonTop100Config));
