function getSuccessMessage(message, time = 3000) {
    var $toast = $('#unigram-copy-toast')
    if (!$toast.length) {
        $toast = $('.toast-top-right.success-message.unigram-copy-success').first()
    }
    if (!$toast.length) {
        $toast = $('.toast-top-right.success-message').not('.lock-word, .lock-word-removed').first()
    }
    if (!$toast.length) {
        return
    }

    var $message = $toast.find('.toast-message, .unigram-copy-success__text').first()
    if (!$message.length) {
        $message = $toast
    }

    $message.text(message)
    clearTimeout(window.__unigramCopyToastTimer)
    $toast.stop(true, true).addClass('is-visible').show(200)
    window.__unigramCopyToastTimer = setTimeout(function () {
        $toast.removeClass('is-visible').hide(200)
    }, time)
}

function copyUnigramWord(text, successMessage) {
    function done() {
        getSuccessMessage(successMessage || 'Успешно скопированно')
    }

    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(done).catch(function () {
            fallbackCopyUnigramWord(text, done)
        })
        return
    }

    fallbackCopyUnigramWord(text, done)
}

function fallbackCopyUnigramWord(text, done) {
    var area = document.createElement('textarea')
    area.value = text
    area.style.position = 'fixed'
    area.style.left = '-9999px'
    document.body.appendChild(area)
    area.select()
    try {
        document.execCommand('copy')
        done()
    } catch (e) {}
    document.body.removeChild(area)
}

function bindUnigramCopyHandler(successMessage) {
    $(document)
        .off('click.unigramCopy', '#unigram .copy-icon, #unigram .copy-text-in-buffer')
        .on('click.unigramCopy', '#unigram .copy-icon, #unigram .copy-text-in-buffer', function (e) {
            e.preventDefault()
            e.stopPropagation()

            var text = $(this).attr('data-target')
            if (!text) {
                text = $(this).closest('td').find('.unigram-word-text').text().trim()
            }
            if (!text) {
                return
            }

            copyUnigramWord(text, successMessage)
        })
}

function decorateUnigramWordCells() {
    var tipCopy = (window.__raActionTips && window.__raActionTips.copy) || 'Копировать'
    $('#unigram tbody td:nth-child(2)').each(function () {
        var cell = $(this)
        if (cell.find('.unigram-word-text').length) {
            return
        }

        var lockBlock = cell.find('.lock-block').detach()
        var wordText = cell.clone().children('.lock-block').remove().end().text().trim().replace(/\s+/g, ' ')

        cell.empty().append(
            $('<div class="unigram-word-cell"></div>').append(
                $('<span class="unigram-word-text"></span>').text(wordText),
                ' ',
                $('<i class="fa fa-copy copy-icon" role="button" tabindex="0"></i>').attr('data-ra-tip', tipCopy)
            )
        )

        if (lockBlock.length) {
            cell.append(lockBlock)
        }
    })
    if (typeof window.initRelevanceActionTips === 'function') {
        window.initRelevanceActionTips(document.getElementById('unigram_wrapper') || document)
    }
}

function formatHybridMetric(number) {
    let value = parseFloat(number)
    if (isNaN(value) || value === 0) {
        return '0'
    }

    if (value >= 0.01) {
        return (Math.round(value * 10000) / 10000).toString()
    }

    return crop(value)
}

function renderUnigramTable(unigramTable, count, words, resultId = 0, searchPassages = false, onReady = null) {
    if (typeof searchPassages === 'function') {
        onReady = searchPassages
        searchPassages = false
    }

    window.__raActionTips = Object.assign({}, window.__raActionTips || {}, {
        copy: (words && words.copy) || 'Копировать',
        lock: (words && words.lockWord) || 'Добавить слово в игнор',
        unlock: (words && words.unlockWord) || 'Убрать слово из игнора',
        expand: (words && words.expandForms) || 'Развернуть словоформы',
        collapse: (words && words.collapseForms) || 'Свернуть',
        childWords: (words && words.childWords) || 'Главные слова',
        missingWords: (words && words.missingWords) || 'Упущенные слова',
    })

    if (searchPassages) {
        $('#unigram > thead > tr:nth-child(2) > th:nth-child(14)').after(
            "<th class='passages-elem'>Среднее кол-во повторений в пассажах</th>" +
            "<th class='passages-elem'>Количество повторений в пассажах</th>"
        )

        $('#unigram > thead > tr:nth-child(1) > th:nth-child(14)').after(
            "<th class='passages-elem unigram-filter-cell'>" +
            "    <div class='unigram-filter-box'>" +
            "        <input class='w-100' type='number' name='minAVGPassages' id='minAVGPassages' placeholder='мин'>" +
            "        <input class='w-100' type='number' name='maxAVGPassages' id='maxAVGPassages' placeholder='макс'>" +
            "    </div>" +
            "</th>" +
            "<th class='passages-elem unigram-filter-cell'>" +
            "    <div class='unigram-filter-box'>" +
            "        <input class='w-100' type='number' name='minPassages' id='minPassages' placeholder='мин'>" +
            "        <input class='w-100' type='number' name='maxPassages' id='maxPassages' placeholder='макс'>" +
            "    </div>" +
            "</th>"
        )
    } else {
        $('.passages-elem').remove()
    }

    sessionStorage.setItem('searchPassages', (searchPassages).toString())
    sessionStorage.setItem('childTableRows', JSON.stringify(unigramTable))

    $('.pb-3.unigram').show()
    let tBody = $('#unigramTBody')
    let keys = Object.keys(unigramTable)
    let rowIndex = 0
    let batchSize = 50
    let htmlParts = []

    if ($.fn.DataTable.fnIsDataTable($('#unigram'))) {
        $('#unigram').dataTable().fnDestroy()
    }

    tBody.empty()

    function buildRows() {
        let end = Math.min(rowIndex + batchSize, keys.length)
        for (; rowIndex < end; rowIndex++) {
            htmlParts.push(renderMainTr(keys[rowIndex], unigramTable[keys[rowIndex]], searchPassages))
        }

        if (rowIndex < keys.length) {
            setTimeout(buildRows, 0)
            return
        }

        tBody.html(htmlParts.join(''))
        initUnigramDataTable(count, words, resultId, searchPassages, onReady)
    }

    buildRows()
}

function unigramExportButtons(words) {
    if (typeof window.relevanceDtExportButtons === 'function') {
        return window.relevanceDtExportButtons(words, {
            // колонка «+» / expand без данных
            columns: ':not(.unigram-expand-col)',
        });
    }

    return [
        {extend: 'copy', text: words.copy || 'Копировать'},
        {extend: 'csv', text: words.csv || 'CSV'},
        {extend: 'excel', text: words.excel || 'Excel'},
    ];
}

function syncUnigramHeaderSticky() {
    var $filterRow = $('#unigram thead .unigram-thead__filters')
    var $dividerRow = $('#unigram thead .unigram-thead__divider')
    var $scroll = $('#unigram_wrapper .relevance-tlp-table-scroll')

    if (!$filterRow.length || !$scroll.length) {
        return
    }

    var filterHeight = $filterRow.outerHeight() || 0
    var dividerHeight = $dividerRow.length ? ($dividerRow.outerHeight() || 0) : 0

    $scroll.css('--unigram-filter-row-height', filterHeight + 'px')
    $scroll.css('--unigram-titles-row-top', (filterHeight + dividerHeight) + 'px')
}

function initUnigramDataTable(count, words, resultId, searchPassages, onReady) {
    var table = $('#unigram').DataTable({
        deferRender: true,
        order: [[2, 'desc']],
        pageLength: count,
        searching: true,
        dom: 'lBfrtip',
        orderCellsTop: false,
        columnDefs: [
            {orderable: false, targets: 0},
        ],
        buttons: unigramExportButtons(words),
        language: {
            paginate: {
                first: '«',
                last: '»',
                next: '»',
                previous: '«'
            },
        },
        oLanguage: {
            sSearch: words.search + ':',
            sLengthMenu: words.show + ' _MENU_ ' + words.records,
            sEmptyTable: words.noRecords,
            sInfo: words.showing + ' ' + words.from + '  _START_ ' + words.to + ' _END_ ' + words.of + ' _TOTAL_ ' + words.entries,
        },
        drawCallback: function () {
            decorateUnigramWordCells()
            syncUnigramHeaderSticky()
        },
        initComplete: function () {
            decorateUnigramWordCells()
            syncUnigramHeaderSticky()
            window.requestAnimationFrame(function () {
                syncUnigramHeaderSticky()
            })
            setTimeout(syncUnigramHeaderSticky, 100)
        }
    })

    bindUnigramCopyHandler(words.successCopied || words.copySuccess || 'Успешно скопированно')

    $(window).on('resize.unigramTable', function () {
        if ($.fn.DataTable.isDataTable('#unigram')) {
            table.columns.adjust()
            syncUnigramHeaderSticky()
        }
    })

    $.each($('.dt-buttons'), function (key, value) {
        if (key === 1) {
            $(this).append(
                "<a class='btn btn-secondary click_tracking' data-click='Child Words' href='/show-child-words/" + resultId + "' target='_blank'" +
                (typeof window.relevanceActionTipAttr === 'function' ? window.relevanceActionTipAttr((words && words.childWords) || 'Главные слова') : '') + ">" +
                (words.childWords || 'Главные слова') +
                "</a>"
            )
            if (resultId !== 0) {
                $(this).append(
                    "<a class='btn btn-secondary mr-1 click_tracking' data-click='Missing Words' href='/show-missing-words/" + resultId + "' target='_blank'" +
                    (typeof window.relevanceActionTipAttr === 'function' ? window.relevanceActionTipAttr((words && words.missingWords) || 'Упущенные слова') : '') + ">" +
                    (words.missingWords || 'Упущенные слова') +
                    "</a>"
                )
            }
        }
    })
    if (typeof window.initRelevanceActionTips === 'function') {
        window.initRelevanceActionTips(document.getElementById('unigram_wrapper') || document)
    }

    bindUnigramFilters(table, searchPassages)
    ensureUnigramTableScrollWrap()

    if (typeof onReady === 'function') {
        onReady()
    }
}

function ensureUnigramTableScrollWrap() {
    var $table = $('#unigram')
    if (!$table.length || $table.parent().hasClass('relevance-tlp-table-scroll')) {
        return
    }

    $table.wrap('<div class="relevance-tlp-table-scroll"></div>')
}

function bindUnigramFilters(table, searchPassages) {
    function isUnigram(min, max, target, settings) {
        if (settings.nTable.id !== 'unigram') {
            return true
        }

        return (isNaN(min) && isNaN(max)) ||
            (isNaN(min) && target <= max) ||
            (min <= target && isNaN(max)) ||
            (min <= target && target <= max)
    }

    function bindFilter(selectors, colIndex) {
        $.fn.dataTable.ext.search.push(function (settings, data) {
            var min = parseFloat($(selectors.min).val())
            var max = parseFloat($(selectors.max).val())
            var value = parseFloat(data[colIndex])
            return isUnigram(min, max, value, settings)
        })

        $(selectors.min + ', ' + selectors.max).off('keyup.unigramFilter').on('keyup.unigramFilter', function () {
            table.draw()
            $.each($('[generated-child=true]'), function () {
                $(this).attr('generated-child', false)
            })
        })
    }

    bindFilter({min: '#minTfidfTop', max: '#maxTfidfTop'}, 2)
    bindFilter({min: '#minTfidfSite', max: '#maxTfidfSite'}, 3)
    bindFilter({min: '#minBm25Top', max: '#maxBm25Top'}, 4)
    bindFilter({min: '#minBm25Site', max: '#maxBm25Site'}, 5)
    bindFilter({min: '#minInter', max: '#maxInter'}, 6)
    bindFilter({min: '#minReSpam', max: '#maxReSpam'}, 7)
    bindFilter({min: '#minAVG', max: '#maxAVG'}, 8)
    bindFilter({min: '#minAVGText', max: '#maxAVGText'}, 9)
    bindFilter({min: '#minInYourPage', max: '#maxInYourPage'}, 10)
    bindFilter({min: '#minTextIYP', max: '#maxTextIYP'}, 11)
    bindFilter({min: '#minAVGLink', max: '#maxAVGLink'}, 12)
    bindFilter({min: '#minLinkIYP', max: '#maxLinkIYP'}, 13)

    if (searchPassages) {
        bindFilter({min: '#minAVGPassages', max: '#maxAVGPassages'}, 14)
        bindFilter({min: '#minPassages', max: '#maxPassages'}, 15)
    }
}

function hybridMetrics(stats) {
    return {
        tfidfTop: formatHybridMetric(stats['tfidfTop'] ?? 0),
        tfidfSite: formatHybridMetric(stats['tfidfSite'] ?? 0),
        bm25Top: formatHybridMetric(stats['bm25Top'] ?? 0),
        bm25Site: formatHybridMetric(stats['bm25Site'] ?? 0),
        tfidfTopText: formatHybridMetric(stats['tfidfTopText'] ?? 0),
        tfidfTopLink: formatHybridMetric(stats['tfidfTopLink'] ?? 0),
        tfidfSiteText: formatHybridMetric(stats['tfidfSiteText'] ?? 0),
        tfidfSiteLink: formatHybridMetric(stats['tfidfSiteLink'] ?? 0),
    }
}

function hybridTfidfTip(metrics, kind) {
    let labels = window.relevanceHybridLabels || {}
    let textLabel = labels.text || 'текст'
    let linkLabel = labels.links || 'ссылки'

    if (kind === 'top') {
        return textLabel + ': ' + metrics.tfidfTopText + ', ' + linkLabel + ': ' + metrics.tfidfTopLink
    }

    return textLabel + ': ' + metrics.tfidfSiteText + ', ' + linkLabel + ': ' + metrics.tfidfSiteLink
}

function hybridTipCell(value, order, tip, extraClass) {
    let className = extraClass ? " class='" + extraClass + "'" : ''
    let titleAttr = tip ? " title='" + String(tip).replace(/'/g, '&#39;') + "'" : ''

    return "<td" + className + " data-order='" + order + "'" + titleAttr + ">" + value + "</td>"
}

function renderMainTr(key, wordWorm, searchPassages) {
    let links = ''
    $.each(wordWorm['total']['occurrences'], function (elem, value) {
        let url = new URL(elem)
        links += "<a href='" + elem + "' target='_blank'>" + url.host + "</a>(" + value + ")<br>"
    })

    let metrics = hybridMetrics(wordWorm['total'])
    let className = wordWorm['total']['danger'] ? 'bg-warning-elem' : ''
    let numberOccurrences = crop(wordWorm['total']['numberOccurrences'])
    let reSpam = crop(wordWorm['total']['reSpam'])
    let avgInTotalCompetitors = wordWorm['total']['avgInTotalCompetitors']
    let totalRepeatMainPage = wordWorm['total']['totalRepeatMainPage']
    let avgInText = wordWorm['total']['avgInText']
    let repeatInTextMainPage = wordWorm['total']['repeatInTextMainPage']
    let avgInLink = wordWorm['total']['avgInLink']
    let repeatInLinkMainPage = wordWorm['total']['repeatInLinkMainPage']
    let repeatInPassagesMainPage = wordWorm['total']['repeatInPassagesMainPage'] === undefined ? 0 : wordWorm['total']['repeatInPassagesMainPage']
    let avgInPassages = wordWorm['total']['avgInPassages'] === undefined ? 0 : wordWorm['total']['avgInPassages']
    let repeatInTextMainPageWarning = repeatInTextMainPage == 0 ? "class='bg-warning-elem'" : ''
    let repeatInLinkMainPageWarning = repeatInLinkMainPage == 0 ? " class='bg-warning-elem'" : ''
    let myPassagesWarning = repeatInPassagesMainPage == 0 ? 'bg-warning-elem' : ''
    let totalInMainPage = repeatInPassagesMainPage == 0 && repeatInLinkMainPage == 0 && repeatInTextMainPage == 0 ? " class='bg-warning-elem'" : ''
    let tip = window.__raActionTips || {}
    let tipAttr = window.relevanceActionTipAttr || function () { return '' }
    let lockBlock =
        "    <span class='lock-block'>" +
        "        <i class='fa fa-solid fa-plus-square-o lock' data-target='" + key + "' onclick='addWordInIgnore($(this))'" + tipAttr(tip.lock || 'Добавить слово в игнор') + "></i>" +
        "        <i class='fa fa-solid fa-minus-square-o unlock' data-target='" + key + "' style='display:none;' onclick='removeWordFromIgnored($(this))'" + tipAttr(tip.unlock || 'Убрать слово из игнора') + "></i>" +
        "    </span>"

    let newRow = "<tr class='render'>" +
        "   <td class='" + className + "' onclick='showWordWorms($(this))' data-target='" + key + "'" + tipAttr(tip.expand || 'Развернуть словоформы') + ">" +
        "      <i class='fa fa-plus'></i>" +
        "   </td>" +
        "   <td>" + key + lockBlock + "</td>" +
        hybridTipCell(
            metrics.tfidfTop,
            wordWorm['total']['tfidfTop'] ?? 0,
            hybridTfidfTip(metrics, 'top'),
            ''
        ) +
        hybridTipCell(
            metrics.tfidfSite,
            wordWorm['total']['tfidfSite'] ?? 0,
            hybridTfidfTip(metrics, 'site'),
            ''
        ) +
        "   <td data-order='" + (wordWorm['total']['bm25Top'] ?? 0) + "'>" + metrics.bm25Top + "</td>" +
        "   <td data-order='" + (wordWorm['total']['bm25Site'] ?? 0) + "'>" + metrics.bm25Site + "</td>" +
        "   <td data-order='" + numberOccurrences + "'>" + numberOccurrences + "" +
        "       <span class='__helper-link ui_tooltip_w'>" +
        "           <i class='fa fa-paperclip'></i>" +
        "           <span class='ui_tooltip __right' style='min-width: 250px; max-width: 450px;'>" +
        "               <span class='ui_tooltip_content'>" + links + "</span>" +
        "           </span>" +
        "       </span>" +
        "   </td>" +
        "   <td>" + reSpam + "</td>" +
        "   <td>" + avgInTotalCompetitors + "</td>" +
        "   <td " + totalInMainPage + ">" + totalRepeatMainPage + "</td>" +
        "   <td>" + avgInText + "</td>" +
        "   <td " + repeatInTextMainPageWarning + ">" + repeatInTextMainPage + "</td>" +
        "   <td>" + avgInLink + "</td>" +
        "   <td " + repeatInLinkMainPageWarning + ">" + repeatInLinkMainPage + "</td>"

    if (searchPassages) {
        newRow += "<td class='passages-elem'>" + avgInPassages + "</td>" +
            "   <td class='passages-elem " + myPassagesWarning + "'>" + repeatInPassagesMainPage + "</td>" +
            "</tr>"
    } else {
        newRow += "</tr>"
    }

    return newRow
}

function renderChildTr(elem, key, word, stats) {
    if (word === 'total') {
        return
    }

    let links = ''
    $.each(stats['occurrences'], function (elem, value) {
        let url = new URL(elem)
        links += "<a href='" + elem + "' target='_blank'>" + url.host + "</a>(" + value + ") <br>"
    })

    let metrics = hybridMetrics(stats)
    let numberOccurrences = crop(stats['numberOccurrences'])
    let reSpam = stats['reSpam']
    let avgInText = stats['avgInText']
    let avgInTotalCompetitors = stats['avgInTotalCompetitors']
    let totalRepeatMainPage = stats['totalRepeatMainPage']
    let repeatInTextMainPage = stats['repeatInTextMainPage']
    let avgInLink = stats['avgInLink']
    let repeatInLinkMainPage = stats['repeatInLinkMainPage']
    let textWarn = ''
    let linkWarn = ''
    let bgWarn = ''
    let bgTotalWarn = ''

    if (repeatInTextMainPage == 0) {
        textWarn = "class='bg-warning-elem'"
        bgWarn = "class='bg-warning-elem'"
    }

    if (repeatInLinkMainPage == 0) {
        linkWarn = "class='bg-warning-elem'"
        bgWarn = "class='bg-warning-elem'"
    }

    if (repeatInLinkMainPage == 0 && repeatInTextMainPage == 0) {
        bgTotalWarn = "class='bg-warning-elem'"
    }

    let avgPassages = stats['avgInPassages'] === undefined ? 0 : stats['avgInPassages']
    let repeatInPassagesMainPage = stats['repeatInPassagesMainPage'] === undefined ? 0 : stats['repeatInPassagesMainPage']
    let repeatInPassagesMainPageWarning = ''
    if (stats['repeatInPassagesMainPage'] == undefined || stats['repeatInPassagesMainPage'] == 0) {
        repeatInPassagesMainPageWarning = 'bg-warning-elem'
    }

    let tip = window.__raActionTips || {}
    let tipAttr = window.relevanceActionTipAttr || function () { return '' }
    let lockBlock =
        "    <span class='lock-block'>" +
        "        <i class='fa fa-solid fa-plus-square-o lock' data-target='" + word + "' onclick='addWordInIgnore($(this))'" + tipAttr(tip.lock || 'Добавить слово в игнор') + "></i>" +
        "        <i class='fa fa-solid fa-minus-square-o unlock' data-target='" + word + "' style='display:none;' onclick='removeWordFromIgnored($(this))'" + tipAttr(tip.unlock || 'Убрать слово из игнора') + "></i>" +
        "        <i class='fa fa-copy copy-text-in-buffer' data-target='" + word + "'" + tipAttr(tip.copy || 'Копировать') + "></i>" +
        "    </span>"

    let newChildRow = "<tr style='background-color: #f4f6f9;' data-order='" + key + "' class='render child-table-row'>" +
        "   <td " + bgWarn + " onclick='hideWordWorms($(this))' data-target='" + key + "'" + tipAttr(tip.collapse || 'Свернуть') + ">" +
        "   <i class='fa fa-minus'></i>" +
        "   </td>" +
        "   <td>" + word + lockBlock + "</td>" +
        hybridTipCell(
            metrics.tfidfTop,
            stats['tfidfTop'] ?? 0,
            hybridTfidfTip(metrics, 'top'),
            ''
        ) +
        hybridTipCell(
            metrics.tfidfSite,
            stats['tfidfSite'] ?? 0,
            hybridTfidfTip(metrics, 'site'),
            ''
        ) +
        "   <td data-order='" + (stats['bm25Top'] ?? 0) + "'>" + metrics.bm25Top + "</td>" +
        "   <td data-order='" + (stats['bm25Site'] ?? 0) + "'>" + metrics.bm25Site + "</td>" +
        "   <td>" + numberOccurrences + "" +
        "       <span class='__helper-link ui_tooltip_w'>" +
        "           <i class='fa fa-paperclip'></i>" +
        "           <span class='ui_tooltip __right' style='min-width: 250px; max-width: 450px;'>" +
        "               <span class='ui_tooltip_content'>" + links + "</span>" +
        "           </span>" +
        "       </span>" +
        "   </td>" +
        "   <td>" + reSpam + "</td>" +
        "   <td>" + avgInTotalCompetitors + "</td>" +
        "   <td " + bgTotalWarn + ">" + totalRepeatMainPage + "</td>" +
        "   <td>" + avgInText + "</td>" +
        "   <td " + textWarn + ">" + repeatInTextMainPage + "</td>" +
        "   <td>" + avgInLink + "</td>" +
        "   <td " + linkWarn + ">" + repeatInLinkMainPage + "</td>"

    if (sessionStorage.getItem('searchPassages') === 'true') {
        newChildRow +=
            "   <td class='passages-elem'>" + avgPassages + "</td>" +
            "   <td class='passages-elem " + repeatInPassagesMainPageWarning + "'>" + repeatInPassagesMainPage + "</td>" +
            "</tr>"
    } else {
        newChildRow += "</tr>"
    }

    elem.after(newChildRow)
}

$(document).off('click.unigramCopyLegacy', '.copy-text-in-buffer')

function showWordWorms(elem) {
    if (elem.attr('generated-child') === 'true') {
        hideWordWorms(elem)
    } else {
        let obj = JSON.parse(sessionStorage.childTableRows)
        let target = elem.attr('data-target')
        let parent = elem.parent()
        elem.attr('generated-child', true)
        $.each(reverseObj(obj[target]), function (word, stats) {
            renderChildTr(parent, target, word, stats)
        })
        elem.addClass('show-children')
        if (typeof window.initRelevanceActionTips === 'function') {
            window.initRelevanceActionTips(document.getElementById('unigram_wrapper') || document)
        }
    }
}

function hideWordWorms(elem) {
    let target = elem.attr('data-target')
    let objects = $('[data-target = ' + target + ']')
    $.each(objects, function () {
        if ($(this).attr('generated-child')) {
            $(this).attr('generated-child', false)
        }
    })
    $('tr[data-order=' + target + ']').remove()
}

$('th.sorting').click(() => {
    $('.child-table-row').remove()
    let objects = $('.show-children')
    $.each(objects, function () {
        $(this).attr('generated-child', false)
    })
})

function crop(number, decimal = false) {
    let position
    let string = number.toString()
    if (decimal) {
        return number.toFixed(1)
    } else {
        if (number[5] === '.') {
            position = 6
        } else {
            position = 7
        }
    }

    return string.substring(0, position)
}

$('#unigram > thead > tr > th').click(() => {
    $.each($('[generated-child=true]'), function () {
        $(this).attr('generated-child', false)
    })
})

$('#filters').click(() => {
    if ($('.pb-2.filters').is(':visible')) {
        $('.pb-2.filters').hide()
    } else {
        $('.pb-2.filters').show()
    }
})

function reverseObj(obj) {
    let newObj = {}
    let reverseObj = Object.keys(obj).reverse()
    reverseObj.forEach(function (i) {
        newObj[i] = obj[i]
    })
    return newObj
}

function addWordInIgnore(elem) {
    if ($('#switchMyListWords').is(':checked') === false) {
        $('#switchMyListWords').prop('checked', true)
        $('.form-group.required.list-words.mt-1').show(300)
    }
    let word = elem.attr('data-target')
    let textarea = $('.form-control.listWords')
    let toastr = $('.toast-top-right.success-message.lock-word')
    if (textarea.val().slice(-1) === "\n" || textarea.val().slice(-1) === '') {
        textarea.val(textarea.val() + word + "\n")
    } else {
        textarea.val(textarea.val() + "\n" + word + "\n")
    }
    toastr.find('.toast').addClass('show')
    toastr.show(300)
    setTimeout(() => {
        toastr.hide(300)
        toastr.find('.toast').removeClass('show')
    }, 3000)
    elem.hide()
    elem.parent().children().eq(1).show()
}

function removeWordFromIgnored(elem) {
    let word = elem.attr('data-target')
    let textarea = $('.form-control.listWords')
    let text = textarea.val()
    let result = ''
    $.each(text.split("\n"), function (key, value) {
        if (value !== word) {
            result += value + "\n"
        }
    })
    textarea.val(result.trim())
    let toastr = $('.toast-top-right.success-message.lock-word-removed')
    toastr.find('.toast').addClass('show')
    toastr.show(300)
    setTimeout(() => {
        toastr.hide(300)
        toastr.find('.toast').removeClass('show')
    }, 3000)
    elem.hide()
    elem.parent().children().eq(0).show()
}
