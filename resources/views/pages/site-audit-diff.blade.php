@component('component.card', [
    'title' => 'Аудит сайта · сравнение #' . $crawl->id . ' ↔ #' . $baseline->id,
])
    @slot('css')
        <link rel="stylesheet" href="{{ asset('css/cabinet-site-audit.css') }}?v={{ @filemtime(public_path('css/cabinet-site-audit.css')) ?: time() }}">
    @endslot

    @slot('tools')
        <a href="{{ route('pages.site-audit.crawl.show', $crawl->id) }}" class="btn btn-sm btn-outline-secondary">← К краулу #{{ $crawl->id }}</a>
        <a href="{{ route('pages.site-audit.crawl.show', $baseline->id) }}" class="btn btn-sm btn-outline-secondary">Краул #{{ $baseline->id }}</a>
    @endslot

    <div class="cabinet-sa-page">
        <div class="d-flex flex-wrap justify-content-between align-items-start mb-3">
            <div>
                <div class="h5 mb-1">{{ optional($project)->domain ?? '—' }}</div>
                <div class="small text-muted">
                    Сравнение findings: текущий
                    <a href="{{ route('pages.site-audit.crawl.show', $crawl->id) }}">#{{ $crawl->id }}</a>
                    ({{ $diff['current']['finished_at'] ?? '—' }}, {{ $diff['current']['pages_total'] }} URL)
                    vs
                    <a href="{{ route('pages.site-audit.crawl.show', $baseline->id) }}">#{{ $baseline->id }}</a>
                    ({{ $diff['baseline']['finished_at'] ?? '—' }}, {{ $diff['baseline']['pages_total'] }} URL)
                </div>
            </div>
            <form method="GET" action="{{ route('pages.site-audit.crawl.diff', $crawl->id) }}" class="form-inline">
                <label class="small text-muted mr-2 mb-0" for="sa-diff-with">Сравнить с</label>
                <select name="with" id="sa-diff-with" class="form-control form-control-sm mr-2" onchange="this.form.submit()">
                    @foreach($candidates as $c)
                        <option value="{{ $c->id }}" @if($c->id === $baseline->id) selected @endif>
                            #{{ $c->id }} · {{ optional($c->finished_at ?: $c->created_at)->format('d.m.Y H:i') }} · {{ $c->pages_total }} URL
                        </option>
                    @endforeach
                </select>
            </form>
        </div>

        <div class="cabinet-sa-diff-summary mb-4">
            <div class="cabinet-sa-diff-pill cabinet-sa-diff-pill--worsened">
                <span class="cabinet-sa-diff-pill__n">{{ (int) ($diff['summary']['worsened'] ?? 0) }}</span>
                <span class="cabinet-sa-diff-pill__l">Ухудшилось</span>
            </div>
            <div class="cabinet-sa-diff-pill cabinet-sa-diff-pill--new">
                <span class="cabinet-sa-diff-pill__n">{{ (int) ($diff['summary']['new'] ?? 0) }}</span>
                <span class="cabinet-sa-diff-pill__l">Новые коды</span>
            </div>
            <div class="cabinet-sa-diff-pill cabinet-sa-diff-pill--improved">
                <span class="cabinet-sa-diff-pill__n">{{ (int) ($diff['summary']['improved'] ?? 0) }}</span>
                <span class="cabinet-sa-diff-pill__l">Улучшилось</span>
            </div>
            <div class="cabinet-sa-diff-pill cabinet-sa-diff-pill--gone">
                <span class="cabinet-sa-diff-pill__n">{{ (int) ($diff['summary']['gone'] ?? 0) }}</span>
                <span class="cabinet-sa-diff-pill__l">Исчезло</span>
            </div>
            <div class="cabinet-sa-diff-pill cabinet-sa-diff-pill--same">
                <span class="cabinet-sa-diff-pill__n">{{ (int) ($diff['summary']['same'] ?? 0) }}</span>
                <span class="cabinet-sa-diff-pill__l">Без изменений</span>
            </div>
        </div>

        <div class="cabinet-sa-table-wrap mb-4">
            <table class="table table-sm mb-0">
                <thead class="thead-light">
                <tr>
                    <th>Корзина</th>
                    <th class="text-right">#{{ $baseline->id }}</th>
                    <th class="text-right">#{{ $crawl->id }}</th>
                    <th class="text-right">Δ</th>
                </tr>
                </thead>
                <tbody>
                <tr>
                    <td>URL в крауле</td>
                    <td class="text-right">{{ $diff['baseline']['pages_total'] }}</td>
                    <td class="text-right">{{ $diff['current']['pages_total'] }}</td>
                    <td class="text-right">
                        @include('pages.partials.site-audit-diff-delta', ['delta' => $diff['pages_delta'], 'invert' => false])
                    </td>
                </tr>
                @foreach($bucketLabels as $key => $label)
                    @php $bd = $diff['buckets'][$key] ?? ['before'=>0,'after'=>0,'delta'=>0]; @endphp
                    <tr>
                        <td>{{ $label }}</td>
                        <td class="text-right">{{ (int) $bd['before'] }}</td>
                        <td class="text-right">{{ (int) $bd['after'] }}</td>
                        <td class="text-right">
                            @include('pages.partials.site-audit-diff-delta', ['delta' => (int) $bd['delta'], 'invert' => true])
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        <h6 class="mb-2">По отчётам</h6>
        <div class="cabinet-sa-table-wrap">
            <table class="table table-sm mb-0">
                <thead class="thead-light">
                <tr>
                    <th>Отчёт</th>
                    <th>Статус</th>
                    <th class="text-right">#{{ $baseline->id }}</th>
                    <th class="text-right">#{{ $crawl->id }}</th>
                    <th class="text-right">Δ</th>
                    <th>URL</th>
                </tr>
                </thead>
                <tbody>
                @forelse($diff['codes'] as $row)
                    <tr class="cabinet-sa-diff-row cabinet-sa-diff-row--{{ $row['status'] }}">
                        <td>
                            <a href="{{ route('pages.site-audit.report.show', [$crawl->id, $row['code']]) }}">{{ $row['title'] }}</a>
                            <div class="small text-muted">{{ $row['code'] }}</div>
                        </td>
                        <td>
                            <span class="cabinet-sa-diff-status cabinet-sa-diff-status--{{ $row['status'] }}">
                                @switch($row['status'])
                                    @case('worsened') ухудшилось @break
                                    @case('new') новый @break
                                    @case('improved') улучшилось @break
                                    @case('gone') исчез @break
                                    @default без изменений
                                @endswitch
                            </span>
                        </td>
                        <td class="text-right">{{ (int) $row['before'] }}</td>
                        <td class="text-right">{{ (int) $row['after'] }}</td>
                        <td class="text-right">
                            @include('pages.partials.site-audit-diff-delta', ['delta' => (int) $row['delta'], 'invert' => true])
                        </td>
                        <td class="small">
                            @if(!empty($row['appeared']))
                                <div class="cabinet-sa-diff-urls">
                                    <span class="text-danger">+</span>
                                    @foreach(array_slice($row['appeared'], 0, 5) as $u)
                                        <div class="cabinet-sa-url" title="{{ $u }}">{{ \Illuminate\Support\Str::limit($u, 70) }}</div>
                                    @endforeach
                                    @if(count($row['appeared']) > 5)
                                        <div class="text-muted">… ещё {{ count($row['appeared']) - 5 }}</div>
                                    @endif
                                </div>
                            @endif
                            @if(!empty($row['fixed']))
                                <div class="cabinet-sa-diff-urls mt-1">
                                    <span class="text-success">−</span>
                                    @foreach(array_slice($row['fixed'], 0, 5) as $u>
                                        <div class="cabinet-sa-url" title="{{ $u }}">{{ \Illuminate\Support\Str::limit($u, 70) }}</div>
                                    @endforeach
                                    @if(count($row['fixed']) > 5)
                                        <div class="text-muted">… ещё {{ count($row['fixed']) - 5 }}</div>
                                    @endif
                                </div>
                            @endif
                            @if(empty($row['appeared']) && empty($row['fixed']))
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">Нет данных для сравнения</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endcomponent
