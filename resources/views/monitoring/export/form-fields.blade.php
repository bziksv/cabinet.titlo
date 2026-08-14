{{-- Общие поля формы экспорта (модалка и отдельная страница) --}}
<div class="cabinet-mon-export-form">
    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label for="startDatePickerInput">{{ __('Monitoring export start date') }}</label>
                <div class="input-group date" id="startDatePicker" data-target-input="nearest">
                    <input id="startDatePickerInput" type="text" name="startDate"
                           class="form-control datetimepicker-input"
                           data-target="#startDatePicker" data-toggle="datetimepicker"
                           value="{{ \Carbon\Carbon::now()->startOfMonth()->isoFormat('DD.MM.YYYY') }}"/>
                    <div class="input-group-append" data-target="#startDatePicker" data-toggle="datetimepicker">
                        <span class="input-group-text"><i class="fa fa-calendar" aria-hidden="true"></i></span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                <label for="endDatePickerInput">{{ __('Monitoring export end date') }}</label>
                <div class="input-group date" id="endDatePicker" data-target-input="nearest">
                    <input id="endDatePickerInput" type="text" name="endDate"
                           class="form-control datetimepicker-input"
                           data-target="#endDatePicker" data-toggle="datetimepicker"
                           value="{{ \Carbon\Carbon::now()->isoFormat('DD.MM.YYYY') }}"/>
                    <div class="input-group-append" data-target="#endDatePicker" data-toggle="datetimepicker">
                        <span class="input-group-text"><i class="fa fa-calendar" aria-hidden="true"></i></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label for="cabinetMonExportMode">{{ __('Monitoring export type') }}</label>
                <select class="form-select" name="mode" id="cabinetMonExportMode">
                    <option value="range">{{ __('Monitoring export mode range') }}</option>
                    <option value="finance">{{ __('Monitoring export mode finance') }}</option>
                    <option value="datesFind">{{ __('Monitoring export mode dates fixed') }}</option>
                    <option value="dates">{{ __('Monitoring export mode dates floating') }}</option>
                    <option value="randWeek">{{ __('Monitoring export mode rand week') }}</option>
                    <option value="randMonth">{{ __('Monitoring export mode rand month') }}</option>
                </select>
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                <label for="cabinetMonExportRegion">{{ __('Region') }}</label>
                <select class="form-select" name="region" id="cabinetMonExportRegion">
                    @foreach($project->searchengines as $searchengines)
                        @php
                            $locName = optional($searchengines->location)->name
                                ?? (is_array($searchengines->location) ? ($searchengines->location['name'] ?? '') : '');
                        @endphp
                        <option value="{{ $searchengines->id }}">{{ $locName }} [{{ $searchengines->lr }}]</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <div class="form-group">
        <label for="cabinetMonExportGroups">{{ __('Group') }}</label>
        <select multiple class="form-select" name="group[]" id="cabinetMonExportGroups"
                data-placeholder="{{ __('Monitoring export groups placeholder') }}">
            @foreach($project->groups as $groups)
                <option value="{{ $groups->id }}">{{ $groups->name }}</option>
            @endforeach
        </select>
        <small class="form-text text-muted">{{ __('Monitoring export groups hint') }}</small>
    </div>

    <div class="row">
        <div class="col-md-4">
            <div class="form-group">
                <label for="cabinetMonExportFormat">{{ __('Monitoring export format') }}</label>
                <select class="form-select" name="format" id="cabinetMonExportFormat">
                    <option value="pdf">PDF</option>
                    <option value="xls">Excel</option>
                    <option value="html">HTML</option>
                    <option value="csv">CSV</option>
                </select>
            </div>
        </div>
        <div class="col-md-8">
            <div class="form-group">
                <label>{{ __('Monitoring export sort') }}</label>
                <div class="row">
                    <div class="col-6">
                        <select class="form-select" name="order[column]" aria-label="{{ __('Monitoring export sort') }}">
                            <option value="{{ \App\Http\Controllers\MonitoringExportsController::GROUP_INDEX }}">{{ __('Group') }}</option>
                            <option value="{{ \App\Http\Controllers\MonitoringExportsController::QUERY_INDEX }}">{{ __('Query') }}</option>
                            <option value="{{ \App\Http\Controllers\MonitoringExportsController::CREATED_AT_INDEX }}">{{ __('Monitoring export sort by created') }}</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <select class="form-select" name="order[dir]" aria-label="{{ __('Monitoring export sort dir') }}">
                            <option value="asc">{{ __('Ascending') }}</option>
                            <option value="desc">{{ __('Descending') }}</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="cabinet-mon-export-form__section">
        <h3 class="cabinet-mon-export-form__section-title">{{ __('Monitoring export columns') }}</h3>
        <div class="row">
            <div class="col-sm-12">
                <div class="form-group mb-2">
                    <div class="custom-control custom-checkbox">
                        <input class="custom-control-input" name="dynamicsDays" type="checkbox" id="dynamicsDays" value="1">
                        <label for="dynamicsDays" class="custom-control-label">{{ __('Monitoring export hide day dynamics') }}</label>
                    </div>
                </div>
            </div>
            <div class="col-sm-6">
                <div class="form-group">
                    <div class="custom-control custom-checkbox">
                        <input class="custom-control-input" name="baseCol" type="checkbox" id="base" value="1">
                        <label for="base" class="custom-control-label">{{ __('Monitoring export col base frequency') }}</label>
                    </div>
                    <div class="custom-control custom-checkbox">
                        <input class="custom-control-input" name="phrasalCol" type="checkbox" id="phrasal" value="1">
                        <label for="phrasal" class="custom-control-label">{{ __('Monitoring export col phrase frequency') }}</label>
                    </div>
                    <div class="custom-control custom-checkbox">
                        <input class="custom-control-input" name="exactCol" type="checkbox" id="exact" value="1">
                        <label for="exact" class="custom-control-label">{{ __('Monitoring export col exact frequency') }}</label>
                    </div>
                </div>
            </div>
            <div class="col-sm-6">
                <div class="form-group">
                    <div class="custom-control custom-checkbox">
                        <input class="custom-control-input" name="groupCol" type="checkbox" id="group" value="1">
                        <label for="group" class="custom-control-label">{{ __('Group') }}</label>
                    </div>
                    <div class="custom-control custom-checkbox">
                        <input class="custom-control-input" name="dynamicsCol" type="checkbox" id="dynamics" value="1">
                        <label for="dynamics" class="custom-control-label">{{ __('Dynamics') }}</label>
                    </div>
                    <div class="custom-control custom-checkbox">
                        <input class="custom-control-input" name="urlCol" type="checkbox" id="url" value="1">
                        <label for="url" class="custom-control-label">{{ __('URL') }}</label>
                    </div>
                    <div class="custom-control custom-checkbox">
                        <input class="custom-control-input" name="target_urlCol" type="checkbox" id="target_url" value="1">
                        <label for="target_url" class="custom-control-label">{{ __('Target URL') }}</label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row d-none cabinet-mon-export-form__finance" id="finance">
        <div class="col-12">
            <h3 class="cabinet-mon-export-form__section-title">{{ __('Monitoring export finance columns') }}</h3>
        </div>
        <div class="col-sm-6">
            <div class="form-group">
                @foreach([1, 3, 5, 10, 20, 50, 100] as $top)
                    <div class="custom-control custom-checkbox">
                        <input class="custom-control-input" name="price_top_{{ $top }}Col" type="checkbox"
                               id="price_top_{{ $top }}" value="1" @if($top === 10) checked @endif>
                        <label for="price_top_{{ $top }}" class="custom-control-label">{{ __('Price') }} top-{{ $top }}</label>
                    </div>
                @endforeach
            </div>
        </div>
        <div class="col-sm-6">
            <div class="form-group">
                @foreach([1, 3, 5, 10, 20, 50, 100] as $top)
                    <div class="custom-control custom-checkbox">
                        <input class="custom-control-input" name="days_top_{{ $top }}Col" type="checkbox"
                               id="days_top_{{ $top }}" value="1" @if(in_array($top, [1, 3, 5, 10], true)) checked @endif>
                        <label for="days_top_{{ $top }}" class="custom-control-label">{{ __('Days') }} top-{{ $top }}</label>
                    </div>
                @endforeach
                <div class="custom-control custom-checkbox">
                    <input class="custom-control-input" name="days_top_10_sumCol" type="checkbox" id="days_top_10_sum" value="1" checked>
                    <label for="days_top_10_sum" class="custom-control-label">{{ __('Monitoring export days top10 sum') }}</label>
                </div>
            </div>
        </div>
    </div>
</div>
