<div class="row">
    <div class="col-12">
        <div class="card cabinet-mon-show-filters">
            <div class="card-header">
                <h3 class="card-title mb-0">{{ __('Keywords filter') }}</h3>
            </div>

            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <form action="" method="get" style="display: contents;">
                        @foreach(request()->except('region') as $key => $value)
                            @if(is_array($value))
                                @foreach($value as $item)
                                    <input type="hidden" name="{{ $key }}[]" value="{{ $item }}">
                                @endforeach
                            @else
                                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                            @endif
                        @endforeach
                        <div class="col-md-4">
                            <div class="mb-0">
                                <label class="form-label" for="searchengines">{{ __('Search engine') }}</label>
                                <select name="region" class="form-select" id="searchengines" onchange="this.form.submit()">
                                    @if($project->searchengines->count() > 1)
                                        <option value="">{{ __('All search engine and regions') }}</option>
                                    @endif

                                    @foreach($project->searchengines as $search)
                                        @if($search->id == request('region'))
                                            <option value="{{ $search->id }}" selected>{{ \App\Classes\Monitoring\MonitoringLocationLabel::filterOption($search) }}</option>
                                        @else
                                            <option value="{{ $search->id }}">{{ \App\Classes\Monitoring\MonitoringLocationLabel::filterOption($search) }}</option>
                                        @endif
                                    @endforeach
                                </select>
                            </div>

                            <button type="button"
                                    class="btn btn-outline-primary btn-sm cabinet-mon-show-filters__charts-btn"
                                    id="showChartsBlock"
                                    data-label-show="{{ __('Monitoring show charts toggle') }}"
                                    data-label-hide="{{ __('Monitoring show charts hide') }}">{{ __('Monitoring show charts toggle') }}</button>
                        </div>
                    </form>

                    @if(request('region') || $project->searchengines->count() === 1)
                    <div class="col-md-4">
                        <div class="mb-0">
                            <label class="form-label" for="date-range">{{ __('Date range') }}</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-calendar3" aria-hidden="true"></i></span>
                                <input type="text" class="form-control" id="date-range">
                            </div>
                        </div>
                    </div>
                    @endif

                    <form action="" id="filter" style="display: contents;" onchange='this.submit()'>
                        @foreach(request()->except('group') as $key => $value)
                            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                        @endforeach

                        <div class="col-md-4">
                            <div class="mb-0">
                                <label class="form-label" for="monitoring-group-filter">{{ __('Groups') }}</label>
                                {{ Form::select('group', $project->groups->prepend(collect(['name' => __('Selected group'), 'id' => null]))->pluck('name', 'id'), request('group', null), ['class' => 'form-select', 'id' => 'monitoring-group-filter']) }}
                            </div>
                        </div>
                    </form>
                </div>

            </div>
        </div>
    </div>
</div>
