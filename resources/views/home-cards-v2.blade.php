@extends('layouts.app')

@section('title', __('Main page'))

@section('css')
    <link rel="stylesheet" href="{{ asset('css/cabinet-home.css') }}">
    <link rel="stylesheet" href="{{ asset('css/cabinet-home-cards-v2.css') }}?v=20260801-modules-collapse1">
@endsection

@section('content')
    <div class="cabinet-home-page cabinet-home-cards-v2-page"
         data-sites-fragment-url="{{ route('home.sites.fragment') }}"
         data-module-counts-url="{{ route('home.module-counts') }}"
         data-seo-checklist-due-url="{{ route('home.seo-checklist-due') }}"
         data-module-counts-deferred="{{ !empty($moduleCountsDeferred) ? '1' : '0' }}">
        @include('home.partials.hero', ['summary' => $summary])
        @include('home.partials.stats', ['summary' => $summary])
        <div id="cabinet-home-sc-due-slot">
            @if(empty($seoChecklistDue['deferred']))
                @include('home.partials.seo-checklist-due', ['seoChecklistDue' => $seoChecklistDue ?? null])
            @else
                <div class="cabinet-home-sc-due-skel alert alert-light border mb-3 d-none" data-sc-due-loading role="status">
                    <span class="spinner-border spinner-border-sm text-secondary me-2" aria-hidden="true"></span>
                    <span class="text-secondary small">{{ __('Loading checklist deadlines') }}</span>
                </div>
            @endif
        </div>
        <div id="cabinet-home-sites-slot">
            @if(!empty($userSites['deferred']))
                <section class="cabinet-home-sites mb-4" id="cabinet-home-sites" aria-busy="true">
                    <div class="cabinet-home-sites-empty text-center text-secondary py-4 px-3">
                        <span class="spinner-border spinner-border-sm text-secondary me-2" role="status" aria-hidden="true"></span>
                        {{ __('Loading sites table') }}
                    </div>
                </section>
            @else
                @include('home-cards-v2.partials.sites', ['userSites' => $userSites ?? []])
            @endif
        </div>
        @include('home-cards-v2.partials.modules', [
            'modules' => $modules,
            'moduleCountsDeferred' => !empty($moduleCountsDeferred),
        ])
    </div>
@endsection

@section('js')
    <script>
        (async function () {
            var pageRoot = document.querySelector('.cabinet-home-cards-v2-page');

            function fetchText(url) {
                return fetch(url, {
                    method: 'GET',
                    headers: {
                        'Accept': 'text/html',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                }).then(function (res) {
                    if (!res.ok) {
                        throw new Error('http');
                    }
                    return res.text();
                });
            }

            function loadModuleCounts() {
                if (!pageRoot || pageRoot.getAttribute('data-module-counts-deferred') !== '1') {
                    return Promise.resolve();
                }
                var url = pageRoot.getAttribute('data-module-counts-url');
                if (!url) {
                    return Promise.resolve();
                }
                return fetch(url, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                })
                    .then(function (res) { return res.json(); })
                    .then(function (data) {
                        if (!data || !data.ok || !data.counts) {
                            return;
                        }
                        var openLabel = @json(__('Open'));
                        var startLabel = @json(__('Open and start'));
                        Object.keys(data.counts).forEach(function (key) {
                            var row = data.counts[key];
                            var col = document.querySelector('[data-module-key="' + key + '"]');
                            if (!col) {
                                return;
                            }
                            var card = col.querySelector('.cabinet-home-cards-v2-card');
                            var meta = col.querySelector('[data-module-meta]');
                            var open = col.querySelector('[data-module-open]');
                            if (!meta) {
                                return;
                            }
                            var count = Number(row.count) || 0;
                            var skel = meta.querySelector('[data-module-count-skel]');
                            if (skel) {
                                skel.remove();
                            }
                            if (count < 1) {
                                if (card) {
                                    card.classList.add('is-empty');
                                }
                                var empty = document.createElement('div');
                                empty.className = 'cabinet-home-cards-v2-empty';
                                empty.setAttribute('role', 'status');
                                empty.innerHTML = '<i class="bi bi-info-circle" aria-hidden="true"></i><span></span>';
                                empty.querySelector('span').textContent = row.empty_label || '';
                                meta.insertBefore(empty, open || null);
                                if (open) {
                                    open.childNodes[0].textContent = startLabel + ' ';
                                }
                            } else {
                                if (card) {
                                    card.classList.remove('is-empty');
                                }
                                var countEl = document.createElement('div');
                                countEl.className = 'cabinet-home-cards-v2-count';
                                countEl.innerHTML = '<span class="cabinet-home-cards-v2-count__num"></span>' +
                                    '<span class="cabinet-home-cards-v2-count__label"></span>';
                                countEl.querySelector('.cabinet-home-cards-v2-count__num').textContent = String(count);
                                countEl.querySelector('.cabinet-home-cards-v2-count__label').textContent = row.count_label || '';
                                meta.insertBefore(countEl, open || null);
                                if (open) {
                                    open.childNodes[0].textContent = openLabel + ' ';
                                }
                            }
                        });
                        document.querySelectorAll('[data-module-count-skel]').forEach(function (el) {
                            var muted = document.createElement('div');
                            muted.className = 'cabinet-home-cards-v2-count cabinet-home-cards-v2-count--muted';
                            muted.innerHTML = '<span class="cabinet-home-cards-v2-count__label">' +
                                @json(__('Utility tool')) + '</span>';
                            el.replaceWith(muted);
                        });
                    })
                    .catch(function () {
                        document.querySelectorAll('[data-module-count-skel]').forEach(function (el) {
                            el.innerHTML = '<span class="cabinet-home-cards-v2-count__label">' +
                                @json(__('Utility tool')) + '</span>';
                        });
                    });
            }

            function loadSeoChecklistDue() {
                if (!pageRoot) {
                    return Promise.resolve();
                }
                var url = pageRoot.getAttribute('data-seo-checklist-due-url');
                var slot = document.getElementById('cabinet-home-sc-due-slot');
                if (!url || !slot || !slot.querySelector('[data-sc-due-loading]')) {
                    return Promise.resolve();
                }
                var loading = slot.querySelector('[data-sc-due-loading]');
                if (loading) {
                    loading.classList.remove('d-none');
                }
                return fetchText(url)
                    .then(function (html) {
                        slot.innerHTML = html;
                    })
                    .catch(function () {
                        slot.innerHTML = '';
                    });
            }

            function loadSitesFragment() {
                var slot = document.getElementById('cabinet-home-sites-slot');
                if (!pageRoot || !slot) {
                    return Promise.resolve();
                }
                var url = pageRoot.getAttribute('data-sites-fragment-url');
                var stub = slot.querySelector('#cabinet-home-sites[aria-busy="true"]');
                if (!url || !stub) {
                    return Promise.resolve();
                }
                return fetchText(url)
                    .then(function (html) {
                        slot.innerHTML = html;
                    })
                    .catch(function () {
                        slot.innerHTML = '<div class="alert alert-warning mb-4">' +
                            @json(__('Could not load sites table')) +
                            '</div>';
                    });
            }

            // Параллельно: counts + due; сайты ждём до инициализации таблицы
            var countsPromise = loadModuleCounts();
            var duePromise = loadSeoChecklistDue();
            await loadSitesFragment();

            var moduleInput = document.getElementById('cabinet-home-module-search');
            if (moduleInput) {
                var cards = document.querySelectorAll('[data-cabinet-module-title]');
                moduleInput.addEventListener('input', function () {
                    var q = moduleInput.value.trim().toLowerCase();
                    var visible = 0;
                    cards.forEach(function (col) {
                        var title = (col.getAttribute('data-cabinet-module-title') || '').toLowerCase();
                        var match = q === '' || title.indexOf(q) !== -1;
                        col.classList.toggle('is-hidden', !match);
                        if (match) {
                            visible++;
                        }
                    });
                    var empty = document.getElementById('cabinet-home-modules-empty');
                    if (empty) {
                        empty.classList.toggle('d-none', visible > 0 || q === '');
                    }
                });
            }

            var sitesSection = document.getElementById('cabinet-home-sites');
            var sitesToggle = document.getElementById('cabinet-home-sites-toggle');
            if (sitesSection && sitesToggle) {
                var sitesStorageKey = 'cabinet-home-sites-collapsed';
                try {
                    if (localStorage.getItem(sitesStorageKey) === '1') {
                        sitesSection.classList.add('is-collapsed');
                        sitesToggle.setAttribute('aria-expanded', 'false');
                    }
                } catch (e) {}
                sitesToggle.addEventListener('click', function () {
                    var collapsed = sitesSection.classList.toggle('is-collapsed');
                    sitesToggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
                    try {
                        localStorage.setItem(sitesStorageKey, collapsed ? '1' : '0');
                    } catch (e) {}
                    if (typeof window.__cabinetHomeSitesFloatUpdate === 'function') {
                        window.__cabinetHomeSitesFloatUpdate();
                    }
                });
            }

            var modulesSection = document.getElementById('cabinet-home-modules');
            var modulesToggle = document.getElementById('cabinet-home-modules-toggle');
            if (modulesSection && modulesToggle) {
                var modulesStorageKey = 'cabinet-home-modules-collapsed';
                try {
                    if (localStorage.getItem(modulesStorageKey) === '1') {
                        modulesSection.classList.add('is-collapsed');
                        modulesToggle.setAttribute('aria-expanded', 'false');
                    }
                } catch (e) {}
                modulesToggle.addEventListener('click', function () {
                    var collapsed = modulesSection.classList.toggle('is-collapsed');
                    modulesToggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
                    try {
                        localStorage.setItem(modulesStorageKey, collapsed ? '1' : '0');
                    } catch (e) {}
                });
            }

            if (sitesSection) {
                var sitesMode = 'active';
                var moduleFilter = null;
                var metrikaFilter = null;
                var webmasterFilter = null;
                var gscFilter = null;
                var sitesPage = 1;
                var sitesSortKey = 'domain';
                var sitesSortDir = 'asc';
                var modeButtons = sitesSection.querySelectorAll('[data-sites-mode]');
                var sitesInput = document.getElementById('cabinet-home-sites-search');
                var pagerEl = sitesSection.querySelector('[data-sites-pager]');
                var pagerInfoEl = pagerEl ? pagerEl.querySelector('[data-sites-pager-info]') : null;
                var pagerLabelEl = pagerEl ? pagerEl.querySelector('[data-sites-pager-label]') : null;
                var pagerPrevBtn = pagerEl ? pagerEl.querySelector('[data-sites-page="prev"]') : null;
                var pagerNextBtn = pagerEl ? pagerEl.querySelector('[data-sites-page="next"]') : null;
                var pageSize = pagerEl ? (parseInt(pagerEl.getAttribute('data-page-size'), 10) || 50) : 50;
                var csrf = document.querySelector('meta[name="csrf-token"]');
                var csrfToken = csrf ? csrf.getAttribute('content') : '';
                var pagerInfoTpl = @json(__('Sites page range'));
                var pagerLabelTpl = @json(__('Page of pages'));

                function activePanel() {
                    return sitesSection.querySelector('[data-sites-panel="' + sitesMode + '"]');
                }

                function formatPagerText(tpl, map) {
                    return String(tpl || '').replace(/:([a-z_]+)/gi, function (_, key) {
                        return map[key] != null ? String(map[key]) : '';
                    });
                }

                function syncSitesSortHeaders() {
                    document.querySelectorAll('[data-sites-sort]').forEach(function (th) {
                        var key = th.getAttribute('data-sites-sort');
                        var active = key === sitesSortKey;
                        th.classList.toggle('is-sorted', active);
                        th.classList.toggle('is-sorted-asc', active && sitesSortDir === 'asc');
                        th.classList.toggle('is-sorted-desc', active && sitesSortDir === 'desc');
                        th.setAttribute('aria-sort', active ? (sitesSortDir === 'asc' ? 'ascending' : 'descending') : 'none');
                        var icon = th.querySelector('.cabinet-home-sites-sort-btn > .bi');
                        if (icon) {
                            icon.className = 'bi ' + (
                                !active ? 'bi-arrow-down-up' :
                                    (sitesSortDir === 'asc' ? 'bi-sort-up' : 'bi-sort-down')
                            );
                        }
                    });
                }

                function sortSitesRows(rows) {
                    if (!sitesSortKey) {
                        return rows;
                    }
                    var key = sitesSortKey;
                    var dir = sitesSortDir === 'desc' ? -1 : 1;
                    var numeric = key.indexOf('visits_') === 0 || key.indexOf('mod-') === 0 || key === 'modules';
                    return rows.slice().sort(function (a, b) {
                        var av = a.getAttribute('data-sort-' + key);
                        var bv = b.getAttribute('data-sort-' + key);
                        if (av == null) av = '';
                        if (bv == null) bv = '';
                        if (numeric) {
                            var an = av === '' ? -1 : parseFloat(av);
                            var bn = bv === '' ? -1 : parseFloat(bv);
                            if (isNaN(an)) an = -1;
                            if (isNaN(bn)) bn = -1;
                            if (an === bn) {
                                return (a.getAttribute('data-sort-domain') || '').localeCompare(
                                    b.getAttribute('data-sort-domain') || '',
                                    'ru',
                                    { sensitivity: 'base' }
                                );
                            }
                            return (an - bn) * dir;
                        }
                        var cmp = String(av).localeCompare(String(bv), 'ru', { sensitivity: 'base' });
                        if (cmp === 0) {
                            return (a.getAttribute('data-sort-domain') || '').localeCompare(
                                b.getAttribute('data-sort-domain') || '',
                                'ru',
                                { sensitivity: 'base' }
                            );
                        }
                        return cmp * dir;
                    });
                }

                function applySitesFilter(resetPage) {
                    if (resetPage) {
                        sitesPage = 1;
                    }
                    var panel = activePanel();
                    if (!panel) {
                        return;
                    }
                    var q = sitesInput ? sitesInput.value.trim().toLowerCase() : '';
                    var tbody = panel.querySelector('.cabinet-home-sites-tbody');
                    var rows = Array.prototype.slice.call(panel.querySelectorAll('tr[data-cabinet-site-domain]'));
                    var matched = [];
                    rows.forEach(function (row) {
                        var domain = (row.getAttribute('data-cabinet-site-domain') || '').toLowerCase();
                        var matchQ = q === '' || domain.indexOf(q) !== -1;
                        var modulesCount = parseInt(row.getAttribute('data-sort-modules') || '0', 10) || 0;
                        var modulesTotal = parseInt(row.getAttribute('data-modules-total') || '0', 10) || 0;
                        var matchModule = true;
                        if (moduleFilter === 'on') {
                            // Есть зелёный кружок — сайт хотя бы в одном модуле
                            matchModule = modulesCount > 0;
                        } else if (moduleFilter === 'off') {
                            // Есть красный кружок — хотя бы в одном модуле отсутствует
                            matchModule = modulesTotal > 0 && modulesCount < modulesTotal;
                        }
                        var metrikaSynced = row.getAttribute('data-metrika-synced') === '1';
                        var matchMetrika = true;
                        if (metrikaFilter === 'on') {
                            matchMetrika = metrikaSynced;
                        } else if (metrikaFilter === 'off') {
                            matchMetrika = !metrikaSynced;
                        }
                        var webmasterSynced = row.getAttribute('data-webmaster-synced') === '1';
                        var matchWebmaster = true;
                        if (webmasterFilter === 'on') {
                            matchWebmaster = webmasterSynced;
                        } else if (webmasterFilter === 'off') {
                            matchWebmaster = !webmasterSynced;
                        }
                        var gscSynced = row.getAttribute('data-gsc-synced') === '1';
                        var matchGsc = true;
                        if (gscFilter === 'on') {
                            matchGsc = gscSynced;
                        } else if (gscFilter === 'off') {
                            matchGsc = !gscSynced;
                        }
                        var match = matchQ && matchModule && matchMetrika && matchWebmaster && matchGsc;
                        row.setAttribute('data-sites-match', match ? '1' : '0');
                        row.classList.add('is-hidden');
                        if (match) {
                            matched.push(row);
                        }
                    });

                    matched = sortSitesRows(matched);
                    if (tbody) {
                        matched.forEach(function (row) {
                            tbody.appendChild(row);
                        });
                    }
                    syncSitesSortHeaders();

                    var totalMatched = matched.length;
                    var totalPages = Math.max(1, Math.ceil(totalMatched / pageSize) || 1);
                    if (sitesPage > totalPages) {
                        sitesPage = totalPages;
                    }
                    if (sitesPage < 1) {
                        sitesPage = 1;
                    }
                    var start = (sitesPage - 1) * pageSize;
                    var end = start + pageSize;
                    matched.forEach(function (row, index) {
                        if (index >= start && index < end) {
                            row.classList.remove('is-hidden');
                        }
                    });

                    var filtered = q !== '' || moduleFilter || metrikaFilter || webmasterFilter || gscFilter;
                    var empty = document.getElementById('cabinet-home-sites-filter-empty');
                    var wrap = panel.querySelector('.cabinet-home-sites-table-wrap');
                    var panelEmpty = panel.querySelector('.cabinet-home-sites-empty');
                    if (empty) {
                        empty.classList.toggle('d-none', totalMatched > 0 || !filtered || rows.length === 0);
                    }
                    if (wrap) {
                        wrap.classList.toggle('d-none', totalMatched === 0 && filtered);
                    }
                    if (panelEmpty && !filtered) {
                        panelEmpty.classList.remove('d-none');
                    }

                    if (pagerEl) {
                        var showPager = totalMatched > pageSize;
                        pagerEl.classList.toggle('d-none', !showPager && totalMatched === 0);
                        if (totalMatched === 0) {
                            pagerEl.classList.add('d-none');
                        } else {
                            pagerEl.classList.remove('d-none');
                            var from = totalMatched ? start + 1 : 0;
                            var to = Math.min(end, totalMatched);
                            if (pagerInfoEl) {
                                pagerInfoEl.textContent = formatPagerText(pagerInfoTpl, {
                                    from: from,
                                    to: to,
                                    total: totalMatched,
                                });
                            }
                            if (pagerLabelEl) {
                                pagerLabelEl.textContent = formatPagerText(pagerLabelTpl, {
                                    page: sitesPage,
                                    pages: totalPages,
                                });
                            }
                            if (pagerPrevBtn) {
                                pagerPrevBtn.disabled = sitesPage <= 1;
                            }
                            if (pagerNextBtn) {
                                pagerNextBtn.disabled = sitesPage >= totalPages;
                            }
                            if (!showPager) {
                                // Одна страница — оставляем только текст «1–N из N»
                                if (pagerPrevBtn) pagerPrevBtn.classList.add('d-none');
                                if (pagerNextBtn) pagerNextBtn.classList.add('d-none');
                                if (pagerLabelEl) pagerLabelEl.classList.add('d-none');
                            } else {
                                if (pagerPrevBtn) pagerPrevBtn.classList.remove('d-none');
                                if (pagerNextBtn) pagerNextBtn.classList.remove('d-none');
                                if (pagerLabelEl) pagerLabelEl.classList.remove('d-none');
                            }
                        }
                    }

                    if (typeof window.__cabinetHomeSitesFloatUpdate === 'function') {
                        window.__cabinetHomeSitesFloatUpdate();
                    }
                }

                function setSitesMode(mode) {
                    if (mode !== 'archive' && mode !== 'hidden') {
                        mode = 'active';
                    }
                    sitesMode = mode;
                    sitesSection.querySelectorAll('[data-sites-panel]').forEach(function (panel) {
                        panel.classList.toggle('d-none', panel.getAttribute('data-sites-panel') !== sitesMode);
                    });
                    modeButtons.forEach(function (btn) {
                        btn.classList.toggle('active', btn.getAttribute('data-sites-mode') === sitesMode);
                    });
                    applySitesFilter(true);
                }

                modeButtons.forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        setSitesMode(btn.getAttribute('data-sites-mode'));
                    });
                });

                function syncLegendFilterButtons() {
                    [
                        ['data-sites-filter-module', moduleFilter],
                        ['data-sites-filter-metrika', metrikaFilter],
                        ['data-sites-filter-webmaster', webmasterFilter],
                        ['data-sites-filter-gsc', gscFilter]
                    ].forEach(function (pair) {
                        var attr = pair[0];
                        var value = pair[1];
                        sitesSection.querySelectorAll('[' + attr + ']').forEach(function (btn) {
                            var active = value !== null && btn.getAttribute(attr) === value;
                            btn.classList.toggle('is-active', active);
                            btn.setAttribute('aria-pressed', active ? 'true' : 'false');
                        });
                    });
                }

                function bindLegendFilter(attr, getValue, setValue) {
                    sitesSection.querySelectorAll('[' + attr + ']').forEach(function (btn) {
                        btn.addEventListener('click', function () {
                            var next = btn.getAttribute(attr);
                            setValue(getValue() === next ? null : next);
                            syncLegendFilterButtons();
                            applySitesFilter(true);
                        });
                    });
                }

                bindLegendFilter('data-sites-filter-module', function () { return moduleFilter; }, function (v) { moduleFilter = v; });
                bindLegendFilter('data-sites-filter-metrika', function () { return metrikaFilter; }, function (v) { metrikaFilter = v; });
                bindLegendFilter('data-sites-filter-webmaster', function () { return webmasterFilter; }, function (v) { webmasterFilter = v; });
                bindLegendFilter('data-sites-filter-gsc', function () { return gscFilter; }, function (v) { gscFilter = v; });

                if (pagerPrevBtn) {
                    pagerPrevBtn.addEventListener('click', function () {
                        if (sitesPage <= 1) return;
                        sitesPage -= 1;
                        applySitesFilter(false);
                    });
                }
                if (pagerNextBtn) {
                    pagerNextBtn.addEventListener('click', function () {
                        sitesPage += 1;
                        applySitesFilter(false);
                    });
                }

                document.addEventListener('click', function (e) {
                    var btn = e.target.closest('[data-sites-sort]');
                    if (!btn) {
                        return;
                    }
                    if (!btn.closest('#cabinet-home-sites') && !btn.closest('.cabinet-home-sites-float-head')) {
                        return;
                    }
                    e.preventDefault();
                    var key = btn.getAttribute('data-sites-sort');
                    if (!key) {
                        return;
                    }
                    if (sitesSortKey === key) {
                        sitesSortDir = sitesSortDir === 'asc' ? 'desc' : 'asc';
                    } else {
                        sitesSortKey = key;
                        sitesSortDir = key === 'domain' ? 'asc' : 'desc';
                    }
                    applySitesFilter(true);
                });

                function postSiteAction(url, domain, button) {
                    if (!url || !domain) {
                        return;
                    }
                    if (button) {
                        button.disabled = true;
                    }
                    fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({ domain: domain }),
                        credentials: 'same-origin',
                    })
                        .then(function (res) {
                            return res.json().then(function (data) {
                                return { ok: res.ok && data && data.ok, data: data };
                            });
                        })
                        .then(function (result) {
                            if (!result.ok) {
                                throw new Error((result.data && result.data.message) || 'error');
                            }
                            window.location.reload();
                        })
                        .catch(function () {
                            if (button) {
                                button.disabled = false;
                            }
                            alert(@json(__('Could not update site archive')));
                        });
                }

                var pendingAction = null;
                var confirmModalEl = document.getElementById('cabinet-sites-confirm-modal');
                var confirmTextEl = confirmModalEl ? confirmModalEl.querySelector('[data-sites-confirm-text]') : null;
                var confirmOkBtn = confirmModalEl ? confirmModalEl.querySelector('[data-sites-confirm-ok]') : null;
                var confirmTitleEl = document.getElementById('cabinet-sites-confirm-title');

                function showConfirmModal(opts) {
                    pendingAction = opts || null;
                    if (!confirmModalEl || !pendingAction) {
                        return;
                    }
                    if (confirmTitleEl) {
                        confirmTitleEl.textContent = pendingAction.title || @json(__('Confirm'));
                    }
                    if (confirmTextEl) {
                        confirmTextEl.textContent = pendingAction.text || '';
                    }
                    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                        bootstrap.Modal.getOrCreateInstance(confirmModalEl).show();
                        return;
                    }
                    if (typeof $ !== 'undefined' && $.fn.modal) {
                        $(confirmModalEl).modal('show');
                    }
                }

                function hideConfirmModal() {
                    if (!confirmModalEl) {
                        return;
                    }
                    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                        var inst = bootstrap.Modal.getInstance(confirmModalEl);
                        if (inst) {
                            inst.hide();
                        }
                        return;
                    }
                    if (typeof $ !== 'undefined' && $.fn.modal) {
                        $(confirmModalEl).modal('hide');
                    }
                }

                if (confirmOkBtn) {
                    confirmOkBtn.addEventListener('click', function () {
                        if (!pendingAction) {
                            return;
                        }
                        var action = pendingAction;
                        pendingAction = null;
                        hideConfirmModal();
                        postSiteAction(action.url, action.domain, action.button);
                    });
                }

                function initSitesActionTooltips() {
                    if (typeof bootstrap === 'undefined' || !bootstrap.Tooltip) {
                        return;
                    }
                    sitesSection.querySelectorAll(
                        '[data-cabinet-site-hide], [data-cabinet-site-archive], [data-cabinet-site-restore]'
                    ).forEach(function (el) {
                        var existing = bootstrap.Tooltip.getInstance(el);
                        if (existing) {
                            existing.dispose();
                        }
                        new bootstrap.Tooltip(el, {
                            container: 'body',
                            trigger: 'hover focus',
                            placement: el.getAttribute('data-bs-placement') || 'top',
                        });
                    });
                }

                sitesSection.addEventListener('click', function (event) {
                    var archiveBtn = event.target.closest('[data-cabinet-site-archive]');
                    var hideBtn = event.target.closest('[data-cabinet-site-hide]');
                    var restoreBtn = event.target.closest('[data-cabinet-site-restore]');
                    var btn = archiveBtn || hideBtn || restoreBtn;
                    if (!btn) {
                        return;
                    }
                    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
                        var tip = bootstrap.Tooltip.getInstance(btn);
                        if (tip) {
                            tip.hide();
                        }
                    }
                    var row = btn.closest('tr[data-cabinet-site-domain]');
                    if (!row) {
                        return;
                    }
                    var domain = row.getAttribute('data-cabinet-site-domain') || '';
                    if (restoreBtn) {
                        postSiteAction(sitesSection.getAttribute('data-restore-url'), domain, btn);
                        return;
                    }
                    if (archiveBtn) {
                        showConfirmModal({
                            title: @json(__('Move to archive')),
                            text: @json(__('Confirm archive site')) + ' «' + domain + '»?',
                            url: sitesSection.getAttribute('data-archive-url'),
                            domain: domain,
                            button: btn,
                        });
                        return;
                    }
                    if (hideBtn) {
                        showConfirmModal({
                            title: @json(__('Hide site')),
                            text: @json(__('Confirm hide site')) + ' «' + domain + '»?',
                            url: sitesSection.getAttribute('data-hide-url'),
                            domain: domain,
                            button: btn,
                        });
                    }
                });

                if (sitesInput) {
                    sitesInput.addEventListener('input', function () {
                        applySitesFilter(true);
                    });
                }

                // Столбцы: как в мониторинге позиций — чекбоксы + сохранение на пользователя
                (function initSitesColumns() {
                    var columnsUrl = sitesSection.getAttribute('data-columns-url') || '';
                    var columnsMenu = document.getElementById('cabinet-home-sites-columns-menu');
                    var columnPrefs = {};
                    try {
                        columnPrefs = JSON.parse(sitesSection.getAttribute('data-columns') || '{}') || {};
                    } catch (e) {
                        columnPrefs = {};
                    }
                    var saveTimer = null;

                    function applyColumnVisibility(key, visible) {
                        sitesSection.querySelectorAll(
                            '.cabinet-home-sites-table [data-col="' + key + '"]'
                        ).forEach(function (el) {
                            el.classList.toggle('is-col-hidden', !visible);
                        });
                        document.querySelectorAll(
                            '.cabinet-home-sites-float-head [data-col="' + key + '"]'
                        ).forEach(function (el) {
                            el.classList.toggle('is-col-hidden', !visible);
                        });
                        if (typeof window.__cabinetHomeSitesFloatUpdate === 'function') {
                            window.__cabinetHomeSitesFloatUpdate();
                        }
                    }

                    function scheduleSaveColumns() {
                        if (!columnsUrl) {
                            return;
                        }
                        if (saveTimer) {
                            clearTimeout(saveTimer);
                        }
                        saveTimer = setTimeout(function () {
                            fetch(columnsUrl, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': csrfToken,
                                    'X-Requested-With': 'XMLHttpRequest',
                                },
                                body: JSON.stringify({ columns: columnPrefs }),
                                credentials: 'same-origin',
                            }).catch(function () { /* UI already updated */ });
                        }, 350);
                    }

                    if (!columnsMenu) {
                        return;
                    }

                    columnsMenu.addEventListener('change', function (event) {
                        var input = event.target.closest('.cabinet-home-sites-col-toggle');
                        if (!input) {
                            return;
                        }
                        var key = input.getAttribute('data-col');
                        if (!key) {
                            return;
                        }
                        var visible = !!input.checked;
                        columnPrefs[key] = visible;
                        applyColumnVisibility(key, visible);
                        scheduleSaveColumns();
                    });
                })();

                initSitesActionTooltips();
                applySitesFilter(true);
            }

            // Плавающая шапка таблицы «Ваши сайты» при скролле страницы
            (function initSitesFloatingHeader() {
                var headerNav = document.getElementById('header-nav-bar');
                var updaters = [];

                function stickyTop() {
                    // Если шапка кабинета ещё в зоне viewport сверху — клеимся под неё.
                    // Если уехала вверх (не fixed) — клеимся к верху окна (0).
                    if (!headerNav) {
                        return 0;
                    }
                    var nav = headerNav.getBoundingClientRect();
                    if (nav.bottom > 0) {
                        return Math.ceil(nav.bottom);
                    }
                    return 0;
                }

                function setupWrap(wrap) {
                    var table = wrap.querySelector('table.cabinet-home-sites-table');
                    var thead = table && table.tHead;
                    if (!table || !thead) {
                        return null;
                    }

                    var float = document.createElement('div');
                    float.className = 'cabinet-home-sites-float-head';
                    float.setAttribute('aria-hidden', 'true');
                    var floatTable = document.createElement('table');
                    floatTable.className = table.className.replace('table-hover', '').trim();
                    floatTable.style.tableLayout = 'fixed';
                    floatTable.appendChild(thead.cloneNode(true));
                    float.appendChild(floatTable);
                    document.body.appendChild(float);

                    function syncWidths() {
                        var srcTh = thead.querySelectorAll('th');
                        var dstTh = floatTable.querySelectorAll('th');
                        var tableWidth = Math.ceil(table.getBoundingClientRect().width);
                        floatTable.style.width = tableWidth + 'px';
                        floatTable.style.minWidth = tableWidth + 'px';
                        for (var i = 0; i < srcTh.length; i++) {
                            if (!dstTh[i]) {
                                continue;
                            }
                            dstTh[i].classList.toggle('is-col-hidden', srcTh[i].classList.contains('is-col-hidden'));
                            if (srcTh[i].classList.contains('is-col-hidden')) {
                                dstTh[i].style.width = '';
                                dstTh[i].style.minWidth = '';
                                dstTh[i].style.maxWidth = '';
                                continue;
                            }
                            var w = Math.max(1, Math.round(srcTh[i].getBoundingClientRect().width));
                            dstTh[i].style.width = w + 'px';
                            dstTh[i].style.minWidth = w + 'px';
                            dstTh[i].style.maxWidth = w + 'px';
                            dstTh[i].style.boxSizing = 'border-box';
                            dstTh[i].style.paddingLeft = getComputedStyle(srcTh[i]).paddingLeft;
                            dstTh[i].style.paddingRight = getComputedStyle(srcTh[i]).paddingRight;
                        }
                    }

                    function update() {
                        var panel = wrap.closest('[data-sites-panel]');
                        if ((panel && panel.classList.contains('d-none')) || wrap.offsetParent === null) {
                            float.classList.remove('is-visible');
                            return;
                        }
                        if (document.getElementById('cabinet-home-sites') &&
                            document.getElementById('cabinet-home-sites').classList.contains('is-collapsed')) {
                            float.classList.remove('is-visible');
                            return;
                        }
                        // Не перекрывать модалки (Метрика, подтверждение и т.п.)
                        if (document.body.classList.contains('modal-open') ||
                            document.querySelector('.modal.show, .modal.in, .modal-backdrop')) {
                            float.classList.remove('is-visible');
                            return;
                        }

                        var top = stickyTop();
                        var headRect = thead.getBoundingClientRect();
                        var wrapRect = wrap.getBoundingClientRect();
                        // Показать, когда оригинальная шапка ушла выше линии закрепления,
                        // но таблица ещё на экране.
                        var shouldShow = headRect.bottom <= top + 1 && wrapRect.bottom > top + 40;
                        if (!shouldShow) {
                            float.classList.remove('is-visible');
                            return;
                        }

                        syncWidths();
                        float.style.top = top + 'px';
                        float.style.left = Math.round(wrapRect.left) + 'px';
                        float.style.width = Math.round(wrapRect.width) + 'px';
                        float.scrollLeft = wrap.scrollLeft || 0;
                        float.classList.add('is-visible');
                    }

                    wrap.addEventListener('scroll', update, { passive: true });
                    updaters.push(update);
                    return update;
                }

                document.querySelectorAll('.cabinet-home-sites-table-wrap').forEach(function (wrap) {
                    setupWrap(wrap);
                });

                function tick() {
                    for (var i = 0; i < updaters.length; i++) {
                        updaters[i]();
                    }
                }

                window.__cabinetHomeSitesFloatUpdate = tick;
                // scroll часто на window; на всякий случай — capture + app-main
                window.addEventListener('scroll', tick, { passive: true, capture: true });
                document.addEventListener('scroll', tick, { passive: true, capture: true });
                var appMain = document.querySelector('.app-main');
                if (appMain) {
                    appMain.addEventListener('scroll', tick, { passive: true });
                }
                window.addEventListener('resize', tick);
                document.addEventListener('show.bs.modal', tick);
                document.addEventListener('shown.bs.modal', tick);
                document.addEventListener('hide.bs.modal', tick);
                document.addEventListener('hidden.bs.modal', tick);
                if (typeof $ !== 'undefined' && $.fn.on) {
                    $(document).on('show.bs.modal shown.bs.modal hide.bs.modal hidden.bs.modal', tick);
                }
                setTimeout(tick, 50);
                setTimeout(tick, 300);
            })();

            // Посещаемость Метрики — фоном, чтобы не блокировать отрисовку главной
            (function loadSitesVisits() {
                var root = document.getElementById('cabinet-home-sites');
                if (!root || root.getAttribute('data-visits-deferred') !== '1') {
                    return;
                }
                var url = root.getAttribute('data-visits-url');
                if (!url) {
                    return;
                }

                var rows = root.querySelectorAll('tr[data-cabinet-site-domain][data-metrika-synced="1"]');
                if (!rows.length) {
                    var metaEmpty = root.querySelector('[data-sites-visits-meta]');
                    if (metaEmpty) {
                        metaEmpty.classList.add('d-none');
                    }
                    return;
                }

                var domains = [];
                var seen = {};
                rows.forEach(function (row) {
                    var d = row.getAttribute('data-cabinet-site-domain') || '';
                    if (d && !seen[d]) {
                        seen[d] = true;
                        domains.push(d);
                    }
                });

                var fieldMap = {
                    visits_today: 'today',
                    visits_yesterday: 'yesterday',
                    visits_sum7: 'sum_7',
                    visits_avg7: 'avg_7',
                    visits_sum30: 'sum_30',
                    visits_avg30: 'avg_30',
                };

                function formatVisits(value) {
                    if (value === null || value === undefined || value === '') {
                        return null;
                    }
                    var n = Math.round(Number(value));
                    if (!isFinite(n)) {
                        return null;
                    }
                    return String(n).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
                }

                function fillRow(row, summary) {
                    Object.keys(fieldMap).forEach(function (colKey) {
                        var apiKey = fieldMap[colKey];
                        var raw = summary && Object.prototype.hasOwnProperty.call(summary, apiKey)
                            ? summary[apiKey]
                            : null;
                        var formatted = formatVisits(raw);
                        row.setAttribute('data-sort-' + colKey, formatted === null ? '' : String(Math.round(Number(raw))));
                        var cell = row.querySelector('td[data-visit-key="' + colKey + '"]');
                        if (!cell) {
                            return;
                        }
                        if (formatted === null) {
                            cell.innerHTML = '<span class="text-secondary">—</span>';
                        } else {
                            cell.textContent = formatted;
                        }
                    });
                }

                function setMeta(meta) {
                    var metaEl = root.querySelector('[data-sites-visits-meta]');
                    if (!metaEl) {
                        return;
                    }
                    var loadingEl = metaEl.querySelector('[data-sites-visits-loading]');
                    var readyEl = metaEl.querySelector('[data-sites-visits-ready]');
                    if (loadingEl) {
                        loadingEl.classList.add('d-none');
                    }
                    if (!meta || !meta.as_of_human) {
                        metaEl.classList.add('d-none');
                        return;
                    }
                    metaEl.classList.remove('d-none');
                    metaEl.removeAttribute('data-loading');
                    var html = '<i class="bi bi-graph-up-arrow me-1" aria-hidden="true"></i>' +
                        @json(__('Metrika visits as of', ['time' => ':time'])).replace(':time', meta.as_of_human);
                    if (meta.next_today_human) {
                        html += ' <span class="text-body-secondary">· ' +
                            @json(__('Metrika visits next today', ['time' => ':time'])).replace(':time', meta.next_today_human) +
                            '</span>';
                    }
                    if (readyEl) {
                        readyEl.classList.remove('d-none');
                        readyEl.innerHTML = html;
                    } else {
                        metaEl.innerHTML = html;
                    }
                }

                function clearSkeletons() {
                    root.querySelectorAll('.cabinet-home-sites-visit-skel').forEach(function (el) {
                        var cell = el.closest('td');
                        if (cell) {
                            cell.innerHTML = '<span class="text-secondary">—</span>';
                        }
                    });
                }

                var qs = domains.map(function (d) {
                    return 'domains[]=' + encodeURIComponent(d);
                }).join('&');

                fetch(url + (url.indexOf('?') >= 0 ? '&' : '?') + qs, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                })
                    .then(function (res) {
                        return res.json().then(function (data) {
                            return { ok: res.ok && data && data.ok, data: data };
                        });
                    })
                    .then(function (result) {
                        if (!result.ok) {
                            throw new Error('visits');
                        }
                        var byDomain = (result.data && result.data.by_domain) || {};
                        rows.forEach(function (row) {
                            var domain = row.getAttribute('data-cabinet-site-domain') || '';
                            fillRow(row, byDomain[domain] || null);
                        });
                        setMeta(result.data && result.data.meta);
                        if (typeof window.__cabinetHomeSitesFloatUpdate === 'function') {
                            window.__cabinetHomeSitesFloatUpdate();
                        }
                    })
                    .catch(function () {
                        clearSkeletons();
                        var metaEl = root.querySelector('[data-sites-visits-meta]');
                        if (metaEl) {
                            metaEl.classList.remove('d-none');
                            metaEl.removeAttribute('data-loading');
                            metaEl.innerHTML = '<span class="text-secondary">' +
                                @json(__('Metrika visits unavailable')) +
                                '</span>';
                        }
                    });
            })();

            document.querySelectorAll('.cabinet-home-cards-v2-open').forEach(function (link) {
                link.addEventListener('click', function () {
                    var card = link.closest('[data-project-id]');
                    if (!card || typeof $ === 'undefined') {
                        return;
                    }
                    $.ajax({
                        type: 'post',
                        url: @json(route('click.tracking')),
                        data: {
                            _token: document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                            button_text: link.getAttribute('data-track') || 'open_module_cards_v2',
                            url: location.href,
                            project_id: card.getAttribute('data-project-id'),
                        },
                    });
                });
            });

            // Яндекс.Метрика: клик по кружку → OAuth / выбор счётчика
            (function initMetrikaPicker() {
                var root = document.getElementById('cabinet-home-sites');
                var modalEl = document.getElementById('cabinet-metrika-modal');
                if (!root || !modalEl) {
                    return;
                }
                var csrfEl = document.querySelector('meta[name="csrf-token"]');
                var csrfToken = csrfEl ? csrfEl.getAttribute('content') : '';
                var currentDomain = '';
                var allCounters = [];
                var selectedCounterId = 0;
                var listEl = modalEl.querySelector('[data-metrika-list]');
                var loadingEl = modalEl.querySelector('[data-metrika-loading]');
                var errorEl = modalEl.querySelector('[data-metrika-error]');
                var authEl = modalEl.querySelector('[data-metrika-auth]');
                var authLink = modalEl.querySelector('[data-metrika-auth-link]');
                var domainLabel = modalEl.querySelector('[data-metrika-domain-label]');
                var currentEl = modalEl.querySelector('[data-metrika-current]');
                var unbindBtn = modalEl.querySelector('[data-metrika-unbind]');
                var searchWrap = modalEl.querySelector('[data-metrika-search-wrap]');
                var searchInput = modalEl.querySelector('[data-metrika-search]');

                function showModal() {
                    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                        bootstrap.Modal.getOrCreateInstance(modalEl).show();
                        return;
                    }
                    if (typeof $ !== 'undefined' && $.fn.modal) {
                        $(modalEl).modal('show');
                    }
                }

                function connectUrl(domain) {
                    var base = root.getAttribute('data-metrika-connect-url') || '';
                    var ret = root.getAttribute('data-metrika-return') || location.href;
                    return base + (base.indexOf('?') === -1 ? '?' : '&') +
                        'domain=' + encodeURIComponent(domain || '') +
                        '&return=' + encodeURIComponent(ret);
                }

                function setError(msg) {
                    if (!errorEl) return;
                    errorEl.textContent = msg || '';
                    errorEl.classList.toggle('d-none', !msg);
                }

                function setLoading(on) {
                    if (loadingEl) loadingEl.classList.toggle('d-none', !on);
                }

                function setSearchVisible(on) {
                    if (searchWrap) searchWrap.classList.toggle('d-none', !on);
                    if (!on && searchInput) searchInput.value = '';
                }

                function filterCounters(counters, query) {
                    var q = String(query || '').trim().toLowerCase();
                    if (!q) return counters.slice();
                    return counters.filter(function (c) {
                        var name = String(c.name || '').toLowerCase();
                        var site = String(c.site || '').toLowerCase();
                        var id = String(c.id || '');
                        return name.indexOf(q) !== -1 || site.indexOf(q) !== -1 || id.indexOf(q) !== -1;
                    });
                }

                function renderCounters(counters, selectedId) {
                    if (!listEl) return;
                    listEl.innerHTML = '';
                    if (!counters.length) {
                        listEl.innerHTML = '<div class="list-group-item text-secondary small">' +
                            (allCounters.length
                                ? @json(__('No counters match the search'))
                                : @json(__('No Metrika counters found'))) + '</div>';
                        return;
                    }
                    counters.forEach(function (c) {
                        var btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-start gap-2';
                        btn.setAttribute('data-metrika-counter-id', String(c.id));
                        if (selectedId && Number(selectedId) === Number(c.id)) {
                            btn.classList.add('active');
                        }
                        btn.innerHTML =
                            '<span class="text-start">' +
                            '<strong>' + String(c.name || ('#' + c.id)).replace(/</g, '&lt;') + '</strong>' +
                            '<br><span class="small opacity-75">' + String(c.site || '').replace(/</g, '&lt;') +
                            ' · ID ' + c.id + '</span></span>' +
                            '<span class="cabinet-metrika-counter-status flex-shrink-0 align-self-center">' +
                            (selectedId && Number(selectedId) === Number(c.id)
                                ? '<span class="badge text-bg-light text-dark border">' + @json(__('Selected')) + '</span>'
                                : '') +
                            '</span>';
                        btn.addEventListener('click', function () {
                            bindCounter(c.id, btn);
                        });
                        listEl.appendChild(btn);
                    });
                }

                function applyCounterFilter() {
                    renderCounters(
                        filterCounters(allCounters, searchInput ? searchInput.value : ''),
                        selectedCounterId
                    );
                }

                function setBindingBusy(busy, activeBtn) {
                    if (searchInput) {
                        searchInput.disabled = !!busy;
                    }
                    if (unbindBtn) {
                        unbindBtn.disabled = !!busy;
                    }
                    if (!listEl) return;
                    listEl.querySelectorAll('button[data-metrika-counter-id]').forEach(function (btn) {
                        var isActive = busy && activeBtn && btn === activeBtn;
                        btn.disabled = !!busy && !isActive;
                        btn.classList.toggle('cabinet-metrika-counter--dimmed', !!busy && !isActive);
                        btn.setAttribute('aria-busy', isActive ? 'true' : 'false');
                        if (!busy) {
                            btn.classList.remove('cabinet-metrika-counter--binding', 'cabinet-metrika-counter--dimmed');
                            btn.removeAttribute('aria-busy');
                            var status = btn.querySelector('.cabinet-metrika-counter-status');
                            if (status && status.getAttribute('data-binding') === '1') {
                                status.removeAttribute('data-binding');
                                status.innerHTML = Number(btn.getAttribute('data-metrika-counter-id')) === Number(selectedCounterId)
                                    ? '<span class="badge text-bg-light text-dark border">' + @json(__('Selected')) + '</span>'
                                    : '';
                            }
                        }
                    });
                    if (busy && activeBtn) {
                        activeBtn.classList.add('cabinet-metrika-counter--binding');
                        activeBtn.classList.remove('active');
                        var statusEl = activeBtn.querySelector('.cabinet-metrika-counter-status');
                        if (statusEl) {
                            statusEl.setAttribute('data-binding', '1');
                            statusEl.innerHTML =
                                '<span class="cabinet-metrika-binding-label">' +
                                '<span class="cabinet-metrika-spinner" aria-hidden="true"></span>' +
                                @json(__('Linking Metrika counter')) +
                                '…</span>';
                        }
                    }
                    if (typeof window.__cabinetHomeSitesFloatUpdate === 'function') {
                        window.__cabinetHomeSitesFloatUpdate();
                    }
                }

                function openForDomain(domain) {
                    currentDomain = domain || '';
                    allCounters = [];
                    selectedCounterId = 0;
                    if (domainLabel) domainLabel.textContent = currentDomain || '—';
                    if (authEl) authEl.classList.add('d-none');
                    if (listEl) listEl.innerHTML = '';
                    setSearchVisible(false);
                    if (currentEl) {
                        currentEl.classList.add('d-none');
                        currentEl.textContent = '';
                    }
                    if (unbindBtn) unbindBtn.classList.add('d-none');
                    setError('');
                    setLoading(true);
                    if (authLink) authLink.href = connectUrl(currentDomain);
                    showModal();

                    var bindingUrl = root.getAttribute('data-metrika-binding-url') +
                        '?domain=' + encodeURIComponent(currentDomain);
                    fetch(bindingUrl, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                        .then(function (r) { return r.json(); })
                        .then(function (info) {
                            if (!info || !info.ok) {
                                throw new Error('binding');
                            }
                            if (!info.configured) {
                                setLoading(false);
                                setError(@json(__('Yandex Metrika is not configured')));
                                return null;
                            }
                            if (!info.connected) {
                                setLoading(false);
                                if (authEl) authEl.classList.remove('d-none');
                                return null;
                            }
                            if (info.binding && currentEl) {
                                currentEl.textContent = @json(__('Current counter')) + ': ' +
                                    (info.binding.counter_name || ('#' + info.binding.counter_id)) +
                                    (info.binding.counter_site ? ' (' + info.binding.counter_site + ')' : '');
                                currentEl.classList.remove('d-none');
                                if (unbindBtn) unbindBtn.classList.remove('d-none');
                            }
                            return fetch(root.getAttribute('data-metrika-counters-url'), {
                                headers: { 'Accept': 'application/json' },
                                credentials: 'same-origin',
                            }).then(function (r) {
                                return r.json().then(function (data) {
                                    return { status: r.status, data: data, selected: info.binding && info.binding.counter_id };
                                });
                            });
                        })
                        .then(function (result) {
                            setLoading(false);
                            if (!result) return;
                            if (result.status === 401 || (result.data && result.data.need_auth)) {
                                if (authEl) authEl.classList.remove('d-none');
                                return;
                            }
                            if (!result.data || !result.data.ok) {
                                setError((result.data && result.data.message) || @json(__('Could not load Metrika counters')));
                                return;
                            }
                            allCounters = result.data.counters || [];
                            selectedCounterId = result.selected || 0;
                            setSearchVisible(allCounters.length > 0);
                            applyCounterFilter();
                        })
                        .catch(function () {
                            setLoading(false);
                            setError(@json(__('Could not load Metrika counters')));
                        });
                }

                function bindCounter(counterId, btn) {
                    setError('');
                    setBindingBusy(true, btn || null);
                    fetch(root.getAttribute('data-metrika-bind-url'), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({ domain: currentDomain, counter_id: counterId }),
                    })
                        .then(function (r) { return r.json(); })
                        .then(function (data) {
                            if (!data || !data.ok) {
                                throw new Error((data && data.message) || 'bind');
                            }
                            window.location.reload();
                        })
                        .catch(function () {
                            setBindingBusy(false);
                            setError(@json(__('Could not bind Metrika counter')));
                        });
                }

                if (unbindBtn) {
                    unbindBtn.addEventListener('click', function () {
                        if (!currentDomain) return;
                        setLoading(true);
                        fetch(root.getAttribute('data-metrika-unbind-url'), {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            credentials: 'same-origin',
                            body: JSON.stringify({ domain: currentDomain }),
                        })
                            .then(function (r) { return r.json(); })
                            .then(function (data) {
                                if (!data || !data.ok) throw new Error('unbind');
                                window.location.reload();
                            })
                            .catch(function () {
                                setLoading(false);
                                setError(@json(__('Could not unbind Metrika counter')));
                            });
                    });
                }

                if (searchInput) {
                    searchInput.addEventListener('input', applyCounterFilter);
                }

                root.addEventListener('click', function (event) {
                    var btn = event.target.closest('[data-cabinet-metrika-dot]');
                    if (!btn) return;
                    event.preventDefault();
                    openForDomain(btn.getAttribute('data-domain') || '');
                });

                try {
                    var params = new URLSearchParams(window.location.search);
                    if (params.get('metrika_picker') === '1') {
                        var d = params.get('metrika_domain') || '';
                        openForDomain(d);
                        if (window.history && window.history.replaceState) {
                            params.delete('metrika_picker');
                            params.delete('metrika_domain');
                            var q = params.toString();
                            window.history.replaceState({}, '', window.location.pathname + (q ? '?' + q : '') + window.location.hash);
                        }
                    }
                } catch (e) {}
            })();

            // Яндекс.Вебмастер: клик по кружку → OAuth / выбор хоста
            (function initWebmasterPicker() {
                var root = document.getElementById('cabinet-home-sites');
                var modalEl = document.getElementById('cabinet-webmaster-modal');
                if (!root || !modalEl) {
                    return;
                }
                var csrfEl = document.querySelector('meta[name="csrf-token"]');
                var csrfToken = csrfEl ? csrfEl.getAttribute('content') : '';
                var currentDomain = '';
                var allHosts = [];
                var selectedHostId = '';
                var listEl = modalEl.querySelector('[data-webmaster-list]');
                var loadingEl = modalEl.querySelector('[data-webmaster-loading]');
                var errorEl = modalEl.querySelector('[data-webmaster-error]');
                var authEl = modalEl.querySelector('[data-webmaster-auth]');
                var authLink = modalEl.querySelector('[data-webmaster-auth-link]');
                var domainLabel = modalEl.querySelector('[data-webmaster-domain-label]');
                var currentEl = modalEl.querySelector('[data-webmaster-current]');
                var unbindBtn = modalEl.querySelector('[data-webmaster-unbind]');
                var searchWrap = modalEl.querySelector('[data-webmaster-search-wrap]');
                var searchInput = modalEl.querySelector('[data-webmaster-search]');

                function showModal() {
                    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                        bootstrap.Modal.getOrCreateInstance(modalEl).show();
                        return;
                    }
                    if (typeof $ !== 'undefined' && $.fn.modal) {
                        $(modalEl).modal('show');
                    }
                }

                function connectUrl(domain) {
                    var base = root.getAttribute('data-webmaster-connect-url') || '';
                    var ret = root.getAttribute('data-webmaster-return') || location.href;
                    return base + (base.indexOf('?') === -1 ? '?' : '&') +
                        'domain=' + encodeURIComponent(domain || '') +
                        '&return=' + encodeURIComponent(ret);
                }

                function setError(msg) {
                    if (!errorEl) return;
                    errorEl.textContent = msg || '';
                    errorEl.classList.toggle('d-none', !msg);
                }

                function setLoading(on) {
                    if (loadingEl) loadingEl.classList.toggle('d-none', !on);
                }

                function setSearchVisible(on) {
                    if (searchWrap) searchWrap.classList.toggle('d-none', !on);
                    if (!on && searchInput) searchInput.value = '';
                }

                function filterHosts(hosts, query) {
                    var q = String(query || '').trim().toLowerCase();
                    if (!q) return hosts.slice();
                    return hosts.filter(function (h) {
                        var url = String(h.unicode_url || h.url || '').toLowerCase();
                        var id = String(h.id || '').toLowerCase();
                        var domain = String(h.domain || '').toLowerCase();
                        return url.indexOf(q) !== -1 || id.indexOf(q) !== -1 || domain.indexOf(q) !== -1;
                    });
                }

                function renderHosts(hosts, selectedId) {
                    if (!listEl) return;
                    listEl.innerHTML = '';
                    if (!hosts.length) {
                        listEl.innerHTML = '<div class="list-group-item text-secondary small">' +
                            (allHosts.length
                                ? @json(__('No hosts match the search'))
                                : @json(__('No Webmaster hosts found'))) + '</div>';
                        return;
                    }
                    hosts.forEach(function (h) {
                        var btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-start gap-2';
                        btn.setAttribute('data-webmaster-host-id', String(h.id));
                        if (selectedId && String(selectedId) === String(h.id)) {
                            btn.classList.add('active');
                        }
                        var title = String(h.unicode_url || h.url || h.id || '').replace(/</g, '&lt;');
                        var meta = String(h.id || '').replace(/</g, '&lt;');
                        if (h.verified) {
                            meta += ' · ' + @json(__('Webmaster host verified'));
                        }
                        btn.innerHTML =
                            '<span class="text-start">' +
                            '<strong>' + title + '</strong>' +
                            '<br><span class="small opacity-75">' + meta + '</span></span>' +
                            '<span class="cabinet-metrika-counter-status flex-shrink-0 align-self-center">' +
                            (selectedId && String(selectedId) === String(h.id)
                                ? '<span class="badge text-bg-light text-dark border">' + @json(__('Selected')) + '</span>'
                                : '') +
                            '</span>';
                        btn.addEventListener('click', function () {
                            bindHost(h.id, btn);
                        });
                        listEl.appendChild(btn);
                    });
                }

                function applyHostFilter() {
                    renderHosts(
                        filterHosts(allHosts, searchInput ? searchInput.value : ''),
                        selectedHostId
                    );
                }

                function setBindingBusy(busy, activeBtn) {
                    if (searchInput) {
                        searchInput.disabled = !!busy;
                    }
                    if (unbindBtn) {
                        unbindBtn.disabled = !!busy;
                    }
                    if (!listEl) return;
                    listEl.querySelectorAll('button[data-webmaster-host-id]').forEach(function (btn) {
                        var isActive = busy && activeBtn && btn === activeBtn;
                        btn.disabled = !!busy && !isActive;
                        btn.classList.toggle('cabinet-metrika-counter--dimmed', !!busy && !isActive);
                        btn.setAttribute('aria-busy', isActive ? 'true' : 'false');
                        if (!busy) {
                            btn.classList.remove('cabinet-metrika-counter--binding', 'cabinet-metrika-counter--dimmed');
                            btn.removeAttribute('aria-busy');
                            var status = btn.querySelector('.cabinet-metrika-counter-status');
                            if (status && status.getAttribute('data-binding') === '1') {
                                status.removeAttribute('data-binding');
                                status.innerHTML = String(btn.getAttribute('data-webmaster-host-id')) === String(selectedHostId)
                                    ? '<span class="badge text-bg-light text-dark border">' + @json(__('Selected')) + '</span>'
                                    : '';
                            }
                        }
                    });
                    if (busy && activeBtn) {
                        activeBtn.classList.add('cabinet-metrika-counter--binding');
                        activeBtn.classList.remove('active');
                        var statusEl = activeBtn.querySelector('.cabinet-metrika-counter-status');
                        if (statusEl) {
                            statusEl.setAttribute('data-binding', '1');
                            statusEl.innerHTML =
                                '<span class="cabinet-metrika-binding-label">' +
                                '<span class="cabinet-metrika-spinner" aria-hidden="true"></span>' +
                                @json(__('Linking Webmaster host')) +
                                '…</span>';
                        }
                    }
                    if (typeof window.__cabinetHomeSitesFloatUpdate === 'function') {
                        window.__cabinetHomeSitesFloatUpdate();
                    }
                }

                function openForDomain(domain) {
                    currentDomain = domain || '';
                    allHosts = [];
                    selectedHostId = '';
                    if (domainLabel) domainLabel.textContent = currentDomain || '—';
                    if (authEl) authEl.classList.add('d-none');
                    if (listEl) listEl.innerHTML = '';
                    setSearchVisible(false);
                    if (currentEl) {
                        currentEl.classList.add('d-none');
                        currentEl.textContent = '';
                    }
                    if (unbindBtn) unbindBtn.classList.add('d-none');
                    setError('');
                    setLoading(true);
                    if (authLink) authLink.href = connectUrl(currentDomain);
                    showModal();

                    var bindingUrl = root.getAttribute('data-webmaster-binding-url') +
                        '?domain=' + encodeURIComponent(currentDomain);
                    fetch(bindingUrl, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                        .then(function (r) { return r.json(); })
                        .then(function (info) {
                            if (!info || !info.ok) {
                                throw new Error('binding');
                            }
                            if (!info.configured) {
                                setLoading(false);
                                setError(@json(__('Yandex Webmaster is not configured')));
                                return null;
                            }
                            if (!info.connected) {
                                setLoading(false);
                                if (authEl) authEl.classList.remove('d-none');
                                return null;
                            }
                            if (info.binding && currentEl) {
                                currentEl.textContent = @json(__('Current Webmaster host')) + ': ' +
                                    (info.binding.host_url || info.binding.host_id);
                                currentEl.classList.remove('d-none');
                                if (unbindBtn) unbindBtn.classList.remove('d-none');
                            }
                            return fetch(root.getAttribute('data-webmaster-hosts-url'), {
                                headers: { 'Accept': 'application/json' },
                                credentials: 'same-origin',
                            }).then(function (r) {
                                return r.json().then(function (data) {
                                    return { status: r.status, data: data, selected: info.binding && info.binding.host_id };
                                });
                            });
                        })
                        .then(function (result) {
                            setLoading(false);
                            if (!result) return;
                            if (result.status === 401 || (result.data && result.data.need_auth)) {
                                if (authEl) authEl.classList.remove('d-none');
                                return;
                            }
                            if (!result.data || !result.data.ok) {
                                setError((result.data && result.data.message) || @json(__('Could not load Webmaster hosts')));
                                return;
                            }
                            allHosts = result.data.hosts || [];
                            selectedHostId = result.selected || '';
                            setSearchVisible(allHosts.length > 0);
                            applyHostFilter();
                        })
                        .catch(function () {
                            setLoading(false);
                            setError(@json(__('Could not load Webmaster hosts')));
                        });
                }

                function bindHost(hostId, btn) {
                    setError('');
                    setBindingBusy(true, btn || null);
                    fetch(root.getAttribute('data-webmaster-bind-url'), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({ domain: currentDomain, host_id: hostId }),
                    })
                        .then(function (r) { return r.json(); })
                        .then(function (data) {
                            if (!data || !data.ok) {
                                throw new Error((data && data.message) || 'bind');
                            }
                            window.location.reload();
                        })
                        .catch(function () {
                            setBindingBusy(false);
                            setError(@json(__('Could not bind Webmaster host')));
                        });
                }

                if (unbindBtn) {
                    unbindBtn.addEventListener('click', function () {
                        if (!currentDomain) return;
                        setLoading(true);
                        fetch(root.getAttribute('data-webmaster-unbind-url'), {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            credentials: 'same-origin',
                            body: JSON.stringify({ domain: currentDomain }),
                        })
                            .then(function (r) { return r.json(); })
                            .then(function (data) {
                                if (!data || !data.ok) throw new Error('unbind');
                                window.location.reload();
                            })
                            .catch(function () {
                                setLoading(false);
                                setError(@json(__('Could not unbind Webmaster host')));
                            });
                    });
                }

                if (searchInput) {
                    searchInput.addEventListener('input', applyHostFilter);
                }

                root.addEventListener('click', function (event) {
                    var btn = event.target.closest('[data-cabinet-webmaster-dot]');
                    if (!btn) return;
                    event.preventDefault();
                    openForDomain(btn.getAttribute('data-domain') || '');
                });

                try {
                    var params = new URLSearchParams(window.location.search);
                    if (params.get('webmaster_picker') === '1') {
                        var d = params.get('webmaster_domain') || '';
                        openForDomain(d);
                        if (window.history && window.history.replaceState) {
                            params.delete('webmaster_picker');
                            params.delete('webmaster_domain');
                            var q = params.toString();
                            window.history.replaceState({}, '', window.location.pathname + (q ? '?' + q : '') + window.location.hash);
                        }
                    }
                } catch (e) {}
            })();

            // Google Search Console: клик по кружку → OAuth / выбор property
            (function initGscPicker() {
                var root = document.getElementById('cabinet-home-sites');
                var modalEl = document.getElementById('cabinet-gsc-modal');
                if (!root || !modalEl) {
                    return;
                }
                var csrfEl = document.querySelector('meta[name="csrf-token"]');
                var csrfToken = csrfEl ? csrfEl.getAttribute('content') : '';
                var currentDomain = '';
                var allProperties = [];
                var selectedPropertyId = '';
                var listEl = modalEl.querySelector('[data-gsc-list]');
                var loadingEl = modalEl.querySelector('[data-gsc-loading]');
                var errorEl = modalEl.querySelector('[data-gsc-error]');
                var authEl = modalEl.querySelector('[data-gsc-auth]');
                var authLink = modalEl.querySelector('[data-gsc-auth-link]');
                var domainLabel = modalEl.querySelector('[data-gsc-domain-label]');
                var currentEl = modalEl.querySelector('[data-gsc-current]');
                var unbindBtn = modalEl.querySelector('[data-gsc-unbind]');
                var searchWrap = modalEl.querySelector('[data-gsc-search-wrap]');
                var searchInput = modalEl.querySelector('[data-gsc-search]');

                function showModal() {
                    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                        bootstrap.Modal.getOrCreateInstance(modalEl).show();
                        return;
                    }
                    if (typeof $ !== 'undefined' && $.fn.modal) {
                        $(modalEl).modal('show');
                    }
                }

                function connectUrl(domain) {
                    var base = root.getAttribute('data-gsc-connect-url') || '';
                    var ret = root.getAttribute('data-gsc-return') || location.href;
                    return base + (base.indexOf('?') === -1 ? '?' : '&') +
                        'domain=' + encodeURIComponent(domain || '') +
                        '&return=' + encodeURIComponent(ret);
                }

                function setError(msg) {
                    if (!errorEl) return;
                    errorEl.textContent = msg || '';
                    errorEl.classList.toggle('d-none', !msg);
                }

                function setLoading(on) {
                    if (loadingEl) loadingEl.classList.toggle('d-none', !on);
                }

                function setSearchVisible(on) {
                    if (searchWrap) searchWrap.classList.toggle('d-none', !on);
                    if (!on && searchInput) searchInput.value = '';
                }

                function filterProperties(hosts, query) {
                    var q = String(query || '').trim().toLowerCase();
                    if (!q) return hosts.slice();
                    return hosts.filter(function (h) {
                        var url = String(h.unicode_url || h.url || '').toLowerCase();
                        var id = String(h.id || '').toLowerCase();
                        var domain = String(h.domain || '').toLowerCase();
                        return url.indexOf(q) !== -1 || id.indexOf(q) !== -1 || domain.indexOf(q) !== -1;
                    });
                }

                function renderProperties(hosts, selectedId) {
                    if (!listEl) return;
                    listEl.innerHTML = '';
                    if (!hosts.length) {
                        listEl.innerHTML = '<div class="list-group-item text-secondary small">' +
                            (allProperties.length
                                ? @json(__('No hosts match the search'))
                                : @json(__('No GSC properties found'))) + '</div>';
                        return;
                    }
                    hosts.forEach(function (h) {
                        var btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-start gap-2';
                        btn.setAttribute('data-gsc-property-id', String(h.id));
                        if (selectedId && String(selectedId) === String(h.id)) {
                            btn.classList.add('active');
                        }
                        var title = String(h.unicode_url || h.url || h.id || '').replace(/</g, '&lt;');
                        var meta = String(h.id || '').replace(/</g, '&lt;');
                        if (h.verified) {
                            meta += ' · ' + @json(__('GSC property verified'));
                        }
                        btn.innerHTML =
                            '<span class="text-start">' +
                            '<strong>' + title + '</strong>' +
                            '<br><span class="small opacity-75">' + meta + '</span></span>' +
                            '<span class="cabinet-metrika-counter-status flex-shrink-0 align-self-center">' +
                            (selectedId && String(selectedId) === String(h.id)
                                ? '<span class="badge text-bg-light text-dark border">' + @json(__('Selected')) + '</span>'
                                : '') +
                            '</span>';
                        btn.addEventListener('click', function () {
                            bindProperty(h.id, btn);
                        });
                        listEl.appendChild(btn);
                    });
                }

                function applyPropertyFilter() {
                    renderProperties(
                        filterProperties(allProperties, searchInput ? searchInput.value : ''),
                        selectedPropertyId
                    );
                }

                function setBindingBusy(busy, activeBtn) {
                    if (searchInput) {
                        searchInput.disabled = !!busy;
                    }
                    if (unbindBtn) {
                        unbindBtn.disabled = !!busy;
                    }
                    if (!listEl) return;
                    listEl.querySelectorAll('button[data-gsc-property-id]').forEach(function (btn) {
                        var isActive = busy && activeBtn && btn === activeBtn;
                        btn.disabled = !!busy && !isActive;
                        btn.classList.toggle('cabinet-metrika-counter--dimmed', !!busy && !isActive);
                        btn.setAttribute('aria-busy', isActive ? 'true' : 'false');
                        if (!busy) {
                            btn.classList.remove('cabinet-metrika-counter--binding', 'cabinet-metrika-counter--dimmed');
                            btn.removeAttribute('aria-busy');
                            var status = btn.querySelector('.cabinet-metrika-counter-status');
                            if (status && status.getAttribute('data-binding') === '1') {
                                status.removeAttribute('data-binding');
                                status.innerHTML = String(btn.getAttribute('data-gsc-property-id')) === String(selectedPropertyId)
                                    ? '<span class="badge text-bg-light text-dark border">' + @json(__('Selected')) + '</span>'
                                    : '';
                            }
                        }
                    });
                    if (busy && activeBtn) {
                        activeBtn.classList.add('cabinet-metrika-counter--binding');
                        activeBtn.classList.remove('active');
                        var statusEl = activeBtn.querySelector('.cabinet-metrika-counter-status');
                        if (statusEl) {
                            statusEl.setAttribute('data-binding', '1');
                            statusEl.innerHTML =
                                '<span class="cabinet-metrika-binding-label">' +
                                '<span class="cabinet-metrika-spinner" aria-hidden="true"></span>' +
                                @json(__('Linking Webmaster host')) +
                                '…</span>';
                        }
                    }
                    if (typeof window.__cabinetHomeSitesFloatUpdate === 'function') {
                        window.__cabinetHomeSitesFloatUpdate();
                    }
                }

                function openForDomain(domain) {
                    currentDomain = domain || '';
                    allProperties = [];
                    selectedPropertyId = '';
                    if (domainLabel) domainLabel.textContent = currentDomain || '—';
                    if (authEl) authEl.classList.add('d-none');
                    if (listEl) listEl.innerHTML = '';
                    setSearchVisible(false);
                    if (currentEl) {
                        currentEl.classList.add('d-none');
                        currentEl.textContent = '';
                    }
                    if (unbindBtn) unbindBtn.classList.add('d-none');
                    setError('');
                    setLoading(true);
                    if (authLink) authLink.href = connectUrl(currentDomain);
                    showModal();

                    var bindingUrl = root.getAttribute('data-gsc-binding-url') +
                        '?domain=' + encodeURIComponent(currentDomain);
                    fetch(bindingUrl, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                        .then(function (r) { return r.json(); })
                        .then(function (info) {
                            if (!info || !info.ok) {
                                throw new Error('binding');
                            }
                            if (!info.configured) {
                                setLoading(false);
                                setError(@json(__('Google Search Console is not configured')));
                                return null;
                            }
                            if (!info.connected) {
                                setLoading(false);
                                if (authEl) authEl.classList.remove('d-none');
                                return null;
                            }
                            if (info.binding && currentEl) {
                                currentEl.textContent = @json(__('Current GSC property')) + ': ' +
                                    (info.binding.property_url || info.binding.property_id || info.binding.host_url || info.binding.host_id);
                                currentEl.classList.remove('d-none');
                                if (unbindBtn) unbindBtn.classList.remove('d-none');
                            }
                            return fetch(root.getAttribute('data-gsc-properties-url'), {
                                headers: { 'Accept': 'application/json' },
                                credentials: 'same-origin',
                            }).then(function (r) {
                                return r.json().then(function (data) {
                                    return { status: r.status, data: data, selected: info.binding && (info.binding.property_id || info.binding.host_id) };
                                });
                            });
                        })
                        .then(function (result) {
                            setLoading(false);
                            if (!result) return;
                            if (result.status === 401 || (result.data && result.data.need_auth)) {
                                if (authEl) authEl.classList.remove('d-none');
                                return;
                            }
                            if (!result.data || !result.data.ok) {
                                setError((result.data && result.data.message) || @json(__('Could not load GSC properties')));
                                return;
                            }
                            allProperties = result.data.properties || result.data.hosts || [];
                            selectedPropertyId = result.selected || '';
                            setSearchVisible(allProperties.length > 0);
                            applyPropertyFilter();
                        })
                        .catch(function () {
                            setLoading(false);
                            setError(@json(__('Could not load GSC properties')));
                        });
                }

                function bindProperty(hostId, btn) {
                    setError('');
                    setBindingBusy(true, btn || null);
                    fetch(root.getAttribute('data-gsc-bind-url'), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({ domain: currentDomain, property_id: hostId }),
                    })
                        .then(function (r) { return r.json(); })
                        .then(function (data) {
                            if (!data || !data.ok) {
                                throw new Error((data && data.message) || 'bind');
                            }
                            window.location.reload();
                        })
                        .catch(function () {
                            setBindingBusy(false);
                            setError(@json(__('Could not bind GSC property')));
                        });
                }

                if (unbindBtn) {
                    unbindBtn.addEventListener('click', function () {
                        if (!currentDomain) return;
                        setLoading(true);
                        fetch(root.getAttribute('data-gsc-unbind-url'), {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            credentials: 'same-origin',
                            body: JSON.stringify({ domain: currentDomain }),
                        })
                            .then(function (r) { return r.json(); })
                            .then(function (data) {
                                if (!data || !data.ok) throw new Error('unbind');
                                window.location.reload();
                            })
                            .catch(function () {
                                setLoading(false);
                                setError(@json(__('Could not unbind GSC property')));
                            });
                    });
                }

                if (searchInput) {
                    searchInput.addEventListener('input', applyPropertyFilter);
                }

                root.addEventListener('click', function (event) {
                    var btn = event.target.closest('[data-cabinet-gsc-dot]');
                    if (!btn) return;
                    event.preventDefault();
                    openForDomain(btn.getAttribute('data-domain') || '');
                });

                try {
                    var params = new URLSearchParams(window.location.search);
                    if (params.get('gsc_picker') === '1') {
                        var d = params.get('gsc_domain') || '';
                        openForDomain(d);
                        if (window.history && window.history.replaceState) {
                            params.delete('gsc_picker');
                            params.delete('gsc_domain');
                            var q = params.toString();
                            window.history.replaceState({}, '', window.location.pathname + (q ? '?' + q : '') + window.location.hash);
                        }
                    }
                } catch (e) {}
            })();
        })();
    </script>
@endsection
