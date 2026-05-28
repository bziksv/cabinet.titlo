@if(($projectCount ?? 0) > 0)
    <li class="nav-item dropdown cabinet-mon-v2-chart-settings-nav">
        <button type="button"
                class="nav-link dropdown-toggle border-0 bg-transparent"
                id="cabinet-mon-v2-chart-settings-btn"
                data-bs-toggle="dropdown"
                data-bs-auto-close="outside"
                aria-expanded="false">
            <i class="bi bi-sliders me-1" aria-hidden="true"></i>{{ __('Monitoring v2 chart settings') }}
        </button>
        <div class="dropdown-menu dropdown-menu-lg cabinet-mon-v2-chart-settings-menu p-3"
             id="cabinet-mon-v2-chart-settings-menu"
             aria-labelledby="cabinet-mon-v2-chart-settings-btn">
            <p class="small text-muted mb-3">{{ __('Monitoring v2 chart settings lead') }}</p>
            <div class="mb-3">
                <label class="form-label small mb-1" for="cabinet-mon-v2-chart-period">{{ __('Monitoring v2 portfolio trend period') }}</label>
                <select class="form-select form-select-sm" id="cabinet-mon-v2-chart-period">
                    @foreach([30, 60, 90, 180, 366] as $d)
                        <option value="{{ $d }}" @if($d === 90) selected @endif>{{ $d }} {{ __('days') }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label small mb-1" for="cabinet-mon-v2-chart-range">{{ __('Monitoring child chart granularity') }}</label>
                <select class="form-select form-select-sm" id="cabinet-mon-v2-chart-range">
                    <option value="days">{{ __('Monitoring child chart range days') }}</option>
                    <option value="weeks" selected>{{ __('Monitoring child chart range weeks') }}</option>
                    <option value="month">{{ __('Monitoring child chart range month') }}</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label small mb-1" for="cabinet-mon-v2-chart-metric">{{ __('Monitoring child chart metric') }}</label>
                <select class="form-select form-select-sm" id="cabinet-mon-v2-chart-metric">
                    <option value="top" selected>{{ __('Monitoring child chart metric top') }}</option>
                    <option value="position">{{ __('Monitoring child chart metric position') }}</option>
                </select>
            </div>
            <div class="mb-0">
                <span class="form-label small d-block mb-1">{{ __('Monitoring child chart series group') }}</span>
                <div class="cabinet-mon-v2-chart-series-presets" role="group">
                    <button type="button" class="btn btn-outline-secondary btn-sm active" data-chart-setting="seriesPreset" data-series-preset="10">TOP-10</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-chart-setting="seriesPreset" data-series-preset="351020100">3/5/10/20/100</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-chart-setting="seriesPreset" data-series-preset="35102050100">3/5/10/20/50/100</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-chart-setting="seriesPreset" data-series-preset="all">{{ __('Monitoring child chart preset all') }}</button>
                </div>
            </div>
        </div>
    </li>
@endif
