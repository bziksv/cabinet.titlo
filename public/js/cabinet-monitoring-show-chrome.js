/**
 * /monitoring/{id} — SER: «Обзор» = графики, «Ключевые слова» = таблица (без дубля «Позиции»).
 *
 * Таблица #monitoringTable: FixedColumns baseline fc21.
 * Док: docs/frontend/monitoring-keywords-fixed-columns.md
 */
(function () {
    'use strict';

    var root = document.getElementById('cabinet-mon-project-root');
    if (!root) {
        return;
    }

    var cfg = window.cabinetMonProjectConfig || {};
    var storageKey = 'cabinet-mon-project-view-v2';
    var relayoutTimer;
    var columnToggleTimer;
    var relayoutRunning = false;
    var monColumnTogglePending = false;
    var monTableLayoutLocked = false;
    var COLUMN_TOGGLE_DEBOUNCE_MS = 220;
    var relayoutPendingOptions = null;
    var RESIZE_RELAYOUT_DEBOUNCE_MS = 250;
    var TABLE_VISIBLE_ROWS = 30;
    var TABLE_ROW_HEIGHT_FALLBACK = 34;
    var TABLE_COL_WIDTHS = {
        checkbox: '46px',
        btn: '62px',
        query: '380px',
        url: '140px',
        group: '140px',
        target_url: '160px',
        target: '112px',
        dynamics: '104px',
        base: '36px',
        phrasal: '56px',
        exact: '60px',
    };

    function monTableColWidth(name) {
        if (TABLE_COL_WIDTHS[name]) {
            return TABLE_COL_WIDTHS[name];
        }
        if (String(name).indexOf('col_') === 0) {
            return '88px';
        }
        if (String(name).indexOf('engine_') === 0) {
            return '96px';
        }
        return null;
    }

    function applyColgroupWidths($table, widths) {
        var $colgroup = $table.children('colgroup');
        if (!$colgroup.length) {
            $colgroup = jQuery('<colgroup/>').prependTo($table);
        }
        $colgroup.empty();
        widths.forEach(function (px) {
            jQuery('<col/>').css('width', px).appendTo($colgroup);
        });
    }

    function columnWidthPx(col) {
        var name = col.sName || col.mData || '';
        var named = monTableColWidth(name);
        if (named) {
            return named;
        }
        var saved = parseInt(col.sWidth, 10);
        if (!isNaN(saved) && saved >= 40) {
            return saved + 'px';
        }
        return '88px';
    }

    function syncColumnVisibilityFromSettings(api) {
        if (!api) {
            return;
        }
        var settings = cfg.columnSettings || {};
        Object.keys(settings).forEach(function (name) {
            var col = api.column(name + ':name');
            if (!col.length) {
                return;
            }
            var wantVisible = !!settings[name];
            if (col.visible() !== wantVisible) {
                col.visible(wantVisible, false);
            }
        });
    }

    function buildColumnWidthPlan(api) {
        var settings = api.settings()[0];
        if (!settings) {
            return null;
        }

        var colgroup = [];
        var visible = [];
        settings.aoColumns.forEach(function (col, idx) {
            if (!col.bVisible) {
                return;
            }
            var nominalPx = columnWidthPx(col);
            col.sWidth = nominalPx;
            colgroup.push(nominalPx);
            visible.push({ idx: idx, width: nominalPx });
        });

        return { colgroup: colgroup, visible: visible, settings: settings };
    }

    function scrollHeadCells($wrapper) {
        return $wrapper.find('.dataTables_scrollHead thead tr:first th');
    }

    function visibleColumnDomIndex(settings, idx) {
        if (!settings || idx < 0) {
            return -1;
        }
        var domIdx = 0;
        for (var i = 0; i < settings.aoColumns.length; i++) {
            if (!settings.aoColumns[i].bVisible) {
                continue;
            }
            if (i === idx) {
                return domIdx;
            }
            domIdx++;
        }
        return -1;
    }

    function applyPlanColumnWidths(api, plan) {
        if (!api || !plan || !window.jQuery) {
            return 0;
        }
        var totalW = 0;
        var $wrapper = jQuery(api.table().container());
        var $scrollHeadTh = scrollHeadCells($wrapper);
        var settings = plan.settings;

        plan.visible.forEach(function (item) {
            var domIdx = visibleColumnDomIndex(plan.settings, item.idx);
            if (domIdx < 0) {
                return;
            }
            var css;
            if (settings._oFixedColumns && item.idx < monFixedLeftCount(api)) {
                return;
            }
            css = { width: item.width, minWidth: item.width, maxWidth: item.width };
            $scrollHeadTh.eq(domIdx).css(css);
            $wrapper.find('.dataTables_scrollBody tbody tr').each(function () {
                jQuery(this).children('td').eq(domIdx).css(css);
            });
            totalW += parseInt(item.width, 10) || 0;
        });
        return totalW;
    }

    function clearHiddenColumnInlineWidths(api, plan) {
        if (!api || !window.jQuery || !plan) {
            return;
        }
        var emptyCss = { width: '', minWidth: '', maxWidth: '' };
        var $wrapper = jQuery(api.table().container());
        plan.settings.aoColumns.forEach(function (col, idx) {
            if (col.bVisible) {
                return;
            }
            var domIdx = visibleColumnDomIndex(plan.settings, idx);
            if (domIdx < 0) {
                return;
            }
            scrollHeadCells($wrapper).eq(domIdx).css(emptyCss);
            $wrapper.find('.dataTables_scrollBody tbody tr').each(function () {
                jQuery(this).children('td').eq(domIdx).css(emptyCss);
            });
        });
    }

    function monFixedLeftCount(api) {
        if (!api) {
            return 3;
        }
        var col = api.column('query:name');
        if (!col.length) {
            return 3;
        }
        return col.index() + 1;
    }

    function destroyFixedColumns(api) {
        if (!api || !window.jQuery) {
            return;
        }
        var settings = api.settings()[0];
        if (!settings || !settings._oFixedColumns) {
            return;
        }
        jQuery(settings.nTable).trigger('destroy.dt.DTFC');
        settings._oFixedColumns = null;
    }

    function ensureFixedColumns(api) {
        if (!api || !window.jQuery || !jQuery.fn.dataTable || !jQuery.fn.dataTable.FixedColumns) {
            return;
        }
        var settings = api.settings()[0];
        if (!settings || settings._oFixedColumns) {
            return;
        }
        var leftCols = monFixedLeftCount(api);
        if (leftCols < 1) {
            return;
        }
        new jQuery.fn.dataTable.FixedColumns(settings, {
            leftColumns: leftCols,
            heightMatch: 'auto',
        });
    }

    function buildFixedLeftColgroup(api, leftCount) {
        var settings = api.settings()[0];
        if (!settings) {
            return [];
        }
        var widths = [];
        for (var i = 0; i < leftCount; i++) {
            widths.push(columnWidthPx(settings.aoColumns[i]));
        }
        return widths;
    }

    function clearMonTableRowInlineHeights(api) {
        if (!api || !window.jQuery) {
            return;
        }
        jQuery(api.table().container())
            .find('.DTFC_LeftBodyLiner tbody tr, .dataTables_scrollBody tbody tr')
            .each(function () {
                this.style.height = '';
            });
    }

    function syncMonTableRowHeights(api) {
        if (!api || !window.jQuery) {
            return;
        }
        var settings = api.settings()[0];
        if (!settings || !settings._oFixedColumns) {
            return;
        }
        var $wrapper = jQuery(api.table().container());
        var $mainRows = $wrapper.find('.dataTables_scrollBody tbody tr');
        var $fcRows = $wrapper.find('.DTFC_LeftBodyLiner tbody tr');
        if (!$mainRows.length || !$fcRows.length) {
            return;
        }
        $mainRows.each(function (i) {
            var fcTr = $fcRows[i];
            if (!fcTr) {
                return;
            }
            this.style.height = 'auto';
            fcTr.style.height = 'auto';
            var h = Math.ceil(Math.max(
                this.getBoundingClientRect().height,
                fcTr.getBoundingClientRect().height
            ));
            if (h > 120) {
                h = Math.ceil(fcTr.getBoundingClientRect().height);
            }
            if (h < 1) {
                return;
            }
            var px = h + 'px';
            this.style.height = px;
            fcTr.style.height = px;
            var h2 = Math.ceil(Math.max(
                this.getBoundingClientRect().height,
                fcTr.getBoundingClientRect().height
            ));
            if (h2 > h) {
                px = h2 + 'px';
                this.style.height = px;
                fcTr.style.height = px;
            }
        });
    }

    function syncFixedLeftBlock(api) {
        if (!api || !window.jQuery) {
            return;
        }
        var $wrapper = jQuery(api.table().container());
        var $leftHead = $wrapper.find('.DTFC_LeftHeadWrapper');
        var $leftBody = $wrapper.find('.DTFC_LeftBodyLiner');
        if (!$leftHead.length || !$leftBody.length) {
            return;
        }

        var plan = buildColumnWidthPlan(api);
        if (!plan) {
            return;
        }
        var settings = plan.settings;

        var leftCount = monFixedLeftCount(api);
        var leftWidths = buildFixedLeftColgroup(api, leftCount);
        if (!leftWidths.length) {
            return;
        }

        $leftHead.find('table').add($leftBody.find('table')).each(function () {
            applyColgroupWidths(jQuery(this), leftWidths);
        });

        var totalLeft = 0;
        var cellCss = {};
        leftWidths.forEach(function (px, i) {
            cellCss[i] = { width: px, minWidth: px, maxWidth: px };
            totalLeft += parseInt(px, 10) || 0;
        });

        $leftHead.find('thead tr:first-child th').each(function (i) {
            if (cellCss[i]) {
                jQuery(this).css(cellCss[i]);
            }
        });

        $leftBody.find('tbody tr').each(function () {
            jQuery(this).children('td').each(function (i) {
                if (cellCss[i]) {
                    jQuery(this).css(cellCss[i]);
                }
            });
        });

        $leftHead.find('table').add($leftBody.find('table')).width(totalLeft);

        var fcWidthPx = totalLeft + 'px';
        $wrapper.find('.DTFC_LeftWrapper').css({
            width: fcWidthPx,
            minWidth: fcWidthPx,
            maxWidth: fcWidthPx,
            overflow: 'visible',
        });
        $wrapper.find('.DTFC_LeftHeadWrapper, .DTFC_LeftBodyWrapper').css({
            width: fcWidthPx,
            minWidth: fcWidthPx,
            maxWidth: fcWidthPx,
            overflow: 'hidden',
        });
        $leftHead.find('table').add($leftBody.find('table')).css({
            width: fcWidthPx,
            maxWidth: fcWidthPx,
        });
        if (root && root.style) {
            root.style.setProperty('--mon-fc-left-width', fcWidthPx);
        }

        var $scrollBody = $wrapper.find('.dataTables_scrollBody');
        var $scrollHead = $wrapper.find('.dataTables_scrollHead');
        if ($scrollBody.length) {
            $leftHead.css('padding-right', '0');
            var bodyH = $scrollBody.innerHeight();
            $wrapper.find('.DTFC_LeftBodyWrapper').height(bodyH);
        }
        if ($scrollHead.length) {
            $leftHead.height($scrollHead.outerHeight());
        }

        hideMainTableLeftColumns(api, leftCount);
        syncMainTableLeftHiddenWidths($wrapper, leftCount, settings, api);
        applyScrollTableFcInset($wrapper);
        clearMonTableRowInlineHeights(api);
        syncMonTableRowHeights(api);
        if ($scrollBody.length) {
            syncMonTableScrollPositions($wrapper, $scrollBody[0], true);
            if ($wrapper.data('monScrollWired')) {
                muteFcVerticalScrollHandlers(api);
            }
        }
    }

    function alignFixedLeftBlockToScrollEdge(api) {
        if (!api || !window.jQuery) {
            return;
        }
        applyScrollTableFcInset(jQuery(api.table().container()));
    }

    function hideMainTableLeftColumns(api, leftCount) {
        if (!api || !window.jQuery) {
            return;
        }
        leftCount = leftCount || monFixedLeftCount(api);
        var $wrapper = jQuery(api.table().container());
        var settings = api.settings()[0];
        var edgeIdx = null;

        if (settings) {
            for (var i = leftCount; i < settings.aoColumns.length; i++) {
                if (settings.aoColumns[i].bVisible) {
                    edgeIdx = i;
                    break;
                }
            }
        }

        $wrapper.find('.dataTables_scrollHead thead th').removeClass(
            'cabinet-mon-scrollhead-left-hidden cabinet-mon-scroll-edge-col'
        );
        $wrapper.find('.dataTables_scrollBody tbody td').removeClass(
            'cabinet-mon-scrollbody-left-hidden cabinet-mon-scroll-edge-col'
        );

        api.columns().every(function () {
            var idx = this.index();
            var $header = jQuery(this.header());
            var $cells = jQuery(this.nodes());
            if (idx < leftCount) {
                $header.addClass('cabinet-mon-scrollhead-left-hidden');
                $cells.addClass('cabinet-mon-scrollbody-left-hidden');
            }
            if (idx === edgeIdx) {
                $header.addClass('cabinet-mon-scroll-edge-col');
                $cells.addClass('cabinet-mon-scroll-edge-col');
            }
        });
    }

    function syncMainTableLeftHiddenWidths($wrapper, leftCount, settings, api) {
        if (!$wrapper || !leftCount || !settings) {
            return;
        }
        var zeroCss = {
            width: '0px',
            minWidth: '0px',
            maxWidth: '0px',
            paddingLeft: '0',
            paddingRight: '0',
            borderLeftWidth: '0',
            borderRightWidth: '0',
        };
        var colWidths = [];
        settings.aoColumns.forEach(function (col, idx) {
            if (!col.bVisible) {
                return;
            }
            colWidths.push(idx < leftCount ? '0px' : columnWidthPx(col));
        });
        $wrapper.find('.dataTables_scrollHead table, .dataTables_scrollBody table').each(function () {
            applyColgroupWidths(jQuery(this), colWidths);
        });
        $wrapper.find('.cabinet-mon-scrollhead-left-hidden, .cabinet-mon-scrollbody-left-hidden').css(zeroCss);
    }

    function applyScrollTableFcInset($wrapper) {
        if (!$wrapper || !window.jQuery) {
            return;
        }
        var fcEl = $wrapper.find('.DTFC_LeftWrapper')[0];
        if (!fcEl) {
            if (root && root.style) {
                root.style.setProperty('--mon-scroll-edge-nudge', '0px');
            }
            $wrapper.find('.dataTables_scrollHeadInner table, .dataTables_scrollBody table').css('margin-left', '');
            return;
        }
        var insetPx = Math.ceil(fcEl.getBoundingClientRect().width);
        var edgeEl = $wrapper.find('.dataTables_scrollBody tbody td.cabinet-mon-scroll-edge-col')[0];
        if (edgeEl) {
            var gap = edgeEl.getBoundingClientRect().left - fcEl.getBoundingClientRect().right;
            if (Math.abs(gap) >= 0.5 && Math.abs(gap) < 240) {
                insetPx -= Math.round(gap);
            }
        }
        if (insetPx < 0) {
            insetPx = 0;
        }
        var inset = insetPx + 'px';
        if (root && root.style) {
            root.style.setProperty('--mon-scroll-edge-nudge', inset);
            root.style.setProperty('--mon-fc-left-width', inset);
        }
        $wrapper.find('.dataTables_scrollHeadInner table, .dataTables_scrollBody table').css('margin-left', inset);
    }

    function remeasureMainTableLeftHiddenWidths(api) {
        if (!api || !window.jQuery) {
            return;
        }
        var settings = api.settings()[0];
        if (!settings || !settings._oFixedColumns) {
            return;
        }
        syncMainTableLeftHiddenWidths(
            jQuery(api.table().container()),
            monFixedLeftCount(api),
            settings,
            api
        );
        applyScrollTableFcInset(jQuery(api.table().container()));
    }

    function relayoutMonTableFcLayout(api) {
        if (!api || !window.jQuery) {
            return;
        }
        var settings = api.settings()[0];
        if (!settings || !settings._oFixedColumns) {
            return;
        }
        remeasureMainTableLeftHiddenWidths(api);
        clearMonTableRowInlineHeights(api);
        syncMonTableRowHeights(api);
    }

    function relayoutFixedColumns(api, options) {
        options = options || {};
        if (!api || !window.jQuery) {
            return;
        }
        if (options.rebuild) {
            destroyFixedColumns(api);
        }
        ensureFixedColumns(api);
        if (typeof api.fixedColumns !== 'function') {
            return;
        }
        try {
            api.fixedColumns().relayout();
            syncFixedLeftBlock(api);
            syncMonTableRowHeights(api);
            muteFcVerticalScrollHandlers(api);
        } catch (e) {}
    }

    function syncMonTableScrollPositions($wrapper, scrollBodyEl, force) {
        if (!$wrapper || !scrollBodyEl || !window.jQuery) {
            return;
        }
        var sl = scrollBodyEl.scrollLeft;
        var st = scrollBodyEl.scrollTop;
        var headInner = $wrapper.find('.dataTables_scrollHeadInner')[0];
        if (headInner && (force || headInner.scrollLeft !== sl)) {
            headInner.scrollLeft = sl;
        }
        var scrollHead = $wrapper.find('.dataTables_scrollHead')[0];
        if (scrollHead && (force || scrollHead.scrollLeft !== sl)) {
            scrollHead.scrollLeft = sl;
        }
        var leftLiner = $wrapper.find('.DTFC_LeftBodyLiner')[0];
        if (leftLiner) {
            leftLiner.scrollTop = st;
            if (force) {
                void leftLiner.scrollHeight;
            }
        }
    }

    function muteFcVerticalScrollHandlers(api) {
        if (!api || !window.jQuery) {
            return;
        }
        var settings = api.settings()[0];
        var fc = settings && settings._oFixedColumns;
        if (!fc || !fc.dom || !fc.dom.scroller) {
            return;
        }
        jQuery(fc.dom.scroller).off('scroll.DTFC');
        if (fc.dom.grid && fc.dom.grid.left && fc.dom.grid.left.liner) {
            jQuery(fc.dom.grid.left.liner).off('scroll.DTFC');
        }
    }

    function wireMonTableScrollSync(api) {
        if (!api || !window.jQuery) {
            return;
        }
        var $wrapper = jQuery(api.table().container());
        var $scrollBody = $wrapper.find('.dataTables_scrollBody');
        if (!$scrollBody.length) {
            return;
        }
        var scrollEl = $scrollBody[0];
        var SCROLL_SYNC_TAIL_MS = 480;

        if (scrollEl._monTableColsScroll) {
            scrollEl.removeEventListener('scroll', scrollEl._monTableColsScroll, true);
        }
        if (scrollEl._monTableColsScrollAfter) {
            scrollEl.removeEventListener('scroll', scrollEl._monTableColsScrollAfter);
        }
        if (scrollEl._monTableColsWheel) {
            scrollEl.removeEventListener('wheel', scrollEl._monTableColsWheel, true);
        }
        if (scrollEl._monTableColsTouch) {
            scrollEl.removeEventListener('touchmove', scrollEl._monTableColsTouch, true);
        }
        if (scrollEl._monTableColsScrollEndHandler) {
            scrollEl.removeEventListener('scrollend', scrollEl._monTableColsScrollEndHandler);
        }
        if (scrollEl._monTableColsScrollRaf) {
            cancelAnimationFrame(scrollEl._monTableColsScrollRaf);
            scrollEl._monTableColsScrollRaf = 0;
        }
        if (scrollEl._monTableColsScrollEnd) {
            clearTimeout(scrollEl._monTableColsScrollEnd);
            scrollEl._monTableColsScrollEnd = 0;
        }

        var stopScrollSync = function () {
            scrollEl._monTableColsScrollActive = false;
            syncMonTableScrollPositions($wrapper, scrollEl, true);
        };

        var kickMonTableScrollSync = function () {
            scrollEl._monTableColsScrollActive = true;
            syncMonTableScrollPositions($wrapper, scrollEl, true);
            if (!scrollEl._monTableColsScrollRaf) {
                scrollEl._monTableColsScrollRaf = requestAnimationFrame(rafLoop);
            }
            clearTimeout(scrollEl._monTableColsScrollEnd);
            scrollEl._monTableColsScrollEnd = setTimeout(stopScrollSync, SCROLL_SYNC_TAIL_MS);
        };

        var rafLoop = function () {
            scrollEl._monTableColsScrollRaf = 0;
            syncMonTableScrollPositions($wrapper, scrollEl, true);
            requestAnimationFrame(function () {
                if (!scrollEl._monTableColsScrollActive) {
                    return;
                }
                syncMonTableScrollPositions($wrapper, scrollEl, true);
            });
            if (scrollEl._monTableColsScrollActive) {
                scrollEl._monTableColsScrollRaf = requestAnimationFrame(rafLoop);
            }
        };

        scrollEl._monTableColsScroll = kickMonTableScrollSync;
        scrollEl.addEventListener('scroll', scrollEl._monTableColsScroll, { passive: true, capture: true });

        scrollEl._monTableColsScrollAfter = function () {
            syncMonTableScrollPositions($wrapper, scrollEl, true);
            requestAnimationFrame(function () {
                syncMonTableScrollPositions($wrapper, scrollEl, true);
            });
        };
        scrollEl.addEventListener('scroll', scrollEl._monTableColsScrollAfter, { passive: true });

        scrollEl._monTableColsWheel = function (e) {
            if (!e.deltaY || Math.abs(e.deltaX) > Math.abs(e.deltaY)) {
                kickMonTableScrollSync();
                return;
            }
            scrollEl.scrollTop += e.deltaY;
            syncMonTableScrollPositions($wrapper, scrollEl, true);
            kickMonTableScrollSync();
            e.preventDefault();
        };
        scrollEl.addEventListener('wheel', scrollEl._monTableColsWheel, { passive: false, capture: true });

        scrollEl._monTableColsTouch = function () {
            kickMonTableScrollSync();
        };
        scrollEl.addEventListener('touchmove', scrollEl._monTableColsTouch, { passive: true, capture: true });

        if ('onscrollend' in scrollEl) {
            scrollEl._monTableColsScrollEndHandler = function () {
                stopScrollSync();
            };
            scrollEl.addEventListener('scrollend', scrollEl._monTableColsScrollEndHandler, { passive: true });
        }

        $wrapper.find('.DTFC_LeftBodyWrapper, .DTFC_LeftBodyLiner').off('wheel.monTableCols').on('wheel.monTableCols', function (e) {
            var oe = e.originalEvent;
            if (!oe || !Math.abs(oe.deltaY)) {
                return;
            }
            scrollEl.scrollTop += oe.deltaY;
            kickMonTableScrollSync();
            e.preventDefault();
        });
        syncMonTableScrollPositions($wrapper, scrollEl, true);
        muteFcVerticalScrollHandlers(api);
        $wrapper.data('monScrollWired', true);
    }

    function monTableRowKey($tr) {
        var $cb = $tr.find('input[type="checkbox"]').first();
        if ($cb.length) {
            return String($cb.val());
        }
        return 'idx:' + $tr.index();
    }

    function monTableRowsByKey($wrapper, key) {
        return $wrapper.find('.dataTables_scrollBody tbody tr, .DTFC_LeftBodyLiner tbody tr').filter(function () {
            return monTableRowKey(jQuery(this)) === key;
        });
    }

    function wireMonTableRowHover(api) {
        if (!api || !window.jQuery) {
            return;
        }
        var $wrapper = jQuery(api.table().container());
        $wrapper.off('.monRowHover');
        $wrapper.on('mouseenter.monRowHover', '.dataTables_scrollBody tbody tr, .DTFC_LeftBodyLiner tbody tr', function () {
            var key = monTableRowKey(jQuery(this));
            $wrapper.find('.dataTables_scrollBody tbody tr, .DTFC_LeftBodyLiner tbody tr').removeClass('is-row-hover');
            monTableRowsByKey($wrapper, key).addClass('is-row-hover');
        });
        $wrapper.on('mouseleave.monRowHover', '.dataTables_scrollBody tbody tr, .DTFC_LeftBodyLiner tbody tr', function (e) {
            var key = monTableRowKey(jQuery(this));
            var $relatedRow = jQuery(e.relatedTarget).closest('.dataTables_scrollBody tbody tr, .DTFC_LeftBodyLiner tbody tr');
            if ($relatedRow.length && monTableRowKey($relatedRow) === key) {
                return;
            }
            monTableRowsByKey($wrapper, key).removeClass('is-row-hover');
        });
    }

    function enforceMonColumnWidths(api) {
        if (!api || !window.jQuery) {
            return;
        }
        syncColumnVisibilityFromSettings(api);
        if (!api.settings()[0]) {
            return;
        }

        var $wrapper = jQuery(api.table().container());
        var plan = buildColumnWidthPlan(api);
        if (!plan) {
            return;
        }

        clearHiddenColumnInlineWidths(api, plan);
        var settings = plan.settings;
        if (!settings._oFixedColumns) {
            $wrapper.find('.dataTables_scrollHead table, .dataTables_scrollBody table').each(function () {
                applyColgroupWidths(jQuery(this), plan.colgroup);
            });
        }

        var $bodyTable = $wrapper.find('.dataTables_scrollBody table');
        var $headInner = $wrapper.find('.dataTables_scrollHeadInner');
        var totalW = applyPlanColumnWidths(api, plan);

        $bodyTable.width(totalW);
        $headInner.find('table').width(totalW);

        var $scrollBody = $wrapper.find('.dataTables_scrollBody');
        if ($scrollBody.length) {
            var barGap = $scrollBody[0].offsetWidth - $scrollBody[0].clientWidth;
            $headInner.css('padding-right', barGap > 0 ? barGap + 'px' : '0');
            $headInner.css('width', $scrollBody.innerWidth() + 'px');
            if (!$wrapper.data('monScrollWired')) {
                wireMonTableScrollSync(api);
            } else {
                syncMonTableScrollPositions($wrapper, $scrollBody[0]);
            }
        } else {
            $headInner.width($bodyTable.parent().innerWidth());
        }

        syncFixedLeftBlock(api);
    }

    function applyVisibleColumnWidths(api) {
        enforceMonColumnWidths(api);
    }

    function finalizeMonTableLayout(api, options) {
        options = options || {};
        if (!api) {
            return;
        }
        if (monTableLayoutLocked && !options.force) {
            return;
        }

        var lockedHere = false;
        if (!monTableLayoutLocked) {
            monTableLayoutLocked = true;
            lockedHere = true;
        }

        try {
            syncColumnVisibilityFromSettings(api);
            api.columns.adjust();
            clearMonTableRowInlineHeights(api);
            destroyFixedColumns(api);
            enforceMonColumnWidths(api);
            ensureFixedColumns(api);
            if (typeof api.fixedColumns === 'function') {
                api.fixedColumns().relayout();
            }
            applyVisibleColumnWidths(api);
            syncFixedLeftBlock(api);
            syncMonTableRowHeights(api);
            wireMonTableRowHover(api);
            requestAnimationFrame(function () {
                syncMonTableRowHeights(api);
                remeasureMainTableLeftHiddenWidths(api);
                requestAnimationFrame(function () {
                    relayoutMonTableFcLayout(api);
                });
            });
            if (options.markInitialDone !== false) {
                monTableInitialLayoutDone = true;
            }
        } finally {
            if (lockedHere) {
                monTableLayoutLocked = false;
            }
        }
    }

    function runKeywordsTableRelayout(api, options, done) {
        options = options || {};
        monTableLayoutLocked = true;
        updateMonTableStickyTop();
        if (options.adjustColumns !== false) {
            api.columns.adjust();
        }
        requestAnimationFrame(function () {
            try {
                if (options.rebuildFixedColumns) {
                    clearMonTableRowInlineHeights(api);
                    destroyFixedColumns(api);
                }
                enforceMonColumnWidths(api);
                relayoutFixedColumns(api, { rebuild: false });
                wireMonTableRowHover(api);
            } catch (relayoutErr) {
                console.error('monitoring table relayout failed', relayoutErr);
            } finally {
                monTableLayoutLocked = false;
                if (typeof done === 'function') {
                    done();
                }
            }
        });
    }

    var monTableInitialLayoutDone = false;

    function tableScrollHeight(api) {
        if (root.getAttribute('data-view') !== 'keywords') {
            return null;
        }

        var rowH = TABLE_ROW_HEIGHT_FALLBACK;
        if (api && window.jQuery) {
            var $row = jQuery(api.table().container()).find(
                '.dataTables_scrollBody tbody tr:visible:first, .DTFC_LeftBodyLiner tbody tr:visible:first'
            );
            if ($row.length) {
                var measured = Math.ceil($row.outerHeight());
                if (measured >= 24 && measured <= 72) {
                    rowH = measured;
                }
            }
        }

        return TABLE_VISIBLE_ROWS * rowH;
    }

    function fitMonTableScrollArea(api) {
        if (!api || !window.jQuery) {
            return;
        }
        var settings = api.settings()[0];
        if (!settings || !settings.oScroll || !settings.oScroll.sY) {
            return;
        }

        var maxPx = tableScrollHeight(api);
        if (!maxPx) {
            return;
        }

        var $scrollBody = jQuery(settings.nScrollBody);
        if (!$scrollBody.length) {
            return;
        }

        var heightPx = maxPx + 'px';
        settings.oScroll.sY = heightPx;
        $scrollBody.css({
            maxHeight: heightPx,
            height: heightPx,
            paddingBottom: '0',
        });
    }

    function afterMonTableDraw(api) {
        if (!api || monColumnTogglePending || monTableLayoutLocked || !monTableInitialLayoutDone) {
            return;
        }
        wireMonTableRowHover(api);
        requestAnimationFrame(function () {
            if (monColumnTogglePending || monTableLayoutLocked) {
                return;
            }
            enforceMonColumnWidths(api);
            syncMonTableRowHeights(api);
            remeasureMainTableLeftHiddenWidths(api);
            requestAnimationFrame(function () {
                relayoutMonTableFcLayout(api);
            });
        });
    }

    function updateMonTableStickyTop() {
        var nav = document.getElementById('header-nav-bar');
        var top = nav ? Math.ceil(nav.getBoundingClientRect().height) : 0;
        root.style.setProperty('--mon-table-sticky-top', top + 'px');
    }

    function relayoutKeywordsTable(done, options) {
        if (typeof done === 'object' && done !== null && typeof options === 'undefined') {
            options = done;
            done = null;
        }
        options = options || {};

        if (!window.jQuery || !jQuery.fn.DataTable) {
            if (typeof done === 'function') {
                done();
            }
            return;
        }
        var $table = jQuery('#monitoringTable');
        if (!$table.length || !jQuery.fn.DataTable.isDataTable($table)) {
            if (typeof done === 'function') {
                done();
            }
            return;
        }

        if (relayoutRunning) {
            relayoutPendingOptions = options;
            if (typeof done === 'function') {
                done();
            }
            return;
        }

        relayoutRunning = true;
        var api = $table.DataTable();
        var finish = function () {
            relayoutRunning = false;
            if (relayoutPendingOptions) {
                var pending = relayoutPendingOptions;
                relayoutPendingOptions = null;
                relayoutKeywordsTable(null, pending);
            }
        };

        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                runKeywordsTableRelayout(api, options, function () {
                    finish();
                    if (typeof done === 'function') {
                        done();
                    }
                });
            });
        });
    }

    function queueColumnVisibilityRelayout(api) {
        if (!api) {
            return;
        }
        monColumnTogglePending = true;
        clearTimeout(relayoutTimer);
        clearTimeout(columnToggleTimer);
        columnToggleTimer = setTimeout(function () {
            syncColumnVisibilityFromSettings(api);

            var finalized = false;
            function runFinalize() {
                if (finalized) {
                    return;
                }
                finalized = true;
                requestAnimationFrame(function () {
                    requestAnimationFrame(function () {
                        finalizeMonTableLayout(api, { force: true });
                        monColumnTogglePending = false;
                    });
                });
            }

            api.one('draw.dt.monColToggle', function () {
                runFinalize();
            });

            api.columns.adjust().draw(false);

            setTimeout(function () {
                if (monColumnTogglePending) {
                    runFinalize();
                }
            }, 800);
        }, COLUMN_TOGGLE_DEBOUNCE_MS);
    }

    function relayoutAfterColumnToggle(api) {
        queueColumnVisibilityRelayout(api);
    }

    function scheduleRelayoutKeywordsTable(options) {
        options = options || {};
        if (monColumnTogglePending || monTableLayoutLocked) {
            return;
        }
        var debounce = options.debounce != null ? options.debounce : 0;
        clearTimeout(relayoutTimer);
        relayoutTimer = setTimeout(function () {
            if (monColumnTogglePending || monTableLayoutLocked) {
                return;
            }
            relayoutKeywordsTable(function () {
                if (typeof options.done === 'function') {
                    options.done();
                }
            }, options.relayoutOptions || { adjustColumns: true });
        }, debounce);
    }

    function setTablePanelCollapsed(collapsed) {
        var panel = document.getElementById('cabinet-mon-show-table-host');
        if (!panel) {
            return;
        }
        panel.classList.toggle('cabinet-mon-view-panel--collapsed', collapsed);
        panel.classList.remove('d-none');
    }

    function setView(mode) {
        if (mode !== 'overview' && mode !== 'keywords') {
            mode = 'keywords';
        }
        root.setAttribute('data-view', mode);
        try {
            localStorage.setItem(storageKey, mode);
            if (mode === 'overview') {
                window.location.hash = 'overview';
            } else {
                window.location.hash = 'keywords';
            }
        } catch (e) {}

        root.querySelectorAll('[data-mon-view-tab]').forEach(function (btn) {
            var active = btn.getAttribute('data-mon-view-tab') === mode;
            btn.classList.toggle('active', active);
            btn.setAttribute('aria-selected', active ? 'true' : 'false');
        });

        root.querySelectorAll('[data-mon-view-panel]').forEach(function (panel) {
            var panelMode = panel.getAttribute('data-mon-view-panel');
            var show = panelMode === mode;
            if (panel.id === 'cabinet-mon-show-table-host') {
                setTablePanelCollapsed(!show);
                return;
            }
            panel.classList.toggle('d-none', !show);
        });

        root.querySelectorAll('[data-mon-view-hint]').forEach(function (hint) {
            hint.classList.toggle('d-none', hint.getAttribute('data-mon-view-hint') !== mode);
        });

        if (mode === 'keywords') {
            scheduleRelayoutKeywordsTable();
        }
    }

    root.querySelectorAll('[data-mon-view-tab]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            setView(btn.getAttribute('data-mon-view-tab'));
        });
    });

    var initial = 'keywords';
    if (window.location.hash === '#overview') {
        initial = 'overview';
    } else if (window.location.hash === '#keywords' || window.location.hash === '#detailed') {
        initial = 'keywords';
    } else {
        try {
            var saved = localStorage.getItem(storageKey);
            if (saved === 'overview') {
                initial = 'overview';
            } else if (saved === 'detailed') {
                initial = 'keywords';
            }
        } catch (e2) {}
    }
    setView(initial);
    updateMonTableStickyTop();

    function formatDelta(val) {
        if (val === null || val === undefined || val === '') {
            return '';
        }
        var n = Number(val);
        if (isNaN(n) || n === 0) {
            return '';
        }
        return (n > 0 ? '+' : '') + n;
    }

    function deltaClass(val) {
        var n = Number(val);
        if (isNaN(n) || n === 0) {
            return '';
        }
        return n > 0 ? 'is-up' : 'is-down';
    }

    function setKpiLoading(loading) {
        var kpis = document.getElementById('cabinetMonProjectKpis');
        if (!kpis) {
            return;
        }
        if (loading) {
            kpis.classList.add('is-loading');
            kpis.setAttribute('aria-busy', 'true');
        } else {
            kpis.classList.remove('is-loading');
            kpis.removeAttribute('aria-busy');
        }
    }

    function setKpiLoadError() {
        var kpis = document.getElementById('cabinetMonProjectKpis');
        var loader = document.getElementById('cabinetMonProjectKpisLoader');
        if (!kpis || !loader) {
            return;
        }
        kpis.classList.remove('is-loading');
        kpis.removeAttribute('aria-busy');
        loader.classList.add('is-error');
        var spin = loader.querySelector('.cabinet-mon-loader__icon');
        if (spin) {
            spin.remove();
        }
        var label = loader.querySelector('.cabinet-mon-loader__label');
        if (label) {
            label.textContent = cfg.i18n && cfg.i18n.kpiLoadError ? cfg.i18n.kpiLoadError : 'Ошибка загрузки';
        }
    }

    function hideKpiLoader() {
        setKpiLoading(false);
        var loader = document.getElementById('cabinetMonProjectKpisLoader');
        if (loader) {
            loader.remove();
        }
    }

    function fillKpi(summary) {
        if (!summary) {
            return;
        }
        var map = {
            top1: summary.top1,
            top3: summary.top3,
            top10: summary.top10,
            top30: summary.top30,
            top100: summary.top100,
            middle: summary.middle,
            words: summary.words,
            snapshot_at: summary.snapshot_at,
        };
        Object.keys(map).forEach(function (key) {
            var el = root.querySelector('[data-kpi="' + key + '"]');
            if (el) {
                el.textContent = map[key] !== null && map[key] !== undefined && map[key] !== '' ? map[key] : '—';
            }
        });
        [
            ['top1', summary.diff_top1],
            ['top3', summary.diff_top3],
            ['top10', summary.diff_top10],
            ['top30', summary.diff_top30],
            ['top100', summary.diff_top100],
        ].forEach(function (pair) {
            var el = root.querySelector('[data-kpi-delta="' + pair[0] + '"]');
            if (!el) {
                return;
            }
            el.textContent = formatDelta(pair[1]);
            el.className = 'cabinet-mon-project-kpi__delta ' + deltaClass(pair[1]);
        });

        var hintEl = root.querySelector('[data-kpi-hint="snapshot"]');
        if (hintEl) {
            if (summary.snapshot_scope === 'region') {
                hintEl.textContent = cfg.i18n && cfg.i18n.kpiSnapshotRegion ? cfg.i18n.kpiSnapshotRegion : '';
            } else {
                hintEl.textContent = cfg.i18n && cfg.i18n.kpiSnapshotProject ? cfg.i18n.kpiSnapshotProject : '';
            }
        }

        if (summary.scope_label) {
            root.querySelectorAll('.cabinet-mon-project-kpi__scope').forEach(function (el) {
                el.textContent = summary.scope_label;
            });
        }
    }

    function loadKpi() {
        if (!cfg.statsUrl || !cfg.projectId) {
            hideKpiLoader();
            return;
        }
        setKpiLoading(true);
        var url =
            cfg.statsUrl +
            (cfg.statsUrl.indexOf('?') >= 0 ? '&' : '?') +
            'projectId=' +
            encodeURIComponent(cfg.projectId) +
            '&summaryOnly=1';
        var params = new URLSearchParams(window.location.search);
        if (params.get('region')) {
            url += '&regionId=' + encodeURIComponent(params.get('region'));
        }
        fetch(url, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        })
            .then(function (r) {
                if (!r.ok) {
                    throw new Error('kpi stats failed');
                }
                return r.json();
            })
            .then(function (data) {
                hideKpiLoader();
                if (data && data.summary) {
                    fillKpi(data.summary);
                }
            })
            .catch(function () {
                setKpiLoadError();
            });
    }

    if (cfg.initialSummary) {
        fillKpi(cfg.initialSummary);
        hideKpiLoader();
    } else {
        loadKpi();
    }

    var resizeRelayoutTimer;
    var zoomRelayoutTimer;

    function scheduleMonTableViewportRelayout() {
        updateMonTableStickyTop();
        if (root.getAttribute('data-view') !== 'keywords') {
            return;
        }
        clearTimeout(zoomRelayoutTimer);
        zoomRelayoutTimer = setTimeout(function () {
            var api = window.__cabinetMonKeywordsTableApi;
            if (api) {
                relayoutMonTableFcLayout(api);
                return;
            }
            scheduleRelayoutKeywordsTable({
                relayoutOptions: { adjustColumns: true },
            });
        }, 120);
    }

    window.addEventListener('resize', function () {
        clearTimeout(resizeRelayoutTimer);
        resizeRelayoutTimer = setTimeout(function () {
            scheduleRelayoutKeywordsTable({
                relayoutOptions: { adjustColumns: true },
            });
        }, RESIZE_RELAYOUT_DEBOUNCE_MS);
        scheduleMonTableViewportRelayout();
    });

    if (window.visualViewport) {
        window.visualViewport.addEventListener('resize', scheduleMonTableViewportRelayout);
        window.visualViewport.addEventListener('scroll', scheduleMonTableViewportRelayout);
    }

    window.cabinetMonitoringShowChrome = {
        relayoutKeywordsTable: relayoutKeywordsTable,
        scheduleRelayoutKeywordsTable: scheduleRelayoutKeywordsTable,
        relayoutAfterColumnToggle: relayoutAfterColumnToggle,
        queueColumnVisibilityRelayout: queueColumnVisibilityRelayout,
        applyVisibleColumnWidths: applyVisibleColumnWidths,
        finalizeMonTableLayout: finalizeMonTableLayout,
        fitMonTableScrollArea: fitMonTableScrollArea,
        enforceMonColumnWidths: enforceMonColumnWidths,
        ensureFixedColumns: ensureFixedColumns,
        relayoutFixedColumns: relayoutFixedColumns,
        afterMonTableDraw: afterMonTableDraw,
        onTableReady: function (api, options) {
            options = options || {};
            window.__cabinetMonKeywordsTableApi = api;
            if (!monTableInitialLayoutDone) {
                requestAnimationFrame(function () {
                    finalizeMonTableLayout(api);
                });
            }
            if (options.skipRelayout) {
                return;
            }
            if (root.getAttribute('data-view') === 'keywords') {
                scheduleRelayoutKeywordsTable();
            }
        },
    };
})();
