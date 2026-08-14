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
                    modal.find('select[name="mode"]').change(function () {
                        if ($(this).val() === 'finance') {
                            modal.find('#finance').removeClass('d-none');
                        } else {
                            modal.find('#finance').addClass('d-none');
                        }
                    });
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

        $root.on('click', '.cancel-project', function () {
            const self = $(this);
            const id = self.closest('.cabinet-mon-v2-card').data('project-id') || self.closest('tr').data('id');

            axios
                .post(@json(route('approve.project')), { approve: 0, id: id })
                .then(function () {
                    toastr.success(@json(__('Request has been canceled')));
                    self.closest('.cabinet-mon-v2-card').remove();
                });
        });

        $root.on('click', '.add-user', function () {
            const id = $(this).data('id');

            axios
                .get('/monitoring/get-user-status-options')
                .then(function (response) {
                    $('.modal')
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

        $root.on('click', '.detach-user', function () {
            const $self = $(this);
            axios
                .post(@json(route('user.detach')), {
                    project_id: $self.data('project'),
                    user_id: $self.data('id'),
                })
                .then(refreshList)
                .catch(function () {
                    toastr.error(@json(__('Wrong request')));
                });
            return false;
        });

        $root.on('click', '.change-user-status', function () {
            const self = $(this);
            const user = self.attr('user-id');
            const project = self.attr('project-id');

            axios
                .get('/monitoring/get-user-status-options')
                .then(function (response) {
                    $('.modal')
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
