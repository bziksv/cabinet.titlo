@php
    $activeModule = $activeModule ?? 'positions';
    $showViewTabs = $showViewTabs ?? true;
    $projectUrl = trim((string) ($project->url ?? ''));
    $projectHost = $projectUrl !== '' ? preg_replace('#^https?://#i', '', rtrim($projectUrl, '/')) : e($project->name);
    $faviconUrl = route('monitoring.v2.favicon', ['project' => $project->id]);
    $regionLabel = '';
    if (request('region')) {
        $se = $project->searchengines->firstWhere('id', (int) request('region'));
        if ($se) {
            $regionLabel = \App\Classes\Monitoring\MonitoringLocationLabel::chromeLabel($se);
        }
    }
@endphp
<header class="cabinet-mon-project-chrome">
    <nav class="cabinet-mon-project-breadcrumb" aria-label="breadcrumb">
        <a href="{{ route('monitoring.v2') }}">{{ __('Monitoring v2') }}</a>
        <span class="cabinet-mon-project-breadcrumb__sep" aria-hidden="true">/</span>
        <span aria-current="page">{{ $projectHost }}</span>
    </nav>

    <div class="cabinet-mon-project-chrome__head">
        <div class="cabinet-mon-project-chrome__identity">
            <img src="{{ $faviconUrl }}" alt="" class="cabinet-mon-project-chrome__favicon" width="40" height="40" loading="lazy">
            <div>
                <h1 class="cabinet-mon-project-chrome__title">{{ $project->name }}</h1>
                <p class="cabinet-mon-project-chrome__meta mb-0">
                    @if($projectUrl !== '')
                        <a href="{{ $projectUrl }}" target="_blank" rel="noopener noreferrer">{{ $projectHost }}</a>
                        <span class="text-secondary">·</span>
                    @endif
                    <span class="text-secondary">#{{ $project->id }}</span>
                    @if($regionLabel !== '')
                        <span class="text-secondary">·</span>
                        <span>{{ $regionLabel }}</span>
                    @endif
                </p>
            </div>
        </div>
        <div class="cabinet-mon-project-chrome__actions">
            <a href="{{ route('monitoring.v2') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>{{ __('Monitoring show back to list') }}
            </a>
            <a href="{{ url('/monitoring/' . $project->id . '/export/edit') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-download me-1" aria-hidden="true"></i>{{ __('Monitoring show export') }}
            </a>
            @include('partials.cabinet-module-version-badge', ['configKey' => 'cabinet-monitoring'])
        </div>
    </div>

    @if($showViewTabs)
        <div class="cabinet-mon-project-view-tabs" role="tablist" aria-label="{{ __('Monitoring show view modes') }}">
            <button type="button" class="cabinet-mon-project-view-tabs__btn" data-mon-view-tab="overview" role="tab" aria-selected="false">
                <i class="bi bi-bar-chart-line me-1" aria-hidden="true"></i>{{ __('Monitoring show tab overview') }}
            </button>
            <button type="button" class="cabinet-mon-project-view-tabs__btn active" data-mon-view-tab="keywords" role="tab" aria-selected="true">
                <i class="bi bi-table me-1" aria-hidden="true"></i>{{ __('Monitoring show tab keywords') }}
            </button>
        </div>
        <p class="cabinet-mon-project-view-hint text-secondary mb-0" data-mon-view-hint="overview">{{ __('Monitoring show hint overview') }}</p>
        <p class="cabinet-mon-project-view-hint text-secondary mb-0 d-none" data-mon-view-hint="keywords">{{ __('Monitoring show hint keywords') }}</p>
    @elseif(!empty($pageHint))
        <p class="cabinet-mon-project-view-hint text-secondary mb-0">{{ $pageHint }}</p>
    @endif

    <nav class="cabinet-mon-project-module-nav" aria-label="{{ __('Monitoring project shortcuts') }}">
        @if($activeModule === 'positions')
            <span class="cabinet-mon-project-module-nav__item is-active" title="{{ __('Monitoring show nav positions title') }}">
                <i class="bi bi-graph-up" aria-hidden="true"></i>{{ __('Monitoring position') }}
            </span>
        @else
            <a href="{{ url('/monitoring/' . $project->id) }}" class="cabinet-mon-project-module-nav__item" title="{{ __('Monitoring show nav positions title') }}">
                <i class="bi bi-graph-up" aria-hidden="true"></i>{{ __('Monitoring position') }}
            </a>
        @endif
        @if($activeModule === 'competitors')
            <span class="cabinet-mon-project-module-nav__item is-active">
                <i class="bi bi-people" aria-hidden="true"></i>{{ __('My competitors') }}
            </span>
        @else
            <a href="{{ route('monitoring.competitors', $project->id) }}" class="cabinet-mon-project-module-nav__item">
                <i class="bi bi-people" aria-hidden="true"></i>{{ __('My competitors') }}
            </a>
        @endif
        @if($activeModule === 'top100')
            <span class="cabinet-mon-project-module-nav__item is-active">
                <i class="bi bi-pie-chart" aria-hidden="true"></i>{{ __('TOP-100 analysis') }}
            </span>
        @else
            <a href="{{ route('monitoring.top100', $project->id) }}" class="cabinet-mon-project-module-nav__item">
                <i class="bi bi-pie-chart" aria-hidden="true"></i>{{ __('TOP-100 analysis') }}
            </a>
        @endif
        @if($activeModule === 'groups')
            <span class="cabinet-mon-project-module-nav__item is-active">
                <i class="bi bi-collection" aria-hidden="true"></i>{{ __('Monitoring show manage groups') }}
            </span>
        @else
            <a href="{{ route('groups.index', $project->id) }}" class="cabinet-mon-project-module-nav__item">
                <i class="bi bi-collection" aria-hidden="true"></i>{{ __('Monitoring show manage groups') }}
            </a>
        @endif
        @if($activeModule === 'prices')
            <span class="cabinet-mon-project-module-nav__item is-active">
                <i class="bi bi-currency-exchange" aria-hidden="true"></i>{{ __('Monitoring show keyword prices') }}
            </span>
        @else
            <a href="{{ route('prices.index', $project->id) }}" class="cabinet-mon-project-module-nav__item">
                <i class="bi bi-currency-exchange" aria-hidden="true"></i>{{ __('Monitoring show keyword prices') }}
            </a>
        @endif
        @can('update_occurrence_monitoring')
            <button type="button" class="cabinet-mon-project-module-nav__item border-0 bg-transparent" id="occurrence-update">
                <i class="bi bi-arrow-repeat" aria-hidden="true"></i>{{ __('Monitoring show update occurrence') }}
            </button>
        @endcan
    </nav>
</header>
