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

    function showLoader(show) {
        $('#groupsLoader').toggleClass('d-none', !show);
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
        var data = api.rows({ page: 'current' }).data().toArray();
        var count = data.length;
        var queries = data.reduce(function (sum, row) {
            return sum + (parseInt(row.queries, 10) || 0);
        }, 0);

        $('#groups-stats-groups').text(count);
        $('#groups-stats-queries').text(queries);
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

        showLoader(true);
        window.axios.get(url).then(function (response) {
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
        }).finally(function () {
            showLoader(false);
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
        var columns = [
            {
                orderable: false,
                searchable: false,
                data: null,
                className: 'cabinet-mon-groups-col-expand',
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
                        '" class="btn btn-sm btn-outline-secondary cabinet-mon-groups-row-actions__btn" title="' +
                        cfg.i18n.openGroup +
                        '"><i class="bi bi-folder2-open" aria-hidden="true"></i></a>';

                    if (cfg.canEdit) {
                        html +=
                            '<button type="button" class="btn btn-sm btn-outline-secondary cabinet-mon-groups-row-actions__btn editor-edit" title="' +
                            cfg.i18n.editGroup +
                            '"><i class="bi bi-pencil" aria-hidden="true"></i></button>';
                    }

                    if (cfg.canDelete) {
                        html +=
                            '<button type="button" class="btn btn-sm btn-outline-danger cabinet-mon-groups-row-actions__btn editor-delete" title="' +
                            cfg.i18n.deleteGroup +
                            '"><i class="bi bi-trash" aria-hidden="true"></i></button>';
                    }

                    html += '</div>';
                    return html;
                },
            },
        ];

        var buttons = [
            {
                text: cfg.i18n.selectAll,
                className: 'btn-outline-secondary btn-sm',
                extend: 'selectAll',
            },
            {
                text: cfg.i18n.selectNone,
                className: 'btn-outline-secondary btn-sm',
                extend: 'selectNone',
            },
        ];

        if (cfg.canCreate) {
            buttons.push({
                extend: 'create',
                editor: editor,
                className: 'btn-primary btn-sm',
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
                className: 'btn-outline-primary btn-sm',
                extend: 'edit',
                editor: editor,
            });
        }

        table = $('#groups').DataTable({
            dom: 'Brt',
            autoWidth: false,
            fixedHeader: true,
            paging: false,
            ordering: true,
            order: [[2, 'asc']],
            language: {
                search: '_INPUT_',
                searchPlaceholder: cfg.i18n.search,
                processing: '',
                emptyTable: cfg.i18n.emptyTable,
                zeroRecords: cfg.i18n.zeroRecords,
            },
            processing: true,
            serverSide: true,
            ajax: {
                url: cfg.routes.list,
                type: 'POST',
                beforeSend: function () {
                    showLoader(true);
                },
                complete: function () {
                    showLoader(false);
                },
            },
            columnDefs: [
                { orderable: false, targets: [0, 5, 6] },
            ],
            columns: columns,
            select: cfg.canEdit || cfg.canDelete ? { style: 'multi' } : false,
            buttons: buttons,
            initComplete: function () {
                var api = this.api();
                var $wrapper = $(api.table().container());

                $wrapper.find('.dt-buttons').appendTo('#groups-dt-actions');
                $wrapper.find('.dataTables_filter').appendTo('#groups-dt-filter');

                if (window.cabinetMonitoringSearch) {
                    window.cabinetMonitoringSearch.wireGlobalDataTableSearch(api);
                }

                $('#groups').on('click', '.cabinet-mon-groups-expand', function (e) {
                    e.preventDefault();
                    toggleChildRow($(this), api);
                });

                updateStats(api);
            },
            drawCallback: function () {
                updateStats(this.api());
            },
        });
    }

    $(document).ready(function () {
        toastr.options = { preventDuplicates: true, timeOut: 5000 };

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
