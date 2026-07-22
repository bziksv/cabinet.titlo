@component('component.card', [
    'title' => 'Аудит сайта · краул #' . $crawl->id,
])
    @slot('css')
        <link rel="stylesheet" href="{{ asset('css/cabinet-site-audit.css') }}?v={{ @filemtime(public_path('css/cabinet-site-audit.css')) ?: time() }}">
    @endslot

    @slot('tools')
        <a href="{{ route('pages.site-audit') }}" class="btn btn-sm btn-outline-secondary">← К списку</a>
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
        @if($crawl->isFinished())
            <form method="POST" action="{{ route('pages.site-audit.crawl.repeat', $crawl->id) }}" class="d-inline"
                  onsubmit="return confirm('Повторить краул для {{ e(optional($project)->domain ?? 'проекта') }} с теми же настройками?');">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-secondary">Повторить</button>
            </form>
            <form method="POST" action="{{ route('pages.site-audit.crawl.destroy', $crawl->id) }}" class="d-inline"
                  onsubmit="return confirm('Удалить краул #{{ $crawl->id }} и все его findings?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-sm btn-outline-danger">Удалить</button>
            </form>
        @endif
    @endslot

    <div class="cabinet-sa-page" id="sa-crawl-root"
         data-status-url="{{ route('pages.site-audit.crawl.status', $crawl->id) }}"
         data-finished="{{ $crawl->isFinished() ? '1' : '0' }}">

        @include('pages.partials.site-audit-beta-banner')

        <div class="d-flex flex-wrap justify-content-between align-items-start mb-3">
            <div>
                    <div class="h5 mb-1">{{ optional($project)->domain ?? '—' }}</div>
                <div class="small text-muted">
                    Краул #{{ $crawl->id }}
                    · лимит {{ $crawl->pages_limit }} URL
                    @php $s = $crawl->progress_json['settings'] ?? []; @endphp
                    @if(!empty($s))
                        · скорость {{ $s['crawl_speed'] ?? '—' }} ({{ $s['rps'] ?? '—' }} URL/с)
                    @endif
                    @if($crawl->started_at) · старт {{ $crawl->started_at }} @endif
                    @if($crawl->finished_at) · конец {{ $crawl->finished_at }} @endif
                    @if(isset(($crawl->counts_json ?? [])['click_depth_max']))
                        · глубина клика до {{ (int) $crawl->counts_json['click_depth_max'] }}
                    @endif
                </div>
            </div>
            @php
                $stClass = $crawl->statusCssClass();
            @endphp
            <span class="cabinet-sa-status cabinet-sa-status--{{ $stClass }}" id="sa-status-pill">{{ $crawl->statusLabelRu() }}</span>
        </div>

        <div class="mb-4" id="sa-progress-wrap" @if($crawl->isFinished()) style="display:none" @endif>
            <div class="d-flex justify-content-between small text-muted mb-1">
                <span>Прогресс</span>
                <span id="sa-progress-label">{{ $crawl->pages_fetched }} / {{ $crawl->pages_total }}</span>
            </div>
            <div class="cabinet-sa-progress">
                <div class="cabinet-sa-progress__bar" id="sa-progress-bar"
                     style="width: {{ $crawl->pages_total > 0 ? round(100 * $crawl->pages_fetched / $crawl->pages_total) : 0 }}%"></div>
            </div>
        </div>

        @if($crawl->error)
            <div class="alert alert-danger">{{ $crawl->error }}</div>
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
            @if(!empty($historyRows) && count($historyRows) > 1)
                <li class="nav-item">
                    <a class="nav-link" id="sa-tab-dynamics" data-bs-toggle="tab" href="#sa-pane-dynamics" role="tab"
                       title="Как менялось число ошибок от краула к краулу">Динамика</a>
                </li>
            @endif
        </ul>

        <div class="tab-content">
            <div class="tab-pane fade show active" id="sa-pane-all" role="tabpanel">
                <div class="cabinet-sa-buckets mb-4" id="sa-buckets">
                    @foreach($bucketLabels as $key => $label)
                        <div class="cabinet-sa-bucket cabinet-sa-bucket--{{ $key }}" data-sa-bucket-preset="{{ $key }}"
                             title="@if($key === 'critical')Самые срочные ошибки — чинить первыми@elseif($key === 'other')Средняя срочность@elseif($key === 'warning')Желательно починить@elseПросто знать, не всегда срочно@endif. Клик — отфильтровать меню слева.">
                            <div class="cabinet-sa-bucket__label">{{ $label }}</div>
                            <div class="cabinet-sa-bucket__value" data-bucket="{{ $key }}">{{ (int) (($bucketsAll ?? $buckets)[$key] ?? 0) }}</div>
                        </div>
                    @endforeach
                </div>

                <div class="cabinet-sa-layout">
                    <aside class="cabinet-sa-tree" data-sa-tree>
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
                <div class="cabinet-sa-buckets mb-4">
                    @foreach($bucketLabels as $key => $label)
                        <div class="cabinet-sa-bucket cabinet-sa-bucket--{{ $key }}" data-sa-bucket-preset="{{ $key }}"
                             title="@if($key === 'critical')Самые срочные ошибки — чинить первыми@elseif($key === 'other')Средняя срочность@elseif($key === 'warning')Желательно починить@elseПросто знать, не всегда срочно@endif. Клик — отфильтровать меню слева.">
                            <div class="cabinet-sa-bucket__label">{{ $label }}</div>
                            <div class="cabinet-sa-bucket__value">{{ (int) ($buckets[$key] ?? 0) }}</div>
                        </div>
                    @endforeach
                </div>

                <div class="cabinet-sa-layout">
                    <aside class="cabinet-sa-tree" data-sa-tree>
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
                        @include('pages.partials.site-audit-hot-table', ['counts' => $counts, 'findingsCatalog' => $findingsCatalog, 'crawl' => $crawl, 'group' => 'tech'])
                    </section>
                </div>
            </div>

            <div class="tab-pane fade" id="sa-pane-seo" role="tabpanel">
                <div class="cabinet-sa-buckets mb-4">
                    @foreach($bucketLabels as $key => $label)
                        <div class="cabinet-sa-bucket cabinet-sa-bucket--{{ $key }}" data-sa-bucket-preset="{{ $key }}"
                             title="@if($key === 'critical')Самые срочные ошибки — чинить первыми@elseif($key === 'other')Средняя срочность@elseif($key === 'warning')Желательно починить@elseПросто знать, не всегда срочно@endif. Клик — отфильтровать меню слева.">
                            <div class="cabinet-sa-bucket__label">{{ $label }}</div>
                            <div class="cabinet-sa-bucket__value">{{ (int) (($bucketsSeo ?? [])[$key] ?? 0) }}</div>
                        </div>
                    @endforeach
                </div>

                <div class="cabinet-sa-layout">
                    <aside class="cabinet-sa-tree" data-sa-tree>
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
                        <div class="alert alert-light border cabinet-sa-module-link mb-3">
                            <div class="d-flex flex-wrap align-items-center justify-content-between" style="gap:8px">
                                <div>
                                    <strong>Конкуренты сайта</strong>
                                    <div class="small text-muted mb-0">Сравнение с ТОП выдачи — в модуле «Анализ конкурентов», без повторного краула.</div>
                                </div>
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('competitor.analysis') }}" target="_blank" rel="noopener">
                                    Открыть анализ конкурентов <i class="fa fa-external-link" aria-hidden="true"></i>
                                </a>
                            </div>
                        </div>
                        @include('pages.partials.site-audit-hot-table', ['counts' => $counts, 'findingsCatalog' => $findingsCatalog, 'crawl' => $crawl, 'group' => 'seo'])
                    </section>
                </div>
            </div>

            @if(!empty($historyRows) && count($historyRows) > 1)
                <div class="tab-pane fade" id="sa-pane-dynamics" role="tabpanel">
                    <h6 class="mb-2">Динамика тех. аудита по краулам</h6>
                    <div class="cabinet-sa-table-wrap mb-4">
                        <table class="table table-sm mb-0">
                            <thead class="thead-light">
                            <tr>
                                <th>Краул</th>
                                <th>Дата</th>
                                <th>URL</th>
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

                    <h6 class="mb-2">Динамика SEO по краулам</h6>
                    <div class="cabinet-sa-table-wrap">
                        <table class="table table-sm mb-0">
                            <thead class="thead-light">
                            <tr>
                                <th>Краул</th>
                                <th>Дата</th>
                                <th>URL</th>
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

                var root = document.getElementById('sa-crawl-root');
                if (!root || root.getAttribute('data-finished') === '1') return;

                var url = root.getAttribute('data-status-url');
                var bar = document.getElementById('sa-progress-bar');
                var label = document.getElementById('sa-progress-label');
                var pill = document.getElementById('sa-status-pill');
                var wrap = document.getElementById('sa-progress-wrap');

                function tick() {
                    fetch(url, { headers: { 'Accept': 'application/json' } })
                        .then(function (r) { return r.json(); })
                        .then(function (j) {
                            if (label) label.textContent = j.pages_fetched + ' / ' + j.pages_total;
                            if (bar) bar.style.width = (j.progress_pct || 0) + '%';
                            if (pill) {
                                pill.textContent = j.status_label || j.status;
                                pill.className = 'cabinet-sa-status cabinet-sa-status--' +
                                    (j.status === 'done' ? 'done' : (j.status === 'failed' ? 'failed' : 'run'));
                            }
                            if (j.buckets) {
                                Object.keys(j.buckets).forEach(function (k) {
                                    var el = document.querySelector('#sa-buckets [data-bucket="' + k + '"]');
                                    if (el) el.textContent = j.buckets[k];
                                });
                            }
                            if (j.finished) {
                                if (wrap) wrap.style.display = 'none';
                                window.location.reload();
                                return;
                            }
                            setTimeout(tick, 2000);
                        })
                        .catch(function () { setTimeout(tick, 4000); });
                }

                setTimeout(tick, 1500);
            })();
        </script>
    @endslot
@endcomponent
