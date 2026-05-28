<div class="cabinet-mon-v2-child-chart-controls">
    <p class="text-muted small mb-2 cabinet-mon-v2-child-chart-hint">{{ __('Monitoring child chart hint local') }}</p>
    <div class="cabinet-mon-v2-child-chart-controls__row">
        <div class="cabinet-mon-v2-child-chart-controls__field">
            <label class="form-label small mb-1">{{ __('Monitoring v2 portfolio trend period') }}</label>
            <select class="form-select form-select-sm cabinet-mon-v2-child-chart-period" aria-label="{{ __('Monitoring v2 portfolio trend period') }}">
                @foreach([30, 60, 90, 180, 366] as $d)
                    <option value="{{ $d }}" @if($d === 90) selected @endif>{{ $d }} {{ __('days') }}</option>
                @endforeach
            </select>
        </div>
        <div class="cabinet-mon-v2-child-chart-controls__field">
            <label class="form-label small mb-1">{{ __('Monitoring child chart granularity') }}</label>
            <select class="form-select form-select-sm cabinet-mon-v2-child-chart-range" aria-label="{{ __('Monitoring child chart granularity') }}">
                <option value="days">{{ __('Monitoring child chart range days') }}</option>
                <option value="weeks" selected>{{ __('Monitoring child chart range weeks') }}</option>
                <option value="month">{{ __('Monitoring child chart range month') }}</option>
            </select>
        </div>
        <div class="cabinet-mon-v2-child-chart-controls__field">
            <label class="form-label small mb-1">{{ __('Monitoring child chart metric') }}</label>
            <select class="form-select form-select-sm cabinet-mon-v2-child-chart-metric" aria-label="{{ __('Monitoring child chart metric') }}">
                <option value="top" selected>{{ __('Monitoring child chart metric top') }}</option>
                <option value="position">{{ __('Monitoring child chart metric position') }}</option>
            </select>
        </div>
    </div>
    <div class="cabinet-mon-v2-child-chart-series-presets mb-2" role="group" aria-label="{{ __('Monitoring child chart series group') }}">
        <span class="form-label small d-block mb-1">{{ __('Monitoring child chart series group') }}</span>
        <div class="d-flex flex-wrap gap-1">
            <button type="button" class="btn btn-outline-secondary btn-sm active" data-child-chart-setting="seriesPreset" data-series-preset="10">TOP-10</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" data-child-chart-setting="seriesPreset" data-series-preset="351020100">3/5/10/20/100</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" data-child-chart-setting="seriesPreset" data-series-preset="35102050100">3/5/10/20/50/100</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" data-child-chart-setting="seriesPreset" data-series-preset="all">{{ __('Monitoring child chart preset all') }}</button>
        </div>
    </div>
    <button type="button" class="btn btn-link btn-sm px-0 cabinet-mon-v2-child-chart-reset-global">
        {{ __('Monitoring child chart reset global') }}
    </button>
</div>
