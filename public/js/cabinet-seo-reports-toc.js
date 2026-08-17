/**
 * Липкое меню «Разделы отчёта».
 *
 * CSS position:sticky ломается из‑за overflow у .app-main / layout-fixed.
 * Паттерн как у SEO-чеклиста (cabinet-seo-checklist-hub.js): slot + position:fixed.
 */
(function () {
    'use strict';

    function init() {
        var toc = document.querySelector('[data-sr-toc]');
        var tocSlot = document.querySelector('[data-sr-toc-slot]');
        var report = document.querySelector('[data-sr-report]');
        if (!toc || !tocSlot || !report) return;

        var bar = document.querySelector('[data-sr-toc-bar]');
        var linksNav = toc.querySelector('.cabinet-sr-toc__links');
        var links = Array.prototype.slice.call(toc.querySelectorAll('[data-sr-toc-link]'));
        var sections = links.map(function (a) {
            return document.getElementById(a.getAttribute('data-sr-toc-link') || '');
        }).filter(Boolean);

        var activeId = '';
        var mainEl = document.querySelector('.app-main');
        var mobileMq = window.matchMedia('(max-width: 980px)');
        var pinTicking = false;
        var topGap = 12;
        var bottomGap = 0;

        function demoBannerHeight() {
            var banner = document.querySelector('.cabinet-sr-demo-banner');
            return banner ? Math.ceil(banner.getBoundingClientRect().height) : 0;
        }

        function probeOffset() {
            var h = demoBannerHeight();
            var viewH = window.innerHeight || document.documentElement.clientHeight || 0;
            return Math.max(Math.round(h + 24), Math.round(viewH * 0.32));
        }

        function setActive(id) {
            if (!id || id === activeId) return;
            activeId = id;
            var activeLink = null;
            links.forEach(function (a) {
                var on = a.getAttribute('data-sr-toc-link') === id;
                a.classList.toggle('is-active', on);
                if (on) activeLink = a;
            });
            if (activeLink && linksNav && typeof activeLink.scrollIntoView === 'function') {
                var linkBox = activeLink.getBoundingClientRect();
                var navBox = linksNav.getBoundingClientRect();
                if (linkBox.top < navBox.top + 4 || linkBox.bottom > navBox.bottom - 4) {
                    activeLink.scrollIntoView({ block: 'nearest' });
                }
            }
        }

        function unpin() {
            toc.classList.remove('is-pinned');
            toc.style.top = '';
            toc.style.left = '';
            toc.style.width = '';
            toc.style.height = '';
            toc.style.maxHeight = '';
            tocSlot.style.height = '';
            tocSlot.style.maxHeight = '';
        }

        function viewportMetrics() {
            var bannerH = demoBannerHeight();
            var mainScrolls = !!(mainEl && mainEl.scrollHeight > mainEl.clientHeight + 5);
            if (mainScrolls) {
                var mr = mainEl.getBoundingClientRect();
                return {
                    stickLine: mr.top + topGap + bannerH,
                    viewH: mainEl.clientHeight || mr.height,
                    minTop: mr.top + 8 + bannerH
                };
            }
            return {
                stickLine: topGap + bannerH,
                viewH: window.innerHeight || document.documentElement.clientHeight || 800,
                minTop: 8 + bannerH
            };
        }

        function contentHeight(maxH) {
            var head = toc.querySelector('.cabinet-sr-toc__head');
            var h = 20;
            if (head) h += head.offsetHeight || 0;
            if (linksNav) h += linksNav.scrollHeight || linksNav.offsetHeight || 0;
            return Math.min(Math.max(h, 120), maxH);
        }

        function reportFloorTop() {
            // Пол = низ отчёта (Инсайты); форма текстов — только если выше.
            var floor = report.getBoundingClientRect().bottom;
            var after = document.querySelector('[data-sr-after-report], .cabinet-sr-texts');
            if (after) {
                floor = Math.min(floor, after.getBoundingClientRect().top);
            }
            return floor;
        }

        function syncPin() {
            if (mobileMq.matches) {
                unpin();
                return;
            }

            var metrics = viewportMetrics();
            var maxH = Math.max(160, Math.floor(metrics.viewH - topGap - bottomGap - demoBannerHeight()));
            var layoutRect = report.getBoundingClientRect();
            var slotRect = tocSlot.getBoundingClientRect();
            var stickLine = metrics.stickLine;

            tocSlot.style.maxHeight = maxH + 'px';

            // Весь отчёт ушёл выше линии прилипания — отпускаем.
            if (layoutRect.bottom <= stickLine + 40) {
                unpin();
                tocSlot.style.maxHeight = maxH + 'px';
                return;
            }

            // Ещё не доскроллили до слота — обычный поток.
            if (slotRect.top > stickLine + 1) {
                if (toc.classList.contains('is-pinned')) {
                    unpin();
                    tocSlot.style.maxHeight = maxH + 'px';
                }
                toc.style.maxHeight = maxH + 'px';
                return;
            }

            var floor = reportFloorTop();
            // Конец отчёта / Инсайты уже у верхней кромки — отлипаем.
            if (floor <= metrics.minTop + 24) {
                unpin();
                return;
            }

            var naturalH = contentHeight(maxH);
            // Низ TOC = пол (Инсайты / форма). При нехватке места уезжаем вверх (top может быть < 0).
            var pinTop = Math.min(stickLine, floor - naturalH);

            tocSlot.style.height = naturalH + 'px';
            tocSlot.style.maxHeight = naturalH + 'px';
            slotRect = tocSlot.getBoundingClientRect();

            toc.classList.add('is-pinned');
            toc.style.left = Math.round(slotRect.left) + 'px';
            toc.style.width = Math.round(slotRect.width) + 'px';
            toc.style.top = Math.round(pinTop) + 'px';
            toc.style.height = Math.round(naturalH) + 'px';
            toc.style.maxHeight = Math.round(naturalH) + 'px';
        }

        function requestPinSync() {
            if (pinTicking) return;
            pinTicking = true;
            window.requestAnimationFrame(function () {
                pinTicking = false;
                syncPin();
            });
        }

        function onScroll() {
            var viewH = window.innerHeight || document.documentElement.clientHeight || 0;
            var y = window.pageYOffset || document.documentElement.scrollTop || 0;
            if (mainEl && mainEl.scrollHeight > mainEl.clientHeight + 5) {
                y = mainEl.scrollTop || 0;
                viewH = mainEl.clientHeight || viewH;
            }
            var docH = Math.max(
                document.body ? document.body.scrollHeight : 0,
                document.documentElement.scrollHeight || 0
            );
            if (mainEl && mainEl.scrollHeight > mainEl.clientHeight + 5) {
                docH = mainEl.scrollHeight;
            }
            if (bar) {
                var max = Math.max(1, docH - viewH);
                bar.style.width = Math.min(100, Math.round((y / max) * 100)) + '%';
            }

            if (sections.length) {
                var probe = probeOffset();
                if (y + viewH >= docH - 80) {
                    probe = Math.max(probe, Math.round(viewH * 0.78));
                }
                var current = sections[0];
                for (var i = 0; i < sections.length; i++) {
                    if (sections[i].getBoundingClientRect().top <= probe) {
                        current = sections[i];
                    } else {
                        break;
                    }
                }
                if (current && current.id) setActive(current.id);
            }

            requestPinSync();
        }

        if (mainEl) {
            mainEl.addEventListener('scroll', onScroll, { passive: true });
        }
        window.addEventListener('scroll', onScroll, { passive: true, capture: true });
        window.addEventListener('resize', onScroll, { passive: true });
        if (mobileMq.addEventListener) {
            mobileMq.addEventListener('change', requestPinSync);
        } else if (mobileMq.addListener) {
            mobileMq.addListener(requestPinSync);
        }

        toc.querySelectorAll('[data-sr-section-jump]').forEach(function (a) {
            a.addEventListener('click', function (e) {
                var key = a.getAttribute('data-sr-section-jump');
                var el = key ? document.getElementById('sr-' + key) : null;
                if (!el) return;
                e.preventDefault();
                setActive('sr-' + key);
                el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                if (history && history.replaceState) {
                    history.replaceState(null, '', '#sr-' + key);
                }
                window.setTimeout(requestPinSync, 80);
            });
        });

        onScroll();
        window.setTimeout(requestPinSync, 50);
        window.setTimeout(requestPinSync, 300);

        var toggle = document.querySelector('[data-sr-compare-toggle]');
        if (toggle) {
            toggle.addEventListener('change', function () {
                report.setAttribute('data-sr-hide-compare', toggle.checked ? '0' : '1');
            });
        }

        document.querySelectorAll('table[data-sr-sortable]').forEach(function (table) {
            table.querySelectorAll('thead th').forEach(function (th, idx) {
                th.addEventListener('click', function () {
                    var tbody = table.tBodies[0];
                    if (!tbody) return;
                    var rows = Array.prototype.slice.call(tbody.rows);
                    var asc = th.getAttribute('data-sort') !== 'asc';
                    rows.sort(function (a, b) {
                        var av = (a.cells[idx] ? a.cells[idx].innerText : '').trim();
                        var bv = (b.cells[idx] ? b.cells[idx].innerText : '').trim();
                        var an = parseFloat(av.replace(/\s/g, '').replace(',', '.'));
                        var bn = parseFloat(bv.replace(/\s/g, '').replace(',', '.'));
                        if (!isNaN(an) && !isNaN(bn)) {
                            return asc ? an - bn : bn - an;
                        }
                        return asc ? av.localeCompare(bv, 'ru') : bv.localeCompare(av, 'ru');
                    });
                    th.setAttribute('data-sort', asc ? 'asc' : 'desc');
                    rows.forEach(function (r) { tbody.appendChild(r); });
                });
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
