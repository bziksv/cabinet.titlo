@php
    $request = $cluster['request'];
    $isPro = ($request['mode'] ?? '') === 'professional' || $admin;
    $regionName = \App\Common::getRegionName($request['region'] ?? '');
@endphp
@component('component.card', [
    'title' => __('Analysis results'),
    'titleHtml' => e(__('Analysis results'))
        . view('partials.cabinet-module-version-badge', ['configKey' => 'cabinet-cluster'])->render(),
])
    @slot('css')
        <link rel="stylesheet" href="{{ asset('plugins/keyword-generator/css/font-awesome-4.7.0/css/font-awesome.css') }}">
        <link rel="stylesheet" href="{{ asset('plugins/toastr/toastr.css') }}">
        <link rel="stylesheet" href="{{ asset('plugins/common/css/datatable.css') }}">
        <link rel="stylesheet" href="{{ asset('css/cabinet-cluster-v2.css') }}?v={{ @filemtime(public_path('css/cabinet-cluster-v2.css')) ?: time() }}">
    @endslot

    <div class="cabinet-cluster-v2-page cabinet-cluster-result-v2" id="cabinet-cluster-result-v2-root">
        @include('cluster.partials.module-nav', [
            'active' => 'result',
            'clusterId' => $cluster['id'],
            'admin' => $admin,
        ])

        <div id="toast-container" class="toast-top-right success-message">
            <div class="toast toast-success" aria-live="polite" style="display:none;">
                <div class="toast-message success-msg"></div>
            </div>
        </div>
        <div id="toast-container" class="toast-top-right error-message">
            <div class="toast toast-error" aria-live="assertive" style="display:none;">
                <div class="toast-message error-msg">{{ __('An unexpected error has occurred, please contact the administrator') }}</div>
            </div>
        </div>

        @include('cluster-v2.partials.relevance-modal')

        <div class="modal fade" id="saveUrlsModal" tabindex="-1" aria-labelledby="saveUrlsModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="saveUrlsModalLabel">{{ __('Select the url that will be saved for each phrase of this cluster') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <select name="relevanceUrls" id="relevanceUrls" class="form-select"></select>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-primary" id="save-cluster-url-button" data-bs-dismiss="modal">{{ __('Save') }}</button>
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Close') }}</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-3 cabinet-cluster-result-v2__summary">
            <div class="card-body py-3">
                <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
                    <div>
                        <p class="text-muted small mb-1">{{ __('Analysis results') }}</p>
                        <h2 class="h5 mb-0">{{ $regionName }} · ТОП {{ $request['count'] ?? '—' }}@if($isPro) · {{ $request['clusteringLevel'] ?? '' }}@endif</h2>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <a class="btn btn-outline-secondary btn-sm" href="{{ route('download.cluster.result', ['cluster' => $cluster['id'], 'type' => 'csv']) }}" target="_blank" rel="noopener">{{ __('Download csv') }}</a>
                        <a class="btn btn-outline-secondary btn-sm" href="{{ route('download.cluster.result', ['cluster' => $cluster['id'], 'type' => 'xls']) }}" target="_blank" rel="noopener">{{ __('Download xls') }}</a>
                        <a class="btn btn-primary btn-sm" href="{{ route('edit.clusters', $cluster['id']) }}">{{ __('Hands editor') }}</a>
                        @if($isPro)
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#fastScan">{{ __('Rebuild') }}</button>
                        @endif
                    </div>
                </div>
                <div class="cabinet-cluster-result-v2__chips">
                    <span class="badge rounded-pill text-bg-light border"><strong>{{ $cluster['count_phrases'] }}</strong> {{ __('Number of phrases') }}</span>
                    <span class="badge rounded-pill text-bg-light border"><strong>{{ $cluster['count_clusters'] }}</strong> {{ __('Number of clusters') }}</span>
                    <span class="badge rounded-pill text-bg-light border">{{ $request['searchEngine'] ?? 'yandex' }}</span>
                    <button type="button" class="badge rounded-pill text-bg-primary border-0" id="copyUsedPhrases">{{ __('Phrases') }} · {{ __('Copy') }}</button>
                    <textarea id="usedPhrases" class="visually-hidden" aria-hidden="true"></textarea>
                </div>
            </div>
        </div>

        @if($isPro)
            @include('cluster-v2.partials.show-fast-scan-modal', ['cluster' => $cluster, 'request' => $request])
        @endif

        <section id="cabinet-cluster-v2-results" class="cabinet-cluster-v2-results-wrap" aria-live="polite">
            <div class="card cabinet-cluster-v2-results-card shadow-sm">
                <div class="card-header cabinet-cluster-v2-results-head d-flex flex-wrap justify-content-between align-items-center gap-2 py-3">
                    <div>
                        <h2 class="h5 mb-0">{{ __('Cluster table') }}</h2>
                        <p class="small text-muted mb-0 mt-1" id="clv2-results-meta"></p>
                    </div>
                </div>
                <div class="card-body p-0 position-relative">
                    <div id="loader-block" class="cabinet-cluster-result-v2__loader text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">{{ __('Render data') }}</span>
                        </div>
                        <p class="mt-2 mb-0 text-muted small">{{ __('Render data') }}</p>
                        <p class="mb-0 small text-muted">
                            <span id="rendered-clusters">0</span> / {{ $cluster['count_phrases'] }}
                        </p>
                    </div>
                    <div id="result-table" class="cabinet-cluster-v2-results-scroll table-responsive" style="display:none;">
                        <table id="clusters-table" class="table table-sm mb-0 cabinet-cluster-v2-clusters-table">
                            <thead class="visually-hidden">
                            <tr>
                                <th>{{ __('Clusters') }}</th>
                                <th>{{ __('Competitors') }}</th>
                            </tr>
                            </thead>
                            <tbody id="clusters-table-tbody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        <textarea id="hiddenForCopy" class="visually-hidden" aria-hidden="true"></textarea>
    </div>

    @slot('js')
        <script>
            window.cabinetClusterResultV2 = {
                clusterId: @json($cluster['id']),
                result: {!! $cluster['result'] !!},
                request: @json($request),
                routes: {
                    fastScan: @json(route('fast.scan.clusters')),
                    setRelevanceUrl: @json(route('set.cluster.relevance.url')),
                    downloadPhrases: @json(url('/download-cluster-phrases')),
                },
                i18n: {
                    copyUrls: @json('Копировать URL'),
                    viewLinks: @json(__('View links phrases')),
                    resultsMeta: @json('Кластеров: :clusters · Фраз: :phrases'),
                    freqZeroHint: @json('Частотность 0: проверьте queue worker и Wordstat. Перезапустите анализ при необходимости.'),
                    copied: @json(__('Successfully copied')),
                },
            };
        </script>
        <script src="{{ asset('plugins/cluster/js/common_v2.min.js') }}"></script>
        <script src="{{ asset('plugins/cluster/js/render-result-table_v2.min.js') }}"></script>
        <script src="{{ asset('plugins/cluster/js/render-result-fast-table.min.js') }}"></script>
        <script src="{{ asset('plugins/cluster/js/render-hidden-fast.min.js') }}"></script>
        <script src="{{ asset('plugins/common/js/common.js') }}"></script>
        <script src="{{ asset('js/cabinet-cluster-result-v2.js') }}?v={{ @filemtime(public_path('js/cabinet-cluster-result-v2.js')) ?: time() }}"></script>
    @endslot
@endcomponent
