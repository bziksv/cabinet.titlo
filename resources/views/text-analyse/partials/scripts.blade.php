<script>
    window.cabinetTextAnalyzerConfig = {
        hasResponse: @json(isset($response)),
        scrollToResults: @json(!empty($scrollToResults)),
        initialUrl: @json($url ?? null),
        cloudWordLimit: 80,
        emptyLabel: @json(__('No records')),
        repetitionsLabel: @json(__('Repetitions')),
        chartLabels: {
            actual: @json(__('Actual values')),
            ideal: @json(__('Ideal values')),
            rank: @json(__('Word rank'))
        },
        dtLang: {
            lengthMenu: '_MENU_',
            search: '',
            searchPlaceholder: @json(__('Search')),
            paginate: {first: '«', last: '»', next: '›', previous: '‹'},
            emptyTable: @json(__('No records')),
            zeroRecords: @json(__('No records')),
            info: @json(__('Showing')) + ' _START_–_END_ ' + @json(__('of')) + ' _TOTAL_',
            infoEmpty: @json(__('No records'))
        }
    };
</script>
<script src="{{ asset('js/cabinet-text-analyzer.js') }}?v={{ @filemtime(public_path('js/cabinet-text-analyzer.js')) ?: time() }}"></script>
