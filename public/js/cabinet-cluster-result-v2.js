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

  function enhanceResults() {
    const $rows = $('#clusters-table-tbody tr.render');
    if (!$rows.length) {
      return;
    }

    const polish = window.cabinetClusterResultPolish;
    if (polish) {
      polish.polishClusterBlocks();
      polish.rebindCopyHandlers();
      polish.applyPhraseActionTips(cfg.i18n || {});
    }

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

    // Прогрев кэша ссылок — иначе копирование после AJAX часто не пишет в буфер.
    $(document).on('mouseenter', '.copy-full-urls', function () {
      if (typeof downloadSites === 'function') {
        downloadSites(cfg.clusterId, $(this).attr('data-action'), 'download');
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
