(function ($) {
  'use strict';

  const cfg = window.clusterEditV2;
  if (!cfg) return;

  let pendingMoveSelect = null;
  let dndBusy = false;
  let sidebarDropTarget = null;
  let dndMirror = null;
  let dndMirrorOffset = { x: 0, y: 0 };

  function cleanupDragVisuals($row) {
    if ($row && $row.length) {
      $row.removeClass('cl-edit-drag-source dragged').removeAttr('style');
    }
    if (dndMirror) {
      dndMirror.remove();
      dndMirror = null;
    }
    $('body').removeClass('dragging cl-edit-dragging');
  }

  function syncPlaceholderHeight(rowHeight) {
    $('tr.cl-edit-placeholder').css('height', rowHeight);
    $('tr.cl-edit-placeholder td').css('height', rowHeight);
  }

  function buildDragMirror($row, e) {
    const tableWidth = $row.closest('table').outerWidth();
    const rowHeight = $row.outerHeight();
    const offset = $row.offset();

    dndMirrorOffset = {
      x: e.pageX - offset.left,
      y: e.pageY - offset.top,
    };

    dndMirror = $('<div class="cl-edit-drag-mirror" aria-hidden="true"></div>');
    const $table = $('<table class="table table-sm mb-0 cabinet-cluster-edit-v2__table"></table>');
    const $tbody = $('<tbody></tbody>');
    $tbody.append($row.clone().removeClass('cl-edit-drag-source dragged').removeAttr('style'));
    $table.append($tbody);
    dndMirror.append($table);
    dndMirror.css({
      position: 'fixed',
      left: offset.left,
      top: offset.top,
      width: tableWidth,
      zIndex: 2000,
      pointerEvents: 'none',
    });
    $('body').append(dndMirror).addClass('cl-edit-dragging');

    window.setTimeout(function () {
      syncPlaceholderHeight(rowHeight);
    }, 0);

    return rowHeight;
  }

  function moveDragMirror(e) {
    if (!dndMirror) return;
    dndMirror.css({
      left: e.pageX - dndMirrorOffset.x,
      top: e.pageY - dndMirrorOffset.y,
    });
  }

  function sidebarDropAt(pageX, pageY) {
    if (pageX == null || pageY == null) return null;

    let restoreVisibility = false;
    if (dndMirror) {
      dndMirror.css('visibility', 'hidden');
      restoreVisibility = true;
    }

    const el = document.elementFromPoint(pageX, pageY);

    if (restoreVisibility && dndMirror) {
      dndMirror.css('visibility', '');
    }

    if (!el) return null;
    const $target = $(el).closest('.cl-edit-sidebar-drop-target');
    if (!$target.length) return null;
    return ($target.data('drop-group') || '').toString();
  }

  function clearSidebarDropHighlight() {
    $('.cl-edit-sidebar-drop-target').removeClass('is-drop-target');
    sidebarDropTarget = null;
  }

  function updateSidebarDropHover(e) {
    const group = sidebarDropAt(e.pageX, e.pageY);
    if (group === sidebarDropTarget) return;
    sidebarDropTarget = group;
    $('.cl-edit-sidebar-drop-target').removeClass('is-drop-target');
    if (group) {
      $('.cl-edit-sidebar-drop-target').filter(function () {
        return ($(this).data('drop-group') || '').toString() === group;
      }).addClass('is-drop-target');
    }
  }

  function isSameGroup(fromGroup, targetGroup, phrase) {
    const mainPhrase = mainPhraseForTarget(targetGroup, phrase);
    return fromGroup === mainPhrase || (targetGroup === '__single__' && fromGroup === phrase);
  }

  function runSidebarDrop($row, targetGroup) {
    const phrase = $row.data('phrase');
    const fromGroup = $row.data('from');
    if (isSameGroup(fromGroup, targetGroup, phrase) || dndBusy) return false;

    dndBusy = true;
    movePhrase(phrase, fromGroup, targetGroup, null, false).always(function () {
      dndBusy = false;
    });
    return true;
  }

  function toastOk(msg) {
    if (typeof toastr !== 'undefined') {
      toastr.success(msg || cfg.i18n.saved);
      return;
    }
    alert(msg || cfg.i18n.saved);
  }

  function toastErr(msg) {
    if (typeof toastr !== 'undefined') {
      toastr.error(msg || cfg.i18n.error);
      return;
    }
    alert(msg || cfg.i18n.error);
  }

  function post(url, data) {
    return $.ajax({
      type: 'POST',
      url: url,
      dataType: 'json',
      data: Object.assign({ _token: cfg.csrf }, data),
    });
  }

  function reloadSoon() {
    window.setTimeout(function () {
      window.location.reload();
    }, 400);
  }

  function resolveTargetGroup($tbody) {
    const g = ($tbody.data('target-group') || '').toString();
    if (g === '__single__') return '__single__';
    return g;
  }

  function mainPhraseForTarget(targetGroup, phrase) {
    if (targetGroup === '__single__') return phrase;
    return targetGroup;
  }

  function initMoveSelects() {
    if (!$.fn.select2) return;

    $('.cl-edit-move').each(function () {
      const $el = $(this);
      if ($el.hasClass('select2-hidden-accessible')) return;

      $el.select2({
        theme: 'bootstrap4',
        width: '100%',
        placeholder: cfg.i18n.chooseCluster,
        allowClear: true,
        minimumResultsForSearch: 0,
        dropdownParent: $('#cabinet-cluster-edit-v2-root'),
        language: {
          noResults: function () { return cfg.i18n.noResults; },
          searching: function () { return cfg.i18n.searching; },
          inputTooShort: function () { return cfg.i18n.typeToSearch; },
        },
      });
    });
  }

  function movePhrase(phrase, fromGroup, targetGroup, $select, skipReload) {
    const mainPhrase = mainPhraseForTarget(targetGroup, phrase);
    if (fromGroup === mainPhrase || (fromGroup === phrase && targetGroup === '__single__')) {
      if ($select) $select.val('').trigger('change');
      return $.Deferred().resolve().promise();
    }

    if ($select) $select.prop('disabled', true);

    return post(cfg.routes.move, {
      id: cfg.clusterId,
      phrase: phrase,
      mainPhrase: mainPhrase,
    })
      .done(function (resp) {
        if (resp.success) {
          if (!skipReload) {
            toastOk(cfg.i18n.saved);
            reloadSoon();
          }
        } else {
          toastErr(cfg.i18n.error);
          if ($select) $select.prop('disabled', false).val('').trigger('change');
        }
      })
      .fail(function () {
        toastErr(cfg.i18n.error);
        if ($select) $select.prop('disabled', false).val('').trigger('change');
      });
  }

  function createGroupAndMove(phrase, groupName, $select) {
    return post(cfg.routes.newGroup, {
      projectId: cfg.clusterId,
      mainPhrase: groupName,
      phrases: [phrase],
    })
      .done(function (resp) {
        if (resp.success) {
          toastOk(cfg.i18n.saved);
          reloadSoon();
        } else {
          toastErr(cfg.i18n.error);
          if ($select) $select.prop('disabled', false).val('').trigger('change');
        }
      })
      .fail(function () {
        toastErr(cfg.i18n.error);
        if ($select) $select.prop('disabled', false).val('').trigger('change');
      });
  }

  function handleMoveSelect($sel) {
    const val = $sel.val();
    const phrase = $sel.data('phrase');
    const from = $sel.data('from');

    if (!val) return;

    if (val === '__new__') {
      pendingMoveSelect = $sel;
      $('#cl-edit-new-group-phrase').val(phrase);
      $('#cl-edit-new-group-name').val('');
      $sel.val('').trigger('change');
      bootstrap.Modal.getOrCreateInstance(document.getElementById('cl-edit-new-group-modal')).show();
      return;
    }

    const target = val === '__single__' ? '__single__' : val;
    movePhrase(phrase, from, target, $sel);
  }

  $(document).on('change', '.cl-edit-move', function () {
    handleMoveSelect($(this));
  });

  $('#cl-edit-new-group-save').on('click', function () {
    const name = $('#cl-edit-new-group-name').val().trim();
    const phrase = $('#cl-edit-new-group-phrase').val();
    if (!name) {
      toastErr(cfg.i18n.emptyName);
      return;
    }

    post(cfg.routes.checkName, { id: cfg.clusterId, groupName: name })
      .done(function () {
        const modal = bootstrap.Modal.getInstance(document.getElementById('cl-edit-new-group-modal'));
        if (modal) modal.hide();
        if (pendingMoveSelect) pendingMoveSelect.prop('disabled', true);
        createGroupAndMove(phrase, name, pendingMoveSelect);
      })
      .fail(function (xhr) {
        const msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : cfg.i18n.renameError;
        toastErr(msg);
      });
  });

  function renameGroup($input) {
    const oldName = $input.data('old-name');
    const newName = $input.val().trim();
    if (!newName || newName === oldName) {
      $input.val(oldName);
      return;
    }

    $input.prop('disabled', true);
    post(cfg.routes.rename, {
      id: cfg.clusterId,
      oldGroupName: oldName,
      newGroupName: newName,
    })
      .done(function () {
        toastOk(cfg.i18n.saved);
        reloadSoon();
      })
      .fail(function () {
        toastErr(cfg.i18n.renameError);
        $input.val(oldName).prop('disabled', false);
      });
  }

  $(document).on('keydown', '.cl-edit-rename', function (e) {
    if (e.key === 'Enter') {
      e.preventDefault();
      $(this).blur();
    }
  });

  $(document).on('blur', '.cl-edit-rename', function () {
    renameGroup($(this));
  });

  function rowSearchText($row) {
    const phrase = ($row.find('.cl-edit-phrase').text() || '').toLowerCase();
    const url = ($row.find('.cl-edit-url').text() || '').toLowerCase();
    return phrase + ' ' + url;
  }

  function applyFilters() {
    const q = ($('#cl-edit-search').val() || '').trim().toLowerCase();
    const onlySingles = $('#cl-edit-only-singles').is(':checked');

    if (onlySingles) {
      $('#cl-edit-groups').addClass('d-none');
      $('#cl-edit-singles-block').removeClass('d-none');
    } else {
      $('#cl-edit-groups').removeClass('d-none');
    }

    $('.cabinet-cluster-edit-v2__row').each(function () {
      const $row = $(this);
      const $group = $row.closest('.cabinet-cluster-edit-v2__group');
      const groupName = ($group.data('group') || '').toString().toLowerCase();
      const haystack = rowSearchText($row) + ' ' + groupName;
      const match = !q || haystack.indexOf(q) !== -1;
      $row.toggle(match);
    });

    $('.cabinet-cluster-edit-v2__group').each(function () {
      const $group = $(this);
      const groupName = ($group.data('group') || '').toString().toLowerCase();
      const groupTitleMatch = q && groupName.indexOf(q) !== -1;
      const visibleInGroup = $group.find('.cabinet-cluster-edit-v2__row:visible').length > 0;
      if (groupTitleMatch) {
        $group.find('.cabinet-cluster-edit-v2__row').show();
      }
      $group.toggle(groupTitleMatch || visibleInGroup || !q);
    });

    const $singles = $('#cl-edit-singles-block');
    if ($singles.length) {
      const singlesVisible = $singles.find('.cabinet-cluster-edit-v2__row:visible').length > 0;
      $singles.toggle(!q || singlesVisible || onlySingles);
    }

    const visibleRows = $('.cabinet-cluster-edit-v2__row:visible').length;
    const $hint = $('#cl-edit-search-hint');
    if (q) {
      $hint.removeClass('d-none').text(visibleRows > 0 ? ('Найдено: ' + visibleRows) : 'Ничего не найдено');
    } else {
      $hint.addClass('d-none').text('');
    }
  }

  $('#cl-edit-search').on('input keyup search', applyFilters);
  $('#cl-edit-only-singles').on('change', applyFilters);
  $('#cl-edit-reset-filter').on('click', function () {
    $('#cl-edit-search').val('');
    $('#cl-edit-only-singles').prop('checked', false);
    applyFilters();
  });

  $('#cl-edit-reset-confirm').on('click', function () {
    post(cfg.routes.reset, { projectId: cfg.clusterId })
      .done(function () { window.location.reload(); })
      .fail(function () { toastErr(cfg.i18n.error); });
  });

  /* --- sidebar: relevance + merge --- */
  $('#cl-edit-show-relevance').on('change', function () {
    const on = $(this).is(':checked');
    $('.cl-edit-sidebar-rel').toggleClass('d-none', !on);
    $('.cabinet-cluster-edit-v2__table .cl-edit-url').closest('td').toggle(on);
  });

  function updateMergeBar() {
    const checked = $('.cl-edit-merge-check:checked').map(function () { return $(this).val(); }).get();
    const $bar = $('#cl-edit-merge-bar');
    const $btn = $('#cl-edit-merge-run');
    const $target = $('#cl-edit-merge-target');

    if (checked.length >= 2) {
      $bar.removeClass('d-none');
      $btn.prop('disabled', !$target.val());
    } else {
      $bar.addClass('d-none');
      $btn.prop('disabled', true);
    }
  }

  $(document).on('change', '.cl-edit-merge-check', updateMergeBar);
  $('#cl-edit-merge-target').on('change', updateMergeBar);

  $('#cl-edit-merge-run').on('click', function () {
    const target = $('#cl-edit-merge-target').val();
    const checked = $('.cl-edit-merge-check:checked').map(function () { return $(this).val(); }).get();
    const sources = checked.filter(function (n) { return n !== target; });
    if (!target || sources.length === 0) return;

    const phrases = [];
    sources.forEach(function (groupName) {
      $('.cabinet-cluster-edit-v2__group').each(function () {
        if ($(this).data('group') === groupName) {
          $(this).find('.cabinet-cluster-edit-v2__row').each(function () {
            phrases.push($(this).data('phrase'));
          });
        }
      });
    });

    if (!phrases.length) return;

    $(this).prop('disabled', true);
    post(cfg.routes.newGroup, {
      projectId: cfg.clusterId,
      mainPhrase: target,
      phrases: phrases,
    })
      .done(function (resp) {
        if (resp.success) {
          toastOk(cfg.i18n.mergeOk);
          reloadSoon();
        } else {
          toastErr(cfg.i18n.error);
          $('#cl-edit-merge-run').prop('disabled', false);
        }
      })
      .fail(function () {
        toastErr(cfg.i18n.error);
        $('#cl-edit-merge-run').prop('disabled', false);
      });
  });

  $(document).on('click', '.cl-edit-sidebar-link', function (e) {
    e.preventDefault();
    const href = $(this).attr('href');
    if (!href || href.charAt(0) !== '#') return;
    const $el = $(href);
    if ($el.length) {
      $('.cl-edit-sidebar-item').removeClass('active');
      $(this).closest('.cl-edit-sidebar-item').addClass('active');
      $el[0].scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  });

  /* --- drag and drop --- */
  function initDragDrop() {
    if (!$.fn.sortable) return;

    $('.cl-edit-sortable-tbody').sortable({
      group: 'cl-edit-phrases',
      itemSelector: 'tr.cabinet-cluster-edit-v2__row',
      containerSelector: 'tbody.cl-edit-sortable-tbody',
      handle: '.cl-edit-drag-handle',
      placeholder: '<tr class="cl-edit-placeholder"><td colspan="5">&nbsp;</td></tr>',
      placeholderClass: 'cl-edit-placeholder',
      pullPlaceholder: true,
      onDragStart: function ($item, container, superFn, e) {
        clearSidebarDropHighlight();
        buildDragMirror($item, e);
        $item.addClass('cl-edit-drag-source');
        $(document).on('mousemove.clEditSidebarDrop', updateSidebarDropHover);
      },
      onDrag: function ($item, position, superFn, e) {
        moveDragMirror(e);
      },
      onCancel: function ($item, container, superFn, e) {
        $(document).off('mousemove.clEditSidebarDrop');
        clearSidebarDropHighlight();
        cleanupDragVisuals($item);
      },
      onDrop: function ($item, container, superFn, e) {
        $(document).off('mousemove.clEditSidebarDrop');

        const $row = $item.closest('tr.cabinet-cluster-edit-v2__row');
        const dropGroup = sidebarDropAt(e && e.pageX, e && e.pageY) || sidebarDropTarget;
        clearSidebarDropHighlight();
        cleanupDragVisuals($row);

        if (dropGroup && runSidebarDrop($row, dropGroup)) {
          return;
        }

        if (dndBusy) return;

        const phrase = $row.data('phrase');
        const fromGroup = $row.data('from');
        const $tbody = $(container.el);
        const targetGroup = resolveTargetGroup($tbody);

        if (isSameGroup(fromGroup, targetGroup, phrase)) {
          return;
        }

        dndBusy = true;
        movePhrase(phrase, fromGroup, targetGroup, null, false).always(function () {
          dndBusy = false;
        });
      },
    });
  }

  $(function () {
    initMoveSelects();
    initDragDrop();
    applyFilters();
  });
})(jQuery);
