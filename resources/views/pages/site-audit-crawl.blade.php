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
            <div class="input-group input-group-sm">
                <input type="text" class="form-control" id="sa-share-url" readonly value="{{ $shareUrl ?? '' }}">
                <div class="input-group-append">
                    <button type="button" class="btn btn-outline-secondary" id="sa-share-copy">Копировать</button>
                    <button type="button" class="btn btn-outline-danger" id="sa-share-revoke">Отключить</button>
                </div>
            </div>
        </div>

        <ul class="nav nav-tabs mb-3" id="sa-audit-tabs" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" id="sa-tab-all" data-bs-toggle="tab" href="#sa-pane-all" role="tab">Сводка</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="sa-tab-tech" data-bs-toggle="tab" href="#sa-pane-tech" role="tab">Тех. аудит</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="sa-tab-seo" data-bs-toggle="tab" href="#sa-pane-seo" role="tab">SEO-аудит</a>
            </li>
            @if(!empty($historyRows) && count($historyRows) > 1)
                <li class="nav-item">
                    <a class="nav-link" id="sa-tab-dynamics" data-bs-toggle="tab" href="#sa-pane-dynamics" role="tab">Динамика</a>
                </li>
            @endif
        </ul>

        <div class="tab-content">
            <div class="tab-pane fade show active" id="sa-pane-all" role="tabpanel">
                <div class="cabinet-sa-buckets mb-4" id="sa-buckets">
                    @foreach($bucketLabels as $key => $label)
                        <div class="cabinet-sa-bucket cabinet-sa-bucket--{{ $key }}" data-sa-bucket-preset="{{ $key }}" title="Показать отчёты: {{ $label }}">
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
                                @foreach(($treeAll ?? [])[$sev] ?? [] as $item)
                                    <a class="cabinet-sa-tree__item {{ $item['count'] ? '' : 'is-empty' }}"
                                       href="{{ route('pages.site-audit.report.show', [$crawl->id, $item['code']]) }}"
                                       data-title="{{ $item['title'] }}"
                                       data-severity="{{ $sev }}"
                                       data-count="{{ (int) $item['count'] }}">
                                        <span>
                                            {{ $item['title'] }}
                                            <span class="cabinet-sa-sev">({{ \App\Services\SiteAudit\SiteAuditFindingPresenter::severityTag($sev) }})</span>
                                            <span class="cabinet-sa-group-tag cabinet-sa-group-tag--{{ $item['group'] ?? 'tech' }}">{{ ($item['group'] ?? '') === 'seo' ? 'SEO' : 'тех' }}</span>
                                        </span>
                                        <span class="cabinet-sa-badge cabinet-sa-badge--{{ $item['count'] > 0 ? $sev : 'zero' }}">{{ $item['count'] }}</span>
                                    </a>
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
                        <div class="cabinet-sa-bucket cabinet-sa-bucket--{{ $key }}" data-sa-bucket-preset="{{ $key }}" title="Показать отчёты: {{ $label }}">
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
                                @foreach($tree[$sev] ?? [] as $item)
                                    <a class="cabinet-sa-tree__item {{ $item['count'] ? '' : 'is-empty' }}"
                                       href="{{ route('pages.site-audit.report.show', [$crawl->id, $item['code']]) }}"
                                       data-title="{{ $item['title'] }}"
                                       data-severity="{{ $sev }}"
                                       data-count="{{ (int) $item['count'] }}">
                                        <span>{{ $item['title'] }} <span class="cabinet-sa-sev">({{ \App\Services\SiteAudit\SiteAuditFindingPresenter::severityTag($sev) }})</span></span>
                                        <span class="cabinet-sa-badge cabinet-sa-badge--{{ $item['count'] > 0 ? $sev : 'zero' }}">{{ $item['count'] }}</span>
                                    </a>
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
                        <div class="cabinet-sa-bucket cabinet-sa-bucket--{{ $key }}" data-sa-bucket-preset="{{ $key }}" title="Показать отчёты: {{ $label }}">
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
                                @foreach(($treeSeo ?? [])[$sev] ?? [] as $item)
                                    <a class="cabinet-sa-tree__item {{ $item['count'] ? '' : 'is-empty' }}"
                                       href="{{ route('pages.site-audit.report.show', [$crawl->id, $item['code']]) }}"
                                       data-title="{{ $item['title'] }}"
                                       data-severity="{{ $sev }}"
                                       data-count="{{ (int) $item['count'] }}">
                                        <span>{{ $item['title'] }} <span class="cabinet-sa-sev">({{ \App\Services\SiteAudit\SiteAuditFindingPresenter::severityTag($sev) }})</span></span>
                                        <span class="cabinet-sa-badge cabinet-sa-badge--{{ $item['count'] > 0 ? $sev : 'zero' }}">{{ $item['count'] }}</span>
                                    </a>
                                @endforeach
                            </div>
                        @endforeach
                    </aside>
                    <section>
                        <h5 class="mb-3">Сводный SEO-аудит</h5>
                        <p class="text-secondary small">Title/Description, H1, canonical, noindex, дубли, похожие страницы, thin content.</p>
                        @include('pages.partials.site-audit-hot-table', ['counts' => $counts, 'findingsCatalog' => $findingsCatalog, 'crawl' => $crawl, 'group' => 'seo'])
                    </section>
                </div>
            </div>

            @if(!empty($historyRows) && count($historyRows) > 1)
                <div class="tab-pane fade" id="sa-pane-dynamics" role="tabpanel">
                    <h6 class="mb-2">Динамика tech по краулам</h6>
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

    @slot('js')
        @include('pages.partials.site-audit-tree-nav-js')
        <script>
            (function () {
                var tokenMeta = document.querySelector('meta[name="csrf-token"]');
                var csrf = tokenMeta ? tokenMeta.getAttribute('content') : '';
                var shareBtn = document.getElementById('sa-share-btn');
                var shareBox = document.getElementById('sa-share-box');
                var shareUrl = document.getElementById('sa-share-url');
                var shareCopy = document.getElementById('sa-share-copy');
                var shareRevoke = document.getElementById('sa-share-revoke');

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

                if (shareBtn) {
                    shareBtn.addEventListener('click', function () {
                        var existing = shareBtn.getAttribute('data-url');
                        if (existing) {
                            showShare(existing);
                            return;
                        }
                        fetch(shareBtn.getAttribute('data-create'), {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrf
                            }
                        }).then(function (r) { return r.json(); })
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
                        fetch(shareBtn.getAttribute('data-revoke'), {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrf
                            }
                        }).then(function (r) { return r.json(); })
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
