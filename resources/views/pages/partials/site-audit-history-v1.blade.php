        <section class="card border shadow-sm cabinet-sa-panel mt-3" id="sa-history" data-sa-tour="history">
            <div class="card-header py-2 px-3">
                <div class="d-flex flex-wrap align-items-center gap-2 justify-content-between">
                    <h2 class="h6 mb-0 fw-semibold">История проверок</h2>
                    <form method="GET" action="{{ route('pages.site-audit') }}#sa-history" class="d-flex align-items-center gap-2 ms-auto" id="sa-history-search" data-sa-history-ajax>
                        <label class="visually-hidden" for="sa-history-domain">Поиск по домену</label>
                        <input type="search" class="form-control form-control-sm" id="sa-history-domain" name="domain"
                               value="{{ $historyDomain ?? '' }}"
                               placeholder="Поиск по домену…"
                               style="min-width:11rem;max-width:16rem"
                               autocomplete="off"
                               data-sa-history-domain>
                        <button type="submit" class="btn btn-sm btn-outline-secondary">Найти</button>
                        @if(!empty($historyDomain))
                            <a href="{{ route('pages.site-audit') }}#sa-history" class="btn btn-sm btn-link text-secondary px-1" data-sa-history-clear>Сбросить</a>
                        @endif
                    </form>
                </div>
                @if(!empty($historyDomain))
                    <div class="small text-secondary mt-1">
                        Найдено: {{ method_exists($crawls, 'total') ? $crawls->total() : $crawls->count() }} по «{{ $historyDomain }}»
                    </div>
                @endif
                <div class="small text-secondary mt-1 mb-0">
                    После окончания платного тарифа история аудита хранится ещё 14 дней, затем удаляется автоматически.
                </div>
            </div>
            @if(!empty($historyPurgeNotice['show']))
                <div class="alert alert-warning border-0 rounded-0 mb-0 px-3 py-2 small">
                    Вы на бесплатном тарифе после платного.
                    История аудита будет удалена
                    @if(($historyPurgeNotice['days_left'] ?? 0) > 0)
                        через {{ $historyPurgeNotice['days_left'] }} дн. ({{ $historyPurgeNotice['purge_at'] ?? '' }}).
                    @else
                        в ближайшее время (срок {{ $historyPurgeNotice['purge_at'] ?? '' }}).
                    @endif
                    Продлите тариф, чтобы сохранить данные.
                </div>
            @endif
            <div class="card-body p-0">
                <div class="table-responsive cabinet-sa-table-wrap cabinet-sa-table-wrap--flush">
                    <table class="table table-sm table-hover align-middle mb-0" id="sa-history-table">
                        <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Домен</th>
                            <th>Команда</th>
                            <th>Статус</th>
                            <th style="min-width:8rem">Прогресс</th>
                            <th class="text-nowrap">Начало</th>
                            <th class="text-nowrap">Конец</th>
                            <th>Настройки</th>
                            <th>Размер</th>
                            <th>Грубые</th>
                            <th>Прочие</th>
                            <th>Важные</th>
                            <th>Предупреждения</th>
                            <th>Инфо</th>
                            <th class="text-end"></th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($crawls as $c)
                            @php
                                $isCrawlOwner = auth()->id() && (int) $c->user_id === (int) auth()->id();
                                $b = $c->buckets_json ?: [];
                                $stClass = $c->statusCssClass();
                                $sizeBytes = (int) (($crawlSizes ?? [])[$c->id] ?? 0);
                                $pct = $c->pages_total > 0
                                    ? (int) round(100 * $c->pages_fetched / max(1, $c->pages_total))
                                    : 0;
                                $finished = $c->isFinished();
                                $rawSettings = $c->settings_json_raw ?? null;
                                if (is_string($rawSettings)) {
                                    $s = json_decode($rawSettings, true) ?: [];
                                } elseif (is_array($rawSettings)) {
                                    $s = $rawSettings;
                                } else {
                                    $s = [];
                                }
                                $concurrency = max(1, (int) ($s['concurrency'] ?? 1));
                                $speed = (string) ($s['crawl_speed'] ?? '—');
                                $rps = isset($s['rps']) ? (float) $s['rps'] : null;
                                $pagesOnly = ! empty($s['pages_only']);
                                $limitShow = (int) ($c->pages_limit ?: ($s['pages_limit'] ?? 0));
                            @endphp
                            <tr data-crawl-id="{{ $c->id }}"
                                data-finished="{{ $finished ? '1' : '0' }}"
                                data-status-url="{{ route('pages.site-audit.crawl.status', $c->id) }}"
                                class="{{ $finished ? '' : 'cabinet-sa-row--active' }}">
                                <td class="text-secondary">#{{ $c->id }}</td>
                                <td class="fw-medium">
                                    {{ optional($c->project)->domain ?? '—' }}
                                    @if($pagesOnly)
                                        <span class="badge text-bg-light border ms-1" title="Только указанные страницы">страницы</span>
                                    @endif
                                </td>
                                <td class="small" data-sa-team>
                                    @if(optional(optional($c->project)->team)->title)
                                        <span class="text-body" title="{{ $c->project->team->title }}">{{ \Illuminate\Support\Str::limit($c->project->team->title, 28) }}</span>
                                    @else
                                        <span class="text-secondary">—</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="cabinet-sa-status cabinet-sa-status--{{ $stClass }}" data-sa-status>
                                        {{ $c->statusLabelRu() }}
                                    </span>
                                </td>
                                <td class="cabinet-sa-progress-cell" data-sa-progress>
                                    @php
                                        $fetchedN = (int) $c->pages_fetched;
                                        $totalN = max(0, (int) $c->pages_total);
                                        $isFailed = $c->status === 'failed' || $c->status === 'cancelled';
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
                                        $hint = $c->status === 'queued_wait'
                                            ? 'ждёт свободный слот на сервере'
                                            : ($c->status === 'queued'
                                                ? 'запуск'
                                                : ($c->status === 'discovering' ? 'сбор URL' : ($c->status === 'aggregating' ? 'агрегация' : ($isFailed ? 'ошибка' : ($finished ? 'готово' : 'сканирование')))));
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
                                <td class="text-nowrap small text-secondary" data-sa-started>
                                    {{ $c->started_at ? $c->started_at->format('d.m.Y H:i') : ($c->created_at ? $c->created_at->format('d.m.Y H:i') : '—') }}
                                </td>
                                <td class="text-nowrap small text-secondary" data-sa-finished>
                                    @if($c->finished_at)
                                        {{ $c->finished_at->format('d.m.Y H:i') }}
                                    @elseif($finished)
                                        —
                                    @else
                                        @php $eta = $c->estimateFinishedAtFormatted(); @endphp
                                        @if($eta)
                                            <span class="text-muted" title="Оценка по текущей скорости">~{{ $eta }}</span>
                                        @else
                                            <span class="text-muted" title="Слишком рано для оценки">~…</span>
                                        @endif
                                    @endif
                                </td>
                                <td class="small" data-sa-settings>
                                    <div class="text-nowrap">
                                        {{ $concurrency }} {{ $concurrency === 1 ? 'поток' : ($concurrency < 5 ? 'потока' : 'потоков') }}
                                        · {{ $speed }}@if($rps !== null) ({{ rtrim(rtrim(number_format($rps, 1, '.', ''), '0'), '.') }}/с)@endif
                                    </div>
                                    <div class="text-secondary text-nowrap">
                                        @if($limitShow > 0)
                                            лимит {{ number_format($limitShow, 0, '', ' ') }}
                                        @endif
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
                                        ~{{ \App\Services\SiteAudit\SiteAuditCrawlStorage::formatBytes($sizeBytes) }}
                                    </span>
                                </td>
                                <td data-sa-bucket="critical">{{ $b['critical'] ?? '—' }}</td>
                                <td data-sa-bucket="other">{{ $b['other'] ?? '—' }}</td>
                                <td data-sa-bucket="important">{{ $b['important'] ?? '—' }}</td>
                                <td data-sa-bucket="warning">{{ $b['warning'] ?? '—' }}</td>
                                <td data-sa-bucket="info">{{ $b['info'] ?? '—' }}</td>
                                <td class="text-end text-nowrap">
                                    <span class="cabinet-sa-row-actions">
                                        <a class="btn btn-sm btn-outline-primary" href="{{ route('pages.site-audit.crawl.show', $c->id) }}">Сводка</a>
                                        @if($isCrawlOwner && ! $finished)
                                            <form method="POST" action="{{ route('pages.site-audit.crawl.cancel', $c->id) }}" class="d-inline"
                                                  data-sa-cancel-crawl
                                                  data-cabinet-confirm="Остановить проверку #{{ $c->id }}? Уже скачанные страницы останутся."
                                                  data-cabinet-confirm-title="Остановка проверки"
                                                  data-cabinet-confirm-ok="Остановить"
                                                  data-cabinet-confirm-danger="1">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Стоп</button>
                                            </form>
                                        @endif
                                        @if($isCrawlOwner && $finished)
                                            @php
                                                $canResume = (new \App\Services\SiteAudit\SiteAuditCrawlEngine())->canResume($c);
                                            @endphp
                                            @if($canResume)
                                                <form method="POST" action="{{ route('pages.site-audit.crawl.continue', $c->id) }}" class="d-inline"
                                                      data-cabinet-confirm="Продолжить проверку #{{ $c->id }} с {{ number_format((int) $c->pages_fetched, 0, '', ' ') }} URL? Уже скачанные страницы сохранятся."
                                                      data-cabinet-confirm-title="Продолжить проверку"
                                                      data-cabinet-confirm-ok="Продолжить">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-outline-primary">Продолжить</button>
                                                </form>
                                            @endif
                                            <form method="POST" action="{{ route('pages.site-audit.crawl.repeat', $c->id) }}" class="d-inline"
                                                  data-cabinet-confirm="Повторить проверку для {{ e(optional($c->project)->domain ?? 'проекта') }} с теми же настройками? Начнётся новая проверка с нуля."
                                                  data-cabinet-confirm-title="Новая проверка"
                                                  data-cabinet-confirm-ok="Повторить">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-secondary">Повторить</button>
                                            </form>
                                            <form method="POST" action="{{ route('pages.site-audit.crawl.destroy', $c->id) }}" class="d-inline"
                                                  data-cabinet-confirm="Удалить проверку #{{ $c->id }}?"
                                                  data-cabinet-confirm-title="Удаление проверки"
                                                  data-cabinet-confirm-ok="Удалить"
                                                  data-cabinet-confirm-danger="1">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Удалить</button>
                                            </form>
                                        @endif
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr data-sa-empty><td colspan="14" class="text-secondary px-3 py-4 text-center">История пуста</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                @if(method_exists($crawls, 'hasPages') && $crawls->hasPages())
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 px-3 py-2 border-top">
                        <div class="small text-secondary">
                            {{ $crawls->firstItem() }}–{{ $crawls->lastItem() }}
                            из {{ number_format($crawls->total(), 0, '', ' ') }}
                        </div>
                        <nav title="Страницы истории проверок">
                            {{ $crawls->links('pagination::bootstrap-4') }}
                        </nav>
                    </div>
                @elseif(method_exists($crawls, 'total') && $crawls->total() > 0)
                    <div class="small text-secondary px-3 py-2 border-top">
                        Всего {{ number_format($crawls->total(), 0, '', ' ') }}
                    </div>
                @endif
            </div>
        </section>
