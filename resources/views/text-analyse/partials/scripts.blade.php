<script>
    window.cabinetTextAnalyzerConfig = {
        hasResponse: @json(isset($response)),
        isPublicView: @json(!empty($isPublicView)),
        scrollToResults: @json(!empty($scrollToResults)),
        initialUrl: @json($url ?? null),
        cloudWordLimit: 100,
        emptyLabel: @json(__('No records')),
        repetitionsLabel: @json(__('Repetitions')),
        chartLabels: {
            actual: @json((isset($response) && !empty($response['comparison'])) ? __('Your page') : __('Actual values')),
            ideal: @json(__('Ideal values')),
            competitor: @json((isset($response) && !empty($response['comparison']))
                ? \App\TextAnalyzer::competitorLabel($response['comparison']['competitor_url'] ?? '')
                : __('Competitor')),
            xAxis: @json(__('Word density'))
        }
    };
</script>
<script src="{{ asset('js/cabinet-text-analyzer.js') }}?v={{ @filemtime(public_path('js/cabinet-text-analyzer.js')) ?: time() }}"></script>
