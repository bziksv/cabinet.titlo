@component('component.card', [
    'title' => __('Reports'),
    'titleHtml' => e(__('Reports')) . view('partials.cabinet-module-version-badge', ['configKey' => 'cabinet-seo-reports'])->render(),
    'documentTitle' => __('Templates') . ' · ' . __('Reports'),
])
    @slot('css')
        <link rel="stylesheet" href="{{ asset('css/cabinet-seo-reports.css') }}?v={{ @filemtime(public_path('css/cabinet-seo-reports.css')) ?: time() }}">
    @endslot

    <div class="cabinet-sr-page">
        @include('pages.partials.seo-reports-nav', [
            'srTab' => 'templates',
            'srProjectsCount' => $projectsCount ?? null,
        ])

        @if(session('success'))
            <div class="alert alert-success py-2 px-3 small">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger py-2 px-3 small">{{ session('error') }}</div>
        @endif

        <div class="cabinet-sr-hero">
            <div>
                <h1 class="cabinet-sr-hero__title">{{ __('Report templates') }}</h1>
                <p class="cabinet-sr-hero__lead">{{ __('Report templates default workflow lead') }}</p>
            </div>
        </div>

        @php
            $defaultTemplate = $templates->firstWhere('is_default', true) ?: $templates->first();
        @endphp
        @if($defaultTemplate)
            <div class="cabinet-sr-fieldset mb-3">
                <legend class="mb-2">{{ __('Default template') }}</legend>
                <p class="small text-secondary mb-2">{{ __('Default template workflow hint') }}</p>
                <div class="cabinet-sr-toolbar flex-wrap mb-0">
                    <a class="btn btn-primary btn-sm"
                       href="{{ route('pages.seo-reports.templates.edit', ['id' => $defaultTemplate->id]) }}">
                        {{ __('Edit default template') }}
                    </a>
                    <form method="post" action="{{ route('pages.seo-reports.templates.duplicate', ['id' => $defaultTemplate->id]) }}" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-outline-primary btn-sm">{{ __('Copy default template') }}</button>
                    </form>
                </div>
            </div>
        @endif

        <details class="cabinet-sr-tpl-panel mb-3">
            <summary>{{ __('Create extra template from preset') }}</summary>
            <div class="cabinet-sr-tpl-panel__body">
                <p class="small text-secondary mb-2">{{ __('Create extra template hint') }}</p>
                <form method="post" action="{{ route('pages.seo-reports.templates.store') }}" class="cabinet-sr-toolbar flex-wrap mb-0">
                    @csrf
                    <input type="text" name="title" class="form-control form-control-sm" style="max-width:14rem"
                           placeholder="{{ __('Template name') }}" maxlength="80">
                    <select name="preset" class="form-select form-select-sm" style="max-width:12rem">
                        @foreach($presets as $preset)
                            <option value="{{ $preset['key'] }}" @if($preset['key'] === 'complex') selected @endif>
                                {{ $preset['title'] }}
                            </option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn btn-outline-secondary btn-sm">{{ __('Create template') }}</button>
                </form>
            </div>
        </details>

        @if($templates->isEmpty())
            <div class="cabinet-sr-empty">
                <p class="fw-semibold mb-1">{{ __('No report templates yet') }}</p>
                <p class="small mb-0">{{ __('No report templates yet hint') }}</p>
            </div>
        @else
            <div class="cabinet-sr-template-grid">
                @foreach($templates as $tpl)
                    @php
                        $toggles = $tpl->resolvedSectionToggles();
                        $enabledTitles = [];
                        foreach (\App\SeoReports\SeoReportSectionRegistry::orderedKeys($tpl->reportSettings()) as $key) {
                            if ($key === 'cover' || empty($toggles[$key])) {
                                continue;
                            }
                            $enabledTitles[] = \App\SeoReports\SeoReportSectionRegistry::title($key);
                        }
                        $brand = \App\SeoReports\SeoReportBrandColor::normalize($tpl->brand_color ?: '#1d4ed8');
                        $desc = trim((string) (($tpl->reportSettings()['description'] ?? '') ?: ''));
                    @endphp
                    <article class="cabinet-sr-template-card">
                        <h2 class="cabinet-sr-template-card__title">
                            <span class="cabinet-sr-template-card__swatch" style="background: {{ $brand }};"></span>
                            {{ $tpl->title }}
                            @if($tpl->is_default)
                                <span class="cabinet-sr-badge cabinet-sr-badge--ok">{{ __('Default') }}</span>
                            @endif
                        </h2>
                        <p class="cabinet-sr-template-card__desc">
                            {{ $desc !== '' ? $desc : __('No template description') }}
                        </p>
                        <p class="cabinet-sr-template-card__lead mb-1">
                            {{ __('Used in :count projects', ['count' => (int) $tpl->projects_count]) }}
                            · {{ count($enabledTitles) }} {{ __('sections') }}
                            · {{ $tpl->agency_name ?: __('No agency branding') }}
                            · {{ $tpl->manager_name ?: __('No manager') }}
                        </p>
                        <div class="cabinet-sr-template-card__chips">
                            @foreach(array_slice($enabledTitles, 0, 6) as $sectionTitle)
                                <span>{{ $sectionTitle }}</span>
                            @endforeach
                            @if(count($enabledTitles) > 6)
                                <span class="is-more">+{{ count($enabledTitles) - 6 }}</span>
                            @endif
                        </div>
                        <div class="cabinet-sr-template-card__actions">
                            <a class="btn btn-primary btn-sm"
                               href="{{ route('pages.seo-reports.templates.edit', ['id' => $tpl->id]) }}">
                                {{ __('Edit') }}
                            </a>
                            <form method="post" action="{{ route('pages.seo-reports.templates.duplicate', ['id' => $tpl->id]) }}" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-outline-secondary btn-sm">{{ __('Copy') }}</button>
                            </form>
                            @if((int) $tpl->projects_count === 0)
                                <form method="post" action="{{ route('pages.seo-reports.templates.destroy', ['id' => $tpl->id]) }}" class="d-inline"
                                      data-confirm="{{ __('Delete this template?') }}"
                                      onsubmit="return confirm(this.dataset.confirm);">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-danger btn-sm">{{ __('Delete') }}</button>
                                </form>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
@endcomponent
