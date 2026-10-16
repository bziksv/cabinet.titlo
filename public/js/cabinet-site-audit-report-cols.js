(function () {
  'use strict';

  function $(sel, root) {
    return (root || document).querySelector(sel);
  }

  function $$(sel, root) {
    return Array.prototype.slice.call((root || document).querySelectorAll(sel));
  }

  function storageKey(code) {
    return 'cabinet_sa_report_cols_v1:' + (code || 'default');
  }

  function loadState(code) {
    try {
      var raw = localStorage.getItem(storageKey(code));
      if (!raw) return null;
      var parsed = JSON.parse(raw);
      if (!parsed || typeof parsed !== 'object') return null;
      return {
        visible: Array.isArray(parsed.visible) ? parsed.visible : null,
        order: Array.isArray(parsed.order) ? parsed.order : null
      };
    } catch (e) {
      return null;
    }
  }

  function saveState(code, visible, order) {
    try {
      localStorage.setItem(storageKey(code), JSON.stringify({
        visible: visible,
        order: order
      }));
    } catch (e) { /* ignore */ }
  }

  function clearState(code) {
    try {
      localStorage.removeItem(storageKey(code));
    } catch (e) { /* ignore */ }
  }

  function defaultKeys(table) {
    var attr = table.getAttribute('data-sa-cols-default') || '';
    return attr.split(',').map(function (s) { return s.trim(); }).filter(Boolean);
  }

  function pinnedEndKeys(root) {
    var attr = root.getAttribute('data-sa-cols-pinned-end') || '';
    return attr.split(',').map(function (s) { return s.trim(); }).filter(Boolean);
  }

  function catalogOrder(root) {
    return $$('[data-sa-col-order]', root)
      .map(function (el) { return el.getAttribute('data-sa-col-order'); })
      .filter(Boolean);
  }

  function catalogDefaultOrder(root) {
    var attr = root.getAttribute('data-sa-cols-catalog') || '';
    var keys = attr.split(',').map(function (s) { return s.trim(); }).filter(Boolean);
    return keys.length ? keys : catalogOrder(root);
  }

  function withPinnedEnd(order, pinned) {
    var pinSet = {};
    (pinned || []).forEach(function (k) { pinSet[k] = true; });
    var body = [];
    var tail = [];
    (order || []).forEach(function (k) {
      if (!k) return;
      if (pinSet[k]) {
        if (tail.indexOf(k) === -1) tail.push(k);
      } else if (body.indexOf(k) === -1) {
        body.push(k);
      }
    });
    (pinned || []).forEach(function (k) {
      if (tail.indexOf(k) === -1) tail.push(k);
    });
    return body.concat(tail);
  }

  function mergeOrder(preferred, catalog, pinned) {
    var seen = {};
    var out = [];
    (preferred || []).forEach(function (k) {
      if (!k || seen[k] || catalog.indexOf(k) === -1) return;
      seen[k] = true;
      out.push(k);
    });
    catalog.forEach(function (k) {
      if (seen[k]) return;
      seen[k] = true;
      out.push(k);
    });
    return withPinnedEnd(out, pinned);
  }

  function applyVisibility(root, table, keys) {
    var set = {};
    (keys || []).forEach(function (k) { set[k] = true; });
    set.url = true;
    if (table.querySelector('[data-sa-col="details"]')) {
      // Детали — часть отчёта; если колонка есть в таблице, не прячем скрытым пресетом без details
      var detailsToggle = root.querySelector('[data-sa-col-toggle="details"]');
      if (detailsToggle && detailsToggle.disabled) set.details = true;
    }

    $$('[data-sa-col]', table).forEach(function (el) {
      var key = el.getAttribute('data-sa-col');
      if (!key) return;
      if (set[key]) el.classList.remove('is-col-hidden');
      else el.classList.add('is-col-hidden');
    });

    $$('[data-sa-col-toggle]', root).forEach(function (input) {
      var key = input.getAttribute('data-sa-col-toggle');
      if (!key || input.disabled) return;
      input.checked = !!set[key];
    });

    /* «Все meta» не влезает в экран: отдельные ширины и горизонтальный скролл. */
    table.classList.toggle(
      'cabinet-sa-findings-table--meta-wide',
      !!(set.title_len || set.desc_len || set.keywords)
    );
  }

  function applyOrder(table, order) {
    var headRow = table.querySelector('thead tr');
    if (!headRow) return;
    var bodyRows = $$('tbody tr', table);
    order.forEach(function (key) {
      var th = headRow.querySelector('[data-sa-col="' + key + '"]');
      if (th) headRow.appendChild(th);
      bodyRows.forEach(function (tr) {
        var td = tr.querySelector(':scope > [data-sa-col="' + key + '"]');
        if (!td) {
          td = Array.prototype.find.call(tr.children, function (ch) {
            return ch.getAttribute && ch.getAttribute('data-sa-col') === key;
          });
        }
        if (td) tr.appendChild(td);
      });
    });
  }

  function applyOrderList(root, order, pinned) {
    var panel = $('[data-sa-cols-order-list]', root);
    if (!panel) return;
    var byKey = {};
    $$('[data-sa-col-order]', panel).forEach(function (el) {
      byKey[el.getAttribute('data-sa-col-order')] = el;
    });
    var hint = panel.querySelector('.cabinet-sa-report-cols__hint');
    var pinnedBox = panel.querySelector('[data-sa-cols-pinned]');
    var foot = panel.querySelector('[data-sa-cols-foot]');
    var ordered = withPinnedEnd(order, pinned);

    ordered.forEach(function (key) {
      var el = byKey[key];
      if (!el) return;
      if (el.getAttribute('data-sa-col-pinned-end') === '1' && pinnedBox) {
        pinnedBox.appendChild(el);
      } else {
        panel.appendChild(el);
      }
    });

    if (hint) panel.insertBefore(hint, panel.firstChild);
    if (pinnedBox) panel.appendChild(pinnedBox);
    if (foot) panel.appendChild(foot);
  }

  function visibleKeys(root) {
    return $$('[data-sa-col-toggle]', root)
      .filter(function (input) { return input.checked || input.disabled; })
      .map(function (input) { return input.getAttribute('data-sa-col-toggle'); })
      .filter(Boolean);
  }

  function currentOrder(root, pinned) {
    return withPinnedEnd(catalogOrder(root), pinned);
  }

  function persist(root, table, code, pinned) {
    saveState(code, visibleKeys(root), currentOrder(root, pinned));
  }

  function bindDrag(root, table, code, pinned) {
    var panel = $('[data-sa-cols-order-list]', root);
    if (!panel) return;
    var dragEl = null;
    var pinnedBox = panel.querySelector('[data-sa-cols-pinned]');

    $$('[data-sa-col-order]', panel).forEach(function (el) {
      if (el.getAttribute('draggable') !== 'true') return;

      el.addEventListener('dragstart', function (e) {
        dragEl = el;
        el.classList.add('is-dragging');
        try {
          e.dataTransfer.setData('text/plain', el.getAttribute('data-sa-col-order'));
        } catch (err) { /* ignore */ }
        e.dataTransfer.effectAllowed = 'move';
      });

      el.addEventListener('dragend', function () {
        el.classList.remove('is-dragging');
        dragEl = null;
        var order = currentOrder(root, pinned);
        applyOrderList(root, order, pinned);
        applyOrder(table, order);
        persist(root, table, code, pinned);
      });

      el.addEventListener('dragover', function (e) {
        if (!dragEl || dragEl === el) return;
        if (el.getAttribute('data-sa-col-pinned-end') === '1') return;
        e.preventDefault();
        var rect = el.getBoundingClientRect();
        var before = (e.clientY - rect.top) < rect.height / 2;
        if (before) panel.insertBefore(dragEl, el);
        else panel.insertBefore(dragEl, el.nextSibling);
        // pinned-блок всегда ниже списка
        if (pinnedBox) panel.appendChild(pinnedBox);
        var foot = panel.querySelector('[data-sa-cols-foot]');
        if (foot) panel.appendChild(foot);
      });
    });
  }

  function ensureActionsInPreset(cols, root) {
    var pinned = pinnedEndKeys(root);
    var out = (cols || []).slice();
    pinned.forEach(function (k) {
      if (out.indexOf(k) === -1
        && root.querySelector('[data-sa-col-toggle="' + k + '"]')) {
        out.push(k);
      }
    });
    return withPinnedEnd(out, pinned);
  }

  function resetToDefault(root, table, code, pinned) {
    clearState(code);
    var order = withPinnedEnd(catalogDefaultOrder(root), pinned);
    var visible = defaultKeys(table).filter(function (k) {
      return order.indexOf(k) !== -1;
    });
    if (visible.indexOf('url') === -1) visible.unshift('url');
    applyOrderList(root, order, pinned);
    applyOrder(table, order);
    applyVisibility(root, table, visible);
  }

  function init() {
    var root = $('[data-sa-report-cols]');
    var table = $('[data-sa-report-table]');
    if (!root || !table) return;

    var code = table.getAttribute('data-sa-report-code') || 'default';
    var pinned = pinnedEndKeys(root);
    var catalog = withPinnedEnd(catalogDefaultOrder(root), pinned);
    var state = loadState(code);
    var order = mergeOrder(state && state.order, catalog, pinned);
    var visible = (state && state.visible) || defaultKeys(table);
    visible = visible.filter(function (k) { return catalog.indexOf(k) !== -1; });
    if (visible.indexOf('url') === -1) visible.unshift('url');

    applyOrderList(root, order, pinned);
    applyOrder(table, order);
    applyVisibility(root, table, visible);

    root.addEventListener('change', function (e) {
      var t = e.target;
      if (!t || !t.getAttribute || !t.getAttribute('data-sa-col-toggle')) return;
      var next = visibleKeys(root);
      if (next.indexOf('url') === -1) next.unshift('url');
      applyVisibility(root, table, next);
      persist(root, table, code, pinned);
    });

    root.addEventListener('click', function (e) {
      var resetBtn = e.target.closest('[data-sa-cols-reset]');
      if (resetBtn && root.contains(resetBtn)) {
        e.preventDefault();
        resetToDefault(root, table, code, pinned);
        return;
      }

      var btn = e.target.closest('[data-sa-cols-preset]');
      if (!btn || !root.contains(btn)) return;
      e.preventDefault();
      var colsAttr = btn.getAttribute('data-sa-cols-preset-cols') || '';
      var cols = colsAttr.split(',').map(function (s) { return s.trim(); }).filter(Boolean);
      if (!cols.length) return;
      if (cols.indexOf('url') === -1) cols.unshift('url');
      // пресет не выкидывает «Действия», если колонка есть в отчёте
      cols = ensureActionsInPreset(cols, root);
      applyVisibility(root, table, cols);
      persist(root, table, code, pinned);
    });

    bindDrag(root, table, code, pinned);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
