@component('component.card', [
    'title' => __('Export') . ' — ' . ($project->name ?: $project->url),
])
    @slot('css')
        <link rel="stylesheet" href="{{ asset('plugins/tempusdominus-bootstrap-4/css/tempusdominus-bootstrap-4.min.css') }}">
        <link rel="stylesheet" href="{{ asset('plugins/select2/css/select2.min.css') }}">
        <link rel="stylesheet" href="{{ asset('plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css') }}">
        <link rel="stylesheet" href="{{ asset('css/cabinet-monitoring-export.css') }}?v={{ @filemtime(public_path('css/cabinet-monitoring-export.css')) ?: time() }}">
    @endslot

    <div class="cabinet-mon-export-page">
        <p class="text-muted mb-3">
            <a href="{{ url('/monitoring/' . $project->id) }}">&larr; {{ __('Monitoring show back to project') }}</a>
        </p>

        <form action="/monitoring/{{ $project->id }}/export" method="GET" class="cabinet-mon-export-page__form" id="cabinetMonExportForm">
            @include('monitoring.export.form-fields')

            <div class="cabinet-mon-export-page__actions">
                <a href="{{ url('/monitoring/' . $project->id) }}" class="btn btn-default">{{ __('Close') }}</a>
                <button type="submit" class="btn btn-success">{{ __('Export') }}</button>
            </div>
        </form>
    </div>

    @slot('js')
        <script src="{{ asset('plugins/moment/moment.min.js') }}"></script>
        <script src="{{ asset('plugins/moment/locale/ru.js') }}"></script>
        <script src="{{ asset('plugins/tempusdominus-bootstrap-4/js/tempusdominus-bootstrap-4.min.js') }}"></script>
        <script src="{{ asset('plugins/select2/js/select2.full.min.js') }}"></script>
        <script src="{{ asset('js/cabinet-select2-defaults.js') }}"></script>
        <script>
            (function ($) {
                $('#startDatePicker, #endDatePicker').datetimepicker({
                    format: 'L',
                    locale: 'ru'
                });

                $('#cabinetMonExportMode').on('change', function () {
                    if ($(this).val() === 'finance') {
                        $('#finance').removeClass('d-none');
                    } else {
                        $('#finance').addClass('d-none');
                    }
                });

                var $groups = $('#cabinetMonExportGroups');
                if ($groups.length && $.fn.select2) {
                    $groups.select2({
                        theme: 'bootstrap4',
                        width: '100%',
                        placeholder: $groups.data('placeholder') || '',
                        allowClear: true,
                        closeOnSelect: false
                    });
                }
            })(jQuery);
        </script>
    @endslot
@endcomponent
