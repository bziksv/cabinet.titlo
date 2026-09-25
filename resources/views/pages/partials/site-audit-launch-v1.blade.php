        {{-- Шаг 1: явный выбор режима --}}
        <section class="cabinet-sa-wizard-step cabinet-sa-wizard-step--mode mb-4" id="sa-step-mode" data-sa-wizard-step="mode">
            <div class="cabinet-sa-wizard-head text-center mb-3">
                <p class="cabinet-sa-hero__title mb-1">Как хотите начать?</p>
                <p class="text-secondary mb-0">Выбор запомнится — потом можно сменить</p>
            </div>
            <div class="cabinet-sa-mode-cards">
                <button type="button" class="cabinet-sa-mode-card" data-sa-pick-mode="lite" id="sa-pick-lite">
                    <span class="cabinet-sa-mode-card__badge">Рекомендуем для новичков</span>
                    <span class="cabinet-sa-mode-card__icon" aria-hidden="true"><i class="bi bi-lightning-charge"></i></span>
                    <span class="cabinet-sa-mode-card__title">Простой</span>
                    <span class="cabinet-sa-mode-card__text">Только адрес сайта и кнопка «Запустить». Без лишних настроек.</span>
                    <span class="cabinet-sa-mode-card__cta">Выбрать простой →</span>
                </button>
                <button type="button" class="cabinet-sa-mode-card" data-sa-pick-mode="pro" id="sa-pick-pro">
                    <span class="cabinet-sa-mode-card__badge cabinet-sa-mode-card__badge--pro">Для Профи</span>
                    <span class="cabinet-sa-mode-card__icon" aria-hidden="true"><i class="bi bi-sliders"></i></span>
                    <span class="cabinet-sa-mode-card__title">Расширенный</span>
                    <span class="cabinet-sa-mode-card__text">Потоки, robots, список URL, авторасписание и тонкая настройка проверки.</span>
                    <span class="cabinet-sa-mode-card__cta">Выбрать расширенный →</span>
                </button>
            </div>
        </section>

        {{-- Шаг 2: форма запуска --}}
        <div id="sa-step-workspace" data-sa-wizard-step="workspace" hidden>
            <div class="cabinet-sa-steps mb-3" aria-label="Шаги">
                <button type="button" class="cabinet-sa-steps__item" id="sa-steps-back-mode" title="Сменить режим">
                    <span class="cabinet-sa-steps__num">1</span>
                    <span class="cabinet-sa-steps__label">Режим · <strong id="sa-steps-mode-label">Простой</strong></span>
                    <span class="cabinet-sa-steps__change">сменить</span>
                </button>
                <span class="cabinet-sa-steps__sep" aria-hidden="true"></span>
                <div class="cabinet-sa-steps__item is-current">
                    <span class="cabinet-sa-steps__num">2</span>
                    <span class="cabinet-sa-steps__label">Сайт и запуск</span>
                </div>
            </div>

            <div class="cabinet-sa-lead px-4 py-3 mb-3" data-sa-pro>
                <div class="d-flex gap-3 align-items-start">
                    <span class="cabinet-sa-lead__icon" aria-hidden="true"><i class="bi bi-clipboard2-pulse"></i></span>
                    <div class="flex-grow-1">
                        <p class="mb-1 fw-semibold text-body">Технический аудит сайта</p>
                        <p class="mb-2 small text-secondary">
                            Обходим сайт по sitemap и ссылкам, смотрим robots, собираем ошибки в отчёт.
                            Можно кинуть список URL — тогда только их, без дальнейшего обхода.
                            Несколько доменов — отдельные проекты.
                        </p>
                        <button type="button" class="btn btn-sm btn-outline-primary cabinet-sa-tour-start" id="sa-tour-start">
                            <i class="bi bi-lightbulb me-1" aria-hidden="true"></i>Как пользоваться…
                        </button>
                    </div>
                </div>
            </div>

            <div class="row g-3 cabinet-sa-start-row">
                <div class="col-lg-5 cabinet-sa-start-col">
                    <section class="card border shadow-sm cabinet-sa-panel h-100" data-sa-tour="new-crawl">
                        <div class="card-body">
                            <h2 class="cabinet-sa-step-title h6 mb-3" data-sa-pro>
                                <span class="cabinet-sa-step-badge">1</span>
                                Новая проверка
                            </h2>

                            <div class="mb-3 cabinet-sa-field" data-sa-tour="domains">
                                <label class="form-label fw-medium" for="sa-domain">
                                    <span class="cabinet-sa-label-lite">Сайт</span>
                                    <span class="cabinet-sa-label-pro">Домены</span>
                                    @include('pages.partials.site-audit-tip', ['tip' => "Один или несколько сайтов — каждый домен с новой строки.\nМожно без https://: titlo.ru\nИли целиком URL: https://titlo.ru/ — возьмём только хост.\nДля каждого домена создаётся свой проект и проверка (лимит — по тарифу). Доп. URL и исключения применяются ко всем."])
                                </label>
                                <textarea class="form-control cabinet-sa-domain-input" id="sa-domain" rows="3" placeholder="example.com" data-placeholder-lite="сайт.ru" data-placeholder-pro="example.com&#10;shop.example.com&#10;https://another.ru/" autocomplete="off"></textarea>
                                <div class="form-text cabinet-sa-domain-hint-lite">Можно без https:// — например kawe.su</div>
                            </div>

                        <div class="mb-3 cabinet-sa-field" data-sa-pro>
                            <label class="form-label fw-medium" for="sa-extra-hosts">
                                Доп. хосты в одном project <span class="text-secondary fw-normal">(опционально)</span>
                                @include('pages.partials.site-audit-tip', ['tip' => "Только если выше указан один основной домен.\nПоддомены (shop.example.com, blog.example.com) войдут в тот же проверка как внутренние.\nНесколько доменов в поле «Домены» — по-прежнему отдельные проекты."])
                            </label>
                            <textarea class="form-control" id="sa-extra-hosts" rows="2" placeholder="shop.example.com&#10;blog.example.com" autocomplete="off"></textarea>
                        </div>

                        <div class="mb-3 cabinet-sa-field" data-sa-pro>
                            <label class="form-label fw-medium" for="sa-seeds">
                                Страницы / доп. URL <span class="text-secondary fw-normal">(опционально)</span>
                                @include('pages.partials.site-audit-tip', ['tip' => "По одному URL на строку, лучше с https://.\nБез галочки ниже — это доп. семена: сайт обходится как обычно (sitemap + ссылки), эти URL точно попадут в очередь.\nС галочкой «только эти страницы» — сканируются исключительно перечисленные URL: без sitemap, без главной «насильно» и без дообхода по ссылкам.\nURL с разных доменов автоматически разбиваются на отдельные проекты/проверки.\nМожно не заполнять «Домены», если галочка включена — домен возьмём из URL."])
                            </label>
                            <textarea class="form-control" id="sa-seeds" rows="3" placeholder="https://example.com/page&#10;https://other.ru/about"></textarea>
                            <div class="form-check mt-2 mb-0">
                                <input type="checkbox" class="form-check-input" id="sa-pages-only">
                                <label class="form-check-label" for="sa-pages-only">
                                    Сканировать только эти страницы
                                    <span class="text-secondary">(без sitemap и дообхода; разные сайты → разные проекты)</span>
                                </label>
                            </div>
                        </div>

                        <div class="mb-3 cabinet-sa-field" data-sa-pro>
                            <label class="form-label fw-medium" for="sa-robots">
                                Виртуальный robots.txt <span class="text-secondary fw-normal">(опционально)</span>
                                @include('pages.partials.site-audit-tip', ['tip' => "По умолчанию проверка читает живой /robots.txt сайта и не ходит по Disallow (корень оставляем для диагностики).\nЕсли вставить сюда свой robots.txt — он подменит файл на сайте: теми же правилами режем обход и пишем findings.\nУдобно закрыть /cart, /admin, utm без отдельного списка исключений.\nПример:\nUser-agent: *\nDisallow: /cart\nDisallow: /admin\nAllow: /"])
                            </label>
                            <textarea class="form-control font-monospace" id="sa-robots" rows="5"
                                      placeholder="User-agent: *&#10;Disallow: /cart&#10;Disallow: /admin&#10;Allow: /"></textarea>
                        </div>

                        @include('pages.partials.site-audit-html-checker')

                        <div data-sa-tour="speed" data-sa-pro>
                        <div class="mb-3 cabinet-sa-field">
                            <label class="form-label fw-medium" for="sa-speed">
                                Скорость на поток
                                @include('pages.partials.site-audit-tip', ['tip' => "Лимит стартов запросов в секунду на один поток.\nИтоговая нагрузка ≈ потоки × скорость на поток (но сайт/CDN могут отвечать медленнее).\nМедленнее — мягче к хостингу и антиботу.\nТурбо и высокая скорость на чужих сайтах часто дают 403/429 или временный бан — начинайте с обычной/медленной."])
                            </label>
                            <select class="form-select" id="sa-speed">
                                <option value="slow">Медленно (~1 URL/с на поток)</option>
                                <option value="normal" selected>Обычная (~5 URL/с на поток)</option>
                                <option value="fast">Быстрая (~10 URL/с на поток)</option>
                                <option value="turbo">Турбо (~15 URL/с на поток) — только свои сайты</option>
                            </select>
                        </div>

                        <div class="mb-3 cabinet-sa-field">
                            <label class="form-label fw-medium" for="sa-concurrency">
                                Потоки (параллельные запросы)
                                @include('pages.partials.site-audit-tip', ['tip' => "Сколько HTTP-запросов к сайту одновременно.\nЛимит тарифа: Free 1 / Optimal 2 / Ultimate 4 / Maximum 8.\nНе лупите сразу максимум потоков: хостинги и WAF ограничивают параллельность — получите 429/бан и пустые findings.\nНа тяжёлых своих сайтах можно поднять потоки осторожно, смотря на ответы сервера."])
                            </label>
                            <select class="form-select" id="sa-concurrency"
                                    data-lite-default="{{ min(2, max(1, (int) ($concurrencyLimit ?? config('site_audit.max_concurrency', 8)))) }}">
                                @php
                                    $maxConc = max(1, (int) ($concurrencyLimit ?? config('site_audit.max_concurrency', 8)));
                                    $defaultConc = min(2, $maxConc);
                                @endphp
                                @for($n = 1; $n <= $maxConc; $n++)
                                    <option value="{{ $n }}" @if($n === $defaultConc) selected @endif>
                                        {{ $n }} {{ $n === 1 ? 'поток' : ($n < 5 ? 'потока' : 'потоков') }}
                                    </option>
                                @endfor
                            </select>
                            <div class="form-text">По тарифу доступно до {{ $maxConc }}</div>
                        </div>

                        <div class="mb-3 cabinet-sa-field">
                            <label class="form-label fw-medium" for="sa-limit">
                                Лимит URL
                                @include('pages.partials.site-audit-tip', ['tip' => "Сколько страниц сканировать в этой проверке.\nНе выше лимита тарифа (сейчас {{ number_format((int) ($pagesLimit ?? 100), 0, '', ' ') }}).\nМожно поставить меньше, чтобы быстрее прогнать важные разделы."])
                            </label>
                            <input type="text" class="form-control sa-num-space" id="sa-limit"
                                   inputmode="numeric" autocomplete="off"
                                   value="{{ number_format((int) ($pagesLimit ?? 100), 0, '', ' ') }}"
                                   data-min="1"
                                   data-max="{{ (int) ($pagesLimit ?? 100) }}">
                            <div class="form-text">
                                Макс. по тарифу: {{ number_format((int) ($pagesLimit ?? 100), 0, '', ' ') }}
                                · проектов {{ (int) ($projectsUsed ?? 0) }}/{{ (int) ($projectsLimit ?? 1) }}
                            </div>
                        </div>
                        </div>

                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <button type="button" class="btn btn-primary cabinet-sa-start-btn" id="sa-start">
                                <i class="bi bi-play-fill" aria-hidden="true"></i>
                                <span>Запустить проверку</span>
                            </button>
                            <div id="sa-msg" class="small text-secondary"></div>
                        </div>
                    </div>
                </section>
            </div>

