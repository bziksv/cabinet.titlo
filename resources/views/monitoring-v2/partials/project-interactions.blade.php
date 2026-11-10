<script>
    (function () {
        const $root = $('#cabinet-mon-v2-root');

        function refreshList() {
            if (window.cabinetMonV2List && typeof window.cabinetMonV2List.reload === 'function') {
                window.cabinetMonV2List.reload();
            } else {
                window.location.reload();
            }
        }

        toastr.options = {
            preventDuplicates: true,
            timeOut: '5000',
            maxOpened: 3,
            autoDismiss: true,
        };

        $('.modal').on('show.bs.modal', function (event) {
            const modal = $(this);
            const button = $(event.relatedTarget);
            const type = button.data('type');
            const projectId = button.data('id');

            if (type === 'create_keywords') {
                axios.get(`/monitoring/keywords/${projectId}/create`).then(function (response) {
                    const content = response.data;
                    modal.find('.modal-content').html(content);
                    modal.find('#upload-queries').click(function () {
                        const self = $(this);
                        const csv = self.closest('.input-group').find('#upload');

                        if (csv[0].files.length && csv[0].files[0].type === 'text/csv') {
                            csv.parse({
                                config: {
                                    skipEmptyLines: 'greedy',
                                    complete: function (result) {
                                        let value = '';
                                        $.each(result.data, function (i, item) {
                                            if (item[0]) {
                                                value += item[0] + '\r\n';
                                            }
                                        });
                                        modal.find('textarea[name="query"]').val(value);
                                    },
                                    download: 0,
                                },
                            });
                        } else {
                            toastr.error(@json(__('Upload csv file required')));
                        }
                    });
                }).then(function () {
                    const group = modal.find('.form-select[name="monitoring_group_id"]');
                    if (group.length) {
                        group.select2({});
                        modal.find('#create-group').click(function () {
                            const input = $(this).closest('.input-group').find('input');
                            if (input.val()) {
                                const id_project = input.data('id');
                                axios
                                    .post('/monitoring/groups', {
                                        monitoring_project_id: id_project,
                                        type: 'keyword',
                                        name: input.val(),
                                    })
                                    .then(function (response) {
                                        const newOption = new Option(response.data.name, response.data.id, false, true);
                                        group.append(newOption).trigger('change');
                                        input.val(null);
                                    })
                                    .catch(function () {
                                        toastr.error(@json(__('Something went wrong')));
                                    });
                            }
                        });
                    }

                    modal.find('.save-modal').click(function (e) {
                        const self = $(this);
                        const form = self.closest('.modal-content').find('form');
                        const action = form.attr('action');
                        const method = form.attr('method');
                        const data = {};

                        $.each(form.serializeArray(), function (inc, item) {
                            $.extend(data, { [item.name]: item.value });
                        });

                        if (!data.hasOwnProperty('monitoring_group_id') || data.monitoring_group_id.length < 1) {
                            e.preventDefault();
                            form.find('.invalid-feedback.monitoring_group_id').fadeIn().delay(3000).fadeOut();
                            return false;
                        }

                        if (data.query.length < 1) {
                            e.preventDefault();
                            form.find('.invalid-feedback.query').fadeIn().delay(3000).fadeOut();
                            return false;
                        }

                        axios({ method: method, url: action, data: data })
                            .then(function () {
                                self.closest('.modal').modal('hide');
                                toastr.success(@json(__('Queries added success')));
                                refreshList();
                            })
                            .catch(function (error) {
                                console.log(error);
                            });
                    });
                });
            }

            if (type === 'export-edit') {
                axios.get(`/monitoring/${projectId}/export/edit`).then(function (response) {
                    modal.find('.modal-content').html(response.data);
                    function syncExportRegionMode() {
                        var allRegions = !String(modal.find('#cabinetMonExportRegion').val() || '');
                        var $mode = modal.find('#cabinetMonExportMode');
                        var $financeOpt = $mode.find('option[value="finance"]');
                        if (allRegions) {
                            if ($mode.val() === 'finance') {
                                $mode.val('range');
                            }
                            $financeOpt.prop('disabled', true);
                            modal.find('#finance').addClass('d-none');
                        } else {
                            $financeOpt.prop('disabled', false);
                        }
                        if ($mode.val() === 'finance') {
                            modal.find('#finance').removeClass('d-none');
                        } else {
                            modal.find('#finance').addClass('d-none');
                        }
                    }
                    modal.find('select[name="mode"]').on('change', syncExportRegionMode);
                    modal.find('#cabinetMonExportRegion').on('change', syncExportRegionMode);
                    syncExportRegionMode();
                    modal.find('#startDatePicker, #endDatePicker').datetimepicker({
                        format: 'L',
                        locale: 'ru',
                    });
                    var $groups = modal.find('#cabinetMonExportGroups');
                    if ($groups.length && $.fn.select2) {
                        $groups.select2({
                            theme: 'bootstrap4',
                            width: '100%',
                            dropdownParent: modal,
                            placeholder: $groups.data('placeholder') || '',
                            allowClear: true,
                            closeOnSelect: false
                        });
                    }
                });
            }
        });

        $root.on('click', '.approve-project', function (e) {
            e.preventDefault();
            const self = $(this);
            const id =
                self.closest('[data-id]').data('id') ||
                self.closest('.cabinet-mon-v2-card').data('project-id') ||
                self.closest('tr').data('id');

            if (!id) {
                return;
            }

            axios
                .post(@json(route('approve.project')), { approve: 1, id: id })
                .then(function () {
                    toastr.success(@json(__('Monitoring v2 invite accepted')));
                    refreshList();
                })
                .catch(function () {
                    toastr.error(@json(__('Wrong request')));
                });
        });

        $root.on('click', '.cancel-project', function (e) {
            e.preventDefault();
            const self = $(this);
            const id =
                self.closest('[data-id]').data('id') ||
                self.closest('.cabinet-mon-v2-card').data('project-id') ||
                self.closest('tr').data('id');

            if (!id) {
                return;
            }

            axios
                .post(@json(route('approve.project')), { approve: 0, id: id })
                .then(function () {
                    toastr.success(@json(__('Request has been canceled')));
                    refreshList();
                })
                .catch(function () {
                    toastr.error(@json(__('Wrong request')));
                });
        });

        $root.on('click', '.add-user', function (e) {
            e.preventDefault();
            const id = $(this).data('id');
            const $formModal = $('#cabinetMonV2FormModal');

            axios
                .get('/monitoring/get-user-status-options')
                .then(function (response) {
                    $formModal
                        .modal('show')
                        .BootstrapModalFormTemplates({
                            title: @json(__('Add user to project')),
                            btnText: @json(__('Invite')),
                            fields: [
                                {
                                    type: 'text',
                                    name: 'email',
                                    label: @json(__('Email user') . ' (Если вы хотите добавить сразу несколько пользователей перечислите email через запятую)'),
                                    params: [{ val: '', placeholder: 'test@mail.ru, test2@mail.ru' }],
                                },
                                {
                                    type: 'select',
                                    name: 'status',
                                    label: @json(__('User status')),
                                    params: response.data,
                                },
                            ],
                            onAgree: function (m) {
                                const formData = new FormData(m.find('form').get(0));
                                axios
                                    .post(@json(route('approve.attach')), {
                                        id: id,
                                        email: formData.getAll('email')[0],
                                        status: formData.getAll('status')[0],
                                    })
                                    .then(function () {
                                        toastr.success(@json(__('Request has been sent')));
                                        m.modal('hide');
                                        refreshList();
                                    })
                                    .catch(function () {
                                        toastr.error(@json(__('Wrong mail')));
                                    });
                            },
                        });
                })
                .catch(console.log);
        });

        $root.on('click', '.detach-user', function (e) {
            e.preventDefault();
            e.stopPropagation();
            const $self = $(this);
            const projectId = $self.data('project');
            const userId = Number($self.data('id'));
            const isSelf = userId === Number(@json(auth()->id()));
            const tip = (($self.closest('li').attr('aria-label') || '').split(' — ')[0] || '').trim();
            const title = isSelf ? 'Покинуть проект' : @json(__('Remove member'));
            const text = isSelf
                ? 'Вы уверены, что хотите покинуть проект?'
                : tip
                  ? 'Удалить «' + tip + '» из проекта?'
                  : @json(__('Detach user from project?'));
            const okLabel = isSelf ? 'Покинуть' : 'Удалить';

            function doDetach() {
                axios
                    .post(@json(route('user.detach')), {
                        project_id: projectId,
                        user_id: userId,
                    })
                    .then(refreshList)
                    .catch(function () {
                        toastr.error(@json(__('Wrong request')));
                    });
            }

            if (window.CabinetConfirm && typeof window.CabinetConfirm.show === 'function') {
                window.CabinetConfirm.show({
                    title: title,
                    text: text,
                    okLabel: okLabel,
                    danger: true,
                    onConfirm: doDetach,
                });
            } else if (window.confirm(text)) {
                doDetach();
            }
            return false;
        });

        $root.on('click', '.change-user-status', function (e) {
            e.preventDefault();
            const self = $(this);
            const user = self.attr('user-id');
            const project = self.attr('project-id');
            const $formModal = $('#cabinetMonV2FormModal');

            axios
                .get('/monitoring/get-user-status-options')
                .then(function (response) {
                    $formModal
                        .modal('show')
                        .BootstrapModalFormTemplates({
                            title: @json(__('Set user status')),
                            btnText: @json(__('Save')),
                            fields: [
                                {
                                    type: 'select',
                                    name: 'status',
                                    label: @json(__('User status')),
                                    params: response.data,
                                },
                            ],
                            onAgree: function (m) {
                                const formData = new FormData(m.find('form').get(0));
                                axios
                                    .post(@json(route('monitoring.user.project.status')), {
                                        user: user,
                                        project: project,
                                        status: formData.getAll('status')[0],
                                    })
                                    .then(function () {
                                        toastr.success(@json(__('Saved')));
                                        m.modal('hide');
                                        refreshList();
                                    })
                                    .catch(function () {
                                        toastr.error(@json(__('You must be administrator project.')));
                                    });
                            },
                        });
                });
            return false;
        });

        $root.on('click', '.copy-project', function () {
            axios.get($(this).data('action')).then(function (response) {
                if (response.statusText === 'OK') {
                    toastr.success(@json(__('Copy request sent')));
                }
            });
        });

        if (window.Echo) {
            window.Echo.private(`App.User.{{ auth()->id() }}`).listen('MonitoringProjectCopyProgress', function (e) {
                toastr.remove();
                toastr.info(e.message);
            });
        }
    })();
</script>
