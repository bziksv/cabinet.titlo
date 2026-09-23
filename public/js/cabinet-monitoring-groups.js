(function ($, cfg) {
    'use strict';

    if (!$ || !cfg) {
        return;
    }

    var table = null;
    var lastOptions = { groups_option: [], users_option: [] };
    var formModalEl = null;
    var deleteModalEl = null;

    function toastError(msg) {
        if (typeof toastr !== 'undefined') {
            toastr.error(msg || cfg.i18n.loadError || 'Error');
        }
    }

    function toastOk(msg) {
        if (typeof toastr !== 'undefined') {
            toastr.success(msg || cfg.i18n.saved || 'OK');
        }
    }

    function toastWarn(msg) {
        if (typeof toastr !== 'undefined') {
            toastr.warning(msg);
        }
    }

    function showModal(el) {
        if (!el) {
            return;
        }
        if (window.bootstrap && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(el).show();
            return;
        }
        $(el).modal('show');
    }

    function hideModal(el) {
        if (!el) {
            return;
        }
        if (window.bootstrap && bootstrap.Modal) {
            var inst = bootstrap.Modal.getInstance(el);
            if (inst) {
                inst.hide();
            }
            return;
        }
        $(el).modal('hide');
    }

    function renderUsers(users) {
        if (!users || !users.length) {
            return '<span class="text-secondary">—</span>';
        }

        var list = $('<ul />', { class: 'cabinet-mon-groups-users list-inline mb-0' });
        $.each(users, function (i, val) {
            var title = $.trim((val.name || '') + ' ' + (val.last_name || ''));
            list.append(
                $('<li />', { class: 'list-inline-item', title: title }).append(
                    $('<img />', {
                        class: 'cabinet-mon-groups-users__avatar',
                        src: val.image,
                        alt: '',
                        loading: 'lazy',
                    })
                )
            );
        });

        return list[0].outerHTML;
    }

    function updateStats(api) {
        if (!api) {
            return;
        }
        var data = api.rows().data().toArray();
        var count = data.length;
        if (count === 0 && parseInt($('#groups-stats-groups').text(), 10) > 0) {
            return;
        }
        var queries = data.reduce(function (sum, row) {
            return sum + (parseInt(row.queries, 10) || 0);
        }, 0);

        $('#groups-stats-groups').text(count);
        $('#groups-stats-queries').text(queries);
    }

    function syncGroupChecks(api) {
        if (!api) {
            return;
        }
        api.rows({ page: 'current' }).every(function () {
            var node = this.node();
            if (!node) {
                return;
            }
            var selected = false;
            try {
                selected = typeof this.selected === 'function' ? !!this.selected() : $(node).hasClass('selected');
            } catch (err) {
                selected = $(node).hasClass('selected');
            }
            $(node).find('.cabinet-mon-groups-check').prop('checked', selected);
        });
    }

    function selectedRows() {
        if (!table) {
            return [];
        }
        return table.rows({ selected: true }).data().toArray();
    }

    function applyTopColors($content) {
        $content.find('.top').each(function () {
            var str = $(this).text();
            if (str.indexOf('+') > 0) {
                $(this).addClass('cabinet-mon-groups-grow grow-color');
            }
            if (str.indexOf('-') > 0) {
                $(this).addClass('cabinet-mon-groups-shrink shrink-color');
            }
        });
    }

    function toggleChildRow($control, api) {
        if (!$control || !api) {
            return;
        }

        var $icon = $control.find('i');
        var $tr = $control.closest('tr');
        if (!$tr.length || $tr.hasClass('child')) {
            return;
        }
        var row = api.row($tr);
        if (!row || !row.node()) {
            return;
        }

        if (row.child.isShown()) {
            row.child.hide();
            $tr.removeClass('shown');
            $icon.removeClass('bi-dash-circle').addClass('bi-plus-circle');
            return;
        }

        var data = row.data() || {};
        var groupId = data.id;
        var projectId = data.monitoring_project_id || cfg.projectId;
        if (!groupId || !projectId) {
            toastError(cfg.i18n.loadError);
            return;
        }

        var url = '/monitoring/' + projectId + '/child-rows/get/' + groupId;
        var req = window.axios
            ? window.axios.get(url)
            : $.ajax({ url: url, method: 'GET', dataType: 'html' }).then(function (html) {
                  return { data: html };
              });

        Promise.resolve(req)
            .then(function (response) {
                var html = response && response.data != null ? response.data : response;
                var $content = $('<div class="cabinet-mon-groups-child" />').append($(html));
                applyTopColors($content);
                row.child($content).show();
                $tr.addClass('shown');
                $icon.removeClass('bi-plus-circle').addClass('bi-dash-circle');

                $content.find('.tooltip-child-table').tooltip({
                    animation: false,
                    trigger: 'hover',
                });

                if (window.cabinetMonitoringChildCharts) {
                    window.cabinetMonitoringChildCharts.wire($content, projectId, {
                        chartsUrl: cfg.chartsUrl || '/monitoring/charts',
                        i18n: {
                            childChartShow: cfg.i18n.childChartShow,
                            childChartHide: cfg.i18n.childChartHide,
                            loadError: cfg.i18n.loadError,
                        },
                    });
                }
            })
            .catch(function () {
                toastError(cfg.i18n.loadError);
            });
    }

    function fillMoveOptions(excludeIds) {
        var $sel = $('#groups-form-move');
        $sel.empty();
        var exclude = {};
        (excludeIds || []).forEach(function (id) {
            exclude[String(id)] = true;
        });
        var opts = lastOptions.groups_option || [];
        var hasNone = opts.some(function (opt) {
            return opt && String(opt.value) === '0';
        });
        if (!hasNone) {
            $sel.append(
                $('<option />', {
                    value: '0',
                    text: cfg.i18n.moveNone || '—',
                })
            );
        }
        opts.forEach(function (opt) {
            if (!opt || exclude[String(opt.value)]) {
                return;
            }
            $sel.append($('<option />', { value: opt.value, text: opt.label }));
        });
    }

    function fillUsersOptions(selectedIds) {
        var $wrap = $('#groups-form-users');
        $wrap.empty();
        var selected = {};
        (selectedIds || []).forEach(function (id) {
            selected[String(id)] = true;
        });
        var opts = lastOptions.users_option || [];
        if (!opts.length) {
            $wrap.append($('<div class="text-secondary small" />').text('—'));
            return;
        }
        opts.forEach(function (opt) {
            if (!opt) {
                return;
            }
            var id = 'groups-user-' + opt.value;
            var $item = $('<div class="form-check" />');
            $item.append(
                $('<input />', {
                    type: 'checkbox',
                    class: 'form-check-input groups-form-user-check',
                    id: id,
                    value: opt.value,
                    checked: !!selected[String(opt.value)],
                })
            );
            $item.append(
                $('<label />', {
                    class: 'form-check-label',
                    for: id,
                    text: opt.label,
                })
            );
            $wrap.append($item);
        });
    }

    function openCreateModal() {
        $('#groups-form-mode').val('create');
        $('#groups-form-ids').val('');
        $('#groups-form-name').val('').removeClass('is-invalid');
        $('#groups-form-name-error').text('');
        $('#groups-form-move-wrap').addClass('d-none');
        $('#groups-form-users-wrap').addClass('d-none');
        $('#groupsFormModalTitle').text(cfg.i18n.createTitle);
        $('#groups-form-submit').text(cfg.i18n.createSubmit);
        showModal(formModalEl);
        setTimeout(function () {
            $('#groups-form-name').trigger('focus');
        }, 200);
    }

    function openEditModal(rows) {
        if (!rows || !rows.length) {
            toastWarn(cfg.i18n.selectRowsFirst || 'Select rows');
            return;
        }
        var ids = rows.map(function (r) {
            return r.id;
        });
        var first = rows[0] || {};
        var sameName = rows.every(function (r) {
            return String(r.name || '') === String(first.name || '');
        });

        $('#groups-form-mode').val('edit');
        $('#groups-form-ids').val(ids.join(','));
        $('#groups-form-name')
            .val(sameName ? first.name || '' : '')
            .removeClass('is-invalid');
        $('#groups-form-name-error').text('');
        fillMoveOptions(ids);
        fillUsersOptions(
            (first.users || []).map(function (u) {
                return u.id;
            })
        );
        $('#groups-form-move-wrap').removeClass('d-none');
        $('#groups-form-users-wrap').removeClass('d-none');
        $('#groupsFormModalTitle').text(cfg.i18n.editTitle);
        $('#groups-form-submit').text(cfg.i18n.editSubmit);
        showModal(formModalEl);
        setTimeout(function () {
            $('#groups-form-name').trigger('focus');
        }, 200);
    }

    function openDeleteModal(row) {
        if (!row || !row.id) {
            return;
        }
        $('#groups-delete-id').val(row.id);
        $('#groups-delete-name').val(row.name || 'group');
        $('#groups-delete-message').text(cfg.i18n.deleteConfirmOne);
        showModal(deleteModalEl);
    }

    function postAction(payload) {
        return $.ajax({
            url: cfg.routes.action,
            type: 'POST',
            dataType: 'json',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': cfg.csrf || '',
            },
            data: $.extend({ _token: cfg.csrf }, payload),
        });
    }

    function reloadTable() {
        if (table) {
            table.ajax.reload(null, false);
        }
    }

    function submitForm() {
        var mode = $('#groups-form-mode').val();
        var name = $.trim($('#groups-form-name').val());
        $('#groups-form-name').removeClass('is-invalid');
        $('#groups-form-name-error').text('');

        if (!name) {
            $('#groups-form-name').addClass('is-invalid');
            $('#groups-form-name-error').text(cfg.i18n.groupLabel || 'Name');
            return;
        }

        var data = {};
        if (mode === 'create') {
            data[0] = { name: name };
        } else {
            var ids = String($('#groups-form-ids').val() || '')
                .split(',')
                .filter(Boolean);
            if (!ids.length) {
                toastWarn(cfg.i18n.selectRowsFirst);
                return;
            }
            var moveTo = parseInt($('#groups-form-move').val(), 10) || 0;
            var users = [];
            $('#groups-form-users .groups-form-user-check:checked').each(function () {
                users.push($(this).val());
            });
            ids.forEach(function (id) {
                data[id] = {
                    id: id,
                    name: name,
                    groups_option: moveTo,
                    users_option: users,
                };
            });
        }

        var $btn = $('#groups-form-submit').prop('disabled', true);
        postAction({ action: mode, data: data })
            .done(function (resp) {
                if (resp && resp.fieldErrors && resp.fieldErrors.length) {
                    var err = resp.fieldErrors[0];
                    $('#groups-form-name').addClass('is-invalid');
                    $('#groups-form-name-error').text(err.status || cfg.i18n.loadError);
                    return;
                }
                if (resp && resp.error) {
                    toastError(resp.error);
                    return;
                }
                hideModal(formModalEl);
                toastOk(cfg.i18n.saved);
                reloadTable();
            })
            .fail(function () {
                toastError(cfg.i18n.loadError);
            })
            .always(function () {
                $btn.prop('disabled', false);
            });
    }

    function submitDelete() {
        var id = $('#groups-delete-id').val();
        var name = $('#groups-delete-name').val() || 'group';
        if (!id) {
            return;
        }
        var data = {};
        data[id] = { id: id, name: name };
        var $btn = $('#groups-delete-submit').prop('disabled', true);
        postAction({ action: 'remove', data: data })
            .done(function (resp) {
                if (resp && resp.error) {
                    toastError(resp.error);
                    return;
                }
                hideModal(deleteModalEl);
                toastOk(cfg.i18n.saved);
                reloadTable();
            })
            .fail(function () {
                toastError(cfg.i18n.loadError);
            })
            .always(function () {
                $btn.prop('disabled', false);
            });
    }

    function relocateFilter(api) {
        if (!api) {
            return;
        }
        var $filter = $('#groups-dt-filter');
        if ($filter.length) {
            $(api.table().container()).find('.dataTables_filter').appendTo($filter);
        }
        // прячем штатные dt-buttons — тулбар свой в blade
        $(api.table().container()).find('.dt-buttons').addClass('d-none');
    }

    function initTable() {
        var canSelect = !!(cfg.canEdit || cfg.canDelete);
        var nameColIndex = canSelect ? 3 : 2;
        var columns = [];

        if (canSelect) {
            columns.push({
                orderable: false,
                searchable: false,
                data: null,
                className: 'cabinet-mon-groups-col-check',
                title: '',
                defaultContent:
                    '<input type="checkbox" class="cabinet-mon-groups-check" tabindex="-1" aria-label="' +
                    (cfg.i18n.selectAll || 'Select') +
                    '">',
            });
        }

        columns.push(
            {
                orderable: false,
                searchable: false,
                data: null,
                className: 'cabinet-mon-groups-col-expand',
                title: '',
                defaultContent:
                    '<button type="button" class="btn btn-sm btn-link text-secondary p-0 cabinet-mon-groups-expand" aria-label="' +
                    cfg.i18n.expand +
                    '"><i class="bi bi-plus-circle" aria-hidden="true"></i></button>',
            },
            {
                title: cfg.i18n.colId,
                data: 'id',
                name: 'id',
                className: 'cabinet-mon-groups-col-id',
            },
            {
                title: cfg.i18n.colGroup,
                data: 'name',
                name: 'name',
                className: 'cabinet-mon-groups-col-name',
                render: function (data) {
                    return (
                        '<span class="cabinet-mon-groups-name">' +
                        $('<div>').text(data || '').html() +
                        '</span>'
                    );
                },
            },
            {
                title: cfg.i18n.colQueries,
                data: 'queries',
                name: 'queries',
                className: 'cabinet-mon-groups-col-queries',
            },
            {
                title: cfg.i18n.colCreated,
                data: 'created',
                name: 'created_at',
                className: 'cabinet-mon-groups-col-created text-secondary',
            },
            {
                title: cfg.i18n.colUsers,
                orderable: false,
                searchable: false,
                data: function (row) {
                    return renderUsers(row.users);
                },
                className: 'cabinet-mon-groups-col-users',
            },
            {
                title: cfg.i18n.colActions,
                orderable: false,
                searchable: false,
                data: null,
                className: 'cabinet-mon-groups-col-actions text-end',
                render: function (data, type, row) {
                    var openUrl =
                        '/monitoring/' + (row.monitoring_project_id || cfg.projectId) + '?group=' + row.id;
                    var html =
                        '<div class="cabinet-mon-groups-row-actions" role="group">' +
                        '<a href="' +
                        openUrl +
                        '" class="btn btn-sm btn-outline-secondary cabinet-mon-groups-row-actions__btn" aria-label="' +
                        cfg.i18n.openGroup +
                        '"><i class="bi bi-folder2-open" aria-hidden="true"></i></a>';

                    if (cfg.canEdit) {
                        html +=
                            '<button type="button" class="btn btn-sm btn-outline-secondary cabinet-mon-groups-row-actions__btn editor-edit" aria-label="' +
                            cfg.i18n.editGroup +
                            '"><i class="bi bi-pencil" aria-hidden="true"></i></button>';
                    }
                    if (cfg.canDelete) {
                        html +=
                            '<button type="button" class="btn btn-sm btn-outline-danger cabinet-mon-groups-row-actions__btn editor-delete" aria-label="' +
                            cfg.i18n.deleteGroup +
                            '"><i class="bi bi-trash" aria-hidden="true"></i></button>';
                    }
                    html += '</div>';
                    return html;
                },
            }
        );

        var nonOrderable = canSelect ? [0, 1, 6, 7] : [0, 5, 6];

        table = $('#groups').DataTable({
            dom: 'Brt',
            rowId: 'id',
            autoWidth: false,
            fixedHeader: false,
            paging: false,
            ordering: true,
            order: [[nameColIndex, 'asc']],
            language: {
                search: '_INPUT_',
                searchPlaceholder: cfg.i18n.search,
                processing: '',
                emptyTable: cfg.i18n.emptyTable,
                zeroRecords: cfg.i18n.zeroRecords,
            },
            processing: false,
            serverSide: false,
            ajax: {
                url: cfg.routes.list,
                type: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': cfg.csrf || '',
                },
                data: function (d) {
                    d._token = cfg.csrf;
                },
                dataSrc: function (json) {
                    function asOpts(raw) {
                        if (!raw) {
                            return [];
                        }
                        if (Array.isArray(raw)) {
                            return raw;
                        }
                        if (typeof raw === 'object') {
                            return Object.keys(raw).map(function (k) {
                                return raw[k];
                            });
                        }
                        return [];
                    }
                    if (json && json.options) {
                        lastOptions.groups_option = asOpts(json.options.groups_option);
                        lastOptions.users_option = asOpts(json.options.users_option);
                    }
                    var rows = json && Array.isArray(json.data) ? json.data : [];
                    var queries = rows.reduce(function (sum, row) {
                        return sum + (parseInt(row.queries, 10) || 0);
                    }, 0);
                    $('#groups-stats-groups').text(rows.length);
                    $('#groups-stats-queries').text(queries);
                    return rows;
                },
            },
            columnDefs: [{ orderable: false, targets: nonOrderable }],
            columns: columns,
            select: canSelect
                ? {
                      style: 'multi',
                      selector: 'td.cabinet-mon-groups-col-check',
                  }
                : false,
            buttons: [],
            initComplete: function () {
                var api = this.api();
                relocateFilter(api);
                if (window.cabinetMonitoringSearch) {
                    window.cabinetMonitoringSearch.wireGlobalDataTableSearch(api);
                }
                api.on('select.dt deselect.dt', function () {
                    syncGroupChecks(api);
                });
                syncGroupChecks(api);
                updateStats(api);
            },
            drawCallback: function () {
                var api = this.api();
                relocateFilter(api);
                syncGroupChecks(api);
                updateStats(api);
            },
        });
    }

    $(document).ready(function () {
        if (typeof toastr !== 'undefined') {
            toastr.options = { preventDuplicates: true, timeOut: 5000 };
        }

        formModalEl = document.getElementById('groupsFormModal');
        deleteModalEl = document.getElementById('groupsDeleteModal');

        initTable();

        $('#groups-select-all').on('click', function (e) {
            e.preventDefault();
            if (table) {
                table.rows({ page: 'current', search: 'applied' }).select();
            }
        });
        $('#groups-select-none').on('click', function (e) {
            e.preventDefault();
            if (table) {
                table.rows({ selected: true }).deselect();
            }
        });
        $('#groups-create-btn').on('click', function (e) {
            e.preventDefault();
            openCreateModal();
        });
        $('#groups-edit-selected-btn').on('click', function (e) {
            e.preventDefault();
            openEditModal(selectedRows());
        });
        $('#groups-form-submit').on('click', function (e) {
            e.preventDefault();
            submitForm();
        });
        $('#groups-delete-submit').on('click', function (e) {
            e.preventDefault();
            submitDelete();
        });

        $(document).on('click', '#groups .cabinet-mon-groups-expand', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (!table) {
                return;
            }
            toggleChildRow($(this), table);
        });

        $(document).on('click', '#groups .cabinet-mon-groups-check', function (e) {
            e.stopPropagation();
        });
        $(document).on('change', '#groups .cabinet-mon-groups-check', function () {
            if (!table) {
                return;
            }
            var row = table.row($(this).closest('tr'));
            if (!row.node()) {
                return;
            }
            if (this.checked) {
                row.select();
            } else {
                row.deselect();
            }
        });

        $(document).on('click', '#groups .editor-edit', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (!table) {
                return;
            }
            var row = table.row($(this).closest('tr'));
            if (!row.node()) {
                return;
            }
            openEditModal([row.data()]);
        });

        $(document).on('click', '#groups .editor-delete', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (!table) {
                return;
            }
            var row = table.row($(this).closest('tr'));
            if (!row.node()) {
                return;
            }
            openDeleteModal(row.data());
        });
    });
}(window.jQuery, window.cabinetMonGroupsConfig));
