const isValidUrl = urlString => {
    let urlPattern = new RegExp('^(https?:\\/\\/)?' +
        '((([a-z\\d]([a-z\\d-]*[a-z\\d])*)\\.)+[a-z]{2,}|' +
        '((\\d{1,3}\\.){3}\\d{1,3}))' +
        '(\\:\\d+)?(\\/[-a-z\\d%_.~+]*)*' +
        '(\\?[;&a-z\\d%_.~+=-]*)?' +
        '(\\#[-a-z\\d_]*)?$', 'i');

    return !!urlPattern.test(urlString);
}

function getData(save = $('#save').val(), progressId = $('#progressId').val()) {

    if ($('#start-analyse').attr('data-target') === 'classic') {
        return {
            _token: $('meta[name="csrf-token"]').attr('content'),
            save: $('#save_classic').val(),
            region: $('#region_classic').val(),
            phrases: $('#phrases_classic').val(),
            domain: $('#domain-textarea_classic').val(),
            sendMessage: $('#sendMessage_classic').val(),
            comment: $('#comment-textarea_classic').val(),
            clusteringLevel: $('#clusteringLevel_classic').val(),
            searchBase: $('#searchBase_classic').is(':checked'),
            searchTarget: $('#searchTarget_classic').is(':checked'),
            searchPhrases: $('#searchPhrases_classic').is(':checked'),
            searchRelevance: $('#searchRelevance_classic').is(':checked'),
            mode: 'classic',
            progressId: progressId,
        };

    } else {
        return {
            _token: $('meta[name="csrf-token"]').attr('content'),
            save: save,
            progressId: progressId,
            region: $('#region').val(),
            count: $('#count').val(),
            phrases: $('#phrases').val(),
            clusteringLevel: $('#clusteringLevel').val(),
            searchBase: $('#searchBase').is(':checked'),
            searchPhrases: $('#searchPhrases').is(':checked'),
            searchTarget: $('#searchTarget').is(':checked'),
            domain: $('#domain-textarea').val(),
            comment: $('#comment-textarea').val(),
            sendMessage: $('#sendMessage').val(),
            brutForce: $('#brutForce').is(':checked'),
            searchRelevance: $('#searchRelevance').is(':checked'),
            searchEngine: $('#searchEngine').val(),
            mode: $('#start-analyse').attr('data-target'),
            brutForceCount: $('#brutForceCount').val(),
            reductionRatio: $('#reductionRatio').val(),
            ignoredWords: $('#ignoredWords').val(),
            ignoredDomains: $('#ignoredDomains').val(),
            gainFactor: $('#gainFactor').val(),
        };
    }
}

function setProgressBarStyles(count) {
    $('#progress-bar-state').html('Просканировано: ' + count + ' из ');
}

$('#save').on('change', function () {
    if ($('#save').val() === '1') {
        $('#extra-block').show()
    } else {
        $('#extra-block').hide()
    }
})

var __clusterSitesCache = Object.create(null);
var __clusterSitesInflight = Object.create(null);

function clusterSitesCacheKey(id, phrase) {
    return String(id) + '\0' + String(phrase);
}

function clusterSitesContentSpans(phrase) {
    return $('span.ui_tooltip_content').filter(function () {
        return $(this).attr('data-action') === phrase;
    });
}

function clusterSitesHost(site) {
    try {
        return new URL(site).host;
    } catch (e) {
        return site;
    }
}

function clusterSerpCaption() {
    var cfg = window.cabinetClusterResultV2 || window.cabinetClusterV2 || {};
    return (cfg.i18n && cfg.i18n.serpUrlsCaption) || 'Из поисковой выдачи по фразе';
}

function buildClusterSitesHtml(response) {
    const caption =
        '<div class="clv2-phrase-links-caption">' + clusterSerpCaption() + '</div>';
    let sitesBlock = '';
    if (response && 'mark' in response && response['mark'] !== 0) {
        $.each(response['mark'], function (site, boolean) {
            if (boolean) {
                sitesBlock +=
                    '<div class="text-muted">' +
                    '   <a href="' + site + '" target="_blank" rel="noopener noreferrer">' + clusterSitesHost(site) + '</a> (игнорируемый)' +
                    '</div>';
            } else {
                sitesBlock +=
                    '<div>' +
                    '   <a href="' + site + '" target="_blank" rel="noopener noreferrer">' + clusterSitesHost(site) + '</a>' +
                    '</div>';
            }
        });
    } else if (response && response['sites']) {
        $.each(response['sites'], function (key, site) {
            sitesBlock +=
                '<div>' +
                '   <a href="' + site + '" target="_blank" rel="noopener noreferrer">' + clusterSitesHost(site) + '</a>' +
                '</div>';
        });
    }
    if (!sitesBlock) {
        sitesBlock = '<div class="text-muted">Нет ссылок в выдаче</div>';
    }
    return caption + sitesBlock;
}

function applyClusterSitesCopy(response) {
    const urls = clusterSitesUrlsFromResponse(response);
    if (!urls.length) {
        if (typeof toastr !== 'undefined') {
            toastr.warning('Нет ссылок для копирования');
        } else if (typeof successCopiedMessage === 'function') {
            // не показываем «скопировано»
        }
        return;
    }
    $('#hiddenForCopy').val(urls.join('\n'));
    copyInBuffer(urls.join('\n'));
}

function clusterSitesUrlsFromResponse(response) {
    const urls = [];
    if (response && 'mark' in response && response['mark'] && response['mark'] !== 0 && typeof response['mark'] === 'object') {
        $.each(response['mark'], function (site, boolean) {
            if (!boolean && site) {
                urls.push(site);
            }
        });
        return urls;
    }
    const sites = (response && response['sites']) || [];
    if (Array.isArray(sites)) {
        return sites.filter(Boolean);
    }
    if (sites && typeof sites === 'object') {
        $.each(sites, function (key, site) {
            // sites как список значений или map url=>…
            if (typeof site === 'string' && /^https?:\/\//i.test(site)) {
                urls.push(site);
            } else if (typeof key === 'string' && /^https?:\/\//i.test(key)) {
                urls.push(key);
            } else if (typeof site === 'string' && site) {
                urls.push(site);
            }
        });
    }
    return urls;
}

function downloadSites(id, target, type) {
    const cacheKey = clusterSitesCacheKey(id, target);

    if (type === 'download') {
        const $els = clusterSitesContentSpans(target);

        if (cacheKey in __clusterSitesCache) {
            $els.html(__clusterSitesCache[cacheKey].html);
            $(document).trigger('clv2:phrase-links-ready', [target]);
            return;
        }

        const alreadyLoading = $els.find('.clv2-phrase-links-loading').length > 0;
        const alreadyFilled = $els.find('div').length > 0 && !alreadyLoading;
        if (alreadyFilled) {
            return;
        }
        if (!alreadyLoading) {
            $els.html(
                '<div class="clv2-phrase-links-caption">' +
                    clusterSerpCaption() +
                    '</div><div class="text-muted clv2-phrase-links-loading">Загрузка…</div>'
            );
            $(document).trigger('clv2:phrase-links-ready', [target]);
        }
        if (cacheKey in __clusterSitesInflight) {
            return;
        }

        __clusterSitesInflight[cacheKey] = $.ajax({
            type: "POST",
            url: "/download-cluster-sites",
            dataType: 'json',
            data: {
                _token: $('meta[name="csrf-token"]').attr('content'),
                phrase: target,
                projectId: id,
            },
        }).done(function (response) {
            const html = buildClusterSitesHtml(response);
            __clusterSitesCache[cacheKey] = { html: html, response: response };
            clusterSitesContentSpans(target).html(html);
            $(document).trigger('clv2:phrase-links-ready', [target]);
        }).fail(function () {
            clusterSitesContentSpans(target).html('<div class="text-muted">Не удалось загрузить ссылки</div>');
            $(document).trigger('clv2:phrase-links-ready', [target]);
        }).always(function () {
            delete __clusterSitesInflight[cacheKey];
        });
        return;
    }

    if (cacheKey in __clusterSitesCache) {
        applyClusterSitesCopy(__clusterSitesCache[cacheKey].response);
        return;
    }

    $.ajax({
        type: "POST",
        url: "/download-cluster-sites",
        dataType: 'json',
        data: {
            _token: $('meta[name="csrf-token"]').attr('content'),
            phrase: target,
            projectId: id,
        },
        success: function (response) {
            __clusterSitesCache[cacheKey] = {
                html: buildClusterSitesHtml(response),
                response: response,
            };
            applyClusterSitesCopy(response);
        },
        error: function () {
        }
    });
}

function downloadAllCompetitors(id, key) {
    if ($('#competitors-' + key.replaceAll(' ', '-')).html() === ' ') {
        $.ajax({
            type: "POST",
            url: "/download-cluster-competitors",
            dataType: 'json',
            data: {
                _token: $('meta[name="csrf-token"]').attr('content'),
                key: key,
                projectId: id,
            },
            success: function (response) {
                let resultBlock = ''
                $.each(response['competitors'], function (site, count) {
                    resultBlock +=
                        '<div>' +
                        '   <a href="' + site + '" target="_blank">' + new URL(site)['host'] + '</a> :' + count +
                        '</div>'
                })
                $('#competitors-' + key.replaceAll(' ', '-')).html(resultBlock)
            },
            error: function (response) {
            }
        });
    }
}

$(document).ready(function () {
    $('#searchRelevance').on('click', function () {
        isSearchRelevance()
    })
    $('#searchRelevance_classic').on('click', function () {
        isSearchRelevanceClassic()
    })

    isSearchRelevance()
    isSearchRelevanceClassic()

    if ($('#brutForce').is(':checked')) {
        $('.brut-force').show(300)
    } else {
        $('.brut-force').hide(300)
    }

    if ($('#brutForce_classic').is(':checked')) {
        $('.brut-force_classic').show(300)
    } else {
        $('.brut-force_classic').hide(300)
    }
})

function isSearchRelevance() {
    if ($('#searchRelevance').is(':checked')) {
        $('#searchEngineBlock').show(300)
    } else {
        $('#searchEngineBlock').hide(300)
    }
}

function isSearchRelevanceClassic() {
    if ($('#searchRelevance_classic').is(':checked')) {
        $('#searchEngineBlock_classic').show(300)
    } else {
        $('#searchEngineBlock_classic').hide(300)
    }
}

function saveAllUrls(id) {
    let button
    let trs
    $('.save-all-urls').unbind().on('click', function () {
        button = $(this)
        trs = button.parents().eq(3).children('td').eq(0).children('table').eq(0).children('tbody').children('tr')

        $('#relevanceUrls').html('')
        let links = []

        $.each(trs, function () {
            let td = $(this).children('td').eq(4)
            if (td.children('a').length === 0) {
                $.each(td.children('div').eq(0).children('select').eq(0).children('option'), function () {
                    links.push($(this).val())
                })
            }
        })
        let uniqueLinks = new Set([...links])

        for (let value of uniqueLinks) {
            $('#relevanceUrls').append($('<option>', {
                value: value,
                text: value
            }));
        }
    })

    $('#save-cluster-url-button').unbind().on('click', function () {
        let phrases = []
        $.each(trs, function (key, value) {
            let thisElem = $(this)
            if (thisElem.children('td').eq(4).children('a').length === 0) {
                if (thisElem.children('td').eq(2).attr('title') !== undefined) {
                    let phrase = thisElem.children('td').eq(2).attr('title')
                    phrase = phrase.replace('Ваша фраза "', '')
                    phrase = phrase.replace('Your phrase "', '')
                    phrase = phrase.replace('" была изменена', '')
                    phrase = phrase.replace('" has been changed', '')
                    phrases.push(phrase)
                } else {
                    phrases.push(thisElem.children('td').eq(2).children('div').eq(0).children('div').eq(0).html())
                }
                thisElem.children('td').eq(4).html('<a href="' + $('#relevanceUrls').val() + '" target="_blank">' + $('#relevanceUrls').val() + '</a>')
            }
        })

        $.ajax({
            type: "POST",
            url: "/set-cluster-relevance-urls",
            data: {
                _token: $('meta[name="csrf-token"]').attr('content'),
                phrases: phrases,
                url: $('#relevanceUrls').val(),
                projectId: id,
            },
            success: function () {

            },
            error: function (response) {
            }
        });
    })
}
