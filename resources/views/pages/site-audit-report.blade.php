@php
    $saReportTitle = (string) ($meta['title'] ?? $code);
    $saReportSev = (string) ($meta['severity'] ?? '');
    $saReportTitleHtml = e($saReportTitle)
        . ($saReportSev !== '' ? ' ' . \App\Services\SiteAudit\SiteAuditFindingPresenter::severityBadgeHtml($saReportSev) : '')
        . ' · проверка #' . (int) $crawl->id;
    $saNeedsSelect2 = in_array(($code ?? ''), ['crawl_pages', 'crawl_images', 'security_headers'], true);
@endphp
@component('component.card', [
    'title' => $saReportTitle . ' · проверка #' . $crawl->id,
    'titleHtml' => $saReportTitleHtml,
    'documentTitle' => $saReportTitle . ' · проверка #' . $crawl->id,
])
    @slot('css')
        <link rel="stylesheet" href="{{ asset('css/cabinet-site-audit.css') }}?v={{ @filemtime(public_path('css/cabinet-site-audit.css')) ?: time() }}">
        @if($saNeedsSelect2)
            <link rel="stylesheet" href="{{ asset('plugins/select2/css/select2.min.css') }}">
            <link rel="stylesheet" href="{{ asset('plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css') }}">
        @endif
    @endslot

    @slot('tools')
        <a href="{{ route('pages.site-audit') }}" class="btn btn-sm btn-outline-secondary">← К проектам</a>
        <a href="{{ route('pages.site-audit.crawl.show', $crawl->id) }}" class="btn btn-sm btn-outline-secondary">Сводка проверки</a>
        @if(empty($isExternalModule))
            <a href="{{ route('pages.site-audit.report.csv', [$crawl->id, $code]) }}{{ !empty($filterParams) ? ('?' . http_build_query($filterParams)) : '' }}" class="btn btn-sm btn-outline-primary">CSV</a>
            <a href="{{ route('pages.site-audit.report.xlsx', [$crawl->id, $code]) }}{{ !empty($filterParams) ? ('?' . http_build_query($filterParams)) : '' }}" class="btn btn-sm btn-outline-success">XLSX</a>
            <button type="button" class="btn btn-sm btn-outline-secondary cabinet-sa-print-btn" onclick="window.print()">Печать</button>
        @endif
    @endslot

    <div class="cabinet-sa-page" id="sa-report-root"
         data-ignore-url="{{ route('pages.site-audit.ignore', $crawl->id) }}"
         data-restore-url="{{ route('pages.site-audit.ignore.restore', $crawl->id) }}"
         data-csrf="{{ csrf_token() }}">
        @if(session('status'))
            <div class="alert alert-success py-2">{{ session('status') }}</div>
        @endif

        @include('pages.partials.site-audit-module-nav', ['active' => 'module'])

        @include('pages.partials.site-audit-breadcrumbs', [
            'crawl' => $crawl,
            'project' => $project ?? optional($crawl)->project,
            'level' => 'report',
            'reportTitle' => $saReportTitle,
            'reportSeverity' => $saReportSev,
        ])

        @include('pages.partials.site-audit-crawl-live', [
            'crawl' => $crawl,
            'reloadOnFinish' => true,
        ])

        <div class="mb-2 text-secondary small d-flex flex-wrap align-items-center" style="gap:8px">
            <span>
                @if(!empty($isExternalModule))
                    {{ optional($project)->domain }} ·
                    <span class="badge text-bg-light border">отдельный модуль</span>
                    — не счётчик ошибок этой проверки
                @elseif(!empty($meta['inventory']))
                    В таблице: <strong>{{ number_format((int) $total, 0, '', ' ') }}</strong>
                    @if(!empty($filtersActive))
                        <span class="text-primary">(с фильтром)</span>
                    @endif
                @else
                    {{ optional($project)->domain }} ·
                    @if($saReportSev !== '')
                        {!! \App\Services\SiteAudit\SiteAuditFindingPresenter::severityBadgeHtml($saReportSev) !!}
                        ·
                    @endif
                    находок: <strong>{{ number_format((int) $total, 0, '', ' ') }}</strong>
                    @if(!empty($filtersActive))
                        <span class="text-primary">(с фильтром)</span>
                    @endif
                    @if(!empty($codeWideIgnored))
                        <span class="badge text-bg-secondary">группа в игноре</span>
                    @endif
                @endif
            </span>
            @if(empty($isExternalModule) && empty($meta['inventory']))
            <span class="ms-auto d-flex flex-wrap" style="gap:6px">
                @if(!empty($showIgnored))
                    <a class="btn btn-sm btn-outline-secondary" href="{{ request()->fullUrlWithQuery(['ignored' => null, 'page' => 1]) }}"
                       title="Спрятать строки, которые вы пометили «Игнор»">Скрыть игнор</a>
                @else
                    <a class="btn btn-sm btn-outline-secondary" href="{{ request()->fullUrlWithQuery(['ignored' => 1, 'page' => 1]) }}"
                       title="Показать и те URL, которые вы раньше скрыли игнором">Показать игнор</a>
                @endif
                @if(!empty($showFixed))
                    <a class="btn btn-sm btn-outline-secondary" href="{{ request()->fullUrlWithQuery(['fixed' => null, 'page' => 1]) }}"
                       title="Спрятать строки со статусом «Исправлено»">Скрыть исправленные</a>
                @else
                    <a class="btn btn-sm btn-outline-secondary" href="{{ request()->fullUrlWithQuery(['fixed' => 1, 'page' => 1]) }}"
                       title="Показать URL, которые вы пометили «Исправлено» (они не в счётчиках)">Показать исправленные</a>
                @endif
                @if(!empty($canIgnore))
                    @if(!empty($codeWideIgnored))
                        <form method="POST" action="{{ route('pages.site-audit.ignore.restore', $crawl->id) }}" class="d-inline">
                            @csrf
                            <input type="hidden" name="return_url" value="{{ request()->fullUrl() }}">
                            <input type="hidden" name="scope" value="code">
                            <input type="hidden" name="code" value="{{ $code }}">
                            <button type="submit" class="btn btn-sm btn-outline-success">Вернуть все страницы в группе</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('pages.site-audit.ignore', $crawl->id) }}" class="d-inline"
                              data-cabinet-confirm="Игнорировать все страницы в отчёте «{{ $meta['title'] ?? $code }}» для проекта?"
                              data-cabinet-confirm-title="Игнорировать группу"
                              data-cabinet-confirm-ok="Игнорировать"
                              data-cabinet-confirm-danger="1">
                            @csrf
                            <input type="hidden" name="return_url" value="{{ request()->fullUrl() }}">
                            <input type="hidden" name="scope" value="code">
                            <input type="hidden" name="code" value="{{ $code }}">
                            <button type="submit" class="btn btn-sm btn-outline-secondary">Игнор всех страниц в группе</button>
                        </form>
                    @endif
                @endif
            </span>
            @endif
        </div>

        @php $activeGroup = $activeGroup ?? 'all'; @endphp

        <ul class="nav nav-tabs mb-3" id="sa-audit-tabs" role="tablist">
            <li class="nav-item">
                <a class="nav-link {{ $activeGroup === 'all' ? 'active' : '' }}" id="sa-tab-all" data-bs-toggle="tab" href="#sa-pane-all" role="tab"
                   title="Всё вместе: тех. и SEO-проблемы">Сводка</a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ $activeGroup === 'tech' ? 'active' : '' }}" id="sa-tab-tech" data-bs-toggle="tab" href="#sa-pane-tech" role="tab"
                   title="Техника: коды ответа, редиректы, скорость, заголовки безопасности">Тех. аудит</a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ $activeGroup === 'seo' ? 'active' : '' }}" id="sa-tab-seo" data-bs-toggle="tab" href="#sa-pane-seo" role="tab"
                   title="SEO: title, описание, H1, дубли, посадочные, контент">SEO-аудит</a>
            </li>
            {{-- Те же разделы, что на сводке проверки: ведут на /crawl#… (контент там, не дублируем тяжёлую загрузку). --}}
            <li class="nav-item">
                <a class="nav-link" href="{{ route('pages.site-audit.crawl.show', $crawl->id) }}#sa-pane-plagiarism"
                   title="Выборочная проверка уникальности текста vs интернет">Антиплагиат</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="{{ route('pages.site-audit.crawl.show', $crawl->id) }}#sa-pane-relevance"
                   title="Посадочные мониторинга ↔ анализатор релевантности">Релевантность</a>
            </li>
        </ul>

        <div class="tab-content">
            <div class="tab-pane fade {{ $activeGroup === 'all' ? 'show active' : '' }}" id="sa-pane-all" role="tabpanel">
                @include('pages.partials.site-audit-buckets', [
                    'bucketLabels' => $bucketLabels,
                    'bucketValues' => $bucketsAll ?? [],
                    'crawl' => $crawl,
                    'bucketsId' => 'sa-buckets',
                    'bucketsClickable' => true,
                    'bucketsLive' => true,
                ])

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
                @include('pages.partials.site-audit-buckets', [
                    'bucketLabels' => $bucketLabels,
                    'bucketValues' => $buckets ?? [],
                    'crawl' => $crawl,
                    'bucketsClickable' => true,
                ])

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
                @include('pages.partials.site-audit-buckets', [
                    'bucketLabels' => $bucketLabels,
                    'bucketValues' => $bucketsSeo ?? [],
                    'crawl' => $crawl,
                    'bucketsClickable' => true,
                ])

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
        @include('partials.cabinet-confirm-modal')
        @include('pages.partials.site-audit-tree-nav-js')
        @include('pages.partials.site-audit-crawl-live-js')
        <script src="{{ asset('js/cabinet-site-audit-copy-url.js') }}?v={{ @filemtime(public_path('js/cabinet-site-audit-copy-url.js')) ?: time() }}" defer></script>
        @if(($code ?? '') === 'index_count_mismatch')
            <script src="{{ asset('js/cabinet-site-audit-index-extra.js') }}?v={{ @filemtime(public_path('js/cabinet-site-audit-index-extra.js')) ?: time() }}" defer></script>
        @endif
        @if(!in_array(($code ?? ''), ['crawl_pages', 'crawl_images'], true) && empty($isExternalModule))
            <script src="{{ asset('js/cabinet-site-audit-report-cols.js') }}?v={{ @filemtime(public_path('js/cabinet-site-audit-report-cols.js')) ?: time() }}" defer></script>
        @endif
        @if(!empty($saNeedsSelect2))
            <script src="{{ asset('plugins/select2/js/select2.full.min.js') }}"></script>
            @if(in_array(($code ?? ''), ['crawl_pages', 'crawl_images'], true))
                <script src="{{ asset('js/cabinet-site-audit-crawl-filters.js') }}?v={{ @filemtime(public_path('js/cabinet-site-audit-crawl-filters.js')) ?: time() }}"></script>
            @endif
            <script>
                (function () {
                    function initSaMulti() {
                        if (!window.jQuery || !jQuery.fn.select2) return;
                        var $gearPanel = jQuery('.cabinet-sa-filters-gear__panel').first();
                        jQuery('[data-sa-select2-multi]').each(function () {
                            var $el = jQuery(this);
                            if ($el.hasClass('select2-hidden-accessible')) {
                                $el.select2('destroy');
                            }
                            var opts = {
                                theme: 'bootstrap4',
                                width: '100%',
                                placeholder: $el.attr('data-placeholder') || 'Выберите…',
                                allowClear: true,
                                closeOnSelect: false,
                                language: {
                                    noResults: function () { return 'Ничего не найдено'; },
                                    searching: function () { return 'Поиск…'; }
                                }
                            };
                            if ($gearPanel.length && $el.closest('.cabinet-sa-filters-gear__panel').length) {
                                opts.dropdownParent = $gearPanel;
                            }
                            $el.select2(opts);
                        });
                    }
                    if (document.readyState === 'loading') {
                        document.addEventListener('DOMContentLoaded', initSaMulti);
                    } else {
                        initSaMulti();
                    }
                })();
            </script>
        @endif
    @endslot
@endcomponent
