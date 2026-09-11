{{-- Тело отчёта (help + filters + table/groups + pagination). --}}
@if(!empty($isExternalModule))
    @include('pages.partials.site-audit-external-module')
@else
@include('pages.partials.site-audit-report-help')
@php
    $isRedirectReport = in_array($code ?? '', ['redirect', 'redirect_chain_long', 'redirect_loop'], true);
    $probeSkipped = is_array($probeStatus ?? null) && ($probeStatus['status'] ?? '') === 'skipped';
    $probePending = is_array($probeStatus ?? null) && ($probeStatus['status'] ?? '') === 'pending';
    $probeRan = is_array($probeStatus ?? null) && ($probeStatus['status'] ?? '') === 'ran';
    $isIndexMismatch = ($code ?? '') === 'index_count_mismatch';
    $plagProgress = null;
    if (($probeStatus['probe'] ?? '') === 'plagiarism_external' && !empty($crawl)) {
        $plagProgress = is_array($crawl->progress_json['plagiarism_external'] ?? null)
            ? $crawl->progress_json['plagiarism_external']
            : null;
    }
    $plagChecked = is_array($plagProgress) ? (int) ($plagProgress['done'] ?? count($plagProgress['rows'] ?? [])) : 0;
    $plagTotal = is_array($plagProgress) ? (int) ($plagProgress['total'] ?? $plagChecked) : 0;
    $plagRows = is_array($plagProgress['rows'] ?? null) ? $plagProgress['rows'] : [];
    $plagRoles = is_array($plagProgress['roles'] ?? null) ? $plagProgress['roles'] : [];
    $plagWarnBelow = (float) config('site_audit.plagiarism_external_warn_below', 70);
    $plagRoleLabels = [
        'home' => 'главная',
        'category' => 'категория',
        'product' => 'товар',
        'service' => 'услуга',
        'sample' => 'добор',
    ];
    $plagShowChecked = ($probeStatus['probe'] ?? '') === 'plagiarism_external'
        && ($probeRan || $probePending)
        && ($plagRows !== [] || $probePending);
@endphp
@if($plagShowChecked)
    <div class="cabinet-sa-plag-checked mb-3">
        <div class="cabinet-sa-plag-checked__head">
            <div>
                <div class="cabinet-sa-plag-checked__title">
                    @if($probePending)
                        Идёт проверка уникальности
                        @if($plagTotal > 0)
                            <span class="cabinet-sa-plag-checked__count">
                                {{ number_format($plagChecked, 0, '', ' ') }}
                                из {{ number_format($plagTotal, 0, '', ' ') }}
                            </span>
                        @endif
                    @else
                        Проверили уникальность
                        <span class="cabinet-sa-plag-checked__count">
                            {{ number_format(count($plagRows), 0, '', ' ') }}
                            {{ count($plagRows) === 1 ? 'страница' : (count($plagRows) < 5 ? 'страницы' : 'страниц') }}
                        </span>
                    @endif
                </div>
                <div class="cabinet-sa-plag-checked__note">
                    В таблицу замечаний ниже попадают только страницы
                    ниже {{ rtrim(rtrim(number_format($plagWarnBelow, 1, ',', ' '), '0'), ',') }}%.
                    Здесь — все URL, которые реально сверили.
                </div>
            </div>
            @if(empty($isPublic) && !empty($crawl))
                <a class="btn btn-sm btn-outline-primary flex-shrink-0"
                   href="{{ route('pages.site-audit.crawl.show', $crawl->id) }}#sa-pane-plagiarism">
                    Антиплагиат
                </a>
            @endif
        </div>
        @if($plagRows !== [])
            <ul class="cabinet-sa-plag-checked__list">
                @foreach($plagRows as $plagRow)
                    @php
                        $pUrl = trim((string) ($plagRow['url'] ?? ''));
                        if ($pUrl === '') {
                            continue;
                        }
                        $pUniq = array_key_exists('uniqueness_pct', $plagRow) && $plagRow['uniqueness_pct'] !== null
                            ? (float) $plagRow['uniqueness_pct']
                            : null;
                        $pErr = trim((string) ($plagRow['error'] ?? ''));
                        $pRole = (string) ($plagRoles[$pUrl] ?? '');
                        $pRoleLabel = $plagRoleLabels[$pRole] ?? '';
                        $pBad = $pUniq !== null && $pUniq < $plagWarnBelow;
                        $pOk = $pUniq !== null && $pUniq >= $plagWarnBelow && $pErr === '';
                    @endphp
                    <li class="cabinet-sa-plag-checked__item{{ $pBad ? ' is-bad' : ($pOk ? ' is-ok' : '') }}">
                        <div class="cabinet-sa-plag-checked__url">
                            @if($pRoleLabel !== '')
                                <span class="cabinet-sa-plag-checked__role">{{ $pRoleLabel }}</span>
                            @endif
                            <a href="{{ $pUrl }}" target="_blank" rel="noopener noreferrer">{{ $pUrl }}</a>
                        </div>
                        <div class="cabinet-sa-plag-checked__meta">
                            @if($pErr !== '')
                                <span class="cabinet-sa-plag-checked__err" title="{{ $pErr }}">ошибка</span>
                            @elseif($pUniq === null)
                                <span class="text-muted">…</span>
                            @else
                                <span class="cabinet-sa-plag-checked__pct{{ $pBad ? ' is-bad' : ' is-ok' }}">
                                    {{ rtrim(rtrim(number_format($pUniq, 1, ',', ' '), '0'), ',') }}%
                                </span>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
@elseif($probeSkipped && ! $isIndexMismatch)
    @if(($probeStatus['probe'] ?? '') === 'plagiarism_external')
        <div class="alert alert-warning border small mb-3 cabinet-sa-probe-cta d-flex flex-wrap align-items-center justify-content-between" style="gap:10px">
            <div>
                Проверку уникальности ещё не запускали — замечаний пока нет не потому что «всё хорошо», а потому что страницы не проверяли.
                Обычно после обхода сами проверяем главную, категорию и товар/услугу; если этого не было — откройте «Антиплагиат» и доберите страницы вручную.
            </div>
            @if(empty($isPublic) && !empty($crawl))
                <a class="btn btn-sm btn-primary flex-shrink-0"
                   href="{{ route('pages.site-audit.crawl.show', $crawl->id) }}#sa-pane-plagiarism">
                    Открыть Антиплагиат
                </a>
            @endif
        </div>
    @else
    <div class="alert alert-warning border small mb-3 cabinet-sa-probe-cta">
        <strong>Проверка не запускалась</strong>
        — {{ \App\Services\SiteAudit\SiteAuditProbeStatus::reasonLabel($probeStatus['reason'] ?? null, $probeStatus['probe'] ?? null) }}.
        Жёлтое «не было» / пустая таблица здесь не значит «всё ок»: отчёт заполняется только после запуска.
        @if(($probeStatus['probe'] ?? '') === 'serp_snippets')
            <div class="mt-1 mb-0">
                Сниппеты — общий набор до <strong>{{ (int) config('site_audit.serp_snippets_max_urls', 30) }}</strong> URL
                (посадочные и добор из обхода), Яндекс и Google; один съём на адрес.
                В «TITLE ≠ выдаче» — все снятые URL: расхождения и совпадения («всё ок»).
                Нормально снимать <strong>при аудите</strong> (вкл. по умолчанию) или кнопкой ниже.
            </div>
        @endif
        @if(($probeStatus['probe'] ?? '') === 'psi')
            @php
                $psiMaxCta = (int) config('site_audit.psi_max_urls', 30);
                $psiReason = (string) ($probeStatus['reason'] ?? '');
            @endphp
            <div class="mt-1 mb-0">
                @if($psiReason === 'api_quota')
                    Замер пробовали (до {{ $psiMaxCta }} страниц), но Google ответил отказом по квоте.
                    Без ключа PageSpeed API лимит очень маленький. Нужен ключ в настройках сервера
                    или подождать сброса дневной квоты — потом снова «Запустить».
                @else
                    PageSpeed меряет до <strong>{{ $psiMaxCta }}</strong> страниц
                    (не весь сайт), каждую — с телефона и с компьютера.
                    Может занять <strong>10–30+ минут</strong>.
                    В новых аудитах запускается само; здесь можно догнать кнопкой.
                @endif
            </div>
        @endif
        @if(!empty($probeStatus['can_run']) && empty($isPublic))
            @php
                $probeConfirm = ($probeStatus['probe'] ?? '') === 'psi'
                    ? 'Запустить PageSpeed по этой проверке? До ' . (int) config('site_audit.psi_max_urls', 30) . ' страниц × телефон и компьютер, может занять 10–30+ минут.'
                    : 'Запустить «' . ($probeStatus['title'] ?? 'проверку') . '» по этой проверке? Может занять 1–3 минуты и потратить API/XML-бюджет.';
            @endphp
            <form method="POST" action="{{ route('pages.site-audit.probe.run', $crawl->id) }}" class="d-inline-block mt-2"
                  data-cabinet-confirm="{{ $probeConfirm }}"
                  data-cabinet-confirm-title="Запустить проверку"
                  data-cabinet-confirm-ok="Запустить">
                @csrf
                <input type="hidden" name="probe" value="{{ $probeStatus['probe'] }}">
                <input type="hidden" name="code" value="{{ $code }}">
                <button type="submit" class="btn btn-sm btn-primary"
                        data-cabinet-probe-submit>
                    Запустить {{ $probeStatus['title'] }}
                </button>
            </form>
        @endif
    </div>
    @endif
@endif
@if($isIndexMismatch && empty($isPublic))
    @php
        $wm = is_array($serpIndexWebmaster ?? null) ? $serpIndexWebmaster : [];
        $wmReady = !empty($wm['ready']);
        $wmConnected = !empty($wm['connected']);
        $wmDomain = (string) ($wm['domain'] ?? ($crawl->project->domain ?? ''));
        $wmMax = (int) config('site_audit.serp_index_webmaster_max', 50000);
        if (! is_array($serpIndexDeep ?? null) && isset($crawl)) {
            $serpIndexDeep = is_array($crawl->progress_json['serp_index']['deep'] ?? null)
                ? $crawl->progress_json['serp_index']['deep']
                : null;
        }
        $probeRan = is_array($probeStatus ?? null) && ($probeStatus['status'] ?? '') === 'ran';
        $deepDone = $probeRan || (
            is_array($serpIndexDeep ?? null)
            && (
                ($serpIndexDeep['source'] ?? '') === 'webmaster'
                || ($serpIndexDeep['mode'] ?? '') === 'webmaster_list'
                || isset($serpIndexDeep['serp_count'])
                || isset($serpIndexDeep['matched'])
            )
        );
        // Не орём «не выполнялась», если в таблице уже есть строки этой сверки.
        if (! $deepDone && ! empty($rows) && count($rows) > 0) {
            $deepDone = true;
        }
        $returnUrl = route('pages.site-audit.report.show', [$crawl->id, $code]);
        $wmConnectUrl = route('yandex-webmaster.connect', [
            'domain' => $wmDomain,
            'return' => $returnUrl,
        ]);
        $extraN = 0;
        $extraUrls = [];
        $extraShown = 0;
        $extraCapped = false;
    @endphp
    <div class="alert {{ $deepDone ? 'alert-success' : 'alert-warning' }} border small mb-3 cabinet-sa-probe-cta">
        <div class="mb-2">
            Сверка списка «в поиске» Вебмастера с проверкой выполняется <strong>автоматически при аудите</strong>
            (этап агрегации), если хост привязан.
        </div>

        @if($wmReady)
            <div class="mb-2">
                <strong>Яндекс.Вебмастер подключён</strong>
                — хост <code>{{ $wm['host_id'] }}</code>
                (до {{ number_format($wmMax, 0, '', ' ') }} URL).
            </div>
        @elseif($wmConnected)
            <div class="mb-2">
                <strong>Вебмастер подключён, хост не привязан</strong>
                к домену <code>{{ $wmDomain }}</code>.
                Привяжите сайт на <a href="{{ url('/#webmaster') }}">главной</a>
                — иначе в следующем аудите сверка будет пропущена.
            </div>
        @else
            <div class="mb-2">
                <strong>Подключите Яндекс.Вебмастер</strong>
                и привяжите хост к <code>{{ $wmDomain !== '' ? $wmDomain : 'домену' }}</code>
                до запуска проверки.
                <a class="btn btn-sm btn-outline-primary ms-1" href="{{ $wmConnectUrl }}">Подключить Вебмастер</a>
            </div>
        @endif

        @if($deepDone && is_array($serpIndexDeep ?? null) && isset($serpIndexDeep['serp_count']))
            @php
                $wmListN = (int) ($serpIndexDeep['serp_count'] ?? 0);
                $crawlN = (int) ($serpIndexDeep['crawl_count'] ?? 0);
                $matchedN = (int) ($serpIndexDeep['matched'] ?? 0);
                $missingN = (int) ($serpIndexDeep['missing_in_index'] ?? 0);
                $extraN = (int) ($serpIndexDeep['extra_in_index'] ?? 0);
                $foundN = isset($serpIndexDeep['found']) ? (int) $serpIndexDeep['found'] : null;
                $extraUrls = [];
                $extraShown = 0;
                $extraCapped = false;
                if ($extraN > 0) {
                    $extraUrls = array_values(array_filter(
                        is_array($serpIndexDeep['extra_urls'] ?? null) ? $serpIndexDeep['extra_urls'] : [],
                        static function ($u) {
                            return is_string($u) && trim($u) !== '';
                        }
                    ));
                    $extraShown = count($extraUrls);
                    $extraCapped = !empty($serpIndexDeep['extra_urls_capped']) || ($extraShown > 0 && $extraShown < $extraN);
                }
            @endphp
            <div class="mb-0">
                <strong>Итог сверки</strong>
                <ul class="mb-0 mt-1 pl-3">
                    <li>В проверке (без robots/noindex):
                        <strong>{{ number_format($crawlN, 0, '', ' ') }}</strong> URL</li>
                    <li>Список «в поиске» из Вебмастера:
                        <strong>{{ number_format($wmListN, 0, '', ' ') }}</strong> URL
                        @if($foundN !== null)
                            <span class="text-muted">
                                (счётчик Вебмастера «в поиске» ≈ {{ number_format($foundN, 0, '', ' ') }} —
                                это другая метрика, со списком может не совпадать)
                            </span>
                        @endif
                    </li>
                    <li>Совпали (есть и в проверке, и в списке):
                        <strong>{{ number_format($matchedN, 0, '', ' ') }}</strong></li>
                    <li>В проверке, но <strong>нет в индексе</strong>:
                        <strong>{{ number_format($missingN, 0, '', ' ') }}</strong>
                        — строки в таблице ниже</li>
                    @if($extraN > 0)
                        <li>Есть в индексе Вебмастера, но <strong>не попали в эту проверку</strong>:
                            {{ number_format($extraN, 0, '', ' ') }}
                            — список ниже</li>
                    @endif
                </ul>
                @if(!empty($serpIndexDeep['truncated']))
                    <div class="alert alert-warning border mb-0 mt-2 py-2 px-3 cabinet-sa-index-truncated">
                        <strong>Список Вебмастера неполный.</strong>
                        Часть строк «нет в индексе» может быть ложной — сначала уточните список в Вебмастере или нажмите «Пересверить».
                    </div>
                @endif
            </div>
        @elseif($deepDone)
            <div class="mb-0 text-muted">
                В таблице {{ number_format((int) ($total ?? 0), 0, '', ' ') }} URL без индекса.
                Сводка сверки не подгрузилась — нажмите «Пересверить» или обновите страницу.
            </div>
        @elseif(!empty($probeSkipped))
            <div class="mb-2 text-muted">
                В этой проверке сверка ещё не выполнялась
                ({{ \App\Services\SiteAudit\SiteAuditProbeStatus::reasonLabel($probeStatus['reason'] ?? null, $probeStatus['probe'] ?? null) }}).
                Запустите новый аудит с привязанным Вебмастером или пересверьте ниже.
            </div>
        @endif

        @if($wmReady && empty($isPublic))
            <form method="POST" action="{{ route('pages.site-audit.probe.run', $crawl->id) }}" class="d-inline-block mt-2">
                @csrf
                <input type="hidden" name="probe" value="serp_index">
                <input type="hidden" name="mode" value="deep">
                <input type="hidden" name="engine" value="yandex">
                <input type="hidden" name="code" value="{{ $code }}">
                <button type="submit" class="btn btn-sm btn-outline-primary"
                        data-cabinet-confirm="Повторно снять список из Вебмастера и сверить с проверкой #{{ $crawl->id }}?"
                        data-cabinet-confirm-title="Пересверить"
                        data-cabinet-confirm-ok="Пересверить">
                    Пересверить сейчас
                </button>
            </form>
        @endif
    </div>
    @php
        if (($extraN ?? 0) > 0 && empty($extraUrls) && ! empty($serpIndexExtraRows) && is_array($serpIndexExtraRows)) {
            $extraUrls = array_values(array_map(static function ($r) {
                return is_array($r) ? (string) ($r['url'] ?? '') : (string) $r;
            }, $serpIndexExtraRows));
            $extraShown = count(array_filter($extraUrls));
        }
        $extraRows = [];
        if (! empty($serpIndexExtraRows) && is_array($serpIndexExtraRows)) {
            $extraRows = array_values($serpIndexExtraRows);
        } elseif (! empty($extraUrls)) {
            foreach ($extraUrls as $u) {
                $extraRows[] = [
                    'url' => $u,
                    'in_crawl' => false,
                    'status' => null,
                    'noindex' => null,
                    'robots' => 'unknown',
                    'reason' => 'not_fetched',
                ];
            }
        }
        $extraShown = count($extraRows);
        $extraAliveN = 0;
        $extraRobotsDenyN = 0;
        $extraRobotsOkN = 0;
        $extraNotFetchedN = 0;
        foreach ($extraRows as $er) {
            $in = ! empty($er['in_crawl']);
            $rob = (string) ($er['robots'] ?? 'unknown');
            if ($in && $rob !== 'deny') {
                $extraAliveN++;
            }
            if ($rob === 'deny') {
                $extraRobotsDenyN++;
            } else {
                $extraRobotsOkN++;
            }
            if (! $in) {
                $extraNotFetchedN++;
            }
        }
    @endphp
    @if(($extraN ?? 0) > 0)
        <div class="cabinet-sa-index-extra" data-sa-index-extra>
            <button type="button"
                    class="cabinet-sa-index-extra__head"
                    data-sa-extra-toggle
                    aria-expanded="true"
                    aria-controls="sa-index-extra-body-{{ $crawl->id }}">
                <span class="cabinet-sa-index-extra__chevron" aria-hidden="true"></span>
                <span class="cabinet-sa-index-extra__title">
                    Есть в индексе, но не попали в эту проверку
                    <span class="cabinet-sa-index-extra__count">{{ number_format($extraN, 0, '', ' ') }}</span>
                </span>
            </button>
            <div class="cabinet-sa-index-extra__body" id="sa-index-extra-body-{{ $crawl->id }}">
                <p class="cabinet-sa-index-extra__hint">
                    Эти URL есть в списке «в поиске» Вебмастера, но не попали в сверку с проверкой
                    (лимит, robots/noindex, нет во внутренних ссылках/sitemap, параметры <code>?cat=</code> и т.п.).
                    Колонка «В обходе» — скачивали ли URL в этой проверке (не live-проверка сайта).
                    По умолчанию — URL, которые <strong>не закрыты robots.txt</strong> (их как раз стоит разобрать: либо вернуть в обход, либо выкинуть из индекса).
                </p>
                <p class="cabinet-sa-index-extra__hint cabinet-sa-index-extra__hint--action">
                    Если страницы <strong>старые / уже удалены</strong> с сайта — подождите переобход
                    или удалите URL вручную в Яндекс.Вебмастере («Переобход страниц» / удаление из поиска).
                    Иначе они ещё долго могут висеть в индексе.
                </p>
                @if($extraShown === 0)
                    <p class="cabinet-sa-index-extra__hint text-muted mb-0">
                        Список ещё не сохранён (старая сверка). Нажмите «Пересверить сейчас» — подтянем все URL.
                    </p>
                @else
                    <div class="cabinet-sa-index-extra__presets" data-sa-extra-presets>
                        <button type="button" class="btn btn-sm btn-primary" data-sa-extra-preset="robots_ok" title="Не запрещены robots.txt — разбирать в первую очередь">
                            robots разрешает
                            <span class="cabinet-sa-index-extra__preset-n">{{ number_format($extraRobotsOkN, 0, '', ' ') }}</span>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-sa-extra-preset="alive" title="Скачаны в этой проверке и не закрыты robots.txt">
                            В обходе · robots OK
                            <span class="cabinet-sa-index-extra__preset-n">{{ number_format($extraAliveN, 0, '', ' ') }}</span>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-sa-extra-preset="not_fetched">
                            Не в обходе
                            <span class="cabinet-sa-index-extra__preset-n">{{ number_format($extraNotFetchedN, 0, '', ' ') }}</span>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-sa-extra-preset="robots_deny">
                            robots запрещает
                            <span class="cabinet-sa-index-extra__preset-n">{{ number_format($extraRobotsDenyN, 0, '', ' ') }}</span>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-sa-extra-preset="all">
                            Все
                            <span class="cabinet-sa-index-extra__preset-n">{{ number_format($extraShown, 0, '', ' ') }}</span>
                        </button>
                    </div>
                    <div class="cabinet-sa-index-extra__toolbar">
                        <input type="search"
                               class="form-control form-control-sm cabinet-sa-index-extra__search"
                               placeholder="Фильтр по URL…"
                               data-sa-extra-filter
                               autocomplete="off">
                        <div class="cabinet-sa-index-extra__actions">
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-sa-extra-copy>
                                Копировать
                            </button>
                            <a class="btn btn-sm btn-outline-primary"
                               href="{{ route('pages.site-audit.report.index-extra', $crawl->id) }}?format=txt">
                                Скачать TXT
                            </a>
                            <a class="btn btn-sm btn-outline-success"
                               href="{{ route('pages.site-audit.report.index-extra', $crawl->id) }}?format=csv">
                                CSV
                            </a>
                        </div>
                    </div>
                    @if(!empty($extraCapped))
                        <div class="small text-muted mb-2">
                            Показано {{ number_format($extraShown, 0, '', ' ') }}
                            из {{ number_format($extraN, 0, '', ' ') }}.
                            @if($extraShown < $extraN)
                                Нажмите «Пересверить» для полного списка.
                            @endif
                        </div>
                    @endif
                    <div class="cabinet-sa-index-extra__table-wrap">
                        <table class="table table-sm table-hover mb-0 cabinet-sa-index-extra__table">
                            <thead>
                            <tr>
                                <th class="cabinet-sa-index-extra__col-n">#</th>
                                <th>URL</th>
                                <th class="cabinet-sa-index-extra__col-flag">В обходе</th>
                                <th class="cabinet-sa-index-extra__col-flag">HTTP</th>
                                <th class="cabinet-sa-index-extra__col-flag">robots.txt</th>
                                <th class="cabinet-sa-index-extra__col-reason">Почему не в сверке</th>
                            </tr>
                            </thead>
                            <tbody data-sa-extra-list></tbody>
                        </table>
                    </div>
                    <div class="cabinet-sa-index-extra__pager" data-sa-extra-pager>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-sa-extra-prev disabled>← Назад</button>
                        <span class="cabinet-sa-index-extra__pager-label" data-sa-extra-page-label>…</span>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-sa-extra-next>Вперёд →</button>
                    </div>
                    <div class="cabinet-sa-index-extra__empty d-none" data-sa-extra-empty>
                        Ничего не найдено по фильтру.
                    </div>
                    <textarea hidden data-sa-extra-json>@json($extraRows)</textarea>
                @endif
            </div>
        </div>
    @endif
@endif
@include('pages.partials.site-audit-report-filters')

@if(!empty($groupable))
    <div class="cabinet-sa-view-toggle mb-3">
        <span class="cabinet-sa-view-toggle__label">Вид:</span>
        @if(!empty($isHtmlErrorReport))
            <a class="cabinet-sa-view-toggle__btn {{ ($viewMode ?? '') === 'groups' ? 'is-active' : '' }}"
               href="{{ request()->fullUrlWithQuery(['view' => 'groups', 'page' => 1]) }}"
               title="Одинаковые ошибки вместе — видно сквозной шаблон (шапка/подвал)">По ошибкам</a>
            <a class="cabinet-sa-view-toggle__btn {{ ($viewMode ?? '') === 'list' ? 'is-active' : '' }}"
               href="{{ request()->fullUrlWithQuery(['view' => 'list', 'page' => 1]) }}"
               title="Список всех URL по одной строке">По страницам</a>
            @if(($viewMode ?? '') === 'groups' && !empty($groupTotal))
                <span class="text-muted small ms-2">паттернов: {{ number_format((int) $groupTotal, 0, '', ' ') }} · URL: {{ number_format((int) $total, 0, '', ' ') }}</span>
            @endif
        @elseif(!empty($isTextInNoindexReport))
            <a class="cabinet-sa-view-toggle__btn {{ ($viewMode ?? '') === 'groups' ? 'is-active' : '' }}"
               href="{{ request()->fullUrlWithQuery(['view' => 'groups', 'page' => 1]) }}">По содержимому</a>
            <a class="cabinet-sa-view-toggle__btn {{ ($viewMode ?? '') === 'list' ? 'is-active' : '' }}"
               href="{{ request()->fullUrlWithQuery(['view' => 'list', 'page' => 1]) }}">По страницам</a>
            @if(($viewMode ?? '') === 'groups' && !empty($groupTotal))
                <span class="text-muted small ms-2">блоков: {{ number_format((int) $groupTotal, 0, '', ' ') }} · URL: {{ number_format((int) $total, 0, '', ' ') }}</span>
            @endif
        @elseif(!empty($isInsecureFormReport))
            <a class="cabinet-sa-view-toggle__btn {{ ($viewMode ?? '') === 'groups' ? 'is-active' : '' }}"
               href="{{ request()->fullUrlWithQuery(['view' => 'groups', 'page' => 1]) }}">По формам</a>
            <a class="cabinet-sa-view-toggle__btn {{ ($viewMode ?? '') === 'list' ? 'is-active' : '' }}"
               href="{{ request()->fullUrlWithQuery(['view' => 'list', 'page' => 1]) }}">По страницам</a>
            @if(($viewMode ?? '') === 'groups' && !empty($groupTotal))
                <span class="text-muted small ms-2">форм: {{ number_format((int) $groupTotal, 0, '', ' ') }} · стр.: {{ number_format((int) $total, 0, '', ' ') }}</span>
            @endif
        @elseif(!empty($isCrawlImagesReport))
            <a class="cabinet-sa-view-toggle__btn {{ ($viewMode ?? '') === 'groups' ? 'is-active' : '' }}"
               href="{{ request()->fullUrlWithQuery(['view' => 'groups', 'page' => 1]) }}">По картинкам</a>
            <a class="cabinet-sa-view-toggle__btn {{ ($viewMode ?? '') === 'list' ? 'is-active' : '' }}"
               href="{{ request()->fullUrlWithQuery(['view' => 'list', 'page' => 1]) }}">По вхождениям</a>
            @if(($viewMode ?? '') === 'groups' && !empty($groupTotal))
                <span class="text-muted small ms-2">картинок: {{ number_format((int) $groupTotal, 0, '', ' ') }} · вхождений: {{ number_format((int) $total, 0, '', ' ') }}</span>
            @endif
        @elseif(!empty($isLinkInvertedReport))
            @php
                $isBrokenLinksReport = in_array($code ?? '', [
                    'page_has_broken_links',
                    'page_has_broken_external_links',
                ], true);
            @endphp
            <a class="cabinet-sa-view-toggle__btn {{ ($viewMode ?? '') === 'groups' ? 'is-active' : '' }}"
               href="{{ request()->fullUrlWithQuery(['view' => 'groups', 'page' => 1]) }}"
               title="{{ $isBrokenLinksReport ? 'Одинаковые битые цели вместе — видно мёртвую ссылку в общем блоке' : 'Одинаковые исходящие вместе — видно общий блок (шапка/подвал/соцсети)' }}">{{ $isBrokenLinksReport ? 'По целям' : 'По ссылкам' }}</a>
            <a class="cabinet-sa-view-toggle__btn {{ ($viewMode ?? '') === 'list' ? 'is-active' : '' }}"
               href="{{ request()->fullUrlWithQuery(['view' => 'list', 'page' => 1]) }}"
               title="{{ $isBrokenLinksReport ? 'Каждая страница со своим списком битых ссылок' : 'Каждая страница со своим списком исходящих' }}">По страницам</a>
            @if(($viewMode ?? '') === 'groups' && !empty($groupTotal))
                <span class="text-muted small ms-2">{{ $isBrokenLinksReport ? 'целей' : 'целей' }}: {{ number_format((int) $groupTotal, 0, '', ' ') }} · стр.: {{ number_format((int) $total, 0, '', ' ') }}</span>
            @endif
        @else
            <a class="cabinet-sa-view-toggle__btn {{ ($viewMode ?? '') === 'groups' ? 'is-active' : '' }}"
               href="{{ request()->fullUrlWithQuery(['view' => 'groups', 'page' => 1]) }}"
               title="Одинаковые проблемы сгруппировать вместе (удобно для дублей)">Группы</a>
            <a class="cabinet-sa-view-toggle__btn {{ ($viewMode ?? '') === 'list' ? 'is-active' : '' }}"
               href="{{ request()->fullUrlWithQuery(['view' => 'list', 'page' => 1]) }}"
               title="Просто список всех URL по одной строке">Список</a>
            @if(($viewMode ?? '') === 'groups' && !empty($groupTotal))
                <span class="text-muted small ms-2">групп: {{ number_format((int) $groupTotal, 0, '', ' ') }} · URL: {{ number_format((int) $total, 0, '', ' ') }}</span>
            @endif
        @endif
    </div>
@endif

@if(!empty($htmlSitewide) && is_array($htmlSitewide))
    <div class="alert alert-warning border small mb-3 cabinet-sa-html-sitewide">
        @if(!empty($isTextInNoindexReport))
            <strong>Скорее общий блок</strong>
            (шапка / подвал / соцсети) —
            одно и то же содержимое noindex на
            <strong>{{ number_format((int) $htmlSitewide['pages'], 0, '', ' ') }}</strong>
            из {{ number_format((int) $htmlSitewide['total'], 0, '', ' ') }} стр.
            ({{ (int) $htmlSitewide['pct'] }}%):
            @if(!empty($htmlSitewide['label']))
                <code class="cabinet-sa-html-sitewide__code">{{ $htmlSitewide['label'] }}</code>.
            @endif
            <div class="mt-1 mb-0">
                Не разбирайте каждую страницу: правьте шаблон один раз.
                @if(($viewMode ?? '') === 'list')
                    <a href="{{ request()->fullUrlWithQuery(['view' => 'groups', 'page' => 1]) }}">Смотреть по содержимому →</a>
                @endif
            </div>
        @elseif(!empty($isCrawlImagesReport))
            <strong>Скорее общий блок</strong>
            (шапка / счётчик / шаблон) —
            одна и та же картинка на
            <strong>{{ number_format((int) $htmlSitewide['pages'], 0, '', ' ') }}</strong>
            из {{ number_format((int) $htmlSitewide['total'], 0, '', ' ') }} стр.
            ({{ (int) $htmlSitewide['pct'] }}%):
            @if(!empty($htmlSitewide['label']))
                <code class="cabinet-sa-html-sitewide__code">{{ $htmlSitewide['label'] }}</code>.
            @endif
            <div class="mt-1 mb-0">
                Не разбирайте каждую страницу: уберите или замените файл в общем шаблоне один раз.
                @if(($viewMode ?? '') === 'list')
                    <a href="{{ request()->fullUrlWithQuery(['view' => 'groups', 'page' => 1]) }}">Смотреть по картинкам →</a>
                @endif
            </div>
        @elseif(!empty($isLinkInvertedReport))
            <strong>Скорее общий блок</strong>
            (шапка / подвал / соцсети) —
            одна и та же исходящая на
            <strong>{{ number_format((int) $htmlSitewide['pages'], 0, '', ' ') }}</strong>
            из {{ number_format((int) $htmlSitewide['total'], 0, '', ' ') }} стр.
            ({{ (int) $htmlSitewide['pct'] }}%):
            @if(!empty($htmlSitewide['label']))
                <code class="cabinet-sa-html-sitewide__code">{{ $htmlSitewide['label'] }}</code>.
            @endif
            <div class="mt-1 mb-0">
                Не разбирайте каждую страницу: уберите или разметьте ссылку в общем шаблоне один раз.
                @if(($viewMode ?? '') === 'list')
                    <a href="{{ request()->fullUrlWithQuery(['view' => 'groups', 'page' => 1]) }}">{{ in_array($code ?? '', ['page_has_broken_links', 'page_has_broken_external_links'], true) ? 'Смотреть по целям →' : 'Смотреть по ссылкам →' }}</a>
                @endif
            </div>
        @elseif(!empty($isInsecureFormReport))
            <strong>Скорее общий блок</strong>
            (шапка / подвал / попап) —
            одна и та же форма на
            <strong>{{ number_format((int) $htmlSitewide['pages'], 0, '', ' ') }}</strong>
            из {{ number_format((int) $htmlSitewide['total'], 0, '', ' ') }} стр.
            ({{ (int) $htmlSitewide['pct'] }}%):
            @if(!empty($htmlSitewide['label']))
                <code class="cabinet-sa-html-sitewide__code">{{ $htmlSitewide['label'] }}</code>.
            @endif
            <div class="mt-1 mb-0">
                Не правьте каждую страницу: смените action в шаблоне формы один раз.
                @if(($viewMode ?? '') === 'list')
                    <a href="{{ request()->fullUrlWithQuery(['view' => 'groups', 'page' => 1]) }}">Смотреть по формам →</a>
                @endif
            </div>
        @else
            <strong>Скорее сквозной шаблон</strong>
            (шапка / подвал / layout) —
            одна и та же ошибка на
            <strong>{{ number_format((int) $htmlSitewide['pages'], 0, '', ' ') }}</strong>
            из {{ number_format((int) $htmlSitewide['total'], 0, '', ' ') }} стр.
            ({{ (int) $htmlSitewide['pct'] }}%):
            <code class="cabinet-sa-html-sitewide__code">{{ $htmlSitewide['label'] }}</code>.
            <div class="mt-1 mb-0">
                Не правьте каждую страницу: найдите общий include и почините один раз —
                ошибка уйдёт с остальных URL после нового съема.
                @if(($viewMode ?? '') === 'list')
                    <a href="{{ request()->fullUrlWithQuery(['view' => 'groups', 'page' => 1]) }}">Смотреть по ошибкам →</a>
                @endif
            </div>
        @endif
    </div>
@endif

@if(!empty($canNote))
    <div class="cabinet-sa-note-legend{{ ($code ?? '') === 'index_count_mismatch' ? ' cabinet-sa-note-legend--compact' : '' }}" role="note">
        <div class="cabinet-sa-note-legend__title">Действия со строкой</div>
        <div class="cabinet-sa-note-legend__items">
            <div class="cabinet-sa-note-legend__item">
                <span class="cabinet-sa-note-legend__chip cabinet-sa-note-legend__chip--note">
                    <i class="fa fa-comment" aria-hidden="true"></i> Заметка
                </span>
                <span class="cabinet-sa-note-legend__desc">свой комментарий</span>
            </div>
            <div class="cabinet-sa-note-legend__item">
                <span class="cabinet-sa-note-legend__chip cabinet-sa-note-legend__chip--fixed">
                    <i class="fa fa-check" aria-hidden="true"></i> Исправлено
                </span>
                <span class="cabinet-sa-note-legend__desc">починили — уходит из счётчиков</span>
            </div>
            <div class="cabinet-sa-note-legend__item">
                <span class="cabinet-sa-note-legend__chip cabinet-sa-note-legend__chip--ignore">
                    <i class="fa fa-ban" aria-hidden="true"></i> Игнор
                </span>
                <span class="cabinet-sa-note-legend__desc">не ошибка / ложное срабатывание</span>
            </div>
        </div>
        @if(($code ?? '') !== 'index_count_mismatch')
            <div class="cabinet-sa-note-legend__foot">
                Пометки помнятся для этого сайта (тот же тип + тот же URL), пока сами не снимете.
            </div>
        @endif
    </div>
@endif

@if(!empty($groupable) && ($viewMode ?? '') === 'groups')
    <div class="cabinet-sa-dup-groups">
        @forelse($groups as $gi => $group)
            @php $tone = $gi % 6; @endphp
            <div class="cabinet-sa-dup-group cabinet-sa-dup-group--t{{ $tone }}{{ !empty($group['likely_template']) ? ' cabinet-sa-dup-group--template' : '' }}">
                <div class="cabinet-sa-dup-group__head">
                    <div class="cabinet-sa-dup-group__meta">
                        <span class="cabinet-sa-dup-group__count">{{ number_format((int) $group['size'], 0, '', ' ') }} стр.</span>
                        @if(!empty($group['status']))
                            @php $gStatus = (int) $group['status']; @endphp
                            <span class="cabinet-sa-status-pill {{ $gStatus >= 500 ? 'cabinet-sa-status-pill--5xx' : ($gStatus >= 400 ? 'cabinet-sa-status-pill--4xx' : '') }}">{{ $gStatus }}</span>
                        @endif
                        @if(!empty($isLinkInvertedReport) && (($group['scope'] ?? '') === 'external' || ($group['scope'] ?? '') === 'internal'))
                            <span class="cabinet-sa-dup-group__scope cabinet-sa-dup-group__scope--{{ $group['scope'] }}">{{ ($group['scope'] ?? '') === 'internal' ? 'внутренняя' : 'внешняя' }}</span>
                        @endif
                        @if(!empty($group['likely_template']))
                            <span class="cabinet-sa-dup-group__badge">{{ (!empty($isCrawlImagesReport) || !empty($isImagesWithoutAltReport) || !empty($isLinkInvertedReport) || !empty($isInsecureFormReport)) ? 'общий блок' : 'сквозной' }}</span>
                        @endif
                    </div>
                    <div class="cabinet-sa-dup-group__label">
                        @if(!empty($isInsecureFormReport))
                            <div class="cabinet-sa-dup-group__form-label">{{ $group['label'] }}</div>
                            @if(!empty($group['href']))
                                <a href="{{ $group['href'] }}" target="_blank" rel="noopener noreferrer" class="cabinet-sa-url-break">{{ $group['href'] }}</a>
                            @endif
                        @elseif(!empty($group['href']))
                            @if(!empty($group['host']))
                                <span class="cabinet-sa-dup-group__host">{{ $group['host'] }}</span>
                            @endif
                            <a href="{{ $group['href'] }}" target="_blank" rel="noopener noreferrer" class="cabinet-sa-url-break">{{ $group['href'] }}</a>
                        @else
                            {{ $group['label'] }}
                        @endif
                    </div>
                </div>
                @if(!empty($group['hint']))
                    <div class="cabinet-sa-dup-group__hint">{{ $group['hint'] }}</div>
                @endif
                @php
                    $groupUrls = is_array($group['urls'] ?? null) ? $group['urls'] : [];
                    $groupUrlTotal = count($groupUrls);
                    $groupUrlPreview = 10;
                    // Не раздуваем DOM тысячами <a> в сквозном блоке.
                    $groupUrlExpandMax = 40;
                    $groupUrlsHead = array_slice($groupUrls, 0, $groupUrlPreview);
                    $groupUrlsTailAll = $groupUrlTotal > $groupUrlPreview
                        ? array_slice($groupUrls, $groupUrlPreview)
                        : [];
                    $groupUrlsTail = array_slice($groupUrlsTailAll, 0, $groupUrlExpandMax);
                    $groupUrlsHidden = max(0, count($groupUrlsTailAll) - count($groupUrlsTail));
                @endphp
                <ul class="cabinet-sa-dup-group__urls">
                    @foreach($groupUrlsHead as $u)
                        <li>
                            <a href="{{ $u['url'] }}" target="_blank" rel="noopener noreferrer">{{ $u['url'] }}</a>
                        </li>
                    @endforeach
                </ul>
                @if($groupUrlsTail !== [] || $groupUrlsHidden > 0)
                    <details class="cabinet-sa-dup-group__more">
                        <summary class="cabinet-sa-dup-group__more-sum">
                            Ещё {{ number_format($groupUrlTotal - $groupUrlPreview, 0, '', ' ') }} {{ !empty($isCrawlImagesReport) ? 'стр.' : 'URL' }}
                        </summary>
                        @if($groupUrlsTail !== [])
                            <ul class="cabinet-sa-dup-group__urls cabinet-sa-dup-group__urls--more">
                                @foreach($groupUrlsTail as $u)
                                    <li>
                                        <a href="{{ $u['url'] }}" target="_blank" rel="noopener noreferrer">{{ $u['url'] }}</a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                        @if($groupUrlsHidden > 0)
                            <div class="cabinet-sa-dup-group__more-note text-muted small mt-2">
                                Показаны {{ number_format($groupUrlPreview + count($groupUrlsTail), 0, '', ' ') }}
                                из {{ number_format($groupUrlTotal, 0, '', ' ') }}.
                                Остальные {{ number_format($groupUrlsHidden, 0, '', ' ') }} —
                                в режиме
                                <a href="{{ request()->fullUrlWithQuery(['view' => 'list', 'page' => 1]) }}">{{ !empty($isCrawlImagesReport) ? 'По вхождениям' : 'По страницам' }}</a>.
                            </div>
                        @endif
                    </details>
                @endif
            </div>
        @empty
            <div class="text-muted px-3 py-3">
                @if(!empty($probeSkipped))
                    @if(($probeStatus['probe'] ?? '') === 'plagiarism_external')
                        Пока пусто.
                    @else
                        Находок нет — проверка ещё не запускалась. Нажмите «Запустить» выше.
                    @endif
                @elseif(($probeStatus['probe'] ?? '') === 'plagiarism_external')
                    Замечаний нет — все проверенные страницы выше порога (список с процентами — в блоке «Проверили» выше).
                @else
                    Находок нет — проверка выполнена, замечаний по этому отчёту нет.
                @endif
            </div>
        @endforelse
    </div>
@else
    @php
        $isPsiReport = in_array($code ?? '', ['psi_mobile', 'psi_desktop'], true);
    @endphp
    @if($isPsiReport)
        @include('pages.partials.site-audit-psi-report')
    @else
    @php
        $colspan = 2; // URL + Детали (по умолчанию)
        if (!empty($showReferrers)) { $colspan++; }
        if (!empty($canIgnore) || !empty($canNote)) { $colspan++; }
        $isRedirectReport = in_array($code ?? '', ['redirect', 'redirect_chain_long', 'redirect_loop'], true);
        $isBrokenTarget = !empty($showReferrers) && ! $isRedirectReport;
        $isSerpTitleReport = ($code ?? '') === 'serp_title_mismatch';
        $isHeadingHierarchyReport = ($code ?? '') === 'heading_hierarchy';
        $isMetaLenReport = in_array($code ?? '', [
            'title_too_short', 'title_too_long', 'description_too_short', 'description_too_long',
        ], true);
        $isHeavyImageReport = ($code ?? '') === 'heavy_image';
        $isImagesWithoutAltReport = ($code ?? '') === 'images_without_alt';
        $isImageCardReport = $isHeavyImageReport || $isImagesWithoutAltReport;
        $isAffiliateReport = ($code ?? '') === 'probable_affiliate';
        $isIndexMismatchReport = ($code ?? '') === 'index_count_mismatch';
        $isCannibalReport = ($code ?? '') === 'keyword_cannibalization';
        $isCrawlPagesReport = ($code ?? '') === 'crawl_pages';
        $isCrawlImagesReport = ($code ?? '') === 'crawl_images';
        if ($isCannibalReport) {
            // URL + Запрос + Посадочная + TITLE (+ действия)
            $colspan = 4;
            if (!empty($canIgnore) || !empty($canNote)) { $colspan++; }
        }
        if ($isCrawlPagesReport) {
            // URL + код + title + desc + h1 + h1/h2 + слов + внутр + внеш + img + canonical + индекс + глубина
            $colspan = 13;
            if (!empty($canIgnore) || !empty($canNote)) { $colspan++; }
        }
        if ($isCrawlImagesReport) {
            $colspan = 7;
        }
        $showActions = !empty($canNote) || !empty($canIgnore);
        // Срочность задаётся на тип ошибки в конфиге. В обычном отчёте все строки одинаковые —
        // колонку «Приор.» показываем только в сводных (virtual), где реально смешаны уровни.
        $showSeverityCol = false;
        if (! empty($meta['virtual']) && ! empty($meta['codes']) && is_array($meta['codes'])) {
            $sevSet = [];
            foreach ($meta['codes'] as $childCode) {
                $sevSet[(string) config('site_audit.findings.' . $childCode . '.severity', 'info')] = true;
            }
            $showSeverityCol = count($sevSet) > 1;
        }
        if ($showSeverityCol) {
            $colspan++;
        }
        $urlTip = $isCrawlPagesReport
            ? "Адрес страницы из этой проверки.\nНажмите — откроется сайт в новой вкладке."
            : ($isCannibalReport
            ? "Страница, у которой в TITLE/H1 нашёлся чужой запрос из мониторинга.\nПравильная посадочная — в соседней колонке."
            : ($isRedirectReport
            ? "URL, который сам отвечает редиректом.\nГде на него ссылаются — колонка «Откуда ссылаются» (меню, HTML, sitemap)."
            : ($isBrokenTarget
                ? "Это URL, который сам ответил ошибкой при обходе (404 и т.п.).\nНе путать со страницей, где стоит ссылка — она в колонке «Откуда ссылаются»."
                : ($isImageCardReport
                    ? ($isImagesWithoutAltReport
                        ? "Страница HTML, на которой стоит картинка без alt.\nСама картинка (файл) — в колонке «Изображение»."
                        : "Страница HTML, на которой стоит тяжёлая картинка.\nСама картинка (файл) — в колонке «Изображение».")
                    : ($isAffiliateReport
                        ? "Страница, на которой стоят исходящие ссылки, похожие на партнёрские.\nСами ссылки — в колонке «Партнёрки»."
                        : "Адрес страницы с проблемой.\nНажмите ссылку — откроется сайт в новой вкладке.")))));
        $urlColLabel = $isCrawlPagesReport
            ? 'URL'
            : ($isCannibalReport
            ? 'Лишняя страница'
            : ($isRedirectReport
            ? 'URL'
            : ($isBrokenTarget ? 'Битый URL' : ($isImageCardReport ? 'Страница' : ($isAffiliateReport ? 'Страница' : 'URL')))));
        $detailsColLabel = $isSerpTitleReport ? 'Сравнение title'
            : ($isHeadingHierarchyReport ? 'Иерархия H1–H6'
            : ($isMetaLenReport
                ? (strpos((string) ($code ?? ''), 'description_') === 0 ? 'Description' : 'TITLE')
            : ($isImageCardReport ? 'Изображение'
                : ($isAffiliateReport ? 'Партнёрки'
                    : ($isIndexMismatchReport ? 'В проверке' : 'Детали')))));
        $detailsColTip = $isSerpTitleReport
            ? "Слева — TITLE в HTML, справа — заголовок в сниппете ПС."
            : ($isHeadingHierarchyReport
                ? "Какое нарушение (пропуск уровня или заголовок до H1) и кусок структуры страницы.\n«Сюда прыжок» — проблемный заголовок."
            : ($isMetaLenReport
                ? (strpos((string) ($code ?? ''), 'description_') === 0
                    ? "Какой meta description сейчас на странице и его длина относительно порога."
                    : "Какой TITLE сейчас на странице и его длина относительно порога.")
            : ($isHeavyImageReport
                ? "Файл картинки: превью, вес, имя и полный URL.\nИменно его нужно сжать или заменить."
                : ($isImagesWithoutAltReport
                    ? "Файл картинки без alt: превью, размер в вёрстке, имя и полный URL.\nДобавьте осмысленный alt (или alt=\"\" для декора)."
                    : ($isAffiliateReport
                        ? "Какие исходящие ссылки похожи на партнёрские: сеть, хост и полный URL."
                        : ($isIndexMismatchReport
                            ? "Как URL попал в эту проверку, глубина, раздел и есть ли ?параметры.\nВсе строки — нет в индексе Вебмастера."
                            : "Кратко что не так: код ответа, какой дубль, какой запрос и т.д."))))));
        $refColTip = $isRedirectReport
            ? "Откуда URL попал в проверка: sitemap.xml, посев, главная или страница со ссылкой."
            : "Откуда URL попал в проверка или страницы со ссылкой.";
    @endphp
    @if($isCrawlPagesReport)
        @include('pages.partials.site-audit-crawl-pages-table')
    @elseif(!empty($isCrawlImagesReport))
        @include('pages.partials.site-audit-crawl-images-table')
    @else
    @php
        $reportColKeys = ['url'];
        if (!empty($showSeverityCol)) {
            $reportColKeys[] = 'severity';
        }
        if (!empty($isCannibalReport)) {
            $reportColKeys[] = 'query';
            $reportColKeys[] = 'landing';
            $reportColKeys[] = 'comp_title';
        } else {
            $reportColKeys[] = 'details';
        }
        if (!empty($showReferrers)) {
            $reportColKeys[] = 'from';
        }
        if (!empty($showActions)) {
            $reportColKeys[] = 'actions';
        }
        $reportColKeys = array_merge($reportColKeys, \App\Services\SiteAudit\SiteAuditReportColumns::pageColumnKeys());
        $reportColKeys = array_values(array_unique($reportColKeys));
        $rcDefaultVisible = array_values(array_filter(
            \App\Services\SiteAudit\SiteAuditReportColumns::defaultKeys(),
            function ($k) use ($reportColKeys) {
                return in_array($k, $reportColKeys, true);
            }
        ));
        $pageColKeys = \App\Services\SiteAudit\SiteAuditReportColumns::pageColumnKeys();
        $pageColMeta = [];
        foreach (\App\Services\SiteAudit\SiteAuditReportColumns::catalog() as $col) {
            if (($col['source'] ?? '') === 'page') {
                $pageColMeta[$col['key']] = $col;
            }
        }
    @endphp
    @include('pages.partials.site-audit-report-cols-toolbar', ['reportColKeys' => $reportColKeys])
    <div class="cabinet-sa-table-wrap{{ $isSerpTitleReport ? ' cabinet-sa-table-wrap--serp-title' : '' }}{{ $isBrokenTarget ? ' cabinet-sa-table-wrap--broken' : '' }}{{ $isRedirectReport ? ' cabinet-sa-table-wrap--redirect' : '' }}{{ $isImageCardReport ? ' cabinet-sa-table-wrap--heavy' : '' }}{{ $isAffiliateReport ? ' cabinet-sa-table-wrap--aff' : '' }}{{ !empty($isIndexMismatchReport) ? ' cabinet-sa-table-wrap--index-mismatch' : '' }}{{ $isCannibalReport ? ' cabinet-sa-table-wrap--cannibal' : '' }}">
        <table class="table table-sm table-hover mb-0 cabinet-sa-findings-table"
               data-sa-report-table
               data-sa-report-code="{{ $code }}"
               data-sa-cols-default="{{ implode(',', $rcDefaultVisible) }}">
            <thead class="table-light">
            <tr>
                <th data-sa-col="url">
                    {{ $urlColLabel }}
                    @include('pages.partials.site-audit-tip', ['tip' => $urlTip])
                </th>
                @if($showSeverityCol)
                    <th data-sa-col="severity">
                        Приор.
                        @include('pages.partials.site-audit-tip', ['tip' => "Срочность разных типов в сводке.\nГрубые — чинить в первую очередь.\nИнфо — просто знать.\nВ обычном отчёте (один тип) колонка скрыта: все строки одного уровня."])
                    </th>
                @endif
                @if($isCannibalReport)
                    <th class="cabinet-sa-th-cannibal" data-sa-col="query" title="Запрос из мониторинга позиций">
                        <span class="cabinet-sa-th-cannibal__main">
                            Запрос
                            @include('pages.partials.site-audit-tip', ['tip' => "Запрос из модуля мониторинга позиций.\nЕго нашли в TITLE/H1 у лишней страницы слева."])
                        </span>
                        <span class="cabinet-sa-th-cannibal__sub">из мониторинга</span>
                    </th>
                    <th class="cabinet-sa-th-cannibal" data-sa-col="landing" title="Посадочная из мониторинга позиций — куда запрос должен вести">
                        <span class="cabinet-sa-th-cannibal__main">
                            Посадочная
                            @include('pages.partials.site-audit-tip', ['tip' => "Целевой URL запроса в мониторинге позиций.\nСюда ключ должен вести; слева — страница, которая с ним конкурирует."])
                        </span>
                        <span class="cabinet-sa-th-cannibal__sub">из мониторинга позиций</span>
                    </th>
                    <th data-sa-col="comp_title" title="TITLE страницы слева">
                        TITLE
                        @include('pages.partials.site-audit-tip', ['tip' => "TITLE лишней страницы — по нему видно, почему сработало совпадение."])
                    </th>
                @else
                    <th data-sa-col="details" title="{{ $isAffiliateReport ? 'Партнёрские ссылки: сеть и URL' : ($isImageCardReport ? 'Файл изображения: превью и URL' : ($isSerpTitleReport ? 'Сравнение TITLE на сайте и в выдаче ПС' : 'Коротко: что именно не так (код ответа, дубль и т.п.).')) }}">
                        {{ $detailsColLabel }}
                        @include('pages.partials.site-audit-tip', ['tip' => $detailsColTip])
                    </th>
                @endif
                @foreach($pageColKeys as $pKey)
                    @php $pCol = $pageColMeta[$pKey] ?? ['label' => $pKey]; @endphp
                    <th data-sa-col="{{ $pKey }}" class="is-col-hidden">{{ $pCol['label'] }}</th>
                @endforeach
                @if(!empty($showReferrers))
                    <th data-sa-col="from" title="Откуда URL попал в очередь проверки">
                        {{ $isBrokenTarget ? 'Страница со ссылкой' : 'Откуда' }}
                        @include('pages.partials.site-audit-tip', [
                            'tip' => $refColTip,
                        ])
                    </th>
                @endif
                @if($showActions)
                    <th class="cabinet-sa-th-actions" data-sa-col="actions">
                        Действия
                        @include('pages.partials.site-audit-tip', [
                            'tip' => "Заметка — комментарий.\nИсправлено — починили.\nИгнор — не считаем ошибкой.",
                            'tipSide' => 'left',
                        ])
                    </th>
                @endif
            </tr>
            </thead>
            <tbody>
            @forelse($rows as $row)
                @php
                    $isIgn = !empty($ignoredMap[(int) ($row->id ?? 0)]);
                    $note = $notesMap[(int) ($row->id ?? 0)] ?? null;
                    $isFixed = is_array($note) && (($note['status'] ?? '') === 'fixed');
                    $noteComment = is_array($note) ? (string) ($note['comment'] ?? '') : '';
                    $rowMeta = is_array($row->meta_json ?? null) ? $row->meta_json : [];
                    $referrers = is_array($rowMeta['referrers'] ?? null) ? $rowMeta['referrers'] : [];
                    $referrerCount = (int) ($rowMeta['referrer_count'] ?? count($referrers));
                    $pageCols = is_array($row->page_cols ?? null) ? $row->page_cols : [];
                @endphp
                <tr class="{{ $isIgn ? 'cabinet-sa-row--ignored' : '' }}{{ $isFixed ? ' cabinet-sa-row--fixed' : '' }}">
                    <td class="cabinet-sa-url" data-sa-col="url">
                        @php
                            $urlDisp = !empty($isBrokenTarget)
                                ? \App\Services\SiteAudit\SiteAuditFindingPresenter::brokenUrlDisplay((string) $row->url)
                                : ['display' => (string) $row->url, 'warn' => null];
                        @endphp
                        @include('pages.partials.site-audit-url-with-copy', [
                            'url' => (string) $row->url,
                            'label' => $urlDisp['display'],
                            'warn' => $urlDisp['warn'] ?? null,
                            'isIgn' => $isIgn,
                            'isFixed' => $isFixed,
                        ])
                    </td>
                    @if(!empty($showSeverityCol))
                        <td class="cabinet-sa-sev-cell" data-sa-col="severity">
                            @php $sevKey = (string) ($row->severity ?? 'info'); @endphp
                            <span class="cabinet-sa-sev-badge cabinet-sa-sev-badge--{{ $sevKey }}" title="{{ \App\Services\SiteAudit\SiteAuditFindingPresenter::severityLabel($sevKey) }}">
                                {{ \App\Services\SiteAudit\SiteAuditFindingPresenter::severityTag($sevKey) }}
                            </span>
                        </td>
                    @endif
                    @if($isCannibalReport)
                        @php
                            $cQuery = trim((string) ($rowMeta['query'] ?? ''));
                            $cLanding = trim((string) ($rowMeta['landing_url'] ?? ''));
                            $cTitle = trim((string) ($rowMeta['competitor_title'] ?? ''));
                        @endphp
                        <td class="small cabinet-sa-cannibal-query-cell" data-sa-col="query">
                            @if($cQuery !== '')
                                <span class="cabinet-sa-cannibal-query" title="{{ $cQuery }}">«{{ \Illuminate\Support\Str::limit($cQuery, 60) }}»</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="small cabinet-sa-cannibal-landing-cell" data-sa-col="landing">
                            @if($cLanding !== '')
                                <a href="{{ $cLanding }}" target="_blank" rel="noopener noreferrer" class="cabinet-sa-url-break" title="Посадочная из мониторинга позиций">{{ $cLanding }}</a>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="small cabinet-sa-cannibal-title-cell" data-sa-col="comp_title">
                            @if($cTitle !== '')
                                <span class="cabinet-sa-cannibal-title text-muted" title="{{ $cTitle }}">{{ \Illuminate\Support\Str::limit($cTitle, 90) }}</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                    @else
                    <td class="small cabinet-sa-details-cell" data-sa-col="details">
                        @if(!empty($isIndexMismatchReport))
                            @php
                                $via = (string) ($rowMeta['page_via'] ?? '');
                                $viaLabels = [
                                    'sitemap' => 'sitemap',
                                    'link' => 'по ссылке',
                                    'seed' => 'посев',
                                    'home' => 'главная',
                                ];
                                $viaLabel = $viaLabels[$via] ?? '';
                                $depth = $rowMeta['page_depth'] ?? null;
                                $section = trim((string) ($rowMeta['path_section'] ?? ''));
                                $hasQuery = ! empty($rowMeta['has_query']);
                                $pageTitle = trim((string) ($rowMeta['page_title'] ?? ''));
                            @endphp
                            <div class="cabinet-sa-index-miss">
                                <div class="cabinet-sa-index-miss__chips">
                                    <span class="cabinet-sa-index-miss__chip cabinet-sa-index-miss__chip--warn" title="Есть в этой проверке, нет в списке «в поиске» Вебмастера">нет в индексе</span>
                                    @if($viaLabel !== '')
                                        <span class="cabinet-sa-index-miss__chip" title="Как URL попал в эту проверку">{{ $viaLabel }}</span>
                                    @endif
                                    @if($depth !== null)
                                        <span class="cabinet-sa-index-miss__chip" title="Глубина кликов от главной">глубина {{ (int) $depth }}</span>
                                    @endif
                                    @if($section !== '')
                                        <span class="cabinet-sa-index-miss__chip cabinet-sa-index-miss__chip--section" title="Первый сегмент пути">/{{ $section }}</span>
                                    @endif
                                    @if($hasQuery)
                                        <span class="cabinet-sa-index-miss__chip cabinet-sa-index-miss__chip--params" title="В URL есть query-параметры">?params</span>
                                    @endif
                                </div>
                                @if($pageTitle !== '')
                                    <div class="cabinet-sa-index-miss__title" title="{{ $pageTitle }}">{{ \Illuminate\Support\Str::limit($pageTitle, 90) }}</div>
                                @endif
                            </div>
                        @else
                            @php
                                $detailsHtml = \App\Services\SiteAudit\SiteAuditFindingPresenter::metaDetailsHtml(
                                    $row->code ?? $code,
                                    $row->meta_json,
                                    $row->url
                                );
                            @endphp
                            @if($detailsHtml !== null)
                                {!! $detailsHtml !!}
                            @else
                                {{ \App\Services\SiteAudit\SiteAuditFindingPresenter::metaLine($row->code ?? $code, $row->meta_json, $row->url) }}
                            @endif
                        @endif
                    </td>
                    @endif
                    @foreach($pageColKeys as $pKey)
                        @php
                            $pVal = $pageCols[$pKey] ?? null;
                            $pEmpty = $pVal === null || $pVal === '';
                        @endphp
                        <td class="small cabinet-sa-page-col is-col-hidden" data-sa-col="{{ $pKey }}">
                            @if($pEmpty)
                                <span class="text-muted">—</span>
                            @elseif(in_array($pKey, ['final_url', 'canonical'], true) && is_string($pVal) && preg_match('#^https?://#i', $pVal))
                                <a href="{{ $pVal }}" target="_blank" rel="noopener noreferrer" class="cabinet-sa-url-break">{{ \Illuminate\Support\Str::limit($pVal, 80) }}</a>
                            @elseif(in_array($pKey, ['title', 'description', 'h1', 'h2', 'h3', 'keywords', 'robots'], true))
                                <span class="cabinet-sa-page-col__text" title="{{ is_scalar($pVal) ? $pVal : '' }}">{{ \Illuminate\Support\Str::limit((string) $pVal, 120) }}</span>
                            @else
                                {{ is_scalar($pVal) ? $pVal : '—' }}
                            @endif
                        </td>
                    @endforeach
                    @if(!empty($showReferrers))
                        <td class="small cabinet-sa-from-cell" data-sa-col="from">
                            @php
                                $originLabel = trim((string) ($rowMeta['origin_label'] ?? ''));
                                $discoveredVia = trim((string) ($rowMeta['discovered_via'] ?? ''));
                                $discoveredFrom = trim((string) ($rowMeta['discovered_from'] ?? ''));
                                if ($discoveredVia === '' && $discoveredFrom === '' && $referrerCount > 0) {
                                    $discoveredVia = 'link';
                                    $discoveredFrom = trim((string) ($referrers[0] ?? ''));
                                }
                                if ($discoveredFrom === '' && !empty($rowMeta['from'])) {
                                    $discoveredVia = $discoveredVia !== '' ? $discoveredVia : 'link';
                                    $discoveredFrom = trim((string) $rowMeta['from']);
                                }
                                $fromUrls = [];
                                if ($discoveredVia === 'link' && $discoveredFrom !== '') {
                                    $fromUrls[] = $discoveredFrom;
                                }
                                foreach ($referrers as $ref) {
                                    $ref = trim((string) $ref);
                                    if ($ref === '' || in_array($ref, $fromUrls, true)) {
                                        continue;
                                    }
                                    $fromUrls[] = $ref;
                                }
                                $fromShow = array_slice($fromUrls, 0, 5);
                                $fromHidden = max(0, count($fromUrls) - count($fromShow));
                                if ($fromHidden === 0 && $referrerCount > count($fromUrls)) {
                                    $fromHidden = $referrerCount - count($fromUrls);
                                }
                            @endphp
                            @if($fromShow !== [])
                                <ul class="cabinet-sa-from-list">
                                    @foreach($fromShow as $ref)
                                        <li>
                                            <a class="cabinet-sa-from-link" href="{{ $ref }}" target="_blank" rel="noopener noreferrer">{{ \App\Services\SiteAudit\SiteAuditFindingPresenter::compactUrlLabel($ref) }}</a>
                                        </li>
                                    @endforeach
                                </ul>
                                @if($fromHidden > 0)
                                    <div class="cabinet-sa-from-more">ещё {{ number_format($fromHidden, 0, '', ' ') }}…</div>
                                @endif
                            @elseif(in_array($discoveredVia, ['sitemap', 'seed', 'home'], true) && $originLabel !== '')
                                @php
                                    $sitemapHref = trim((string) ($rowMeta['sitemap_href'] ?? ''));
                                    $isSitemapOrigin = $discoveredVia === 'sitemap'
                                        || ! empty($rowMeta['from_sitemap'])
                                        || stripos($originLabel, 'sitemap') !== false;
                                @endphp
                                @if($isSitemapOrigin && $sitemapHref !== '')
                                    <a class="cabinet-sa-from-link" href="{{ $sitemapHref }}" target="_blank" rel="noopener noreferrer">{{ $originLabel }}</a>
                                @elseif($discoveredVia === 'home' && !empty($project->domain))
                                    <a class="cabinet-sa-from-link" href="https://{{ $project->domain }}/" target="_blank" rel="noopener noreferrer">{{ $originLabel }}</a>
                                @else
                                    {{ $originLabel }}
                                @endif
                            @elseif($originLabel !== '')
                                {{ $originLabel }}
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                    @endif
                    @if($showActions)
                        <td class="cabinet-sa-actions-cell" data-sa-col="actions">
                            @if(!empty($canNote) && $isFixed)
                                <span class="cabinet-sa-note-flag">исправлено</span>
                            @endif
                            @if(!empty($canNote) && $noteComment !== '')
                                <div class="cabinet-sa-note-text">{{ $noteComment }}</div>
                            @endif
                            <div class="cabinet-sa-actions">
                                @if(!empty($canNote) && !empty($row->id))
                                    <div class="cabinet-sa-act cabinet-sa-act--note">
                                        <label class="cabinet-sa-act__main" for="sa-note-{{ (int) $row->id }}">
                                            <i class="fa fa-comment" aria-hidden="true"></i><span>Заметка</span>
                                        </label>
                                        @include('pages.partials.site-audit-action-help', [
                                            'tip' => "Комментарий к ошибке.\nСохраняется навсегда (тип + URL).\nОткройте → текст → «Сохранить».",
                                        ])
                                    </div>
                                    @if(!$isFixed)
                                        <form method="POST" action="{{ route('pages.site-audit.note', $crawl->id) }}" class="cabinet-sa-act-form">
                                            @csrf
                                            <input type="hidden" name="return_url" value="{{ request()->fullUrl() }}">
                                            <input type="hidden" name="finding_id" value="{{ $row->id }}">
                                            <input type="hidden" name="comment" value="{{ $noteComment }}">
                                            <div class="cabinet-sa-act cabinet-sa-act--fixed">
                                                <button type="submit" name="status" value="fixed" class="cabinet-sa-act__main">
                                                    <i class="fa fa-check" aria-hidden="true"></i><span>Исправлено</span>
                                                </button>
                                                @include('pages.partials.site-audit-action-help', [
                                                    'tip' => "Починили — спрятать из счётчиков.\nВернуть: «Открыть» или «Показать исправленные».\nНе путать с «Игнор».",
                                                ])
                                            </div>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('pages.site-audit.note', $crawl->id) }}" class="cabinet-sa-act-form">
                                            @csrf
                                            <input type="hidden" name="return_url" value="{{ request()->fullUrl() }}">
                                            <input type="hidden" name="finding_id" value="{{ $row->id }}">
                                            <input type="hidden" name="comment" value="{{ $noteComment }}">
                                            <div class="cabinet-sa-act cabinet-sa-act--open">
                                                <button type="submit" name="status" value="open" class="cabinet-sa-act__main">
                                                    <i class="fa fa-undo" aria-hidden="true"></i><span>Открыть</span>
                                                </button>
                                                @include('pages.partials.site-audit-action-help', [
                                                    'tip' => "Снова учитывать в счётчиках (снять «Исправлено»).",
                                                ])
                                            </div>
                                        </form>
                                    @endif
                                @endif
                                @if(!empty($canIgnore) && !empty($row->id))
                                    @if($isIgn)
                                        <form method="POST" action="{{ route('pages.site-audit.ignore.restore', $crawl->id) }}" class="cabinet-sa-act-form">
                                            @csrf
                                            <input type="hidden" name="return_url" value="{{ request()->fullUrl() }}">
                                            <input type="hidden" name="finding_id" value="{{ $row->id }}">
                                            <div class="cabinet-sa-act cabinet-sa-act--restore">
                                                <button type="submit" class="cabinet-sa-act__main">
                                                    <i class="fa fa-undo" aria-hidden="true"></i><span>Вернуть</span>
                                                </button>
                                                @include('pages.partials.site-audit-action-help', [
                                                    'tip' => "Снова считать ошибкой (снять игнор).",
                                                ])
                                            </div>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('pages.site-audit.ignore', $crawl->id) }}" class="cabinet-sa-act-form">
                                            @csrf
                                            <input type="hidden" name="return_url" value="{{ request()->fullUrl() }}">
                                            <input type="hidden" name="finding_id" value="{{ $row->id }}">
                                            <div class="cabinet-sa-act cabinet-sa-act--ignore">
                                                <button type="submit" class="cabinet-sa-act__main">
                                                    <i class="fa fa-ban" aria-hidden="true"></i><span>Игнор</span>
                                                </button>
                                                @include('pages.partials.site-audit-action-help', [
                                                    'tip' => "Не ошибка / ложное срабатывание.\nУбираем из отчёта навсегда, пока не нажмёте «Вернуть».\nИгнор ≠ Исправлено.",
                                                ])
                                            </div>
                                        </form>
                                    @endif
                                @endif
                                @if(!empty($canNote) && !empty($row->id))
                                    <input type="checkbox" class="cabinet-sa-note-toggle" id="sa-note-{{ (int) $row->id }}">
                                    <div class="cabinet-sa-note-panel">
                                        <form method="POST" action="{{ route('pages.site-audit.note', $crawl->id) }}" class="cabinet-sa-note-form">
                                            @csrf
                                            <input type="hidden" name="return_url" value="{{ request()->fullUrl() }}">
                                            <input type="hidden" name="finding_id" value="{{ $row->id }}">
                                            <textarea name="comment" rows="2" class="form-control form-control-sm"
                                                      placeholder="Текст заметки…">{{ $noteComment }}</textarea>
                                            <div class="cabinet-sa-act cabinet-sa-act--save">
                                                <button type="submit" name="status" value="{{ $isFixed ? 'fixed' : 'open' }}"
                                                        class="cabinet-sa-act__main">
                                                    <i class="fa fa-save" aria-hidden="true"></i><span>Сохранить</span>
                                                </button>
                                                @include('pages.partials.site-audit-action-help', [
                                                    'tip' => "Только текст заметки.\n«Исправлено» и «Игнор» — кнопки выше.",
                                                ])
                                            </div>
                                        </form>
                                    </div>
                                @endif
                            </div>
                        </td>
                    @endif
                </tr>
            @empty
                <tr><td colspan="{{ $colspan }}" class="text-secondary px-3 py-3">
                    @if(!empty($probeSkipped))
                        @if(($probeStatus['probe'] ?? '') === 'plagiarism_external')
                            Пока пусто.
                        @else
                            Находок нет — проверка ещё не запускалась. Нажмите «Запустить» выше.
                        @endif
                    @elseif(($probeStatus['probe'] ?? '') === 'plagiarism_external')
                        Замечаний нет — все проверенные страницы выше порога (список с процентами — в блоке «Проверили» выше).
                    @else
                        Находок нет — проверка выполнена, замечаний по этому отчёту нет.
                    @endif
                </td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @endif {{-- /isCrawlPagesReport --}}
    @endif {{-- /isPsiReport --}}
@endif

@include('pages.partials.site-audit-pager', [
    'page' => $page,
    'pages' => $pages,
    'total' => $total ?? null,
])
@endif