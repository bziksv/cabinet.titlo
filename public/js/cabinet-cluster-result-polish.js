/**
 * Общая полировка таблицы кластеров (карточки, тулбар копирования).
 * Используется и на /cluster (результаты прогона), и на /show-cluster-result.
 */
(function (window, $) {
  'use strict';

  function escapeHtml(text) {
    return String(text == null ? '' : text)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function mergeRelevanceColumn($table) {
    var $rows = $table.find('tbody tr');
    if ($rows.length < 2) {
      return;
    }

    var $cells = $rows.find('td[class*="relevance-"]');
    if ($cells.length < 2) {
      return;
    }

    var $first = $cells.first();
    $first.attr('rowspan', $rows.length).addClass('clv2-url-cell');
    $cells.slice(1).remove();
  }

  function rebuildActionPanel($td) {
    if ($td.find('.clv2-cluster-toolbar').length) {
      return;
    }

    var copyLabels = {
      'copy-cluster-phrases': 'Ключи',
      'copy-group': 'Группа',
      'copy-based': 'Баз.',
      'copy-phrase': 'Фраз.',
      'copy-target': 'Точн.',
    };

    var $toolbar = $('<div class="clv2-cluster-toolbar"></div>');
    var $copies = $('<div class="clv2-cluster-toolbar__copies" role="group" aria-label="Копировать"></div>');

    $td.find('p[class^="copy-"]').each(function () {
      var $p = $(this);
      var cls =
        ($p.attr('class') || '')
          .split(/\s+/)
          .find(function (c) {
            return c.indexOf('copy-') === 0;
          }) || '';
      var label = copyLabels[cls] || $.trim($p.text());
      var $btn = $('<button type="button" class="btn btn-sm btn-outline-secondary clv2-copy-chip"></button>');
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

    var $actions = $('<div class="clv2-cluster-toolbar__actions"></div>');
    var $competitors = $td.find('.all-competitors').first();
    if ($competitors.length) {
      $actions.append(
        $competitors
          .clone()
          .removeClass('btn-secondary col-6')
          .addClass('btn-outline-primary btn-sm w-100')
      );
    }
    var $saveUrl = $td.find('.save-all-urls').first();
    if ($saveUrl.length) {
      $actions.append(
        $saveUrl.clone().removeClass('btn-secondary col-6').addClass('btn-primary btn-sm w-100 mt-1')
      );
    }
    if ($actions.children().length) {
      $toolbar.append($actions);
    }

    var $collapse = $td.find('.collapse').first();
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
      var $tr = $(this);
      var $table = $tr.find('table.render-table').first();
      if (!$table.length || $table.data('clv2Polished')) {
        return;
      }

      $tr.addClass('clv2-cluster-block');
      $table.data('clv2Polished', true);

      var $bodyRows = $table.find('tbody tr');
      var phraseCount = $bodyRows.length;
      var groupTitle = $.trim($bodyRows.first().find('td[class*="group-"]').text());
      if (!groupTitle) {
        groupTitle = $.trim($bodyRows.first().find('[class*="cluster-id-"]').first().text());
      }
      if (!groupTitle) {
        groupTitle = 'Кластер';
      }

      $table.find('thead th').each(function () {
        var t = $.trim($(this).text());
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
        var $phraseCell = $(this).find('td').eq(2);
        var $tools = $phraseCell.find('.ml-1, .d-flex > div:last-child').first();
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

  function applyPhraseActionTips(i18n) {
    i18n = i18n || {};
    var copyTip =
      i18n.copyUrlsTip ||
      'Скопировать URL из поисковой выдачи по этой фразе (ТОП). Это не поле «Релевантные url».';
    var viewTip = i18n.viewLinksTip || i18n.viewLinks || 'Показать URL из поисковой выдачи по этой фразе';

    $('#clusters-table .fa-copy.copy-full-urls').each(function () {
      var $icon = $(this);
      $icon
        .removeAttr('data-bs-toggle')
        .removeAttr('data-bs-placement')
        .removeAttr('title')
        .removeAttr('data-bs-original-title')
        .attr('aria-label', copyTip);
      if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
        var tip = bootstrap.Tooltip.getInstance(this);
        if (tip) {
          tip.dispose();
        }
      }
      if (!$icon.closest('.clv2-phrase-act').length) {
        $icon.wrap('<span class="clv2-phrase-act" tabindex="0"></span>');
      }
      var $wrap = $icon.closest('.clv2-phrase-act');
      var $pop = $wrap.children('.clv2-phrase-act__popover');
      if (!$pop.length) {
        $wrap.append($('<span class="clv2-phrase-act__popover" role="tooltip" />').text(copyTip));
      } else {
        $pop.text(copyTip);
      }
    });

    $('#clusters-table .fa-paperclip').each(function () {
      var $icon = $(this);
      $icon
        .removeAttr('data-bs-toggle')
        .removeAttr('data-bs-placement')
        .removeAttr('title')
        .removeAttr('data-bs-original-title')
        .attr('aria-label', viewTip);
      if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
        var tip = bootstrap.Tooltip.getInstance(this);
        if (tip) {
          tip.dispose();
        }
      }
    });
  }

  window.cabinetClusterResultPolish = {
    polishClusterBlocks: polishClusterBlocks,
    rebindCopyHandlers: rebindCopyHandlers,
    applyPhraseActionTips: applyPhraseActionTips,
    rebuildActionPanel: rebuildActionPanel,
  };
})(window, jQuery);
