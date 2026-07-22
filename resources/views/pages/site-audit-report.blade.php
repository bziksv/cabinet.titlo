@component('component.card', [
    'title' => ($meta['title'] ?? $code) . ' · краул #' . $crawl->id,
])
    @slot('css')
        <link rel="stylesheet" href="{{ asset('css/cabinet-site-audit.css') }}?v={{ @filemtime(public_path('css/cabinet-site-audit.css')) ?: time() }}">
    @endslot

    @slot('tools')
        <a href="{{ route('pages.site-audit.crawl.show', $crawl->id) }}" class="btn btn-sm btn-outline-secondary">← Сводка</a>
        <a href="{{ route('pages.site-audit.report.csv', [$crawl->id, $code]) }}{{ !empty($filterParams) ? ('?' . http_build_query($filterParams)) : '' }}" class="btn btn-sm btn-outline-primary">CSV</a>
        <a href="{{ route('pages.site-audit.report.xlsx', [$crawl->id, $code]) }}{{ !empty($filterParams) ? ('?' . http_build_query($filterParams)) : '' }}" class="btn btn-sm btn-outline-success">XLSX</a>
        <button type="button" class="btn btn-sm btn-outline-secondary cabinet-sa-print-btn" onclick="window.print()">Печать</button>
    @endslot

    <div class="cabinet-sa-page" id="sa-report-root"
         data-ignore-url="{{ route('pages.site-audit.ignore', $crawl->id) }}"
         data-restore-url="{{ route('pages.site-audit.ignore.restore', $crawl->id) }}"
         data-csrf="{{ csrf_token() }}">
        @if(session('status'))
            <div class="alert alert-success py-2">{{ session('status') }}</div>
        @endif

        <div class="mb-2 text-secondary small d-flex flex-wrap align-items-center" style="gap:8px">
            <span>
                {{ optional($project)->domain }} ·
                приоритет: <strong>{{ \App\Services\SiteAudit\SiteAuditFindingPresenter::severityLabel($meta['severity'] ?? '') }}</strong>
                · находок: <strong>{{ $total }}</strong>
                @if(!empty($filtersActive))
                    <span class="text-primary">(с фильтром)</span>
                @endif
                @if(!empty($codeWideIgnored))
                    <span class="badge text-bg-secondary">группа в игноре</span>
                @endif
            </span>
            <span class="ms-auto d-flex flex-wrap" style="gap:6px">
                @if(!empty($showIgnored))
                    <a class="btn btn-sm btn-outline-secondary" href="{{ request()->fullUrlWithQuery(['ignored' => null, 'page' => 1]) }}">Скрыть игнор</a>
                @else
                    <a class="btn btn-sm btn-outline-secondary" href="{{ request()->fullUrlWithQuery(['ignored' => 1, 'page' => 1]) }}">Показать игнор</a>
                @endif
                @if(!empty($canIgnore))
                    @if(!empty($codeWideIgnored))
                        <form method="POST" action="{{ route('pages.site-audit.ignore.restore', $crawl->id) }}" class="d-inline">
                            @csrf
                            <input type="hidden" name="scope" value="code">
                            <input type="hidden" name="code" value="{{ $code }}">
                            <button type="submit" class="btn btn-sm btn-outline-success">Вернуть все страницы в группе</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('pages.site-audit.ignore', $crawl->id) }}" class="d-inline"
                              onsubmit="return confirm('Игнорировать все страницы в отчёте «{{ $meta['title'] ?? $code }}» для проекта?');">
                            @csrf
                            <input type="hidden" name="scope" value="code">
                            <input type="hidden" name="code" value="{{ $code }}">
                            <button type="submit" class="btn btn-sm btn-outline-secondary">Игнор всех страниц в группе</button>
                        </form>
                    @endif
                @endif
            </span>
        </div>

        @php $activeGroup = $activeGroup ?? 'all'; @endphp

        <ul class="nav nav-tabs mb-3" id="sa-audit-tabs" role="tablist">
            <li class="nav-item">
                <a class="nav-link {{ $activeGroup === 'all' ? 'active' : '' }}" id="sa-tab-all" data-bs-toggle="tab" href="#sa-pane-all" role="tab">Сводка</a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ $activeGroup === 'tech' ? 'active' : '' }}" id="sa-tab-tech" data-bs-toggle="tab" href="#sa-pane-tech" role="tab">Тех. аудит</a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ $activeGroup === 'seo' ? 'active' : '' }}" id="sa-tab-seo" data-bs-toggle="tab" href="#sa-pane-seo" role="tab">SEO-аудит</a>
            </li>
        </ul>

        <div class="tab-content">
            <div class="tab-pane fade {{ $activeGroup === 'all' ? 'show active' : '' }}" id="sa-pane-all" role="tabpanel">
                <div class="cabinet-sa-buckets mb-4">
                    @foreach($bucketLabels as $key => $label)
                        <div class="cabinet-sa-bucket cabinet-sa-bucket--{{ $key }}" data-sa-bucket-preset="{{ $key }}" title="Показать отчёты: {{ $label }}">
                            <div class="cabinet-sa-bucket__label">{{ $label }}</div>
                            <div class="cabinet-sa-bucket__value">{{ (int) (($bucketsAll ?? [])[$key] ?? 0) }}</div>
                        </div>
                    @endforeach
                </div>

                <div class="cabinet-sa-layout">
                    @include('pages.partials.site-audit-report-tree', [
                        'tree' => $treeAll ?? [],
                        'bucketLabels' => $bucketLabels,
                        'crawl' => $crawl,
                        'activeCode' => $code,
                        'treeTitle' => 'Все замечания',
                        'showGroup' => true,
                    ])
                    <section>
                        @include('pages.partials.site-audit-report-body')
                    </section>
                </div>
            </div>

            <div class="tab-pane fade {{ $activeGroup === 'tech' ? 'show active' : '' }}" id="sa-pane-tech" role="tabpanel">
                <div class="cabinet-sa-buckets mb-4">
                    @foreach($bucketLabels as $key => $label)
                        <div class="cabinet-sa-bucket cabinet-sa-bucket--{{ $key }}" data-sa-bucket-preset="{{ $key }}" title="Показать отчёты: {{ $label }}">
                            <div class="cabinet-sa-bucket__label">{{ $label }}</div>
                            <div class="cabinet-sa-bucket__value">{{ (int) (($buckets ?? [])[$key] ?? 0) }}</div>
                        </div>
                    @endforeach
                </div>

                <div class="cabinet-sa-layout">
                    @include('pages.partials.site-audit-report-tree', [
                        'tree' => $tree ?? [],
                        'bucketLabels' => $bucketLabels,
                        'crawl' => $crawl,
                        'activeCode' => ($itemGroup ?? '') === 'tech' ? $code : null,
                        'treeTitle' => 'Тех. аудит',
                    ])
                    <section>
                        <div class="alert alert-light border text-secondary mb-0">
                            Детали отчёта — во вкладке
                            <a href="#sa-pane-all" data-bs-toggle="tab" data-bs-target="#sa-pane-all">Сводка</a>.
                            Слева — только тех. замечания.
                        </div>
                    </section>
                </div>
            </div>

            <div class="tab-pane fade {{ $activeGroup === 'seo' ? 'show active' : '' }}" id="sa-pane-seo" role="tabpanel">
                <div class="cabinet-sa-buckets mb-4">
                    @foreach($bucketLabels as $key => $label)
                        <div class="cabinet-sa-bucket cabinet-sa-bucket--{{ $key }}" data-sa-bucket-preset="{{ $key }}" title="Показать отчёты: {{ $label }}">
                            <div class="cabinet-sa-bucket__label">{{ $label }}</div>
                            <div class="cabinet-sa-bucket__value">{{ (int) (($bucketsSeo ?? [])[$key] ?? 0) }}</div>
                        </div>
                    @endforeach
                </div>

                <div class="cabinet-sa-layout">
                    @include('pages.partials.site-audit-report-tree', [
                        'tree' => $treeSeo ?? [],
                        'bucketLabels' => $bucketLabels,
                        'crawl' => $crawl,
                        'activeCode' => ($itemGroup ?? '') === 'seo' ? $code : null,
                        'treeTitle' => 'SEO-аудит',
                    ])
                    <section>
                        <div class="alert alert-light border text-secondary mb-0">
                            Детали отчёта — во вкладке
                            <a href="#sa-pane-all" data-bs-toggle="tab" data-bs-target="#sa-pane-all">Сводка</a>.
                            Слева — только SEO-замечания.
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </div>

    @slot('js')
        @include('pages.partials.site-audit-tree-nav-js')
    @endslot
@endcomponent
