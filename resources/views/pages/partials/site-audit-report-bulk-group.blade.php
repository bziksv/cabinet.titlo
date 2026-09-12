{{-- Массовые действия для одного блока в режиме «По ошибкам». --}}
@php
    $groupFindingIds = array_values(array_filter(array_map('intval', is_array($group['finding_ids'] ?? null) ? $group['finding_ids'] : [])));
    $groupBulkCount = count($groupFindingIds);
    $groupBulkLabel = number_format($groupBulkCount, 0, '', ' ');
    $groupBulkCsv = $groupBulkCount > 0 ? implode(',', $groupFindingIds) : '';
@endphp
@if($groupBulkCount > 0 && (!empty($canNote) || !empty($canIgnore)))
    <div class="cabinet-sa-dup-group__actions" role="group" aria-label="Действия для всего блока ({{ $groupBulkLabel }})">
        @if(!empty($canNote))
            <form method="POST"
                  action="{{ route('pages.site-audit.note.bulk-fixed', $crawl->id) }}"
                  class="cabinet-sa-dup-group__act-form"
                  data-cabinet-confirm="Пометить весь блок ({{ $groupBulkLabel }} стр.) как исправленный? Счётчики уменьшатся. Вернуть можно через «Показать исправленные»."
                  data-cabinet-confirm-title="Исправлено · блок"
                  data-cabinet-confirm-ok="Пометить">
                @csrf
                <input type="hidden" name="return_url" value="{{ request()->fullUrl() }}">
                <input type="hidden" name="code" value="{{ $code }}">
                <input type="hidden" name="bulk_scope" value="group">
                <input type="hidden" name="finding_ids_csv" value="{{ $groupBulkCsv }}">
                <button type="submit" class="btn btn-sm btn-outline-success cabinet-sa-dup-group__act-btn">
                    <i class="fa fa-check" aria-hidden="true"></i>
                    Исправлено · блок
                </button>
                @include('pages.partials.site-audit-tip', [
                    'tip' => "Пометить все URL этого блока как исправленные (не только видимый список из 10).\nУйдут из счётчиков. Вернуть: «Показать исправленные» → «Открыть».",
                    'tipSide' => 'left',
                ])
            </form>
        @endif
        @if(!empty($canIgnore))
            <form method="POST"
                  action="{{ route('pages.site-audit.ignore.bulk', $crawl->id) }}"
                  class="cabinet-sa-dup-group__act-form"
                  data-cabinet-confirm="Добавить весь блок ({{ $groupBulkLabel }} стр.) в игнор? Не будут считаться ошибками и в следующих проверках."
                  data-cabinet-confirm-title="Игнор · блок"
                  data-cabinet-confirm-ok="В игнор"
                  data-cabinet-confirm-danger="1">
                @csrf
                <input type="hidden" name="return_url" value="{{ request()->fullUrl() }}">
                <input type="hidden" name="code" value="{{ $code }}">
                <input type="hidden" name="bulk_scope" value="group">
                <input type="hidden" name="finding_ids_csv" value="{{ $groupBulkCsv }}">
                <button type="submit" class="btn btn-sm btn-outline-secondary cabinet-sa-dup-group__act-btn">
                    <i class="fa fa-ban" aria-hidden="true"></i>
                    Игнор · блок
                </button>
                @include('pages.partials.site-audit-tip', [
                    'tip' => "Добавить все URL этого блока в игнор (не только видимый список).\nКак «Игнор» у строки: не ошибка / ложное срабатывание, в т.ч. в следующих проверках.\nСнять: «Показать игнор» → «Вернуть».",
                    'tipSide' => 'left',
                ])
            </form>
        @endif
    </div>
@endif
