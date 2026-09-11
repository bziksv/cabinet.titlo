@component('component.card', [
    'title' => 'Аудит сайта · проверка #' . $crawl->id,
])
    @slot('css')
        <link rel="stylesheet" href="{{ asset('css/cabinet-site-audit.css') }}?v={{ @filemtime(public_path('css/cabinet-site-audit.css')) ?: time() }}">
    @endslot

    @slot('tools')
        <a href="{{ route('pages.site-audit') }}" class="btn btn-sm btn-outline-secondary">← К проектам</a>
        @if(!empty($archiveCrawls) && $archiveCrawls->count() > 0)
            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#sa-archive-modal">
                Архив
                <span class="badge text-bg-light text-dark ms-1">{{ $archiveCrawls->count() }}</span>
            </button>
        @endif
        @if($crawl->status === 'done')
            <a href="{{ route('pages.site-audit.crawl.xlsx', $crawl->id) }}" class="btn btn-sm btn-outline-success">XLSX сводка</a>
            <a href="{{ route('pages.site-audit.crawl.docx', $crawl->id) }}" class="btn btn-sm btn-outline-secondary">DOCX</a>
            <button type="button" class="btn btn-sm btn-outline-secondary cabinet-sa-print-btn" onclick="window.print()">Печать</button>
            @if(!empty($compareCandidates) && $compareCandidates->count() > 0)
                <a href="{{ route('pages.site-audit.crawl.diff', $crawl->id) }}" class="btn btn-sm btn-outline-info">Сравнить с предыдущим</a>
            @endif
            @if(!empty($canManageCrawl))
            <button type="button" class="btn btn-sm btn-outline-primary" id="sa-share-btn"
                    data-create="{{ route('pages.site-audit.share.create', $crawl->id) }}"
                    data-revoke="{{ route('pages.site-audit.share.revoke', $crawl->id) }}"
                    data-url="{{ $shareUrl ?? '' }}">
                {{ !empty($shareUrl) ? 'Ссылка шаринга' : 'Поделиться' }}
            </button>
            <button type="button" class="btn btn-sm btn-outline-dark" id="sa-plan-btn"
                    data-bs-toggle="modal" data-bs-target="#sa-plan-modal"
                    data-generate="{{ route('pages.site-audit.action-plan.generate', $crawl->id) }}"
                    data-toggle="{{ route('pages.site-audit.action-plan.toggle', $crawl->id) }}"
                    data-has-ai="{{ !empty($canActionPlanAi) ? '1' : '0' }}">
                План работ
            </button>
            @endif
        @endif
        @if(!empty($canManageCrawl) && ! $crawl->isFinished())
            <form method="POST" action="{{ route('pages.site-audit.crawl.cancel', $crawl->id) }}" class="d-inline"
                  data-cabinet-confirm="Остановить проверку #{{ $crawl->id }}? Уже скачанные страницы останутся, дальше сканировать не будет."
                  data-cabinet-confirm-title="Остановка проверки"
                  data-cabinet-confirm-ok="Остановить"
                  data-cabinet-confirm-danger="1">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-danger">Остановить</button>
            </form>
        @endif
        @if(!empty($canManageCrawl) && $crawl->isFinished())
            @php
                $canResumeCrawl = (new \App\Services\SiteAudit\SiteAuditCrawlEngine())->canResume($crawl);
            @endphp
            @if($canResumeCrawl)
                <form method="POST" action="{{ route('pages.site-audit.crawl.continue', $crawl->id) }}" class="d-inline"
                      data-cabinet-confirm="Продолжить проверку #{{ $crawl->id }} с {{ number_format((int) $crawl->pages_fetched, 0, '', ' ') }} URL? Уже скачанные страницы сохранятся."
                      data-cabinet-confirm-title="Продолжить проверку"
                      data-cabinet-confirm-ok="Продолжить">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-primary">Продолжить сканирование</button>
                </form>
            @endif
            <form method="POST" action="{{ route('pages.site-audit.crawl.repeat', $crawl->id) }}" class="d-inline"
                  data-cabinet-confirm="Повторить проверку для {{ e(optional($project)->domain ?? 'проекта') }} с теми же настройками? Начнётся новая проверка с нуля."
                  data-cabinet-confirm-title="Новая проверка"
                  data-cabinet-confirm-ok="Повторить">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-secondary">Повторить</button>
            </form>
            <form method="POST" action="{{ route('pages.site-audit.crawl.destroy', $crawl->id) }}" class="d-inline"
                  data-cabinet-confirm="Удалить проверку #{{ $crawl->id }} и все её findings?"
                  data-cabinet-confirm-title="Удаление проверки"
                  data-cabinet-confirm-ok="Удалить"
                  data-cabinet-confirm-danger="1">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-sm btn-outline-danger">Удалить</button>
            </form>
        @endif
    @endslot

    <div class="cabinet-sa-page" id="sa-crawl-root"
         data-status-url="{{ route('pages.site-audit.crawl.status', $crawl->id) }}"
         data-finished="{{ $crawl->isFinished() ? '1' : '0' }}">

        @include('pages.partials.site-audit-module-nav', ['active' => 'module'])

        @include('pages.partials.site-audit-breadcrumbs', [
            'crawl' => $crawl,
            'project' => $project ?? optional($crawl)->project,
            'level' => 'crawl',
        ])

        <div class="d-flex flex-wrap justify-content-between align-items-start mb-3">
            <div>
                    <div class="h5 mb-1">{{ optional($project)->domain ?? '—' }}</div>
                <div class="small text-muted">
                    Проверка #{{ $crawl->id }}
                    @if(optional($project)->team)
                        · команда {{ $project->team->title }}
                    @endif
                    · лимит {{ number_format((int) $crawl->pages_limit, 0, '', ' ') }} URL
                    @php $s = $crawl->progress_json['settings'] ?? []; @endphp
                    @if(!empty($s))
                        · скорость {{ $s['crawl_speed'] ?? '—' }} ({{ $s['rps'] ?? '—' }} URL/с на поток)
                        · потоки {{ (int) ($s['concurrency'] ?? 1) }}
                    @endif
                    @if($crawl->started_at) · старт {{ $crawl->started_at->format('d.m.Y H:i') }} @endif
                    @if($crawl->finished_at) · конец {{ $crawl->finished_at->format('d.m.Y H:i') }} @endif
                    @if(isset(($crawl->counts_json ?? [])['click_depth_max']))
                        · глубина клика до {{ (int) $crawl->counts_json['click_depth_max'] }}
                    @endif
                    @php $unchanged = (int) (($crawl->progress_json['pages_unchanged'] ?? 0)); @endphp
                    @if($unchanged > 0)
                        · без изменений {{ $unchanged }} стр.
                    @endif
                </div>
            </div>
            @php
                $stClass = $crawl->statusCssClass();
            @endphp
            @if($crawl->isFinished())
                <span class="cabinet-sa-status cabinet-sa-status--{{ $stClass }}" id="sa-status-pill">{{ $crawl->statusLabelRu() }}</span>
            @endif
        </div>

        @include('pages.partials.site-audit-crawl-live', [
            'crawl' => $crawl,
            'reloadOnFinish' => true,
        ])

        @if($crawl->error)
            <div class="alert {{ $crawl->status === 'cancelled' ? 'alert-warning' : 'alert-danger' }}">{{ $crawl->error }}</div>
        @endif

        <div id="sa-share-box" class="alert alert-light border mb-3" style="{{ empty($shareUrl) ? 'display:none' : '' }}">
            <div class="small text-muted mb-1">Публичная ссылка (только просмотр):</div>
            <div class="input-group input-group-sm mb-2">
                <input type="text" class="form-control" id="sa-share-url" readonly value="{{ $shareUrl ?? '' }}">
                <div class="input-group-append">
                    <button type="button" class="btn btn-outline-secondary" id="sa-share-copy">Копировать</button>
                    <button type="button" class="btn btn-outline-danger" id="sa-share-revoke">Отключить</button>
                </div>
            </div>
            @if(!empty($canWhiteLabel))
                @php $swl = is_array($shareWhiteLabel ?? null) ? $shareWhiteLabel : []; @endphp
                <div class="border-top pt-2 mt-1">
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="sa-share-wl"
                               {{ !empty($swl['enabled']) ? 'checked' : '' }}>
                        <label class="form-check-label small" for="sa-share-wl">
                            White-label: без бренда Titlo (для клиента)
                        </label>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <input type="text" class="form-control form-control-sm" id="sa-share-brand"
                                   maxlength="120" placeholder="Название агентства / компании"
                                   value="{{ $swl['brand_name'] ?? '' }}">
                        </div>
                        <div class="col-md-6">
                            <input type="url" class="form-control form-control-sm" id="sa-share-brand-url"
                                   maxlength="255" placeholder="https://сайт-агентства (необяз.)"
                                   value="{{ $swl['brand_url'] ?? '' }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label small mb-1" for="sa-share-logo">Логотип (PNG/JPG/WebP, до 1 МБ)</label>
                            <input type="file" class="form-control form-control-sm" id="sa-share-logo"
                                   accept="image/png,image/jpeg,image/webp,image/gif,image/svg+xml">
                            @if(!empty($swl['brand_logo_url']))
                                <div class="d-flex align-items-center mt-2" style="gap:10px" id="sa-share-logo-preview-wrap">
                                    <img src="{{ $swl['brand_logo_url'] }}" alt="" width="40" height="40"
                                         style="object-fit:contain;border:1px solid #e5e7eb;border-radius:6px;background:#fff"
                                         id="sa-share-logo-preview">
                                    <label class="form-check small mb-0">
                                        <input type="checkbox" class="form-check-input" id="sa-share-clear-logo">
                                        Убрать логотип
                                    </label>
                                </div>
                            @endif
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="sa-share-save-wl">
                        Сохранить оформление ссылки
                    </button>
                </div>
            @else
                <div class="small text-muted mt-1">White-label (без бренда Titlo) — на платных тарифах.</div>
            @endif
        </div>

        <ul class="nav nav-tabs mb-3" id="sa-audit-tabs" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" id="sa-tab-all" data-bs-toggle="tab" href="#sa-pane-all" role="tab"
                   title="Всё вместе: тех. и SEO-проблемы">Сводка</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="sa-tab-tech" data-bs-toggle="tab" href="#sa-pane-tech" role="tab"
                   title="Техника: коды ответа, редиректы, скорость, безопасность">Тех. аудит</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="sa-tab-seo" data-bs-toggle="tab" href="#sa-pane-seo" role="tab"
                   title="SEO: title, описание, H1, дубли, посадочные">SEO-аудит</a>
            </li>
            {{-- Антиплагиат / Релевантность видны всегда: иначе #sa-pane-plagiarism с незавершённой проверки молча открывает «Сводку». --}}
            <li class="nav-item">
                <a class="nav-link" id="sa-tab-plagiarism" data-bs-toggle="tab" href="#sa-pane-plagiarism" role="tab"
                   title="Выборочная проверка уникальности текста vs интернет">Антиплагиат</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="sa-tab-relevance" data-bs-toggle="tab" href="#sa-pane-relevance" role="tab"
                   title="Посадочные мониторинга ↔ анализатор релевантности">Релевантность</a>
            </li>
            @if(!empty($historyRows) && count($historyRows) > 1)
                <li class="nav-item">
                    <a class="nav-link" id="sa-tab-dynamics" data-bs-toggle="tab" href="#sa-pane-dynamics" role="tab"
                       title="Как менялось число ошибок от проверки к проверке">Динамика</a>
                </li>
            @endif
        </ul>

        <div class="tab-content">
            <div class="tab-pane fade show active" id="sa-pane-all" role="tabpanel">
                @include('pages.partials.site-audit-buckets', [
                    'bucketLabels' => $bucketLabels,
                    'bucketValues' => $bucketsAll ?? $buckets,
                    'crawl' => $crawl,
                    'bucketsId' => 'sa-buckets',
                    'bucketsClickable' => true,
                    'bucketsLive' => true,
                ])

                <div class="cabinet-sa-layout">
                    <aside class="cabinet-sa-tree" data-sa-tree data-crawl-id="{{ (int) $crawl->id }}">
                        <div class="px-3 py-2 border-bottom fw-semibold small">Все замечания</div>
                        @include('pages.partials.site-audit-tree-controls')
                        @foreach($bucketLabels as $sev => $label)
                            <div class="cabinet-sa-tree__group" data-severity-group="{{ $sev }}">
                                <div class="cabinet-sa-tree__group-title">{{ $label }}</div>
                                @foreach(($treeAll[$sev] ?? []) as $item)
                                    @include('pages.partials.site-audit-tree-item', [
                                        'item' => $item,
                                        'sev' => $sev,
                                        'crawl' => $crawl,
                                        'showGroup' => true,
                                    ])
                                @endforeach
                            </div>
                        @endforeach
                    </aside>
                    <section>
                        <h5 class="mb-3">Сводка аудита</h5>
                        <p class="text-secondary small">Полный перечень замечаний: тех. и SEO. Находки с нулём скрыты в таблице справа.</p>
                        @include('pages.partials.site-audit-hot-table', ['counts' => $counts, 'findingsCatalog' => $findingsCatalog, 'crawl' => $crawl, 'group' => null])
                    </section>
                </div>
            </div>

            <div class="tab-pane fade" id="sa-pane-tech" role="tabpanel">
                @include('pages.partials.site-audit-buckets', [
                    'bucketLabels' => $bucketLabels,
                    'bucketValues' => $buckets,
                    'crawl' => $crawl,
                    'bucketsClickable' => true,
                ])

                <div class="cabinet-sa-layout">
                    <aside class="cabinet-sa-tree" data-sa-tree data-crawl-id="{{ (int) $crawl->id }}">
                        <div class="px-3 py-2 border-bottom fw-semibold small">Тех. аудит</div>
                        @include('pages.partials.site-audit-tree-controls')
                        @foreach($bucketLabels as $sev => $label)
                            <div class="cabinet-sa-tree__group" data-severity-group="{{ $sev }}">
                                <div class="cabinet-sa-tree__group-title">{{ $label }}</div>
                                @foreach(($tree[$sev] ?? []) as $item)
                                    @include('pages.partials.site-audit-tree-item', [
                                        'item' => $item,
                                        'sev' => $sev,
                                        'crawl' => $crawl,
                                    ])
                                @endforeach
                            </div>
                        @endforeach
                    </aside>
                    <section>
                        <h5 class="mb-3">Сводный тех. аудит</h5>
                        <p class="text-secondary small">HTTP, редиректы, robots, разметка, размер страниц.</p>
                        @include('pages.partials.site-audit-module-links', ['linkGroup' => 'tech'])
                        @include('pages.partials.site-audit-hot-table', ['counts' => $counts, 'findingsCatalog' => $findingsCatalog, 'crawl' => $crawl, 'group' => 'tech'])
                    </section>
                </div>
            </div>

            <div class="tab-pane fade" id="sa-pane-seo" role="tabpanel">
                @include('pages.partials.site-audit-buckets', [
                    'bucketLabels' => $bucketLabels,
                    'bucketValues' => $bucketsSeo ?? [],
                    'crawl' => $crawl,
                    'bucketsClickable' => true,
                ])

                <div class="cabinet-sa-layout">
                    <aside class="cabinet-sa-tree" data-sa-tree data-crawl-id="{{ (int) $crawl->id }}">
                        <div class="px-3 py-2 border-bottom fw-semibold small">SEO-аудит</div>
                        @include('pages.partials.site-audit-tree-controls')
                        @foreach($bucketLabels as $sev => $label)
                            <div class="cabinet-sa-tree__group" data-severity-group="{{ $sev }}">
                                <div class="cabinet-sa-tree__group-title">{{ $label }}</div>
                                @foreach(($treeSeo[$sev] ?? []) as $item)
                                    @include('pages.partials.site-audit-tree-item', [
                                        'item' => $item,
                                        'sev' => $sev,
                                        'crawl' => $crawl,
                                    ])
                                @endforeach
                            </div>
                        @endforeach
                    </aside>
                    <section>
                        <h5 class="mb-3">Сводный SEO-аудит</h5>
                        <p class="text-secondary small">Title/Description, H1, canonical, noindex, дубли, похожие страницы, thin content.</p>
                        @include('pages.partials.site-audit-module-links', ['linkGroup' => 'seo'])
                        @include('pages.partials.site-audit-hot-table', ['counts' => $counts, 'findingsCatalog' => $findingsCatalog, 'crawl' => $crawl, 'group' => 'seo'])
                    </section>
                </div>
            </div>

            @include('pages.partials.site-audit-plagiarism-tab')
            @include('pages.partials.site-audit-relevance-tab')

            @if(!empty($historyRows) && count($historyRows) > 1)
                <div class="tab-pane fade" id="sa-pane-dynamics" role="tabpanel">
                    <h6 class="mb-2">Динамика тех. аудита по проверкам</h6>
                    <div class="cabinet-sa-table-wrap mb-4">
                        <table class="table table-sm mb-0">
                            <thead class="thead-light">
                            <tr>
                                <th>Проверка</th>
                                <th>Дата</th>
                                <th>Страниц</th>
                                @foreach($bucketLabels as $label)
                                    <th>{{ $label }}</th>
                                @endforeach
                            </tr>
                            </thead>
                            <tbody>
                            @foreach(collect($historyRows)->sortBy(function ($row) { return $row['crawl']->id; }) as $row)
                                @php $h = $row['crawl']; $hb = $row['tech']; @endphp
                                <tr class="{{ $h->id === $crawl->id ? 'table-active' : '' }}">
                                    <td>
                                        <a href="{{ route('pages.site-audit.crawl.show', $h->id) }}">#{{ $h->id }}</a>
                                        @if($h->id !== $crawl->id && $crawl->status === 'done')
                                            <a class="small d-block" href="{{ route('pages.site-audit.crawl.diff', ['id' => $crawl->id, 'with' => $h->id]) }}">сравнить</a>
                                        @endif
                                    </td>
                                    <td class="small text-muted">{{ optional($h->finished_at ?: $h->created_at)->format('d.m.Y H:i') }}</td>
                                    <td>{{ $h->pages_total }}</td>
                                    <td>{{ (int) ($hb['critical'] ?? 0) }}</td>
                                    <td>{{ (int) ($hb['other'] ?? 0) }}</td>
                                    <td>{{ (int) ($hb['warning'] ?? 0) }}</td>
                                    <td>{{ (int) ($hb['info'] ?? 0) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>

                    <h6 class="mb-2">Динамика SEO по проверкам</h6>
                    <div class="cabinet-sa-table-wrap">
                        <table class="table table-sm mb-0">
                            <thead class="thead-light">
                            <tr>
                                <th>Проверка</th>
                                <th>Дата</th>
                                <th>Страниц</th>
                                @foreach($bucketLabels as $label)
                                    <th>{{ $label }}</th>
                                @endforeach
                            </tr>
                            </thead>
                            <tbody>
                            @foreach(collect($historyRows)->sortBy(function ($row) { return $row['crawl']->id; }) as $row)
                                @php $h = $row['crawl']; $hb = $row['seo']; @endphp
                                <tr class="{{ $h->id === $crawl->id ? 'table-active' : '' }}">
                                    <td>
                                        <a href="{{ route('pages.site-audit.crawl.show', $h->id) }}">#{{ $h->id }}</a>
                                        @if($h->id !== $crawl->id && $crawl->status === 'done')
                                            <a class="small d-block" href="{{ route('pages.site-audit.crawl.diff', ['id' => $crawl->id, 'with' => $h->id]) }}">сравнить</a>
                                        @endif
                                    </td>
                                    <td class="small text-muted">{{ optional($h->finished_at ?: $h->created_at)->format('d.m.Y H:i') }}</td>
                                    <td>{{ $h->pages_total }}</td>
                                    <td>{{ (int) ($hb['critical'] ?? 0) }}</td>
                                    <td>{{ (int) ($hb['other'] ?? 0) }}</td>
                                    <td>{{ (int) ($hb['warning'] ?? 0) }}</td>
                                    <td>{{ (int) ($hb['info'] ?? 0) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </div>

    @include('pages.partials.site-audit-archive')

    <div class="modal fade" id="sa-plan-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">План работ по аудиту</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <button type="button" class="btn btn-sm btn-primary" id="sa-plan-gen">Сформировать</button>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="sa-plan-gen-ai"
                                style="{{ empty($canActionPlanAi) ? 'display:none' : '' }}">
                            Сформировать + ИИ-резюме
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="sa-plan-copy">Копировать Markdown</button>
                    </div>
                    <div id="sa-plan-empty" class="text-muted small" style="{{ !empty($actionPlan['items']) ? 'display:none' : '' }}">
                        Нажмите «Сформировать» — задачи из findings (по приоритету) с подсказками «как исправить».
                    </div>
                    <div id="sa-plan-ai" class="alert alert-light border small mb-3" style="{{ empty($actionPlan['ai_summary']) ? 'display:none' : '' }}">
                        <div class="fw-semibold mb-1">Резюме ИИ</div>
                        <div id="sa-plan-ai-text" style="white-space:pre-wrap">{{ $actionPlan['ai_summary'] ?? '' }}</div>
                    </div>
                    <ol id="sa-plan-list" class="list-group list-group-numbered">
                        @foreach(($actionPlan['items'] ?? []) as $it)
                            <li class="list-group-item d-flex gap-2 align-items-start" data-code="{{ $it['code'] }}">
                                <input type="checkbox" class="form-check-input mt-1 sa-plan-done" {{ !empty($it['done']) ? 'checked' : '' }}>
                                <div class="flex-grow-1">
                                    <div class="fw-semibold">
                                        {{ $it['title'] }}
                                        <span class="badge text-bg-secondary">{{ $it['severity'] }}</span>
                                        <span class="badge text-bg-light text-dark">{{ (int) $it['count'] }}</span>
                                    </div>
                                    <div class="small text-muted">{{ $it['how'] }}</div>
                                    @if(!empty($it['sample_urls']))
                                        <div class="small mt-1">
                                            @foreach(array_slice($it['sample_urls'], 0, 2) as $u)
                                                <div class="text-truncate"><a href="{{ $u }}" target="_blank" rel="noopener">{{ $u }}</a></div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ol>
                    <textarea id="sa-plan-md" class="d-none">{{ $actionPlan['markdown'] ?? '' }}</textarea>
                </div>
            </div>
        </div>
    </div>

    @slot('js')
        @include('partials.cabinet-confirm-modal')
        @include('pages.partials.site-audit-tree-nav-js')
        <script>
            (function () {
                var tokenMeta = document.querySelector('meta[name="csrf-token"]');
                var csrf = tokenMeta ? tokenMeta.getAttribute('content') : '';
                var planBtn = document.getElementById('sa-plan-btn');
                var planGen = document.getElementById('sa-plan-gen');
                var planGenAi = document.getElementById('sa-plan-gen-ai');
                var planCopy = document.getElementById('sa-plan-copy');
                var planList = document.getElementById('sa-plan-list');
                var planEmpty = document.getElementById('sa-plan-empty');
                var planAi = document.getElementById('sa-plan-ai');
                var planAiText = document.getElementById('sa-plan-ai-text');
                var planMd = document.getElementById('sa-plan-md');

                function renderPlan(plan) {
                    if (!planList) return;
                    planList.innerHTML = '';
                    var items = (plan && plan.items) ? plan.items : [];
                    if (planEmpty) planEmpty.style.display = items.length ? 'none' : '';
                    if (planMd) planMd.value = (plan && plan.markdown) ? plan.markdown : '';
                    if (planAi && planAiText) {
                        if (plan && plan.ai_summary) {
                            planAi.style.display = '';
                            planAiText.textContent = plan.ai_summary;
                        } else {
                            planAi.style.display = 'none';
                            planAiText.textContent = '';
                        }
                    }
                    items.forEach(function (it) {
                        var li = document.createElement('li');
                        li.className = 'list-group-item d-flex gap-2 align-items-start';
                        li.setAttribute('data-code', it.code || '');
                        var cb = document.createElement('input');
                        cb.type = 'checkbox';
                        cb.className = 'form-check-input mt-1 sa-plan-done';
                        cb.checked = !!it.done;
                        var box = document.createElement('div');
                        box.className = 'flex-grow-1';
                        var title = document.createElement('div');
                        title.className = 'fw-semibold';
                        title.appendChild(document.createTextNode((it.title || it.code || '') + ' '));
                        var b1 = document.createElement('span');
                        b1.className = 'badge text-bg-secondary';
                        b1.textContent = it.severity || '';
                        var b2 = document.createElement('span');
                        b2.className = 'badge text-bg-light text-dark ms-1';
                        b2.textContent = String(it.count || 0);
                        title.appendChild(b1);
                        title.appendChild(b2);
                        var how = document.createElement('div');
                        how.className = 'small text-muted';
                        how.textContent = it.how || '';
                        box.appendChild(title);
                        box.appendChild(how);
                        if (it.sample_urls && it.sample_urls.length) {
                            var samples = document.createElement('div');
                            samples.className = 'small mt-1';
                            it.sample_urls.slice(0, 2).forEach(function (u) {
                                var row = document.createElement('div');
                                row.className = 'text-truncate';
                                var a = document.createElement('a');
                                a.href = u; a.target = '_blank'; a.rel = 'noopener'; a.textContent = u;
                                row.appendChild(a);
                                samples.appendChild(row);
                            });
                            box.appendChild(samples);
                        }
                        li.appendChild(cb);
                        li.appendChild(box);
                        planList.appendChild(li);
                    });
                }

                function generatePlan(withAi) {
                    if (!planBtn) return;
                    var fd = new FormData();
                    fd.append('ai', withAi ? '1' : '0');
                    if (planGen) planGen.disabled = true;
                    if (planGenAi) planGenAi.disabled = true;
                    fetch(planBtn.getAttribute('data-generate'), {
                        method: 'POST',
                        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                        body: fd
                    }).then(function (r) { return r.json(); })
                      .then(function (j) {
                          if (j.ok && j.plan) renderPlan(j.plan);
                          else alert((j && j.message) ? j.message : 'Не удалось сформировать план');
                      })
                      .finally(function () {
                          if (planGen) planGen.disabled = false;
                          if (planGenAi) planGenAi.disabled = false;
                      });
                }

                if (planGen) planGen.addEventListener('click', function () { generatePlan(false); });
                if (planGenAi) planGenAi.addEventListener('click', function () { generatePlan(true); });
                if (planCopy) {
                    planCopy.addEventListener('click', function () {
                        if (!planMd || !planMd.value) { alert('Сначала сформируйте план'); return; }
                        planMd.classList.remove('d-none');
                        planMd.select();
                        document.execCommand('copy');
                        planMd.classList.add('d-none');
                    });
                }
                if (planList && planBtn) {
                    planList.addEventListener('change', function (e) {
                        var t = e.target;
                        if (!t || !t.classList.contains('sa-plan-done')) return;
                        var li = t.closest('[data-code]');
                        if (!li) return;
                        var fd = new FormData();
                        fd.append('code', li.getAttribute('data-code') || '');
                        fd.append('done', t.checked ? '1' : '0');
                        fetch(planBtn.getAttribute('data-toggle'), {
                            method: 'POST',
                            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                            body: fd
                        }).then(function (r) { return r.json(); })
                          .then(function (j) {
                              if (j.ok && j.plan && planMd) planMd.value = j.plan.markdown || '';
                          });
                    });
                }
            })();
        </script>
        <script>
            (function () {
                var tokenMeta = document.querySelector('meta[name="csrf-token"]');
                var csrf = tokenMeta ? tokenMeta.getAttribute('content') : '';
                var shareBtn = document.getElementById('sa-share-btn');
                var shareBox = document.getElementById('sa-share-box');
                var shareUrl = document.getElementById('sa-share-url');
                var shareCopy = document.getElementById('sa-share-copy');
                var shareRevoke = document.getElementById('sa-share-revoke');
                var shareWl = document.getElementById('sa-share-wl');
                var shareBrand = document.getElementById('sa-share-brand');
                var shareBrandUrl = document.getElementById('sa-share-brand-url');
                var shareLogo = document.getElementById('sa-share-logo');
                var shareClearLogo = document.getElementById('sa-share-clear-logo');
                var shareSaveWl = document.getElementById('sa-share-save-wl');

                if (window.location.hash === '#sa-archive') {
                    var arch = document.getElementById('sa-archive-modal');
                    if (arch && window.bootstrap && bootstrap.Modal) {
                        bootstrap.Modal.getOrCreateInstance(arch).show();
                    } else if (arch && window.jQuery) {
                        window.jQuery(arch).modal('show');
                    }
                }

                // Deep-link с отчёта: /crawl/N#sa-pane-plagiarism|relevance|dynamics
                // Откладываем: Bootstrap Tab иногда ещё не готов в том же тике, что inline-скрипт.
                function activateHashTab(hash) {
                    hash = hash || window.location.hash || '';
                    if (hash.indexOf('#sa-pane-') !== 0) return false;
                    var tab = document.querySelector('#sa-audit-tabs a[href="' + hash + '"]');
                    if (!tab) return false;
                    var shown = false;
                    try {
                        if (window.bootstrap && bootstrap.Tab && typeof bootstrap.Tab.getOrCreateInstance === 'function') {
                            bootstrap.Tab.getOrCreateInstance(tab).show();
                            shown = true;
                        }
                    } catch (e) { /* fallback below */ }
                    if (!shown) {
                        try {
                            if (window.jQuery && jQuery.fn.tab) {
                                window.jQuery(tab).tab('show');
                                shown = true;
                            }
                        } catch (e2) { /* fallback below */ }
                    }
                    if (!shown) {
                        // Ручной fallback: классы tab/pill Bootstrap 4/5
                        var tabs = document.querySelectorAll('#sa-audit-tabs a[data-bs-toggle="tab"], #sa-audit-tabs a[data-toggle="tab"]');
                        for (var i = 0; i < tabs.length; i++) {
                            tabs[i].classList.remove('active');
                            tabs[i].setAttribute('aria-selected', 'false');
                        }
                        tab.classList.add('active');
                        tab.setAttribute('aria-selected', 'true');
                        var panes = document.querySelectorAll('.tab-content > .tab-pane');
                        for (var j = 0; j < panes.length; j++) {
                            panes[j].classList.remove('show', 'active');
                        }
                        var pane = document.querySelector(hash);
                        if (pane) {
                            pane.classList.add('show', 'active');
                        }
                        tab.dispatchEvent(new Event('shown.bs.tab', { bubbles: true }));
                    }
                    // Lazy-вкладки: не ждём только shown.bs.tab (на части сборок BS он молчит).
                    if (hash === '#sa-pane-plagiarism' && typeof window.__saLoadPlagiarismCandidates === 'function') {
                        setTimeout(window.__saLoadPlagiarismCandidates, 0);
                        setTimeout(window.__saLoadPlagiarismCandidates, 120);
                    }
                    if (hash === '#sa-pane-relevance' && typeof window.__saLoadRelevanceRows === 'function') {
                        setTimeout(window.__saLoadRelevanceRows, 0);
                        setTimeout(window.__saLoadRelevanceRows, 120);
                    }
                    return true;
                }
                setTimeout(function () { activateHashTab(); }, 0);
                setTimeout(function () { activateHashTab(); }, 50);
                setTimeout(function () { activateHashTab(); }, 300);
                window.addEventListener('hashchange', function () { activateHashTab(); });

                function showShare(url) {
                    if (shareUrl) shareUrl.value = url || '';
                    if (shareBox) shareBox.style.display = url ? '' : 'none';
                    if (shareBtn) shareBtn.textContent = url ? 'Ссылка шаринга' : 'Поделиться';
                }

                function sharePayload() {
                    var fd = new FormData();
                    fd.append('white_label', (shareWl && shareWl.checked) ? '1' : '0');
                    if (shareBrand) fd.append('brand_name', shareBrand.value || '');
                    if (shareBrandUrl) fd.append('brand_url', shareBrandUrl.value || '');
                    if (shareClearLogo && shareClearLogo.checked) fd.append('clear_logo', '1');
                    if (shareLogo && shareLogo.files && shareLogo.files[0]) {
                        fd.append('brand_logo', shareLogo.files[0]);
                    }
                    return fd;
                }

                function postShare(url, body) {
                    var headers = {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf
                    };
                    var opts = { method: 'POST', headers: headers };
                    if (body) opts.body = body;
                    return fetch(url, opts).then(function (r) { return r.json(); });
                }

                if (shareBtn) {
                    shareBtn.addEventListener('click', function () {
                        var existing = shareBtn.getAttribute('data-url');
                        if (existing) {
                            showShare(existing);
                            return;
                        }
                        postShare(shareBtn.getAttribute('data-create'), sharePayload())
                          .then(function (j) {
                              if (j.ok && j.url) {
                                  shareBtn.setAttribute('data-url', j.url);
                                  showShare(j.url);
                              } else {
                                  alert((j && j.message) ? j.message : 'Не удалось создать ссылку');
                              }
                          });
                    });
                }
                if (shareSaveWl) {
                    shareSaveWl.addEventListener('click', function () {
                        if (!shareBtn) return;
                        postShare(shareBtn.getAttribute('data-create'), sharePayload())
                          .then(function (j) {
                              if (j.ok && j.url) {
                                  shareBtn.setAttribute('data-url', j.url);
                                  showShare(j.url);
                                  alert('Оформление ссылки сохранено');
                              } else {
                                  alert((j && j.message) ? j.message : 'Не удалось сохранить');
                              }
                          });
                    });
                }
                if (shareCopy) {
                    shareCopy.addEventListener('click', function () {
                        if (!shareUrl || !shareUrl.value) return;
                        shareUrl.select();
                        document.execCommand('copy');
                    });
                }
                if (shareRevoke) {
                    shareRevoke.addEventListener('click', function () {
                        if (!shareBtn) return;
                        postShare(shareBtn.getAttribute('data-revoke'))
                          .then(function (j) {
                              if (j.ok) {
                                  shareBtn.setAttribute('data-url', '');
                                  showShare('');
                              }
                          });
                    });
                }

                (function initPlagiarismTab() {
                    var pane = document.getElementById('sa-pane-plagiarism');
                    if (!pane) return;
                    var max = parseInt(pane.getAttribute('data-max') || '10', 10) || 10;
                    var warn = parseFloat(pane.getAttribute('data-warn') || '70') || 70;
                    var startUrl = pane.getAttribute('data-start-url');
                    var statusUrl = pane.getAttribute('data-status-url');
                    var candidatesUrl = pane.getAttribute('data-candidates-url') || '';
                    var candidatesLazy = pane.getAttribute('data-candidates-lazy') === '1';
                    var csrf = pane.getAttribute('data-csrf');
                    var progressWrap = document.getElementById('sa-plag-progress');
                    var progressLabel = document.getElementById('sa-plag-progress-label');
                    var progressBar = document.getElementById('sa-plag-progress-bar');
                    var statusEl = document.getElementById('sa-plag-status');
                    var errorEl = document.getElementById('sa-plag-error');
                    var resultsBody = document.getElementById('sa-plag-results');
                    var costEl = document.getElementById('sa-plag-cost');
                    var remainingEl = document.getElementById('sa-plag-remaining');
                    var limitEl = document.getElementById('sa-plag-limit');
                    var limitHint = document.getElementById('sa-plag-limit-hint');
                    var reportLink = document.getElementById('sa-plag-report-link');
                    var pollTimer = null;
                    var candidatesLoaded = !candidatesLazy;
                    var candidatesLoading = false;
                    var limitsLoaded = false;
                    var selectedMap = {};
                    var filterTimer = null;
                    var lastFilterQ = '';

                    function esc(s) {
                        return String(s == null ? '' : s)
                            .replace(/&/g, '&amp;')
                            .replace(/</g, '&lt;')
                            .replace(/"/g, '&quot;');
                    }
                    function short(s, n) {
                        s = String(s == null ? '' : s);
                        return s.length > n ? s.slice(0, n) : s;
                    }
                    function fmtNum(n) {
                        return String(n).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
                    }
                    function cbs() {
                        return Array.prototype.slice.call(document.querySelectorAll('.sa-plag-cb'));
                    }
                    function selectedUrls() {
                        return Object.keys(selectedMap);
                    }
                    function runBtn() { return document.getElementById('sa-plag-run'); }
                    function selectedEl() { return document.getElementById('sa-plag-selected'); }
                    function syncCheckboxDisabled() {
                        var n = selectedUrls().length;
                        var rb = runBtn();
                        var running = rb && rb.disabled && rb.textContent.indexOf('Проверка') === 0;
                        cbs().forEach(function (c) {
                            if (running) {
                                c.disabled = true;
                                return;
                            }
                            if (!c.checked && n >= max) c.disabled = true;
                            else c.disabled = false;
                        });
                    }
                    function updateSelected() {
                        var n = selectedUrls().length;
                        var el = selectedEl();
                        if (el) el.textContent = 'Выбрано: ' + n + ' / ' + max;
                        syncCheckboxDisabled();
                    }
                    function setUrlSelected(url, on) {
                        if (!url) return;
                        if (on) {
                            if (selectedUrls().length >= max && !selectedMap[url]) return false;
                            selectedMap[url] = true;
                        } else {
                            delete selectedMap[url];
                        }
                        return true;
                    }
                    function setRunning(on) {
                        var rb = runBtn();
                        if (rb) {
                            rb.disabled = !!on;
                            rb.textContent = on ? 'Проверка…' : 'Проверить выбранные';
                        }
                        cbs().forEach(function (c) { c.disabled = !!on; });
                        if (progressWrap) progressWrap.style.display = on ? '' : 'none';
                    }
                    function renderState(state, meta) {
                        state = state || {};
                        var st = state.status || 'idle';
                        var running = st === 'queued' || st === 'running';
                        setRunning(running);
                        if (statusEl) statusEl.textContent = st;
                        var done = parseInt(state.done || 0, 10);
                        var total = parseInt(state.total || 0, 10) || 1;
                        if (progressLabel) progressLabel.textContent = done + ' / ' + (state.total || 0);
                        if (progressBar) progressBar.style.width = Math.min(100, Math.round(100 * done / total)) + '%';
                        if (errorEl) {
                            if (state.error) {
                                errorEl.style.display = '';
                                errorEl.textContent = state.error;
                            } else {
                                errorEl.style.display = 'none';
                                errorEl.textContent = '';
                            }
                        }
                        if (costEl) {
                            costEl.textContent = state.cost_spent
                                ? ('Списано зондов: ' + state.cost_spent)
                                : '';
                        }
                        if (remainingEl && meta && meta.remaining != null) {
                            remainingEl.textContent = meta.remaining;
                            limitsLoaded = true;
                        }
                        if (limitEl && meta && meta.limit != null) {
                            limitEl.textContent = meta.limit;
                            limitsLoaded = true;
                        }
                        if (limitHint && limitsLoaded) {
                            limitHint.style.display = 'none';
                        }
                        if (reportLink && (state.rows || []).length) {
                            reportLink.style.display = '';
                            if (meta && meta.report_url) reportLink.setAttribute('href', meta.report_url);
                        }
                        if (resultsBody) {
                            var rows = state.rows || [];
                            if (!rows.length) {
                                resultsBody.innerHTML = '<tr id="sa-plag-empty"><td colspan="5" class="text-muted small">Ещё не запускали</td></tr>';
                            } else {
                                resultsBody.innerHTML = rows.map(function (row) {
                                    var low = row.uniqueness_pct != null && row.uniqueness_pct < warn;
                                    var src = (row.sources || []).slice(0, 2).map(function (s) {
                                        return '<div><a href="' + esc(s.url || '#') + '" target="_blank" rel="noopener">' +
                                            esc(short(s.url || '', 40)) + '</a> (' + (s.overlap_pct || 0) + '%)</div>';
                                    }).join('') || '—';
                                    return '<tr class="' + (low ? 'table-warning' : '') + '">' +
                                        '<td class="small"><a href="' + esc(row.url || '#') + '" target="_blank" rel="noopener">' +
                                        esc(short(row.url || '', 60)) + '</a></td>' +
                                        '<td>' + (row.uniqueness_pct != null ? row.uniqueness_pct + '%' : '—') + '</td>' +
                                        '<td>' + (row.matched_pct != null ? row.matched_pct + '%' : '—') + '</td>' +
                                        '<td class="small">' + src + '</td>' +
                                        '<td class="small text-danger">' + esc(row.error || '') + '</td>' +
                                        '</tr>';
                                }).join('');
                            }
                        }
                        if (running) {
                            if (!pollTimer) pollTimer = setInterval(poll, 2500);
                        } else if (pollTimer) {
                            clearInterval(pollTimer);
                            pollTimer = null;
                        }
                    }
                    function poll() {
                        fetch(statusUrl, { headers: { Accept: 'application/json' } })
                            .then(function (r) { return r.json(); })
                            .then(function (j) {
                                if (!j || !j.ok) return;
                                renderState(j.state, j);
                            })
                            .catch(function () {});
                    }
                    function bindCandidateUi() {
                        cbs().forEach(function (c) {
                            c.checked = !!selectedMap[c.value];
                            c.addEventListener('change', function () {
                                if (c.checked) {
                                    if (!setUrlSelected(c.value, true)) c.checked = false;
                                } else {
                                    setUrlSelected(c.value, false);
                                }
                                updateSelected();
                            });
                        });
                        var landBtn = document.getElementById('sa-plag-landings');
                        var clearBtn = document.getElementById('sa-plag-clear');
                        var filterEl = document.getElementById('sa-plag-filter');
                        var rb = runBtn();
                        if (landBtn) {
                            landBtn.addEventListener('click', function () {
                                selectedMap = {};
                                var n = 0;
                                cbs().forEach(function (c) {
                                    var want = c.getAttribute('data-landing') === '1' && n < max;
                                    c.checked = want;
                                    if (want) {
                                        selectedMap[c.value] = true;
                                        n++;
                                    }
                                });
                                updateSelected();
                            });
                        }
                        if (clearBtn) {
                            clearBtn.addEventListener('click', function () {
                                selectedMap = {};
                                cbs().forEach(function (c) { c.checked = false; });
                                updateSelected();
                            });
                        }
                        if (filterEl) {
                            filterEl.value = lastFilterQ;
                            filterEl.addEventListener('input', function () {
                                var q = String(filterEl.value || '').trim();
                                if (filterTimer) clearTimeout(filterTimer);
                                filterTimer = setTimeout(function () {
                                    fetchCandidates(q, true);
                                }, 320);
                            });
                        }
                        if (rb) {
                            rb.addEventListener('click', function () {
                                var urls = selectedUrls();
                                if (!urls.length) {
                                    alert('Выберите URL');
                                    return;
                                }
                                setRunning(true);
                                fetch(startUrl, {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'Accept': 'application/json',
                                        'X-CSRF-TOKEN': csrf
                                    },
                                    body: JSON.stringify({ urls: urls })
                                })
                                    .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
                                    .then(function (pack) {
                                        if (!pack.ok) {
                                            setRunning(false);
                                            alert((pack.j && pack.j.message) ? pack.j.message : 'Не удалось запустить');
                                            return;
                                        }
                                        renderState(pack.j.state, pack.j);
                                        poll();
                                    })
                                    .catch(function () {
                                        setRunning(false);
                                        alert('Ошибка сети');
                                    });
                            });
                        }
                        updateSelected();
                    }
                    function renderCandidates(list, meta) {
                        var wrap = document.getElementById('sa-plag-candidates-wrap');
                        if (!wrap) return;
                        meta = meta || {};
                        var q = String(meta.q || '');
                        lastFilterQ = q;
                        var total = parseInt(meta.total != null ? meta.total : 0, 10) || 0;
                        if (!list || !list.length) {
                            var emptyMsg = q
                                ? ('По запросу «' + esc(q) + '» ничего не найдено. Уточните URL или title.')
                                : 'В этой проверке нет страниц для выбора.';
                            wrap.innerHTML =
                                '<div class="d-flex flex-wrap align-items-center mb-2" style="gap:8px">' +
                                '<span class="small text-muted" id="sa-plag-selected">Выбрано: ' + selectedUrls().length + ' / ' + max + '</span>' +
                                '<input type="search" class="form-control form-control-sm" id="sa-plag-filter" placeholder="Найти страницу по URL или title…" style="max-width:280px" value="' + esc(q) + '">' +
                                '<button type="button" class="btn btn-sm btn-primary ms-auto" id="sa-plag-run">Проверить выбранные</button>' +
                                '</div>' +
                                '<div class="alert alert-light border mb-0">' + emptyMsg +
                                (total ? (' Всего в проверке: ' + fmtNum(total) + '.') : '') +
                                '</div>';
                            bindCandidateUi();
                            return;
                        }
                        var rows = list.map(function (c) {
                            var url = String(c.url || '');
                            var title = String(c.title || '—');
                            var checked = selectedMap[url] ? ' checked' : '';
                            return '<tr data-sa-plag-row>' +
                                '<td><input type="checkbox" class="sa-plag-cb" value="' + esc(url) + '" data-landing="' + (c.is_landing ? '1' : '0') + '"' + checked + '></td>' +
                                '<td class="small"><a href="' + esc(url) + '" target="_blank" rel="noopener">' + esc(short(url, 70)) + '</a></td>' +
                                '<td class="small text-muted">' + esc(short(title, 50)) + '</td>' +
                                '<td>' + fmtNum(parseInt(c.word_count || 0, 10) || 0) + '</td>' +
                                '<td>' + (c.is_landing ? '<span class="badge bg-info text-dark">посадочная</span>' : '') + '</td>' +
                                '</tr>';
                        }).join('');
                        var listNote;
                        if (q) {
                            listNote = 'Найдено: <strong>' + fmtNum(list.length) + '</strong>' +
                                (total ? (' · в проверке всего ' + fmtNum(total)) : '') +
                                ' · выбор сохраняется при поиске';
                        } else if (total > list.length) {
                            listNote = 'Показаны <strong>' + fmtNum(list.length) + '</strong> из <strong>' + fmtNum(total) + '</strong>' +
                                ' (посадочные + самые «текстовые»). Остальные — через поиск по URL/title.';
                        } else {
                            listNote = 'В списке: <strong>' + fmtNum(list.length) + '</strong> стр. этой проверки.';
                        }
                        if (selectedUrls().length) {
                            listNote += ' Выбрано вне экрана: <strong>' + fmtNum(selectedUrls().length) + '</strong>.';
                        }
                        wrap.innerHTML =
                            '<div class="d-flex flex-wrap align-items-center mb-2" style="gap:8px">' +
                            '<button type="button" class="btn btn-sm btn-outline-secondary" id="sa-plag-landings">Только посадочные</button>' +
                            '<button type="button" class="btn btn-sm btn-outline-secondary" id="sa-plag-clear">Снять выбор</button>' +
                            '<span class="small text-muted" id="sa-plag-selected">Выбрано: 0 / ' + max + '</span>' +
                            '<input type="search" class="form-control form-control-sm" id="sa-plag-filter" placeholder="Найти страницу по URL или title…" style="max-width:280px" value="' + esc(q) + '">' +
                            '<button type="button" class="btn btn-sm btn-primary ms-auto" id="sa-plag-run">Проверить выбранные</button>' +
                            '</div>' +
                            '<div class="small text-muted mb-2" id="sa-plag-list-note">' + listNote + '</div>' +
                            '<div class="cabinet-sa-table-wrap mb-3" style="max-height:420px;overflow:auto">' +
                            '<table class="table table-sm mb-0" id="sa-plag-table"><thead class="thead-light"><tr>' +
                            '<th style="width:36px"></th><th>URL</th><th>Title</th><th>Слов</th><th></th>' +
                            '</tr></thead><tbody>' + rows + '</tbody></table></div>';
                        bindCandidateUi();
                    }
                    function fetchCandidates(q, fromFilter) {
                        if (!candidatesUrl || candidatesLoading) return;
                        candidatesLoading = true;
                        var wrap = document.getElementById('sa-plag-candidates-wrap');
                        if (wrap && !fromFilter && !document.getElementById('sa-plag-table')) {
                            wrap.innerHTML = '<div class="alert alert-light border mb-0" id="sa-plag-candidates-loading">Загрузка списка URL…</div>';
                        }
                        var note = document.getElementById('sa-plag-list-note');
                        if (fromFilter && note) note.textContent = 'Ищем…';
                        var ctrl = (typeof AbortController !== 'undefined') ? new AbortController() : null;
                        var to = setTimeout(function () {
                            if (ctrl) ctrl.abort();
                        }, 45000);
                        var url = candidatesUrl;
                        q = String(q || '').trim();
                        if (q) {
                            url += (url.indexOf('?') >= 0 ? '&' : '?') + 'q=' + encodeURIComponent(q);
                        }
                        var fetchOpts = { headers: { Accept: 'application/json' }, credentials: 'same-origin' };
                        if (ctrl) fetchOpts.signal = ctrl.signal;
                        fetch(url, fetchOpts)
                            .then(function (r) {
                                if (!r.ok) throw new Error('HTTP ' + r.status);
                                return r.json();
                            })
                            .then(function (j) {
                                candidatesLoaded = true;
                                candidatesLoading = false;
                                renderCandidates((j && j.candidates) || [], {
                                    total: j && j.total,
                                    truncated: j && j.truncated,
                                    q: (j && j.q) || q
                                });
                                if (!limitsLoaded) poll();
                            })
                            .catch(function () {
                                candidatesLoading = false;
                                if (fromFilter) {
                                    var n = document.getElementById('sa-plag-list-note');
                                    if (n) n.textContent = 'Не удалось найти. Попробуйте ещё раз.';
                                    return;
                                }
                                var w = document.getElementById('sa-plag-candidates-wrap');
                                if (w) {
                                    w.innerHTML = '<div class="alert alert-warning mb-0">Не удалось загрузить список URL. '
                                        + '<button type="button" class="btn btn-sm btn-outline-primary ms-2" id="sa-plag-retry">Повторить</button></div>';
                                    var retry = document.getElementById('sa-plag-retry');
                                    if (retry) {
                                        retry.addEventListener('click', function () {
                                            candidatesLoaded = false;
                                            loadCandidates();
                                        });
                                    }
                                }
                            })
                            .then(function () { clearTimeout(to); }, function () { clearTimeout(to); });
                    }
                    function loadCandidates() {
                        if (candidatesLoaded || candidatesLoading || !candidatesUrl) return;
                        fetchCandidates('', false);
                    }
                    window.__saLoadPlagiarismCandidates = loadCandidates;
                    if (!candidatesLazy) {
                        bindCandidateUi();
                        if (!limitsLoaded) poll();
                    } else {
                        var plagTab = document.getElementById('sa-tab-plagiarism');
                        if (plagTab) {
                            plagTab.addEventListener('shown.bs.tab', loadCandidates);
                            plagTab.addEventListener('click', function () {
                                setTimeout(loadCandidates, 0);
                            });
                        }
                        // Уже открыли по hash / вкладка уже active — грузим сразу
                        var plagPane = document.getElementById('sa-pane-plagiarism');
                        if (window.location.hash === '#sa-pane-plagiarism'
                            || (plagPane && plagPane.classList.contains('active'))
                            || (plagTab && plagTab.classList.contains('active'))) {
                            setTimeout(loadCandidates, 0);
                            setTimeout(loadCandidates, 120);
                            setTimeout(loadCandidates, 400);
                        }
                    }
                    @if(!empty($plagiarismState['status']) && in_array($plagiarismState['status'], ['queued', 'running'], true))
                    poll();
                    @endif
                })();

                (function initRelevanceTab() {
                    var pane = document.getElementById('sa-pane-relevance');
                    if (!pane) return;
                    var rowsUrl = pane.getAttribute('data-rows-url') || '';
                    var lazy = pane.getAttribute('data-rows-lazy') === '1';
                    if (!lazy || !rowsUrl) return;
                    var loaded = false;
                    var loading = false;

                    function esc(s) {
                        return String(s == null ? '' : s)
                            .replace(/&/g, '&amp;')
                            .replace(/</g, '&lt;')
                            .replace(/"/g, '&quot;');
                    }
                    function short(s, n) {
                        s = String(s == null ? '' : s);
                        return s.length > n ? s.slice(0, n) : s;
                    }
                    function renderRows(rows) {
                        var body = document.getElementById('sa-relevance-body');
                        if (!body) return;
                        if (!rows || !rows.length) {
                            body.innerHTML = '<div class="alert alert-light border mb-0">' +
                                'Нет посадочных из мониторинга для этого домена. ' +
                                'Добавьте URL страницы к запросам в модуле мониторинга — появятся здесь.</div>';
                            return;
                        }
                        var withH = 0;
                        rows.forEach(function (r) { if (r.history_id) withH++; });
                        var html = '<div class="small text-muted mb-2">Посадочных: ' + rows.length +
                            ' · с анализом: ' + withH +
                            ' · без анализа: ' + (rows.length - withH) + '</div>' +
                            '<div class="cabinet-sa-table-wrap"><table class="table table-sm mb-0">' +
                            '<thead class="thead-light"><tr>' +
                            '<th>Запрос</th><th>Посадочная</th><th>Баллы</th><th>Покрытие</th><th>Поз.</th><th>Проверка</th><th></th>' +
                            '</tr></thead><tbody>';
                        rows.forEach(function (row) {
                            html += '<tr><td class="small">' + esc(short(row.query, 40)) + '</td>' +
                                '<td class="small"><a href="' + esc(row.url) + '" target="_blank" rel="noopener">' +
                                esc(short(row.url, 55)) + '</a></td>';
                            if (row.history_id) {
                                var cov = (row.coverage != null || row.coverage_tf != null)
                                    ? ((row.coverage != null ? row.coverage : '—') + ' / TF ' + (row.coverage_tf != null ? row.coverage_tf : '—'))
                                    : '—';
                                html += '<td>' + (row.points != null ? row.points : '—') + '</td>' +
                                    '<td class="small">' + esc(cov) + '</td>' +
                                    '<td>' + (row.position != null ? row.position : '—') + '</td>' +
                                    '<td class="small text-muted">' + esc(row.last_check || '—') + '</td>' +
                                    '<td class="text-nowrap">';
                                if (row.history_url) {
                                    html += '<a class="btn btn-sm btn-outline-primary" href="' + esc(row.history_url) +
                                        '" target="_blank" rel="noopener">Открыть анализ</a> ';
                                }
                                html += '<a class="btn btn-sm btn-outline-secondary" href="' + esc(row.analyze_url || '#') +
                                    '" target="_blank" rel="noopener" title="Повторить с prefill">Ещё раз</a></td>';
                            } else {
                                html += '<td colspan="4" class="small text-muted">Расчёта ещё не было</td>' +
                                    '<td class="text-nowrap"><a class="btn btn-sm btn-primary" href="' +
                                    esc(row.analyze_url || '#') + '" target="_blank" rel="noopener">Проверить в анализаторе</a></td>';
                            }
                            html += '</tr>';
                        });
                        html += '</tbody></table></div>';
                        body.innerHTML = html;
                    }
                    function loadRows() {
                        if (loaded || loading) return;
                        loading = true;
                        fetch(rowsUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
                            .then(function (r) { return r.json(); })
                            .then(function (j) {
                                loaded = true;
                                loading = false;
                                renderRows((j && j.rows) || []);
                            })
                            .catch(function () {
                                loading = false;
                                var body = document.getElementById('sa-relevance-body');
                                if (body) {
                                    body.innerHTML = '<div class="alert alert-warning mb-0">Не удалось загрузить. Откройте вкладку ещё раз.</div>';
                                }
                            });
                    }
                    window.__saLoadRelevanceRows = loadRows;
                    var tab = document.getElementById('sa-tab-relevance');
                    if (tab) {
                        tab.addEventListener('shown.bs.tab', loadRows);
                        tab.addEventListener('click', function () { setTimeout(loadRows, 0); });
                    }
                    if (window.location.hash === '#sa-pane-relevance'
                        || (tab && tab.classList.contains('active'))) {
                        setTimeout(loadRows, 0);
                        setTimeout(loadRows, 120);
                    }
                })();
            })();
        </script>
        {{-- Отдельный <script>: нельзя @include внутрь открытого script — ломает весь JS (hash-вкладки, антиплагиат). --}}
        @include('pages.partials.site-audit-crawl-live-js')
    @endslot
@endcomponent
