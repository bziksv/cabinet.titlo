{{-- Панель столбцов обычного отчёта (не crawl_pages / crawl_images) --}}
@php
    use App\Services\SiteAudit\SiteAuditReportColumns;
    $rcCatalog = SiteAuditReportColumns::catalog();
    $rcPresets = SiteAuditReportColumns::presets();
    $rcGroups = SiteAuditReportColumns::groupLabels();
    $rcAvailable = $reportColKeys ?? [];
    $rcAvailableSet = array_fill_keys($rcAvailable, true);
    $rcDefault = array_values(array_filter(SiteAuditReportColumns::defaultKeys(), function ($k) use ($rcAvailableSet) {
        return isset($rcAvailableSet[$k]);
    }));
    $rcFlat = array_values(array_filter($rcCatalog, function ($col) use ($rcAvailableSet) {
        return isset($rcAvailableSet[$col['key']]);
    }));
    $rcMovable = array_values(array_filter($rcFlat, function ($col) {
        return empty($col['pinned_end']);
    }));
    $rcPinned = array_values(array_filter($rcFlat, function ($col) {
        return ! empty($col['pinned_end']);
    }));
    $rcCatalogKeys = array_values(array_map(function ($col) {
        return $col['key'];
    }, array_merge($rcMovable, $rcPinned)));
    $rcPinnedKeys = array_values(array_map(function ($col) {
        return $col['key'];
    }, $rcPinned));
@endphp
<div class="cabinet-sa-crawl-pages-toolbar mb-2"
     data-sa-report-cols
     data-sa-cols-catalog="{{ implode(',', $rcCatalogKeys) }}"
     data-sa-cols-pinned-end="{{ implode(',', $rcPinnedKeys) }}">
    <div class="cabinet-sa-crawl-pages-toolbar__presets" role="group" aria-label="Пресеты столбцов">
        @foreach($rcPresets as $presetKey => $preset)
            @php
                $presetCols = array_values(array_filter($preset['cols'], function ($k) use ($rcAvailableSet) {
                    return isset($rcAvailableSet[$k]);
                }));
            @endphp
            @if($presetCols === [])
                @continue
            @endif
            <button type="button" class="btn btn-sm btn-outline-secondary cabinet-sa-crawl-pages-preset"
                    data-sa-cols-preset="{{ $presetKey }}"
                    data-sa-cols-preset-cols="{{ implode(',', $presetCols) }}">{{ $preset['label'] }}</button>
        @endforeach
    </div>
    <div class="cabinet-sa-crawl-pages-toolbar__end">
        @include('pages.partials.site-audit-report-bulk-page', [
            'pageFindingIds' => $pageFindingIds ?? [],
            'crawl' => $crawl,
            'code' => $code ?? '',
            'canNote' => $canNote ?? false,
            'canIgnore' => $canIgnore ?? false,
        ])
        <details class="cabinet-sa-crawl-pages-cols">
            <summary class="btn btn-sm btn-outline-secondary">Столбцы</summary>
            <div class="cabinet-sa-crawl-pages-cols__panel" data-sa-cols-order-list>
                <div class="cabinet-sa-report-cols__hint">Перетащите строки — так задаётся порядок. «Действия» всегда в конце таблицы.</div>
                @foreach($rcMovable as $col)
                    @php
                        $gLabel = $rcGroups[$col['group'] ?? ''] ?? '';
                    @endphp
                    <label class="cabinet-sa-crawl-pages-cols__item cabinet-sa-report-cols__item"
                           draggable="true"
                           data-sa-col-order="{{ $col['key'] }}">
                        <span class="cabinet-sa-report-cols__drag" aria-hidden="true">⋮⋮</span>
                        <input type="checkbox"
                               data-sa-col-toggle="{{ $col['key'] }}"
                               @if(!empty($col['locked'])) disabled @endif
                               @if(in_array($col['key'], $rcDefault, true)) checked @endif>
                        <span class="cabinet-sa-report-cols__label">{{ $col['label'] }}</span>
                        @if($gLabel !== '')
                            <span class="cabinet-sa-report-cols__group-tag">{{ $gLabel }}</span>
                        @endif
                    </label>
                @endforeach
                @if($rcPinned !== [])
                    <div class="cabinet-sa-report-cols__pinned" data-sa-cols-pinned>
                        <div class="cabinet-sa-report-cols__pinned-title">Всегда в конце</div>
                        @foreach($rcPinned as $col)
                            @php
                                $gLabel = $rcGroups[$col['group'] ?? ''] ?? '';
                            @endphp
                            <label class="cabinet-sa-crawl-pages-cols__item cabinet-sa-report-cols__item is-pinned-end"
                                   draggable="false"
                                   data-sa-col-order="{{ $col['key'] }}"
                                   data-sa-col-pinned-end="1">
                                <span class="cabinet-sa-report-cols__drag cabinet-sa-report-cols__drag--locked" aria-hidden="true">⌁</span>
                                <input type="checkbox"
                                       data-sa-col-toggle="{{ $col['key'] }}"
                                       @if(in_array($col['key'], $rcDefault, true)) checked @endif>
                                <span class="cabinet-sa-report-cols__label">{{ $col['label'] }}</span>
                                @if($gLabel !== '')
                                    <span class="cabinet-sa-report-cols__group-tag">{{ $gLabel }}</span>
                                @endif
                            </label>
                        @endforeach
                    </div>
                @endif
                <div class="cabinet-sa-report-cols__foot" data-sa-cols-foot>
                    <button type="button" class="btn btn-sm btn-link px-0" data-sa-cols-reset>По умолчанию</button>
                </div>
            </div>
        </details>
    </div>
</div>
