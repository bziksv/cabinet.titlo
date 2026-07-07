/**
 * Мониторинг позиций v2 — плитки и таблица (/monitoring-v2).
 */
(function ($, window) {
    'use strict';

    const cfg = window.cabinetMonV2Config;
    if (!cfg || !cfg.projectCount) {
        return;
    }

    const $root = $('#cabinet-mon-v2-root');
    const $grid = $('#cabinet-mon-v2-grid');
    const $skeleton = $('#cabinet-mon-v2-skeleton');
    const $progress = $('#cabinet-mon-v2-progress');
    const $progressBar = $progress.find('.progress-bar');
    const $progressLabel = $('#cabinet-mon-v2-progress-label');
    const $noResults = $('#cabinet-mon-v2-no-results');
    const $search = $('#cabinet-mon-v2-search');
    const $statusFilter = $('#cabinet-mon-v2-status-filter');
    const $loadError = $('#cabinet-mon-v2-load-error');
    const $tablePanel = $('#cabinet-mon-v2-table-panel');
    const $cardsPanel = $('#cabinet-mon-v2-cards-panel');
    const $viewCards = $('#cabinet-mon-v2-view-cards');
    const $viewTable = $('#cabinet-mon-v2-view-table');
    const $columnsMenu = $('#cabinet-mon-v2-columns-menu');
    const VIEW_STORAGE_KEY = 'cabinet-mon-v2-view';

    const LIST_COLUMN_DEFAULTS = {
        top3: true,
        top5: true,
        top10: true,
        top30: true,
        top100: true,
        middle: true,
        words: true,
        users: true,
        engines: true,
        budget: true,
        mastered: true,
    };

    const LIST_COLUMN_META = [
        { key: 'top3', label: function () { return cfg.i18n.colTop3; } },
        { key: 'top5', label: function () { return cfg.i18n.colTop5; } },
        { key: 'top10', label: function () { return cfg.i18n.colTop10; } },
        { key: 'top30', label: function () { return cfg.i18n.colTop30; } },
        { key: 'top100', label: function () { return cfg.i18n.colTop100; } },
        { key: 'middle', label: function () { return cfg.i18n.colMiddle; } },
        { key: 'words', label: function () { return cfg.i18n.colWords; } },
        { key: 'users', label: function () { return cfg.i18n.colUsers; } },
        { key: 'engines', label: function () { return cfg.i18n.colEngines; } },
        { key: 'budget', label: function () { return cfg.i18n.colBudget; } },
        { key: 'mastered', label: function () { return cfg.i18n.colMastered; } },
    ];

    let listColumnPrefs = Object.assign({}, LIST_COLUMN_DEFAULTS, cfg.listColumns || {});
    let saveColumnsTimer = null;

    let allRows = [];
    let dataTable = null;
    let tableFilterFn = null;
    let monV2DtSettings = null;
    let filterDebounceTimer = null;
    const FILTER_DEBOUNCE_MS = 220;
    let snapshotFillActive = false;
    let faviconFillActive = false;
    let snapshotFillSteps = 0;
    let faviconFillSteps = 0;
    let listReady = false;
    let faviconScheduleTimer = null;
    let snapshotFillLastForce = false;
    let snapshotFillRetries = 0;
    const SNAPSHOT_FILL_MAX_STEPS = 40;
    const FAVICON_FILL_MAX_STEPS = 28;
    const FAVICON_FILL_MAX_ZERO_STEPS = 5;
    const FAVICON_FILL_MAX_RETRIES = 2;
    let faviconFillRetries = 0;
    let faviconFillZeroSteps = 0;
    let faviconFillAfterRefresh = false;
    const faviconRefreshBusy = {};
    let childRowsPrefetchTimer = null;
    const CHILD_ROWS_PREFETCH_MS = 120;
    const CHILD_ROWS_WARM_FIRST = 6;
    const CHILD_ROWS_WARM_STAGGER_MS = 280;
    const CHILD_ROWS_HTML_GEN = 'p8';
    const childRowsHtmlCache = {};

    function getChildRowsCachedHtml(projectId) {
        const entry = childRowsHtmlCache[String(projectId)];
        if (entry && entry.gen === CHILD_ROWS_HTML_GEN && entry.html) {
            return entry.html;
        }
        return null;
    }

    function setChildRowsCachedHtml(projectId, html) {
        childRowsHtmlCache[String(projectId)] = { gen: CHILD_ROWS_HTML_GEN, html: html };
    }

    function getCardChildRowsHtml($card, projectId) {
        if ($card.length && $card.data('childRowsHtmlGen') === CHILD_ROWS_HTML_GEN) {
            return $card.data('childRowsHtml');
        }
        return getChildRowsCachedHtml(projectId);
    }

    function setCardChildRowsHtml($card, projectId, html) {
        if ($card.length) {
            $card.data('childRowsHtml', html);
            $card.data('childRowsHtmlGen', CHILD_ROWS_HTML_GEN);
        }
        setChildRowsCachedHtml(projectId, html);
    }
    const childRowsFetchPromises = {};
    const SNAPSHOT_FILL_MAX_RETRIES = 2;

    const monV2AdminDebug = !!cfg.adminDebug;
    const monV2DebugSession = cfg.debugSessionId || '';
    let monV2ClientDebugLines = [];
    let monV2LastServerDebugLog = [];
    let monV2LastDebugState = null;
    let monV2DebugReqCount = 0;
    let monV2DebugRenderTimer = null;

    function monV2DebugLine(level, message, context) {
        if (!monV2AdminDebug) {
            return;
        }
        const t = new Date();
        const ts =
            t.toLocaleTimeString('ru-RU') + '.' + String(t.getMilliseconds()).padStart(3, '0');
        const ctx = context ? ' ' + JSON.stringify(context) : '';
        monV2ClientDebugLines.push('[' + ts + '] [' + level + '] [client] ' + message + ctx);
        if (monV2ClientDebugLines.length > 120) {
            monV2ClientDebugLines = monV2ClientDebugLines.slice(-120);
        }
        if (monV2DebugRenderTimer) {
            return;
        }
        monV2DebugRenderTimer = window.setTimeout(function () {
            monV2DebugRenderTimer = null;
            renderMonV2DebugLog(null, null);
        }, 200);
    }

    window.cabinetMonV2DebugLine = monV2DebugLine;

    function monV2PostData(extra) {
        const data = Object.assign({ _token: cfg.csrf }, extra || {});
        if (monV2AdminDebug && monV2DebugSession) {
            data.debug_session = monV2DebugSession;
        }
        return data;
    }

    function countSnapshotsPending(payload) {
        if (payload && typeof payload.snapshots_pending === 'number') {
            return payload.snapshots_pending;
        }
        const rows = payload && payload.projects ? payload.projects : [];
        let n = 0;
        rows.forEach(function (row) {
            if (
                row.top10 == null &&
                row.top30 == null &&
                row.words == null &&
                row.middle == null
            ) {
                n += 1;
            }
        });
        return n;
    }

    function applyMonV2DebugResponse(res) {
        if (res && res.debug_admin) {
            renderMonV2DebugLog(res.debug_log || [], res.debug_state || null);
        }
    }

    function renderMonV2DebugLog(serverLog, debugState) {
        if (!monV2AdminDebug) {
            return;
        }

        const $panel = $('#cabinet-mon-v2-admin-debug');
        const $pre = $('#cabinet-mon-v2-debug-log');
        if (!$panel.length || !$pre.length) {
            return;
        }

        $panel.show();
        $('#cabinet-mon-v2-debug-session').text(monV2DebugSession || '—');
        $('#cabinet-mon-v2-debug-req').text(String(monV2DebugReqCount));

        if (Array.isArray(serverLog)) {
            monV2LastServerDebugLog = serverLog;
        }
        if (debugState) {
            monV2LastDebugState = debugState;
        }

        const state = debugState || monV2LastDebugState;
        const serverEntries =
            Array.isArray(serverLog) && serverLog.length ? serverLog : monV2LastServerDebugLog;

        const lines = [];
        if (state) {
            lines.push('--- state ---');
            lines.push(JSON.stringify(state, null, 2));
        }
        if (serverEntries.length) {
            lines.push('--- server ---');
            serverEntries.forEach(function (row) {
                const ctx =
                    row.context && Object.keys(row.context).length
                        ? ' ' + JSON.stringify(row.context)
                        : '';
                lines.push('[' + row.t + '] [' + row.level + '] ' + row.message + ctx);
            });
        }
        if (monV2ClientDebugLines.length) {
            lines.push('--- browser ---');
            Array.prototype.push.apply(lines, monV2ClientDebugLines);
        }
        $pre.text(lines.join('\n'));
        $pre.scrollTop($pre[0].scrollHeight);
    }

    function escHtml(str) {
        return String(str == null ? '' : str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function formatTop(value) {
        if (value == null || value === '') {
            return '—';
        }
        const s = String(value);
        return s.indexOf('%') >= 0 ? s : s + '%';
    }

    function projectHost(url) {
        return String(url || '')
            .replace(/^https?:\/\//i, '')
            .split('/')[0]
            .split(':')[0];
    }

    function projectInitial(url) {
        const host = projectHost(url);
        return (host.charAt(0) || '?').toUpperCase();
    }

    /** Относительный URL с того же origin, что страница (не server url() с другим host). */
    function projectFaviconUrl(row) {
        const srcId = row.favicon_src_project_id;
        if (srcId && cfg.faviconProjectUrlTemplate) {
            let u = cfg.faviconProjectUrlTemplate.replace('__ID__', String(srcId));
            const v = row.favicon_v;
            if (v) {
                u += (u.indexOf('?') >= 0 ? '&' : '?') + 'v=' + encodeURIComponent(String(v));
            }
            return u;
        }
        return row.favicon_url ? row.favicon_url : '';
    }

    function faviconSrcIdFromUpdate(row, update) {
        if (update.favicon_src_project_id != null) {
            return update.favicon_src_project_id;
        }
        const url = update.favicon_url || '';
        let m = url.match(/monitoring-favicons\/(\d+)\.png/i);
        if (m) {
            return parseInt(m[1], 10);
        }
        m = url.match(/[?&]project=(\d+)/i);
        if (m) {
            return parseInt(m[1], 10);
        }
        return row.favicon_src_project_id || null;
    }

    function patchFaviconFields(row, update) {
        if (!update) {
            return row;
        }
        const next = Object.assign({}, row);
        const srcId = faviconSrcIdFromUpdate(row, update);
        if (srcId != null) {
            next.favicon_src_project_id = srcId;
            if (update.favicon_v != null) {
                next.favicon_v = update.favicon_v;
            }
        }
        if (update.favicon_url) {
            next.favicon_url = update.favicon_url;
        }
        const built = projectFaviconUrl(next);
        if (built) {
            next.favicon_url = built;
        }
        return next;
    }

    function showFaviconFallback(img) {
        img.style.display = 'none';
        const wrap = img.closest('.cabinet-mon-v2-project-cell__icon');
        if (!wrap) {
            return;
        }
        const fallback = wrap.querySelector('.cabinet-mon-v2-project-cell__fallback');
        if (fallback) {
            fallback.classList.add('is-visible');
        }
    }

    function faviconRouteFallbackSrc(failedSrc) {
        const m =
            String(failedSrc || '').match(/monitoring-favicons\/(\d+)\.png/i) ||
            String(failedSrc || '').match(/[?&]project=(\d+)/i);
        if (!m || !cfg.faviconProjectUrlTemplate) {
            return '';
        }
        return cfg.faviconProjectUrlTemplate.replace('__ID__', m[1]) + '&refresh=1';
    }

    function wireFaviconImages(root) {
        const imgs = (root || document).querySelectorAll('img.cabinet-mon-v2-project-cell__favicon');
        imgs.forEach(function (img) {
            if (img.getAttribute('data-favicon-wired') === '1') {
                return;
            }
            img.setAttribute('data-favicon-wired', '1');
            img.addEventListener('error', function onFaviconError() {
                const failed = img.src;
                if (img.getAttribute('data-favicon-retried') !== '1') {
                    const retry = faviconRouteFallbackSrc(failed);
                    if (retry && retry !== failed) {
                        img.setAttribute('data-favicon-retried', '1');
                        img.src = retry;
                        return;
                    }
                }
                img.removeEventListener('error', onFaviconError);
                if (window.console && console.warn) {
                    console.warn('[monitoring-v2] favicon load failed', failed);
                }
                showFaviconFallback(img);
            });
        });
    }

    function faviconBtnAttrs(row) {
        const title = escHtml(cfg.i18n.faviconRefreshClick || 'Обновить иконку');
        return (
            ' type="button" class="cabinet-mon-v2-project-cell__icon cabinet-mon-v2-favicon-btn"' +
            ' data-project-id="' +
            escHtml(String(row.id)) +
            '" title="' +
            title +
            '" aria-label="' +
            title +
            '"'
        );
    }

    function applyFaviconToProjectCell(projectId, url) {
        if (!projectId || !url) {
            return;
        }
        $root.find('.cabinet-mon-v2-favicon-btn[data-project-id="' + projectId + '"]').each(function () {
            const $btn = $(this);
            let $img = $btn.find('img.cabinet-mon-v2-project-cell__favicon');
            $btn.find('.cabinet-mon-v2-project-cell__fallback').removeClass('is-visible');
            if (!$img.length) {
                $img = $(
                    '<img class="cabinet-mon-v2-project-cell__favicon" alt="" width="64" height="64" loading="lazy" decoding="async">'
                );
                $btn.prepend($img);
            }
            $img.attr('src', url).attr('data-project-id', String(projectId));
            $img.removeAttr('data-favicon-wired');
            wireFaviconImages($btn[0]);
        });
    }

    function refreshProjectFavicon(projectId) {
        const id = String(projectId);
        if (!cfg.fillFaviconsUrl || !id || faviconRefreshBusy[id]) {
            return;
        }
        faviconRefreshBusy[id] = true;
        $root.find('.cabinet-mon-v2-favicon-btn[data-project-id="' + id + '"]').addClass('is-loading');

        $.ajax({
            type: 'POST',
            url: cfg.fillFaviconsUrl,
            dataType: 'json',
            timeout: 60000,
            data: monV2PostData({ project_id: id }),
        })
            .done(function (res) {
                applyMonV2DebugResponse(res);
                const updates = res && res.updates ? res.updates : [];
                mergeFaviconUpdates(updates);
                const hasIcon = updates.some(function (u) {
                    return u && (u.favicon_src_project_id || u.favicon_url);
                });
                if (hasIcon) {
                    if (typeof toastr !== 'undefined') {
                        toastr.success(cfg.i18n.faviconRefreshed || 'Иконка обновлена');
                    }
                } else if (typeof toastr !== 'undefined') {
                    toastr.warning(cfg.i18n.faviconRefreshFailed || 'Не удалось загрузить иконку');
                }
            })
            .fail(function () {
                if (typeof toastr !== 'undefined') {
                    toastr.error(cfg.i18n.faviconRefreshFailed || 'Не удалось загрузить иконку');
                }
            })
            .always(function () {
                delete faviconRefreshBusy[id];
                $root.find('.cabinet-mon-v2-favicon-btn[data-project-id="' + id + '"]').removeClass(
                    'is-loading'
                );
            });
    }

  /** @param {{ large?: boolean }} opts */
    function renderProjectIcon(row, opts) {
        const large = opts && opts.large;
        const lgCls = large ? ' cabinet-mon-v2-project-cell__icon--lg' : '';
        const initial = escHtml(projectInitial(row.url));
        const favicon = projectFaviconUrl(row);
        if (!favicon) {
            return (
                '<button' +
                faviconBtnAttrs(row) +
                lgCls +
                '><span class="cabinet-mon-v2-project-cell__fallback is-visible">' +
                initial +
                '</span></button>'
            );
        }
        return (
            '<button' +
            faviconBtnAttrs(row) +
            lgCls +
            '>' +
            '<img class="cabinet-mon-v2-project-cell__favicon" src="' +
            escHtml(favicon) +
            '" alt="" width="64" height="64" loading="lazy" decoding="async"' +
            ' data-project-id="' +
            escHtml(String(row.id)) +
            '">' +
            '<span class="cabinet-mon-v2-project-cell__fallback">' +
            initial +
            '</span></button>'
        );
    }

    function parseDiffNum(diff) {
        if (diff == null || diff === '') {
            return null;
        }
        const n = parseFloat(String(diff).replace(/[^\d.\-+]/g, '').replace(',', '.'));
        return Number.isNaN(n) ? null : n;
    }

    /** @param {{ invert?: boolean }} opts — для позиции: меньше = лучше */
    function renderDiff(diff, opts) {
        const n = parseDiffNum(diff);
        if (n === null) {
            return '';
        }
        const invert = opts && opts.invert;
        const improved = invert ? n < 0 : n > 0;
        const worsened = invert ? n > 0 : n < 0;
        const cls = improved
            ? 'cabinet-mon-v2-diff--up'
            : worsened
              ? 'cabinet-mon-v2-diff--down'
              : 'cabinet-mon-v2-diff--flat';
        const text = String(diff).trim();
        return '<span class="cabinet-mon-v2-diff ' + cls + '">' + escHtml(text) + '</span>';
    }

    function topStrengthClass(value) {
        const n = topNum(value);
        if (n < 0) {
            return '';
        }
        if (n >= 70) {
            return 'cabinet-mon-v2-top-cell--strong';
        }
        if (n >= 40) {
            return 'cabinet-mon-v2-top-cell--mid';
        }
        return 'cabinet-mon-v2-top-cell--weak';
    }

    function topFillPct(value) {
        const n = topNum(value);
        if (n < 0) {
            return 0;
        }
        return Math.min(100, Math.max(0, n));
    }

    function renderTopCell(row, size) {
        const value = row['top' + size];
        const diff = row['diff_top' + size];
        const tier = topStrengthClass(value);
        const fill = topFillPct(value);
        return (
            '<div class="cabinet-mon-v2-top-cell cabinet-mon-v2-top-cell--' +
            size +
            ' ' +
            tier +
            '" style="--top-fill:' +
            fill +
            '" title="' +
            escHtml(cfg.i18n.top + size) +
            '">' +
            '<span class="cabinet-mon-v2-top-cell__fill" aria-hidden="true"></span>' +
            '<div class="cabinet-mon-v2-top-cell__content">' +
            '<span class="cabinet-mon-v2-top-cell__value">' +
            escHtml(formatTop(value)) +
            '</span>' +
            renderDiff(diff) +
            '</div></div>'
        );
    }

    function renderTopRow(row, variant) {
        const mod = variant === 'card' ? ' cabinet-mon-v2-top-row--card' : '';
        const sizes = [];
        if (listColumnPrefs.top3 !== false) {
            sizes.push(3);
        }
        if (listColumnPrefs.top5 !== false) {
            sizes.push(5);
        }
        if (listColumnPrefs.top10 !== false) {
            sizes.push(10);
        }
        if (listColumnPrefs.top30 !== false) {
            sizes.push(30);
        }
        if (listColumnPrefs.top100 !== false) {
            sizes.push(100);
        }
        if (!sizes.length) {
            sizes.push(10, 30, 100);
        }
        let html = '<div class="cabinet-mon-v2-top-row' + mod + '" style="--top-cols:' + sizes.length + '">';
        sizes.forEach(function (size) {
            html += renderTopCell(row, size);
        });
        return html + '</div>';
    }

    function isListColumnVisible(key) {
        return listColumnPrefs[key] !== false;
    }

    function buildTopColumn(name, size) {
        return {
            name: name,
            className: 'cabinet-mon-v2-table__col-top align-middle',
            visible: isListColumnVisible(name),
            data: function (row, type) {
                if (type === 'sort' || type === 'type') {
                    return topNum(row['top' + size]);
                }
                return renderTopCell(row, size);
            },
        };
    }

    function applyListColumnVisibility(redraw) {
        if (!dataTable) {
            return;
        }
        LIST_COLUMN_META.forEach(function (meta) {
            const col = dataTable.column(meta.key + ':name');
            if (col.length) {
                col.visible(isListColumnVisible(meta.key), false);
            }
        });
        dataTable.columns.adjust();
        if (redraw !== false) {
            dataTable.draw(false);
        }
    }

    function scheduleSaveListColumns() {
        if (!cfg.saveColumnsUrl) {
            return;
        }
        if (saveColumnsTimer) {
            clearTimeout(saveColumnsTimer);
        }
        saveColumnsTimer = setTimeout(function () {
            axios
                .post(cfg.saveColumnsUrl, { columns: listColumnPrefs })
                .catch(function () {
                    /* prefs stay in UI; retry on next toggle */
                });
        }, 350);
    }

    function initColumnsMenu() {
        if (!$columnsMenu.length) {
            return;
        }
        const header =
            '<p class="dropdown-header mb-1 px-2 py-0 small text-uppercase text-secondary">' +
            escHtml(cfg.i18n.columnsMenu || 'Столбцы') +
            '</p>';
        let body = '';
        LIST_COLUMN_META.forEach(function (meta) {
            const id = 'cabinet-mon-v2-col-' + meta.key;
            const checked = isListColumnVisible(meta.key) ? ' checked' : '';
            body +=
                '<label class="dropdown-item-text d-flex align-items-center gap-2 py-1 px-2 mb-0" for="' +
                id +
                '">' +
                '<input type="checkbox" class="form-check-input m-0 cabinet-mon-v2-col-toggle" id="' +
                id +
                '" data-col="' +
                meta.key +
                '"' +
                checked +
                '>' +
                '<span class="small">' +
                escHtml(meta.label()) +
                '</span></label>';
        });
        $columnsMenu.html(header + body);
    }

    function wireColumnsMenu() {
        $columnsMenu.on('change', '.cabinet-mon-v2-col-toggle', function () {
            const key = $(this).data('col');
            if (!key) {
                return;
            }
            listColumnPrefs[key] = $(this).is(':checked');
            applyListColumnVisibility(true);
            scheduleSaveListColumns();
        });
    }

    function renderProjectCell(row) {
        const showUrl = cfg.showUrlTemplate.replace('__ID__', row.id);
        return (
            '<div class="cabinet-mon-v2-project-cell">' +
            renderProjectIcon(row) +
            '<div class="cabinet-mon-v2-project-cell__body">' +
            '<a class="cabinet-mon-v2-project-cell__domain" href="https://' +
            escHtml(row.url) +
            '" target="_blank" rel="noopener">' +
            escHtml(row.url) +
            '</a>' +
            '<div class="cabinet-mon-v2-project-cell__name-row">' +
            '<a class="cabinet-mon-v2-project-cell__name" href="' +
            escHtml(showUrl) +
            '">' +
            escHtml(row.name || '—') +
            '</a>' +
            renderPublicShareBadge(row) +
            '</div>' +
            '</div></div>'
        );
    }

    function renderPublicShareBadge(row) {
        const ps = row.public_share;
        if (!ps || !ps.active) {
            return '';
        }
        const title = ps.expires_label
            ? escHtml(ps.expires_label)
            : escHtml(cfg.i18n.publicShareActive || 'Public link active');
        const url = ps.url ? escHtml(ps.url) : '#';
        return (
            '<a href="' +
            url +
            '" target="_blank" rel="noopener" class="cabinet-mon-v2-public-share-badge badge rounded-pill text-bg-success text-decoration-none" title="' +
            title +
            '" data-bs-toggle="tooltip">' +
            '<i class="bi bi-share-fill" aria-hidden="true"></i>' +
            '<span class="cabinet-mon-v2-public-share-badge__text">' +
            escHtml(cfg.i18n.publicShareBadge || 'Guest link') +
            '</span></a>'
        );
    }

    function renderQuickActions(row, forTable) {
        const id = row.id;
        const showUrl = cfg.showUrlTemplate.replace('__ID__', id);
        const expandCls = forTable
            ? 'btn btn-outline-secondary btn-sm cabinet-mon-v2-table-expand'
            : 'btn btn-outline-secondary btn-sm cabinet-mon-v2-card__expand';
        return (
            '<div class="cabinet-mon-v2-quick-actions">' +
            '<a href="' +
            escHtml(showUrl) +
            '" class="btn btn-primary btn-sm"><i class="bi bi-graph-up me-1" aria-hidden="true"></i>' +
            escHtml(cfg.i18n.openPositions) +
            '</a>' +
            '<button type="button" class="' +
            expandCls +
            '" data-expanded="0" title="' +
            escHtml(cfg.i18n.expandRegions) +
            '"><i class="bi bi-layers" aria-hidden="true"></i>' +
            (forTable ? '' : '<span class="ms-1">' + escHtml(cfg.i18n.expandRegions) + '</span>') +
            '</button>' +
            renderMenu(row.actions) +
            '</div>'
        );
    }

    function formatMoney(value) {
        if (typeof window.currencyFormatRu === 'function') {
            return currencyFormatRu(value || 0);
        }
        return value || '0';
    }

    function topNum(value) {
        if (value == null || value === '') {
            return -1;
        }
        return parseFloat(String(value).replace('%', '').replace(',', '.')) || -1;
    }

    function masteredDayPercent(row) {
        const budget = parseFloat(row.budget) || 0;
        const mastered = parseFloat(row.mastered) || 0;
        if (!budget || !mastered) {
            return 0;
        }
        const pct = Math.floor(mastered / (budget / 30) * 100);
        return Number.isNaN(pct) ? 0 : pct;
    }

    function renderBudgetCell(row) {
        let html = escHtml(formatMoney(row.budget));
        const pct = row.mastered_percent != null && row.mastered_percent !== ''
            ? parseFloat(String(row.mastered_percent).replace(',', '.'))
            : 0;
        if (pct > 0) {
            html += '<sup class="text-success ms-1">' + escHtml(String(Math.floor(pct)) + '%') + '</sup>';
        }
        return html;
    }

    function renderMasteredCell(row) {
        const mastered = parseFloat(row.mastered) || 0;
        let html = escHtml(formatMoney(mastered));
        if (mastered > 0) {
            const pct = masteredDayPercent(row);
            if (pct > 0) {
                html += '<br><small class="text-success">' + escHtml(String(pct) + '%') + '</small>';
            }
        }
        return html;
    }

    function prepareRows(rows) {
        return (rows || []).map(function (row) {
            const next = Object.assign({}, row);
            next._statusCodes = userStatusCodes(next);
            next._searchBlob = searchBlob(next);
            const built = projectFaviconUrl(next);
            if (built) {
                next.favicon_url = built;
            }
            return next;
        });
    }

    function getViewMode() {
        try {
            const stored = window.localStorage.getItem(VIEW_STORAGE_KEY);
            if (stored === 'table' || stored === 'cards') {
                return stored;
            }
        } catch (e) {
            /* ignore */
        }
        return cfg.defaultView === 'table' ? 'table' : 'cards';
    }

    function setViewMode(mode, skipSave) {
        const isTable = mode === 'table';
        $viewCards.toggleClass('active', !isTable);
        $viewTable.toggleClass('active', isTable);
        $cardsPanel.toggleClass('d-none', isTable);
        $tablePanel.toggleClass('d-none', !isTable);
        $noResults.toggleClass('d-none', true);

        if (!skipSave) {
            try {
                window.localStorage.setItem(VIEW_STORAGE_KEY, mode);
            } catch (e) {
                /* ignore */
            }
        }

        if (isTable && allRows.length) {
            initDataTable();
            applyTableFilters();
            wireAvatarImages($tablePanel[0]);
            wireFaviconImages($tablePanel[0]);
        }
    }

    function userStatusCodes(row) {
        return (row.users || [])
            .map(function (u) {
                return u.status_code || '';
            })
            .filter(Boolean)
            .join(',');
    }

    function searchBlob(row) {
        const parts = [row.url, row.name];
        (row.users || []).forEach(function (u) {
            if (u && u.name) {
                parts.push(u.name);
            }
            if (u && u.role_title) {
                parts.push(u.role_title);
            }
        });
        return parts
            .filter(Boolean)
            .join(' ')
            .toLowerCase();
    }

    function rowMatchesFilters(row) {
        if (!row) {
            return true;
        }
        const q = ($search.val() || '').trim().toLowerCase();
        const status = $statusFilter.val() || '';
        const blob = row._searchBlob || searchBlob(row);
        if (q) {
            const matched = window.cabinetMonitoringSearch
                ? window.cabinetMonitoringSearch.matches(q, blob)
                : String(blob).indexOf(q) >= 0;
            if (!matched) {
                return false;
            }
        }
        if (status && String(row._statusCodes || '').indexOf(status) < 0) {
            return false;
        }
        return true;
    }

    function filterRowData(settings, dataIndex) {
        const ao = settings.aoData && settings.aoData[dataIndex];
        return ao && ao._aData ? ao._aData : null;
    }

    function metricChip(label, innerHtml, modifier) {
        const mod = modifier ? ' cabinet-mon-v2-metric--' + modifier : '';
        return (
            '<div class="cabinet-mon-v2-metric' +
            mod +
            '">' +
            '<span class="cabinet-mon-v2-metric__label">' +
            escHtml(label) +
            '</span>' +
            '<span class="cabinet-mon-v2-metric__value">' +
            innerHtml +
            '</span></div>'
        );
    }

    function engineChipMeta(engine) {
        const key = String(engine || '').toLowerCase();
        if (key === 'yandex') {
            return {
                mod: 'yandex',
                icon: 'fab fa-yandex fa-sm',
                label: 'Яндекс',
            };
        }
        if (key === 'google') {
            return {
                mod: 'google',
                icon: 'fab fa-google fa-sm',
                label: 'Google',
            };
        }
        return {
            mod: 'other',
            icon: 'bi bi-globe2',
            label: key || 'ПС',
        };
    }

    function normalizeEngineRegions(engineRegions, engineKey) {
        const raw =
            engineRegions && engineRegions[engineKey] ? engineRegions[engineKey] : [];
        return raw.map(function (item) {
            if (typeof item === 'string') {
                return {
                    name: item,
                    schedule: '',
                    schedule_short: '',
                    manual: true,
                    mode: 'manual',
                };
            }
            return item;
        });
    }

    function engineScheduleSummary(regions) {
        if (!regions.length) {
            return '';
        }
        const lines = regions.map(function (r) {
            return r.schedule_short || r.schedule || '';
        });
        const unique = [];
        lines.forEach(function (line) {
            if (line && unique.indexOf(line) === -1) {
                unique.push(line);
            }
        });
        if (unique.length === 1) {
            return unique[0];
        }
        if (regions.length > 1) {
            const tpl = cfg.i18n.scheduleRegionsCount || ':n регионов';
            return tpl.replace(':n', String(regions.length));
        }

        return unique[0] || '';
    }

    function engineRegionsTooltip(meta, engineKey, engineRegions) {
        const regions = normalizeEngineRegions(engineRegions, engineKey);
        const label = meta.label;
        const manualLabel = cfg.i18n.scheduleManual || 'Ручной съём';
        if (!regions.length) {
            return label;
        }
        return [label]
            .concat(
                regions.map(function (region) {
                    const sched = region.schedule || manualLabel;
                    return region.name + ': ' + sched;
                })
            )
            .join('\n');
    }

    function renderEngines(engines, engineRegions) {
        if (!engines || !engines.length) {
            return '<span class="text-secondary small">—</span>';
        }
        return (
            '<span class="cabinet-mon-v2-engines">' +
            engines
                .map(function (engine) {
                    const meta = engineChipMeta(engine);
                    const engineKey = String(engine || '').toLowerCase();
                    const regions = normalizeEngineRegions(engineRegions, engineKey);
                    const tipPlain = engineRegionsTooltip(meta, engineKey, engineRegions);
                    const scheduleLine = engineScheduleSummary(regions);
                    const scheduleClass = scheduleLine && regions[0] && regions[0].manual
                        ? 'cabinet-mon-v2-engine-schedule--manual'
                        : '';
                    return (
                        '<span class="cabinet-mon-v2-engine-wrap">' +
                        '<span class="cabinet-mon-v2-engine cabinet-mon-v2-engine--' +
                        meta.mod +
                        '" data-bs-toggle="tooltip" data-bs-custom-class="cabinet-mon-v2-engine-tooltip" data-bs-placement="top" title="' +
                        escHtml(tipPlain) +
                        '"><i class="' +
                        escHtml(meta.icon) +
                        '" aria-hidden="true"></i><span class="visually-hidden">' +
                        escHtml(tipPlain) +
                        '</span></span>' +
                        (scheduleLine
                            ? '<span class="cabinet-mon-v2-engine-schedule ' +
                              scheduleClass +
                              '" aria-hidden="true">' +
                              escHtml(scheduleLine) +
                              '</span>'
                            : '') +
                        '</span>'
                    );
                })
                .join('') +
            '</span>'
        );
    }

    const RENDER_BATCH = 18;

    function avatarImageSrc(url) {
        if (!url) {
            return '';
        }
        const s = String(url);
        if (s.indexOf('user-icon.svg') >= 0) {
            return '';
        }
        return s;
    }

    function renderUsers(users) {
        if (!users || !users.length) {
            return '';
        }
        const items = users
            .map(function (u) {
                const liClass =
                    'list-inline-item position-relative' + (u.can_change_status ? ' change-user-status' : '');
                const avatarClass =
                    'cabinet-mon-v2-avatar table-avatar img-circle' +
                    (u.is_admin ? ' admin-monitoring' : '');
                const initials = escHtml(u.initials || '?');
                const imgSrc = avatarImageSrc(u.image);
                let html =
                    '<li class="' +
                    liClass +
                    '" user-id="' +
                    u.id +
                    '" project-id="' +
                    u.project_id +
                    '" data-bs-toggle="tooltip" title="' +
                    escHtml(u.name + ' — ' + (u.role_title || '')) +
                    '">';
                html += '<span class="' + avatarClass + '" role="img" aria-label="' + escHtml(u.name) + '">';
                html += '<span class="cabinet-mon-v2-avatar__initials">' + initials + '</span>';
                if (imgSrc) {
                    html +=
                        '<img class="cabinet-mon-v2-avatar__img" src="' +
                        escHtml(imgSrc) +
                        '" alt="" loading="lazy" decoding="async">';
                }
                html += '</span>';
                if (u.can_detach) {
                    html +=
                        '<span class="badge badge-secondary navbar-badge detach-user" data-id="' +
                        u.id +
                        '" data-project="' +
                        u.project_id +
                        '" style="cursor:pointer;top:-5px;right:0;font-size:x-small"><i class="fas fa-times"></i></span>';
                }
                html += '</li>';
                return html;
            })
            .join('');

        return '<ul class="list-inline user-list mb-0">' + items + '</ul>';
    }

    function renderMenu(actions) {
        if (!actions || !actions.length) {
            return '';
        }

        let menuHtml = '';
        actions.forEach(function (a) {
            if (a.kind === 'detach_self') {
                menuHtml += '<div class="dropdown-divider"></div>';
            }

            switch (a.kind) {
                case 'link':
                    menuHtml +=
                        '<a class="dropdown-item" href="' +
                        escHtml(a.href) +
                        '"><i class="' +
                        escHtml(a.icon) +
                        ' me-2"></i>' +
                        escHtml(a.label) +
                        '</a>';
                    break;
                case 'add_user':
                    menuHtml +=
                        '<a class="dropdown-item add-user" href="#" data-id="' +
                        a.id +
                        '"><i class="' +
                        escHtml(a.icon) +
                        ' me-2"></i>' +
                        escHtml(a.label) +
                        '</a>';
                    break;
                case 'copy':
                    menuHtml +=
                        '<a class="dropdown-item copy-project" href="#" data-action="' +
                        escHtml(a.href) +
                        '"><i class="' +
                        escHtml(a.icon) +
                        ' me-2"></i>' +
                        escHtml(a.label) +
                        '</a>';
                    break;
                case 'modal':
                    menuHtml +=
                        '<a class="dropdown-item" href="#" data-bs-toggle="modal" data-target=".modal" data-type="' +
                        escHtml(a.modal) +
                        '" data-id="' +
                        a.id +
                        '"><i class="' +
                        escHtml(a.icon) +
                        ' me-2"></i>' +
                        escHtml(a.label) +
                        '</a>';
                    break;
                case 'detach_self':
                    menuHtml +=
                        '<a class="dropdown-item detach-user" href="#" data-id="' +
                        a.user_id +
                        '" data-project="' +
                        a.project_id +
                        '"><i class="' +
                        escHtml(a.icon) +
                        ' me-2"></i>' +
                        escHtml(a.label) +
                        '</a>';
                    break;
                case 'public_share':
                    menuHtml +=
                        '<a class="dropdown-item cabinet-mon-v2-public-share-open" href="#" data-id="' +
                        a.id +
                        '"><i class="' +
                        escHtml(a.icon) +
                        ' me-2"></i>' +
                        escHtml(a.label) +
                        '</a>';
                    break;
                default:
                    break;
            }
        });

        return (
            '<div class="btn-group cabinet-mon-v2-row-menu">' +
            '<button type="button" data-bs-toggle="dropdown" aria-expanded="false" class="btn btn-outline-secondary btn-sm cabinet-mon-v2-card__menu-btn">' +
            '<i class="fas fa-bars" aria-hidden="true"></i></button>' +
            '<div class="dropdown-menu dropdown-menu-end shadow-sm">' +
            menuHtml +
            '</div></div>'
        );
    }

    function buildCard(row) {
        const id = row.id;
        const showUrl = cfg.showUrlTemplate.replace('__ID__', id);
        const posVal = row.middle != null ? escHtml(String(row.middle)) : '—';
        const top10Val = escHtml(formatTop(row.top10));

        return (
            '<article class="cabinet-mon-v2-card cabinet-mon-v2-card--pro" data-project-id="' +
            id +
            '" data-search="' +
            escHtml(searchBlob(row)) +
            '" data-status-codes="' +
            escHtml(userStatusCodes(row)) +
            '">' +
            '<div class="cabinet-mon-v2-card__check">' +
            '<input type="checkbox" class="form-check-input cabinet-mon-v2-card__checkbox" id="mon-v2-check-' +
            id +
            '" aria-label="' +
            escHtml(row.name || row.url) +
            '">' +
            '</div>' +
            '<header class="cabinet-mon-v2-card__head cabinet-mon-v2-card__head--pro">' +
            renderProjectIcon(row, { large: true }) +
            '<div class="cabinet-mon-v2-card__titles flex-grow-1">' +
            '<a class="cabinet-mon-v2-card__domain" href="https://' +
            escHtml(row.url) +
            '" target="_blank" rel="noopener">' +
            escHtml(row.url) +
            '</a>' +
            '<div class="cabinet-mon-v2-card__name-row">' +
            '<h3 class="cabinet-mon-v2-card__name mb-0"><a class="cabinet-mon-v2-card__name-link text-decoration-none" href="' +
            escHtml(showUrl) +
            '">' +
            escHtml(row.name || '—') +
            '</a></h3>' +
            renderPublicShareBadge(row) +
            '</div>' +
            '</div>' +
            '<div class="cabinet-mon-v2-card__menu">' +
            renderMenu(row.actions) +
            '</div>' +
            '</header>' +
            '<div class="cabinet-mon-v2-card__hero">' +
            '<div class="cabinet-mon-v2-hero-kpi">' +
            '<span class="cabinet-mon-v2-hero-kpi__label">' +
            escHtml(cfg.i18n.position) +
            '</span>' +
            '<span class="cabinet-mon-v2-hero-kpi__value">' +
            posVal +
            renderDiff(row.diff_middle, { invert: true }) +
            '</span></div>' +
            '<div class="cabinet-mon-v2-hero-kpi cabinet-mon-v2-hero-kpi--accent">' +
            '<span class="cabinet-mon-v2-hero-kpi__label">' +
            escHtml(cfg.i18n.top + '10') +
            '</span>' +
            '<span class="cabinet-mon-v2-hero-kpi__value">' +
            top10Val +
            renderDiff(row.diff_top10) +
            '</span></div>' +
            '<div class="cabinet-mon-v2-hero-kpi">' +
            '<span class="cabinet-mon-v2-hero-kpi__label">' +
            escHtml(cfg.i18n.words) +
            '</span>' +
            '<span class="cabinet-mon-v2-hero-kpi__value">' +
            escHtml(row.words != null ? row.words : '—') +
            '</span></div>' +
            '</div>' +
            renderTopRow(row, 'card') +
            '<div class="cabinet-mon-v2-card__meta">' +
            '<div class="cabinet-mon-v2-card__users">' +
            renderUsers(row.users) +
            '</div>' +
            '<div class="cabinet-mon-v2-card__engines">' +
            renderEngines(row.engines, row.engine_regions) +
            '</div>' +
            '<div class="cabinet-mon-v2-card__budget small text-secondary">' +
            escHtml(cfg.i18n.budget) +
            ': ' +
            renderBudgetCell(row) +
            (isListColumnVisible('mastered')
                ? '<br>' +
                  escHtml(cfg.i18n.mastered) +
                  ': ' +
                  renderMasteredCell(row)
                : '') +
            '</div>' +
            '</div>' +
            '<footer class="cabinet-mon-v2-card__foot">' +
            renderQuickActions(row, false) +
            '</footer>' +
            '<div class="cabinet-mon-v2-card__detail d-none"></div>' +
            '</article>'
        );
    }

    let loadedCount = 0;

    function getDashboardRows() {
        if (getViewMode() === 'table' && dataTable) {
            return dataTable.rows({ search: 'applied' }).data().toArray();
        }
        if (getViewMode() === 'cards') {
            const ids = [];
            $grid.find('.cabinet-mon-v2-card:not(.d-none)').each(function () {
                ids.push(String($(this).data('project-id')));
            });
            if (!ids.length || ids.length >= allRows.length) {
                return allRows;
            }
            return allRows.filter(function (r) {
                return ids.indexOf(String(r.id)) >= 0;
            });
        }
        return allRows;
    }

    function refreshDashboard() {
        if (!window.cabinetMonV2Dashboard || !allRows.length) {
            return;
        }
        const rows = getDashboardRows();
        const filtered = rows.length < allRows.length;
        window.cabinetMonV2Dashboard.render(rows, filtered);
    }

    function updateProgress(fetching) {
        if (fetching) {
            $progressBar.css('width', '35%');
            $progressLabel.text(cfg.i18n.loadingList || 'Загрузка списка проектов…');
            return;
        }
        const pct = cfg.projectCount ? Math.round((loadedCount / cfg.projectCount) * 100) : 100;
        $progressBar.css('width', pct + '%');
        $progressLabel.text(cfg.i18n.loading + ': ' + loadedCount + ' / ' + cfg.projectCount);
    }

    function wireAvatarImages(root) {
        const imgs = (root || $grid[0]).querySelectorAll('img.cabinet-mon-v2-avatar__img');
        imgs.forEach(function (img) {
            if (img.getAttribute('data-wired') === '1') {
                return;
            }
            img.setAttribute('data-wired', '1');

            function showPhoto() {
                const wrap = img.closest('.cabinet-mon-v2-avatar');
                if (wrap) {
                    wrap.classList.add('cabinet-mon-v2-avatar--photo');
                }
            }

            if (img.complete && img.naturalWidth > 0) {
                showPhoto();
                return;
            }

            img.addEventListener('load', showPhoto);
            img.addEventListener('error', function () {
                img.remove();
            });
        });
    }

    function finishLoading() {
        $skeleton.addClass('d-none').attr('aria-hidden', 'true');
        $progress.addClass('d-none');
        if (getViewMode() === 'table') {
            $cardsPanel.addClass('d-none');
            $tablePanel.removeClass('d-none');
            initDataTable();
            applyTableFilters();
            wireAvatarImages($tablePanel[0]);
            wireFaviconImages($tablePanel[0]);
            refreshProjectIconsFromRowData();
        } else {
            $cardsPanel.removeClass('d-none');
            $tablePanel.addClass('d-none');
            applyFilters();
            wireAvatarImages($grid[0]);
            wireFaviconImages($grid[0]);
        }
        $root.find('[data-bs-toggle="tooltip"]').tooltip({ animation: false, trigger: 'hover' });
        refreshDashboard();
    }

    function showGridEarly() {
        if ($grid.hasClass('d-none')) {
            $skeleton.addClass('d-none').attr('aria-hidden', 'true');
            $grid.removeClass('d-none');
        }
    }

    function showLoadError(message) {
        $skeleton.addClass('d-none');
        $grid.addClass('d-none');
        $progress.addClass('d-none');
        if ($loadError.length) {
            $loadError.removeClass('d-none').text(message);
        } else if (typeof toastr !== 'undefined') {
            toastr.error(message);
        }
    }

    function accumulateRowKpi() {
        loadedCount += 1;
    }

    function ingestAll(rows) {
        $grid.empty();
        loadedCount = 0;

        if (!rows || !rows.length) {
            finishLoading();
            return;
        }

        const temp = document.createElement('div');
        let index = 0;
        const total = rows.length;

        function renderBatch() {
            const fragment = document.createDocumentFragment();
            const end = Math.min(index + RENDER_BATCH, total);

            for (; index < end; index += 1) {
                const row = rows[index];
                accumulateRowKpi();
                temp.innerHTML = buildCard(row);
                while (temp.firstChild) {
                    fragment.appendChild(temp.firstChild);
                }
            }

            $grid[0].appendChild(fragment);
            updateProgress();

            if (index < total) {
                showGridEarly();
                wireAvatarImages($grid[0]);
                wireFaviconImages($grid[0]);
                requestAnimationFrame(renderBatch);
                return;
            }

            finishLoading();
        }

        requestAnimationFrame(renderBatch);
    }

    function updateSelectionBadge() {
        const $badge = $('#cabinet-mon-v2-selection-badge');
        if (!$badge.length) {
            return;
        }
        let n = 0;
        if (getViewMode() === 'table' && dataTable) {
            n = dataTable.rows({ selected: true }).count();
        } else {
            n = $grid.find('.cabinet-mon-v2-card__checkbox:checked').length;
        }
        $badge.text(n).toggleClass('d-none', n < 1);
    }

    function syncCardSelectedState() {
        $grid.find('.cabinet-mon-v2-card').each(function () {
            const checked = $(this).find('.cabinet-mon-v2-card__checkbox').prop('checked');
            $(this).toggleClass('cabinet-mon-v2-card--selected', !!checked);
        });
    }

    function updateSnapshotProgress(pending, total) {
        const t = total || allRows.length || cfg.projectCount || 0;
        const p = pending || 0;
        const done = Math.max(0, t - p);
        $progress.removeClass('d-none');
        $progressBar.css('width', t ? Math.round((done / t) * 100) + '%' : '0%');
        const tpl = cfg.i18n.snapshotsLoading || 'Метрики проектов: :done / :total';
        $progressLabel.text(tpl.replace(':done', done).replace(':total', t));
    }

    function refreshTableFromAllRows() {
        if (!dataTable) {
            return;
        }
        dataTable.clear();
        dataTable.rows.add(allRows);
        applyListColumnVisibility(false);
        dataTable.draw(false);
        $tablePanel.find('[data-bs-toggle="tooltip"]').tooltip({ animation: false, trigger: 'hover' });
        wireAvatarImages($tablePanel[0]);
        wireFaviconImages($tablePanel[0]);
    }

    function patchDataTableRows(byId, fullRedraw) {
        if (!dataTable) {
            return;
        }
        if (fullRedraw) {
            refreshTableFromAllRows();
            return;
        }
        let patched = false;
        dataTable.rows().every(function () {
            const data = this.data();
            const patch = byId[String(data.id)];
            if (!patch) {
                return;
            }
            this.data(Object.assign({}, data, patch));
            this.invalidate();
            patched = true;
        });
        if (patched) {
            dataTable.draw(false);
            $tablePanel.find('[data-bs-toggle="tooltip"]').tooltip({ animation: false, trigger: 'hover' });
            wireAvatarImages($tablePanel[0]);
            wireFaviconImages($tablePanel[0]);
        }
    }

    function mergePublicShareUpdate(projectId, share) {
        const patch = {
            public_share: {
                active: !!(share && share.active),
                url: share && share.url ? share.url : null,
                expires_label: share && share.expires_label ? share.expires_label : null,
            },
        };
        mergeSnapshotUpdates([{ id: projectId, public_share: patch.public_share }], true);
    }

    function mergeSnapshotUpdates(updates, fullRedraw) {
        if (!updates || !updates.length) {
            return;
        }
        const byId = {};
        updates.forEach(function (u) {
            if (u && u.id != null) {
                byId[String(u.id)] = u;
            }
        });

        let touched = false;
        allRows = allRows.map(function (row) {
            const patch = byId[String(row.id)];
            if (!patch) {
                return row;
            }
            touched = true;
            return Object.assign({}, row, patch);
        });

        if (!touched) {
            return;
        }

        if (dataTable) {
            patchDataTableRows(byId, !!fullRedraw);
        } else if (getViewMode() === 'cards' && $grid.children().length) {
            ingestAll(allRows);
        }

        refreshDashboard();
    }

    function stopSnapshotFill(scheduleFavicon) {
        snapshotFillActive = false;
        snapshotFillSteps = 0;
        if (dataTable) {
            refreshTableFromAllRows();
        }
        if (!faviconFillActive) {
            $progress.addClass('d-none');
        }
        if (scheduleFavicon !== false) {
            if (faviconFillAfterRefresh) {
                faviconFillAfterRefresh = false;
                startFaviconFill();
            } else {
                scheduleFaviconFillAfterSnapshots();
            }
        }
    }

    function cancelFaviconSchedule() {
        if (faviconScheduleTimer) {
            window.clearTimeout(faviconScheduleTimer);
            faviconScheduleTimer = null;
        }
    }

    function scheduleFaviconFillAfterSnapshots() {
        if (!listReady || snapshotFillActive || faviconFillActive) {
            return;
        }
        cancelFaviconSchedule();
        faviconScheduleTimer = window.setTimeout(function () {
            faviconScheduleTimer = null;
            const startFill = function () {
                if (listReady && !snapshotFillActive && !faviconFillActive) {
                    startFaviconFill();
                }
            };
            if (typeof window.requestIdleCallback === 'function') {
                window.requestIdleCallback(startFill, { timeout: 4000 });
            } else {
                startFill();
            }
        }, 1500);
    }

    function mergeFaviconUpdates(updates) {
        if (!updates || !updates.length) {
            return;
        }
        const byId = {};
        updates.forEach(function (u) {
            if (u && u.id != null) {
                byId[String(u.id)] = u;
            }
        });
        if (!Object.keys(byId).length) {
            return;
        }

        const patches = {};
        allRows = allRows.map(function (row) {
            const update = byId[String(row.id)];
            if (!update) {
                return row;
            }
            const next = patchFaviconFields(row, update);
            patches[String(row.id)] = {
                favicon_src_project_id: next.favicon_src_project_id,
                favicon_v: next.favicon_v,
                favicon_url: next.favicon_url,
            };
            return next;
        });

        Object.keys(byId).forEach(function (id) {
            const row = allRows.find(function (r) {
                return String(r.id) === id;
            });
            if (row) {
                applyFaviconToProjectCell(id, projectFaviconUrl(row));
            }
        });

        if (dataTable) {
            patchDataTableRows(patches, false);
        }
    }

    function refreshProjectIconsFromRowData() {
        allRows.forEach(function (row) {
            if (row && row.id != null && row.favicon_src_project_id) {
                applyFaviconToProjectCell(row.id, projectFaviconUrl(row));
            }
        });
    }

    function countMissingFavicons(payload) {
        const rows = payload && payload.projects ? payload.projects : allRows;
        let n = 0;
        rows.forEach(function (row) {
            if (!row.favicon_src_project_id) {
                n += 1;
            }
        });
        return n;
    }

    function stopFaviconFill() {
        faviconFillActive = false;
        faviconFillSteps = 0;
        faviconFillZeroSteps = 0;
        if (!snapshotFillActive) {
            $progress.addClass('d-none');
        }
    }

    function runFaviconFillStep() {
        if (!faviconFillActive || !cfg.fillFaviconsUrl) {
            return;
        }
        if (snapshotFillActive) {
            return;
        }
        if (faviconFillSteps >= FAVICON_FILL_MAX_STEPS) {
            monV2DebugLine('warn', 'favicons.fill.cap', { steps: faviconFillSteps });
            stopFaviconFill();
            return;
        }
        faviconFillSteps += 1;

        monV2DebugReqCount += 1;
        monV2DebugLine('info', 'ajax.favicons.fill.start', {
            step: faviconFillSteps,
            retry: faviconFillRetries,
        });

        $.ajax({
            type: 'POST',
            url: cfg.fillFaviconsUrl,
            dataType: 'json',
            timeout: 35000,
            data: monV2PostData({ limit: 3 }),
        })
            .done(function (res) {
                faviconFillRetries = 0;
                applyMonV2DebugResponse(res);
                const rebuilt = res && res.rebuilt ? res.rebuilt : 0;
                const propagated = res && res.propagated ? res.propagated : 0;
                const stalled = !!(res && res.stalled);
                monV2DebugLine('info', 'ajax.favicons.fill.done', {
                    rebuilt: rebuilt,
                    propagated: propagated,
                    pending: res && res.pending,
                    wall_ms: res && res.wall_ms,
                    stalled: stalled,
                    failed: res && res.failed,
                });
                mergeFaviconUpdates(res && res.updates ? res.updates : []);
                const pending = res && res.pending ? res.pending : 0;
                if (stalled) {
                    monV2DebugLine('warn', 'favicons.fill.stalled', {
                        pending: pending,
                        failed: res && res.failed,
                    });
                    stopFaviconFill();
                    return;
                }
                if (rebuilt === 0 && propagated === 0 && pending > 0) {
                    faviconFillZeroSteps += 1;
                    if (faviconFillZeroSteps >= FAVICON_FILL_MAX_ZERO_STEPS) {
                        monV2DebugLine('warn', 'favicons.fill.no_progress', {
                            steps: faviconFillZeroSteps,
                            pending: pending,
                        });
                        stopFaviconFill();
                        return;
                    }
                } else {
                    faviconFillZeroSteps = 0;
                }
                if (faviconFillActive && pending > 0) {
                    updateFaviconProgress(pending, allRows.length);
                    runFaviconFillStep();
                    return;
                }
                stopFaviconFill();
            })
            .fail(function (xhr) {
                const status = xhr && xhr.status;
                monV2DebugLine('error', 'ajax.favicons.fill.fail', {
                    status: status,
                    textStatus: xhr && xhr.statusText,
                });
                if (
                    faviconFillRetries < FAVICON_FILL_MAX_RETRIES &&
                    (status === 0 || status === 504 || status === 502)
                ) {
                    faviconFillRetries += 1;
                    window.setTimeout(function () {
                        runFaviconFillStep();
                    }, 600);
                    return;
                }
                faviconFillRetries = 0;
                stopFaviconFill();
            });
    }

    function startFaviconFill() {
        if (!listReady || !cfg.fillFaviconsUrl || snapshotFillActive) {
            return;
        }
        cancelFaviconSchedule();
        faviconFillActive = true;
        faviconFillRetries = 0;
        faviconFillSteps = 0;
        faviconFillZeroSteps = 0;
        runFaviconFillStep();
    }

    function runSnapshotFillStep(force) {
        if (!snapshotFillActive || !cfg.fillSnapshotsUrl) {
            return;
        }
        snapshotFillLastForce = !!force;
        if (snapshotFillSteps >= SNAPSHOT_FILL_MAX_STEPS) {
            monV2DebugLine('warn', 'snapshots.fill.cap', { steps: snapshotFillSteps });
            stopSnapshotFill();
            return;
        }
        snapshotFillSteps += 1;

        monV2DebugReqCount += 1;
        monV2DebugLine('info', 'ajax.snapshots.fill.start', {
            force: !!force,
            step: snapshotFillSteps,
            retry: snapshotFillRetries,
        });

        $.ajax({
            type: 'POST',
            url: cfg.fillSnapshotsUrl,
            dataType: 'json',
            timeout: 45000,
            data: monV2PostData({ force: force ? 1 : 0 }),
        })
            .done(function (res) {
                snapshotFillRetries = 0;
                applyMonV2DebugResponse(res);
                const rebuilt = res && res.rebuilt ? res.rebuilt : 0;
                monV2DebugLine('info', 'ajax.snapshots.fill.done', {
                    rebuilt: rebuilt,
                    pending: res && res.pending,
                    wall_ms: res && res.wall_ms,
                    timed_out: res && res.timed_out,
                });
                mergeSnapshotUpdates(res && res.updates ? res.updates : [], false);
                const pending = res && res.pending ? res.pending : 0;
                if (pending > 0 && snapshotFillActive && (rebuilt > 0 || (res && res.timed_out))) {
                    updateSnapshotProgress(pending, allRows.length);
                    runSnapshotFillStep(false);
                    return;
                }
                stopSnapshotFill();
                if (dataTable && pending > 0 && rebuilt === 0) {
                    if (typeof toastr !== 'undefined') {
                        toastr.info(
                            cfg.i18n.snapshotsPartial ||
                                'Часть проектов без снятых позиций — метрики недоступны'
                        );
                    }
                }
            })
            .fail(function (xhr) {
                const status = xhr && xhr.status;
                monV2DebugLine('error', 'ajax.snapshots.fill.fail', {
                    status: status,
                    textStatus: xhr && xhr.statusText,
                });
                if (
                    snapshotFillRetries < SNAPSHOT_FILL_MAX_RETRIES &&
                    (status === 0 || status === 504 || status === 502)
                ) {
                    snapshotFillRetries += 1;
                    monV2DebugLine('warn', 'ajax.snapshots.fill.retry', {
                        attempt: snapshotFillRetries,
                    });
                    window.setTimeout(function () {
                        runSnapshotFillStep(snapshotFillLastForce);
                    }, 800);
                    return;
                }
                snapshotFillRetries = 0;
                if (typeof toastr !== 'undefined') {
                    toastr.warning(
                        cfg.i18n.snapshotsFillTimeout ||
                            'Пересчёт метрик прерван по таймауту. Нажмите «Обновить» или дождитесь фона.'
                    );
                }
                stopSnapshotFill();
            });
    }

    function startSnapshotFill(force, pendingHint) {
        if (!listReady) {
            return;
        }
        cancelFaviconSchedule();
        stopFaviconFill();
        if (!cfg.fillSnapshotsUrl) {
            scheduleFaviconFillAfterSnapshots();
            return;
        }
        snapshotFillActive = true;
        snapshotFillSteps = 0;
        snapshotFillRetries = 0;
        faviconFillSteps = 0;
        updateSnapshotProgress(pendingHint || allRows.length, allRows.length);
        runSnapshotFillStep(!!force);
    }

    function applyLoadedProjects(payload) {
        allRows = prepareRows(payload && payload.projects ? payload.projects : []);
        loadedCount = allRows.length;

        if (getViewMode() === 'table') {
            if (dataTable) {
                dataTable.clear();
                dataTable.rows.add(allRows);
                applyListColumnVisibility(false);
                dataTable.draw(false);
                refreshProjectIconsFromRowData();
            } else {
                finishLoading();
            }
        } else {
            ingestAll(allRows);
        }
        updateSelectionBadge();
        refreshDashboard();
        warmChildRowsBatch(allRows);
    }

    function loadProjects(forceRefresh) {
        $loadError.addClass('d-none');
        listReady = false;
        faviconFillAfterRefresh = !!forceRefresh;
        cancelFaviconSchedule();
        stopSnapshotFill(false);
        stopFaviconFill();
        $progress.removeClass('d-none');
        loadedCount = 0;
        updateProgress(true);

        const $refreshBtn = $('#cabinet-mon-v2-refresh');
        $refreshBtn.prop('disabled', true).addClass('disabled');

        const started = performance.now();
        monV2DebugReqCount += 1;
        monV2DebugLine('info', 'ajax.list.start', { refresh: !!forceRefresh });

        $.ajax({
            type: 'POST',
            url: cfg.listUrl,
            dataType: 'json',
            timeout: 45000,
            data: monV2PostData({ refresh: forceRefresh ? 1 : 0 }),
        })
            .done(function (payload) {
                applyMonV2DebugResponse(payload);
                if (dataTable) {
                    if (tableFilterFn) {
                        const ext = $.fn.dataTable.ext.search;
                        const idx = ext.indexOf(tableFilterFn);
                        if (idx >= 0) {
                            ext.splice(idx, 1);
                        }
                    }
                    dataTable.destroy();
                    dataTable = null;
                    tableFilterFn = null;
                    monV2DtSettings = null;
                }
                $grid.empty();

                applyLoadedProjects(payload);
                listReady = true;

                const pending = countSnapshotsPending(payload);
                const missingFavicons = countMissingFavicons(payload);
                monV2DebugLine('info', 'list.ready', {
                    pending: pending,
                    missing_favicons: missingFavicons,
                    force: !!forceRefresh,
                });

                if (pending > 0) {
                    startSnapshotFill(forceRefresh, pending);
                } else if (forceRefresh && missingFavicons > 0) {
                    updateFaviconProgress(missingFavicons, allRows.length);
                    startFaviconFill();
                } else if (missingFavicons > 0) {
                    $progress.addClass('d-none');
                    scheduleFaviconFillAfterSnapshots();
                } else {
                    $progress.addClass('d-none');
                }

                monV2DebugLine('info', 'ajax.list.done', {
                    ms: Math.round(performance.now() - started),
                    total: payload && payload.total,
                    snapshots_pending: payload && payload.snapshots_pending,
                });
                if (window.console && console.info) {
                    console.info(
                        '[monitoring-v2] list loaded in',
                        Math.round(performance.now() - started),
                        'ms'
                    );
                }
            })
            .fail(function (xhr) {
                stopSnapshotFill();
                stopFaviconFill();
                monV2DebugLine('error', 'ajax.list.fail', { status: xhr && xhr.status });
                let msg = cfg.i18n.listLoadError || cfg.i18n.loadError;
                if (xhr && xhr.status === 419) {
                    msg = cfg.i18n.sessionExpired || msg;
                } else if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
                showLoadError(msg);
            })
            .always(function () {
                $('#cabinet-mon-v2-refresh').prop('disabled', false).removeClass('disabled');
            });
    }

    function updateFaviconProgress(pending, total) {
        const t = total || allRows.length || cfg.projectCount || 0;
        const p = pending || 0;
        const done = Math.max(0, t - p);
        $progress.removeClass('d-none');
        $progressBar.css('width', t ? Math.round((done / t) * 100) + '%' : '0%');
        const tpl = cfg.i18n.faviconsLoading || 'Иконки проектов: :done / :total';
        $progressLabel.text(tpl.replace(':done', done).replace(':total', t));
    }

    function applyFilters() {
        if (getViewMode() === 'table') {
            applyTableFilters();
            return;
        }

        let visible = 0;

        $grid.find('.cabinet-mon-v2-card').each(function () {
            const $card = $(this);
            const id = String($card.data('project-id') || '');
            const row =
                allRows.find(function (r) {
                    return String(r.id) === id;
                }) || null;
            let show;
            if (row) {
                show = rowMatchesFilters(row);
            } else {
                const q = ($search.val() || '').trim().toLowerCase();
                const status = $statusFilter.val() || '';
                const matchSearch =
                    !q ||
                    (window.cabinetMonitoringSearch
                        ? window.cabinetMonitoringSearch.matches(q, String($card.data('search') || ''))
                        : String($card.data('search') || '').indexOf(q) >= 0);
                const codes = String($card.data('status-codes') || '');
                const matchStatus = !status || codes.indexOf(status) >= 0;
                show = matchSearch && matchStatus;
            }
            $card.toggleClass('d-none', !show);
            if (show) {
                visible += 1;
            }
        });

        $noResults.toggleClass('d-none', visible > 0 || !loadedCount);
        refreshDashboard();
    }

    function applyTableFilters() {
        if (!dataTable) {
            return;
        }
        dataTable.draw(false);
    }

    function scheduleFilters() {
        clearTimeout(filterDebounceTimer);
        filterDebounceTimer = setTimeout(applyFilters, FILTER_DEBOUNCE_MS);
    }

    function initDataTable() {
        if (!$.fn.DataTable) {
            return;
        }

        if (dataTable) {
            dataTable.clear();
            dataTable.rows.add(allRows);
            applyListColumnVisibility(false);
            dataTable.draw(false);
            return;
        }

        if (tableFilterFn) {
            const ext = $.fn.dataTable.ext.search;
            const idx = ext.indexOf(tableFilterFn);
            if (idx >= 0) {
                ext.splice(idx, 1);
            }
        }

        tableFilterFn = function (settings, data, dataIndex, rowData) {
            if (monV2DtSettings && settings !== monV2DtSettings) {
                return true;
            }
            const row = rowData || filterRowData(settings, dataIndex);
            if (!row) {
                return true;
            }
            return rowMatchesFilters(row);
        };
        $.fn.dataTable.ext.search.push(tableFilterFn);

        dataTable = $('#cabinet-mon-v2-projects').DataTable({
            data: allRows,
            dom: 'rt',
            paging: false,
            // searching: false отключает _fnFilterCustom — ext.search не вызывается
            searching: true,
            info: false,
            select: {
                style: 'multi',
                selector: 'td:not(:last-child)',
            },
            language: {
                zeroRecords: cfg.i18n.noResults || '',
            },
            order: [[4, 'desc']],
            columns: [
                {
                    orderable: false,
                    className: 'cabinet-mon-v2-table__col-expand text-center align-middle',
                    data: function () {
                        return '<a href="#" class="dt-control text-muted"><i class="fas fa-plus-circle"></i></a>';
                    },
                },
                {
                    name: 'name',
                    className: 'align-middle',
                    data: 'url',
                    render: function (data, type, row) {
                        if (type === 'display') {
                            return renderProjectCell(row);
                        }
                        if (type === 'filter') {
                            return String(row.url || '') + ' ' + String(row.name || '');
                        }
                        return row.url || '';
                    },
                },
                buildTopColumn('top3', 3),
                buildTopColumn('top5', 5),
                buildTopColumn('top10', 10),
                buildTopColumn('top30', 30),
                buildTopColumn('top100', 100),
                {
                    name: 'middle',
                    className: 'align-middle text-nowrap',
                    visible: isListColumnVisible('middle'),
                    data: function (row, type) {
                        if (type === 'sort' || type === 'type') {
                            return parseFloat(row.middle) || 9999;
                        }
                        const val = row.middle != null ? escHtml(String(row.middle)) : '—';
                        return '<span class="cabinet-mon-v2-position-cell">' + val + '</span>';
                    },
                },
                {
                    name: 'words',
                    className: 'align-middle',
                    visible: isListColumnVisible('words'),
                    data: function (row, type) {
                        if (type === 'sort' || type === 'type') {
                            return parseInt(row.words, 10) || 0;
                        }
                        return row.words != null ? row.words : '—';
                    },
                },
                {
                    orderable: false,
                    name: 'users',
                    className: 'align-middle',
                    visible: isListColumnVisible('users'),
                    data: function (row) {
                        return renderUsers(row.users);
                    },
                },
                {
                    orderable: false,
                    className:
                        'cabinet-mon-v2-table__col-engines text-nowrap align-middle text-center',
                    name: 'engines',
                    visible: isListColumnVisible('engines'),
                    data: function (row) {
                        return renderEngines(row.engines, row.engine_regions);
                    },
                },
                {
                    name: 'budget',
                    className: 'align-middle text-nowrap',
                    visible: isListColumnVisible('budget'),
                    data: function (row, type) {
                        if (type === 'sort' || type === 'type') {
                            return parseFloat(row.budget) || 0;
                        }
                        return renderBudgetCell(row);
                    },
                },
                {
                    name: 'mastered',
                    className: 'align-middle text-nowrap',
                    visible: isListColumnVisible('mastered'),
                    data: function (row, type) {
                        if (type === 'sort' || type === 'type') {
                            return parseFloat(row.mastered) || 0;
                        }
                        return renderMasteredCell(row);
                    },
                },
                {
                    orderable: false,
                    className: 'project-actions align-middle text-end',
                    data: function (row) {
                        return renderQuickActions(row, true);
                    },
                },
            ],
            drawCallback: function () {
                const api = this.api();
                const total = api.rows({ search: 'applied' }).count();
                $('#cabinet-mon-v2-table-info').text(cfg.i18n.projectsCount + ': ' + total);
                $noResults.toggleClass('d-none', total > 0 || !loadedCount);
                $tablePanel.find('[data-bs-toggle="tooltip"]').tooltip({ animation: false, trigger: 'hover' });
                wireAvatarImages($tablePanel[0]);
                wireFaviconImages($tablePanel[0]);
                refreshDashboard();
            },
        });

        monV2DtSettings = dataTable.settings()[0];

        $('#cabinet-mon-v2-projects').on('select.dt deselect.dt', function () {
            updateSelectionBadge();
        });
    }

    function childRowsLoadingHtml() {
        const label = cfg.i18n.loadingRegions || cfg.i18n.loading || 'Загрузка…';
        return (
            '<div class="cabinet-mon-v2-child-loading text-secondary small py-3 px-2 text-center">' +
            '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>' +
            escHtml(label) +
            '</div>'
        );
    }

    function fetchChildRowsHtml(projectId) {
        const key = String(projectId);
        const cached = getChildRowsCachedHtml(key);
        if (cached) {
            return Promise.resolve(cached);
        }
        if (childRowsFetchPromises[key]) {
            return childRowsFetchPromises[key];
        }
        monV2DebugLine('info', 'child-rows.fetch', { project_id: key });
        childRowsFetchPromises[key] = axios
            .get(cfg.childRowsUrlTemplate.replace('__ID__', key))
            .then(function (response) {
                setChildRowsCachedHtml(key, response.data);
                return response.data;
            })
            .finally(function () {
                delete childRowsFetchPromises[key];
            });
        return childRowsFetchPromises[key];
    }

    function stashChildRowsOnRow(tr, html) {
        if (tr && tr.length && html) {
            tr.data('childRowsHtml', html);
            tr.data('childRowsHtmlGen', CHILD_ROWS_HTML_GEN);
        }
    }

    function warmChildRowsBatch(rows) {
        if (!rows || !rows.length) {
            return;
        }
        const ids = rows.slice(0, CHILD_ROWS_WARM_FIRST).map(function (r) {
            return r.id;
        });
        let i = 0;
        function step() {
            if (i >= ids.length) {
                return;
            }
            const id = ids[i];
            i += 1;
            if (!getChildRowsCachedHtml(id)) {
                fetchChildRowsHtml(id).catch(function () {
                    /* тихо */
                });
            }
            window.setTimeout(step, CHILD_ROWS_WARM_STAGGER_MS);
        }
        window.setTimeout(step, 600);
    }

    function childChartWireOptions() {
        return {
            chartsUrl: cfg.chartsUrl || '/monitoring/charts',
            i18n: {
                childChartShow: cfg.i18n.childChartShow,
                childChartHide: cfg.i18n.childChartHide,
                loadError: cfg.i18n.loadError,
            },
        };
    }

    function wireChildRowsCharts($root, projectId) {
        if (!window.cabinetMonitoringChildCharts) {
            return;
        }
        window.cabinetMonitoringChildCharts.wire($root, projectId, childChartWireOptions());
    }

    function decorateChildRowsHtml(html) {
        const $content = $(html);
        $content.find('.top').each(function () {
            const str = $(this).text();
            if (str.indexOf('+') > 0) {
                $(this).addClass('cabinet-mon-v2-grow grow-color');
            }
            if (str.indexOf('-') > 0) {
                $(this).addClass('cabinet-mon-v2-shrink shrink-color');
            }
        });
        return $content;
    }

    function prefetchChildRows(tr, rowData) {
        if (!rowData || !rowData.id || tr.data('childRowsHtml') || tr.data('childRowsLoading')) {
            return;
        }
        tr.data('childRowsLoading', 1);
        fetchChildRowsHtml(rowData.id)
            .then(function (html) {
                stashChildRowsOnRow(tr, html);
            })
            .catch(function () {
                /* тихо — покажем ошибку по клику */
            })
            .finally(function () {
                tr.removeData('childRowsLoading');
            });
    }

    function revealChildRows(tr, row, icon, html) {
        const $content = decorateChildRowsHtml(html);
        stashChildRowsOnRow(tr, html);
        row.child($content).show();
        tr.addClass('shown');
        icon.removeClass('fa-plus-circle fa-spinner fa-spin').addClass('fa-minus-circle');
        $content.find('.tooltip-child-table').tooltip({ animation: false, trigger: 'hover' });
        wireChildRowsCharts($content, row.data().id);
    }

    function showChildRows(tr, row, icon) {
        const rowData = row.data();
        if (!rowData) {
            return;
        }

        const cached =
            (tr.data('childRowsHtmlGen') === CHILD_ROWS_HTML_GEN && tr.data('childRowsHtml')) ||
            getChildRowsCachedHtml(rowData.id);
        if (cached) {
            revealChildRows(tr, row, icon, cached);
            return;
        }

        row.child($(childRowsLoadingHtml())).show();
        tr.addClass('shown');
        icon.removeClass('fa-plus-circle').addClass('fa-spinner fa-spin');

        const started = performance.now();
        fetchChildRowsHtml(rowData.id)
            .then(function (html) {
                revealChildRows(tr, row, icon, html);
                if (window.console && console.info) {
                    console.info(
                        '[monitoring-v2] child-rows',
                        rowData.id,
                        Math.round(performance.now() - started) + ' ms'
                    );
                }
            })
            .catch(function () {
                row.child.hide();
                tr.removeClass('shown');
                icon.removeClass('fa-spinner fa-spin').addClass('fa-plus-circle');
                if (typeof toastr !== 'undefined') {
                    toastr.error(cfg.i18n.loadError);
                }
            });
    }

    $('#cabinet-mon-v2-projects tbody').on('click', 'td .dt-control', function (e) {
        e.preventDefault();
        const icon = $(this).find('i');
        const tr = $(this).closest('tr');
        const row = dataTable.row(tr);

        if (row.child.isShown()) {
            row.child.hide();
            tr.removeClass('shown');
            icon.removeClass('fa-minus-circle fa-spinner fa-spin').addClass('fa-plus-circle');
            return;
        }

        showChildRows(tr, row, icon);
    });

    function scheduleChildRowsPrefetch(tr) {
        if (!dataTable || !tr || !tr.length || !tr.find('.dt-control').length) {
            return;
        }
        const row = dataTable.row(tr);
        const rowData = row.data();
        clearTimeout(childRowsPrefetchTimer);
        childRowsPrefetchTimer = window.setTimeout(function () {
            prefetchChildRows(tr, rowData);
        }, CHILD_ROWS_PREFETCH_MS);
    }

    $('#cabinet-mon-v2-projects tbody').on('mouseenter', 'td .dt-control', function () {
        scheduleChildRowsPrefetch($(this).closest('tr'));
    });

    $('#cabinet-mon-v2-projects tbody').on('mouseenter', 'tr', function () {
        scheduleChildRowsPrefetch($(this));
    });

    function wrapCardDetailTables($root) {
        $root.find('.card-body > table').each(function () {
            const $table = $(this);
            if ($table.parent().hasClass('cabinet-mon-v2-card__detail-scroll')) {
                return;
            }
            $table.wrap('<div class="cabinet-mon-v2-card__detail-scroll"></div>');
        });
    }

    function setCardRegionsOpen($card, open) {
        $card.toggleClass('cabinet-mon-v2-card--regions-open', !!open);
    }

    function applyChildRowsHtml($detail, html) {
        const $content = decorateChildRowsHtml(html);
        wrapCardDetailTables($content);
        const $card = $detail.closest('.cabinet-mon-v2-card');
        $detail.html($content).removeClass('d-none');
        $detail.find('.tooltip-child-table').tooltip({ animation: false, trigger: 'hover' });
        wireChildRowsCharts($detail);
        if ($card.length) {
            setCardRegionsOpen($card, true);
        }
    }

    function toggleExpand($btn) {
        const $card = $btn.closest('.cabinet-mon-v2-card');
        const $detail = $card.find('.cabinet-mon-v2-card__detail');
        const expanded = $btn.attr('data-expanded') === '1';
        const id = $card.data('project-id');
        const cachedHtml = getCardChildRowsHtml($card, id);

        if (expanded) {
            $detail.addClass('d-none');
            setCardRegionsOpen($card, false);
            $btn.attr('data-expanded', '0');
            $btn.find('span').text(cfg.i18n.expandRegions);
            $btn.find('i').removeClass('bi-chevron-up').addClass('bi-chevron-down');
            return;
        }

        if (cachedHtml) {
            applyChildRowsHtml($detail, cachedHtml);
            $btn.attr('data-expanded', '1');
            $btn.find('span').text(cfg.i18n.collapseRegions);
            $btn.find('i').removeClass('bi-chevron-down').addClass('bi-chevron-up');
            return;
        }

        $btn.prop('disabled', true);
        $detail
            .removeClass('d-none')
            .html(
                '<div class="cabinet-mon-v2-card__detail-loading text-secondary small py-3 text-center">' +
                    '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>' +
                    escHtml(cfg.i18n.loadingRegions || cfg.i18n.loading) +
                    '</div>'
            );

        const started = performance.now();

        fetchChildRowsHtml(id)
            .then(function (html) {
                applyChildRowsHtml($detail, html);
                setCardChildRowsHtml($card, id, html);
                $btn.attr('data-expanded', '1');
                $btn.find('span').text(cfg.i18n.collapseRegions);
                $btn.find('i').removeClass('bi-chevron-down').addClass('bi-chevron-up');
                if (window.console && console.info) {
                    console.info(
                        '[monitoring-v2] child-rows',
                        id,
                        Math.round(performance.now() - started) + ' ms'
                    );
                }
            })
            .catch(function () {
                $detail.html(
                    '<p class="text-danger small mb-0 py-2">' + escHtml(cfg.i18n.loadError) + '</p>'
                );
            })
            .finally(function () {
                $btn.prop('disabled', false);
            });
    }

    function getSelectedIds() {
        const ids = [];
        if (getViewMode() === 'table' && dataTable) {
            dataTable.rows({ selected: true }).every(function () {
                const row = this.data();
                if (row && row.id) {
                    ids.push(row.id);
                }
            });
            return ids;
        }
        $grid.find('.cabinet-mon-v2-card__checkbox:checked').each(function () {
            ids.push($(this).closest('.cabinet-mon-v2-card').data('project-id'));
        });
        return ids;
    }

    $search.on('input', scheduleFilters);
    $statusFilter.on('change', function () {
        clearTimeout(filterDebounceTimer);
        applyFilters();
    });

    $viewCards.on('click', function () {
        if (getViewMode() === 'cards') {
            return;
        }
        setViewMode('cards');
        if (allRows.length && !$grid.children().length) {
            ingestAll(allRows);
        } else {
            applyFilters();
        }
    });

    $viewTable.on('click', function () {
        if (getViewMode() === 'table') {
            return;
        }
        setViewMode('table');
    });

    $root.on('click', '.cabinet-mon-v2-card__expand', function () {
        toggleExpand($(this));
    });

    $root.on('click', '.cabinet-mon-v2-table-expand', function (e) {
        e.preventDefault();
        $(this).closest('tr').find('.dt-control').trigger('click');
    });

    $('#cabinet-mon-v2-refresh').on('click', function () {
        loadProjects(true);
    });

    $root.on('click', '.cabinet-mon-v2-favicon-btn', function (e) {
        e.preventDefault();
        e.stopPropagation();
        const projectId = $(this).attr('data-project-id');
        if (projectId) {
            refreshProjectFavicon(projectId);
        }
    });

    $grid.on('change', '.cabinet-mon-v2-card__checkbox', function () {
        syncCardSelectedState();
        updateSelectionBadge();
    });

    $('#cabinet-mon-v2-select-all').on('click', function () {
        if (getViewMode() === 'table' && dataTable) {
            const api = dataTable;
            const selected = api.rows({ selected: true, search: 'applied' }).count();
            const total = api.rows({ search: 'applied' }).count();
            if (selected === total && total > 0) {
                api.rows({ search: 'applied' }).deselect();
            } else {
                api.rows({ search: 'applied' }).select();
            }
            updateSelectionBadge();
            return;
        }
        const $boxes = $grid.find('.cabinet-mon-v2-card:not(.d-none) .cabinet-mon-v2-card__checkbox');
        const allChecked = $boxes.length && $boxes.filter(':checked').length === $boxes.length;
        $boxes.prop('checked', !allChecked);
        syncCardSelectedState();
        updateSelectionBadge();
    });

    $('#cabinet-mon-v2-delete-selected').on('click', function () {
        const ids = getSelectedIds();
        if (!ids.length) {
            toastr.error(cfg.i18n.selectOne);
            return;
        }
        if (!window.confirm(cfg.i18n.confirmDelete)) {
            return;
        }
        ids.forEach(function (id) {
            axios.delete('monitoring/' + id);
            $grid.find('[data-project-id="' + id + '"]').remove();
            allRows = allRows.filter(function (r) {
                return String(r.id) !== String(id);
            });
            if (dataTable) {
                dataTable.rows(function (idx, data) {
                    return String(data.id) === String(id);
                })
                    .remove()
                    .draw(false);
            }
            loadedCount -= 1;
            refreshDashboard();
        });
    });

    window.cabinetMonV2List = {
        reload: function () {
            window.location.reload();
        },
        patchPublicShare: function (projectId, share) {
            mergePublicShareUpdate(projectId, share);
        },
    };

    initColumnsMenu();
    wireColumnsMenu();

    if (monV2AdminDebug) {
        monV2DebugLine('info', 'page.init', {
            projects: cfg.projectCount,
            session: monV2DebugSession,
        });
        $('#cabinet-mon-v2-debug-clear').on('click', function () {
            monV2ClientDebugLines = [];
            monV2LastServerDebugLog = [];
            monV2LastDebugState = null;
            $('#cabinet-mon-v2-debug-log').text('');
        });
        $('#cabinet-mon-v2-debug-copy').on('click', function () {
            const text = $('#cabinet-mon-v2-debug-log').text();
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text);
            }
        });
    }

    setViewMode(getViewMode(), true);
    loadProjects();
})(jQuery, window);
