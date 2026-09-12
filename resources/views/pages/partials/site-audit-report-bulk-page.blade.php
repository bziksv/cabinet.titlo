{{-- Массовые действия: кнопки для вставки в toolbar__end (перед «Столбцы»). --}}
@php
    $bulkIds = $pageFindingIds ?? [];
    $bulkCount = count($bulkIds);
    $bulkCountLabel = number_format($bulkCount, 0, '', ' ');
@endphp
@if($bulkCount > 0 && (!empty($canNote) || !empty($canIgnore)))
    <div class="cabinet-sa-bulk-page cabinet-sa-bulk-page--toolbar" role="group" aria-label="Действия для всей страницы списка ({{ $bulkCountLabel }})">
        @if(!empty($canNote))
            <form method="POST"
                  action="{{ route('pages.site-audit.note.bulk-fixed', $crawl->id) }}"
                  class="cabinet-sa-bulk-page__form"
                  data-cabinet-confirm="Пометить все {{ $bulkCountLabel }} строк на этой странице как исправленные? Счётчики уменьшатся. Это не весь отчёт — только видимая страница списка."
                  data-cabinet-confirm-title="Исправлено · страница"
                  data-cabinet-confirm-ok="Пометить">
                @csrf
                <input type="hidden" name="return_url" value="{{ request()->fullUrl() }}">
                <input type="hidden" name="code" value="{{ $code }}">
                @foreach($bulkIds as $fid)
                    <input type="hidden" name="finding_ids[]" value="{{ $fid }}">
                @endforeach
                <button type="submit" class="btn btn-sm btn-outline-success cabinet-sa-bulk-page__btn">
                    <i class="fa fa-check" aria-hidden="true"></i>
                    Исправлено · страница
                </button>
                @include('pages.partials.site-audit-tip', [
                    'tip' => "Пометить все строки на этой странице списка как исправленные.\nУйдут из счётчиков (как кнопка «Исправлено» у строки).\nНе весь отчёт — только то, что сейчас видно с учётом пагинации.\nВернуть: «Показать исправленные» → «Открыть».",
                    'tipSide' => 'left',
                ])
            </form>
        @endif
        @if(!empty($canIgnore))
            <form method="POST"
                  action="{{ route('pages.site-audit.ignore.bulk', $crawl->id) }}"
                  class="cabinet-sa-bulk-page__form"
                  data-cabinet-confirm="Добавить все {{ $bulkCountLabel }} строк на этой странице в игнор? Не будут считаться ошибками и в следующих проверках. Это не весь отчёт — только видимая страница списка."
                  data-cabinet-confirm-title="Игнор · страница"
                  data-cabinet-confirm-ok="В игнор"
                  data-cabinet-confirm-danger="1">
                @csrf
                <input type="hidden" name="return_url" value="{{ request()->fullUrl() }}">
                <input type="hidden" name="code" value="{{ $code }}">
                @foreach($bulkIds as $fid)
                    <input type="hidden" name="finding_ids[]" value="{{ $fid }}">
                @endforeach
                <button type="submit" class="btn btn-sm btn-outline-secondary cabinet-sa-bulk-page__btn">
                    <i class="fa fa-ban" aria-hidden="true"></i>
                    Игнор · страница
                </button>
                @include('pages.partials.site-audit-tip', [
                    'tip' => "Добавить все строки на этой странице в игнор.\nКак «Игнор» у строки: не ошибка / ложное срабатывание, в т.ч. в следующих проверках.\nНе весь отчёт — только видимая страница списка.\nСнять: «Показать игнор» → «Вернуть».",
                    'tipSide' => 'left',
                ])
            </form>
        @endif
    </div>
@endif
