@if($count < 1)
    <section class="cabinet-mon-v2-portfolio cabinet-mon-v2-portfolio--empty">
        <p class="mb-0 text-secondary">{{ __('Monitoring v2 dash empty') }}</p>
    </section>
@else
    <section class="cabinet-mon-v2-portfolio" id="cabinet-mon-v2-dashboard" aria-label="{{ __('Monitoring v2 dash title') }}">
        <div class="cabinet-mon-v2-portfolio__bar">
            <div class="cabinet-mon-v2-portfolio__intro">
                <h2 class="cabinet-mon-v2-portfolio__title mb-0">{{ __('Monitoring v2 portfolio title') }}</h2>
                <p class="cabinet-mon-v2-portfolio__subtitle mb-0">{{ __('Monitoring v2 dash subtitle') }}</p>
            </div>
            <div class="cabinet-mon-v2-portfolio__kpis" role="list">
                <div class="cabinet-mon-v2-kpi" role="listitem">
                    <span class="cabinet-mon-v2-kpi__value" data-dash="projects">{{ $count }}</span>
                    <span class="cabinet-mon-v2-kpi__label">{{ __('Projects count') }}</span>
                </div>
                <div class="cabinet-mon-v2-kpi cabinet-mon-v2-kpi--accent" role="listitem">
                    <span class="cabinet-mon-v2-kpi__value" data-dash="avgTop10">—</span>
                    <span class="cabinet-mon-v2-kpi__label">{{ __('Monitoring v2 dash avg top10') }}</span>
                </div>
                <div class="cabinet-mon-v2-kpi" role="listitem">
                    <span class="cabinet-mon-v2-kpi__value" data-dash="avgMiddle">—</span>
                    <span class="cabinet-mon-v2-kpi__label">{{ __('Monitoring v2 dash avg position') }}</span>
                </div>
                <div class="cabinet-mon-v2-kpi" role="listitem">
                    <span class="cabinet-mon-v2-kpi__value" data-dash="words">—</span>
                    <span class="cabinet-mon-v2-kpi__label">{{ __('Words') }}</span>
                </div>
                <div class="cabinet-mon-v2-kpi" role="listitem">
                    <span class="cabinet-mon-v2-kpi__value" data-dash="weak">—</span>
                    <span class="cabinet-mon-v2-kpi__label">{{ __('Monitoring v2 dash weak') }}</span>
                </div>
            </div>
        </div>

        <div class="cabinet-mon-v2-portfolio__chart-panel">
            <div class="cabinet-mon-v2-portfolio__chart-toolbar">
                <div class="btn-group btn-group-sm cabinet-mon-v2-dash-view" role="group" aria-label="{{ __('Monitoring v2 dash chart mode') }}">
                    <button type="button" class="btn btn-light active" data-dash-chart="leaders">{{ __('Monitoring v2 dash chart leaders') }}</button>
                    <button type="button" class="btn btn-light" data-dash-chart="distribution">{{ __('Monitoring v2 dash chart distribution') }}</button>
                    <button type="button" class="btn btn-light" data-dash-chart="portfolio">{{ __('Monitoring v2 dash chart portfolio') }}</button>
                    <button type="button" class="btn btn-light" data-dash-chart="trend">{{ __('Monitoring v2 dash chart trend') }}</button>
                </div>
                <div class="btn-group btn-group-sm cabinet-mon-v2-dash-metric d-none" id="cabinet-mon-v2-dash-metric" role="group" aria-label="{{ __('Monitoring v2 dash metric label') }}">
                    <button type="button" class="btn btn-light active" data-dash-metric="top10">{{ __('TOP') }}‑10</button>
                    <button type="button" class="btn btn-light" data-dash-metric="top30">{{ __('TOP') }}‑30</button>
                    <button type="button" class="btn btn-light" data-dash-metric="middle">{{ __('Position') }}</button>
                </div>
            </div>
            <div class="cabinet-mon-v2-portfolio__canvas">
                <div
                    id="cabinet-mon-v2-trend-loader"
                    class="cabinet-mon-v2-trend-loader"
                    hidden
                    role="status"
                    aria-live="polite"
                    aria-busy="true"
                >
                    <div class="cabinet-mon-v2-trend-loader__card">
                        <div class="cabinet-mon-v2-trend-loader__spinner" aria-hidden="true"></div>
                        <p class="cabinet-mon-v2-trend-loader__title mb-1">
                            {{ __('Monitoring v2 portfolio trend loading title') }}
                        </p>
                        <p class="cabinet-mon-v2-trend-loader__detail mb-2" data-trend-loader-detail></p>
                        <p class="cabinet-mon-v2-trend-loader__stage mb-2" data-trend-loader-stage></p>
                        <p class="cabinet-mon-v2-trend-loader__elapsed mb-3" data-trend-loader-elapsed aria-live="polite"></p>
                        <div
                            class="cabinet-mon-v2-trend-loader__track"
                            data-trend-loader-track
                            role="progressbar"
                            aria-valuemin="0"
                            aria-valuemax="100"
                            aria-valuenow="0"
                            aria-label="{{ __('Monitoring v2 portfolio trend loading title') }}"
                        >
                            <div class="cabinet-mon-v2-trend-loader__bar" data-trend-loader-bar></div>
                        </div>
                        <div class="cabinet-mon-v2-trend-loader__timeline" aria-hidden="true">
                            @for ($i = 0; $i < 14; $i++)
                                <span class="cabinet-mon-v2-trend-loader__dot" style="--dot-i: {{ $i }}"></span>
                            @endfor
                        </div>
                    </div>
                </div>
                <div
                    id="cabinet-mon-v2-trend-build"
                    class="cabinet-mon-v2-trend-build"
                    hidden
                    aria-live="polite"
                >
                    <div class="cabinet-mon-v2-trend-build__track">
                        <div class="cabinet-mon-v2-trend-build__bar" data-trend-build-bar></div>
                    </div>
                    <span class="cabinet-mon-v2-trend-build__text" data-trend-build-text></span>
                </div>
                <canvas id="cabinet-mon-v2-chart-main" height="320" aria-hidden="true"></canvas>
            </div>
            <p class="cabinet-mon-v2-portfolio__hint mb-0">
                <span id="cabinet-mon-v2-dash-hint">{{ __('Monitoring v2 dash hint all') }}</span>
                <button
                    type="button"
                    class="btn btn-sm btn-outline-secondary ms-2 d-none"
                    id="cabinet-mon-v2-trend-refresh"
                >{{ __('Monitoring v2 portfolio trend refresh') }}</button>
            </p>
        </div>
    </section>
@endif
