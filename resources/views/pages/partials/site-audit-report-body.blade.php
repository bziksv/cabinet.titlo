{{-- Тело отчёта (help + filters + table/groups + pagination). --}}
@include('pages.partials.site-audit-report-help')
@include('pages.partials.site-audit-report-filters')

@if(!empty($groupable))
    <div class="cabinet-sa-view-toggle mb-3">
        <span class="cabinet-sa-view-toggle__label">Вид:</span>
        <a class="cabinet-sa-view-toggle__btn {{ ($viewMode ?? '') === 'groups' ? 'is-active' : '' }}"
           href="{{ request()->fullUrlWithQuery(['view' => 'groups', 'page' => 1]) }}">Группы</a>
        <a class="cabinet-sa-view-toggle__btn {{ ($viewMode ?? '') === 'list' ? 'is-active' : '' }}"
           href="{{ request()->fullUrlWithQuery(['view' => 'list', 'page' => 1]) }}">Список</a>
        @if(($viewMode ?? '') === 'groups' && !empty($groupTotal))
            <span class="text-muted small ms-2">групп: {{ $groupTotal }} · URL: {{ $total }}</span>
        @endif
    </div>
@endif

@if(!empty($groupable) && ($viewMode ?? '') === 'groups')
    <div class="cabinet-sa-dup-groups">
        @forelse($groups as $gi => $group)
            @php $tone = $gi % 6; @endphp
            <div class="cabinet-sa-dup-group cabinet-sa-dup-group--t{{ $tone }}">
                <div class="cabinet-sa-dup-group__head">
                    <span class="cabinet-sa-dup-group__count">{{ (int) $group['size'] }} стр.</span>
                    <div class="cabinet-sa-dup-group__label">{{ $group['label'] }}</div>
                </div>
                <ul class="cabinet-sa-dup-group__urls">
                    @foreach($group['urls'] as $u)
                        <li>
                            <a href="{{ $u['url'] }}" target="_blank" rel="noopener noreferrer">{{ $u['url'] }}</a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @empty
            <div class="text-muted px-3 py-3">Находок нет</div>
        @endforelse
    </div>
@else
    <div class="cabinet-sa-table-wrap">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
            <tr>
                <th style="width:46%">URL</th>
                <th>Приоритет</th>
                <th>Детали</th>
                @if(!empty($canIgnore))
                    <th style="width:110px"></th>
                @endif
            </tr>
            </thead>
            <tbody>
            @forelse($rows as $row)
                @php
                    $isIgn = !empty($ignoredMap[(int) ($row->id ?? 0)]);
                @endphp
                <tr class="{{ $isIgn ? 'cabinet-sa-row--ignored' : '' }}">
                    <td class="cabinet-sa-url">
                        <a href="{{ $row->url }}" target="_blank" rel="noopener noreferrer">{{ $row->url }}</a>
                        @if($isIgn)
                            <span class="badge text-bg-light border ms-1">игнор</span>
                        @endif
                    </td>
                    <td>{{ \App\Services\SiteAudit\SiteAuditFindingPresenter::severityLabel($row->severity) }}</td>
                    <td class="small">
                        {{ \App\Services\SiteAudit\SiteAuditFindingPresenter::metaLine($row->code ?? $code, $row->meta_json) }}
                    </td>
                    @if(!empty($canIgnore) && !empty($row->id))
                        <td class="text-end">
                            @if($isIgn)
                                <form method="POST" action="{{ route('pages.site-audit.ignore.restore', $crawl->id) }}" class="d-inline">
                                    @csrf
                                    <input type="hidden" name="finding_id" value="{{ $row->id }}">
                                    <button type="submit" class="btn btn-link btn-sm p-0">Вернуть</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('pages.site-audit.ignore', $crawl->id) }}" class="d-inline">
                                    @csrf
                                    <input type="hidden" name="finding_id" value="{{ $row->id }}">
                                    <button type="submit" class="btn btn-link btn-sm p-0 text-secondary" title="Не считать false positive">Игнор</button>
                                </form>
                            @endif
                        </td>
                    @elseif(!empty($canIgnore))
                        <td></td>
                    @endif
                </tr>
            @empty
                <tr><td colspan="{{ !empty($canIgnore) ? 4 : 3 }}" class="text-secondary px-3 py-3">Находок нет</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endif

@if($pages > 1)
    <nav class="mt-3">
        <ul class="pagination pagination-sm mb-0">
            @for($p = 1; $p <= $pages; $p++)
                <li class="page-item {{ $p === $page ? 'active' : '' }}">
                    <a class="page-link" href="{{ request()->fullUrlWithQuery(['page' => $p]) }}">{{ $p }}</a>
                </li>
            @endfor
        </ul>
    </nav>
@endif
