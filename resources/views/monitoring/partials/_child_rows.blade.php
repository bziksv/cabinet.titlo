<div class="row">
    @foreach($groups as $engine)
    <div class="col-12">
        <div class="card card-outline card-success">
            <div class="card-header">
                <h3 class="card-title">{{ ucfirst($engine->engine) }}, {{ $engine->location->name }} [{{ $engine->lr }}]</h3>

                <div class="card-tools">
                    <button type="button" class="btn btn-tool" data-lte-toggle="card-collapse" aria-label="{{ __('Collapse') }}">
                        <i class="fas fa-minus"></i>
                    </button>
                </div>
                <!-- /.card-tools -->
            </div>
            <!-- /.card-header -->
            <div class="card-body" style="display: block;">
                <table class="table table-bordered table-hover table-sm">
                    <thead>
                        <tr>
                            <th>{{ __('Update date') }}</th>
                            <th class="tooltip-child-table" title="{{ __('In this column, the average position on the search engine of a certain region/city. We consider it in the classical way: the sum of all positions divided by the number of words. Thanks to grouping by region and day, you will be able to see its dynamics. The closer the average value is to 1, the better.') }}">Средняя позиция <i class="far fa-question-circle"></i></th>
                            <th class="tooltip-child-table" title="Процент фраз в ТОП. В скобках — изменение к срезу в строке ниже (более ранняя дата); подсказка при наведении на ячейку.">ТОП-1 <i class="far fa-question-circle"></i></th>
                            <th>ТОП-3</th>
                            <th>ТОП-5</th>
                            <th>ТОП-10</th>
                            <th>ТОП-20</th>
                            <th>ТОП-50</th>
                            <th>ТОП-100</th>
                            <th>{{{ __('Mastered') }}}</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($engine->data as $data)
                        <tr>
                            <td class="text-nowrap align-middle">
                                {{ $data->latest_created->format('d.m.Y') }}
                                @if(!empty($data->snapshot_period_label))
                                    <br><small class="text-secondary">{{ $data->snapshot_period_label }}</small>
                                @endif
                            </td>
                            <td>{{ $data->middle_position }}</td>
                            <td class="top" @if(!empty($data->delta_vs_label)) title="{{ $data->delta_vs_label }}" @endif>{{$data->top_1}}</td>
                            <td class="top" @if(!empty($data->delta_vs_label)) title="{{ $data->delta_vs_label }}" @endif>{{$data->top_3}}</td>
                            <td class="top" @if(!empty($data->delta_vs_label)) title="{{ $data->delta_vs_label }}" @endif>{{$data->top_5}}</td>
                            <td class="top" @if(!empty($data->delta_vs_label)) title="{{ $data->delta_vs_label }}" @endif>{{$data->top_10}}</td>
                            <td class="top" @if(!empty($data->delta_vs_label)) title="{{ $data->delta_vs_label }}" @endif>{{$data->top_20}}</td>
                            <td class="top" @if(!empty($data->delta_vs_label)) title="{{ $data->delta_vs_label }}" @endif>{{$data->top_50}}</td>
                            <td class="top" @if(!empty($data->delta_vs_label)) title="{{ $data->delta_vs_label }}" @endif>{{$data->top_100}}</td>
                            <td class="top">
                                {{number_format($data->mastered, 2, ',', ' ')}}
                                @if($data->mastered_percent)
                                    <sup style="color: green;">{{$data->mastered_percent}}%</sup>
                                @endif
                                <br />
                                <small style="color: green">{{$data->mastered_percent_day}}%</small>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center">{{ __('No data') }}</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
                @if(!empty($engine->chart_payload['points']))
                    <div class="cabinet-mon-v2-child-chart-toolbar mt-2">
                        <button type="button" class="btn btn-sm btn-outline-primary cabinet-mon-v2-child-chart-toggle">
                            <i class="fas fa-chart-line me-1" aria-hidden="true"></i>
                            <span class="cabinet-mon-v2-child-chart-toggle-label">{{ __('Monitoring child chart show') }}</span>
                        </button>
                    </div>
                    <div class="cabinet-mon-v2-child-chart-wrap d-none mt-3"
                         data-project-id="{{ $projectId }}"
                         data-engine-id="{{ $engine->id }}">
                        @include('monitoring.partials._child_chart_controls')
                        <div class="cabinet-mon-v2-child-chart-canvas-wrap">
                            <canvas
                                class="cabinet-mon-v2-child-chart"
                                data-chart='@json($engine->chart_payload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)'
                            ></canvas>
                        </div>
                    </div>
                @endif
            </div>
            <!-- /.card-body -->
        </div>
        <!-- /.card -->
    </div>
    @endforeach
</div>
