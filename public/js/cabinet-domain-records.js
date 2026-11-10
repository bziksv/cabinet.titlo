(function () {
    'use strict';

    var root = document.getElementById('cabinetDrPage');
    if (!root) return;

    var lookupUrl = root.getAttribute('data-lookup-url');
    var neighborsUrl = root.getAttribute('data-neighbors-url');
    var historyBase = root.getAttribute('data-history-url');
    var compareUrl = root.getAttribute('data-compare-url');
    var addSiteUrl = root.getAttribute('data-add-site-url');
    var addDomainUrl = root.getAttribute('data-add-domain-url');
    var csrf = root.getAttribute('data-csrf');
    var canSite = root.getAttribute('data-can-site') === '1';
    var canDomain = root.getAttribute('data-can-domain') === '1';
    var canSave = root.getAttribute('data-can-save') === '1';

    var i18n = {
        ipCol: root.getAttribute('data-i18n-ip-col') || 'IP-адрес сайта',
        neighborsCol: root.getAttribute('data-i18n-neighbors-col') || 'На этом IP ещё сайты',
        neighborsLoad: root.getAttribute('data-i18n-neighbors-load') || 'Показать сайты на IP',
        neighborsEmpty: root.getAttribute('data-i18n-neighbors-empty') || 'Других доменов не найдено',
        neighborsSelf: root.getAttribute('data-i18n-neighbors-self') || 'На IP только этот домен (и www)',
        neighborsError: root.getAttribute('data-i18n-neighbors-error') || 'Не удалось получить список доменов на IP',
        neighborsLoading: root.getAttribute('data-i18n-neighbors-loading') || 'Ищем…',
        neighborsViewAll: root.getAttribute('data-i18n-neighbors-view-all') || 'Посмотреть всех',
        neighborsDownload: root.getAttribute('data-i18n-neighbors-download') || 'Скачать список',
        neighborsModalTitle: root.getAttribute('data-i18n-neighbors-modal-title') || 'Сайты на IP :ip',
        neighborsModalCount: root.getAttribute('data-i18n-neighbors-modal-count') || 'Показано :shown из :total',
        neighborsModalSearch: root.getAttribute('data-i18n-neighbors-modal-search') || 'Фильтр по домену…',
        neighborsMore: root.getAttribute('data-i18n-neighbors-more') || 'ещё :more (:total всего)',
        comparePick: root.getAttribute('data-i18n-compare-pick') || 'Выберите две проверки',
        compareTitle: root.getAttribute('data-i18n-compare-title') || 'Сравнение проверок',
    };

    var form = document.getElementById('cabinetDrForm');
    var input = document.getElementById('cabinetDrDomain');
    var saveCb = document.getElementById('cabinetDrSave');
    var submitBtn = document.getElementById('cabinetDrSubmit');
    var statusEl = document.getElementById('cabinetDrStatus');
    var results = document.getElementById('cabinetDrResults');
    var summaryEl = document.getElementById('cabinetDrSummary');
    var whoisEl = document.getElementById('cabinetDrWhois');
    var dnsEl = document.getElementById('cabinetDrDns');
    var dnsTabs = document.getElementById('cabinetDrDnsTabs');
    var ipsEl = document.getElementById('cabinetDrIps');
    var addSiteBtn = document.getElementById('cabinetDrAddSite');
    var addDomainBtn = document.getElementById('cabinetDrAddDomain');
    var historyBody = document.getElementById('cabinetDrHistoryBody');
    var compareBtn = document.getElementById('cabinetDrCompareBtn');
    var compareEl = document.getElementById('cabinetDrCompare');

    var currentDomain = '';
    var currentDns = {};
    var activeDnsType = 'ALL';
    var neighborsCache = {};

    function setStatus(text, kind) {
        statusEl.textContent = text || '';
        statusEl.className = 'cabinet-dr-form__status small' + (kind ? ' is-' + kind : '');
    }

    function escapeHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function postJson(url, body) {
        return fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(body || {}),
        }).then(function (r) {
            return r.text().then(function (text) {
                var data = null;
                if (text) {
                    try {
                        data = JSON.parse(text);
                    } catch (e) {
                        throw new Error('Неверный ответ сервера (' + r.status + ')');
                    }
                }
                return { ok: r.ok, status: r.status, data: data || {} };
            });
        });
    }

    function requestJson(url, method) {
        return fetch(url, {
            method: method || 'GET',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
        }).then(function (r) {
            return r.text().then(function (text) {
                var data = null;
                if (text) {
                    try {
                        data = JSON.parse(text);
                    } catch (e) {
                        throw new Error('Неверный ответ сервера (' + r.status + ')');
                    }
                }
                return { ok: r.ok, status: r.status, data: data || {} };
            });
        });
    }

    function formatHeaderCount(n) {
        var s = String(Math.max(0, Number(n) || 0));
        return s.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    }

    function updateHeaderRemaining(remaining) {
        if (remaining == null) return;
        var header = document.getElementById('cabinet-header-module-limit');
        if (!header) return;
        var strong = header.querySelector('strong.ms-1');
        if (strong) {
            strong.textContent = formatHeaderCount(remaining);
        }
        if (Number(remaining) <= 0) {
            header.classList.add('is-exhausted');
        } else {
            header.classList.remove('is-exhausted');
        }
    }

    function updateSavedCount(count) {
        if (count == null) return;
        root.setAttribute('data-saved-count', String(count));
        var header = document.getElementById('cabinet-header-module-secondary');
        if (!header) return;
        var strong = header.querySelector('strong');
        if (strong) {
            strong.textContent = formatHeaderCount(count);
        }
    }

    function renderSummary(result, already) {
        var s = result.summary || {};
        var whois = result.whois || {};
        var statusClass = s.registered ? 'cabinet-dr-stat--ok' : 'cabinet-dr-stat--bad';
        var statusText = s.registered ? 'Занят' : 'Свободен / ошибка';
        if (whois.status_key === 'free') statusText = 'Свободен';
        if (whois.status_key === 'error') statusText = 'Ошибка WHOIS';

        var days = s.days_until_expiry;
        var daysClass = 'cabinet-dr-stat';
        if (days != null && days <= 30) daysClass += ' cabinet-dr-stat--warn';
        if (days != null && days <= 7) daysClass += ' cabinet-dr-stat--bad';

        summaryEl.innerHTML =
            '<div class="cabinet-dr-stat ' + statusClass + '"><span class="cabinet-dr-stat__label">Статус</span><span class="cabinet-dr-stat__value">' + escapeHtml(statusText) + '</span></div>' +
            '<div class="cabinet-dr-stat"><span class="cabinet-dr-stat__label">Домен</span><span class="cabinet-dr-stat__value">' + escapeHtml(result.domain) + '</span></div>' +
            '<div class="' + daysClass + '"><span class="cabinet-dr-stat__label">До окончания</span><span class="cabinet-dr-stat__value">' + (days != null ? escapeHtml(String(days)) + ' дн.' : '—') + '</span></div>' +
            '<div class="cabinet-dr-stat"><span class="cabinet-dr-stat__label">A / MX</span><span class="cabinet-dr-stat__value">' + (s.a_count || 0) + ' / ' + (s.mx_count || 0) + '</span></div>';

        if (addSiteBtn) {
            addSiteBtn.disabled = !!(already && already.site_monitoring);
            addSiteBtn.textContent = already && already.site_monitoring
                ? 'Уже в мониторинге сайтов'
                : 'Добавить в мониторинг сайтов';
        }
        if (addDomainBtn) {
            addDomainBtn.disabled = !!(already && already.domain_information);
            addDomainBtn.textContent = already && already.domain_information
                ? 'Уже в сроке регистрации'
                : 'Добавить в срок регистрации';
        }
    }

    function renderWhois(whois, domain, punycode) {
        if (!whois) {
            whoisEl.innerHTML = '<p class="cabinet-dr-empty">Нет данных</p>';
            return;
        }
        var ns = (whois.dns_servers || []).map(function (n) {
            return '<div>' + escapeHtml(n) + '</div>';
        }).join('') || '—';

        whoisEl.innerHTML =
            '<dl class="cabinet-dr-kv">' +
            '<dt>Домен</dt><dd>' + escapeHtml(domain || '') + '</dd>' +
            (punycode && punycode !== domain ? '<dt>Punycode</dt><dd><code>' + escapeHtml(punycode) + '</code></dd>' : '') +
            '<dt>Статус</dt><dd>' + escapeHtml(whois.status || '') + '</dd>' +
            '<dt>Регистрация</dt><dd>' + escapeHtml(whois.registered_at || '—') + '</dd>' +
            '<dt>Окончание</dt><dd>' + escapeHtml(whois.expires_at || '—') + '</dd>' +
            '<dt>NS (WHOIS)</dt><dd>' + ns + '</dd>' +
            (whois.message ? '<dt>Комментарий</dt><dd>' + escapeHtml(whois.message) + '</dd>' : '') +
            '</dl>';
    }

    function dnsTypes(dns) {
        var order = ['A', 'AAAA', 'MX', 'NS', 'CNAME', 'TXT', 'SOA', 'SRV'];
        return order.filter(function (t) {
            return (dns[t] || []).length > 0;
        });
    }

    function renderDnsTabs(dns) {
        var types = dnsTypes(dns);
        var html = '<button type="button" class="cabinet-dr-dns-tab' + (activeDnsType === 'ALL' ? ' is-active' : '') + '" data-type="ALL">ALL</button>';
        types.forEach(function (t) {
            html += '<button type="button" class="cabinet-dr-dns-tab' + (activeDnsType === t ? ' is-active' : '') + '" data-type="' + t + '">' +
                t + ' <span class="text-muted">(' + (dns[t] || []).length + ')</span></button>';
        });
        dnsTabs.innerHTML = html;
    }

    function renderDns(dns) {
        currentDns = dns || {};
        if (dnsTabs) {
            renderDnsTabs(currentDns);
        }

        var rows = [];
        var types = activeDnsType === 'ALL' ? dnsTypes(currentDns) : [activeDnsType];
        types.forEach(function (type) {
            (currentDns[type] || []).forEach(function (rec) {
                rows.push(rec);
            });
        });

        if (!rows.length) {
            dnsEl.innerHTML = '<p class="cabinet-dr-empty">DNS-записи не найдены</p>';
            return;
        }

        var html = '<table class="cabinet-dr-dns-table"><thead><tr><th>Тип</th><th>Host</th><th>Значение</th><th>TTL</th></tr></thead><tbody>';
        rows.forEach(function (rec) {
            html += '<tr>' +
                '<td><strong>' + escapeHtml(rec.type || '') + '</strong></td>' +
                '<td>' + escapeHtml(rec.host || '') + '</td>' +
                '<td>' + escapeHtml(rec.value || '') + '</td>' +
                '<td>' + escapeHtml(rec.ttl != null ? String(rec.ttl) : '—') + '</td>' +
                '</tr>';
        });
        html += '</tbody></table>';
        dnsEl.innerHTML = html;
    }

    function renderNeighborsCell(ip, payload) {
        var domains = (payload && payload.domains) || [];
        var status = (payload && payload.status) || (domains.length ? 'ok' : 'empty');
        var message = (payload && payload.message) || '';

        if (status === 'api_error') {
            return '<span class="text-danger small">' +
                escapeHtml(message || i18n.neighborsError) + '</span>';
        }
        if (!domains.length) {
            var emptyText = status === 'self_only'
                ? (message || i18n.neighborsSelf)
                : (message || i18n.neighborsEmpty);
            return '<span class="cabinet-dr-neighbors-empty">' + escapeHtml(emptyText) + '</span>';
        }

        var maxShow = 20;
        var html = '<ul class="cabinet-dr-neighbors">';
        domains.slice(0, maxShow).forEach(function (d) {
            html += '<li><a href="https://' + escapeHtml(d) + '" target="_blank" rel="noopener noreferrer">' +
                escapeHtml(d) + '</a></li>';
        });
        html += '</ul>';

        var actions = '<div class="cabinet-dr-neighbors-actions">';
        if (domains.length > maxShow) {
            var more = domains.length - maxShow;
            actions += '<span class="text-muted small cabinet-dr-neighbors-more">' +
                escapeHtml(
                    i18n.neighborsMore
                        .replace(':more', String(more))
                        .replace(':total', String(domains.length))
                ) +
                '</span>';
            actions += '<button type="button" class="btn btn-sm btn-outline-secondary cabinet-dr-neighbors-view-all" data-ip="' +
                escapeHtml(ip) + '">' + escapeHtml(i18n.neighborsViewAll) + '</button>';
        }
        actions += '<button type="button" class="btn btn-sm btn-outline-secondary cabinet-dr-neighbors-download" data-ip="' +
            escapeHtml(ip) + '"><i class="bi bi-download" aria-hidden="true"></i> ' +
            escapeHtml(i18n.neighborsDownload) + '</button>';
        actions += '</div>';
        html += actions;

        return html;
    }

    function downloadNeighborsList(ip) {
        var payload = neighborsCache[ip];
        var domains = (payload && payload.domains) || [];
        if (!domains.length) {
            return;
        }
        var lines = domains.slice();
        var blob = new Blob(['\uFEFF' + lines.join('\n') + '\n'], { type: 'text/plain;charset=utf-8;' });
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = 'ip-neighbors-' + String(ip).replace(/[^\w.-]+/g, '_') + '.txt';
        document.body.appendChild(a);
        a.click();
        a.remove();
        URL.revokeObjectURL(url);
    }

    function openNeighborsModal(ip) {
        var payload = neighborsCache[ip];
        var domains = (payload && payload.domains) || [];
        if (!domains.length) {
            return;
        }

        var modalEl = document.getElementById('cabinetDrNeighborsModal');
        var titleEl = document.getElementById('cabinetDrNeighborsModalTitle');
        var metaEl = document.getElementById('cabinetDrNeighborsModalMeta');
        var listEl = document.getElementById('cabinetDrNeighborsModalList');
        var searchEl = document.getElementById('cabinetDrNeighborsModalSearch');
        var downloadEl = document.getElementById('cabinetDrNeighborsModalDownload');
        if (!modalEl || !listEl) {
            return;
        }

        if (titleEl) {
            titleEl.textContent = i18n.neighborsModalTitle.replace(':ip', ip);
        }
        if (metaEl) {
            metaEl.textContent = i18n.neighborsModalCount
                .replace(':shown', String(domains.length))
                .replace(':total', String((payload && payload.found_total) || domains.length));
        }
        if (searchEl) {
            searchEl.value = '';
        }

        function paintList(filter) {
            var q = String(filter || '').trim().toLowerCase();
            var html = '';
            var shown = 0;
            domains.forEach(function (d) {
                if (q && String(d).toLowerCase().indexOf(q) === -1) {
                    return;
                }
                shown++;
                html += '<li><a href="https://' + escapeHtml(d) + '" target="_blank" rel="noopener noreferrer">' +
                    escapeHtml(d) + '</a></li>';
            });
            listEl.innerHTML = html || '<li class="text-muted small">—</li>';
            if (metaEl) {
                metaEl.textContent = i18n.neighborsModalCount
                    .replace(':shown', String(shown))
                    .replace(':total', String(domains.length));
            }
        }

        paintList('');
        if (searchEl) {
            searchEl.oninput = function () {
                paintList(searchEl.value);
            };
        }
        if (downloadEl) {
            downloadEl.onclick = function () {
                downloadNeighborsList(ip);
            };
        }

        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        } else if (window.jQuery) {
            window.jQuery(modalEl).modal('show');
        }
    }

    function renderIps(ips) {
        neighborsCache = {};
        if (!ips || !ips.length) {
            ipsEl.innerHTML = '<p class="cabinet-dr-empty">IP-адреса не найдены (нет A/AAAA)</p>';
            return;
        }

        var html = '<div class="table-responsive"><table class="table table-sm cabinet-dr-ip-table">' +
            '<thead><tr><th style="width:12rem">' + escapeHtml(i18n.ipCol) + '</th>' +
            '<th>' + escapeHtml(i18n.neighborsCol) + '</th></tr></thead><tbody>';

        ips.forEach(function (item) {
            var ip = item.ip || '';
            var hostHint = item.hostname && item.hostname !== ip
                ? '<div class="text-muted small">' + escapeHtml(item.hostname) + '</div>'
                : '';
            var preloaded = item.neighbors_loaded
                ? {
                    domains: item.neighbors || [],
                    status: item.neighbors_status || 'ok',
                    message: item.neighbors_message || '',
                    found_total: item.neighbors_count != null
                        ? item.neighbors_count
                        : ((item.neighbors && item.neighbors.length) || 0),
                    truncated: !!item.neighbors_truncated,
                }
                : null;
            if (preloaded) {
                neighborsCache[ip] = preloaded;
            }

            html += '<tr data-ip="' + escapeHtml(ip) + '">' +
                '<td class="cabinet-dr-ip-table__ip">' +
                '<code class="cabinet-dr-ip-code">' + escapeHtml(ip) + '</code>' + hostHint +
                '</td>' +
                '<td class="cabinet-dr-ip-table__neighbors" data-ip-neighbors="' + escapeHtml(ip) + '">' +
                (preloaded
                    ? renderNeighborsCell(ip, preloaded)
                    : '<span class="cabinet-dr-neighbors-loading">' + escapeHtml(i18n.neighborsLoading) + '</span>') +
                '</td></tr>';
        });

        html += '</tbody></table></div>';
        ipsEl.innerHTML = html;

        // Если бэкенд не отдал соседей — догружаем отдельно.
        ips.forEach(function (item) {
            if (item && item.ip && !item.neighbors_loaded) {
                loadNeighbors(item.ip);
            }
        });
    }

    function loadNeighbors(ip) {
        if (!neighborsUrl || !ip) return;
        var cell = ipsEl.querySelector('[data-ip-neighbors="' + ip + '"]');
        if (!cell) return;

        if (neighborsCache[ip]) {
            cell.innerHTML = renderNeighborsCell(ip, neighborsCache[ip]);
            return;
        }

        cell.innerHTML = '<span class="cabinet-dr-neighbors-loading">' + escapeHtml(i18n.neighborsLoading) + '</span>';

        postJson(neighborsUrl, { ip: ip, domain: currentDomain })
            .then(function (res) {
                if (!res.ok) {
                    cell.innerHTML = '<span class="text-danger small">' +
                        escapeHtml((res.data && res.data.message) || i18n.neighborsError) + '</span>';
                    return;
                }
                var payload = {
                    domains: (res.data && res.data.domains) || [],
                    status: (res.data && res.data.status) || 'ok',
                    message: (res.data && res.data.message) || '',
                    found_total: (res.data && res.data.found_total) != null
                        ? res.data.found_total
                        : (((res.data && res.data.domains) || []).length),
                    truncated: !!(res.data && res.data.truncated),
                };
                neighborsCache[ip] = payload;
                cell.innerHTML = renderNeighborsCell(ip, payload);
            })
            .catch(function (err) {
                cell.innerHTML = '<span class="text-danger small">' +
                    escapeHtml((err && err.message) || i18n.neighborsError) + '</span>';
            });
    }

    function showResult(result, already) {
        currentDomain = result.domain || '';
        activeDnsType = 'ALL';
        if (results) {
            results.classList.remove('d-none');
        }
        renderSummary(result, already || {});
        renderWhois(result.whois, result.domain, result.punycode);
        renderDns(result.dns || {});
        renderIps(result.ips || []);
        if (results && typeof results.scrollIntoView === 'function') {
            results.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    function formatNow() {
        var d = new Date();
        function pad(n) { return n < 10 ? '0' + n : String(n); }
        return pad(d.getDate()) + '.' + pad(d.getMonth() + 1) + '.' + d.getFullYear() +
            ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes());
    }

    function historySummaryFromResult(result) {
        var ips = [];
        (result.ips || []).forEach(function (row) {
            if (row && row.ip) {
                ips.push(String(row.ip));
            }
        });
        ips = ips.filter(function (v, i, a) { return a.indexOf(v) === i; });
        var ipLabel = ips.length ? ips.slice(0, 2).join(', ') : '—';
        if (ips.length > 2) {
            ipLabel += ' +' + (ips.length - 2);
        }

        var dnsParts = [];
        var dns = result.dns || {};
        ['A', 'AAAA', 'MX', 'NS', 'TXT', 'SOA', 'CNAME'].forEach(function (type) {
            var n = (dns[type] && dns[type].length) || 0;
            if (n > 0) {
                dnsParts.push(type + ' ' + n);
            }
        });
        var dnsLabel = dnsParts.length ? dnsParts.join(' · ') : '—';

        var neighbors = null;
        var loaded = false;
        (result.ips || []).forEach(function (row) {
            if (row && row.neighbors_loaded) {
                loaded = true;
                neighbors = (neighbors || 0) + ((row.neighbors && row.neighbors.length) || 0);
            }
        });

        return {
            ip: ipLabel,
            dns: dnsLabel,
            neighbors: loaded ? neighbors : null,
        };
    }

    function prependHistoryRow(id, domain, createdAt, summary) {
        if (!historyBody || !id) return;
        var empty = historyBody.querySelector('.cabinet-dr-history-empty');
        if (empty) {
            empty.parentNode.removeChild(empty);
        }
        var sum = summary || { ip: '—', dns: '—', neighbors: null };
        var neighborsHtml = sum.neighbors == null
            ? '<span class="text-muted">—</span>'
            : String(sum.neighbors);
        var tr = document.createElement('tr');
        tr.setAttribute('data-id', String(id));
        tr.innerHTML =
            '<td class="text-center"><input type="checkbox" class="cabinet-dr-history-cmp" value="' + escapeHtml(String(id)) + '"></td>' +
            '<td class="text-nowrap">' + escapeHtml(createdAt || formatNow()) + '</td>' +
            '<td>' + escapeHtml(domain || '') + '</td>' +
            '<td class="small text-nowrap"><code>' + escapeHtml(sum.ip || '—') + '</code></td>' +
            '<td class="small">' + escapeHtml(sum.dns || '—') + '</td>' +
            '<td class="text-nowrap">' + neighborsHtml + '</td>' +
            '<td class="text-nowrap">' +
            '<button type="button" class="btn btn-xs btn-outline-primary cabinet-dr-history-open">Открыть</button> ' +
            '<button type="button" class="btn btn-xs btn-outline-danger cabinet-dr-history-del">Удалить</button>' +
            '</td>';
        historyBody.insertBefore(tr, historyBody.firstChild);
        syncCompareBtn();
    }

    function selectedCompareIds() {
        if (!historyBody) return [];
        var boxes = historyBody.querySelectorAll('.cabinet-dr-history-cmp:checked');
        var ids = [];
        boxes.forEach(function (cb) {
            ids.push(Number(cb.value));
        });
        return ids;
    }

    function syncCompareBtn() {
        if (!compareBtn) return;
        compareBtn.disabled = selectedCompareIds().length !== 2;
    }

    function renderDiffList(items, cls) {
        if (!items || !items.length) return '';
        return items.map(function (v) {
            return '<li class="cabinet-dr-diff ' + cls + '">' + escapeHtml(v) + '</li>';
        }).join('');
    }

    function renderCompare(data) {
        if (!compareEl) return;
        var a = data.a || {};
        var b = data.b || {};
        var diff = data.diff || {};
        var whois = diff.whois || {};
        var dns = diff.dns || {};
        var ips = diff.ips || {};

        var html = '<div class="cabinet-dr-compare__head">' +
            '<h5 class="h6 mb-1">' + escapeHtml(i18n.compareTitle) + '</h5>' +
            '<p class="small text-muted mb-2">' +
            escapeHtml((a.domain || '') + ' · ' + (a.created_at || '')) +
            ' → ' +
            escapeHtml((b.domain || '') + ' · ' + (b.created_at || '')) +
            '</p></div>';

        html += '<div class="cabinet-dr-compare__section"><h6>WHOIS</h6><ul class="cabinet-dr-diff-list">';
        ['status', 'registered_at', 'expires_at', 'status_key'].forEach(function (field) {
            var row = whois[field];
            if (!row || !row.changed) return;
            html += '<li class="cabinet-dr-diff cabinet-dr-diff--changed">' +
                '<strong>' + escapeHtml(field) + ':</strong> ' +
                escapeHtml(row.old || '—') + ' → ' + escapeHtml(row.new || '—') +
                '</li>';
        });
        var ns = whois.dns_servers || {};
        html += renderDiffList(ns.added, 'cabinet-dr-diff--added');
        html += renderDiffList(ns.removed, 'cabinet-dr-diff--removed');
        html += '</ul></div>';

        html += '<div class="cabinet-dr-compare__section"><h6>DNS</h6>';
        Object.keys(dns).sort().forEach(function (type) {
            var block = dns[type] || {};
            var parts = renderDiffList(block.added, 'cabinet-dr-diff--added') +
                renderDiffList(block.removed, 'cabinet-dr-diff--removed');
            if (!parts) return;
            html += '<div class="cabinet-dr-compare__dns-type"><strong>' + escapeHtml(type) + '</strong>' +
                '<ul class="cabinet-dr-diff-list">' + parts + '</ul></div>';
        });
        html += '</div>';

        html += '<div class="cabinet-dr-compare__section"><h6>IP</h6><ul class="cabinet-dr-diff-list">' +
            renderDiffList(ips.added, 'cabinet-dr-diff--added') +
            renderDiffList(ips.removed, 'cabinet-dr-diff--removed') +
            '</ul></div>';

        compareEl.innerHTML = html;
        compareEl.classList.remove('d-none');
        if (typeof compareEl.scrollIntoView === 'function') {
            compareEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }

    function looksLikePublicDomain(raw) {
        var v = String(raw || '').trim();
        if (!v) {
            return false;
        }
        v = v.replace(/^https?:\/\//i, '');
        v = v.split('/')[0].split('?')[0].split('#')[0];
        v = v.replace(/:\d+$/, '');
        v = v.replace(/^www\./i, '');
        v = v.replace(/\.$/, '');
        if (!v || v.indexOf('.') === -1) {
            return false;
        }
        if (/^\d{1,3}(\.\d{1,3}){3}$/.test(v)) {
            return false;
        }
        var labels = v.split('.');
        if (labels.length < 2) {
            return false;
        }
        var tld = labels[labels.length - 1];
        return tld.length >= 2;
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var domain = (input.value || '').trim();
        if (!domain) {
            setStatus('Укажите домен', 'error');
            return;
        }
        if (!looksLikePublicDomain(domain)) {
            setStatus('Укажите корректный домен с зоной, например example.ru', 'error');
            return;
        }
        submitBtn.disabled = true;
        setStatus('Проверяем WHOIS и DNS…', 'busy');

        var body = { domain: domain };
        if (canSave && saveCb) {
            body.save = !!saveCb.checked;
        }

        postJson(lookupUrl, body)
            .then(function (res) {
                submitBtn.disabled = false;
                if (res.data) {
                    updateHeaderRemaining(res.data.remaining);
                    if (res.data.saved_count != null) {
                        updateSavedCount(res.data.saved_count);
                    }
                }
                if (!res.ok) {
                    setStatus((res.data && res.data.message) || 'Ошибка', 'error');
                    return;
                }
                try {
                    var result = res.data.result || {};
                    showResult(result, res.data.already || {});
                    var msg = 'Готово: ' + currentDomain;
                    if (res.data.history_warning) {
                        msg += '. ' + res.data.history_warning;
                        setStatus(msg, 'error');
                    } else {
                        setStatus(msg, 'ok');
                    }
                    if (res.data.history_id) {
                        prependHistoryRow(
                            res.data.history_id,
                            result.domain,
                            formatNow(),
                            historySummaryFromResult(result)
                        );
                    }
                } catch (renderErr) {
                    setStatus('Ошибка отображения: ' + (renderErr && renderErr.message ? renderErr.message : renderErr), 'error');
                }
            })
            .catch(function (err) {
                submitBtn.disabled = false;
                var msg = (err && err.message) ? err.message : 'Сеть или сервер недоступны';
                setStatus(msg, 'error');
            });
    });

    if (dnsTabs) {
        dnsTabs.addEventListener('click', function (e) {
            var btn = e.target.closest('.cabinet-dr-dns-tab');
            if (!btn) return;
            activeDnsType = btn.getAttribute('data-type') || 'ALL';
            renderDns(currentDns);
        });
    }

    if (ipsEl) {
        ipsEl.addEventListener('click', function (e) {
            var viewAll = e.target.closest('.cabinet-dr-neighbors-view-all');
            if (viewAll) {
                e.preventDefault();
                openNeighborsModal(viewAll.getAttribute('data-ip') || '');
                return;
            }
            var downloadBtn = e.target.closest('.cabinet-dr-neighbors-download');
            if (downloadBtn) {
                e.preventDefault();
                downloadNeighborsList(downloadBtn.getAttribute('data-ip') || '');
                return;
            }
            var btn = e.target.closest('.cabinet-dr-ip-btn');
            if (!btn) return;
            loadNeighbors(btn.getAttribute('data-ip') || '');
        });
    }

    if (historyBody) {
        historyBody.addEventListener('change', function (e) {
            if (!e.target.classList.contains('cabinet-dr-history-cmp')) return;
            var ids = selectedCompareIds();
            if (ids.length > 2) {
                e.target.checked = false;
                setStatus(i18n.comparePick, 'error');
            }
            syncCompareBtn();
        });

        historyBody.addEventListener('click', function (e) {
            var openBtn = e.target.closest('.cabinet-dr-history-open');
            var delBtn = e.target.closest('.cabinet-dr-history-del');
            var row = e.target.closest('tr[data-id]');
            if (!row) return;
            var id = row.getAttribute('data-id');

            if (openBtn) {
                setStatus('Загружаем снимок…', 'busy');
                requestJson(historyBase + '/' + id, 'GET').then(function (res) {
                    if (!res.ok) {
                        setStatus((res.data && res.data.message) || 'Не удалось открыть', 'error');
                        return;
                    }
                    var item = res.data.item || {};
                    var snapshot = item.snapshot || {};
                    if (input && item.domain) {
                        input.value = item.domain;
                    }
                    showResult(snapshot, {});
                    setStatus('История: ' + (item.domain || '') + ' · ' + (item.created_at || ''), 'ok');
                }).catch(function (err) {
                    setStatus((err && err.message) || 'Сеть или сервер недоступны', 'error');
                });
                return;
            }

            if (delBtn) {
                if (!window.confirm('Удалить проверку?')) return;
                requestJson(historyBase + '/' + id, 'DELETE').then(function (res) {
                    if (!res.ok) {
                        setStatus((res.data && res.data.message) || 'Не удалось удалить', 'error');
                        return;
                    }
                    row.parentNode.removeChild(row);
                    if (res.data && res.data.saved_count != null) {
                        updateSavedCount(res.data.saved_count);
                    }
                    if (!historyBody.querySelector('tr[data-id]')) {
                        historyBody.innerHTML = '<tr class="cabinet-dr-history-empty"><td colspan="4" class="text-muted small">История пуста</td></tr>';
                    }
                    syncCompareBtn();
                    setStatus('Удалено', 'ok');
                }).catch(function (err) {
                    setStatus((err && err.message) || 'Сеть или сервер недоступны', 'error');
                });
            }
        });
    }

    if (compareBtn) {
        compareBtn.addEventListener('click', function () {
            var ids = selectedCompareIds();
            if (ids.length !== 2) {
                setStatus(i18n.comparePick, 'error');
                return;
            }
            compareBtn.disabled = true;
            setStatus('Сравниваем…', 'busy');
            postJson(compareUrl, { a: ids[0], b: ids[1] })
                .then(function (res) {
                    syncCompareBtn();
                    if (!res.ok) {
                        setStatus((res.data && res.data.message) || 'Ошибка сравнения', 'error');
                        return;
                    }
                    renderCompare(res.data);
                    setStatus('Сравнение готово', 'ok');
                })
                .catch(function (err) {
                    syncCompareBtn();
                    setStatus((err && err.message) || 'Сеть или сервер недоступны', 'error');
                });
        });
        syncCompareBtn();
    }

    function wireAdd(btn, url, extra) {
        if (!btn) return;
        btn.addEventListener('click', function () {
            if (!currentDomain) return;
            btn.disabled = true;
            setStatus('Добавляем…', 'busy');
            var body = Object.assign({ domain: currentDomain }, extra || {});
            postJson(url, body).then(function (res) {
                if (!res.ok) {
                    btn.disabled = false;
                    setStatus((res.data && res.data.message) || 'Не удалось добавить', 'error');
                    return;
                }
                setStatus(res.data.message || 'Добавлено', 'ok');
                if (res.data.redirect) {
                    setTimeout(function () {
                        window.location.href = res.data.redirect;
                    }, 700);
                } else {
                    btn.disabled = true;
                }
            }).catch(function (err) {
                btn.disabled = false;
                setStatus((err && err.message) || 'Сеть или сервер недоступны', 'error');
            });
        });
    }

    if (canSite) wireAdd(addSiteBtn, addSiteUrl);
    if (canDomain) wireAdd(addDomainBtn, addDomainUrl, { check_dns: 1, check_registration_date: 1 });

    (function tryOpenHistoryFromUrl() {
        var match = window.location.search.match(/(?:\?|&)history=(\d+)/);
        if (!match || !historyBase) {
            return;
        }
        var id = match[1];
        var btn = document.querySelector('tr[data-id="' + id + '"] .cabinet-dr-history-open');
        if (btn) {
            btn.click();
        }
    })();
})();
