@component('component.card', [
    'title' => __('Reports'),
    'titleHtml' => e(__('Reports')) . view('partials.cabinet-module-version-badge', ['configKey' => 'cabinet-seo-reports'])->render(),
    'documentTitle' => __('Reports'),
])
    @slot('css')
        <link rel="stylesheet" href="{{ asset('plugins/select2/css/select2.min.css') }}">
        <link rel="stylesheet" href="{{ asset('plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css') }}">
        <link rel="stylesheet" href="{{ asset('css/cabinet-seo-reports.css') }}?v={{ @filemtime(public_path('css/cabinet-seo-reports.css')) ?: time() }}">
    @endslot

    <div class="cabinet-sr-page">
        @include('pages.partials.seo-reports-nav', [
            'srTab' => 'projects',
            'srProjectsCount' => $projects->count(),
        ])

        @if(session('success'))
            <div class="alert alert-success py-2 px-3 small">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger py-2 px-3 small">{{ session('error') }}</div>
        @endif

        <div class="cabinet-sr-hero">
            <div>
                <h1 class="cabinet-sr-hero__title">{{ __('Projects') }}</h1>
                <p class="cabinet-sr-hero__lead">{{ __('SEO reports lead') }}</p>
            </div>
            @if(count($availableDomains) > 0)
                <div class="cabinet-sr-actions">
                    <button type="button"
                            class="btn btn-primary btn-sm"
                            data-bs-toggle="modal"
                            data-bs-target="#cabinetSrCreateModal">
                        {{ __('Create SEO report project') }}
                    </button>
                </div>
            @endif
        </div>

        @if(!empty($missingReports))
            <div class="alert alert-warning py-2 px-3 small mb-3">
                <div class="fw-semibold mb-1">{{ __('Missing monthly reports') }} · {{ $missingMonthLabel ?? '' }}</div>
                <ul class="mb-0 ps-3">
                    @foreach($missingReports as $mp)
                        <li>
                            <a href="{{ route('pages.seo-reports.show', ['id' => $mp->id]) }}">{{ $mp->domain }}</a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if($projects->isEmpty())
            <div class="cabinet-sr-empty">
                <i class="bi bi-file-earmark-bar-graph display-6 d-block mb-2 opacity-50"></i>
                <p class="fw-semibold mb-1 text-body">{{ __('No SEO report projects yet') }}</p>
                <p class="small mb-3">{{ __('No SEO report projects yet hint') }}</p>
                @if(count($availableDomains) > 0)
                    <button type="button"
                            class="btn btn-primary btn-sm"
                            data-bs-toggle="modal"
                            data-bs-target="#cabinetSrCreateModal">
                        {{ __('Create SEO report project') }}
                    </button>
                @endif
            </div>
        @else
            <div class="cabinet-sr-toolbar cabinet-sr-toolbar--projects">
                <input type="search"
                       class="form-control form-control-sm"
                       placeholder="{{ __('Search projects') }}…"
                       data-sr-project-search
                       autocomplete="off">
                <form method="post" action="{{ route('pages.seo-reports.batch') }}" class="cabinet-sr-batch" id="cabinetSrBatchForm">
                    @csrf
                    <button type="submit" class="btn btn-outline-primary btn-sm">{{ __('Batch generate previous month') }}</button>
                    <label class="cabinet-sr-toggle-row mb-0">
                        <input type="checkbox" name="force" value="1">
                        <span class="small">{{ __('Force regenerate existing') }}</span>
                    </label>
                </form>
            </div>
            <div class="cabinet-sr-project-list" data-sr-project-grid>
                @foreach($projects as $project)
                    @php
                        $isShared = !empty($sharedProjectIds) && in_array($project->id, $sharedProjectIds, true);
                        $isTeam = !empty($teamProjectIds) && in_array($project->id, $teamProjectIds, true);
                        $isOwner = (int) $project->user_id === (int) Auth::id();
                        $projSettings = is_array($project->settings_json) ? $project->settings_json : [];
                        $hasMetrika = (int) ($project->metrika_counter_id ?? 0) > 0;
                        $hasMonitoring = (int) ($project->monitoring_project_id ?? 0) > 0;
                        $hasWm = trim((string) ($projSettings['webmaster_host'] ?? '')) !== '';
                        $reportsCount = (int) $project->reports_count;
                        $title = trim((string) ($project->title ?? ''));
                    @endphp
                    <article class="cabinet-sr-project"
                             data-sr-project-card
                             data-domain="{{ $project->domain }}"
                             data-title="{{ $title }}">
                        <div class="cabinet-sr-project__top">
                            <label class="cabinet-sr-project__select" title="{{ __('Select for batch') }}">
                                <input type="checkbox" form="cabinetSrBatchForm" name="project_ids[]" value="{{ $project->id }}">
                                <span class="visually-hidden">{{ __('Select') }}</span>
                            </label>
                            <div class="cabinet-sr-project__identity">
                                <h2 class="cabinet-sr-project__domain">{{ $project->domain }}</h2>
                                <p class="cabinet-sr-project__title">
                                    {{ $title !== '' ? $title : __('SEO report project') }}
                                    @if($isShared)
                                        <span class="cabinet-sr-project__tag">{{ __('Shared') }}</span>
                                    @elseif($isTeam)
                                        <span class="cabinet-sr-project__tag">{{ __('Team') }}</span>
                                    @endif
                                </p>
                            </div>
                            <div class="cabinet-sr-project__reports" title="{{ __('Reports') }}">
                                <strong>{{ $reportsCount }}</strong>
                                <span>{{ __('Reports') }}</span>
                            </div>
                        </div>
                        <ul class="cabinet-sr-project__sources" aria-label="{{ __('Connections health') }}">
                            <li class="is-{{ $hasMetrika ? 'on' : 'off' }}">
                                <i class="bi bi-{{ $hasMetrika ? 'check-circle-fill' : 'circle' }}" aria-hidden="true"></i>
                                {{ __('Metrika') }}
                            </li>
                            <li class="is-{{ $hasMonitoring ? 'on' : 'off' }}">
                                <i class="bi bi-{{ $hasMonitoring ? 'check-circle-fill' : 'circle' }}" aria-hidden="true"></i>
                                {{ __('Monitoring') }}
                            </li>
                            <li class="is-{{ $hasWm ? 'on' : 'off' }}">
                                <i class="bi bi-{{ $hasWm ? 'check-circle-fill' : 'circle' }}" aria-hidden="true"></i>
                                {{ __('Webmaster') }}
                            </li>
                        </ul>
                        <div class="cabinet-sr-project__actions">
                            <a class="btn btn-primary btn-sm"
                               href="{{ route('pages.seo-reports.show', ['id' => $project->id]) }}">
                                {{ __('Open project') }}
                            </a>
                            @if($isOwner)
                                <a class="btn btn-outline-secondary btn-sm"
                                   href="{{ route('pages.seo-reports.settings', ['id' => $project->id]) }}">
                                    {{ __('Settings') }}
                                </a>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        @endif

        @if($archived->isNotEmpty())
            <details class="mt-4">
                <summary class="small text-secondary" style="cursor:pointer">
                    {{ __('Archived') }} ({{ $archived->count() }})
                </summary>
                <ul class="list-unstyled mt-2 mb-0 small">
                    @foreach($archived as $project)
                        <li class="d-flex align-items-center gap-2 py-1">
                            <span>{{ $project->domain }}</span>
                            <form method="post" action="{{ route('pages.seo-reports.restore', ['id' => $project->id]) }}" class="ms-auto">
                                @csrf
                                <button type="submit" class="btn btn-link btn-sm p-0">{{ __('Restore') }}</button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            </details>
        @endif
    </div>

    <div class="modal fade" id="cabinetSrCreateModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <form class="modal-content" method="post" action="{{ route('pages.seo-reports.store') }}" data-sr-wizard>
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">{{ __('Create SEO report project') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="cabinet-sr-wizard-steps mb-3">
                        <span class="is-active" data-sr-step-label="1">1. {{ __('Domain') }}</span>
                        <span data-sr-step-label="2">2. {{ __('Integrations') }}</span>
                        <span data-sr-step-label="3">3. {{ __('Template') }}</span>
                    </div>

                    <div data-sr-step="1">
                        <div class="mb-3">
                            <label class="form-label" for="cabinetSrDomain">{{ __('Domain') }}</label>
                            <select class="form-select" name="domain" id="cabinetSrDomain" required
                                    data-sr-domain
                                    data-placeholder="{{ __('Select domain') }}">
                                <option value=""></option>
                                @foreach($availableDomains as $domain)
                                    <option value="{{ $domain }}"
                                            data-metrika="{{ $domainHints[$domain]['metrika'] ?? '' }}"
                                            data-metrika-label="{{ $domainHints[$domain]['metrika_label'] ?? '' }}"
                                            data-monitoring="{{ $domainHints[$domain]['monitoring'] ?? '' }}"
                                            data-monitoring-label="{{ $domainHints[$domain]['monitoring_label'] ?? '' }}"
                                            data-webmaster="{{ $domainHints[$domain]['webmaster'] ?? '' }}"
                                            data-webmaster-label="{{ $domainHints[$domain]['webmaster_label'] ?? '' }}">
                                        {{ $domain }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-0">
                            <label class="form-label" for="cabinetSrTitle">{{ __('Title') }} <span class="text-secondary">({{ __('optional') }})</span></label>
                            <input type="text" class="form-control" name="title" id="cabinetSrTitle" maxlength="190">
                        </div>
                    </div>

                    <div data-sr-step="2" hidden>
                        <p class="small text-secondary">{{ __('SEO report wizard integrations hint') }}</p>
                        <div class="mb-2"
                             data-sr-metrika
                             data-metrika-configured="{{ !empty($metrikaConfigured) ? '1' : '0' }}"
                             data-metrika-connected="{{ !empty($metrikaConnected) ? '1' : '0' }}"
                             data-metrika-connect-url="{{ route('yandex-metrika.connect') }}"
                             data-metrika-binding-url="{{ route('yandex-metrika.binding') }}"
                             data-metrika-counters-url="{{ route('yandex-metrika.counters') }}"
                             data-metrika-bind-url="{{ route('yandex-metrika.bind') }}"
                             data-metrika-unbind-url="{{ route('yandex-metrika.unbind') }}"
                             data-metrika-return="{{ route('pages.seo-reports') }}">
                            <label class="form-label" for="cabinetSrMetrika">{{ __('Yandex Metrika') }}</label>
                            <div class="d-flex flex-wrap gap-2 align-items-start mb-1">
                                <div class="flex-grow-1" style="min-width: 12rem;">
                                    <select class="form-select form-select-sm"
                                            name="metrika_counter_id"
                                            id="cabinetSrMetrika"
                                            data-sr-wizard-select2
                                            data-sr-metrika-select
                                            data-placeholder="{{ __('Not connected') }}">
                                        <option value=""></option>
                                        @foreach(($metrikaBindings ?? collect()) as $binding)
                                            <option value="{{ $binding->counter_id }}"
                                                    data-domain="{{ $binding->domain }}">
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
                            <div class="form-text">{{ __('Metrika connect from create hint') }}</div>
                        </div>

                        <div class="mb-2"
                             data-sr-webmaster
                             data-webmaster-configured="{{ !empty($webmasterConfigured) ? '1' : '0' }}"
                             data-webmaster-connected="{{ !empty($webmasterConnected) ? '1' : '0' }}"
                             data-webmaster-connect-url="{{ route('yandex-webmaster.connect') }}"
                             data-webmaster-binding-url="{{ route('yandex-webmaster.binding') }}"
                             data-webmaster-hosts-url="{{ route('yandex-webmaster.hosts') }}"
                             data-webmaster-bind-url="{{ route('yandex-webmaster.bind') }}"
                             data-webmaster-unbind-url="{{ route('yandex-webmaster.unbind') }}"
                             data-webmaster-return="{{ route('pages.seo-reports') }}">
                            <label class="form-label" for="cabinetSrWebmaster">{{ __('Yandex Webmaster') }}</label>
                            <div class="d-flex flex-wrap gap-2 align-items-start mb-1">
                                <div class="flex-grow-1" style="min-width: 12rem;">
                                    <select class="form-select form-select-sm"
                                            name="webmaster_host"
                                            id="cabinetSrWebmaster"
                                            data-sr-wizard-select2
                                            data-sr-webmaster-select
                                            data-placeholder="{{ __('Not connected') }}">
                                        <option value=""></option>
                                        @foreach(($webmasterBindings ?? collect()) as $binding)
                                            <option value="{{ $binding->host_id }}"
                                                    data-domain="{{ $binding->domain }}">
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
                            <div class="form-text">{{ __('Webmaster connect from create hint') }}</div>
                        </div>

                        <div class="mb-0">
                            <label class="form-label" for="cabinetSrMonitoring">{{ __('Position monitoring') }}</label>
                            <select class="form-select form-select-sm"
                                    name="monitoring_project_id"
                                    id="cabinetSrMonitoring"
                                    data-sr-wizard-select2
                                    data-sr-monitoring-select
                                    data-placeholder="{{ __('Not connected') }}">
                                <option value=""></option>
                                @foreach(($monitoringOptions ?? []) as $option)
                                    <option value="{{ $option['id'] }}">{{ $option['label'] }}</option>
                                @endforeach
                            </select>
                            <div class="form-text">{{ __('Monitoring connect from create hint') }}</div>
                        </div>
                    </div>

                    <div data-sr-step="3" hidden>
                        <label class="form-label mb-1">{{ __('Report template') }}</label>
                        <p class="small text-secondary mb-2">{{ __('SEO report wizard attach template hint') }}</p>

                        @if(!empty($reportTemplates) && $reportTemplates->isNotEmpty())
                            <div class="cabinet-sr-template-pick-list mb-3">
                                @foreach($reportTemplates as $idx => $tpl)
                                    <label class="cabinet-sr-toggle-row">
                                        <input type="radio" name="template_id" value="{{ $tpl->id }}"
                                            @if($tpl->is_default || $idx === 0) checked @endif
                                            data-sr-template-pick>
                                        <span>
                                            <strong>{{ $tpl->title }}</strong>
                                            @if($tpl->is_default)
                                                <small>· {{ __('Default') }}</small>
                                            @endif
                                            @if($tpl->agency_name)
                                                <small class="d-block text-secondary">{{ $tpl->agency_name }}</small>
                                            @endif
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                            <p class="small text-secondary mb-2">
                                <a href="{{ route('pages.seo-reports.templates') }}" target="_blank" rel="noopener">
                                    {{ __('Manage templates') }}
                                </a>
                                — {{ __('SEO report wizard copy template hint') }}
                            </p>
                        @endif

                        <details class="cabinet-sr-fieldset" @if(empty($reportTemplates) || $reportTemplates->isEmpty()) open @endif>
                            <summary class="small fw-semibold mb-2">{{ __('Or create new template from preset') }}</summary>
                            <input type="hidden" name="force_new_template" value="0" data-sr-force-new-template>
                            <div class="cabinet-sr-preset-list">
                                @foreach(\App\SeoReports\SeoReportSectionRegistry::presetCards() as $idx => $preset)
                                    <div class="cabinet-sr-preset-card">
                                        <label class="cabinet-sr-preset-card__pick">
                                            <input type="radio" name="template" value="{{ $preset['key'] }}"
                                                data-sr-preset-pick
                                                @if((empty($reportTemplates) || $reportTemplates->isEmpty()) && $idx === 0) checked @endif>
                                            <div class="cabinet-sr-preset-card__body">
                                                <div class="cabinet-sr-preset-card__head">
                                                    <strong class="cabinet-sr-preset-card__title">{{ $preset['title'] }}</strong>
                                                    <span class="cabinet-sr-preset-card__count">{{ $preset['sections_count'] }} {{ __('sections') }}</span>
                                                </div>
                                                <p class="cabinet-sr-preset-card__lead mb-0">{{ $preset['lead'] }}</p>
                                            </div>
                                        </label>
                                        <a class="cabinet-sr-preset-card__demo-link"
                                           href="{{ route('pages.seo-reports.preset-demo', ['preset' => $preset['key']]) }}"
                                           target="_blank"
                                           rel="noopener">
                                            {{ __('Open full HTML demo report') }} →
                                        </a>
                                    </div>
                                @endforeach
                            </div>
                        </details>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                    <button type="button" class="btn btn-outline-secondary" data-sr-prev hidden>{{ __('Back') }}</button>
                    <button type="button" class="btn btn-primary" data-sr-next>{{ __('Next') }}</button>
                    <button type="submit" class="btn btn-primary" data-sr-finish hidden>{{ __('Create') }}</button>
                </div>
            </form>
        </div>
    </div>

    @include('pages.partials.seo-reports-metrika-modal')
    @include('pages.partials.seo-reports-webmaster-modal')

    @slot('js')
        <script src="{{ asset('plugins/select2/js/select2.full.min.js') }}"></script>
        <script>
            (function () {
                var input = document.querySelector('[data-sr-project-search]');
                var grid = document.querySelector('[data-sr-project-grid]');
                if (input && grid) {
                    input.addEventListener('input', function () {
                        var q = (input.value || '').toLowerCase().trim();
                        grid.querySelectorAll('[data-sr-project-card]').forEach(function (card) {
                            var domain = (card.getAttribute('data-domain') || '').toLowerCase();
                            var title = (card.getAttribute('data-title') || '').toLowerCase();
                            var match = !q || domain.indexOf(q) !== -1 || title.indexOf(q) !== -1;
                            card.style.display = match ? '' : 'none';
                        });
                    });
                }

                var form = document.querySelector('[data-sr-wizard]');
                if (!form) return;
                var step = 1;
                var max = 3;
                var forceNew = form.querySelector('[data-sr-force-new-template]');
                function syncTemplateMode() {
                    if (!forceNew) return;
                    var presetChecked = form.querySelector('[data-sr-preset-pick]:checked');
                    var templateChecked = form.querySelector('[data-sr-template-pick]:checked');
                    forceNew.value = presetChecked && !templateChecked ? '1' : (presetChecked && templateChecked ? '0' : '0');
                    // Selecting a preset means create a fresh template for this project.
                    if (presetChecked) {
                        form.querySelectorAll('[data-sr-template-pick]').forEach(function (el) { el.checked = false; });
                        forceNew.value = '1';
                    }
                }
                form.querySelectorAll('[data-sr-preset-pick]').forEach(function (el) {
                    el.addEventListener('change', syncTemplateMode);
                });
                form.querySelectorAll('[data-sr-template-pick]').forEach(function (el) {
                    el.addEventListener('change', function () {
                        form.querySelectorAll('[data-sr-preset-pick]').forEach(function (p) { p.checked = false; });
                        if (forceNew) forceNew.value = '0';
                    });
                });
                var domainSelect = form.querySelector('[data-sr-domain]');
                var metrikaSelect = form.querySelector('[data-sr-metrika-select]');
                var webmasterSelect = form.querySelector('[data-sr-webmaster-select]');
                var monitoringSelect = form.querySelector('[data-sr-monitoring-select]');
                var btnPrev = form.querySelector('[data-sr-prev]');
                var btnNext = form.querySelector('[data-sr-next]');
                var btnFinish = form.querySelector('[data-sr-finish]');
                var modal = document.getElementById('cabinetSrCreateModal');

                function elevateNestedModal(modalEl) {
                    if (!modalEl) return;
                    var onShow = function () {
                        modalEl.classList.add('cabinet-sr-modal-nested');
                    };
                    var onShown = function () {
                        var backs = document.querySelectorAll('.modal-backdrop');
                        if (backs.length) {
                            backs[backs.length - 1].classList.add('cabinet-sr-modal-nested-backdrop');
                        }
                    };
                    var onHidden = function () {
                        modalEl.classList.remove('cabinet-sr-modal-nested');
                        document.querySelectorAll('.modal-backdrop.cabinet-sr-modal-nested-backdrop').forEach(function (el) {
                            el.classList.remove('cabinet-sr-modal-nested-backdrop');
                        });
                    };
                    modalEl.addEventListener('show.bs.modal', onShow, { once: true });
                    modalEl.addEventListener('shown.bs.modal', onShown, { once: true });
                    modalEl.addEventListener('hidden.bs.modal', onHidden, { once: true });
                }

                function setSelectValue(el, val) {
                    if (!el) return;
                    var next = val == null ? '' : String(val);
                    if (typeof window.jQuery !== 'undefined' && window.jQuery(el).data('select2')) {
                        window.jQuery(el).val(next || null).trigger('change');
                        return;
                    }
                    el.value = next;
                }

                function syncHints() {
                    var opt = domainSelect.options[domainSelect.selectedIndex];
                    var m = opt ? (opt.getAttribute('data-metrika') || '') : '';
                    var mon = opt ? (opt.getAttribute('data-monitoring') || '') : '';
                    var wm = opt ? (opt.getAttribute('data-webmaster') || '') : '';
                    setSelectValue(monitoringSelect, mon);
                    if (metrikaSelect) {
                        if (m) {
                            setSelectValue(metrikaSelect, m);
                        } else if (!metrikaSelect.value) {
                            setSelectValue(metrikaSelect, '');
                        }
                    }
                    if (webmasterSelect) {
                        if (wm) {
                            setSelectValue(webmasterSelect, wm);
                        } else if (!webmasterSelect.value) {
                            setSelectValue(webmasterSelect, '');
                        }
                    }
                    var wmBox = form.querySelector('[data-sr-webmaster]');
                    if (wmBox) {
                        wmBox.setAttribute('data-domain', domainSelect.value || '');
                    }
                }

                function render() {
                    form.querySelectorAll('[data-sr-step]').forEach(function (el) {
                        el.hidden = String(el.getAttribute('data-sr-step')) !== String(step);
                    });
                    form.querySelectorAll('[data-sr-step-label]').forEach(function (el) {
                        el.classList.toggle('is-active', String(el.getAttribute('data-sr-step-label')) === String(step));
                    });
                    btnPrev.hidden = step <= 1;
                    btnNext.hidden = step >= max;
                    btnFinish.hidden = step < max;
                }

                var restoreCreateState = null;
                try {
                    var bootParams = new URLSearchParams(window.location.search);
                    if (bootParams.get('sr_create') === '1' || bootParams.get('metrika_picker') === '1' || bootParams.get('webmaster_picker') === '1') {
                        restoreCreateState = {
                            domain: bootParams.get('domain') || bootParams.get('metrika_domain') || bootParams.get('webmaster_domain') || '',
                            picker: bootParams.get('metrika_picker') === '1',
                            webmasterPicker: bootParams.get('webmaster_picker') === '1',
                            create: bootParams.get('sr_create') === '1' || bootParams.get('metrika_picker') === '1' || bootParams.get('webmaster_picker') === '1',
                        };
                    }
                } catch (e) {}

                function mountWizardSelect2($select) {
                    if (!$select || !$select.length) return;
                    if ($select.hasClass('select2-hidden-accessible')) {
                        $select.select2('destroy');
                    }
                    var $modal = modal ? window.jQuery(modal) : null;
                    $select.select2({
                        theme: 'bootstrap4',
                        width: '100%',
                        placeholder: $select.attr('data-placeholder') || '',
                        allowClear: true,
                        dropdownParent: $modal && $modal.length ? $modal : window.jQuery(document.body),
                        language: {
                            noResults: function () { return 'Ничего не найдено'; },
                            searching: function () { return 'Поиск…'; }
                        }
                    });
                }

                function destroyWizardSelect2($select) {
                    if ($select && $select.length && $select.hasClass('select2-hidden-accessible')) {
                        $select.select2('destroy');
                    }
                }

                function initWizardSelect2() {
                    if (typeof window.jQuery === 'undefined' || typeof window.jQuery.fn.select2 !== 'function') {
                        return;
                    }
                    if (!domainSelect) return;
                    var $ = window.jQuery;
                    var $domain = $(domainSelect);
                    var $modal = modal ? $(modal) : null;
                    var $extra = form.querySelectorAll('[data-sr-wizard-select2]');

                    function mountAll() {
                        mountWizardSelect2($domain);
                        Array.prototype.forEach.call($extra, function (el) {
                            mountWizardSelect2($(el));
                        });
                    }

                    function destroyAll() {
                        destroyWizardSelect2($domain);
                        Array.prototype.forEach.call($extra, function (el) {
                            destroyWizardSelect2($(el));
                        });
                    }

                    if ($modal && $modal.length) {
                        $modal.on('shown.bs.modal', function () {
                            mountAll();
                            if (restoreCreateState && restoreCreateState.domain) {
                                $domain.val(restoreCreateState.domain).trigger('change');
                                step = 2;
                                syncHints();
                                render();
                            } else {
                                $domain.val(null).trigger('change');
                                step = 1;
                                render();
                            }
                        });
                        $modal.on('hidden.bs.modal', function () {
                            destroyAll();
                            restoreCreateState = null;
                        });
                    } else {
                        mountAll();
                    }
                }

                domainSelect.addEventListener('change', syncHints);
                if (typeof window.jQuery !== 'undefined') {
                    window.jQuery(domainSelect).on('change.select2', syncHints);
                }
                btnNext.addEventListener('click', function () {
                    if (step === 1 && !domainSelect.value) {
                        if (typeof window.jQuery !== 'undefined' && window.jQuery(domainSelect).data('select2')) {
                            window.jQuery(domainSelect).select2('open');
                        } else {
                            domainSelect.focus();
                        }
                        return;
                    }
                    if (step < max) {
                        step += 1;
                        if (step === 2) syncHints();
                        render();
                    }
                });
                btnPrev.addEventListener('click', function () {
                    if (step > 1) {
                        step -= 1;
                        render();
                    }
                });
                initWizardSelect2();
                render();

                (function initMetrikaPicker() {
                    var box = form.querySelector('[data-sr-metrika]');
                    var modalEl = document.getElementById('cabinet-sr-metrika-modal');
                    if (!box || !modalEl) return;

                    var csrfEl = document.querySelector('meta[name="csrf-token"]');
                    var csrfToken = csrfEl ? csrfEl.getAttribute('content') : '';
                    var currentDomain = '';
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
                            elevateNestedModal(modalEl);
                            bootstrap.Modal.getOrCreateInstance(modalEl).show();
                            return;
                        }
                        if (typeof $ !== 'undefined' && $.fn.modal) {
                            elevateNestedModal(modalEl);
                            $(modalEl).modal('show');
                        }
                    }

                    function connectUrl(domain) {
                        var base = box.getAttribute('data-metrika-connect-url') || '';
                        var ret = box.getAttribute('data-metrika-return') || location.href;
                        try {
                            var u = new URL(ret, window.location.origin);
                            u.searchParams.set('sr_create', '1');
                            if (domain) u.searchParams.set('domain', domain);
                            ret = u.pathname + u.search;
                        } catch (e) {}
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
                            if (selectedId && Number(selectedId) === Number(c.id)) {
                                btn.classList.add('active');
                            }
                            btn.innerHTML =
                                '<span class="text-start">' +
                                '<strong>' + String(c.name || ('#' + c.id)).replace(/</g, '&lt;') + '</strong>' +
                                '<br><span class="small opacity-75">' + String(c.site || '').replace(/</g, '&lt;') +
                                ' · ID ' + c.id + '</span></span>';
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
                        currentDomain = domain || (domainSelect ? domainSelect.value : '') || '';
                        if (!currentDomain) {
                            step = 1;
                            render();
                            if (typeof window.jQuery !== 'undefined' && window.jQuery(domainSelect).data('select2')) {
                                window.jQuery(domainSelect).select2('open');
                            }
                            return;
                        }
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
                                if (!info || !info.ok) throw new Error('binding');
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
                                        (info.binding.counter_name || ('#' + info.binding.counter_id));
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
                                if (!data || !data.ok) throw new Error((data && data.message) || 'bind');
                                var ret = box.getAttribute('data-metrika-return') || location.pathname;
                                try {
                                    var u = new URL(ret, window.location.origin);
                                    u.searchParams.set('sr_create', '1');
                                    if (currentDomain) u.searchParams.set('domain', currentDomain);
                                    ret = u.pathname + u.search;
                                } catch (e) {}
                                window.location.href = ret;
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
                                    window.location.reload();
                                })
                                .catch(function () {
                                    setLoading(false);
                                    setError(@json(__('Could not bind Metrika counter')));
                                });
                        });
                    }

                    if (searchInput) {
                        searchInput.addEventListener('input', applyCounterFilter);
                    }

                    var openBtn = box.querySelector('[data-sr-metrika-open]');
                    if (openBtn) {
                        openBtn.addEventListener('click', function () {
                            openForDomain(domainSelect ? domainSelect.value : '');
                        });
                    }

                    try {
                        if (restoreCreateState) {
                            var preDomain = restoreCreateState.domain || '';
                            var wantPicker = !!restoreCreateState.picker;
                            if (restoreCreateState.create && modal && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                                bootstrap.Modal.getOrCreateInstance(modal).show();
                            }
                            if (wantPicker) {
                                setTimeout(function () {
                                    openForDomain(preDomain || (domainSelect ? domainSelect.value : ''));
                                }, 450);
                            }
                            if (window.history && window.history.replaceState) {
                                var params = new URLSearchParams(window.location.search);
                                params.delete('sr_create');
                                params.delete('metrika_picker');
                                params.delete('metrika_domain');
                                params.delete('webmaster_picker');
                                params.delete('webmaster_domain');
                                params.delete('domain');
                                var q = params.toString();
                                window.history.replaceState({}, '', window.location.pathname + (q ? '?' + q : '') + window.location.hash);
                            }
                        }
                    } catch (e) {}
                })();

                (function initWebmasterPicker() {
                    var box = form.querySelector('[data-sr-webmaster]');
                    var modalEl = document.getElementById('cabinet-sr-webmaster-modal');
                    if (!box || !modalEl) return;

                    var csrfEl = document.querySelector('meta[name="csrf-token"]');
                    var csrfToken = csrfEl ? csrfEl.getAttribute('content') : '';
                    var currentDomain = '';
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
                            elevateNestedModal(modalEl);
                            bootstrap.Modal.getOrCreateInstance(modalEl).show();
                            return;
                        }
                        if (typeof $ !== 'undefined' && $.fn.modal) {
                            elevateNestedModal(modalEl);
                            $(modalEl).modal('show');
                        }
                    }

                    function connectUrl(domain) {
                        var base = box.getAttribute('data-webmaster-connect-url') || '';
                        var ret = box.getAttribute('data-webmaster-return') || location.href;
                        try {
                            var u = new URL(ret, window.location.origin);
                            u.searchParams.set('sr_create', '1');
                            u.searchParams.set('webmaster_picker', '1');
                            if (domain) {
                                u.searchParams.set('domain', domain);
                                u.searchParams.set('webmaster_domain', domain);
                            }
                            ret = u.pathname + u.search;
                        } catch (e) {}
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
                                '<br><span class="small opacity-75">' + meta + '</span></span>';
                            btn.addEventListener('click', function () {
                                bindHost(String(h.id));
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
                        currentDomain = domain || (domainSelect ? domainSelect.value : '') || '';
                        if (!currentDomain) {
                            step = 1;
                            render();
                            if (typeof window.jQuery !== 'undefined' && window.jQuery(domainSelect).data('select2')) {
                                window.jQuery(domainSelect).select2('open');
                            }
                            return;
                        }
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
                        showModal();

                        if (box.getAttribute('data-webmaster-configured') !== '1') {
                            setLoading(false);
                            setError(@json(__('Yandex Webmaster is not configured')));
                            return;
                        }

                        var bindingUrl = box.getAttribute('data-webmaster-binding-url') +
                            '?domain=' + encodeURIComponent(currentDomain);
                        fetch(bindingUrl, {
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                            credentials: 'same-origin',
                        })
                            .then(function (r) { return r.json(); })
                            .then(function (binding) {
                                if (binding && binding.connected === false) {
                                    setLoading(false);
                                    if (authEl) authEl.classList.remove('d-none');
                                    return null;
                                }
                                if (binding && binding.host_id) {
                                    selectedHostId = String(binding.host_id);
                                    if (currentEl) {
                                        currentEl.textContent = (binding.host_url || binding.host_id) +
                                            (binding.verified ? (' · ' + @json(__('Webmaster host verified'))) : '');
                                        currentEl.classList.remove('d-none');
                                    }
                                    if (unbindBtn) unbindBtn.classList.remove('d-none');
                                }
                                return fetch(box.getAttribute('data-webmaster-hosts-url'), {
                                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                                    credentials: 'same-origin',
                                });
                            })
                            .then(function (r) {
                                if (!r) return null;
                                return r.json();
                            })
                            .then(function (data) {
                                if (!data) return;
                                setLoading(false);
                                allHosts = Array.isArray(data.hosts) ? data.hosts : [];
                                setSearchVisible(allHosts.length > 8);
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
                                if (!data || !data.ok) throw new Error((data && data.message) || 'bind');
                                var ret = box.getAttribute('data-webmaster-return') || location.pathname;
                                try {
                                    var u = new URL(ret, window.location.origin);
                                    u.searchParams.set('sr_create', '1');
                                    if (currentDomain) u.searchParams.set('domain', currentDomain);
                                    ret = u.pathname + u.search;
                                } catch (e) {}
                                window.location.href = ret;
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
                                    window.location.reload();
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
                            openForDomain(domainSelect ? domainSelect.value : '');
                        });
                    }

                    try {
                        if (restoreCreateState && restoreCreateState.webmasterPicker) {
                            setTimeout(function () {
                                openForDomain(restoreCreateState.domain || (domainSelect ? domainSelect.value : ''));
                            }, 450);
                        }
                    } catch (e) {}
                })();
            })();
        </script>
    @endslot
@endcomponent
