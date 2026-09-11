{{-- Подключать один раз на странице с деревьями отчётов. --}}
<script>
(function () {
    if (window.__saTreeNavInit) return;
    window.__saTreeNavInit = true;

    var EN = "`qwertyuiop[]asdfghjkl;'zxcvbnm,./QWERTYUIOP{}ASDFGHJKL:\"ZXCVBNM<>?";
    var RU = "ёйцукенгшщзхъфывапролджэячсмитьбю.ЁЙЦУКЕНГШЩЗХЪФЫВАПРОЛДЖЭЯЧСМИТЬБЮ,";
    var flipMap = {};
    for (var i = 0; i < EN.length; i++) {
        flipMap[EN.charAt(i)] = RU.charAt(i);
        flipMap[RU.charAt(i)] = EN.charAt(i);
    }

    var PRESET_KEYS = { all: 1, hot: 1, critical: 1, other: 1, important: 1, warning: 1, info: 1 };

    function flipLayout(s) {
        var out = '';
        for (var i = 0; i < s.length; i++) {
            var ch = s.charAt(i);
            out += flipMap[ch] != null ? flipMap[ch] : ch;
        }
        return out;
    }

    function matchTitle(title, q) {
        if (!q) return true;
        var t = (title || '').toLowerCase();
        var a = q.toLowerCase();
        var b = flipLayout(q).toLowerCase();
        return t.indexOf(a) !== -1 || (b && t.indexOf(b) !== -1);
    }

    function crawlKey(tree) {
        var id = tree.getAttribute('data-crawl-id') || '';
        if (!id) {
            var m = String(window.location.pathname || '').match(/\/site-audit\/crawl\/(\d+)/);
            id = m ? m[1] : '';
        }
        return id ? ('sa-tree-preset:' + id) : '';
    }

    function readStoredPreset(tree) {
        var key = crawlKey(tree);
        if (!key) return null;
        try {
            var v = sessionStorage.getItem(key);
            return v && PRESET_KEYS[v] ? v : null;
        } catch (e) {
            return null;
        }
    }

    function writeStoredPreset(tree, preset) {
        var key = crawlKey(tree);
        if (!key || !PRESET_KEYS[preset]) return;
        try {
            sessionStorage.setItem(key, preset);
        } catch (e) {}
    }

    function currentPreset(tree) {
        var activePreset = tree.querySelector('.cabinet-sa-tree-preset.is-active');
        return activePreset ? activePreset.getAttribute('data-preset') : 'all';
    }

    function applyTree(tree) {
        var searchEl = tree.querySelector('.cabinet-sa-tree-search');
        var preset = currentPreset(tree) || 'all';
        var q = searchEl ? String(searchEl.value || '').trim() : '';
        var items = tree.querySelectorAll('.cabinet-sa-tree__item');
        var groups = tree.querySelectorAll('.cabinet-sa-tree__group');

        items.forEach(function (item) {
            var title = item.getAttribute('data-title') || item.textContent || '';
            var sev = item.getAttribute('data-severity') || '';
            var count = parseInt(item.getAttribute('data-count') || '0', 10) || 0;
            var isExternal = item.getAttribute('data-external') === '1';
            var probeSkipped = item.getAttribute('data-probe-skipped') === '1';
            var ok = matchTitle(title, q);
            if (ok && preset === 'hot') ok = count > 0;
            if (ok && preset !== 'all' && preset !== 'hot') {
                ok = sev === preset && (count > 0 || isExternal || probeSkipped);
            }
            item.classList.toggle('is-tree-hidden', !ok);
            item.hidden = !ok;
        });

        groups.forEach(function (group) {
            var visible = group.querySelectorAll('.cabinet-sa-tree__item:not(.is-tree-hidden)');
            group.hidden = visible.length === 0;
        });
    }

    function setPreset(tree, preset, persist) {
        var btn = tree.querySelector('.cabinet-sa-tree-preset[data-preset="' + preset + '"]');
        if (!btn) return;
        tree.querySelectorAll('.cabinet-sa-tree-preset').forEach(function (b) {
            b.classList.toggle('is-active', b === btn);
        });
        if (persist !== false) {
            writeStoredPreset(tree, preset);
        }
        applyTree(tree);
    }

    function restorePreset(tree) {
        var stored = readStoredPreset(tree);
        if (stored && stored !== currentPreset(tree)) {
            setPreset(tree, stored, false);
            return;
        }
        applyTree(tree);
    }

    function bindTree(tree) {
        if (tree.getAttribute('data-sa-bound') === '1') return;
        tree.setAttribute('data-sa-bound', '1');

        var searchEl = tree.querySelector('.cabinet-sa-tree-search');
        if (searchEl) {
            searchEl.addEventListener('input', function () { applyTree(tree); });
        }

        tree.querySelectorAll('.cabinet-sa-tree-preset').forEach(function (btn) {
            btn.addEventListener('click', function () {
                setPreset(tree, btn.getAttribute('data-preset') || 'all', true);
            });
        });

        // Перед уходом в отчёт — ещё раз зафиксировать активный пресет.
        tree.querySelectorAll('a.cabinet-sa-tree__item').forEach(function (link) {
            link.addEventListener('click', function () {
                writeStoredPreset(tree, currentPreset(tree) || 'all');
            });
        });

        restorePreset(tree);
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-sa-tree]').forEach(bindTree);

        document.querySelectorAll('[data-sa-bucket-preset]').forEach(function (bucket) {
            bucket.style.cursor = 'pointer';
            bucket.setAttribute('role', 'button');
            bucket.setAttribute('tabindex', '0');
            var go = function () {
                var preset = bucket.getAttribute('data-sa-bucket-preset');
                var pane = bucket.closest('.tab-pane') || document;
                var tree = pane.querySelector('[data-sa-tree]');
                if (tree && preset) setPreset(tree, preset, true);
            };
            bucket.addEventListener('click', go);
            bucket.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    go();
                }
            });
        });
    });
})();
</script>
