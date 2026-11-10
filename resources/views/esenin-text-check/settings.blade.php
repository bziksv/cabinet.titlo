@php
    $s = $settings ?? [];
    $bool = static function (string $code) use ($s) {
        return in_array(strtolower((string) ($s[$code] ?? '0')), ['1', 'true', 'yes', 'on'], true);
    };
    $val = static function (string $code, $default = '') use ($s) {
        return old(str_replace('.', '_', $code), $s[$code] ?? $default);
    };
    $shareTtl = $s['module.public_share_ttl_days'] ?? '[30,90,180,365,0]';
    if (is_string($shareTtl) && strpos($shareTtl, '[') === 0) {
        $decoded = json_decode($shareTtl, true);
        $shareTtl = is_array($decoded) ? implode(', ', $decoded) : $shareTtl;
    }
@endphp

@component('component.card', [
    'title' => __('Esenin text check'),
    'titleHtml' => e(__('Esenin text check')) . view('partials.cabinet-module-version-badge', ['configKey' => 'cabinet-esenin-text-check'])->render(),
])
    @slot('css')
        <link rel="stylesheet" href="{{ asset('css/cabinet-esenin-text-check.css') }}?v={{ @filemtime(public_path('css/cabinet-esenin-text-check.css')) ?: time() }}">
    @endslot

    <div class="cabinet-esenin-page cabinet-esenin-settings-page">
        @include('esenin-text-check.partials.module-nav', ['active' => 'settings'])

        <div class="row g-3 align-items-start">
            <div class="col-xl-8">
                <form action="{{ route('pages.esenin-text-check.settings') }}" method="post" class="row g-3">
                    @csrf

                    <div class="col-12">
                        <div class="card shadow-sm border-0">
                            <div class="card-header py-2">
                                <h3 class="card-title h6 mb-0">{{ __('Esenin admin module limits title') }}</h3>
                            </div>
                            <div class="card-body row g-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="module_max_chars">{{ __('Esenin admin max chars') }}</label>
                                    <input type="number" name="module_max_chars" id="module_max_chars" min="1000" max="100000"
                                           class="form-control form-control-sm" value="{{ old('module_max_chars', $s['module.max_chars'] ?? 20000) }}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="module_cost_per_check">{{ __('Esenin admin cost per check') }}</label>
                                    <input type="number" name="module_cost_per_check" id="module_cost_per_check" min="1" max="100"
                                           class="form-control form-control-sm" value="{{ old('module_cost_per_check', $s['module.cost_per_check'] ?? 1) }}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="module_default_mode">{{ __('Esenin text check mode label') }}</label>
                                    <select name="module_default_mode" id="module_default_mode" class="form-select form-select-sm">
                                        @foreach(\App\Services\EseninTextCheckService::MODES as $mode => $label)
                                            <option value="{{ $mode }}" @if(old('module_default_mode', $s['module.default_mode'] ?? 'risk') === $mode) selected @endif>{{ $label }} ({{ $mode }})</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="module_analyzer_version">{{ __('Esenin admin analyzer version') }}</label>
                                    <input type="number" name="module_analyzer_version" id="module_analyzer_version" min="1" max="999"
                                           class="form-control form-control-sm" value="{{ old('module_analyzer_version', $s['module.analyzer_version'] ?? 4) }}">
                                    <p class="form-text small mb-0">{{ __('Esenin admin analyzer version hint') }}</p>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="module_max_versions_per_session">{{ __('Esenin admin max versions') }}</label>
                                    <input type="number" name="module_max_versions_per_session" id="module_max_versions_per_session" min="1" max="50"
                                           class="form-control form-control-sm" value="{{ old('module_max_versions_per_session', $s['module.max_versions_per_session'] ?? 3) }}">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="module_max_saved_sessions">{{ __('Esenin admin max sessions') }}</label>
                                    <input type="number" name="module_max_saved_sessions" id="module_max_saved_sessions" min="1" max="500"
                                           class="form-control form-control-sm" value="{{ old('module_max_saved_sessions', $s['module.max_saved_sessions'] ?? 100) }}">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="module_autosave_debounce_ms">{{ __('Esenin admin autosave ms') }}</label>
                                    <input type="number" name="module_autosave_debounce_ms" id="module_autosave_debounce_ms" min="500" max="30000"
                                           class="form-control form-control-sm" value="{{ old('module_autosave_debounce_ms', $s['module.autosave_debounce_ms'] ?? 2500) }}">
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="module_public_share_ttl_days">{{ __('Esenin admin share ttl') }}</label>
                                    <input type="text" name="module_public_share_ttl_days" id="module_public_share_ttl_days"
                                           class="form-control form-control-sm" value="{{ old('module_public_share_ttl_days', $shareTtl) }}">
                                    <p class="form-text small mb-0">{{ __('Esenin admin share ttl hint') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="card shadow-sm border-0">
                            <div class="card-header py-2">
                                <h3 class="card-title h6 mb-0">{{ __('Esenin admin demo title') }}</h3>
                            </div>
                            <div class="card-body row g-3">
                                <div class="col-md-3">
                                    <label class="form-label" for="demo_max_runs_per_day">{{ __('Esenin admin demo runs') }}</label>
                                    <input type="number" name="demo_max_runs_per_day" id="demo_max_runs_per_day" min="1" max="100"
                                           class="form-control form-control-sm" value="{{ old('demo_max_runs_per_day', $s['demo.max_runs_per_day'] ?? 3) }}">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label" for="demo_min_chars">{{ __('Esenin admin demo min chars') }}</label>
                                    <input type="number" name="demo_min_chars" id="demo_min_chars" min="10" max="5000"
                                           class="form-control form-control-sm" value="{{ old('demo_min_chars', $s['demo.min_chars'] ?? 100) }}">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label" for="demo_max_chars">{{ __('Esenin admin demo max chars') }}</label>
                                    <input type="number" name="demo_max_chars" id="demo_max_chars" min="100" max="50000"
                                           class="form-control form-control-sm" value="{{ old('demo_max_chars', $s['demo.max_chars'] ?? 5000) }}">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label" for="demo_full_max_chars">{{ __('Esenin admin demo full max') }}</label>
                                    <input type="number" name="demo_full_max_chars" id="demo_full_max_chars" min="1000" max="100000"
                                           class="form-control form-control-sm" value="{{ old('demo_full_max_chars', $s['demo.full_max_chars'] ?? 20000) }}">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="card shadow-sm border-0">
                            <div class="card-header py-2 d-flex justify-content-between align-items-center">
                                <h3 class="card-title h6 mb-0">{{ __('Esenin admin turgenev title') }}</h3>
                                @if(!empty($providerStatus['turgenev']['ok']))
                                    <span class="badge text-bg-success">{{ __('Esenin admin balance') }}: {{ number_format((float) ($providerStatus['turgenev']['balance'] ?? 0), 2, ',', ' ') }} ₽</span>
                                @elseif($bool('provider.turgenev.enabled'))
                                    <span class="badge text-bg-warning">{{ __('Esenin admin provider error') }}: {{ $providerStatus['turgenev']['error'] ?? '—' }}</span>
                                @endif
                            </div>
                            <div class="card-body row g-3">
                                <div class="col-12">
                                    <div class="form-check form-switch">
                                        <input type="checkbox" class="form-check-input" name="provider_turgenev_enabled" id="provider_turgenev_enabled" value="1"
                                               @if(old('provider_turgenev_enabled', $bool('provider.turgenev.enabled'))) checked @endif>
                                        <label class="form-check-label" for="provider_turgenev_enabled">{{ __('Esenin admin turgenev enabled') }}</label>
                                    </div>
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label" for="provider_turgenev_url">{{ __('Esenin admin api url') }}</label>
                                    <input type="url" name="provider_turgenev_url" id="provider_turgenev_url"
                                           class="form-control form-control-sm" value="{{ old('provider_turgenev_url', $s['provider.turgenev.url'] ?? '') }}">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="provider_turgenev_score_blend_percent">{{ __('Esenin admin turgenev blend') }}</label>
                                    <input type="number" name="provider_turgenev_score_blend_percent" id="provider_turgenev_score_blend_percent" min="0" max="100"
                                           class="form-control form-control-sm" value="{{ old('provider_turgenev_score_blend_percent', $s['provider.turgenev.score_blend_percent'] ?? 100) }}">
                                    <div class="form-text">{{ __('Esenin admin turgenev blend hint') }}</div>
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label" for="provider_turgenev_key">{{ __('Esenin admin turgenev key') }}</label>
                                    <input type="password" name="provider_turgenev_key" id="provider_turgenev_key" autocomplete="new-password"
                                           class="form-control form-control-sm" placeholder="{{ !empty($s['provider.turgenev.key']) ? '••••••••' : '' }}">
                                    <p class="form-text small mb-0">{{ __('Esenin admin secret keep hint') }}</p>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="provider_turgenev_timeout">{{ __('Timeout') }}</label>
                                    <input type="number" name="provider_turgenev_timeout" id="provider_turgenev_timeout" min="5" max="120"
                                           class="form-control form-control-sm" value="{{ old('provider_turgenev_timeout', $s['provider.turgenev.timeout'] ?? 30) }}">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="card shadow-sm border-0">
                            <div class="card-header py-2 d-flex justify-content-between align-items-center">
                                <h3 class="card-title h6 mb-0">{{ __('Esenin admin languagetool title') }}</h3>
                                @if($bool('provider.languagetool.enabled'))
                                    <span class="badge {{ !empty($providerStatus['languagetool']['ok']) ? 'text-bg-success' : 'text-bg-warning' }}">
                                        {{ !empty($providerStatus['languagetool']['ok']) ? __('Esenin admin provider online') : __('Esenin admin provider offline') }}
                                    </span>
                                @endif
                            </div>
                            <div class="card-body row g-3">
                                <div class="col-12">
                                    <div class="form-check form-switch">
                                        <input type="checkbox" class="form-check-input" name="provider_languagetool_enabled" id="provider_languagetool_enabled" value="1"
                                               @if(old('provider_languagetool_enabled', $bool('provider.languagetool.enabled'))) checked @endif>
                                        <label class="form-check-label" for="provider_languagetool_enabled">{{ __('Esenin admin languagetool enabled') }}</label>
                                    </div>
                                    <p class="form-text small mb-0">{{ __('Esenin admin languagetool hint') }}</p>
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label" for="provider_languagetool_url">{{ __('Esenin admin languagetool url') }}</label>
                                    <input type="url" name="provider_languagetool_url" id="provider_languagetool_url"
                                           class="form-control form-control-sm" value="{{ old('provider_languagetool_url', $s['provider.languagetool.url'] ?? 'http://127.0.0.1:8010') }}">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="provider_languagetool_timeout">{{ __('Timeout') }}</label>
                                    <input type="number" name="provider_languagetool_timeout" id="provider_languagetool_timeout" min="3" max="120"
                                           class="form-control form-control-sm" value="{{ old('provider_languagetool_timeout', $s['provider.languagetool.timeout'] ?? 20) }}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="provider_languagetool_language">{{ __('Esenin admin languagetool language') }}</label>
                                    <input type="text" name="provider_languagetool_language" id="provider_languagetool_language"
                                           class="form-control form-control-sm" value="{{ old('provider_languagetool_language', $s['provider.languagetool.language'] ?? 'ru-RU') }}">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="card shadow-sm border-0">
                            <div class="card-header py-2">
                                <h3 class="card-title h6 mb-0">{{ __('Esenin admin opencorpora title') }}</h3>
                            </div>
                            <div class="card-body row g-3">
                                <div class="col-12">
                                    <div class="form-check form-switch">
                                        <input type="checkbox" class="form-check-input" name="provider_opencorpora_enabled" id="provider_opencorpora_enabled" value="1"
                                               @if(old('provider_opencorpora_enabled', $bool('provider.opencorpora.enabled'))) checked @endif>
                                        <label class="form-check-label" for="provider_opencorpora_enabled">{{ __('Esenin admin opencorpora enabled') }}</label>
                                    </div>
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label" for="provider_opencorpora_url">{{ __('Esenin admin api url') }}</label>
                                    <input type="url" name="provider_opencorpora_url" id="provider_opencorpora_url"
                                           class="form-control form-control-sm" value="{{ old('provider_opencorpora_url', $s['provider.opencorpora.url'] ?? '') }}">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="provider_opencorpora_timeout">{{ __('Timeout') }}</label>
                                    <input type="number" name="provider_opencorpora_timeout" id="provider_opencorpora_timeout" min="3" max="120"
                                           class="form-control form-control-sm" value="{{ old('provider_opencorpora_timeout', $s['provider.opencorpora.timeout'] ?? 10) }}">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="card shadow-sm border-0">
                            <div class="card-header py-2">
                                <h3 class="card-title h6 mb-0">{{ __('Esenin admin learning title') }}</h3>
                            </div>
                            <div class="card-body">
                                <div class="form-check form-switch mb-2">
                                    <input type="checkbox" class="form-check-input" name="learning_report_fetch_enabled" id="learning_report_fetch_enabled" value="1"
                                           @if(old('learning_report_fetch_enabled', $bool('learning.report_fetch_enabled'))) checked @endif>
                                    <label class="form-check-label" for="learning_report_fetch_enabled">{{ __('Esenin admin learning report fetch') }}</label>
                                </div>
                                <div class="form-check form-switch mb-2">
                                    <input type="checkbox" class="form-check-input" name="learning_enabled" id="learning_enabled" value="1"
                                           @if(old('learning_enabled', $bool('learning.enabled'))) checked @endif>
                                    <label class="form-check-label" for="learning_enabled">{{ __('Esenin admin learning enabled') }}</label>
                                </div>
                                <p class="form-text small mb-0">{{ __('Esenin admin learning hint') }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="bi bi-save me-1" aria-hidden="true"></i>{{ __('Update') }}
                        </button>
                    </div>
                </form>
            </div>

            <div class="col-xl-4">
                <div class="info-box shadow-sm mb-3">
                    <span class="info-box-icon text-bg-primary"><i class="bi bi-folder2-open" aria-hidden="true"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ __('Esenin admin stat sessions') }}</span>
                        <span class="info-box-number">{{ number_format($stats['sessions_total'] ?? 0, 0, ',', ' ') }}</span>
                        <span class="info-box-text">{{ __('Esenin admin stat versions') }}: {{ number_format($stats['versions_total'] ?? 0, 0, ',', ' ') }}</span>
                    </div>
                </div>
                <div class="info-box shadow-sm mb-3">
                    <span class="info-box-icon text-bg-info"><i class="bi bi-graph-up" aria-hidden="true"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ __('Esenin admin stat checks month') }}</span>
                        <span class="info-box-number">{{ number_format($stats['checks_this_month'] ?? 0, 0, ',', ' ') }}</span>
                        <span class="info-box-text">{{ __('Esenin admin stat users month') }}: {{ number_format($stats['users_with_checks'] ?? 0, 0, ',', ' ') }}</span>
                    </div>
                </div>
                <div class="info-box shadow-sm mb-3">
                    <span class="info-box-icon text-bg-secondary"><i class="bi bi-book" aria-hidden="true"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ __('Esenin admin stat style candidates') }}</span>
                        <span class="info-box-number">{{ number_format($stats['style_candidates'] ?? 0, 0, ',', ' ') }}</span>
                        <span class="info-box-text"><code>php artisan esenin:style-candidates</code></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endcomponent
