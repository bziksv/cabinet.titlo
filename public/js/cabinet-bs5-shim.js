/**
 * Совместимость Blade/страниц на BS4-атрибутах с Bootstrap 5 + AdminLTE 4.
 * Дублирует атрибуты на лету для динамически вставленного HTML (DataTables, AJAX).
 */
(function () {
    'use strict';

    var BS_TOGGLE_WITH_TARGET = {
        modal: true,
        dropdown: true,
        tab: true,
        collapse: true,
        tooltip: true,
        popover: true
    };

    var CARD_WIDGET_MAP = {
        maximize: 'card-maximize',
        collapse: 'card-collapse',
        remove: 'card-remove',
        'card-refresh': 'card-refresh'
    };

    function shouldMigrateTarget(toggle, target) {
        if (!target) {
            return false;
        }
        if (toggle === 'modal' || toggle === 'dropdown' || toggle === 'tab') {
            return target.charAt(0) === '#' || (toggle === 'modal' && target.charAt(0) === '.');
        }
        if (toggle === 'collapse') {
            return target.charAt(0) === '#';
        }
        return BS_TOGGLE_WITH_TARGET[toggle];
    }

    function migrateDataToggle(root) {
        (root || document).querySelectorAll('[data-toggle]:not([data-bs-toggle])').forEach(function (el) {
            var toggle = el.getAttribute('data-toggle');
            if (toggle === 'datetimepicker') {
                return;
            }
            el.setAttribute('data-bs-toggle', toggle);
            if (el.hasAttribute('data-target') && !el.hasAttribute('data-bs-target') && shouldMigrateTarget(toggle, el.getAttribute('data-target'))) {
                el.setAttribute('data-bs-target', el.getAttribute('data-target'));
            }
        });

        (root || document).querySelectorAll('[data-bs-toggle]:not([data-toggle])').forEach(function (el) {
            var toggle = el.getAttribute('data-bs-toggle');
            if (toggle === 'datetimepicker') {
                return;
            }
            if (el.hasAttribute('data-target') && !el.hasAttribute('data-bs-target') && shouldMigrateTarget(toggle, el.getAttribute('data-target'))) {
                el.setAttribute('data-bs-target', el.getAttribute('data-target'));
            }
            if (toggle === 'collapse' && !el.hasAttribute('data-bs-target')) {
                var href = el.getAttribute('href');
                if (href && href.charAt(0) === '#') {
                    el.setAttribute('data-bs-target', href);
                }
            }
        });

        (root || document).querySelectorAll('[data-dismiss]:not([data-bs-dismiss])').forEach(function (el) {
            el.setAttribute('data-bs-dismiss', el.getAttribute('data-dismiss'));
        });
    }

    function migrateCardWidgets(root) {
        (root || document).querySelectorAll('[data-card-widget]').forEach(function (el) {
            var action = el.getAttribute('data-card-widget');
            if (CARD_WIDGET_MAP[action]) {
                el.setAttribute('data-lte-toggle', CARD_WIDGET_MAP[action]);
                el.removeAttribute('data-card-widget');
            }
        });
    }

    function migrateDataWidget(root) {
        (root || document).querySelectorAll('[data-widget="pushmenu"]').forEach(function (el) {
            el.setAttribute('data-lte-toggle', 'sidebar');
            el.removeAttribute('data-widget');
        });
    }

    function migrateTooltipAttrs(root) {
        (root || document).querySelectorAll('[data-bs-toggle="tooltip"][data-placement]:not([data-bs-placement])').forEach(function (el) {
            el.setAttribute('data-bs-placement', el.getAttribute('data-placement'));
        });
        (root || document).querySelectorAll('[data-toggle="tooltip"][data-placement]:not([data-bs-placement])').forEach(function (el) {
            el.setAttribute('data-bs-placement', el.getAttribute('data-placement'));
        });
    }

    function initBootstrapTooltips(root) {
        if (typeof bootstrap === 'undefined' || !bootstrap.Tooltip) {
            return;
        }
        (root || document).querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
            if (!bootstrap.Tooltip.getInstance(el)) {
                new bootstrap.Tooltip(el);
            }
        });
    }

    function migrateTempusdominus(root) {
        (root || document).querySelectorAll('[data-bs-toggle="datetimepicker"]').forEach(function (el) {
            el.setAttribute('data-toggle', 'datetimepicker');
            var target = el.getAttribute('data-bs-target');
            if (target && !el.hasAttribute('data-target')) {
                el.setAttribute('data-target', target);
            }
        });
    }

    function migrateDataTablesLengthSelect(root) {
        (root || document).querySelectorAll('.dataTables_length select').forEach(function (el) {
            el.classList.remove('custom-select', 'custom-select-sm');
            el.classList.add('form-select', 'form-select-sm');
        });
    }

    function run(root) {
        migrateDataToggle(root);
        migrateTempusdominus(root);
        migrateCardWidgets(root);
        migrateDataWidget(root);
        migrateTooltipAttrs(root);
        migrateDataTablesLengthSelect(root);
        initBootstrapTooltips(root);
    }

    function observeDynamic() {
        if (!window.MutationObserver || !document.body) {
            return;
        }
        var scheduled = false;
        var observer = new MutationObserver(function (mutations) {
            var touched = false;
            mutations.forEach(function (m) {
                if (m.addedNodes && m.addedNodes.length) {
                    touched = true;
                }
            });
            if (!touched || scheduled) {
                return;
            }
            scheduled = true;
            requestAnimationFrame(function () {
                scheduled = false;
                mutations.forEach(function (m) {
                    m.addedNodes.forEach(function (node) {
                        if (node.nodeType === 1) {
                            run(node);
                        }
                    });
                });
            });
        });
        observer.observe(document.body, {childList: true, subtree: true});
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            run(document);
            observeDynamic();
        });
    } else {
        run(document);
        observeDynamic();
    }

    window.cabinetBs5Shim = {
        run: run,
        migrateDataToggle: migrateDataToggle,
        migrateCardWidgets: migrateCardWidgets,
        initBootstrapTooltips: initBootstrapTooltips
    };

    if (window.jQuery && typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
        window.jQuery.fn.tooltip = function (action) {
            return this.each(function () {
                var instance = bootstrap.Tooltip.getInstance(this);
                if (action === 'dispose') {
                    if (instance) {
                        instance.dispose();
                    }
                    return;
                }
                if (!instance) {
                    new bootstrap.Tooltip(this);
                }
            });
        };
    }
})();
