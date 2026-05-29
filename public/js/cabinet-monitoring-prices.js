(function ($, cfg) {
    'use strict';

    if (!$ || !cfg) {
        return;
    }

    var priceFields = cfg.priceFields || [];
    var table = null;
    var storageKey = 'cabinet_mon_prices_region_' + cfg.projectId;

    function formatPrice(value) {
        if (value === null || value === undefined || value === '') {
            return '—';
        }
        return value;
    }

    function priceCell(field) {
        return function (data, type, row) {
            if (type !== 'display') {
                return data;
            }
            var text = formatPrice(data);
            if (!cfg.canEditPrice) {
                return text;
            }
            return '<button type="button" class="cabinet-mon-prices-cell-btn" data-field="' + field + '" data-id="' + row.DT_RowId + '" title="' + field + '">' +
                '<span class="cabinet-mon-prices-cell-btn__value">' + text + '</span>' +
                '<i class="bi bi-pencil-square cabinet-mon-prices-cell-btn__icon" aria-hidden="true"></i>' +
                '</button>';
        };
    }

    function selectedIds() {
        var ids = [];
        $('#prices tbody .cabinet-mon-prices-row-check:checked').each(function () {
            ids.push($(this).data('id'));
        });
        return ids;
    }

    function updateSelectionUi() {
        var count = selectedIds().length;
        $('#prices-selected-count').text(count);
        $('#prices-bulk-edit').prop('disabled', count === 0);
    }

    function showLoader(show) {
        $('#cabinetMonPricesLoader').toggleClass('d-none', !show);
    }

    function savePrices(payload) {
        return $.ajax({
            url: cfg.routes.action,
            type: 'POST',
            dataType: 'json',
            data: $.extend({ action: 'edit', region: $('#select-region').val(), _token: cfg.csrf }, payload),
        });
    }

    function startInlineEdit($btn) {
        if ($btn.closest('td').find('.cabinet-mon-prices-cell-input').length) {
            return;
        }

        var field = $btn.data('field');
        var id = $btn.data('id');
        var current = $btn.find('.cabinet-mon-prices-cell-btn__value').text();
        var initial = current === '—' ? '' : current;

        var $input = $('<input type="number" class="form-control form-control-sm cabinet-mon-prices-cell-input" min="0" step="0.01">')
            .val(initial);

        $btn.replaceWith($input);
        $input.trigger('focus').trigger('select');

        function finish(save) {
            var nextValue = $.trim($input.val());
            $input.off('keydown blur');

            if (!save) {
                if (table) {
                    table.ajax.reload(null, false);
                }
                return;
            }

            if (nextValue === initial) {
                if (table) {
                    table.ajax.reload(null, false);
                }
                return;
            }

            if (nextValue !== '' && !$.isNumeric(nextValue)) {
                if (table) {
                    table.ajax.reload(null, false);
                }
                return;
            }

            if (nextValue === '') {
                if (table) {
                    table.ajax.reload(null, false);
                }
                return;
            }

            var rowData = {};
            rowData[id] = {};
            rowData[id][field] = nextValue;

            savePrices({ data: rowData })
                .done(function () {
                    toastr.success(cfg.i18n.saved);
                    if (table) {
                        table.ajax.reload(null, false);
                    }
                })
                .fail(function () {
                    toastr.error(cfg.i18n.saveError);
                    if (table) {
                        table.ajax.reload(null, false);
                    }
                });
        }

        $input.on('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                finish(true);
            } else if (e.key === 'Escape') {
                e.preventDefault();
                finish(false);
            }
        }).on('blur', function () {
            finish(true);
        });
    }

    function initTable() {
        var columns = [];

        if (cfg.canEditPrice) {
            columns.push({
                title: '<input type="checkbox" class="form-check-input" id="prices-check-all" aria-label="' + cfg.i18n.selectRows + '">',
                data: null,
                orderable: false,
                searchable: false,
                className: 'cabinet-mon-prices-check-col',
                render: function (data, type, row) {
                    return '<input type="checkbox" class="form-check-input cabinet-mon-prices-row-check" data-id="' + row.DT_RowId + '">';
                },
            });
        }

        columns.push({
            title: cfg.i18n.query,
            data: 'query',
            className: 'cabinet-mon-prices-query-col',
        });
        priceFields.forEach(function (field) {
            var top = field.replace('top', '');
            columns.push({
                title: 'TOP\u00a0' + top,
                data: field,
                className: 'cabinet-mon-prices-value-col',
                render: priceCell(field),
            });
        });

        table = $('#prices').DataTable({
            dom: 'lfrt<"cabinet-mon-prices-dt-footer"ip>',
            autoWidth: false,
            ordering: false,
            paging: true,
            lengthMenu: [10, 30, 50, 100, 500, 1000],
            pageLength: 100,
            pagingType: 'simple_numbers',
            language: {
                lengthMenu: '_MENU_',
                search: '_INPUT_',
                searchPlaceholder: cfg.i18n.search,
                paginate: {
                    first: '«',
                    last: '»',
                    next: '»',
                    previous: '«',
                },
                processing: '',
                emptyTable: '—',
            },
            processing: true,
            serverSide: true,
            ajax: {
                url: cfg.routes.list,
                type: 'GET',
                data: function (data) {
                    data.region = $('#select-region').val();
                },
                beforeSend: function () {
                    showLoader(true);
                },
                complete: function () {
                    showLoader(false);
                    updateSelectionUi();
                },
            },
            columns: columns,
            initComplete: function () {
                var api = this.api();
                var $wrapper = $(api.table().container());

                $wrapper.find('.dataTables_length').appendTo('#prices-dt-length');
                $wrapper.find('.dataTables_filter').appendTo('#prices-dt-filter');

                if (window.cabinetMonitoringSearch) {
                    window.cabinetMonitoringSearch.wireGlobalDataTableSearch(api);
                }
            },
        });

        $('#prices').on('draw.dt', function () {
            $('#prices-check-all').prop('checked', false);
            updateSelectionUi();
        });
    }

    $(document).ready(function () {
        toastr.options = { preventDuplicates: true, timeOut: 5000 };

        var savedRegion = localStorage.getItem(storageKey);
        if (savedRegion && $('#select-region option[value="' + savedRegion + '"]').length) {
            $('#select-region').val(savedRegion);
        }

        initTable();

        $('#select-region').on('change', function () {
            localStorage.setItem(storageKey, $(this).val());
            if (table) {
                table.ajax.reload();
            }
        });

        $('#prices').on('click', '.cabinet-mon-prices-cell-btn', function () {
            startInlineEdit($(this));
        });

        $('#prices').on('change', '.cabinet-mon-prices-row-check', updateSelectionUi);

        $('#prices').on('change', '#prices-check-all', function () {
            var checked = $(this).is(':checked');
            $('#prices tbody .cabinet-mon-prices-row-check').prop('checked', checked);
            updateSelectionUi();
        });

        $('#prices-select-all').on('click', function () {
            $('#prices tbody .cabinet-mon-prices-row-check').prop('checked', true);
            $('#prices-check-all').prop('checked', true);
            updateSelectionUi();
        });

        $('#prices-select-none').on('click', function () {
            $('#prices tbody .cabinet-mon-prices-row-check').prop('checked', false);
            $('#prices-check-all').prop('checked', false);
            updateSelectionUi();
        });

        $('#prices-bulk-save').on('click', function () {
            var ids = selectedIds();
            if (!ids.length) {
                toastr.warning(cfg.i18n.selectRows);
                return;
            }

            var patch = {};
            $('.prices-bulk-field').each(function () {
                var val = $.trim($(this).val());
                if (val !== '' && $.isNumeric(val)) {
                    patch[$(this).data('field')] = val;
                }
            });

            if ($.isEmptyObject(patch)) {
                toastr.warning(cfg.i18n.bulkEmpty);
                return;
            }

            var data = {};
            ids.forEach(function (id) {
                data[id] = $.extend({}, patch);
            });

            $('#prices-bulk-save').prop('disabled', true);
            savePrices({ data: data })
                .done(function () {
                    toastr.success(cfg.i18n.saved);
                    $('.prices-bulk-field').val('');
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('pricesBulkEditModal')).hide();
                    if (table) {
                        table.ajax.reload(null, false);
                    }
                })
                .fail(function () {
                    toastr.error(cfg.i18n.saveError);
                })
                .always(function () {
                    $('#prices-bulk-save').prop('disabled', false);
                });
        });

        if (cfg.canEditBudget) {
            $('#save-budget').on('click', function () {
                var $btn = $(this);
                $btn.prop('disabled', true);
                $.ajax({
                    url: cfg.routes.budget,
                    type: 'POST',
                    data: {
                        _token: cfg.csrf,
                        budget: $('#project-budget').val(),
                    },
                })
                    .done(function () {
                        toastr.success(cfg.i18n.saved);
                    })
                    .fail(function () {
                        toastr.error(cfg.i18n.saveError);
                    })
                    .always(function () {
                        $btn.prop('disabled', false);
                    });
            });
        }
    });
}(window.jQuery, window.cabinetMonPricesConfig));
