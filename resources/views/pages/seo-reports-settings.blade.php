@component('component.card', [
    'title' => __('Settings') . ' · ' . $project->domain,
    'titleHtml' => e(__('Settings') . ' · ' . $project->domain) . view('partials.cabinet-module-version-badge', ['configKey' => 'cabinet-seo-reports'])->render(),
    'documentTitle' => __('Settings') . ' · ' . $project->domain,
])
    @slot('css')
        <link rel="stylesheet" href="{{ asset('plugins/select2/css/select2.min.css') }}">
        <link rel="stylesheet" href="{{ asset('plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css') }}">
        <link rel="stylesheet" href="{{ asset('css/cabinet-seo-reports.css') }}?v={{ @filemtime(public_path('css/cabinet-seo-reports.css')) ?: time() }}">
    @endslot

    @php
        $hasTemplate = (int) ($project->template_id ?? 0) > 0;
        $hasMetrika = (int) ($project->metrika_counter_id ?? 0) > 0;
        $hasMonitoring = (int) ($project->monitoring_project_id ?? 0) > 0;
        $hasWm = trim((string) ($settings['webmaster_host'] ?? '')) !== '';
        $hasEmail = !empty($settings['auto_email']) || trim((string) ($settings['auto_email_to'] ?? '')) !== '';

        $stepKinds = [
            1 => trim((string) ($project->title ?? '')) !== '' ? 'done' : 'needed',
            2 => $hasTemplate ? 'done' : 'needed',
            3 => ($hasMetrika || $hasMonitoring) ? 'done' : 'optional',
            // GSC пока в разработке — статус шага только по Я.Вебмастеру
            4 => $hasWm ? 'done' : 'optional',
            // Реклама / SMM / звонки — пока в разработке
            5 => 'dev',
            6 => $hasEmail ? 'done' : 'optional',
        ];
        $stepLabels = [
            'done' => __('Step done'),
            'needed' => __('Step needed'),
            'optional' => __('Step optional'),
            'dev' => __('In development'),
        ];
        $openStep = 1;
        $forcedStep = (int) request()->query('step', 0);
        if ($forcedStep >= 1 && $forcedStep <= 6) {
            $openStep = $forcedStep;
        } else {
            foreach ([1, 2, 3, 4, 5, 6] as $n) {
                if (($stepKinds[$n] ?? '') === 'needed') {
                    $openStep = $n;
                    break;
                }
            }
        }
        $devModules = [
            [
                'title' => 'Google Search Console',
                'hint' => __('GSC property in development hint'),
                'step' => 4,
            ],
            [
                'title' => 'Яндекс.Директ',
                'hint' => __('Yandex Direct in development hint'),
            ],
            [
                'title' => 'Google Ads',
                'hint' => __('Google Ads in development hint'),
            ],
            [
                'title' => 'VK Реклама',
                'hint' => __('VK Ads in development hint'),
            ],
            [
                'title' => 'VK / SMM',
                'hint' => __('VK SMM in development hint'),
            ],
            [
                'title' => 'Звонки',
                'hint' => __('Calls in development hint'),
            ],
        ];
    @endphp

    <div class="cabinet-sr-page">
        @include('pages.partials.seo-reports-nav', [
            'srTab' => 'settings',
            'srContextProject' => $project,
            'srCanEditSettings' => true,
        ])

        @if(session('success'))
            <div class="alert alert-success py-2 px-3 small">{{ session('success') }}</div>
        @endif

        <div class="cabinet-sr-hero">
            <div>
                <h1 class="cabinet-sr-hero__title">{{ __('Project settings') }}</h1>
                <p class="cabinet-sr-hero__lead">{{ __('SEO report project settings steps lead') }}</p>
            </div>
        </div>

        <form method="post"
              id="sr-settings-form"
              class="cabinet-sr-steps"
              action="{{ route('pages.seo-reports.settings.update', ['id' => $project->id]) }}"
              data-sr-settings-steps>
            @csrf

            <details class="cabinet-sr-step" data-sr-step="1" @if($openStep === 1) open @endif>
                <summary class="cabinet-sr-step__summary">
                    <span class="cabinet-sr-step__num">1</span>
                    <span class="cabinet-sr-step__text">
                        <span class="cabinet-sr-step__title">{{ __('Project') }}</span>
                        <span class="cabinet-sr-step__hint">{{ __('Settings step project hint') }}</span>
                    </span>
                    <span class="cabinet-sr-step__status is-{{ $stepKinds[1] }}">{{ $stepLabels[$stepKinds[1]] }}</span>
                </summary>
                <div class="cabinet-sr-step__body">
                    <div class="mb-2">
                        <label class="form-label" for="srTitle">{{ __('Title') }}</label>
                        <input type="text" class="form-control form-control-sm" id="srTitle" name="title"
                               value="{{ old('title', $project->title) }}">
                        <div class="form-text">{{ __('Project title hint') }}</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('Mirror domains') }}</label>
                        <input type="text" class="form-control form-control-sm" name="mirror_domains"
                               value="{{ old('mirror_domains', implode(', ', $settings['mirror_domains'] ?? [])) }}"
                               placeholder="www.example.com, m.example.com">
                        <div class="form-text">{{ __('Mirror domains hint') }}</div>
                    </div>
                    <button type="button" class="btn btn-outline-primary btn-sm" data-sr-step-next="2">{{ __('Next step') }}</button>
                </div>
            </details>

            <details class="cabinet-sr-step" data-sr-step="2" @if($openStep === 2) open @endif>
                <summary class="cabinet-sr-step__summary">
                    <span class="cabinet-sr-step__num">2</span>
                    <span class="cabinet-sr-step__text">
                        <span class="cabinet-sr-step__title">{{ __('Report template') }}</span>
                        <span class="cabinet-sr-step__hint">{{ __('Attach report template hint') }}</span>
                    </span>
                    <span class="cabinet-sr-step__status is-{{ $stepKinds[2] }}">{{ $stepLabels[$stepKinds[2]] }}</span>
                </summary>
                <div class="cabinet-sr-step__body">
                    <div class="mb-2">
                        <label class="form-label" for="srTemplateId">{{ __('Template') }}</label>
                        <select class="form-select form-select-sm" id="srTemplateId" name="template_id" required>
                            @foreach($templates as $tpl)
                                <option value="{{ $tpl->id }}"
                                    @if((int) $project->template_id === (int) $tpl->id) selected @endif>
                                    {{ $tpl->title }}
                                    @if($tpl->is_default) · {{ __('Default') }} @endif
                                </option>
                            @endforeach
                        </select>
                    </div>
                    @if($attachedTemplate)
                        <p class="small mb-2">
                            <a href="{{ route('pages.seo-reports.templates.edit', ['id' => $attachedTemplate->id]) }}">
                                {{ __('Edit template') }}: {{ $attachedTemplate->title }}
                            </a>
                        </p>
                        <p class="form-text mb-3">{{ __('Template change affects all linked projects') }}</p>
                    @endif
                    <button type="button" class="btn btn-outline-primary btn-sm" data-sr-step-next="3">{{ __('Next step') }}</button>
                </div>
            </details>

            <details class="cabinet-sr-step" data-sr-step="3" @if($openStep === 3) open @endif>
                <summary class="cabinet-sr-step__summary">
                    <span class="cabinet-sr-step__num">3</span>
                    <span class="cabinet-sr-step__text">
                        <span class="cabinet-sr-step__title">{{ __('Integrations') }}</span>
                        <span class="cabinet-sr-step__hint">{{ __('Settings step integrations hint') }}</span>
                    </span>
                    <span class="cabinet-sr-step__status is-{{ $stepKinds[3] }}">{{ $stepLabels[$stepKinds[3]] }}</span>
                </summary>
                <div class="cabinet-sr-step__body">
                    <div class="mb-2"
                         data-sr-metrika
                         data-domain="{{ $project->domain }}"
                         data-metrika-configured="{{ !empty($metrikaConfigured) ? '1' : '0' }}"
                         data-metrika-connected="{{ !empty($metrikaConnected) ? '1' : '0' }}"
                         data-metrika-connect-url="{{ route('yandex-metrika.connect') }}"
                         data-metrika-binding-url="{{ route('yandex-metrika.binding') }}"
                         data-metrika-counters-url="{{ route('yandex-metrika.counters') }}"
                         data-metrika-bind-url="{{ route('yandex-metrika.bind') }}"
                         data-metrika-unbind-url="{{ route('yandex-metrika.unbind') }}"
                         data-metrika-return="{{ route('pages.seo-reports.settings', ['id' => $project->id, 'step' => 3]) }}">
                        <label class="form-label">{{ __('Yandex Metrika') }}</label>
                        @php
                            $selectedMetrika = (int) old('metrika_counter_id', $project->metrika_counter_id ?? 0);
                            $metrikaList = $metrikaBindings ?? collect();
                            $selectedMetrikaInList = $metrikaList->contains(static function ($b) use ($selectedMetrika) {
                                return (int) $b->counter_id === $selectedMetrika;
                            });
                        @endphp
                        <div class="d-flex flex-wrap gap-2 align-items-start mb-1">
                            <div class="flex-grow-1" style="min-width: 12rem;">
                                <select class="form-select form-select-sm" name="metrika_counter_id" data-sr-select2 data-sr-metrika-select>
                                    <option value="">{{ __('Not connected') }}</option>
                                    @if($selectedMetrika > 0 && !$selectedMetrikaInList)
                                        <option value="{{ $selectedMetrika }}" selected>#{{ $selectedMetrika }}</option>
                                    @endif
                                    @foreach($metrikaList as $binding)
                                        <option value="{{ $binding->counter_id }}"
                                            @if($selectedMetrika === (int) $binding->counter_id) selected @endif>
                                            @if($binding->counter_name){{ $binding->counter_name }} · @endif{{ $binding->domain }}
                                            · #{{ $binding->counter_id }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <button type="button" class="btn btn-outline-primary btn-sm" data-sr-metrika-open>
                                {{ __('Connect or change Metrika') }}
                            </button>
                        </div>
                        <div class="form-text">{{ __('Metrika connect from settings hint') }}</div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">{{ __('Position monitoring') }}</label>
                        <select class="form-select form-select-sm" name="monitoring_project_id" data-sr-select2>
                            <option value="">{{ __('Not connected') }}</option>
                            @foreach($monitoringOptions as $option)
                                <option value="{{ $option['id'] }}"
                                    @if((int) $project->monitoring_project_id === (int) $option['id']) selected @endif>
                                    {{ $option['label'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('Metrika goals') }}</label>
                        @if(empty($metrikaGoals))
                            <div class="form-text">{{ __('Connect Metrika counter to load goals') }}</div>
                        @else
                            <div class="cabinet-sr-goals">
                                @foreach($metrikaGoals as $goal)
                                    <label class="cabinet-sr-toggle-row">
                                        <input type="checkbox"
                                               name="metrika_goal_ids[]"
                                               value="{{ $goal['id'] }}"
                                            @if(in_array((int) $goal['id'], $selectedGoalIds, true)) checked @endif>
                                        <span>{{ $goal['name'] }} <span class="text-secondary">#{{ $goal['id'] }}</span></span>
                                    </label>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    <button type="button" class="btn btn-outline-primary btn-sm" data-sr-step-next="4">{{ __('Next step') }}</button>
                </div>
            </details>

            <details class="cabinet-sr-step" data-sr-step="4" @if($openStep === 4) open @endif>
                <summary class="cabinet-sr-step__summary">
                    <span class="cabinet-sr-step__num">4</span>
                    <span class="cabinet-sr-step__text">
                        <span class="cabinet-sr-step__title">{{ __('Search consoles') }}</span>
                        <span class="cabinet-sr-step__hint">{{ __('Settings step consoles hint') }}</span>
                    </span>
                    <span class="cabinet-sr-step__status is-{{ $stepKinds[4] }}">{{ $stepLabels[$stepKinds[4]] }}</span>
                </summary>
                <div class="cabinet-sr-step__body">
                    <div class="mb-2">
                        <label class="form-label">
                            Google Search Console property
                            <span class="badge bg-secondary ms-1">{{ __('In development') }}</span>
                        </label>
                        <input type="text" class="form-control form-control-sm" disabled
                               value="{{ $settings['gsc_property'] ?? '' }}"
                               placeholder="sc-domain:example.com"
                               aria-disabled="true">
                        <div class="form-text">{{ __('GSC property in development hint') }}</div>
                    </div>
                    <div class="mb-3"
                         data-sr-webmaster
                         data-domain="{{ $project->domain }}"
                         data-webmaster-configured="{{ !empty($webmasterConfigured) ? '1' : '0' }}"
                         data-webmaster-connected="{{ !empty($webmasterConnected) ? '1' : '0' }}"
                         data-webmaster-connect-url="{{ route('yandex-webmaster.connect') }}"
                         data-webmaster-binding-url="{{ route('yandex-webmaster.binding') }}"
                         data-webmaster-hosts-url="{{ route('yandex-webmaster.hosts') }}"
                         data-webmaster-bind-url="{{ route('yandex-webmaster.bind') }}"
                         data-webmaster-unbind-url="{{ route('yandex-webmaster.unbind') }}"
                         data-webmaster-return="{{ route('pages.seo-reports.settings', ['id' => $project->id]) }}">
                        <label class="form-label">{{ __('Yandex Webmaster host') }}</label>
                        @php
                            $selectedWm = old('webmaster_host', $settings['webmaster_host'] ?? '');
                            $wmBindings = $webmasterBindings ?? collect();
                            $selectedInList = $wmBindings->contains(static function ($b) use ($selectedWm) {
                                return (string) $b->host_id === (string) $selectedWm;
                            });
                        @endphp
                        <div class="d-flex flex-wrap gap-2 align-items-start mb-1">
                            <div class="flex-grow-1" style="min-width: 12rem;">
                                <select class="form-select form-select-sm" name="webmaster_host" data-sr-select2 data-sr-webmaster-select>
                                    <option value="">{{ __('Not connected') }}</option>
                                    @if($selectedWm !== '' && !$selectedInList)
                                        <option value="{{ $selectedWm }}" selected>{{ $selectedWm }}</option>
                                    @endif
                                    @foreach($wmBindings as $binding)
                                        <option value="{{ $binding->host_id }}"
                                            @if((string) $selectedWm === (string) $binding->host_id) selected @endif>
                                            {{ $binding->domain }}
                                            @if($binding->host_url) · {{ $binding->host_url }}@endif
                                            · {{ $binding->host_id }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <button type="button" class="btn btn-outline-primary btn-sm" data-sr-webmaster-open>
                                {{ __('Connect or change Webmaster') }}
                            </button>
                        </div>
                        <div class="form-text">{{ __('Webmaster host connect from settings hint') }}</div>
                    </div>
                    <button type="button" class="btn btn-outline-primary btn-sm" data-sr-step-next="5">{{ __('Next step') }}</button>
                </div>
            </details>

            <details class="cabinet-sr-step" data-sr-step="5" @if($openStep === 5) open @endif>
                <summary class="cabinet-sr-step__summary">
                    <span class="cabinet-sr-step__num">5</span>
                    <span class="cabinet-sr-step__text">
                        <span class="cabinet-sr-step__title">
                            {{ __('Ads, SMM and calls') }}
                            <span class="badge bg-secondary ms-1">{{ __('In development') }}</span>
                        </span>
                        <span class="cabinet-sr-step__hint">{{ __('Settings step ads sources hint') }}</span>
                    </span>
                    <span class="cabinet-sr-step__status is-{{ $stepKinds[5] }}">{{ $stepLabels[$stepKinds[5]] }}</span>
                </summary>
                <div class="cabinet-sr-step__body">
                    <p class="form-text mb-3">{{ __('Settings step ads sources body') }}</p>
                    <ul class="cabinet-sr-dev-modules mb-3">
                        @foreach($devModules as $mod)
                            <li class="cabinet-sr-dev-modules__item">
                                <div class="cabinet-sr-dev-modules__main">
                                    <strong class="cabinet-sr-dev-modules__title">{{ $mod['title'] }}</strong>
                                    <span class="cabinet-sr-dev-modules__badge">{{ __('In development') }}</span>
                                    <span class="cabinet-sr-dev-modules__hint">{{ $mod['hint'] }}</span>
                                </div>
                                @if(!empty($mod['step']))
                                    <button type="button"
                                            class="btn btn-link btn-sm p-0"
                                            data-sr-step-next="{{ $mod['step'] }}">
                                        {{ __('Open step') }} {{ $mod['step'] }}
                                    </button>
                                @else
                                    <span class="cabinet-sr-dev-modules__soon">{{ __('Soon') }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                    <button type="button" class="btn btn-outline-primary btn-sm" data-sr-step-next="6">{{ __('Next step') }}</button>
                </div>
            </details>

            <details class="cabinet-sr-step" data-sr-step="6" @if($openStep === 6) open @endif>
                <summary class="cabinet-sr-step__summary">
                    <span class="cabinet-sr-step__num">6</span>
                    <span class="cabinet-sr-step__text">
                        <span class="cabinet-sr-step__title">{{ __('Client email') }}</span>
                        <span class="cabinet-sr-step__hint">{{ __('Settings step email hint') }}</span>
                    </span>
                    <span class="cabinet-sr-step__status is-{{ $stepKinds[6] }}">{{ $stepLabels[$stepKinds[6]] }}</span>
                </summary>
                <div class="cabinet-sr-step__body">
                    <label class="cabinet-sr-toggle-row">
                        <input type="checkbox" name="auto_email" value="1"
                            @if(!empty($settings['auto_email'])) checked @endif>
                        <span>{{ __('Email report after auto-generate') }}</span>
                    </label>
                    <div class="mb-2">
                        <label class="form-label">{{ __('Email recipients') }}</label>
                        <input type="text" class="form-control form-control-sm" name="auto_email_to"
                               value="{{ old('auto_email_to', $settings['auto_email_to'] ?? '') }}"
                               placeholder="client@firma.ru, seo@firma.ru">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">{{ __('Email message') }}</label>
                        <textarea class="form-control form-control-sm" name="auto_email_message" rows="2">{{ old('auto_email_message', $settings['auto_email_message'] ?? '') }}</textarea>
                    </div>
                    <label class="cabinet-sr-toggle-row mb-0">
                        <input type="checkbox" name="auto_email_cc_manager" value="1"
                            @if(!empty($settings['auto_email_cc_manager'])) checked @endif>
                        <span>{{ __('CC manager') }}</span>
                    </label>
                </div>
            </details>

            <div class="cabinet-sr-steps__actions">
                <button type="submit" class="btn btn-primary btn-sm">{{ __('Save') }}</button>
                <a class="btn btn-outline-secondary btn-sm"
                   href="{{ route('pages.seo-reports.show', ['id' => $project->id]) }}">{{ __('Cancel') }}</a>
            </div>
        </form>
    </div>

    @include('pages.partials.seo-reports-metrika-modal')

    <div class="modal fade" id="cabinet-sr-webmaster-modal" tabindex="-1" aria-labelledby="cabinet-sr-webmaster-modal-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="cabinet-sr-webmaster-modal-title">{{ __('Yandex Webmaster') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-secondary mb-2">
                        {{ __('Choose Webmaster host for domain') }}:
                        <strong data-webmaster-domain-label>—</strong>
                    </p>
                    <div data-webmaster-current class="alert alert-light border py-2 px-3 small d-none mb-3"></div>
                    <div data-webmaster-loading class="text-secondary small py-3 d-none">{{ __('Loading Webmaster hosts') }}…</div>
                    <div data-webmaster-error class="alert alert-danger py-2 px-3 small d-none"></div>
                    <div data-webmaster-auth class="text-center py-3 d-none">
                        <p class="mb-3">{{ __('Connect Yandex Webmaster to pick a host') }}</p>
                        <a href="#" class="btn btn-primary" data-webmaster-auth-link>
                            <i class="bi bi-box-arrow-in-right me-1" aria-hidden="true"></i>
                            {{ __('Authorize Yandex Webmaster') }}
                        </a>
                    </div>
                    <div data-webmaster-search-wrap class="mb-2 d-none">
                        <input type="search"
                               class="form-control form-control-sm"
                               data-webmaster-search
                               placeholder="{{ __('Search by site or host ID') }}"
                               autocomplete="off">
                    </div>
                    <div class="list-group list-group-flush border rounded" data-webmaster-list style="max-height: 22rem; overflow: auto;"></div>
                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-outline-danger btn-sm d-none" data-webmaster-unbind>
                        {{ __('Unbind Webmaster host') }}
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Close') }}</button>
                </div>
            </div>
        </div>
    </div>

    @slot('js')
        <script src="{{ asset('plugins/select2/js/select2.full.min.js') }}"></script>
        <script>
            (function () {
                if (typeof window.jQuery !== 'undefined' && typeof window.jQuery.fn.select2 === 'function') {
                    window.jQuery('[data-sr-select2]').each(function () {
                        var $el = window.jQuery(this);
                        var realOptions = $el.find('option').filter(function () {
                            return String(this.value || '') !== '';
                        }).length;
                        if (realOptions < 10) return;
                        $el.select2({
                            theme: 'bootstrap4',
                            width: '100%',
                            allowClear: true,
                            placeholder: $el.find('option[value=""]').first().text() || ''
                        });
                    });
                }

                var root = document.querySelector('[data-sr-settings-steps]');
                if (!root) return;
                var steps = Array.prototype.slice.call(root.querySelectorAll('[data-sr-step]'));

                function openStep(n) {
                    steps.forEach(function (el) {
                        var id = el.getAttribute('data-sr-step');
                        el.open = String(id) === String(n);
                    });
                    var target = root.querySelector('[data-sr-step="' + n + '"]');
                    if (target && typeof target.scrollIntoView === 'function') {
                        target.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                    }
                }

                steps.forEach(function (el) {
                    el.addEventListener('toggle', function () {
                        if (!el.open) return;
                        steps.forEach(function (other) {
                            if (other !== el) other.open = false;
                        });
                    });
                });

                root.addEventListener('click', function (e) {
                    var btn = e.target.closest('[data-sr-step-next]');
                    if (!btn) return;
                    e.preventDefault();
                    openStep(btn.getAttribute('data-sr-step-next'));
                });

                (function initMetrikaPicker() {
                    var box = document.querySelector('[data-sr-metrika]');
                    var modalEl = document.getElementById('cabinet-sr-metrika-modal');
                    if (!box || !modalEl) return;

                    var csrfEl = document.querySelector('meta[name="csrf-token"]');
                    var csrfToken = csrfEl ? csrfEl.getAttribute('content') : '';
                    var currentDomain = box.getAttribute('data-domain') || '';
                    var allCounters = [];
                    var selectedCounterId = 0;
                    var listEl = modalEl.querySelector('[data-metrika-list]');
                    var loadingEl = modalEl.querySelector('[data-metrika-loading]');
                    var errorEl = modalEl.querySelector('[data-metrika-error]');
                    var authEl = modalEl.querySelector('[data-metrika-auth]');
                    var authLink = modalEl.querySelector('[data-metrika-auth-link]');
                    var domainLabel = modalEl.querySelector('[data-metrika-domain-label]');
                    var currentEl = modalEl.querySelector('[data-metrika-current]');
                    var unbindBtn = modalEl.querySelector('[data-metrika-unbind]');
                    var searchWrap = modalEl.querySelector('[data-metrika-search-wrap]');
                    var searchInput = modalEl.querySelector('[data-metrika-search]');

                    function showModal() {
                        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                            bootstrap.Modal.getOrCreateInstance(modalEl).show();
                            return;
                        }
                        if (typeof $ !== 'undefined' && $.fn.modal) {
                            $(modalEl).modal('show');
                        }
                    }

                    function connectUrl(domain) {
                        var base = box.getAttribute('data-metrika-connect-url') || '';
                        var ret = box.getAttribute('data-metrika-return') || location.href;
                        return base + (base.indexOf('?') === -1 ? '?' : '&') +
                            'domain=' + encodeURIComponent(domain || '') +
                            '&return=' + encodeURIComponent(ret);
                    }

                    function setError(msg) {
                        if (!errorEl) return;
                        errorEl.textContent = msg || '';
                        errorEl.classList.toggle('d-none', !msg);
                    }

                    function setLoading(on) {
                        if (loadingEl) loadingEl.classList.toggle('d-none', !on);
                    }

                    function setSearchVisible(on) {
                        if (searchWrap) searchWrap.classList.toggle('d-none', !on);
                        if (!on && searchInput) searchInput.value = '';
                    }

                    function filterCounters(counters, query) {
                        var q = String(query || '').trim().toLowerCase();
                        if (!q) return counters.slice();
                        return counters.filter(function (c) {
                            var name = String(c.name || '').toLowerCase();
                            var site = String(c.site || '').toLowerCase();
                            var id = String(c.id || '');
                            return name.indexOf(q) !== -1 || site.indexOf(q) !== -1 || id.indexOf(q) !== -1;
                        });
                    }

                    function renderCounters(counters, selectedId) {
                        if (!listEl) return;
                        listEl.innerHTML = '';
                        if (!counters.length) {
                            listEl.innerHTML = '<div class="list-group-item text-secondary small">' +
                                (allCounters.length
                                    ? @json(__('No counters match the search'))
                                    : @json(__('No Metrika counters found'))) + '</div>';
                            return;
                        }
                        counters.forEach(function (c) {
                            var btn = document.createElement('button');
                            btn.type = 'button';
                            btn.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-start gap-2';
                            btn.setAttribute('data-metrika-counter-id', String(c.id));
                            if (selectedId && Number(selectedId) === Number(c.id)) {
                                btn.classList.add('active');
                            }
                            btn.innerHTML =
                                '<span class="text-start">' +
                                '<strong>' + String(c.name || ('#' + c.id)).replace(/</g, '&lt;') + '</strong>' +
                                '<br><span class="small opacity-75">' + String(c.site || '').replace(/</g, '&lt;') +
                                ' · ID ' + c.id + '</span></span>' +
                                '<span class="flex-shrink-0 align-self-center">' +
                                (selectedId && Number(selectedId) === Number(c.id)
                                    ? '<span class="badge text-bg-light text-dark border">' + @json(__('Selected')) + '</span>'
                                    : '') +
                                '</span>';
                            btn.addEventListener('click', function () {
                                bindCounter(c.id);
                            });
                            listEl.appendChild(btn);
                        });
                    }

                    function applyCounterFilter() {
                        renderCounters(
                            filterCounters(allCounters, searchInput ? searchInput.value : ''),
                            selectedCounterId
                        );
                    }

                    function openForDomain(domain) {
                        currentDomain = domain || box.getAttribute('data-domain') || '';
                        allCounters = [];
                        selectedCounterId = 0;
                        if (domainLabel) domainLabel.textContent = currentDomain || '—';
                        if (authEl) authEl.classList.add('d-none');
                        if (listEl) listEl.innerHTML = '';
                        setSearchVisible(false);
                        if (currentEl) {
                            currentEl.classList.add('d-none');
                            currentEl.textContent = '';
                        }
                        if (unbindBtn) unbindBtn.classList.add('d-none');
                        setError('');
                        setLoading(true);
                        if (authLink) authLink.href = connectUrl(currentDomain);
                        openStep(3);
                        showModal();

                        if (box.getAttribute('data-metrika-configured') !== '1') {
                            setLoading(false);
                            setError(@json(__('Yandex Metrika is not configured')));
                            return;
                        }

                        var bindingUrl = box.getAttribute('data-metrika-binding-url') +
                            '?domain=' + encodeURIComponent(currentDomain);
                        fetch(bindingUrl, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                            .then(function (r) { return r.json(); })
                            .then(function (info) {
                                if (!info || !info.ok) {
                                    throw new Error('binding');
                                }
                                if (!info.configured) {
                                    setLoading(false);
                                    setError(@json(__('Yandex Metrika is not configured')));
                                    return null;
                                }
                                if (!info.connected) {
                                    setLoading(false);
                                    if (authEl) authEl.classList.remove('d-none');
                                    return null;
                                }
                                if (info.binding && currentEl) {
                                    currentEl.textContent = @json(__('Current counter')) + ': ' +
                                        (info.binding.counter_name || ('#' + info.binding.counter_id)) +
                                        (info.binding.counter_site ? ' (' + info.binding.counter_site + ')' : '');
                                    currentEl.classList.remove('d-none');
                                    if (unbindBtn) unbindBtn.classList.remove('d-none');
                                }
                                return fetch(box.getAttribute('data-metrika-counters-url'), {
                                    headers: { 'Accept': 'application/json' },
                                    credentials: 'same-origin',
                                }).then(function (r) {
                                    return r.json().then(function (data) {
                                        return { status: r.status, data: data, selected: info.binding && info.binding.counter_id };
                                    });
                                });
                            })
                            .then(function (result) {
                                setLoading(false);
                                if (!result) return;
                                if (result.status === 401 || (result.data && result.data.need_auth)) {
                                    if (authEl) authEl.classList.remove('d-none');
                                    return;
                                }
                                if (!result.data || !result.data.ok) {
                                    setError((result.data && result.data.message) || @json(__('Could not load Metrika counters')));
                                    return;
                                }
                                allCounters = result.data.counters || [];
                                selectedCounterId = result.selected || 0;
                                setSearchVisible(allCounters.length > 0);
                                applyCounterFilter();
                            })
                            .catch(function () {
                                setLoading(false);
                                setError(@json(__('Could not load Metrika counters')));
                            });
                    }

                    function bindCounter(counterId) {
                        setError('');
                        setLoading(true);
                        fetch(box.getAttribute('data-metrika-bind-url'), {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            credentials: 'same-origin',
                            body: JSON.stringify({ domain: currentDomain, counter_id: counterId }),
                        })
                            .then(function (r) { return r.json(); })
                            .then(function (data) {
                                if (!data || !data.ok) {
                                    throw new Error((data && data.message) || 'bind');
                                }
                                window.location.href = box.getAttribute('data-metrika-return') || location.pathname;
                            })
                            .catch(function () {
                                setLoading(false);
                                setError(@json(__('Could not bind Metrika counter')));
                            });
                    }

                    if (unbindBtn) {
                        unbindBtn.addEventListener('click', function () {
                            if (!currentDomain) return;
                            setLoading(true);
                            fetch(box.getAttribute('data-metrika-unbind-url'), {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': csrfToken,
                                    'X-Requested-With': 'XMLHttpRequest',
                                },
                                credentials: 'same-origin',
                                body: JSON.stringify({ domain: currentDomain }),
                            })
                                .then(function (r) { return r.json(); })
                                .then(function (data) {
                                    if (!data || !data.ok) throw new Error('unbind');
                                    window.location.href = box.getAttribute('data-metrika-return') || location.pathname;
                                })
                                .catch(function () {
                                    setLoading(false);
                                    setError(@json(__('Could not unbind Metrika counter')));
                                });
                        });
                    }

                    if (searchInput) {
                        searchInput.addEventListener('input', applyCounterFilter);
                    }

                    var openBtn = box.querySelector('[data-sr-metrika-open]');
                    if (openBtn) {
                        openBtn.addEventListener('click', function () {
                            openForDomain(box.getAttribute('data-domain') || '');
                        });
                    }

                    try {
                        var params = new URLSearchParams(window.location.search);
                        if (params.get('metrika_picker') === '1') {
                            openForDomain(params.get('metrika_domain') || box.getAttribute('data-domain') || '');
                            if (window.history && window.history.replaceState) {
                                params.delete('metrika_picker');
                                params.delete('metrika_domain');
                                var q = params.toString();
                                window.history.replaceState({}, '', window.location.pathname + (q ? '?' + q : '') + window.location.hash);
                            }
                        }
                    } catch (e) {}
                })();

                (function initWebmasterPicker() {
                    var box = document.querySelector('[data-sr-webmaster]');
                    var modalEl = document.getElementById('cabinet-sr-webmaster-modal');
                    if (!box || !modalEl) return;

                    var csrfEl = document.querySelector('meta[name="csrf-token"]');
                    var csrfToken = csrfEl ? csrfEl.getAttribute('content') : '';
                    var currentDomain = box.getAttribute('data-domain') || '';
                    var allHosts = [];
                    var selectedHostId = '';
                    var listEl = modalEl.querySelector('[data-webmaster-list]');
                    var loadingEl = modalEl.querySelector('[data-webmaster-loading]');
                    var errorEl = modalEl.querySelector('[data-webmaster-error]');
                    var authEl = modalEl.querySelector('[data-webmaster-auth]');
                    var authLink = modalEl.querySelector('[data-webmaster-auth-link]');
                    var domainLabel = modalEl.querySelector('[data-webmaster-domain-label]');
                    var currentEl = modalEl.querySelector('[data-webmaster-current]');
                    var unbindBtn = modalEl.querySelector('[data-webmaster-unbind]');
                    var searchWrap = modalEl.querySelector('[data-webmaster-search-wrap]');
                    var searchInput = modalEl.querySelector('[data-webmaster-search]');

                    function showModal() {
                        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                            bootstrap.Modal.getOrCreateInstance(modalEl).show();
                            return;
                        }
                        if (typeof $ !== 'undefined' && $.fn.modal) {
                            $(modalEl).modal('show');
                        }
                    }

                    function connectUrl(domain) {
                        var base = box.getAttribute('data-webmaster-connect-url') || '';
                        var ret = box.getAttribute('data-webmaster-return') || location.href;
                        return base + (base.indexOf('?') === -1 ? '?' : '&') +
                            'domain=' + encodeURIComponent(domain || '') +
                            '&return=' + encodeURIComponent(ret);
                    }

                    function setError(msg) {
                        if (!errorEl) return;
                        errorEl.textContent = msg || '';
                        errorEl.classList.toggle('d-none', !msg);
                    }

                    function setLoading(on) {
                        if (loadingEl) loadingEl.classList.toggle('d-none', !on);
                    }

                    function setSearchVisible(on) {
                        if (searchWrap) searchWrap.classList.toggle('d-none', !on);
                        if (!on && searchInput) searchInput.value = '';
                    }

                    function filterHosts(hosts, query) {
                        var q = String(query || '').trim().toLowerCase();
                        if (!q) return hosts.slice();
                        return hosts.filter(function (h) {
                            var url = String(h.unicode_url || h.url || '').toLowerCase();
                            var id = String(h.id || '').toLowerCase();
                            var domain = String(h.domain || '').toLowerCase();
                            return url.indexOf(q) !== -1 || id.indexOf(q) !== -1 || domain.indexOf(q) !== -1;
                        });
                    }

                    function renderHosts(hosts, selectedId) {
                        if (!listEl) return;
                        listEl.innerHTML = '';
                        if (!hosts.length) {
                            listEl.innerHTML = '<div class="list-group-item text-secondary small">' +
                                (allHosts.length
                                    ? @json(__('No hosts match the search'))
                                    : @json(__('No Webmaster hosts found'))) + '</div>';
                            return;
                        }
                        hosts.forEach(function (h) {
                            var btn = document.createElement('button');
                            btn.type = 'button';
                            btn.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-start gap-2';
                            btn.setAttribute('data-webmaster-host-id', String(h.id));
                            if (selectedId && String(selectedId) === String(h.id)) {
                                btn.classList.add('active');
                            }
                            var title = String(h.unicode_url || h.url || h.id || '').replace(/</g, '&lt;');
                            var meta = String(h.id || '').replace(/</g, '&lt;');
                            if (h.verified) {
                                meta += ' · ' + @json(__('Webmaster host verified'));
                            }
                            btn.innerHTML =
                                '<span class="text-start">' +
                                '<strong>' + title + '</strong>' +
                                '<br><span class="small opacity-75">' + meta + '</span></span>' +
                                '<span class="flex-shrink-0 align-self-center">' +
                                (selectedId && String(selectedId) === String(h.id)
                                    ? '<span class="badge text-bg-light text-dark border">' + @json(__('Selected')) + '</span>'
                                    : '') +
                                '</span>';
                            btn.addEventListener('click', function () {
                                bindHost(h.id);
                            });
                            listEl.appendChild(btn);
                        });
                    }

                    function applyHostFilter() {
                        renderHosts(
                            filterHosts(allHosts, searchInput ? searchInput.value : ''),
                            selectedHostId
                        );
                    }

                    function openForDomain(domain) {
                        currentDomain = domain || box.getAttribute('data-domain') || '';
                        allHosts = [];
                        selectedHostId = '';
                        if (domainLabel) domainLabel.textContent = currentDomain || '—';
                        if (authEl) authEl.classList.add('d-none');
                        if (listEl) listEl.innerHTML = '';
                        setSearchVisible(false);
                        if (currentEl) {
                            currentEl.classList.add('d-none');
                            currentEl.textContent = '';
                        }
                        if (unbindBtn) unbindBtn.classList.add('d-none');
                        setError('');
                        setLoading(true);
                        if (authLink) authLink.href = connectUrl(currentDomain);
                        openStep(4);
                        showModal();

                        if (box.getAttribute('data-webmaster-configured') !== '1') {
                            setLoading(false);
                            setError(@json(__('Yandex Webmaster is not configured')));
                            return;
                        }

                        var bindingUrl = box.getAttribute('data-webmaster-binding-url') +
                            '?domain=' + encodeURIComponent(currentDomain);
                        fetch(bindingUrl, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                            .then(function (r) { return r.json(); })
                            .then(function (info) {
                                if (!info || !info.ok) {
                                    throw new Error('binding');
                                }
                                if (!info.configured) {
                                    setLoading(false);
                                    setError(@json(__('Yandex Webmaster is not configured')));
                                    return null;
                                }
                                if (!info.connected) {
                                    setLoading(false);
                                    if (authEl) authEl.classList.remove('d-none');
                                    return null;
                                }
                                if (info.binding && currentEl) {
                                    currentEl.textContent = @json(__('Current Webmaster host')) + ': ' +
                                        (info.binding.host_url || info.binding.host_id);
                                    currentEl.classList.remove('d-none');
                                    if (unbindBtn) unbindBtn.classList.remove('d-none');
                                }
                                return fetch(box.getAttribute('data-webmaster-hosts-url'), {
                                    headers: { 'Accept': 'application/json' },
                                    credentials: 'same-origin',
                                }).then(function (r) {
                                    return r.json().then(function (data) {
                                        return { status: r.status, data: data, selected: info.binding && info.binding.host_id };
                                    });
                                });
                            })
                            .then(function (result) {
                                setLoading(false);
                                if (!result) return;
                                if (result.status === 401 || (result.data && result.data.need_auth)) {
                                    if (authEl) authEl.classList.remove('d-none');
                                    return;
                                }
                                if (!result.data || !result.data.ok) {
                                    setError((result.data && result.data.message) || @json(__('Could not load Webmaster hosts')));
                                    return;
                                }
                                allHosts = result.data.hosts || [];
                                selectedHostId = result.selected || '';
                                setSearchVisible(allHosts.length > 0);
                                applyHostFilter();
                            })
                            .catch(function () {
                                setLoading(false);
                                setError(@json(__('Could not load Webmaster hosts')));
                            });
                    }

                    function bindHost(hostId) {
                        setError('');
                        setLoading(true);
                        fetch(box.getAttribute('data-webmaster-bind-url'), {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            credentials: 'same-origin',
                            body: JSON.stringify({ domain: currentDomain, host_id: hostId }),
                        })
                            .then(function (r) { return r.json(); })
                            .then(function (data) {
                                if (!data || !data.ok) {
                                    throw new Error((data && data.message) || 'bind');
                                }
                                window.location.href = box.getAttribute('data-webmaster-return') || location.pathname;
                            })
                            .catch(function () {
                                setLoading(false);
                                setError(@json(__('Could not bind Webmaster host')));
                            });
                    }

                    if (unbindBtn) {
                        unbindBtn.addEventListener('click', function () {
                            if (!currentDomain) return;
                            setLoading(true);
                            fetch(box.getAttribute('data-webmaster-unbind-url'), {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': csrfToken,
                                    'X-Requested-With': 'XMLHttpRequest',
                                },
                                credentials: 'same-origin',
                                body: JSON.stringify({ domain: currentDomain }),
                            })
                                .then(function (r) { return r.json(); })
                                .then(function (data) {
                                    if (!data || !data.ok) throw new Error('unbind');
                                    window.location.href = box.getAttribute('data-webmaster-return') || location.pathname;
                                })
                                .catch(function () {
                                    setLoading(false);
                                    setError(@json(__('Could not unbind Webmaster host')));
                                });
                        });
                    }

                    if (searchInput) {
                        searchInput.addEventListener('input', applyHostFilter);
                    }

                    var openBtn = box.querySelector('[data-sr-webmaster-open]');
                    if (openBtn) {
                        openBtn.addEventListener('click', function () {
                            openForDomain(box.getAttribute('data-domain') || '');
                        });
                    }

                    try {
                        var params = new URLSearchParams(window.location.search);
                        if (params.get('webmaster_picker') === '1') {
                            openForDomain(params.get('webmaster_domain') || box.getAttribute('data-domain') || '');
                            if (window.history && window.history.replaceState) {
                                params.delete('webmaster_picker');
                                params.delete('webmaster_domain');
                                var q = params.toString();
                                window.history.replaceState({}, '', window.location.pathname + (q ? '?' + q : '') + window.location.hash);
                            }
                        }
                    } catch (e) {}
                })();
            })();
        </script>
    @endslot
@endcomponent
