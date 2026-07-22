@component('component.card', [
    'title' => 'Аудит сайта',
    'titleHtml' => e('Аудит сайта') . view('partials.cabinet-module-version-badge', ['configKey' => 'cabinet-site-audit'])->render(),
])
    @slot('css')
        <link rel="stylesheet" href="{{ asset('css/cabinet-site-audit.css') }}?v={{ @filemtime(public_path('css/cabinet-site-audit.css')) ?: time() }}">
    @endslot

    <div class="cabinet-sa-page">
        @if(session('status'))
            <div class="alert alert-success py-2">{{ session('status') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-warning py-2">{{ session('error') }}</div>
        @endif

        <div class="cabinet-sa-lead px-4 py-3 mb-3">
            <div class="d-flex gap-3 align-items-start">
                <span class="cabinet-sa-lead__icon" aria-hidden="true"><i class="bi bi-clipboard2-pulse"></i></span>
                <div>
                    <p class="mb-1 fw-semibold text-body">Технический аудит сайта</p>
                    <p class="mb-0 small text-secondary">
                        Sitemap + robots.txt → проверка страниц → сводка по приоритетам.
                        @if($isLocal)
                            <span class="badge text-bg-secondary">local · лимиты тарифа отключены</span>
                        @endif
                    </p>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-lg-5">
                <section class="card border shadow-sm cabinet-sa-panel h-100">
                    <div class="card-body">
                        <h2 class="cabinet-sa-step-title h6 mb-3">
                            <span class="cabinet-sa-step-badge">1</span>
                            Новый краул
                        </h2>

                        <div class="mb-3 cabinet-sa-field">
                            <label class="form-label fw-medium" for="sa-domain">
                                Домен
                                @include('pages.partials.site-audit-tip', ['tip' => "Сайт для проверки без https:// — например titlo.ru.\nСтартуем с sitemap и главной, затем добираем страницы по внутренним ссылкам до лимита тарифа (или тестового лимита)."])
                            </label>
                            <input type="text" class="form-control" id="sa-domain" placeholder="example.com" autocomplete="off">
                        </div>

                        <div class="mb-3 cabinet-sa-field">
                            <label class="form-label fw-medium" for="sa-seeds">
                                Доп. URL <span class="text-secondary fw-normal">(опционально)</span>
                                @include('pages.partials.site-audit-tip', ['tip' => "Страницы, которые обязательно нужно проверить, даже если их нет в sitemap.\nПо одному URL на строку. Полезно для посадочных, новых разделов, страниц за логикой меню."])
                            </label>
                            <textarea class="form-control" id="sa-seeds" rows="3" placeholder="https://example.com/page"></textarea>
                        </div>

                        <div class="mb-3 cabinet-sa-field">
                            <label class="form-label fw-medium" for="sa-exclude">
                                Исключить URL <span class="text-secondary fw-normal">(опционально)</span>
                                @include('pages.partials.site-audit-tip', ['tip' => "Что не обходить и не анализировать.\n• подстрока: /admin — всё, где встречается /admin\n• glob: */cart* — маска со звёздочкой\n• regex:~pattern~i — регулярное выражение\nПо одному правилу на строку. Типично: корзина, ЛК, фильтры, служебные разделы."])
                            </label>
                            <textarea class="form-control" id="sa-exclude" rows="2"
                                      placeholder="/admin&#10;*/cart*&#10;regex:~[?&]utm_~i"></textarea>
                        </div>

                        <div class="mb-3 cabinet-sa-field">
                            <label class="form-label fw-medium" for="sa-speed">
                                Скорость снятия
                                @include('pages.partials.site-audit-tip', ['tip' => "Сколько запросов в секунду к одному домену.\nМедленнее — мягче к антиботу и меньше риск блокировок.\nТурбо — только для своих/тестовых сайтов: можно получить 403/429."])
                            </label>
                            <select class="form-select" id="sa-speed">
                                <option value="slow">Медленно (~1 URL/с) — мягче к антиботу</option>
                                <option value="normal" selected>Обычная (~5 URL/с)</option>
                                <option value="fast">Быстрая (~10 URL/с)</option>
                                <option value="turbo">Турбо (~15 URL/с) — только свои сайты</option>
                            </select>
                        </div>

                        @if($isLocal)
                            <div class="cabinet-sa-opt-group cabinet-sa-opt-group--local mb-3">
                                <div class="cabinet-sa-opt-group__head">
                                    <span class="fw-semibold">Local / тест</span>
                                    @include('pages.partials.site-audit-tip', ['tip' => "Только в local-окружении. На проде лимит из тарифа, краул идёт через очередь."])
                                </div>
                                <div class="mb-2 mt-2 cabinet-sa-field">
                                    <label for="sa-limit" class="form-label small mb-1">Лимит URL</label>
                                    <input type="number" class="form-control form-control-sm" id="sa-limit" value="70" min="1" max="50000">
                                </div>
                                <div class="cabinet-sa-opt-row mb-0">
                                    <div class="form-check form-switch mb-0">
                                        <input type="checkbox" class="form-check-input" id="sa-sync" role="switch">
                                        <label class="form-check-label" for="sa-sync">Синхронно (без queue)</label>
                                    </div>
                                    @include('pages.partials.site-audit-tip', ['tip' => "Выкл. — краул в очереди site_audit, в истории виден прогресс-бар (нужен ./scripts/dev-site-audit-queue.sh).\nВкл. — ждёт до конца в одном запросе, прогресса в таблице не будет."])
                                </div>
                            </div>
                        @endif

                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <button type="button" class="btn btn-primary" id="sa-start">
                                <i class="bi bi-play-fill me-1"></i>Запустить
                            </button>
                            <div id="sa-msg" class="small text-secondary"></div>
                        </div>
                    </div>
                </section>
            </div>

            <div class="col-lg-7">
                <section class="card border shadow-sm cabinet-sa-panel h-100">
                    <div class="card-header py-2 px-3 d-flex align-items-center justify-content-between">
                        <h2 class="h6 mb-0 fw-semibold">Проекты</h2>
                        <span class="badge text-bg-light border">{{ $projects->count() }}</span>
                    </div>
                    @forelse($projects as $project)
                        @php
                            $last = $project->crawls->first();
                            $sch = ($schedules ?? collect())->get($project->id);
                        @endphp
                        <div class="cabinet-sa-project">
                            <div class="cabinet-sa-project__main">
                                <div class="min-w-0">
                                    <div class="fw-semibold text-body text-truncate">{{ $project->domain }}</div>
                                    <div class="small text-secondary">
                                        @if($last)
                                            последний краул
                                            <a href="{{ route('pages.site-audit.crawl.show', $last->id) }}">#{{ $last->id }}</a>
                                            · {{ $last->statusLabelRu() }}
                                            · {{ $last->pages_fetched }}/{{ $last->pages_total }}
                                        @else
                                            ещё не запускался
                                        @endif
                                    </div>
                                </div>
                                <div class="d-flex flex-shrink-0 gap-1">
                                        @if($last)
                                            <a class="btn btn-sm btn-outline-primary" href="{{ route('pages.site-audit.crawl.show', $last->id) }}">Открыть</a>
                                            @if(($project->crawls_count ?? 0) > 1)
                                                <a class="btn btn-sm btn-outline-secondary"
                                                   href="{{ route('pages.site-audit.crawl.show', $last->id) }}#sa-archive">Архив</a>
                                            @endif
                                        @endif
                                    <form method="POST" action="{{ route('pages.site-audit.project.destroy', $project->id) }}" class="d-inline"
                                          onsubmit="return confirm('Удалить проект {{ $project->domain }} и все краулы?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Удалить</button>
                                    </form>
                                </div>
                            </div>
                            @if(!empty($canSchedule))
                                <form method="POST" action="{{ route('pages.site-audit.schedule', $project->id) }}" class="cabinet-sa-project__schedule">
                                    @csrf
                                    <div class="d-flex flex-wrap align-items-center gap-2">
                                        <div class="cabinet-sa-check-row mb-0">
                                            <div class="form-check mb-0">
                                                <input type="checkbox" class="form-check-input" id="sa-sch-{{ $project->id }}"
                                                       name="enabled" value="1" {{ $sch && $sch->enabled ? 'checked' : '' }}>
                                                <label class="form-check-label" for="sa-sch-{{ $project->id }}">Расписание</label>
                                            </div>
                                            @include('pages.partials.site-audit-tip', ['tip' => "Автозапуск полного аудита по расписанию.\nДоступно только на платных тарифах.\nВарианты: раз в неделю, в 2 недели, в 3 недели или раз в месяц. Списывает краул из месячного лимита."])
                                        </div>
                                        <select name="frequency" class="form-select form-select-sm cabinet-sa-schedule-select"
                                                title="Как часто запускать повторный аудит">
                                            @foreach(($scheduleFrequencies ?? []) as $freqCode => $freqLabel)
                                                <option value="{{ $freqCode }}"
                                                    {{ ($sch ? \App\SiteAuditSchedule::normalizeFrequency($sch->frequency) : 'weekly') === $freqCode ? 'selected' : '' }}>
                                                    {{ $freqLabel }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="btn btn-sm btn-outline-secondary">Сохранить</button>
                                        @if($sch && $sch->next_run_at)
                                            <span class="small text-secondary">след.: {{ $sch->next_run_at->format('d.m.Y H:i') }}</span>
                                        @endif
                                    </div>
                                </form>
                            @else
                                <div class="cabinet-sa-project__schedule small text-secondary">
                                    Расписание аудита — на платных тарифах (раз в неделю / 2 / 3 недели / месяц).
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="card-body">
                            <div class="alert alert-light border text-center py-4 mb-0 text-secondary">
                                Проектов пока нет — запустите первый краул.
                            </div>
                        </div>
                    @endforelse
                </section>
            </div>
        </div>

        <section class="card border shadow-sm cabinet-sa-panel mt-3" id="sa-history">
            <div class="card-header py-2 px-3 d-flex align-items-center justify-content-between">
                <h2 class="h6 mb-0 fw-semibold">История краулов</h2>
                <span class="badge text-bg-light border">{{ $crawls->count() }}</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive cabinet-sa-table-wrap cabinet-sa-table-wrap--flush">
                    <table class="table table-sm table-hover align-middle mb-0" id="sa-history-table">
                        <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Домен</th>
                            <th>Статус</th>
                            <th style="min-width:8rem">Прогресс</th>
                            <th>Размер</th>
                            <th>Грубые</th>
                            <th>Прочие</th>
                            <th>Пред.</th>
                            <th>Инфо</th>
                            <th class="text-end"></th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($crawls as $c)
                            @php
                                $b = $c->buckets_json ?: [];
                                $stClass = $c->statusCssClass();
                                $sizeBytes = (int) (($crawlSizes ?? [])[$c->id] ?? 0);
                                $pct = $c->pages_total > 0
                                    ? (int) round(100 * $c->pages_fetched / max(1, $c->pages_total))
                                    : 0;
                                $finished = $c->isFinished();
                            @endphp
                            <tr data-crawl-id="{{ $c->id }}"
                                data-finished="{{ $finished ? '1' : '0' }}"
                                data-status-url="{{ route('pages.site-audit.crawl.status', $c->id) }}"
                                class="{{ $finished ? '' : 'cabinet-sa-row--active' }}">
                                <td class="text-secondary">#{{ $c->id }}</td>
                                <td class="fw-medium">{{ optional($c->project)->domain ?? '—' }}</td>
                                <td>
                                    <span class="cabinet-sa-status cabinet-sa-status--{{ $stClass }}" data-sa-status>
                                        {{ $c->statusLabelRu() }}
                                    </span>
                                </td>
                                <td class="cabinet-sa-progress-cell" data-sa-progress>
                                    @php
                                        $fetchedN = (int) $c->pages_fetched;
                                        $totalN = max(0, (int) $c->pages_total);
                                        $isFailed = $c->status === 'failed';
                                        $indeterminate = ! $finished && ($totalN < 1 || in_array($c->status, ['queued', 'queued_wait', 'discovering'], true));
                                        // /html/UI/general.html — Progress
                                        if ($finished && ! $isFailed) {
                                            $barClass = 'progress-bar bg-success';
                                            $fillPct = 100;
                                            $labelText = $fetchedN . '/' . $totalN;
                                        } elseif ($isFailed) {
                                            $barClass = 'progress-bar progress-bar-striped progress-bar-animated bg-danger';
                                            $fillPct = $totalN > 0 ? (int) round(100 * $fetchedN / max(1, $totalN)) : 0;
                                            if ($fillPct < 1) {
                                                $fillPct = 100;
                                            }
                                            $labelText = $fetchedN . '/' . $totalN;
                                        } elseif ($indeterminate) {
                                            $barClass = 'progress-bar progress-bar-striped progress-bar-animated bg-warning';
                                            $fillPct = 100;
                                            $labelText = $totalN > 0 ? ($fetchedN . '/' . $totalN) : '…';
                                        } else {
                                            $barClass = 'progress-bar progress-bar-striped progress-bar-animated bg-info';
                                            $fillPct = max(0, (int) $pct);
                                            $labelText = $fetchedN . '/' . $totalN;
                                        }
                                        $hint = $c->status === 'queued' || $c->status === 'queued_wait'
                                            ? 'ждёт воркер'
                                            : ($c->status === 'discovering' ? 'сбор URL' : ($c->status === 'aggregating' ? 'агрегация' : ($isFailed ? 'ошибка' : ($finished ? 'готово' : 'сканирование'))));
                                    @endphp
                                    <div class="progress"
                                         role="progressbar"
                                         aria-label="{{ $hint }}"
                                         aria-valuenow="{{ $fillPct }}"
                                         aria-valuemin="0"
                                         aria-valuemax="100"
                                         title="{{ $hint }} · {{ $fetchedN }}/{{ $totalN }}">
                                        <div class="{{ $barClass }}" style="width: {{ $fillPct }}%; border-radius: 0.375rem">{{ $labelText }}</div>
                                    </div>
                                </td>
                                <td class="text-nowrap" data-sa-size>
                                    @php
                                        $sizeClass = 'cabinet-sa-size--sm';
                                        if ($sizeBytes >= 80 * 1024) {
                                            $sizeClass = 'cabinet-sa-size--lg';
                                        } elseif ($sizeBytes >= 30 * 1024) {
                                            $sizeClass = 'cabinet-sa-size--md';
                                        }
                                    @endphp
                                    <span class="cabinet-sa-size {{ $sizeClass }}" title="payload в БД (pages + findings + meta), без HTML">
                                        {{ \App\Services\SiteAudit\SiteAuditCrawlStorage::formatBytes($sizeBytes) }}
                                    </span>
                                </td>
                                <td data-sa-bucket="critical">{{ $b['critical'] ?? '—' }}</td>
                                <td data-sa-bucket="other">{{ $b['other'] ?? '—' }}</td>
                                <td data-sa-bucket="warning">{{ $b['warning'] ?? '—' }}</td>
                                <td data-sa-bucket="info">{{ $b['info'] ?? '—' }}</td>
                                <td class="text-end text-nowrap">
                                    <span class="cabinet-sa-row-actions">
                                        <a class="btn btn-sm btn-outline-primary" href="{{ route('pages.site-audit.crawl.show', $c->id) }}">Сводка</a>
                                        @if($finished)
                                            <form method="POST" action="{{ route('pages.site-audit.crawl.repeat', $c->id) }}" class="d-inline"
                                                  onsubmit="return confirm('Повторить краул для {{ e(optional($c->project)->domain ?? 'проекта') }} с теми же настройками?');">
                                                @csrf
                                                @if(app()->environment('local'))
                                                    <input type="hidden" name="sync" value="" data-sa-repeat-sync>
                                                @endif
                                                <button type="submit" class="btn btn-sm btn-outline-secondary">Повторить</button>
                                            </form>
                                            <form method="POST" action="{{ route('pages.site-audit.crawl.destroy', $c->id) }}" class="d-inline"
                                                  onsubmit="return confirm('Удалить краул #{{ $c->id }}?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Удалить</button>
                                            </form>
                                        @endif
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr data-sa-empty><td colspan="10" class="text-secondary px-3 py-4 text-center">История пуста</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>

    @slot('js')
        <script>
            (function () {
                var startBtn = document.getElementById('sa-start');
                var msg = document.getElementById('sa-msg');
                var tokenMeta = document.querySelector('meta[name="csrf-token"]');
                var token = tokenMeta ? tokenMeta.getAttribute('content') : '';
                var historyTable = document.getElementById('sa-history-table');
                var pollTimers = {};

                function syncFlag() {
                    var syncEl = document.getElementById('sa-sync');
                    return (syncEl && syncEl.checked) ? '1' : '0';
                }

                document.querySelectorAll('[data-sa-repeat-sync]').forEach(function (inp) {
                    var form = inp.closest('form');
                    if (!form) return;
                    form.addEventListener('submit', function () {
                        inp.value = syncFlag();
                    });
                });

                function scrollToHistory() {
                    var el = document.getElementById('sa-history');
                    if (el && el.scrollIntoView) {
                        el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                }

                function updateRow(row, j) {
                    if (!row || !j) return;
                    var statusEl = row.querySelector('[data-sa-status]');
                    if (statusEl) {
                        statusEl.textContent = j.status_label || j.status;
                        statusEl.className = 'cabinet-sa-status cabinet-sa-status--' +
                            (j.status === 'done' ? 'done' : (j.status === 'failed' ? 'failed' : 'run'));
                    }
                    var prog = row.querySelector('[data-sa-progress]');
                    if (prog) {
                        var fetched = j.pages_fetched || 0;
                        var total = j.pages_total || 0;
                        var pct = j.progress_pct || (total > 0 ? Math.round(100 * fetched / total) : 0);
                        var st = j.status || '';
                        var isFailed = st === 'failed';
                        var finished = !!j.finished;
                        var indeterminate = !finished && (total < 1 || st === 'queued' || st === 'queued_wait' || st === 'discovering');
                        var barClass, fill, label, hint;
                        // /html/UI/general.html — Progress
                        if (finished && !isFailed) {
                            barClass = 'progress-bar bg-success';
                            fill = 100;
                            label = fetched + '/' + total;
                            hint = 'готово';
                        } else if (isFailed) {
                            barClass = 'progress-bar progress-bar-striped progress-bar-animated bg-danger';
                            fill = total > 0 ? Math.round(100 * fetched / Math.max(1, total)) : 0;
                            if (fill < 1) fill = 100;
                            label = fetched + '/' + total;
                            hint = 'ошибка';
                        } else if (indeterminate) {
                            barClass = 'progress-bar progress-bar-striped progress-bar-animated bg-warning';
                            fill = 100;
                            label = total > 0 ? (fetched + '/' + total) : '…';
                            hint = (st === 'queued' || st === 'queued_wait') ? 'ждёт воркер' : (st === 'discovering' ? 'сбор URL' : 'ожидание');
                        } else {
                            barClass = 'progress-bar progress-bar-striped progress-bar-animated bg-info';
                            fill = pct;
                            label = fetched + '/' + total;
                            hint = st === 'aggregating' ? 'агрегация' : 'сканирование';
                        }
                        prog.innerHTML =
                            '<div class="progress" role="progressbar" aria-label="' + hint +
                            '" aria-valuenow="' + fill + '" aria-valuemin="0" aria-valuemax="100" title="' +
                            hint + ' · ' + fetched + '/' + total + '">' +
                            '<div class="' + barClass + '" style="width:' + fill + '%; border-radius: 0.375rem">' +
                            label + '</div></div>';
                    }
                    if (j.buckets) {
                        ['critical', 'other', 'warning', 'info'].forEach(function (k) {
                            var cell = row.querySelector('[data-sa-bucket="' + k + '"]');
                            if (cell && typeof j.buckets[k] !== 'undefined') {
                                cell.textContent = j.buckets[k];
                            }
                        });
                    }
                    if (j.finished) {
                        row.setAttribute('data-finished', '1');
                        row.classList.remove('cabinet-sa-row--active');
                        var actions = row.querySelector('.cabinet-sa-row-actions');
                        if (actions && !actions.querySelector('form')) {
                            var domain = (row.querySelector('td.fw-medium') || {}).textContent || 'проекта';
                            domain = String(domain).trim() || 'проекта';
                            var repeat = document.createElement('form');
                            repeat.method = 'POST';
                            repeat.action = '{{ url('site-audit/crawl') }}/' + j.id + '/repeat';
                            repeat.className = 'd-inline';
                            repeat.onsubmit = function () {
                                return confirm('Повторить краул для ' + domain + ' с теми же настройками?');
                            };
                            var syncHidden = @json(app()->environment('local'))
                                ? '<input type="hidden" name="sync" value="' + syncFlag() + '">'
                                : '';
                            repeat.innerHTML =
                                '<input type="hidden" name="_token" value="' + token + '">' +
                                syncHidden +
                                '<button type="submit" class="btn btn-sm btn-outline-secondary">Повторить</button>';
                            actions.appendChild(repeat);

                            var del = document.createElement('form');
                            del.method = 'POST';
                            del.action = '{{ url('site-audit/crawl') }}/' + j.id;
                            del.className = 'd-inline';
                            del.onsubmit = function () { return confirm('Удалить краул #' + j.id + '?'); };
                            del.innerHTML =
                                '<input type="hidden" name="_token" value="' + token + '">' +
                                '<input type="hidden" name="_method" value="DELETE">' +
                                '<button type="submit" class="btn btn-sm btn-outline-danger">Удалить</button>';
                            actions.appendChild(del);
                        }
                    }
                }

                function pollRow(row) {
                    var id = row.getAttribute('data-crawl-id');
                    var url = row.getAttribute('data-status-url');
                    if (!id || !url || row.getAttribute('data-finished') === '1') return;
                    if (pollTimers[id]) return;

                    function tick() {
                        if (row.getAttribute('data-finished') === '1') {
                            delete pollTimers[id];
                            return;
                        }
                        fetch(url, { headers: { 'Accept': 'application/json' } })
                            .then(function (r) { return r.json(); })
                            .then(function (j) {
                                updateRow(row, j);
                                if (j.finished) {
                                    delete pollTimers[id];
                                    return;
                                }
                                pollTimers[id] = setTimeout(tick, 2000);
                            })
                            .catch(function () {
                                pollTimers[id] = setTimeout(tick, 4000);
                            });
                    }
                    pollTimers[id] = setTimeout(tick, 800);
                }

                function pollActiveRows() {
                    if (!historyTable) return;
                    historyTable.querySelectorAll('tr[data-crawl-id][data-finished="0"]').forEach(pollRow);
                }

                if (startBtn) {
                    startBtn.addEventListener('click', function () {
                        startBtn.disabled = true;
                        msg.textContent = 'Запуск…';
                        var body = {
                            domain: document.getElementById('sa-domain').value,
                            seed_urls: document.getElementById('sa-seeds').value,
                            exclude_patterns: document.getElementById('sa-exclude').value,
                            crawl_speed: document.getElementById('sa-speed').value,
                            unify_www: true,
                            force_https: true,
                            strip_trailing_slash: true,
                            check_broken_links: true
                        };
                        var limitEl = document.getElementById('sa-limit');
                        if (limitEl) body.pages_limit = limitEl.value;
                        var syncEl = document.getElementById('sa-sync');
                        if (syncEl) body.sync = syncEl.checked ? '1' : '0';

                        fetch('{{ route('pages.site-audit.start') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': token,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify(body)
                        }).then(function (r) {
                            return r.json().then(function (j) { return { ok: r.ok, j: j }; });
                        }).then(function (x) {
                            if (x.ok) {
                                msg.textContent = (x.j && x.j.message) ? x.j.message : 'Запущено';
                                var base = (x.j && x.j.redirect) ? x.j.redirect : '{{ route('pages.site-audit') }}';
                                var q = (x.j && x.j.crawl_id) ? ('?highlight=' + x.j.crawl_id) : '';
                                window.location = base + q + '#sa-history';
                                return;
                            }
                            msg.textContent = (x.j && x.j.message) ? x.j.message : 'Ошибка';
                            startBtn.disabled = false;
                        }).catch(function (e) {
                            msg.textContent = String(e);
                            startBtn.disabled = false;
                        });
                    });
                }

                pollActiveRows();

                if (window.location.hash === '#sa-history' || /[?&]highlight=/.test(window.location.search)) {
                    setTimeout(scrollToHistory, 100);
                    var m = window.location.search.match(/[?&]highlight=(\d+)/);
                    if (m) {
                        var hi = historyTable && historyTable.querySelector('tr[data-crawl-id="' + m[1] + '"]');
                        if (hi) hi.classList.add('table-active');
                    }
                }
            })();
        </script>
    @endslot
@endcomponent
