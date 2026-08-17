@php
    $sectionMetrics = $metricCatalog[$key] ?? [];
    $origin = $meta['origin'] ?? \App\SeoReports\SeoReportSectionRegistry::origin($key);
    $groupKey = $meta['group'] ?? 'core';
    $groupLabel = $groupLabels[$groupKey] ?? $groupKey;
    $isTitlo = ($meta['origin_kind'] ?? '') === 'titlo' || $groupKey === 'titlo';
@endphp
<div class="cabinet-sr-builder__row {{ $enabled ? 'is-on' : 'is-off' }}"
     @if($enabled) draggable="true" @endif
     data-sr-row
     data-key="{{ $key }}"
     data-title="{{ $meta['title'] }}"
     data-hint="{{ $meta['hint'] ?? '' }}"
     data-group="{{ $groupKey }}"
     data-source="{{ $meta['source'] }}"
     data-source-label="{{ $origin }}"
     data-titlo="{{ $isTitlo ? '1' : '0' }}"
     data-enabled="{{ $enabled ? '1' : '0' }}">
    <label class="cabinet-sr-builder__switch">
        <input type="checkbox" data-sr-toggle @if($enabled) checked @endif>
        <span class="cabinet-sr-builder__switch-ui" aria-hidden="true"></span>
        <span class="sr-only">{{ $meta['title'] }}</span>
    </label>
    <span class="cabinet-sr-builder__drag" aria-hidden="true" @if(!$enabled) hidden @endif>⋮⋮</span>
    <input type="hidden" name="sections[{{ $key }}]" value="{{ $enabled ? '1' : '0' }}" data-sr-section-val>
    @if($enabled)
        <input type="hidden" name="section_order[]" value="{{ $key }}" data-sr-order>
    @endif
    <span class="cabinet-sr-builder__block-body">
        <span class="cabinet-sr-builder__block-title">{{ $meta['title'] }}</span>
        <span class="cabinet-sr-builder__block-hint">{{ $meta['hint'] ?? '' }}</span>
        <span class="cabinet-sr-builder__block-meta">{{ $origin }} · {{ $groupLabel }}</span>
    </span>
    @if($sectionMetrics !== [])
        <details class="cabinet-sr-builder__metrics" data-sr-metrics @if(!$enabled) hidden @endif>
            <summary class="cabinet-sr-builder__metrics-toggle">
                <span class="cabinet-sr-builder__metrics-toggle-main">
                    <span class="cabinet-sr-builder__metrics-toggle-icon" aria-hidden="true"></span>
                    <span class="cabinet-sr-builder__metrics-toggle-label">{{ __('Metrics in block') }}</span>
                    <span class="cabinet-sr-builder__metrics-count" data-sr-metrics-count></span>
                </span>
                <span class="cabinet-sr-builder__metrics-toggle-hint">{{ __('Configure metrics') }}</span>
            </summary>
            <div class="cabinet-sr-builder__metrics-body">
                @if($key === 'traffic')
                    @php
                        $scopeNow = \App\SeoReports\SeoReportTrafficScope::normalize($settings ?? []);
                        $scopeChannels = $scopeNow['channels'];
                    @endphp
                    <div class="cabinet-sr-traffic-scope" data-sr-traffic-scope>
                        <div class="cabinet-sr-traffic-scope__rec" role="note">
                            <strong>{{ __('Recommendation') }}:</strong>
                            {{ __('SEO traffic scope recommendation') }}
                        </div>
                        <div class="cabinet-sr-traffic-scope__presets">
                            <button type="button" class="btn btn-sm btn-outline-primary" data-sr-traffic-preset="search_only">
                                {{ __('Traffic mode search only') }} ★
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-sr-traffic-preset="organic_ad">
                                {{ __('Traffic preset search + ads') }}
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-sr-traffic-preset="all">
                                {{ __('Traffic mode all sources') }}
                            </button>
                        </div>
                        <div class="cabinet-sr-traffic-scope__list">
                            @foreach(\App\SeoReports\SeoReportTrafficScope::channels() as $ch)
                                <label class="cabinet-sr-traffic-scope__ch {{ !empty($ch['distorts']) ? 'is-distort' : '' }} {{ !empty($ch['recommended']) ? 'is-rec' : '' }}"
                                       title="{{ __($ch['hint']) }}">
                                    <input type="checkbox"
                                           name="traffic_channels[]"
                                           value="{{ $ch['id'] }}"
                                           data-sr-traffic-ch
                                           data-distort="{{ !empty($ch['distorts']) ? '1' : '0' }}"
                                        @if(in_array($ch['id'], $scopeChannels, true)) checked @endif>
                                    <span class="cabinet-sr-traffic-scope__ch-main">
                                        <span>{{ __($ch['label']) }}</span>
                                        @if(!empty($ch['recommended']))
                                            <em>{{ __('Recommended') }}</em>
                                        @endif
                                        @if(!empty($ch['distorts']))
                                            <b>{{ __('Distorts picture') }}</b>
                                        @endif
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        <p class="small text-secondary mb-0">{{ __('SEO traffic scope applies to') }}</p>
                    </div>
                @endif
                <input type="search"
                       class="form-control form-control-sm cabinet-sr-builder__metrics-search"
                       data-sr-metrics-search
                       placeholder="{{ __('Search metrics') }}"
                       autocomplete="off">
                <div class="cabinet-sr-builder__metrics-list">
                    @foreach($sectionMetrics as $metric)
                        <div class="cabinet-sr-builder__metric" data-sr-metric data-label="{{ $metric['label'] }}">
                            <label class="cabinet-sr-builder__metric-main">
                                <input type="hidden" name="metric_toggles[{{ $key }}][{{ $metric['key'] }}]" value="0">
                                <input type="checkbox"
                                       name="metric_toggles[{{ $key }}][{{ $metric['key'] }}]"
                                       value="1"
                                       data-sr-metric-cb
                                    @if(!empty($metricToggles[$key][$metric['key']])) checked @endif>
                                <span>{{ $metric['label'] }}</span>
                            </label>
                            @include('pages.partials.seo-reports-metric-look-tip', ['metric' => $metric])
                        </div>
                    @endforeach
                </div>
                <p class="cabinet-sr-builder__metrics-empty" data-sr-metrics-empty hidden>{{ __('No metrics match') }}</p>
            </div>
        </details>
    @endif
</div>
