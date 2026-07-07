@php
    $columnSettings = $columnSettings ?? \App\MonitoringProjectColumnsSetting::DEFAULT_VISIBILITY;
    $isMultiRegionView = $isMultiRegionView ?? false;
    $multiRegionDisabledColumns = ['dynamics', 'base', 'phrasal', 'exact'];
    $colChipState = function (string $name, string $default) use ($columnSettings, $isMultiRegionView, $multiRegionDisabledColumns): array {
        if ($isMultiRegionView && in_array($name, $multiRegionDisabledColumns, true)) {
            return [
                'class' => 'is-off disabled',
                'pressed' => 'false',
                'disabled' => true,
                'title' => $name === 'dynamics'
                    ? __('Monitoring column dynamics multi region')
                    : __('Monitoring column occurrence multi region'),
            ];
        }
        $visible = array_key_exists($name, $columnSettings)
            ? (bool) $columnSettings[$name]
            : ($default === 'on');
        return [
            'class' => $visible ? 'is-on' : 'is-off',
            'pressed' => $visible ? 'true' : 'false',
            'disabled' => false,
            'title' => __('Monitoring show column toggle'),
        ];
    };
@endphp
<div class="cabinet-mon-keywords-toolbar">
    <div class="cabinet-mon-keywords-toolbar__main">
        <div class="cabinet-mon-keywords-toolbar__actions">
            <button type="button" class="btn btn-default btn-sm checkbox-toggle tooltip-on" title="Выбрать все">
                <i class="far fa-square"></i>
            </button>

            <div class="btn-group queries-controls">
                @can('create_query_monitoring')
                    <button type="button" class="btn btn-default btn-sm tooltip-on" data-bs-toggle="modal" data-bs-target="#cabinetMonKeywordsModal" data-type="create_keywords" title="Добавить запрос">
                        <i class="fas fa-plus"></i>
                    </button>
                @endcan

                @can('edit_query_monitoring')
                    <button type="button" class="btn btn-default btn-sm tooltip-on" data-bs-toggle="modal" data-bs-target="#cabinetMonKeywordsModal" data-type="edit_plural" title="Редактировать запросы">
                        <i class="fas fa-pen"></i>
                    </button>
                @endcan

                @can('delete_query_monitoring')
                    <button type="button" class="btn btn-outline-secondary btn-sm delete-multiple tooltip-on" title="{{ __('Delete') }}">
                        <i class="bi bi-trash" aria-hidden="true"></i>
                        <span class="visually-hidden">{{ __('Delete') }}</span>
                    </button>
                @endcan
            </div>

            <div class="cabinet-mon-keywords-toolbar__action-group">
                <span class="cabinet-mon-keywords-toolbar__action-group-label">{{ __('Monitoring toolbar label positions') }}</span>
                <div class="btn-group positions-controls" role="group" aria-label="{{ __('Monitoring toolbar label positions') }}">
                    @can('update_position_monitoring')
                        <button type="button" class="btn btn-default btn-sm parse-positions-keys tooltip-on" title="{{ __('Monitoring position selected keys') }}">
                            <i class="fas fa-crosshairs"></i>
                        </button>
                    @endcan

                    @can('update_position_all_monitoring')
                        <button type="button" class="btn btn-default btn-sm parse-positions tooltip-on" title="{{ __('Monitoring position all keys') }}">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    @endcan
                </div>
            </div>

            <div class="cabinet-mon-keywords-toolbar__action-group">
                <span class="cabinet-mon-keywords-toolbar__action-group-label">{{ __('Monitoring toolbar label occurrence') }}</span>
                <div class="btn-group occurrence-controls" role="group" aria-label="{{ __('Monitoring toolbar label occurrence') }}">
                    @can('update_occurrence_monitoring')
                        <button type="button" class="btn btn-default btn-sm parse-occurrence-all tooltip-on" title="{{ __('Monitoring occurrence all keys') }}">
                            <i class="fas fa-chart-line"></i>
                        </button>
                        <button type="button" class="btn btn-default btn-sm parse-occurrence-keys tooltip-on" title="{{ __('Monitoring occurrence selected keys') }}">
                            <i class="fas fa-chart-bar"></i>
                        </button>
                    @endcan
                </div>
            </div>
        </div>

        <div class="cabinet-mon-column-toggles cabinet-mon-column-toggles--inline"
             role="group"
             aria-label="{{ __('Columns') }}"
             title="{{ __('Monitoring show columns hint') }}">
            <span class="cabinet-mon-column-toggles__label">{{ __('Columns') }}:</span>
            <div class="cabinet-mon-column-toggles__list">
                @foreach ([
                    ['name' => 'query', 'text' => __('Query'), 'default' => 'on'],
                    ['name' => 'url', 'text' => __('URL'), 'default' => 'off'],
                    ['name' => 'group', 'text' => __('Group'), 'default' => 'on'],
                    ['name' => 'target_url', 'text' => __('Target URL'), 'default' => 'off'],
                    ['name' => 'target', 'text' => __('Target'), 'default' => 'on'],
                    ['name' => 'dynamics', 'text' => __('Dynamics'), 'default' => 'on'],
                    ['name' => 'base', 'text' => __('YW'), 'default' => 'off'],
                    ['name' => 'phrasal', 'text' => __('YW') . ' "[]"', 'default' => 'off'],
                    ['name' => 'exact', 'text' => __('YW') . ' "[!]"', 'default' => 'off'],
                ] as $col)
                    @php($chip = $colChipState($col['name'], $col['default']))
                    <button type="button"
                            class="cabinet-mon-col-chip column-visible {{ $chip['class'] }}"
                            data-default="{{ $col['default'] }}"
                            data-column="{{ $col['name'] }}"
                            title="{{ $chip['title'] }}"
                            aria-pressed="{{ $chip['pressed'] }}"
                            @if($chip['disabled']) disabled @endif>
                        <span class="cabinet-mon-col-chip__mark" aria-hidden="true"></span>
                        <span class="cabinet-mon-col-chip__text">{{ $col['text'] }}</span>
                    </button>
                @endforeach
            </div>
        </div>
    </div>
</div>
