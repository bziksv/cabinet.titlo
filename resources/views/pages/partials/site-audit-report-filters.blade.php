<form method="GET" action="{{ $filterAction }}" class="cabinet-sa-filters mb-3" id="sa-report-filters">
    @if(!empty($groupable) && !empty($viewMode))
        <input type="hidden" name="view" value="{{ $viewMode }}">
    @endif
    <div class="cabinet-sa-filters__row">
        @foreach($filterFields as $field)
            <div class="cabinet-sa-filters__field">
                <label class="cabinet-sa-filters__label" for="sa-f-{{ $field['key'] }}">{{ $field['label'] }}</label>
                <input type="search"
                       class="form-control form-control-sm"
                       id="sa-f-{{ $field['key'] }}"
                       name="{{ $field['param'] }}"
                       value="{{ $filterValues[$field['key']] ?? '' }}"
                       placeholder="Умный фильтр…"
                       autocomplete="off">
            </div>
        @endforeach
        <div class="cabinet-sa-filters__actions">
            <button type="submit" class="btn btn-sm btn-outline-primary">Найти</button>
            @if(!empty($filtersActive))
                <a href="{{ $filterClearUrl }}{{ !empty($groupable) && !empty($viewMode) ? ('?view=' . urlencode($viewMode)) : '' }}" class="btn btn-sm btn-link">Сбросить</a>
            @endif
        </div>
    </div>
    <div class="cabinet-sa-filters__hint">Любая раскладка (йцукен ↔ qwerty). Для Title/Description — по данным страницы.</div>
</form>
