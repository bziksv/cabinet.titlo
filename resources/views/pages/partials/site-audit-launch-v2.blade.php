        {{-- Режим: Простой / Расширенный --}}
        <div class="cabinet-sa-workspace-bar cabinet-sa-workspace-bar--sticky mb-3" id="sa-mode-bar">
            <div class="cabinet-sa-switcher" role="group" aria-label="Режим интерфейса">
                <button type="button" class="cabinet-sa-switcher__btn" data-sa-switch-mode="lite" id="sa-switch-lite" aria-pressed="true">
                    <i class="bi bi-lightning-charge" aria-hidden="true"></i>
                    <span>Простой</span>
                </button>
                <button type="button" class="cabinet-sa-switcher__btn" data-sa-switch-mode="pro" id="sa-switch-pro" aria-pressed="false">
                    <i class="bi bi-sliders" aria-hidden="true"></i>
                    <span>Расширенный</span>
                </button>
            </div>
            <p class="cabinet-sa-workspace-bar__hint mb-0" id="sa-mode-hint">
                <span class="cabinet-sa-label-lite">Адрес сайта → запуск</span>
                <span class="cabinet-sa-label-pro">Домен обязательно · остальное в секциях ниже</span>
            </p>
        </div>

        {{-- Первый визит: короткий выбор --}}
        <section class="cabinet-sa-wizard-step cabinet-sa-wizard-step--mode mb-4" id="sa-step-mode" data-sa-wizard-step="mode">
            <div class="cabinet-sa-chooser">
                <div class="cabinet-sa-chooser__head">
                    <h2 class="cabinet-sa-chooser__title">Как проверить сайт?</h2>
                    <p class="cabinet-sa-chooser__sub">Потом можно переключить сверху в один клик</p>
                </div>
                <div class="cabinet-sa-mode-cards" role="group" aria-label="Режим аудита">
                    <button type="button" class="cabinet-sa-mode-card cabinet-sa-mode-card--lite" data-sa-pick-mode="lite" id="sa-pick-lite">
                        <span class="cabinet-sa-mode-card__badge">Быстрый старт</span>
                        <span class="cabinet-sa-mode-card__icon" aria-hidden="true"><i class="bi bi-lightning-charge-fill"></i></span>
                        <span class="cabinet-sa-mode-card__title">Простой</span>
                        <span class="cabinet-sa-mode-card__text">Один сайт — одна кнопка. Скорость и лимит сами.</span>
                        <span class="cabinet-sa-mode-card__cta">Выбрать <i class="bi bi-arrow-right" aria-hidden="true"></i></span>
                    </button>
                    <button type="button" class="cabinet-sa-mode-card cabinet-sa-mode-card--pro" data-sa-pick-mode="pro" id="sa-pick-pro">
                        <span class="cabinet-sa-mode-card__badge cabinet-sa-mode-card__badge--pro">Гибко</span>
                        <span class="cabinet-sa-mode-card__icon" aria-hidden="true"><i class="bi bi-sliders"></i></span>
                        <span class="cabinet-sa-mode-card__title">Расширенный</span>
                        <span class="cabinet-sa-mode-card__text">URL-список, пресеты нагрузки, robots, команды.</span>
                        <span class="cabinet-sa-mode-card__cta">Выбрать <i class="bi bi-arrow-right" aria-hidden="true"></i></span>
                    </button>
                </div>
            </div>
        </section>

        <div id="sa-step-workspace" data-sa-wizard-step="workspace" hidden>
            <div class="row g-3 cabinet-sa-start-row">
                <div class="col-lg-5 cabinet-sa-start-col">
                    <section class="cabinet-sa-panel cabinet-sa-launch h-100" data-sa-tour="new-crawl">
                        <div class="cabinet-sa-launch__inner">
                            <div class="cabinet-sa-launch__head">
                                <h2 class="cabinet-sa-launch__title h5 mb-0">
                                    <span class="cabinet-sa-label-lite">Проверка сайта</span>
                                    <span class="cabinet-sa-label-pro">Новая проверка</span>
                                </h2>
                                <button type="button" class="btn btn-link btn-sm px-0 cabinet-sa-tour-start" id="sa-tour-start" data-sa-pro>
                                    Как это работает?
                                </button>
                            </div>

                            <div class="mb-3 cabinet-sa-field" data-sa-tour="domains">
                                <label class="form-label fw-medium" for="sa-domain">
                                    <span class="cabinet-sa-label-lite">Сайт</span>
                                    <span class="cabinet-sa-label-pro">Домен(ы)</span>
                                    @include('pages.partials.site-audit-tip', ['tip' => "Один или несколько сайтов — каждый домен с новой строки.\nМожно без https://: titlo.ru\nИли целиком URL: https://titlo.ru/ — возьмём только хост.\nДля каждого домена создаётся свой проект и проверка (лимит — по тарифу). Доп. URL и исключения применяются ко всем."])
                                </label>
                                <textarea class="form-control cabinet-sa-domain-input" id="sa-domain" rows="3" placeholder="example.com" data-placeholder-lite="сайт.ru" data-placeholder-pro="example.com&#10;shop.example.com&#10;https://another.ru/" autocomplete="off"></textarea>
                                <div class="form-text cabinet-sa-domain-hint-lite">Можно без https://</div>
                            </div>

                            <details class="cabinet-sa-section" data-sa-pro data-sa-tour="scope">
                                <summary class="cabinet-sa-section__summary">
                                    <span class="cabinet-sa-section__icon" aria-hidden="true"><i class="bi bi-diagram-3"></i></span>
                                    <span class="cabinet-sa-section__meta">
                                        <span class="cabinet-sa-section__title">Поддомены и список URL</span>
                                        <span class="cabinet-sa-section__desc">необязательно</span>
                                    </span>
                                    <i class="bi bi-chevron-down cabinet-sa-section__chev" aria-hidden="true"></i>
                                </summary>
                                <div class="cabinet-sa-section__body">
                                    <div class="mb-3 cabinet-sa-field">
                                        <label class="form-label fw-medium" for="sa-extra-hosts">
                                            Поддомены в том же проекте
                                            @include('pages.partials.site-audit-tip', ['tip' => "Только если выше указан один основной домен.\nПоддомены (shop.example.com, blog.example.com) войдут в тот же проверка как внутренние.\nНесколько доменов в поле «Домены» — по-прежнему отдельные проекты."])
                                        </label>
                                        <textarea class="form-control" id="sa-extra-hosts" rows="2" placeholder="shop.example.com&#10;blog.example.com" autocomplete="off"></textarea>
                                    </div>
                                    <div class="mb-0 cabinet-sa-field">
                                        <label class="form-label fw-medium" for="sa-seeds">
                                            Список URL
                                            @include('pages.partials.site-audit-tip', ['tip' => "По одному URL на строку, лучше с https://.\nБез галочки ниже — это доп. семена: сайт обходится как обычно (sitemap + ссылки), эти URL точно попадут в очередь.\nС галочкой «только эти страницы» — сканируются исключительно перечисленные URL: без sitemap, без главной «насильно» и без дообхода по ссылкам.\nURL с разных доменов автоматически разбиваются на отдельные проекты/проверки.\nМожно не заполнять «Домены», если галочка включена — домен возьмём из URL."])
                                        </label>
                                        <textarea class="form-control" id="sa-seeds" rows="3" placeholder="https://example.com/page&#10;https://other.ru/about"></textarea>
                                        <div class="form-check mt-2 mb-0">
                                            <input type="checkbox" class="form-check-input" id="sa-pages-only">
                                            <label class="form-check-label" for="sa-pages-only">
                                                Только эти URL <span class="text-secondary">(без sitemap и дообхода)</span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </details>

                            <details class="cabinet-sa-section" data-sa-pro data-sa-tour="speed" open>
                                <summary class="cabinet-sa-section__summary">
                                    <span class="cabinet-sa-section__icon" aria-hidden="true"><i class="bi bi-speedometer2"></i></span>
                                    <span class="cabinet-sa-section__meta">
                                        <span class="cabinet-sa-section__title">Скорость и объём</span>
                                        <span class="cabinet-sa-section__desc">пресет или вручную</span>
                                    </span>
                                    <i class="bi bi-chevron-down cabinet-sa-section__chev" aria-hidden="true"></i>
                                </summary>
                                <div class="cabinet-sa-section__body">
                                    @php
                                        $maxConc = max(1, (int) ($concurrencyLimit ?? config('site_audit.max_concurrency', 8)));
                                        $defaultConc = min(2, $maxConc);
                                        $gentleConc = 1;
                                        $turboConc = $maxConc;
                                    @endphp
                                    <div class="cabinet-sa-presets mb-3" role="group" aria-label="Пресет нагрузки">
                                        <button type="button" class="cabinet-sa-preset" data-sa-preset="gentle"
                                                data-speed="slow" data-concurrency="{{ $gentleConc }}">
                                            <span class="cabinet-sa-preset__name">Бережно</span>
                                            <span class="cabinet-sa-preset__meta">1/с · {{ $gentleConc }} пот.</span>
                                        </button>
                                        <button type="button" class="cabinet-sa-preset is-active" data-sa-preset="normal"
                                                data-speed="normal" data-concurrency="{{ $defaultConc }}">
                                            <span class="cabinet-sa-preset__name">Обычный</span>
                                            <span class="cabinet-sa-preset__meta">5/с · {{ $defaultConc }} пот.</span>
                                        </button>
                                        <button type="button" class="cabinet-sa-preset" data-sa-preset="turbo"
                                                data-speed="turbo" data-concurrency="{{ $turboConc }}">
                                            <span class="cabinet-sa-preset__name">Турбо</span>
                                            <span class="cabinet-sa-preset__meta">15/с · {{ $turboConc }} · свои</span>
                                        </button>
                                    </div>

                                    <div class="row g-2">
                                        <div class="col-md-6 cabinet-sa-field">
                                            <label class="form-label fw-medium" for="sa-speed">
                                                Скорость
                                                @include('pages.partials.site-audit-tip', ['tip' => "Лимит стартов запросов в секунду на один поток.\nИтоговая нагрузка ≈ потоки × скорость на поток (но сайт/CDN могут отвечать медленнее).\nМедленнее — мягче к хостингу и антиботу.\nТурбо и высокая скорость на чужих сайтах часто дают 403/429 или временный бан — начинайте с обычной/медленной."])
                                            </label>
                                            <select class="form-select" id="sa-speed">
                                                <option value="slow">Медленно (~1/с)</option>
                                                <option value="normal" selected>Обычная (~5/с)</option>
                                                <option value="fast">Быстрая (~10/с)</option>
                                                <option value="turbo">Турбо (~15/с)</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6 cabinet-sa-field">
                                            <label class="form-label fw-medium" for="sa-concurrency">
                                                Потоки
                                                @include('pages.partials.site-audit-tip', ['tip' => "Сколько HTTP-запросов к сайту одновременно.\nЛимит тарифа: Free 1 / Optimal 2 / Ultimate 4 / Maximum 8.\nНе лупите сразу максимум потоков: хостинги и WAF ограничивают параллельность — получите 429/бан и пустые findings.\nНа тяжёлых своих сайтах можно поднять потоки осторожно, смотря на ответы сервера."])
                                            </label>
                                            <select class="form-select" id="sa-concurrency"
                                                    data-lite-default="{{ min(2, max(1, (int) ($concurrencyLimit ?? config('site_audit.max_concurrency', 8)))) }}">
                                                @for($n = 1; $n <= $maxConc; $n++)
                                                    <option value="{{ $n }}" @if($n === $defaultConc) selected @endif>
                                                        {{ $n }}
                                                    </option>
                                                @endfor
                                            </select>
                                            <div class="form-text">макс. {{ $maxConc }}</div>
                                        </div>
                                        <div class="col-12 cabinet-sa-field mb-0">
                                            <label class="form-label fw-medium" for="sa-limit">
                                                Лимит страниц
                                                @include('pages.partials.site-audit-tip', ['tip' => "Сколько страниц сканировать в этой проверке.\nНе выше лимита тарифа (сейчас {{ number_format((int) ($pagesLimit ?? 100), 0, '', ' ') }}).\nМожно поставить меньше, чтобы быстрее прогнать важные разделы."])
                                            </label>
                                            <input type="text" class="form-control sa-num-space" id="sa-limit"
                                                   inputmode="numeric" autocomplete="off"
                                                   value="{{ number_format((int) ($pagesLimit ?? 100), 0, '', ' ') }}"
                                                   data-min="1"
                                                   data-max="{{ (int) ($pagesLimit ?? 100) }}">
                                            <div class="form-text">
                                                до {{ number_format((int) ($pagesLimit ?? 100), 0, '', ' ') }}
                                                · проектов {{ (int) ($projectsUsed ?? 0) }}/{{ (int) ($projectsLimit ?? 1) }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </details>

                            <details class="cabinet-sa-section" data-sa-pro data-sa-tour="robots">
                                <summary class="cabinet-sa-section__summary">
                                    <span class="cabinet-sa-section__icon" aria-hidden="true"><i class="bi bi-file-earmark-lock"></i></span>
                                    <span class="cabinet-sa-section__meta">
                                        <span class="cabinet-sa-section__title">Виртуальный robots.txt</span>
                                        <span class="cabinet-sa-section__desc">необязательно</span>
                                    </span>
                                    <i class="bi bi-chevron-down cabinet-sa-section__chev" aria-hidden="true"></i>
                                </summary>
                                <div class="cabinet-sa-section__body">
                                    <div class="mb-0 cabinet-sa-field">
                                        <label class="form-label fw-medium visually-hidden" for="sa-robots">robots.txt</label>
                                        <textarea class="form-control font-monospace" id="sa-robots" rows="4"
                                                  placeholder="User-agent: *&#10;Disallow: /cart&#10;Disallow: /admin&#10;Allow: /"></textarea>
                                        <div class="form-text">Пусто = robots с сайта</div>
                                    </div>
                                </div>
                            </details>

                            @include('pages.partials.site-audit-html-checker')

                            <div class="cabinet-sa-launch__actions">
                                <button type="button" class="btn btn-primary cabinet-sa-start-btn" id="sa-start">
                                    <i class="bi bi-play-fill" aria-hidden="true"></i>
                                    <span>Запустить проверку</span>
                                </button>
                                <div id="sa-msg" class="small text-secondary"></div>
                            </div>
                        </div>
                    </section>
                </div>
