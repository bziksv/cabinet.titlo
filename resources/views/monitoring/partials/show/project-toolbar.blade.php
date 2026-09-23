<div class="cabinet-mon-project-toolbar">
    <form action="" method="get" class="cabinet-mon-project-toolbar__form">
        @foreach(request()->except('region') as $key => $value)
            @if(is_array($value))
                @foreach($value as $item)
                    <input type="hidden" name="{{ $key }}[]" value="{{ $item }}">
                @endforeach
            @else
                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endif
        @endforeach
        <label class="visually-hidden" for="searchengines">{{ __('Search engine') }}</label>
        <select name="region" class="form-select form-select-sm" id="searchengines" onchange="this.form.submit()">
            @if($project->searchengines->count() > 1)
                <option value="">{{ __('All search engine and regions') }}</option>
            @endif
            @foreach($project->searchengines as $search)
                <option value="{{ $search->id }}" data-engine="{{ $search->engine }}" data-google-depth="{{ (int) ($search->google_depth ?? 10) }}" @if($search->id == request('region')) selected @endif>
                    {{ \App\Classes\Monitoring\MonitoringLocationLabel::filterOption($search) }}
                </option>
            @endforeach
        </select>
    </form>

    @if($project->searchengines->count() > 0)
        <div class="cabinet-mon-project-toolbar__dates">
            <label class="visually-hidden" for="date-range">{{ __('Date range') }}</label>
            <div class="input-group input-group-sm">
                <span class="input-group-text"><i class="bi bi-calendar3" aria-hidden="true"></i></span>
                <input type="text" class="form-control" id="date-range" autocomplete="off">
            </div>
        </div>
    @endif

    <form action="" id="filter" class="cabinet-mon-project-toolbar__form" onchange="this.submit()">
        @foreach(request()->except('group') as $key => $value)
            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
        @endforeach
        <label class="visually-hidden" for="monitoring-group-filter">{{ __('Groups') }}</label>
        {{ Form::select(
            'group',
            $project->groups->prepend(collect(['name' => __('Selected group'), 'id' => null]))->pluck('name', 'id'),
            request('group', null),
            ['class' => 'form-select form-select-sm', 'id' => 'monitoring-group-filter']
        ) }}
    </form>

    <div class="cabinet-mon-project-toolbar__compare" id="cabinetMonProjectCompare">
        <button
            type="button"
            class="cabinet-mon-compare-chip"
            id="cabinet-mon-compare-toggle"
            aria-expanded="false"
            aria-controls="cabinet-mon-compare-panel"
        >
            <i class="bi bi-intersect cabinet-mon-compare-chip__icon" aria-hidden="true"></i>
            <span class="cabinet-mon-compare-chip__label" id="cabinet-mon-compare-chip-label">
                {{ __('Monitoring show compare project') }}
            </span>
            <span class="cabinet-mon-compare-chip__vs d-none" id="cabinet-mon-compare-chip-vs" aria-hidden="true"></span>
            <i class="bi bi-chevron-down cabinet-mon-compare-chip__chev" aria-hidden="true"></i>
        </button>
        <button
            type="button"
            class="cabinet-mon-compare-clear d-none"
            id="cabinet-mon-compare-clear"
            aria-label="{{ __('Monitoring show compare clear') }}"
        >
            <i class="bi bi-x-lg" aria-hidden="true"></i>
        </button>

        <div class="cabinet-mon-compare-panel d-none" id="cabinet-mon-compare-panel">
            <p class="cabinet-mon-compare-panel__hint mb-0">{{ __('Monitoring show compare pick project') }}</p>
            <div class="cabinet-mon-compare-panel__fields">
                <label class="visually-hidden" for="cabinet-mon-compare-project">{{ __('Monitoring show compare project') }}</label>
                <select
                    class="form-select form-select-sm cabinet-mon-compare-project-select"
                    id="cabinet-mon-compare-project"
                    data-placeholder="{{ __('Monitoring show compare search placeholder') }}"
                >
                    <option value="">{{ __('Monitoring show compare none') }}</option>
                </select>
                <label class="visually-hidden" for="cabinet-mon-compare-group">{{ __('Monitoring show compare group') }}</label>
                <select class="form-select form-select-sm" id="cabinet-mon-compare-group" disabled>
                    <option value="">{{ __('Monitoring show compare all groups') }}</option>
                </select>
            </div>
            <div id="cabinetMonCompareNotice" class="cabinet-mon-compare-notice d-none" role="status" aria-live="polite"></div>
            <div id="cabinetMonCompareIntersect" class="cabinet-mon-compare-intersect d-none" role="status" aria-live="polite"></div>
        </div>
    </div>
</div>
