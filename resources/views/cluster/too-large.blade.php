@component('component.card', ['title' => __('Analysis results')])
    <p class="mb-3">
        {{ __('Cluster result is too large to display in the browser. Download the file or split the project.') }}
    </p>
    <p class="text-muted mb-4">
        {{ __('Phrases') }}: {{ number_format($countPhrases, 0, ',', ' ') }},
        {{ __('Clusters') }}: {{ number_format($countClusters, 0, ',', ' ') }}
    </p>
    <div class="btn-group">
        <a href="{{ route('download.cluster.result', ['cluster' => $clusterId, 'type' => 'xls']) }}"
           class="btn btn-primary">{{ __('Download') }} XLS</a>
        <a href="{{ route('download.cluster.result', ['cluster' => $clusterId, 'type' => 'csv']) }}"
           class="btn btn-secondary">{{ __('Download') }} CSV</a>
        <a href="{{ route('cluster.projects') }}" class="btn btn-outline-secondary">{{ __('Projects') }}</a>
    </div>
@endcomponent
