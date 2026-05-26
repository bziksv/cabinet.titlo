/**
 * Просмотр сохранённого результата кластеризации v2 (/show-cluster-result-v2/{id}).
 */
(function ($) {
  'use strict';

  const cfg = window.cabinetClusterResultV2 || {};
  let enhanceTimer = null;

  function csrf() {
    return $('meta[name="csrf-token"]').attr('content');
  }

  function toastSuccess(message) {
    if (typeof toastr !== 'undefined') {
      toastr.success(message);
      return;
    }
    $('.success-message .toast-success').show(200);
    $('.success-message .success-msg').text(message);
    setTimeout(function () {
      $('.success-message .toast-success').hide(300);
    }, 2500);
  }

  function initTooltips() {
    if (typeof bootstrap === 'undefined' || !bootstrap.Tooltip) {
      return;
    }
    $('#clusters-table [data-bs-toggle="tooltip"]').each(function () {
      bootstrap.Tooltip.getOrCreateInstance(this);
    });
  }

  function escapeHtml(text) {
    return String(text)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function mergeRelevanceColumn($table) {
    const $rows = $table.find('tbody tr');
    if ($rows.length < 2) {
      return;
    }

    const $cells = $rows.find('td[class*="relevance-"]');
    if ($cells.length < 2) {
      return;
    }

    const $first = $cells.first();
    $first.attr('rowspan', $rows.length).addClass('clv2-url-cell');
    $cells.slice(1).remove();
  }

  function rebuildActionPanel($td) {
    if ($td.find('.clv2-cluster-toolbar').length) {
      return;
    }

    const copyLabels = {
      'copy-cluster-phrases': 'Ключи',
      'copy-group': 'Группа',
      'copy-based': 'Баз.',
      'copy-phrase': 'Фраз.',
      'copy-target': 'Точн.',
    };

    const $toolbar = $('<div class="clv2-cluster-toolbar"></div>');
    const $copies = $('<div class="clv2-cluster-toolbar__copies" role="group" aria-label="Копировать"></div>');

    $td.find('p[class^="copy-"]').each(function () {
      const $p = $(this);
      const cls =
        ($p.attr('class') || '')
          .split(/\s+/)
          .find(function (c) {
            return c.indexOf('copy-') === 0;
          }) || '';
      const label = copyLabels[cls] || $.trim($p.text());
      const $btn = $('<button type="button" class="btn btn-sm btn-outline-secondary clv2-copy-chip"></button>');
      $btn.addClass(cls);
      if ($p.data('target') !== undefined) {
        $btn.attr('data-target', $p.data('target'));
      }
      if ($p.attr('data-click')) {
        $btn.addClass('click_tracking').attr('data-click', $p.attr('data-click'));
      }
      if ($p.attr('data-bs-toggle')) {
        $btn.attr('data-bs-toggle', $p.attr('data-bs-toggle'));
      }
      $btn.html('<i class="fa fa-copy" aria-hidden="true"></i> ' + label);
      $copies.append($btn);
    });

    if ($copies.children().length) {
      $toolbar.append($copies);
    }

    const $actions = $('<div class="clv2-cluster-toolbar__actions"></div>');
    const $competitors = $td.find('.all-competitors').first();
    if ($competitors.length) {
      $actions.append(
        $competitors
          .clone()
          .removeClass('btn-secondary col-6')
          .addClass('btn-outline-primary btn-sm w-100')
      );
    }
    const $saveUrl = $td.find('.save-all-urls').first();
    if ($saveUrl.length) {
      $actions.append(
        $saveUrl.clone().removeClass('btn-secondary col-6').addClass('btn-primary btn-sm w-100 mt-1')
      );
    }
    if ($actions.children().length) {
      $toolbar.append($actions);
    }

    const $collapse = $td.find('.collapse').first();
    if ($collapse.length) {
      $toolbar.append($collapse);
    }

    $td.empty().append($toolbar);
  }

  function rebindCopyHandlers() {
    if (typeof coloredPhrases === 'function') {
      coloredPhrases();
    }
    if (typeof copyBased === 'function') {
      copyBased();
    }
    if (typeof copyPhrases === 'function') {
      copyPhrases();
    }
    if (typeof copyTarget === 'function') {
      copyTarget();
    }
    if (typeof copyCluster === 'function') {
      copyCluster();
    }
    if (typeof copyGroup === 'function') {
      copyGroup();
    }
  }

  function polishClusterBlocks() {
    $('#clusters-table-tbody tr.render').each(function () {
      const $tr = $(this);
      const $table = $tr.find('table.render-table').first();
      if (!$table.length || $table.data('clv2Polished')) {
        return;
      }

      $tr.addClass('clv2-cluster-block');
      $table.data('clv2Polished', true);

      const $bodyRows = $table.find('tbody tr');
      const phraseCount = $bodyRows.length;
      let groupTitle = $.trim($bodyRows.first().find('td[class*="group-"]').text());
      if (!groupTitle) {
        groupTitle = $.trim($bodyRows.first().find('[class*="cluster-id-"]').first().text());
      }
      if (!groupTitle) {
        groupTitle = 'Кластер';
      }

      $table.find('thead th').each(function () {
        const t = $.trim($(this).text());
        if (t === 'Группа' || t.indexOf('Группа') === 0) {
          $(this).remove();
        }
      });
      $bodyRows.find('td[class*="group-"]').remove();

      mergeRelevanceColumn($table);

      if (!$table.prev('.clv2-cluster-head').length) {
        $table.before(
          '<div class="clv2-cluster-head">' +
            '<span class="clv2-cluster-head__title">' +
            escapeHtml(groupTitle) +
            '</span>' +
            '<span class="clv2-cluster-head__meta">' +
            phraseCount +
            ' фраз</span>' +
            '</div>'
        );
      }

      $bodyRows.each(function () {
        const $phraseCell = $(this).find('td').eq(2);
        const $tools = $phraseCell.find('.ml-1, .d-flex > div:last-child').first();
        if ($tools.length) {
          $tools.addClass('clv2-phrase-tools');
        }
      });

      rebuildActionPanel($tr.find('> td:last-child'));
    });

    $('#clusters-table-tbody tr.render').each(function () {
      $(this).find('> td:first-child').addClass('cabinet-cluster-v2-cluster-data');
      $(this).find('> td:last-child').addClass('cabinet-cluster-v2-cluster-actions');
    });
  }

  function enhanceResults() {
    const $rows = $('#clusters-table-tbody tr.render');
    if (!$rows.length) {
      return;
    }

    polishClusterBlocks();
    rebindCopyHandlers();

    $('#clusters-table .fa-copy.copy-full-urls').attr({
      'data-bs-toggle': 'tooltip',
      'data-bs-placement': 'top',
      title: cfg.i18n.copyUrls,
    });

    $('#clusters-table .fa-paperclip').attr({
      'data-bs-toggle': 'tooltip',
      'data-bs-placement': 'top',
      title: cfg.i18n.viewLinks,
    });

    const phraseCount = $('#rendered-clusters').text();
    const clusterCount = $rows.length;
    if (cfg.i18n.resultsMeta) {
      $('#clv2-results-meta').text(
        cfg.i18n.resultsMeta
          .replace(':clusters', clusterCount)
          .replace(':phrases', phraseCount || '0')
      );
    }

    $('#clv2-freq-zero-hint').remove();
    const $freqCells = $('#clusters-table td[class*="base-"], #clusters-table td[class*="phrase-"], #clusters-table td[class*="target-"]');
    let freqNonZero = 0;
    $freqCells.each(function () {
      const val = parseInt(String($(this).text()).replace(/\s/g, ''), 10);
      if (!isNaN(val) && val > 0) {
        freqNonZero += 1;
      }
    });
    if ($freqCells.length > 0 && freqNonZero === 0 && cfg.i18n.freqZeroHint) {
      $('#cabinet-cluster-v2-results .card-body').prepend(
        '<div id="clv2-freq-zero-hint" class="alert alert-warning py-2 px-3 mb-0 small">' +
          cfg.i18n.freqZeroHint +
          '</div>'
      );
    }

    $('#files-downloads').empty().hide();

    initTooltips();
  }

  function scheduleEnhance() {
    if (enhanceTimer) {
      clearInterval(enhanceTimer);
    }

    let attempts = 0;
    enhanceTimer = setInterval(function () {
      attempts += 1;
      const $rows = $('#clusters-table-tbody tr.render');
      const ready = $rows.length > 0;
      const timedOut = attempts > 40;

      if (!ready && !timedOut) {
        return;
      }

      clearInterval(enhanceTimer);
      enhanceTimer = null;

      if (ready) {
        enhanceResults();
      }

      $('#loader-block').addClass('d-none');
      $('#result-table').show();
    }, 250);
  }

  function bindRelevanceSave() {
    $(document).on('click', '.save-relevance-url', function () {
      const phrase = $(this).attr('data-order');
      const select = $('#' + phrase.replaceAll(' ', '-'));

      $.ajax({
        type: 'POST',
        url: cfg.routes.setRelevanceUrl,
        data: {
          _token: csrf(),
          phrase: phrase,
          url: select.val(),
          projectId: cfg.clusterId,
        },
        success: function () {
          select
            .parent()
            .parent()
            .html('<a href="' + select.val() + '" target="_blank" rel="noopener">' + select.val() + '</a>');
        },
      });
    });
  }

  function bindCopyPhrases() {
    $('#copyUsedPhrases').on('click', function () {
      const $object = $('#usedPhrases');

      function copyText() {
        $object.removeClass('visually-hidden').show();
        $object[0].select();
        document.execCommand('copy');
        $object.addClass('visually-hidden').hide();
        toastSuccess(cfg.i18n.copied);
      }

      if ($object.val()) {
        copyText();
        return;
      }

      $.ajax({
        type: 'POST',
        url: cfg.routes.downloadPhrases,
        dataType: 'json',
        data: { _token: csrf(), projectId: cfg.clusterId },
        success: function (response) {
          $object.val((response.phrases || []).join('\n'));
          copyText();
        },
      });
    });
  }

  function bindFastScan() {
    let oldBrutCount = 1;
    $('#brutForce').on('change', function () {
      if ($(this).is(':checked')) {
        $('#brutForceCount').val(oldBrutCount);
        $('#brutForceCountBlock').removeClass('d-none');
      } else {
        $('#brutForceCountBlock').addClass('d-none');
        oldBrutCount = $('#brutForceCount').val();
        $('#brutForceCount').val(1);
      }
    });

    $('#brutForceFast').on('click', function () {
      const req = cfg.request || {};
      $.ajax({
        type: 'POST',
        url: cfg.routes.fastScan,
        data: {
          _token: csrf(),
          count: req.count || 40,
          clusteringLevel: $('#clusteringLevelFast').val(),
          engineVersion: $('#engineVersionFast').val(),
          resultId: cfg.clusterId,
          brutForce: $('#brutForce').is(':checked'),
          mode: 'professional',
          brutForceCount: $('#brutForceCount').val(),
          reductionRatio: $('#reductionRatio').val(),
          ignoredDomains: $('#ignoredDomains').val(),
          gainFactor: $('#gainFactor').val(),
          ignoredWords: $('#ignoredWords').val(),
        },
        success: function () {
          window.location.reload();
        },
      });
    });
  }

  function init() {
    if (!cfg.clusterId || !cfg.result) {
      return;
    }

    bindRelevanceSave();
    bindCopyPhrases();
    bindFastScan();

    if (typeof renderResultTable_v2 === 'function') {
      renderResultTable_v2(cfg.result, cfg.clusterId);
      scheduleEnhance();
    }

    if (typeof saveAllUrls === 'function') {
      saveAllUrls(cfg.clusterId);
    }

    $(document).on('click', '.copy-full-urls', function () {
      if (typeof downloadSites === 'function') {
        downloadSites(cfg.clusterId, $(this).attr('data-action'), 'copy');
      }
    });

    $(document).on('mouseenter', '.fa.fa-paperclip', function () {
      if (typeof downloadSites === 'function') {
        downloadSites(cfg.clusterId, $(this).attr('data-action'), 'download');
      }
    });

    $(document).on('click', '.all-competitors', function () {
      if (typeof downloadAllCompetitors === 'function') {
        downloadAllCompetitors(cfg.clusterId, $(this).attr('data-action'));
      }
    });
  }

  $(init);
})(jQuery);
