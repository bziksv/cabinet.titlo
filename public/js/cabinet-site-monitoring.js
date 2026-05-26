/**
 * Мониторинг сайтов — таблица проектов, inline-редактирование, проверка.
 */
(function (window, document) {
    'use strict';

    function initIndex(root) {
        var cfgEl = root.querySelector('[data-sm-config]');
        if (!cfgEl) {
            return;
        }
        var cfg;
        try {
            cfg = JSON.parse(cfgEl.textContent || '{}');
        } catch (e) {
            return;
        }

        var tableEl = root.querySelector('#cabinet-sm-table');
        if (!tableEl || typeof window.jQuery === 'undefined' || !window.jQuery.fn.dataTable) {
            return;
        }

        var $ = window.jQuery;
        var table = $(tableEl).DataTable({
            language: cfg.datatableLang || {},
            drawCallback: function () {
                $(tableEl).wrap("<div class='table-responsive' id='cabinet-sm-table-wrap'></div>");
            },
        });

        if (typeof window.search === 'function') {
            window.search(table);
        }

        function showToast(selector) {
            var el = root.querySelector(selector);
            if (!el) {
                return;
            }
            el.classList.remove('d-none');
            setTimeout(function () {
                el.classList.add('d-none');
            }, 4000);
        }

        $('input.send-notification-switch', root).on('click', function () {
            var row = $(this).closest('tr');
            $.ajax({
                type: 'POST',
                dataType: 'json',
                url: cfg.editUrl,
                data: {
                    id: row.attr('id'),
                    name: 'send_notification',
                    option: $(this).is(':checked') ? 1 : 0,
                    _token: cfg.csrf,
                },
                success: function () {
                    showToast('[data-sm-toast="success"]');
                },
                error: function () {
                    showToast('[data-sm-toast="error"]');
                },
            });
        });

        var oldValue = '';
        $('.monitoring', root).on('focus', function () {
            oldValue = $(this).val();
        }).on('blur', function () {
            if (oldValue === $(this).val() && $(this).attr('name') !== 'phrase') {
                return;
            }
            var row = $(this).closest('tr');
            $.ajax({
                type: 'POST',
                dataType: 'json',
                url: cfg.editUrl,
                data: {
                    id: row.attr('id'),
                    name: $(this).attr('name'),
                    option: $(this).val(),
                    _token: cfg.csrf,
                },
                success: function () {
                    showToast('[data-sm-toast="success"]');
                },
                error: function () {
                    showToast('[data-sm-toast="error"]');
                },
            });
        });

        var checkedInput = root.querySelector('.checked-projects');
        $('.checbox-for-remove-project', root).on('change', function () {
            var box = $(this).find('input[type=checkbox]');
            var selectedId = box.attr('id').substr(8);
            var text = checkedInput ? checkedInput.value : '';
            if (box.is(':checked')) {
                $(this).closest('tr').attr('data-select', 'true');
                if (checkedInput) {
                    checkedInput.value = text + selectedId + ', ';
                }
            } else {
                $(this).closest('tr').attr('data-select', 'false');
                if (checkedInput) {
                    checkedInput.value = text.replace(selectedId + ', ', '');
                }
            }
        });

        $('#selectedProjects', root).on('click', function (e) {
            e.preventDefault();
            $.ajax({
                type: 'POST',
                dataType: 'json',
                url: cfg.deleteBulkUrl,
                data: {
                    ids: checkedInput ? checkedInput.value : '',
                    _token: cfg.csrf,
                },
                success: function () {
                    var removed = 0;
                    $('[data-select=true]', root).each(function () {
                        removed++;
                        table.row($(this)).remove().draw(false);
                    });
                    var countEl = root.querySelector('#count-projects');
                    if (countEl) {
                        var n = Math.max(0, parseInt(countEl.textContent, 10) - removed);
                        countEl.textContent = String(n);
                        if (n === 0) {
                            window.location.href = cfg.addUrl;
                        }
                    }
                    if (checkedInput) {
                        checkedInput.value = '';
                    }
                    showToast('[data-sm-toast="delete-success"]');
                },
                error: function () {
                    showToast('[data-sm-toast="delete-error"]');
                },
            });
        });

        $(root).on('click', '.check', function () {
            var btn = $(this);
            var icon = btn.find('i');
            icon.attr('class', 'bi bi-hourglass-split');
            var row = btn.closest('tr');
            $.ajax({
                type: 'POST',
                url: cfg.checkUrl,
                data: {
                    projectId: btn.attr('data-target'),
                    _token: cfg.csrf,
                },
                success: function (response) {
                    icon.attr('class', 'bi bi-arrow-repeat');
                    var cls = response.broken ? 'cabinet-sm-status-bad' : 'cabinet-sm-status-ok';
                    var html =
                        '<span class="' + cls + '">' +
                        '<div>' + response.status + '</div>' +
                        '<div>HTTP: ' + response.code + '</div>' +
                        '<div>Uptime: ' + response.uptime + '%</div>' +
                        '</span>';
                    row.children('td').eq(6).html(html);
                },
                error: function () {
                    icon.attr('class', 'bi bi-arrow-repeat');
                },
            });
        });
    }

    function initCreate(root) {
        var phraseToggle = root.querySelector('[data-sm-phrase-toggle]');
        var phraseWrap = root.querySelector('[data-sm-phrase-wrap]');
        var phraseInput = root.querySelector('#phrase');
        var note = root.querySelector('[data-sm-phrase-note]');
        if (!phraseToggle) {
            return;
        }
        function syncPhrase() {
            var on = phraseToggle.checked;
            if (phraseWrap) {
                phraseWrap.hidden = !on;
            }
            if (phraseInput) {
                phraseInput.required = on;
            }
            if (note) {
                note.hidden = on;
            }
        }
        phraseToggle.addEventListener('change', syncPhrase);
        syncPhrase();
    }

    document.querySelectorAll('.cabinet-site-mon-page').forEach(initIndex);
    document.querySelectorAll('.cabinet-site-mon-create').forEach(initCreate);
}(window, document));
