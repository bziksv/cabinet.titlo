{{-- Архив отчётов проекта (Labrika: выбор прошлого краула) --}}
@php
    $archiveList = $archiveCrawls ?? collect();
@endphp
@if($archiveList->count() > 0)
    <div class="modal fade" id="sa-archive-modal" tabindex="-1" role="dialog" aria-labelledby="sa-archive-title" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="sa-archive-title">
                        Архив отчётов
                        <span class="text-muted small">· {{ optional($project)->domain ?? 'проект' }}</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="cabinet-sa-table-wrap border-0 rounded-0">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="thead-light">
                            <tr>
                                <th>Краул</th>
                                <th>Дата</th>
                                <th>Статус</th>
                                <th>Страниц</th>
                                @foreach($bucketLabels as $label)
                                    <th class="text-end">{{ $label }}</th>
                                @endforeach
                                <th></th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($archiveList as $a)
                                @php
                                    $b = is_array($a->buckets_json) ? $a->buckets_json : [];
                                    $isCurrent = (int) $a->id === (int) $crawl->id;
                                @endphp
                                <tr class="{{ $isCurrent ? 'table-active' : '' }}">
                                    <td>
                                        <a href="{{ route('pages.site-audit.crawl.show', $a->id) }}">#{{ $a->id }}</a>
                                        @if($isCurrent)
                                            <span class="badge text-bg-secondary ms-1">текущий</span>
                                        @endif
                                    </td>
                                    <td class="small text-muted text-nowrap">
                                        {{ optional($a->finished_at ?: $a->created_at)->format('d.m.Y H:i') }}
                                    </td>
                                    <td>
                                        <span class="cabinet-sa-status cabinet-sa-status--{{ $a->statusCssClass() }}">
                                            {{ $a->statusLabelRu() }}
                                        </span>
                                    </td>
                                    <td class="text-nowrap">{{ (int) $a->pages_fetched }}/{{ (int) $a->pages_total }}</td>
                                    <td class="text-end">{{ (int) ($b['critical'] ?? 0) }}</td>
                                    <td class="text-end">{{ (int) ($b['other'] ?? 0) }}</td>
                                    <td class="text-end">{{ (int) ($b['warning'] ?? 0) }}</td>
                                    <td class="text-end">{{ (int) ($b['info'] ?? 0) }}</td>
                                    <td class="text-end text-nowrap">
                                        <a class="btn btn-sm btn-outline-primary" href="{{ route('pages.site-audit.crawl.show', $a->id) }}">Сводка</a>
                                        @if(! $isCurrent && $crawl->status === 'done' && $a->status === 'done')
                                            <a class="btn btn-sm btn-outline-info"
                                               href="{{ route('pages.site-audit.crawl.diff', ['id' => $crawl->id, 'with' => $a->id]) }}"
                                               title="Сравнить с текущим краулом">Сравнить</a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer justify-content-between">
                    <span class="small text-muted">Хранится до {{ (int) config('site_audit.history_keep_per_project', 200) }} краулов на проект</span>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Закрыть</button>
                </div>
            </div>
        </div>
    </div>
@endif
