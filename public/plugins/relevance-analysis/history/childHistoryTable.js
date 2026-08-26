let object
let hash = 'Loremipsumdolorsit'
hash = hash.split('').sort(function () {
    return 0.5 - Math.random()
}).join('');

function raPh(key, fallback) {
    var map = window.raFilterPh || {}
    return map[key] || fallback
}

/** Компактная дата для ячейки: 14.08.26 + время второй строкой */
function raFormatHistoryDate(raw) {
    var s = String(raw == null ? '' : raw).trim()
    if (!s) {
        return ''
    }
    var iso = s.match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?/)
    if (iso) {
        var day = iso[3] + '.' + iso[2] + '.' + iso[1].slice(2)
        if (iso[4] != null) {
            return '<span class="ra-hist-date"><span class="ra-hist-date__d">' + day +
                '</span><span class="ra-hist-date__t">' + iso[4] + ':' + iso[5] + '</span></span>'
        }
        return '<span class="ra-hist-date"><span class="ra-hist-date__d">' + day + '</span></span>'
    }
    return s
}

function parseRaHistoryDate(raw) {
    var s = String(raw == null ? '' : raw)
        .replace(/<[^>]+>/g, ' ')
        .replace(/\s+/g, ' ')
        .trim()
    if (!s) {
        return new Date(NaN)
    }
    var iso = s.match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?/)
    if (iso) {
        return new Date(+iso[1], +iso[2] - 1, +iso[3], +(iso[4] || 0), +(iso[5] || 0), +(iso[6] || 0))
    }
    var ru = s.match(/^(\d{2})\.(\d{2})\.(\d{2})(?:\s+(\d{2}):(\d{2}))?/)
    if (ru) {
        return new Date(2000 + (+ru[3]), +ru[2] - 1, +ru[1], +(ru[4] || 0), +(ru[5] || 0))
    }
    return new Date(s)
}

window.raFormatHistoryDate = raFormatHistoryDate
window.parseRaHistoryDate = parseRaHistoryDate

/**
 * Кнопки действий в истории: иконки Подробнее | Повторить.
 * @param {number|string} id
 * @param {{detail?: boolean, repeat?: boolean, error?: boolean}} opts
 */
function raHistoryActionsHtml(id, opts) {
    opts = opts || {}
    var showDetail = opts.detail !== false
    var showRepeat = opts.repeat !== false
    var showError = !!opts.error
    var html = '<div class="ra-hist-actions">'
    if (showDetail) {
        html +=
            '<a href="/show-history/' + id + '" target="_blank" rel="noopener" ' +
            'class="btn btn-secondary btn-sm ra-hist-act ra-hist-act--icon ra-hist-act--detail" ' +
            'data-ra-tip="Подробнее" aria-label="Подробнее">' +
            '<i class="fa fa-eye" aria-hidden="true"></i>' +
            '</a>'
    }
    if (showRepeat) {
        html +=
            '<button type="button" class="btn btn-secondary btn-sm ra-hist-act ra-hist-act--icon ra-hist-act--repeat get-history-info" ' +
            'data-order="' + id + '" data-bs-toggle="modal" data-bs-target="#staticBackdrop" ' +
            'data-ra-tip="Повторить" aria-label="Повторить">' +
            '<i class="fa fa-repeat" aria-hidden="true"></i>' +
            '</button>'
    }
    if (showError) {
        html += '<span class="ra-hist-actions__err text-muted" data-ra-tip="Ошибка">!</span>'
    }
    html += '</div>'
    return html
}

/** Статус «Обрабатывается» — компактный блок по центру ячейки (как кнопки). */
function raHistoryProcessingHtml() {
    return (
        '<div class="ra-hist-processing">' +
        '<span class="ra-hist-processing__text">Обрабатывается..</span>' +
        '<span class="loader relevance-history-spinner" aria-hidden="true"></span>' +
        '</div>'
    )
}

window.raHistoryActionsHtml = raHistoryActionsHtml
window.raHistoryProcessingHtml = raHistoryProcessingHtml

function getHistoryInfo() {
    $('.get-history-info').unbind("click").click(function () {
        let id = $(this).attr('data-order')
        $.ajax({
            type: "get",
            dataType: "json",
            url: "/get-history-info/" + id,
            success: function (response) {
                $('.history').show()
                let history = response.history
                if (history.type === 'list') {
                    $('#key-phrase').hide()
                    $('#site-list').show()
                    $('#siteList').val(history.siteList)
                } else {
                    $('#key-phrase').show()
                    $('#site-list').hide()
                }

                $('.form-control.link').val(history.link)
                $('.form-control.phrase').val(history.phrase)
                $('#type').val(history.type)
                $('#hiddenId').val(id)
                $(".form-select#count").val(history.count).change();
                $(".form-select.rounded-0.region").val(history.region).change();
                $(".form-control.ignoredDomains").val(history.ignoredDomains);
                $("#separator").val(history.separator);

                changeSwitchState($('#switchNoindex'), history.noIndex)

                changeSwitchState($('#switchAltAndTitle'), history.hiddenText)

                changeSwitchState($('#switchConjunctionsPrepositionsPronouns'), history.conjunctionsPrepositionsPronouns)

                changeSwitchState($('#switchMyListWords'), history.switchMyListWords, history.listWords, '.listWords')
            },
        });
    });
}

function changeSwitchState(object, state, value = '', target = '') {
    if (state === "true") {
        if (!object.is(':checked')) {
            object.trigger('click')
        }
    } else {
        if (object.is(':checked')) {
            object.trigger('click')
        }
    }

    if (value !== '') {
        $(target).val(value)
        $(target).show()
    } else {
        $(target).hide()
    }
}

function changeState(elem) {
    $.ajax({
        type: "POST",
        dataType: "json",
        url: "/change-state",
        data: {
            id: elem.attr('data-target'),
            calculate: elem.is(':checked')
        },
    });
}

function isValidate(min, max, target, settings, tableId) {
    if (settings.nTable.id === tableId) {
        return (isNaN(min) && isNaN(max)) ||
            (isNaN(min) && target <= max) ||
            (min <= target && isNaN(max)) ||
            (min <= target && target <= max);
    } else {
        return true;
    }
}

function isIncludes(target, search, settings, tableId) {
    if (settings.nTable.id === tableId) {
        if (search.length > 0) {
            return target.includes(search)
        } else {
            return true;
        }
    } else {
        return true;
    }
}

function isDateValid(target, settings, tableId, prefix) {
    if (settings.nTable.id === tableId) {
        let date = (typeof parseRaHistoryDate === 'function' ? parseRaHistoryDate(target) : new Date(target))
        let dateMin = new Date($('#dateMin' + prefix).val() + ' 00:00:00')
        let dateMax = new Date($('#dateMax' + prefix).val() + ' 23:59:59')
        if (date >= dateMin && date <= dateMax) {
            return true;
        }
    } else {
        return true;
    }
}

function scrollTo(elemPath) {
    $(document).ready(function () {
        $('html, body').animate({
            scrollTop: $(elemPath).offset().top
        }, {
            duration: 370,
            easing: "linear"
        });
    })
}

function raSetHistoryStateHtml(id, html) {
    $('#history-state-' + id + ', #history-state-v2-' + id).html(html);
}

function hideListHistory() {
    $('.list-children').dataTable().fnDestroy();
    $('#list-history').dataTable().fnDestroy();
    $('#history-list-subject').hide()
    $('#list-history').hide()
    $('#ra-hist-list-bar').hide().attr('hidden', true)
}

function hideTableHistory() {
    if ($.fn.DataTable && $.fn.DataTable.isDataTable('#history_table')) {
        $("#history_table").dataTable().fnDestroy();
    }
    $('.render').remove()
    $('#ra-hist-v2-list').empty()
    $('#ra-hist-v2').addClass('d-none')
    $('.history').hide()
}

function format(data) {
    let array = object

    let child = ''
    $.each(array[data], function (key, value) {
        let state
        if (value['state'] === 1) {
            state = raHistoryActionsHtml(value['id'])
        } else if (value['state'] === 0) {
            state = raHistoryProcessingHtml()
            checkAnalyseProgress(value['id'])
        } else if (value['state'] === -1) {
            state = raHistoryActionsHtml(value['id'], { detail: false, repeat: true, error: true })
        }

        let checked = value['calculate'] ? 'checked' : ''
        let position = value['position']
        if (position == 0 || position === '0') {
            position = 'вне ТОП-100'
        }
        let avg = value['average_values'] || null
        let metricCells = (typeof buildHistoryMetricCells === 'function')
            ? buildHistoryMetricCells(value, avg)
            : (
                '<td>' + (value['points'] != null ? value['points'] : '') + '</td>' +
                '<td>' + (value['coverage'] != null ? value['coverage'] : '') + '</td>' +
                '<td>' + (value['coverage_tf'] != null ? value['coverage_tf'] : '') + '</td>' +
                '<td>' + (value['width'] != null ? value['width'] : '') + '</td>' +
                '<td>' + (value['density'] != null ? value['density'] : '') + '</td>'
            )
        child +=
            '<tr>' +
            '   <td data-order="' + value['created_at'] + '" class="ra-hist-date-cell">' + raFormatHistoryDate(value['created_at']) + '</td>' +
            '   <td id="history-state-' + value['id'] + '" class="ra-hist-actions-cell">' +
            state +
            '   </td>' +
            '   <td><input type="text" data-target="' + value['id'] + '" class="history-comment form-control form-control-sm" value="' + String(value['comment'] || '').replace(/"/g, '&quot;') + '"></td>' +
            '   <td style="width: 150px;">' + value['phrase'] + '</td>' +
            '   <td style="width: 150px;">' + (value['region_name'] || getRegionName(value['region'])) + '</td>' +
            '   <td style="width: 150px;">' + value['main_link'] + '</td>' +
            '   <td>' + position + '</td>' +
            metricCells +
            '   <td>' +
            "   <div class='d-flex justify-content-center'> " +
            "       <div class='__helper-link ui_tooltip_w'> " +
            "           <div class='custom-control custom-switch custom-switch-off-danger custom-switch-on-success'>" +
            "               <input onclick='changeState($(this))' type='checkbox' class='custom-control-input switch' id='calculate-project-" + value['id'] + "' name='noIndex' data-target='" + value['id'] + "' " + checked + ">" +
            "               <label class='custom-control-label' for='calculate-project-" + value['id'] + "'></label>" +
            "           </div>" +
            "       </div>" +
            "   </div>" +
            '   </td>' +
            '</tr>'


    })

    let date = new Date()

    let month = date.getMonth() + 1;
    if (month < 10) {
        month = '0' + month
    }

    if (date.getDate() < 10) {
        var day = '0' + date.getDate()
    } else {
        var day = date.getDate();
    }

    date = date.getFullYear() + '-' + month + '-' + day
    let tableId = data.replace(' ', '-')

    return (
        '<table class="table table-bordered table-hover dataTable dtr-inline list-children table-sm" id="' + tableId + '">' +
        '<thead>' +
        '<tr>' +
        '     <th style="position: inherit" class="table-header ra-hist-date-th">' +
        '         <input class="form form-control form-control-sm ra-hist-date-filter" type="date" name="dateMin' + tableId + '"' +
        '                id="dateMin' + tableId + '"' +
        '                value="2022-03-01">' +
        '         <input class="form form-control form-control-sm ra-hist-date-filter" type="date" name="dateMax' + tableId + '" id="dateMax' + tableId + '"' +
        '                value="' + date + '">' +
        '     </th>' +
        '   <th class="ra-hist-actions-th"></th>' +
        '    <th style="position: inherit" class="table-header">' +
        '        <input class="w-100 form form-control search-input" type="text"' +
        '               name="projectComment' + tableId + '" id="projectComment' + tableId + '" placeholder="' + raPh('comment', 'Комментарий') + '">' +
        '    </th>' +
        '    <th style="position: inherit" class="table-header">' +
        '        <input class="w-100 form form-control search-input" type="text"' +
        '               name="phraseSearch' + tableId + '" id="phraseSearch' + tableId + '" placeholder="' + raPh('phrase', 'Фраза') + '">' +
        '    </th>' +
        '    <th style="position: inherit" class="table-header">' +
        '        <input class="w-100 form form-control search-input" type="text"' +
        '               name="regionSearch' + tableId + '" id="regionSearch' + tableId + '" placeholder="' + raPh('region', 'Регион') + '">' +
        '    </th>' +
        '    <th style="position: inherit" class="table-header">' +
        '        <input class="w-100 form form-control search-input" type="text"' +
        '               name="mainPageSearch' + tableId + '" id="mainPageSearch' + tableId + '" placeholder="' + raPh('link', 'Ссылка') + '">' +
        '    </th>' +
        '    <th style="position: inherit" class="table-header">' +
        '        <input class="w-100 form form-control search-input" type="number"' +
        '               name="minPosition' + tableId + '" id="minPosition' + tableId + '" placeholder="' + raPh('min', 'мин') + '">' +
        '        <input class="w-100 form form-control search-input" type="number"' +
        '               name="maxPosition' + tableId + '" id="maxPosition' + tableId + '" placeholder="' + raPh('max', 'макс') + '">' +
        '    </th>' +
        '    <th style="position: inherit" class="table-header">' +
        '        <input class="w-100 form form-control search-input" type="number"' +
        '               name="minPoints' + tableId + '" id="minPoints' + tableId + '" placeholder="' + raPh('min', 'мин') + '">' +
        '        <input class="w-100 form form-control search-input" type="number"' +
        '               name="maxPoints' + tableId + '" id="maxPoints' + tableId + '" placeholder="' + raPh('max', 'макс') + '">' +
        '    </th>' +
        '    <th style="position: inherit" class="table-header">' +
        '        <input class="w-100 form form-control search-input" type="number"' +
        '               name="minCoverage' + tableId + '" id="minCoverage' + tableId + '" placeholder="' + raPh('min', 'мин') + '">' +
        '        <input class="w-100 form form-control search-input" type="number"' +
        '               name="maxCoverage' + tableId + '" id="maxCoverage' + tableId + '" placeholder="' + raPh('max', 'макс') + '">' +
        '    </th>' +
        '    <th style="position: inherit" class="table-header">' +
        '        <input class="w-100 form form-control search-input" type="number"' +
        '               name="minCoverageTf' + tableId + '" id="minCoverageTf' + tableId + '" placeholder="' + raPh('min', 'мин') + '">' +
        '        <input class="w-100 form form-control search-input" type="number"' +
        '               name="maxCoverageTf' + tableId + '" id="maxCoverageTf' + tableId + '" placeholder="' + raPh('max', 'макс') + '">' +
        '    </th>' +
        '    <th style="position: inherit" class="table-header">' +
        '        <input class="w-100 form form-control search-input" type="number" name="minWidth"' + tableId +
        '               id="minWidth' + tableId + '" placeholder="' + raPh('min', 'мин') + '">' +
        '        <input class="w-100 form form-control search-input" type="number"' +
        '               name="maxWidth' + tableId + '" id="maxWidth' + tableId + '" placeholder="' + raPh('max', 'макс') + '">' +
        '    </th>' +
        '    <th style="position: inherit" class="table-header">' +
        '        <input class="w-100 form form-control search-input" type="number"' +
        '               name="minDensity' + tableId + '" id="minDensity' + tableId + '" placeholder="' + raPh('min', 'мин') + '">' +
        '        <input class="w-100 form form-control search-input" type="number"' +
        '               name="maxDensity' + tableId + '" id="maxDensity' + tableId + '" placeholder="' + raPh('max', 'макс') + '">' +
        '    </th>' +
        '   <th style="position: inherit" class="table-header">' +
        '       <div>' +
        '          Переключить всё' +
        '          <div class="d-flex w-100">' +
        '             <div class="__helper-link ui_tooltip_w">' +
        '                 <div class="custom-control custom-switch custom-switch-off-danger custom-switch-on-success changeAllStateList">' +
        '                     <input type="checkbox" id="changeAllStateList" class="custom-control-input"> ' +
        '                     <label for="changeAllStateList" class="custom-control-label"></label>' +
        '                 </div>' +
        '             </div>' +
        '          </div>' +
        '       </div>' +
        '   </th>' +
        '   </tr>' +
        '      <tr>' +
        '         <th class="table-header">Дата</th>' +
        '         <th class="table-header ra-hist-actions-th">Действия</th>' +
        '         <th class="table-header" style="max-width: 150px">Комментарий</th>' +
        '         <th class="table-header">Фраза</th>' +
        '         <th class="table-header">Регион</th>' +
        '         <th class="table-header" style="max-width: 150px">URL</th>' +
        '         <th class="table-header">Поз.</th>' +
        '         <th class="table-header">Баллы</th>' +
        '         <th class="table-header">Покр.</th>' +
        '         <th class="table-header">TF</th>' +
        '         <th class="table-header">Шир.</th>' +
        '         <th class="table-header">Плотн.</th>' +
        '         <th class="table-header">В балле</th>' +
        '   </tr>' +
        '</thead>' +
        child +
        '</table>'
    );
}

function repeatScan() {
    $('#relevance-repeat-scan').unbind("click").click(function () {
        let id = $('#hiddenId').val()
        $.ajax({
            type: "POST",
            dataType: "json",
            url: "/repeat-scan",
            data: {
                id: id,
                type: $('#type').val(),
                siteList: $('#siteList').val(),
                link: $('.form-control.link').val(),
                phrase: $('.form-control.phrase').val(),
                count: $(".form-select#count").val(),
                region: $(".form-select.rounded-0.region").val(),
                ignoredDomains: $(".form-control.ignoredDomains").val(),
                separator: $("#separator").val(),
                noIndex: $('#switchNoindex').is(':checked'),
                hiddenText: $('#switchAltAndTitle').is(':checked'),
                conjunctionsPrepositionsPronouns: $('#switchConjunctionsPrepositionsPronouns').is(':checked'),
                switchMyListWords: $('#switchMyListWords').is(':checked'),
                listWords: $('.form-control.listWords').val(),
            },
            success: function (response) {
                if (response.code === 415) {
                    $('#message-error-info').html(response.message)
                    $('.toast-top-right.error-message').show(300)

                    setTimeout(() => {
                        $('.toast-top-right.error-message').show(300)
                    }, 5000)
                }

                if (response.code === 200) {
                    raSetHistoryStateHtml(id, raHistoryProcessingHtml())
                    checkAnalyseProgress(id)
                }

            },
            error: function (response) {
                $('#toast-container').show(300)
                $('#message-info').html(response.responseJSON.message)
                setInterval(function () {
                    $('#toast-container').hide(300)
                }, 3500)
            }
        });
    });
}

function checkAnalyseProgress(id) {
    $.ajax({
        type: "POST",
        dataType: "json",
        url: "/check-state",
        data: {
            id: id,
        },
        success: function (response) {
            if (response.message === 'wait') {
                setTimeout(() => {
                    checkAnalyseProgress(id)
                }, 10000)
            } else if (response.message === 'error') {
                raSetHistoryStateHtml(id,
                    raHistoryActionsHtml(id, { detail: false, repeat: true, error: true })
                );
            } else if (response.message === 'success') {
                let newObject = response.newObject
                if (!newObject || !newObject.id) {
                    raSetHistoryStateHtml(id, raHistoryActionsHtml(id))
                    getHistoryInfo()
                    return
                }

                let avg = newObject.average_values || null
                // Без avg — ждём saveHistoryResult (иначе «голые» цифры без заливки).
                if (!avg || avg.points == null) {
                    setTimeout(function () {
                        checkAnalyseProgress(id)
                    }, 2000)
                    return
                }

                raSetHistoryStateHtml(id, raHistoryActionsHtml(id))

                // Та же запись (обновили in-place) — новую строку не добавляем.
                if (String(newObject.id) === String(id)) {
                    getHistoryInfo()
                    return
                }

                // Уже есть строка с этим id (повторный poll / redraw).
                if ($('#history_table').find('[data-target="' + newObject.id + '"], #history-state-' + newObject.id).length) {
                    getHistoryInfo()
                    return
                }

                // Без phrase/link — битый payload; не создаём пустую строку (DataTables tn/4).
                if (newObject.phrase === undefined && newObject.main_link === undefined) {
                    getHistoryInfo()
                    return
                }

                if (!$.fn.DataTable.isDataTable('#history_table')) {
                    getHistoryInfo()
                    return
                }

                let table = $('#history_table').DataTable()
                let phrase = newObject.phrase
                if (phrase == null || phrase === '') {
                    phrase = 'Анализ без ключевого слова'
                }
                let position = Number(newObject.position) === 0
                    ? 'Не попал в топ 100'
                    : (newObject.position != null ? newObject.position : '')
                let region = newObject.region_name || (typeof getRegionName === 'function' ? getRegionName(newObject.region) : '') || ''
                let checked = newObject.calculate ? 'checked' : ''

                let $tr = $('<tr class="render"></tr>')
                $tr.append('<td data-order="' + (newObject.last_check || '') + '" class="ra-hist-date-cell">' + raFormatHistoryDate(newObject.last_check || '') + '</td>')
                $tr.append(
                    '<td id="history-state-' + newObject.id + '" class="ra-hist-actions-cell">' +
                    raHistoryActionsHtml(newObject.id) +
                    '</td>'
                )
                $tr.append(
                    '<td><input type="text" data-target="' + newObject.id +
                    '" class="history-comment form-control form-control-sm" value="' +
                    String(newObject.comment || '').replace(/"/g, '&quot;') + '"></td>'
                )
                $tr.append('<td>' + phrase + '</td>')
                $tr.append('<td>' + region + '</td>')
                $tr.append('<td>' + (newObject.main_link || '') + '</td>')
                $tr.append('<td data-order="' + (newObject.position != null ? newObject.position : '') + '">' + position + '</td>')
                $tr.append(buildHistoryMetricCells(newObject, avg))

                $tr.append(
                    '<td><div class="d-flex justify-content-center">' +
                    '  <div class="__helper-link ui_tooltip_w">' +
                    '    <div class="custom-control custom-switch custom-switch-off-danger custom-switch-on-success">' +
                    '      <input ' + checked + ' onclick="changeState($(this))" type="checkbox"' +
                    '             class="custom-control-input switch" id="calculate-project-' + newObject.id + '" name="noIndex"' +
                    '             data-target="' + newObject.id + '">' +
                    '      <label class="custom-control-label" for="calculate-project-' + newObject.id + '"></label>' +
                    '    </div>' +
                    '  </div>' +
                    '</div></td>'
                )

                table.row.add($tr[0]).draw(false)
                getHistoryInfo()
            }

        },
    });
}

function customFilters(tableID, table, prefix = '', index = 0) {
    $.fn.dataTable.ext.search.push(function (settings, data) {
        let target = String(data[index]);
        return isDateValid(target, settings, tableID, prefix)
    });
    $('#dateMin' + prefix).change(function () {
        table.draw();
    });
    $('#dateMax' + prefix).change(function () {
        table.draw();
    });

    $.fn.dataTable.ext.search.push(function (settings, data) {
        let phraseSearch = String($('#projectComment' + prefix).val()).toLowerCase();
        let target = String(data[index + 1]).toLowerCase();
        return isIncludes(target, phraseSearch, settings, tableID)
    });
    $('#projectComment' + prefix).keyup(function () {
        table.draw();
    });

    $.fn.dataTable.ext.search.push(function (settings, data) {
        let phraseSearch = String($('#phraseSearch' + prefix).val()).toLowerCase();
        let target = String(data[index + 2]).toLowerCase();
        return isIncludes(target, phraseSearch, settings, tableID)
    });
    $('#phraseSearch' + prefix).keyup(function () {
        table.draw();
    });

    $.fn.dataTable.ext.search.push(function (settings, data) {
        let regionSearch = String($('#regionSearch' + prefix).val()).toLowerCase();
        let target = String(data[index + 3]).toLowerCase();
        return isIncludes(target, regionSearch, settings, tableID)
    });
    $('#regionSearch' + prefix).keyup(function () {
        table.draw();
    });

    $.fn.dataTable.ext.search.push(function (settings, data) {
        let mainPageSearch = String($('#mainPageSearch' + prefix).val()).toLowerCase();
        let target = String(data[index + 4]).toLowerCase();
        return isIncludes(target, mainPageSearch, settings, tableID)
    });
    $('#mainPageSearch' + prefix).keyup(function () {
        table.draw();
    });

    $.fn.dataTable.ext.search.push(function (settings, data) {
        let maxPosition = parseFloat($('#maxPosition' + prefix).val());
        let minPosition = parseFloat($('#minPosition' + prefix).val());
        let target = parseFloat(data[index + 5]);
        return isValidate(minPosition, maxPosition, target, settings, tableID)
    });
    $('#minPosition' + prefix + ', #maxPosition' + prefix).keyup(function () {
        table.draw();
    });

    $.fn.dataTable.ext.search.push(function (settings, data) {
        let maxPoints = parseFloat($('#maxPoints' + prefix).val());
        let minPoints = parseFloat($('#minPoints' + prefix).val());
        let target = parseFloat(data[index + 6]);
        return isValidate(minPoints, maxPoints, target, settings, tableID)
    });
    $('#minPoints' + prefix + ', #maxPoints' + prefix).keyup(function () {
        table.draw();
    });

    $.fn.dataTable.ext.search.push(function (settings, data) {
        let maxAVGPoints = parseFloat($('#maxAVGPoints' + prefix).val());
        let minAvgPoints = parseFloat($('#minAVGPoints' + prefix).val());
        let target = parseFloat(data[index + 1 + 6]);
        return isValidate(minAvgPoints, maxAVGPoints, target, settings, tableID)
    });
    $('#minAVGPoints' + prefix + ', #maxAVGPoints' + prefix).keyup(function () {
        table.draw();
    });

    $.fn.dataTable.ext.search.push(function (settings, data) {
        let maxCoverage = parseFloat($('#maxCoverage' + prefix).val());
        let minCoverage = parseFloat($('#minCoverage' + prefix).val());
        let target = parseFloat(data[index + 7]);
        return isValidate(minCoverage, maxCoverage, target, settings, tableID)
    });
    $('#minCoverage' + prefix + ', #maxCoverage' + prefix).keyup(function () {
        table.draw();
    });

    $.fn.dataTable.ext.search.push(function (settings, data) {
        let maxCoverageTf = parseFloat($('#maxCoverageTf' + prefix).val());
        let minCoverageTf = parseFloat($('#minCoverageTf' + prefix).val());
        let target = parseFloat(data[index + 8]);
        return isValidate(minCoverageTf, maxCoverageTf, target, settings, tableID)
    });
    $('#minCoverageTf' + prefix + ', #maxCoverageTf' + prefix).keyup(function () {
        table.draw();
    });

    $.fn.dataTable.ext.search.push(function (settings, data) {
        let maxWidth = parseFloat($('#maxWidth' + prefix).val());
        let minWidth = parseFloat($('#minWidth' + prefix).val());
        let target = parseFloat(data[index + 9]);
        return isValidate(minWidth, maxWidth, target, settings, tableID)
    });
    $('#minWidth' + prefix + ', #maxWidth' + prefix).keyup(function () {
        table.draw();
    });

    $.fn.dataTable.ext.search.push(function (settings, data) {
        let maxDensity = parseFloat($('#maxDensity' + prefix).val());
        let minDensity = parseFloat($('#minDensity' + prefix).val());
        let target = parseFloat(data[index + 10]);
        return isValidate(minDensity, maxDensity, target, settings, tableID)
    });
    $('#minDensity' + prefix + ', #maxDensity' + prefix).keyup(function () {
        table.draw();
    });
}

function customHistoryFilters(tableID, table, prefix = '') {
    // Колонки: 0 дата, 1 действия, 2 комментарий, 3 фраза, …
    $.fn.dataTable.ext.search.push(function (settings, data) {
        let target = String(data[0]);
        return isDateValid(target, settings, tableID, prefix)
    });
    $('#dateMin' + prefix).change(function () {
        table.draw();
    });
    $('#dateMax' + prefix).change(function () {
        table.draw();
    });

    $.fn.dataTable.ext.search.push(function (settings, data) {
        let phraseSearch = String($('#projectComment' + prefix).val()).toLowerCase();
        let target = String(data[2]).toLowerCase();
        return isIncludes(target, phraseSearch, settings, tableID)
    });
    $('#projectComment' + prefix).keyup(function () {
        table.draw();
    });

    $.fn.dataTable.ext.search.push(function (settings, data) {
        let phraseSearch = String($('#phraseSearch' + prefix).val()).toLowerCase();
        let target = String(data[3]).toLowerCase();
        return isIncludes(target, phraseSearch, settings, tableID)
    });
    $('#phraseSearch' + prefix).keyup(function () {
        table.draw();
    });

    $.fn.dataTable.ext.search.push(function (settings, data) {
        let regionSearch = String($('#regionSearch' + prefix).val()).toLowerCase();
        let target = String(data[4]).toLowerCase();
        return isIncludes(target, regionSearch, settings, tableID)
    });
    $('#regionSearch' + prefix).keyup(function () {
        table.draw();
    });

    $.fn.dataTable.ext.search.push(function (settings, data) {
        let mainPageSearch = String($('#mainPageSearch' + prefix).val()).toLowerCase();
        let target = String(data[5]).toLowerCase();
        return isIncludes(target, mainPageSearch, settings, tableID)
    });
    $('#mainPageSearch' + prefix).keyup(function () {
        table.draw();
    });

    $.fn.dataTable.ext.search.push(function (settings, data) {
        let maxPosition = parseFloat($('#maxPosition' + prefix).val());
        let minPosition = parseFloat($('#minPosition' + prefix).val());
        let target = parseFloat(data[6]);
        return isValidate(minPosition, maxPosition, target, settings, tableID)
    });
    $('#minPosition' + prefix + ', #maxPosition' + prefix).keyup(function () {
        table.draw();
    });

    $.fn.dataTable.ext.search.push(function (settings, data) {
        let maxPoints = parseFloat($('#maxPoints' + prefix).val());
        let minPoints = parseFloat($('#minPoints' + prefix).val());
        let target = parseFloat(data[7]);
        return isValidate(minPoints, maxPoints, target, settings, tableID)
    });
    $('#minPoints' + prefix + ', #maxPoints' + prefix).keyup(function () {
        table.draw();
    });

    $.fn.dataTable.ext.search.push(function (settings, data) {
        let maxCoverage = parseFloat($('#maxCoverage' + prefix).val());
        let minCoverage = parseFloat($('#minCoverage' + prefix).val());
        let target = parseFloat(data[8]);
        return isValidate(minCoverage, maxCoverage, target, settings, tableID)
    });
    $('#minCoverage' + prefix + ', #maxCoverage' + prefix).keyup(function () {
        table.draw();
    });

    $.fn.dataTable.ext.search.push(function (settings, data) {
        let maxCoverageTf = parseFloat($('#maxCoverageTf' + prefix).val());
        let minCoverageTf = parseFloat($('#minCoverageTf' + prefix).val());
        let target = parseFloat(data[9]);
        return isValidate(minCoverageTf, maxCoverageTf, target, settings, tableID)
    });
    $('#minCoverageTf' + prefix + ', #maxCoverageTf' + prefix).keyup(function () {
        table.draw();
    });

    $.fn.dataTable.ext.search.push(function (settings, data) {
        let maxWidth = parseFloat($('#maxWidth' + prefix).val());
        let minWidth = parseFloat($('#minWidth' + prefix).val());
        let target = parseFloat(data[10]);
        return isValidate(minWidth, maxWidth, target, settings, tableID)
    });
    $('#minWidth' + prefix + ', #maxWidth' + prefix).keyup(function () {
        table.draw();
    });

    $.fn.dataTable.ext.search.push(function (settings, data) {
        let maxDensity = parseFloat($('#maxDensity' + prefix).val());
        let minDensity = parseFloat($('#minDensity' + prefix).val());
        let target = parseFloat(data[11]);
        return isValidate(minDensity, maxDensity, target, settings, tableID)
    });
    $('#minDensity' + prefix + ', #maxDensity' + prefix).keyup(function () {
        table.draw();
    });
}

function getColor(result, ideal) {
    let percent = ideal / 100

    let difference = 100 - (result / percent)

    if (difference >= 0 && difference < 15 || difference < 0) {
        return 'rgba(78,183,103,0.5)';
    }

    if (difference >= 15 && difference <= 20) {
        return 'rgba(245,226,170,0.5)';
    }

    return 'rgba(220,53,69,0.5)';
}
window.getColor = getColor

function getTextResult(result, ideal) {
    if (typeof window.relevanceHistoryGetTextResult === 'function') {
        return window.relevanceHistoryGetTextResult(result, ideal)
    }
    var tip = 'Факт: ' + result + '. Рекомендуется: ' + ideal
    return '<span class="ra-hist-metric" data-ra-tip="' + tip + '"><b>' + result + '</b> / ' + ideal + '</span>'
}

/**
 * Ячейки баллов/охвата с заливкой — как при полной перезагрузке get-stories.
 */
function buildHistoryMetricCells(values, avg) {
    let points = values.points
    let coverage = values.coverage
    let coverageTf = values.coverage_tf
    let width = values.width
    let density = values.density

    if (!avg || avg.points == null || typeof getColor !== 'function') {
        return '' +
            '<td>' + (points != null ? points : '') + '</td>' +
            '<td data-order="' + (coverage != null ? coverage : '') + '">' + (coverage != null ? coverage : '') + '</td>' +
            '<td data-order="' + (coverageTf != null ? coverageTf : '') + '">' + (coverageTf != null ? coverageTf : '') + '</td>' +
            '<td data-order="' + (width != null ? width : '') + '">' + (width != null ? width : '') + '</td>' +
            '<td data-order="' + (density != null ? density : '') + '">' + (density != null ? density : '') + '</td>'
    }

    function cell(value, ideal) {
        let v = value != null ? value : ''
        let rounded = Math.round(ideal)
        return '<td data-order="' + v + '" style="background: ' + getColor(value, rounded) + '">' +
            getTextResult(value, rounded) + '</td>'
    }

    return '' +
        cell(points, avg.points) +
        cell(coverage, avg.coverage) +
        cell(coverageTf, avg.coverageTf) +
        cell(width, avg.width) +
        cell(density, avg.densityPercent)
}
