/**
 * /monitoring/offset-positions — коррекция позиций при экспорте отчёта.
 */
(function ($, window) {
    'use strict';

    const cfg = window.cabinetMonitoringOffsetConfig;
    if (!cfg) {
        return;
    }

    const $project = $('#mon-offset-project');
    const $openExport = $('#mon-offset-open-export');
    const $modal = $('#cabinetMonKeywordsModal');
    const modalEl = $modal.length ? $modal[0] : null;

    function getModalInstance() {
        if (!modalEl || !window.bootstrap || !window.bootstrap.Modal) {
            return null;
        }
        return window.bootstrap.Modal.getOrCreateInstance(modalEl);
    }

    function exportEditUrl(projectId) {
        return cfg.exportEditUrlTemplate.replace('__ID__', String(projectId));
    }

    function updateOpenButton() {
        $openExport.prop('disabled', !$project.val());
    }

    function initExportModalContent($content) {
        $content.find('select[name="mode"] option[value="finance"]').prop('selected', true);
        $content.find('#finance').removeClass('d-none');

        $content.find('select[name="mode"]').on('change', function () {
            if ($(this).val() === 'finance') {
                $content.find('#finance').removeClass('d-none');
            } else {
                $content.find('#finance').addClass('d-none');
            }
        });

        $content.find('#startDatePicker, #endDatePicker').datetimepicker({
            format: 'L',
            locale: 'ru',
        });

        var $groups = $content.find('#cabinetMonExportGroups');
        if ($groups.length && $.fn.select2) {
            $groups.select2({
                theme: 'bootstrap4',
                width: '100%',
                dropdownParent: $modal,
                placeholder: $groups.data('placeholder') || '',
                allowClear: true,
                closeOnSelect: false,
            });
        }
    }

    function appendOffsetFields($form) {
        $form.find('input[name^="offset["]').remove();

        $('#mon-offset-rules .cabinet-mon-offset-rule').each(function (index) {
            const $rule = $(this);
            const fields = ['from', 'to', 'operator', 'count'];

            fields.forEach(function (field) {
                const value = $rule.find('[name="offset[' + index + '][' + field + ']"]').val();
                if (value === '' || value == null) {
                    return;
                }
                $('<input>', {
                    type: 'hidden',
                    name: 'offset[' + index + '][' + field + ']',
                    value: value,
                }).appendTo($form);
            });
        });
    }

    function openExportModal() {
        const projectId = $project.val();
        if (!projectId) {
            if (typeof toastr !== 'undefined') {
                toastr.warning(cfg.i18n.projectRequired);
            }
            return;
        }

        $modal.find('.modal-content').html(
            '<div class="modal-body text-center py-5">' +
            '<span class="cabinet-mon-loader cabinet-mon-loader--sm me-2" role="presentation">' +
            '<i class="fas fa-circle-notch cabinet-mon-loader__icon" aria-hidden="true"></i></span>' +
            cfg.i18n.exportLoading +
            '</div>'
        );

        const instance = getModalInstance();
        if (instance) {
            instance.show();
        }

        if (window.cabinetButtonBusy) {
            window.cabinetButtonBusy.set($openExport, {label: cfg.i18n.exportSubmitBusy});
        }

        window.axios.get(exportEditUrl(projectId))
            .then(function (response) {
                $modal.find('.modal-content').html(response.data);
                initExportModalContent($modal.find('.modal-content'));

                $modal.find('form').on('submit', function (event) {
                    event.preventDefault();
                    const $form = $(this);
                    appendOffsetFields($form);
                    window.location = $form.attr('action') + '?' + $form.serialize();
                });
            })
            .catch(function () {
                $modal.find('.modal-content').html(
                    '<div class="modal-body"><div class="alert alert-danger mb-0">' +
                    cfg.i18n.exportLoadError +
                    '</div></div>'
                );
                if (typeof toastr !== 'undefined') {
                    toastr.error(cfg.i18n.exportLoadError);
                }
            })
            .finally(function () {
                if (window.cabinetButtonBusy) {
                    window.cabinetButtonBusy.clear($openExport);
                }
                updateOpenButton();
            });
    }

    $(function () {
        if (typeof toastr !== 'undefined') {
            toastr.options = {preventDuplicates: true, timeOut: 5000};
        }

        $('.select2').select2({
            theme: 'bootstrap4',
            width: '100%',
        });

        $project.on('change', updateOpenButton);
        $openExport.on('click', openExportModal);
        updateOpenButton();
    });
})(jQuery, window);
