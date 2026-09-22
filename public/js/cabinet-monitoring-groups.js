(function ($, cfg) {
    'use strict';

    if (!$ || !cfg) {
        return;
    }

    var editor = null;
    var table = null;
    var dynamicHideFields = [
        { label: cfg.i18n.moveQueriesLabel, name: 'groups_option', type: 'select' },
        { label: cfg.i18n.usersLabel, name: 'users_option', type: 'checkbox' },
    ];

    function showLoader() {
        // Оверлей отключён: раньше #groupsLoader с rgba(255,255,255,.88) залипал поверх таблицы.
        $('#groupsLoader').addClass('d-none').attr('aria-hidden', 'true');
    }

    function buildFields() {
        var fields = [
            { name: 'id', type: 'hidden' },
            {
                label: cfg.i18n.groupLabel,
                name: 'name',
                fieldInfo: cfg.i18n.groupFieldInfo,
                def: '',
            },
        ];
        return fields.concat(dynamicHideFields);
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
        var data = api.rows({ search: 'applied' }).data().toArray();
        var count = data.length;
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
            $(node).find('.cabinet-mon-groups-check').prop('checked', !!this.selected());
        });
    }

    function toggleChildRow($control, api) {
        var $icon = $control.find('i');
        var $tr = $control.closest('tr');
        var row = api.row($tr);

        if (row.child.isShown()) {
            row.child.hide();
            $tr.removeClass('shown');
            $icon.removeClass('bi-dash-circle').addClass('bi-plus-circle');
            return;
        }

        var data = row.data();
        var url = cfg.routes.childRows
            .replace('__PROJECT__', data.monitoring_project_id)
            .replace('__GROUP__', data.id);

        showLoader();
        window.axios
            .get(url)
            .then(function (response) {
                var $content = $('<div class="cabinet-mon-groups-child" />').append($(response.data));

                $content.find('.top').each(function () {
                    var str = $(this).text();
                    if (str.indexOf('+') > 0) {
                        $(this).addClass('cabinet-mon-groups-grow');
                    }
                    if (str.indexOf('-') > 0) {
                        $(this).addClass('cabinet-mon-groups-shrink');
                    }
                });

                row.child($content).show();
                $tr.addClass('shown');
                $icon.removeClass('bi-plus-circle').addClass('bi-dash-circle');

                $content.find('.tooltip-child-table').tooltip({
                    animation: false,
                    trigger: 'hover',
                });

                if (window.cabinetMonitoringChildCharts) {
                    window.cabinetMonitoringChildCharts.wire(
                        $content,
                        data.monitoring_project_id || cfg.projectId,
                        {
                            chartsUrl: cfg.chartsUrl || '/monitoring/charts',
                            i18n: {
                                childChartShow: cfg.i18n.childChartShow,
                                childChartHide: cfg.i18n.childChartHide,
                                loadError: cfg.i18n.loadError,
                            },
                        }
                    );
                }
            })
            .catch(function () {
                if (typeof toastr !== 'undefined') {
                    toastr.error(cfg.i18n.loadError || 'Error');
                }
            })
            .finally(function () {
                showLoader();
            });
    }

    function initEditor() {
        editor = new $.fn.dataTable.Editor({
            ajax: cfg.routes.action,
            table: '#groups',
            fields: buildFields(),
            i18n: {
                create: {
                    button: cfg.i18n.createButton,
                    submit: cfg.i18n.createSubmit,
                },
                edit: {
                    submit: cfg.i18n.editSubmit,
                },
                remove: {
                    submit: cfg.i18n.deleteSubmit,
                    confirm: {
                        _: cfg.i18n.deleteConfirm,
                        1: cfg.i18n.deleteConfirmOne,
                    },
                },
                multi: {
                    title: cfg.i18n.multiTitle,
                    info: cfg.i18n.multiInfo,
                    restore: cfg.i18n.multiRestore,
                    noMulti: cfg.i18n.multiNoMulti,
                },
            },
        });
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
                    return '<span class="cabinet-mon-groups-name">' + $('<div>').text(data || '').html() + '</span>';
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
                className: 'cabinet-mon-groups-col-actions',
                data: function (row) {
                    var openUrl = '/monitoring/' + row.monitoring_project_id + '?group=' + row.id;
                    var html =
                        '<div class="cabinet-mon-groups-row-actions" role="group" aria-label="' +
                        cfg.i18n.colActions +
                        '">' +
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

        var buttons = [];

        if (canSelect) {
            buttons.push({
                text: cfg.i18n.selectAll,
                className: 'btn btn-outline-secondary btn-sm',
                action: function (e, dt) {
                    e.preventDefault();
                    dt.rows({ page: 'current', search: 'applied' }).select();
                },
            });
            buttons.push({
                text: cfg.i18n.selectNone,
                className: 'btn btn-outline-secondary btn-sm',
                action: function (e, dt) {
                    e.preventDefault();
                    dt.rows({ selected: true }).deselect();
                },
            });
        }

        if (cfg.canCreate) {
            buttons.push({
                extend: 'create',
                editor: editor,
                className: 'btn btn-primary btn-sm',
                text: cfg.i18n.createButton,
                action: function () {
                    dynamicHideFields.forEach(function (obj) {
                        editor.field(obj.name).hide();
                    });
                    editor.create({
                        title: cfg.i18n.createTitle,
                        buttons: cfg.i18n.createSubmit,
                    });
                },
            });
        }

        if (cfg.canEdit) {
            buttons.push({
                text: cfg.i18n.editSelected,
                className: 'btn btn-outline-primary btn-sm',
                extend: 'edit',
                editor: editor,
            });
        }

        var nonOrderable = canSelect ? [0, 1, 6, 7] : [0, 5, 6];

        table = $('#groups').DataTable({
            dom: 'Brt',
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
            // API отдаёт весь список без draw/recordsTotal — serverSide здесь ломает redraw (статы 0).
            processing: false,
            serverSide: false,
            ajax: {
                url: cfg.routes.list,
                type: 'POST',
                dataSrc: 'data',
                complete: function () {
                    showLoader();
                    if (table) {
                        updateStats(table);
                    }
                },
                error: function () {
                    showLoader();
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
            buttons: {
                dom: {
                    container: {
                        className: 'dt-buttons cabinet-mon-groups-dt-buttons',
                    },
                    button: {
                        className: 'btn',
                    },
                },
                buttons: buttons,
            },
            initComplete: function () {
                var api = this.api();
                var $wrapper = $(api.table().container());

                $wrapper
                    .find('.dt-buttons')
                    .removeClass('btn-group')
                    .appendTo('#groups-dt-actions');
                $wrapper.find('.dataTables_filter').appendTo('#groups-dt-filter');

                if (window.cabinetMonitoringSearch) {
                    window.cabinetMonitoringSearch.wireGlobalDataTableSearch(api);
                }

                $('#groups').on('click', '.cabinet-mon-groups-expand', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    toggleChildRow($(this), api);
                });

                api.on('select.dt deselect.dt', function () {
                    syncGroupChecks(api);
                });
                syncGroupChecks(api);
                showLoader();
                updateStats(api);
            },
            drawCallback: function () {
                var api = this.api();
                syncGroupChecks(api);
                updateStats(api);
                showLoader();
            },
        });
    }

    $(document).ready(function () {
        toastr.options = { preventDuplicates: true, timeOut: 5000 };

        showLoader();
        initEditor();
        initTable();

        $('#groups').on('click', 'td .editor-edit', function (e) {
            e.preventDefault();
            dynamicHideFields.forEach(function (obj) {
                editor.field(obj.name).show();
            });
            editor.edit($(this).closest('tr'), {
                title: cfg.i18n.editTitle,
                buttons: cfg.i18n.editSubmit,
            });
        });

        $('#groups').on('click', 'td .editor-delete', function (e) {
            e.preventDefault();
            editor.remove($(this).closest('tr'), {
                title: cfg.i18n.deleteTitle,
                message: cfg.i18n.deleteConfirmOne,
                buttons: cfg.i18n.deleteSubmit,
            });
        });
    });
}(window.jQuery, window.cabinetMonGroupsConfig));
