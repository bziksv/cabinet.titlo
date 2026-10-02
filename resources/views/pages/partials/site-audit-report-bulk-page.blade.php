{{-- Массовые действия: кнопки для вставки в toolbar__end (перед «Столбцы»). --}}
@php
    $bulkIds = $pageFindingIds ?? [];
    $bulkCount = count($bulkIds);
    $bulkCountLabel = number_format($bulkCount, 0, '', ' ');
    $reportTotal = (int) ($reportTotal ?? 0);
    $reportTotalLabel = number_format($reportTotal, 0, '', ' ');
    $reportTitle = (string) ($reportTitle ?? ($code ?? 'отчёт'));
    $codeWideIgnored = ! empty($codeWideIgnored);
    $filtersActive = ! empty($filtersActive);
    $showReportIgnore = ! empty($canIgnore) && $reportTotal > 0 && empty($meta['inventory'] ?? null);
@endphp
@if(($bulkCount > 0 && (!empty($canNote) || !empty($canIgnore))) || $showReportIgnore)
    <div class="cabinet-sa-bulk-page cabinet-sa-bulk-page--toolbar" role="group" aria-label="Массовые действия по отчёту">
        @if(!empty($canNote) && $bulkCount > 0)
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
        @if(!empty($canIgnore) && $bulkCount > 0)
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
        @if($showReportIgnore)
            @if($codeWideIgnored)
                <form method="POST"
                      action="{{ route('pages.site-audit.ignore.restore', $crawl->id) }}"
                      class="cabinet-sa-bulk-page__form"
                      data-cabinet-confirm="Снять игнор со всего отчёта «{{ $reportTitle }}»? Находки снова попадут в счётчики."
                      data-cabinet-confirm-title="Вернуть · весь отчёт"
                      data-cabinet-confirm-ok="Вернуть">
                    @csrf
                    <input type="hidden" name="return_url" value="{{ request()->fullUrl() }}">
                    <input type="hidden" name="scope" value="code">
                    <input type="hidden" name="code" value="{{ $code }}">
                    <button type="submit" class="btn btn-sm btn-outline-success cabinet-sa-bulk-page__btn">
                        <i class="fa fa-undo" aria-hidden="true"></i>
                        Вернуть · весь отчёт
                    </button>
                    @include('pages.partials.site-audit-tip', [
                        'tip' => "Снять игнор со всех находок этого отчёта.\nОни снова будут считаться ошибками в сводке и следующих проверках.",
                        'tipSide' => 'left',
                    ])
                </form>
            @else
                @php
                    $reportIgnoreMsg = "Добавить в игнор весь отчёт «{$reportTitle}»"
                        . ($reportTotal > 0 ? " ({$reportTotalLabel} находок)" : '')
                        . '? Все URL этого отчёта не будут считаться ошибками в этой и следующих проверках.'
                        . ($filtersActive ? ' Сработают все находки отчёта, не только текущий фильтр.' : '')
                        . ' Снять: «Показать игнор» → «Вернуть · весь отчёт».';
                @endphp
                <form method="POST"
                      action="{{ route('pages.site-audit.ignore', $crawl->id) }}"
                      class="cabinet-sa-bulk-page__form"
                      data-cabinet-confirm="{{ $reportIgnoreMsg }}"
                      data-cabinet-confirm-title="Игнор · весь отчёт"
                      data-cabinet-confirm-ok="В игнор всё"
                      data-cabinet-confirm-danger="1">
                    @csrf
                    <input type="hidden" name="return_url" value="{{ request()->fullUrl() }}">
                    <input type="hidden" name="scope" value="code">
                    <input type="hidden" name="code" value="{{ $code }}">
                    <button type="submit" class="btn btn-sm btn-outline-danger cabinet-sa-bulk-page__btn">
                        <i class="fa fa-ban" aria-hidden="true"></i>
                        Игнор · весь отчёт
                        @if($reportTotal > 0)
                            <span class="cabinet-sa-bulk-page__count">({{ $reportTotalLabel }})</span>
                        @endif
                    </button>
                    @include('pages.partials.site-audit-tip', [
                        'tip' => "Игнор всех находок этого отчёта сразу (не только страница списка).\nКак «Игнор» у строки, но для всего кода замечания.\nНе ошибка / ложное срабатывание — в т.ч. в следующих проверках.\nСнять: «Показать игнор» → «Вернуть · весь отчёт».",
                        'tipSide' => 'left',
                    ])
                </form>
            @endif
        @endif
    </div>
@endif
