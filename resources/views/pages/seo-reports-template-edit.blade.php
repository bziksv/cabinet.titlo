@component('component.card', [
    'title' => __('Edit template') . ' · ' . $template->title,
    'documentTitle' => __('Edit template') . ' · ' . $template->title,
])
    @slot('css')
        <link rel="stylesheet" href="{{ asset('css/cabinet-seo-reports.css') }}?v={{ @filemtime(public_path('css/cabinet-seo-reports.css')) ?: time() }}">
    @endslot

    @php
        $orderKeys = \App\SeoReports\SeoReportSectionRegistry::orderedKeys($settings);
        $groupLabels = \App\SeoReports\SeoReportSectionRegistry::groupLabels();
        $brandPreview = \App\SeoReports\SeoReportBrandColor::normalize(
            old('brand_color', $template->brand_color) ?: '#1d4ed8'
        );
        $selectedKeys = [];
        $offKeys = [];
        foreach ($orderKeys as $key) {
            $meta = $sectionCatalog[$key] ?? null;
            if (!$meta || $key === 'cover') {
                continue;
            }
            if (!empty($toggles[$key])) {
                $selectedKeys[] = $key;
            } else {
                $offKeys[] = $key;
            }
        }
        foreach ($sectionCatalog as $key => $meta) {
            if ($key === 'cover' || in_array($key, $selectedKeys, true) || in_array($key, $offKeys, true)) {
                continue;
            }
            $offKeys[] = $key;
        }
        $kpiHints = collect(\App\SeoReports\SeoReportKpiGoals::wizardRows())->keyBy('type');
        $metricCatalog = \App\SeoReports\SeoReportMetricRegistry::catalog();
        $metricToggles = \App\SeoReports\SeoReportMetricRegistry::normalize($settings['metric_toggles'] ?? null);
        $builderDefaults = [
            'order' => array_values(array_filter(
                \App\SeoReports\SeoReportSectionRegistry::defaultOrder(),
                static function ($key) {
                    return $key !== 'cover';
                }
            )),
            'toggles' => \App\SeoReports\SeoReportSectionRegistry::defaultToggles(),
            'metrics' => \App\SeoReports\SeoReportMetricRegistry::defaults(),
        ];
        unset($builderDefaults['toggles']['cover']);
    @endphp

    <div class="cabinet-sr-page">
        @include('pages.partials.seo-reports-nav', [
            'srTab' => 'templates',
        ])

        @if(session('success'))
            <div class="alert alert-success py-2 px-3 small">{{ session('success') }}</div>
        @endif

        <form method="post"
              action="{{ route('pages.seo-reports.templates.update', ['id' => $template->id]) }}"
              enctype="multipart/form-data"
              data-sr-tpl-form>
            @csrf
            <input type="hidden" name="sections[cover]" value="1">
            <input type="hidden" name="section_order[]" value="cover">

            <div class="cabinet-sr-tpl-hero">
                <div class="cabinet-sr-tpl-hero__main">
                    <a class="cabinet-sr-tpl-hero__back" href="{{ route('pages.seo-reports.templates') }}">← {{ __('Templates') }}</a>
                    <h1 class="cabinet-sr-hero__title">{{ __('Edit report template') }}</h1>
                    <p class="cabinet-sr-hero__lead mb-0">
                        {{ __('Report template edit lead') }}
                        @if(($projectsCount ?? 0) > 0)
                            · {{ __('Used in :count projects', ['count' => (int) $projectsCount]) }}
                        @endif
                    </p>
                </div>
                <div class="cabinet-sr-tpl-hero__actions">
                    <a class="btn btn-outline-secondary btn-sm"
                       href="{{ route('pages.seo-reports.templates.demo', ['id' => $template->id]) }}"
                       target="_blank" rel="noopener">{{ __('Open demo report') }}</a>
                    <button type="submit" class="btn btn-primary btn-sm">{{ __('Save template') }}</button>
                </div>
            </div>

            <section class="cabinet-sr-tpl-basics">
                <div class="cabinet-sr-tpl-basics__fields">
                    <div>
                        <label class="form-label" for="srTplTitle">{{ __('Template name') }}</label>
                        <input type="text" class="form-control" id="srTplTitle" name="title"
                               value="{{ old('title', $template->title) }}" required maxlength="80"
                               data-sr-tpl-title>
                    </div>
                    <div>
                        <label class="form-label" for="srTplDesc">{{ __('Description') }}</label>
                        <input type="text" class="form-control" id="srTplDesc" name="description"
                               value="{{ old('description', $settings['description'] ?? '') }}"
                               maxlength="190"
                               placeholder="{{ __('Template description placeholder') }}">
                    </div>
                    @php
                        $trafficScope = \App\SeoReports\SeoReportTrafficScope::normalize($settings);
                    @endphp
                    <div>
                        <label class="form-label" for="srTraffic">
                            {{ __('Traffic in report') }}
                            <i class="bi bi-question-circle text-muted ms-1"
                               style="font-size:0.85em;cursor:help"
                               data-bs-toggle="tooltip"
                               data-bs-placement="top"
                               title="{{ __('Traffic mode hint') }}"
                               aria-label="{{ __('Traffic mode hint') }}"></i>
                        </label>
                        <select class="form-select" id="srTraffic" name="traffic_mode" data-sr-traffic-mode>
                            <option value="search_only" @if($trafficScope['mode'] === 'search_only') selected @endif>
                                {{ __('Traffic mode search only') }} ★
                            </option>
                            <option value="all" @if($trafficScope['mode'] === 'all') selected @endif>
                                {{ __('Traffic mode all sources') }}
                            </option>
                            <option value="custom" @if($trafficScope['mode'] === 'custom') selected @endif>
                                {{ __('Traffic mode custom') }}
                            </option>
                        </select>
                        <p class="small text-secondary mb-0 mt-1">{{ __('Traffic scope open metrics hint') }}</p>
                    </div>
                </div>

                @php
                    $periodPreset = old('default_period', $settings['default_period'] ?? 'prev_month');
                    $compareMode = old('compare_mode', $settings['compare_mode'] ?? 'previous_period');
                    $autoCompare = old('auto_compare', !empty($settings['auto_compare']) || !array_key_exists('auto_compare', $settings));
                @endphp
                <div class="cabinet-sr-period" data-sr-period-settings>
                    <div class="cabinet-sr-period__grid">
                        <div>
                            <label class="form-label" for="srPeriod">{{ __('Default period') }}</label>
                            <select class="form-select" id="srPeriod" name="default_period" data-sr-period-preset>
                                <option value="prev_month" @if($periodPreset === 'prev_month') selected @endif>
                                    {{ __('Previous calendar month') }}
                                </option>
                                <option value="last_30" @if($periodPreset === 'last_30') selected @endif>
                                    {{ __('Last 30 days') }}
                                </option>
                                <option value="calendar_month" @if($periodPreset === 'calendar_month') selected @endif>
                                    {{ __('Specific calendar month') }}
                                </option>
                                <option value="custom" @if($periodPreset === 'custom') selected @endif>
                                    {{ __('Custom dates') }}
                                </option>
                            </select>
                            <p class="small text-secondary mb-0 mt-1">{{ __('Default period hint') }}</p>
                        </div>
                        <div data-sr-period-month @if($periodPreset !== 'calendar_month') hidden @endif>
                            <label class="form-label" for="srPeriodMonth">{{ __('Report month') }}</label>
                            <input type="month" class="form-control" id="srPeriodMonth" name="default_period_month"
                                   value="{{ old('default_period_month', $settings['default_period_month'] ?? '') }}">
                        </div>
                        <div class="cabinet-sr-period__dates" data-sr-period-custom @if($periodPreset !== 'custom') hidden @endif>
                            <div>
                                <label class="form-label" for="srPeriodFrom">{{ __('Date from') }}</label>
                                <input type="date" class="form-control" id="srPeriodFrom" name="default_period_from"
                                       value="{{ old('default_period_from', $settings['default_period_from'] ?? '') }}">
                            </div>
                            <div>
                                <label class="form-label" for="srPeriodTo">{{ __('Date to') }}</label>
                                <input type="date" class="form-control" id="srPeriodTo" name="default_period_to"
                                       value="{{ old('default_period_to', $settings['default_period_to'] ?? '') }}">
                            </div>
                        </div>
                    </div>

                    <div class="cabinet-sr-period__compare">
                        <label class="cabinet-sr-toggle-row mb-2">
                            <input type="checkbox" name="auto_compare" value="1" data-sr-auto-compare
                                @if($autoCompare) checked @endif>
                            <span>{{ __('Compare with another period') }}</span>
                        </label>
                        <div data-sr-compare-fields @if(!$autoCompare) hidden @endif>
                            <div class="cabinet-sr-period__grid">
                                <div>
                                    <label class="form-label" for="srCompareMode">{{ __('Compare mode') }}</label>
                                    <select class="form-select" id="srCompareMode" name="compare_mode" data-sr-compare-mode>
                                        <option value="previous_period" @if($compareMode === 'previous_period') selected @endif>
                                            {{ __('Compare previous equal period') }}
                                        </option>
                                        <option value="previous_calendar_month" @if($compareMode === 'previous_calendar_month') selected @endif>
                                            {{ __('Compare previous calendar month') }}
                                        </option>
                                        <option value="same_month_last_year" @if($compareMode === 'same_month_last_year') selected @endif>
                                            {{ __('Compare same month last year') }}
                                        </option>
                                        <option value="calendar_month" @if($compareMode === 'calendar_month') selected @endif>
                                            {{ __('Compare specific calendar month') }}
                                        </option>
                                        <option value="custom" @if($compareMode === 'custom') selected @endif>
                                            {{ __('Compare custom dates') }}
                                        </option>
                                    </select>
                                    <p class="small text-secondary mb-0 mt-1">{{ __('Compare mode hint') }}</p>
                                </div>
                                <div data-sr-compare-month @if($compareMode !== 'calendar_month') hidden @endif>
                                    <label class="form-label" for="srCompareMonth">{{ __('Compare month') }}</label>
                                    <input type="month" class="form-control" id="srCompareMonth" name="compare_month"
                                           value="{{ old('compare_month', $settings['compare_month'] ?? '') }}">
                                </div>
                                <div class="cabinet-sr-period__dates" data-sr-compare-custom @if($compareMode !== 'custom') hidden @endif>
                                    <div>
                                        <label class="form-label" for="srCompareFrom">{{ __('Compare from') }}</label>
                                        <input type="date" class="form-control" id="srCompareFrom" name="default_compare_from"
                                               value="{{ old('default_compare_from', $settings['default_compare_from'] ?? '') }}">
                                    </div>
                                    <div>
                                        <label class="form-label" for="srCompareTo">{{ __('Compare to') }}</label>
                                        <input type="date" class="form-control" id="srCompareTo" name="default_compare_to"
                                               value="{{ old('default_compare_to', $settings['default_compare_to'] ?? '') }}">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="cabinet-sr-tpl-basics__flags">
                    <label class="cabinet-sr-toggle-row mb-0">
                        <input type="checkbox" name="is_default" value="1"
                            @if(old('is_default', $template->is_default)) checked @endif>
                        <span>{{ __('Default template for new projects') }}</span>
                    </label>
                </div>
            </section>

            <section class="cabinet-sr-builder" data-sr-builder data-sr-defaults='@json($builderDefaults)'>
                <div class="cabinet-sr-builder__col cabinet-sr-builder__col--list">
                    <div class="cabinet-sr-builder__head">
                        <div class="cabinet-sr-builder__head-top">
                            <h2 class="cabinet-sr-builder__title">
                                {{ __('Report blocks') }}
                                <span class="cabinet-sr-builder__count" data-sr-selected-count>{{ count($selectedKeys) }}</span>
                            </h2>
                            <button type="button"
                                    class="btn btn-outline-secondary btn-sm cabinet-sr-builder__reset"
                                    data-sr-reset-defaults
                                    title="{{ __('Reset blocks defaults hint') }}">
                                {{ __('Reset blocks defaults') }}
                            </button>
                        </div>
                        <div class="cabinet-sr-builder__toolbar">
                            <div class="cabinet-sr-builder__filters" data-sr-builder-filters>
                                <button type="button" class="is-active" data-sr-filter="all">{{ __('Blocks filter all') }}</button>
                                <button type="button" data-sr-filter="on">{{ __('Blocks filter on') }}</button>
                                <button type="button" data-sr-filter="off">{{ __('Blocks filter off') }}</button>
                            </div>
                            <input type="search" class="form-control form-control-sm cabinet-sr-builder__search" data-sr-builder-search
                                   placeholder="{{ __('Search blocks and metrics') }}" autocomplete="off">
                        </div>
                        <p class="cabinet-sr-builder__hint">{{ __('Report blocks hint') }}</p>
                    </div>
                    <div class="cabinet-sr-builder__scroll" data-sr-list>
                        <div class="cabinet-sr-builder__zone" data-sr-zone-on>
                            <div class="cabinet-sr-builder__zone-title">{{ __('Blocks on') }}</div>
                            @foreach($selectedKeys as $key)
                                @include('pages.partials.seo-reports-template-block-row', [
                                    'key' => $key,
                                    'meta' => $sectionCatalog[$key],
                                    'enabled' => true,
                                    'metricCatalog' => $metricCatalog,
                                    'metricToggles' => $metricToggles,
                                    'groupLabels' => $groupLabels,
                                    'settings' => $settings,
                                ])
                            @endforeach
                            <p class="cabinet-sr-builder__empty" data-sr-on-empty @if(count($selectedKeys) > 0) hidden @endif>
                                {{ __('No blocks enabled yet') }}
                            </p>
                        </div>
                        <div class="cabinet-sr-builder__zone cabinet-sr-builder__zone--off" data-sr-zone-off>
                            <div class="cabinet-sr-builder__zone-title">{{ __('Blocks off') }}</div>
                            @foreach($offKeys as $key)
                                @include('pages.partials.seo-reports-template-block-row', [
                                    'key' => $key,
                                    'meta' => $sectionCatalog[$key],
                                    'enabled' => false,
                                    'metricCatalog' => $metricCatalog,
                                    'metricToggles' => $metricToggles,
                                    'groupLabels' => $groupLabels,
                                    'settings' => $settings,
                                ])
                            @endforeach
                            <p class="cabinet-sr-builder__empty" data-sr-off-empty @if(count($offKeys) > 0) hidden @endif>
                                {{ __('All blocks already in report') }}
                            </p>
                        </div>
                    </div>
                </div>

                <aside class="cabinet-sr-builder__preview" data-sr-builder-preview>
                    <div class="cabinet-sr-builder__preview-card" style="--sr-accent: {{ $brandPreview }};">
                        <div class="cabinet-sr-builder__cover" data-sr-cover-preview>
                            <div class="cabinet-sr-builder__cover-accent"></div>
                            <div class="cabinet-sr-builder__cover-brand">
                                @if($template->agencyLogoUrl())
                                    <img class="cabinet-sr-builder__cover-logo" src="{{ $template->agencyLogoUrl() }}" alt="">
                                @endif
                                <div class="cabinet-sr-builder__cover-agency" data-sr-cover-agency>
                                    {{ old('agency_name', $template->agency_name) ?: __('Your agency') }}
                                </div>
                            </div>
                            <div class="cabinet-sr-builder__cover-title" data-sr-cover-title>
                                {{ old('title', $template->title) }}
                            </div>
                            <div class="cabinet-sr-builder__cover-meta">{{ __('Live cover preview') }}</div>
                        </div>
                        <div class="cabinet-sr-builder__outline-label">
                            <span class="cabinet-sr-builder__outline-label-text">{{ __('Report outline') }}</span>
                            <span class="cabinet-sr-builder__outline-count" data-sr-outline-count>{{ count($selectedKeys) }}</span>
                        </div>
                        <ol class="cabinet-sr-builder__outline" data-sr-outline>
                            @foreach($selectedKeys as $key)
                                <li data-key="{{ $key }}">{{ $sectionCatalog[$key]['title'] ?? $key }}</li>
                            @endforeach
                        </ol>
                        <p class="cabinet-sr-builder__preview-note mb-0">{{ __('Template preview note') }}</p>
                    </div>
                </aside>
            </section>

            <details class="cabinet-sr-tpl-panel" open>
                <summary>{{ __('KPI goals') }}</summary>
                <div class="cabinet-sr-tpl-panel__body">
                    <p class="small text-secondary mb-2">{{ __('SEO report kpi settings hint') }}</p>
                    <div class="cabinet-sr-kpi-grid">
                        @foreach(($kpiGoals ?? []) as $goal)
                            @php $hint = $kpiHints[$goal['type']] ?? null; @endphp
                            <div class="cabinet-sr-kpi-card">
                                <label class="cabinet-sr-toggle-row mb-2">
                                    <input type="checkbox"
                                           name="kpi_goals[{{ $goal['type'] }}][enabled]"
                                           value="1"
                                        @if(!empty($goal['enabled'])) checked @endif>
                                    <span><strong>{{ $goal['label'] }}</strong></span>
                                </label>
                                <input type="number" min="0" step="1" class="form-control form-control-sm"
                                       name="kpi_goals[{{ $goal['type'] }}][target]"
                                       value="{{ $goal['target'] > 0 ? (int) $goal['target'] : '' }}"
                                       placeholder="{{ $hint['placeholder'] ?? __('Monthly target') }}">
                                @if($hint)
                                    <div class="form-text">{{ $hint['hint'] }} · {{ $hint['unit'] }}</div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </details>

            <div class="cabinet-sr-settings-grid mt-3">
                <details class="cabinet-sr-tpl-panel" open>
                    <summary>{{ __('Agency branding') }}</summary>
                    <div class="cabinet-sr-tpl-panel__body">
                        <div class="mb-2">
                            <label class="form-label">{{ __('Agency name') }}</label>
                            <input type="text" class="form-control form-control-sm" name="agency_name"
                                   value="{{ old('agency_name', $template->agency_name) }}"
                                   data-sr-agency-name>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">{{ __('Address') }}</label>
                            <input type="text" class="form-control form-control-sm" name="agency_address"
                                   value="{{ old('agency_address', $template->agency_address) }}">
                        </div>
                        <div class="mb-2">
                            <label class="form-label">{{ __('Email') }}</label>
                            <input type="email" class="form-control form-control-sm" name="agency_email"
                                   value="{{ old('agency_email', $template->agency_email) }}"
                                   data-sr-email-latin
                                   autocomplete="email"
                                   inputmode="email">
                        </div>
                        <div class="mb-2">
                            <label class="form-label">{{ __('Phone') }}</label>
                            <input type="text" class="form-control form-control-sm" name="agency_phone"
                                   value="{{ old('agency_phone', $template->agency_phone) }}">
                        </div>
                        <div class="mb-2">
                            <label class="form-label">{{ __('Brand color') }}</label>
                            <div class="cabinet-sr-color-row">
                                <input type="color" class="cabinet-sr-color-row__swatch" value="{{ $brandPreview }}"
                                       data-sr-brand-swatch aria-label="{{ __('Brand color') }}">
                                <input type="text" class="form-control form-control-sm" name="brand_color"
                                       placeholder="#1d4ed8"
                                       value="{{ old('brand_color', $template->brand_color) }}"
                                       data-sr-brand-color>
                            </div>
                        </div>
                        <label class="cabinet-sr-toggle-row">
                            <input type="checkbox" name="public_dark_theme" value="1"
                                @if(!empty($settings['public_dark_theme'])) checked @endif>
                            <span>{{ __('Dark theme for public link') }}</span>
                        </label>
                        <div class="mb-0 mt-2">
                            <label class="form-label">{{ __('Logo') }}</label>
                            @if($template->agencyLogoUrl())
                                <div class="mb-2">
                                    <img src="{{ $template->agencyLogoUrl() }}" alt="" style="max-height:40px">
                                </div>
                                <label class="cabinet-sr-toggle-row mb-2">
                                    <input type="checkbox" name="clear_agency_logo" value="1">
                                    <span>{{ __('Remove logo') }}</span>
                                </label>
                            @endif
                            <input type="file" class="form-control form-control-sm" name="agency_logo" accept="image/*">
                        </div>
                    </div>
                </details>

                <details class="cabinet-sr-tpl-panel" open>
                    <summary>{{ __('Your manager') }}</summary>
                    <div class="cabinet-sr-tpl-panel__body">
                        <div class="mb-2">
                            <label class="form-label">{{ __('Name') }}</label>
                            <input type="text" class="form-control form-control-sm" name="manager_name"
                                   value="{{ old('manager_name', $template->manager_name) }}">
                        </div>
                        <div class="mb-2">
                            <label class="form-label">{{ __('Phone') }}</label>
                            <input type="text" class="form-control form-control-sm" name="manager_phone"
                                   value="{{ old('manager_phone', $template->manager_phone) }}">
                        </div>
                        <div class="mb-2">
                            <label class="form-label">{{ __('Email') }}</label>
                            <input type="email" class="form-control form-control-sm" name="manager_email"
                                   value="{{ old('manager_email', $template->manager_email) }}"
                                   data-sr-email-latin
                                   autocomplete="email"
                                   inputmode="email">
                        </div>
                        <div class="mb-0">
                            <label class="form-label">{{ __('Avatar') }}</label>
                            @if($template->managerAvatarUrl())
                                <div class="mb-2">
                                    <img src="{{ $template->managerAvatarUrl() }}" alt="" class="cabinet-sr-cover__avatar">
                                </div>
                                <label class="cabinet-sr-toggle-row mb-2">
                                    <input type="checkbox" name="clear_manager_avatar" value="1">
                                    <span>{{ __('Remove avatar') }}</span>
                                </label>
                            @endif
                            <input type="file" class="form-control form-control-sm" name="manager_avatar" accept="image/*">
                        </div>
                    </div>
                </details>
            </div>

            <details class="cabinet-sr-tpl-panel mt-3">
                <summary>{{ __('Automation') }}</summary>
                <div class="cabinet-sr-tpl-panel__body cabinet-sr-tpl-panel__body--row">
                    <label class="cabinet-sr-toggle-row">
                        <input type="checkbox" name="auto_generate" value="1"
                            @if(!empty($settings['auto_generate'])) checked @endif>
                        <span>{{ __('Auto-generate monthly report') }}</span>
                    </label>
                    <label class="cabinet-sr-toggle-row">
                        <input type="checkbox" name="remind_missing" value="1"
                            @if(!empty($settings['remind_missing'])) checked @endif>
                        <span>{{ __('Remind if monthly report missing') }}</span>
                    </label>
                    <label class="cabinet-sr-toggle-row">
                        <input type="checkbox" name="confirmed_sources_only" value="1"
                            @if(!empty($settings['confirmed_sources_only'])) checked @endif>
                        <span>{{ __('Confirmed sources only') }}</span>
                    </label>
                    <label class="cabinet-sr-toggle-row">
                        <input type="checkbox" name="enable_ai_summary" value="1"
                            @if(!empty($settings['enable_ai_summary'])) checked @endif>
                        <span>{{ __('Enable AI summary') }}</span>
                    </label>
                </div>
            </details>

            <div class="cabinet-sr-tpl-sticky">
                <div class="cabinet-sr-tpl-sticky__meta">
                    <span data-sr-sticky-count>{{ count($selectedKeys) }}</span> {{ __('blocks in report') }}
                    @if(($projectsCount ?? 0) > 0)
                        · {{ __('Applies to :count projects', ['count' => (int) $projectsCount]) }}
                    @endif
                </div>
                <div class="cabinet-sr-tpl-sticky__actions">
                    <a class="btn btn-outline-secondary btn-sm" href="{{ route('pages.seo-reports.templates') }}">{{ __('Cancel') }}</a>
                    <button type="submit" class="btn btn-primary btn-sm">{{ __('Save template') }}</button>
                </div>
            </div>
        </form>
    </div>

    @slot('js')
        <script>
            (function () {
                if (window.bootstrap && bootstrap.Tooltip) {
                    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
                        new bootstrap.Tooltip(el);
                    });
                }

                (function bindLatinEmailHints() {
                    var msgLatin = @json(__('Email: Latin characters only'));
                    var msgFormat = @json(__('Enter a valid email'));
                    document.querySelectorAll('[data-sr-email-latin]').forEach(function (input) {
                        function refreshValidity() {
                            input.setCustomValidity('');
                            if (!input.value) {
                                return;
                            }
                            if (/[^\x00-\x7F]/.test(input.value)) {
                                input.setCustomValidity(msgLatin);
                                return;
                            }
                            if (input.validity.typeMismatch) {
                                input.setCustomValidity(msgFormat);
                            }
                        }
                        input.addEventListener('input', refreshValidity);
                        input.addEventListener('invalid', refreshValidity);
                        refreshValidity();
                    });
                })();

                var periodBox = document.querySelector('[data-sr-period-settings]');
                if (periodBox) {
                    var periodPreset = periodBox.querySelector('[data-sr-period-preset]');
                    var periodMonth = periodBox.querySelector('[data-sr-period-month]');
                    var periodCustom = periodBox.querySelector('[data-sr-period-custom]');
                    var autoCompare = periodBox.querySelector('[data-sr-auto-compare]');
                    var compareFields = periodBox.querySelector('[data-sr-compare-fields]');
                    var compareMode = periodBox.querySelector('[data-sr-compare-mode]');
                    var compareMonth = periodBox.querySelector('[data-sr-compare-month]');
                    var compareCustom = periodBox.querySelector('[data-sr-compare-custom]');

                    function syncPeriodUi() {
                        var p = periodPreset ? periodPreset.value : 'prev_month';
                        if (periodMonth) periodMonth.hidden = p !== 'calendar_month';
                        if (periodCustom) periodCustom.hidden = p !== 'custom';
                        var on = !autoCompare || autoCompare.checked;
                        if (compareFields) compareFields.hidden = !on;
                        var m = compareMode ? compareMode.value : 'previous_period';
                        if (compareMonth) compareMonth.hidden = !on || m !== 'calendar_month';
                        if (compareCustom) compareCustom.hidden = !on || m !== 'custom';
                    }
                    if (periodPreset) periodPreset.addEventListener('change', syncPeriodUi);
                    if (autoCompare) autoCompare.addEventListener('change', syncPeriodUi);
                    if (compareMode) compareMode.addEventListener('change', syncPeriodUi);
                    syncPeriodUi();
                }

                var builder = document.querySelector('[data-sr-builder]');
                if (!builder) return;

                var list = builder.querySelector('[data-sr-list]');
                var zoneOn = builder.querySelector('[data-sr-zone-on]');
                var zoneOff = builder.querySelector('[data-sr-zone-off]');
                var onEmpty = builder.querySelector('[data-sr-on-empty]');
                var offEmpty = builder.querySelector('[data-sr-off-empty]');
                var search = builder.querySelector('[data-sr-builder-search]');
                var filters = builder.querySelector('[data-sr-builder-filters]');
                var outline = builder.querySelector('[data-sr-outline]');
                var outlineCount = builder.querySelector('[data-sr-outline-count]');
                var countEls = document.querySelectorAll('[data-sr-selected-count], [data-sr-sticky-count]');
                var filterMode = 'all';
                var dragEl = null;
                var defaults = {};
                try {
                    defaults = JSON.parse(builder.getAttribute('data-sr-defaults') || '{}') || {};
                } catch (e) {
                    defaults = {};
                }
                var defaultOrder = Array.isArray(defaults.order) ? defaults.order : [];
                var defaultToggles = defaults.toggles && typeof defaults.toggles === 'object' ? defaults.toggles : {};
                var defaultMetrics = defaults.metrics && typeof defaults.metrics === 'object' ? defaults.metrics : {};

                function focusOutlineKey(key) {
                    if (!outline || !key) return;
                    var li = outline.querySelector('[data-key="' + key.replace(/"/g, '\\"') + '"]');
                    if (!li) {
                        // Just scrolled off / removed — keep list readable at bottom when disabling last items
                        outline.scrollTop = outline.scrollHeight;
                        return;
                    }
                    if (typeof li.scrollIntoView === 'function') {
                        li.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                    }
                    li.classList.remove('is-flash');
                    // restart animation
                    void li.offsetWidth;
                    li.classList.add('is-flash');
                    window.setTimeout(function () { li.classList.remove('is-flash'); }, 900);
                }

                function rows() {
                    return list.querySelectorAll('[data-sr-row]');
                }

                function syncMetricCounts() {
                    list.querySelectorAll('[data-sr-metrics]').forEach(function (box) {
                        var cbs = box.querySelectorAll('[data-sr-metric-cb]');
                        var on = 0;
                        cbs.forEach(function (cb) { if (cb.checked) on += 1; });
                        var el = box.querySelector('[data-sr-metrics-count]');
                        if (el) el.textContent = on + '/' + cbs.length;
                    });
                }

                function applyMetricFilter(box, query) {
                    if (!box) return 0;
                    var q = (query || '').toLowerCase().trim();
                    var visible = 0;
                    box.querySelectorAll('[data-sr-metric]').forEach(function (metric) {
                        var label = (metric.getAttribute('data-label') || '').toLowerCase();
                        var ok = !q || label.indexOf(q) !== -1;
                        metric.hidden = !ok;
                        if (ok) visible += 1;
                    });
                    var empty = box.querySelector('[data-sr-metrics-empty]');
                    if (empty) empty.hidden = visible > 0;
                    return visible;
                }

                function countMetricHits(box, query) {
                    if (!box || !query) return 0;
                    var hits = 0;
                    box.querySelectorAll('[data-sr-metric]').forEach(function (metric) {
                        var label = (metric.getAttribute('data-label') || '').toLowerCase();
                        if (label.indexOf(query) !== -1) hits += 1;
                    });
                    return hits;
                }

                function applyFilter() {
                    var q = ((search && search.value) || '').toLowerCase().trim();
                    rows().forEach(function (row) {
                        var on = row.getAttribute('data-enabled') === '1';
                        var title = (row.getAttribute('data-title') || '').toLowerCase();
                        var hint = (row.getAttribute('data-hint') || '').toLowerCase();
                        var source = (row.getAttribute('data-source-label') || '').toLowerCase();
                        var metricsBox = row.querySelector('[data-sr-metrics]');
                        var localSearch = metricsBox && metricsBox.querySelector('[data-sr-metrics-search]');
                        var qLocal = ((localSearch && localSearch.value) || '').toLowerCase().trim();
                        var metricHits = countMetricHits(metricsBox, q);
                        var matchBlock = !q || title.indexOf(q) !== -1 || hint.indexOf(q) !== -1 || source.indexOf(q) !== -1;
                        var matchQ = matchBlock || metricHits > 0;
                        var matchF = filterMode === 'all' || (filterMode === 'on' && on) || (filterMode === 'off' && !on);
                        row.hidden = !(matchQ && matchF);

                        // Local search wins; else filter metrics by global q only when block matched via metrics
                        var listQuery = qLocal || (!matchBlock && q ? q : '');
                        applyMetricFilter(metricsBox, listQuery);
                        if (metricsBox && ((qLocal && listQuery) || (!matchBlock && metricHits > 0))) {
                            metricsBox.open = true;
                        }
                    });
                    if (zoneOn) zoneOn.hidden = filterMode === 'off';
                    if (zoneOff) zoneOff.hidden = filterMode === 'on';
                }

                function syncUi(focusKey) {
                    var onRows = zoneOn.querySelectorAll('[data-sr-row][data-enabled="1"]');
                    var offRows = zoneOff.querySelectorAll('[data-sr-row][data-enabled="0"]');
                    countEls.forEach(function (el) { el.textContent = String(onRows.length); });
                    if (outlineCount) outlineCount.textContent = String(onRows.length);
                    if (onEmpty) onEmpty.hidden = onRows.length > 0;
                    if (offEmpty) offEmpty.hidden = offRows.length > 0;
                    if (outline) {
                        outline.innerHTML = '';
                        onRows.forEach(function (item) {
                            var li = document.createElement('li');
                            var k = item.getAttribute('data-key') || '';
                            if (k) li.setAttribute('data-key', k);
                            li.textContent = item.getAttribute('data-title') || '';
                            outline.appendChild(li);
                        });
                    }
                    syncMetricCounts();
                    applyFilter();
                    if (focusKey) focusOutlineKey(focusKey);
                }

                function applyRowEnabled(row, enabled, append) {
                    if (!row) return;
                    var key = row.getAttribute('data-key');
                    var val = row.querySelector('[data-sr-section-val]');
                    var order = row.querySelector('[data-sr-order]');
                    var drag = row.querySelector('.cabinet-sr-builder__drag');
                    var metrics = row.querySelector('[data-sr-metrics]');
                    var toggle = row.querySelector('[data-sr-toggle]');

                    row.setAttribute('data-enabled', enabled ? '1' : '0');
                    row.classList.toggle('is-on', enabled);
                    row.classList.toggle('is-off', !enabled);
                    row.draggable = !!enabled;
                    if (val) val.value = enabled ? '1' : '0';
                    if (toggle) toggle.checked = !!enabled;
                    if (drag) drag.hidden = !enabled;
                    if (metrics) metrics.hidden = !enabled;

                    if (enabled) {
                        if (!order) {
                            order = document.createElement('input');
                            order.type = 'hidden';
                            order.name = 'section_order[]';
                            order.value = key;
                            order.setAttribute('data-sr-order', '');
                            row.insertBefore(order, row.querySelector('.cabinet-sr-builder__block-body'));
                        } else {
                            order.value = key;
                        }
                        if (append !== false) {
                            if (onEmpty) zoneOn.insertBefore(row, onEmpty);
                            else zoneOn.appendChild(row);
                        }
                        bindDrag(row);
                    } else {
                        if (order) order.parentNode.removeChild(order);
                        if (append !== false) {
                            if (offEmpty) zoneOff.insertBefore(row, offEmpty);
                            else zoneOff.appendChild(row);
                        }
                    }
                }

                function setEnabled(row, enabled) {
                    applyRowEnabled(row, enabled, true);
                    syncUi(row ? row.getAttribute('data-key') : null);
                }

                function resetToDefaults() {
                    if (defaultOrder.length === 0) return;
                    if (!window.confirm(@json(__('Reset blocks defaults confirm')))) return;

                    // 1) Показатели — как в базовом шаблоне (все вкл.)
                    list.querySelectorAll('[data-sr-metric-cb]').forEach(function (cb) {
                        var name = cb.getAttribute('name') || '';
                        var secMatch = name.match(/metric_toggles\[([^\]]+)\]\[([^\]]+)\]/);
                        if (!secMatch) {
                            cb.checked = true;
                            return;
                        }
                        var sec = secMatch[1];
                        var key = secMatch[2];
                        if (defaultMetrics[sec] && Object.prototype.hasOwnProperty.call(defaultMetrics[sec], key)) {
                            cb.checked = !!defaultMetrics[sec][key];
                        } else {
                            cb.checked = true;
                        }
                    });

                    // 2) Порядок и вкл/выкл блоков
                    defaultOrder.forEach(function (key) {
                        var row = list.querySelector('[data-sr-row][data-key="' + key + '"]');
                        if (!row) return;
                        var enabled = Object.prototype.hasOwnProperty.call(defaultToggles, key)
                            ? !!defaultToggles[key]
                            : false;
                        applyRowEnabled(row, enabled, true);
                    });

                    rows().forEach(function (row) {
                        var key = row.getAttribute('data-key');
                        if (defaultOrder.indexOf(key) !== -1) return;
                        applyRowEnabled(row, false, true);
                    });

                    filterMode = 'all';
                    if (filters) {
                        filters.querySelectorAll('[data-sr-filter]').forEach(function (b) {
                            b.classList.toggle('is-active', b.getAttribute('data-sr-filter') === 'all');
                        });
                    }
                    if (search) search.value = '';
                    syncUi();
                }

                function bindDrag(el) {
                    if (el._srDragBound) return;
                    el._srDragBound = true;
                    el.addEventListener('dragstart', function (e) {
                        if (el.getAttribute('data-enabled') !== '1') {
                            e.preventDefault();
                            return;
                        }
                        dragEl = el;
                        el.classList.add('is-dragging');
                        if (e.dataTransfer) {
                            e.dataTransfer.effectAllowed = 'move';
                            e.dataTransfer.setData('text/plain', el.getAttribute('data-key') || '');
                        }
                    });
                    el.addEventListener('dragend', function () {
                        el.classList.remove('is-dragging');
                        zoneOn.querySelectorAll('.is-drop-target').forEach(function (n) {
                            n.classList.remove('is-drop-target');
                        });
                        dragEl = null;
                        syncUi();
                    });
                }

                list.addEventListener('change', function (e) {
                    var t = e.target;
                    if (!t) return;
                    if (t.matches('[data-sr-toggle]')) {
                        setEnabled(t.closest('[data-sr-row]'), t.checked);
                        return;
                    }
                    if (t.matches('[data-sr-metric-cb]')) syncMetricCounts();
                });

                zoneOn.addEventListener('dragover', function (e) {
                    e.preventDefault();
                    if (!dragEl) return;
                    var over = e.target.closest('[data-sr-row][data-enabled="1"]');
                    zoneOn.querySelectorAll('.is-drop-target').forEach(function (n) {
                        n.classList.remove('is-drop-target');
                    });
                    if (over && over !== dragEl) {
                        over.classList.add('is-drop-target');
                        var rect = over.getBoundingClientRect();
                        var before = (e.clientY - rect.top) < rect.height / 2;
                        if (before) zoneOn.insertBefore(dragEl, over);
                        else zoneOn.insertBefore(dragEl, over.nextSibling);
                    }
                });

                zoneOn.addEventListener('drop', function (e) {
                    e.preventDefault();
                    syncUi();
                });

                if (search) search.addEventListener('input', applyFilter);

                if (filters) {
                    filters.addEventListener('click', function (e) {
                        var btn = e.target.closest('[data-sr-filter]');
                        if (!btn) return;
                        filterMode = btn.getAttribute('data-sr-filter') || 'all';
                        filters.querySelectorAll('[data-sr-filter]').forEach(function (b) {
                            b.classList.toggle('is-active', b === btn);
                        });
                        applyFilter();
                    });
                }

                var resetBtn = builder.querySelector('[data-sr-reset-defaults]');
                if (resetBtn) resetBtn.addEventListener('click', resetToDefaults);

                list.addEventListener('input', function (e) {
                    if (e.target && e.target.matches('[data-sr-metrics-search]')) {
                        e.stopPropagation();
                        applyFilter();
                    }
                });

                list.querySelectorAll('[data-sr-row][data-enabled="1"]').forEach(bindDrag);
                list.querySelectorAll('[data-sr-metrics]').forEach(function (box) {
                    box.addEventListener('click', function (e) {
                        if (e.target && e.target.closest('[data-sr-metrics-search]')) {
                            e.stopPropagation();
                            return;
                        }
                        e.stopPropagation();
                    });
                });

                var titleInput = document.querySelector('[data-sr-tpl-title]');
                var agencyInput = document.querySelector('[data-sr-agency-name]');
                var coverTitle = document.querySelector('[data-sr-cover-title]');
                var coverAgency = document.querySelector('[data-sr-cover-agency]');
                var brandColor = document.querySelector('[data-sr-brand-color]');
                var brandSwatch = document.querySelector('[data-sr-brand-swatch]');
                var previewCard = document.querySelector('[data-sr-builder-preview] .cabinet-sr-builder__preview-card');

                function syncCover() {
                    if (coverTitle && titleInput) coverTitle.textContent = titleInput.value || '—';
                    if (coverAgency && agencyInput) {
                        coverAgency.textContent = agencyInput.value || @json(__('Your agency'));
                    }
                }
                if (titleInput) titleInput.addEventListener('input', syncCover);
                if (agencyInput) agencyInput.addEventListener('input', syncCover);

                function syncBrand(val) {
                    var v = (val || '').trim();
                    if (!/^#?[0-9a-fA-F]{6}$/.test(v)) return;
                    if (v.charAt(0) !== '#') v = '#' + v;
                    if (previewCard) previewCard.style.setProperty('--sr-accent', v);
                    if (brandSwatch && brandSwatch.value.toLowerCase() !== v.toLowerCase()) brandSwatch.value = v;
                    if (brandColor && brandColor.value !== v) brandColor.value = v;
                }
                if (brandColor) brandColor.addEventListener('input', function () { syncBrand(brandColor.value); });
                if (brandSwatch) brandSwatch.addEventListener('input', function () { syncBrand(brandSwatch.value); });

                (function bindTrafficScope() {
                    var modeSelect = document.querySelector('[data-sr-traffic-mode]');
                    var scope = document.querySelector('[data-sr-traffic-scope]');
                    if (!scope) return;
                    var boxes = scope.querySelectorAll('[data-sr-traffic-ch]');
                    var allIds = [];
                    boxes.forEach(function (cb) { allIds.push(cb.value); });

                    function selectedIds() {
                        var out = [];
                        boxes.forEach(function (cb) { if (cb.checked) out.push(cb.value); });
                        return out.sort();
                    }

                    function setChecked(ids) {
                        var map = {};
                        (ids || []).forEach(function (id) { map[id] = true; });
                        boxes.forEach(function (cb) { cb.checked = !!map[cb.value]; });
                    }

                    function syncModeFromChecks() {
                        if (!modeSelect) return;
                        var ids = selectedIds();
                        var isAll = ids.length === allIds.length;
                        var isSearch = ids.length === 1 && ids[0] === 'organic';
                        modeSelect.value = isAll ? 'all' : (isSearch ? 'search_only' : 'custom');
                    }

                    function applyMode(mode) {
                        if (mode === 'search_only') setChecked(['organic']);
                        else if (mode === 'all') setChecked(allIds);
                        else if (mode === 'organic_ad') setChecked(['organic', 'ad']);
                        syncModeFromChecks();
                    }

                    if (modeSelect) {
                        modeSelect.addEventListener('change', function () {
                            applyMode(modeSelect.value);
                            if (modeSelect.value === 'custom') {
                                var trafficRow = list.querySelector('[data-sr-row][data-key="traffic"]');
                                var details = trafficRow && trafficRow.querySelector('[data-sr-metrics]');
                                if (details && !details.open) details.open = true;
                            }
                        });
                    }

                    scope.querySelectorAll('[data-sr-traffic-preset]').forEach(function (btn) {
                        btn.addEventListener('click', function () {
                            applyMode(btn.getAttribute('data-sr-traffic-preset') || 'search_only');
                        });
                    });

                    boxes.forEach(function (cb) {
                        cb.addEventListener('change', function () {
                            if (selectedIds().length === 0) {
                                cb.checked = true;
                            }
                            syncModeFromChecks();
                        });
                    });
                })();

                // Подсказки «как будет в отчёте» у показателей — клик/тап закрепляет
                document.querySelectorAll('[data-sr-metric-tip]').forEach(function (tip) {
                    var btn = tip.querySelector('.cabinet-sr-metric-tip__btn');
                    if (!btn) return;
                    btn.addEventListener('click', function (e) {
                        e.preventDefault();
                        e.stopPropagation();
                        var open = tip.classList.contains('is-open');
                        document.querySelectorAll('[data-sr-metric-tip].is-open').forEach(function (el) {
                            el.classList.remove('is-open');
                        });
                        if (!open) tip.classList.add('is-open');
                    });
                });
                document.addEventListener('click', function (e) {
                    if (e.target && e.target.closest && e.target.closest('[data-sr-metric-tip]')) return;
                    document.querySelectorAll('[data-sr-metric-tip].is-open').forEach(function (el) {
                        el.classList.remove('is-open');
                    });
                });

                syncUi();
            })();
        </script>
    @endslot
@endcomponent
