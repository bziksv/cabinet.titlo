@php
    $sitesPayload = $userSites ?? [];
    $sites = $sitesPayload['sites'] ?? [];
    $archivedSites = $sitesPayload['archived'] ?? [];
    $hiddenSites = $sitesPayload['hidden'] ?? [];
    $catalog = $sitesPayload['catalog'] ?? \App\Support\HomeUserSites::moduleCatalog();
    $sitesTotal = (int) ($sitesPayload['total'] ?? 0);
    $archivedTotal = (int) ($sitesPayload['archived_total'] ?? count($archivedSites));
    $hiddenTotal = (int) ($sitesPayload['hidden_total'] ?? count($hiddenSites));
    $sitesShown = (int) ($sitesPayload['shown'] ?? count($sites));
    $modulesTotal = (int) ($sitesPayload['modules_total'] ?? 0);
    if ($modulesTotal < 1) {
        foreach ($catalog as $catalogItem) {
            if (($catalogItem['kind'] ?? 'module') === 'module') {
                $modulesTotal++;
            }
        }
    }
    $visitsMeta = $sitesPayload['visits_meta'] ?? null;
    $visitsDeferred = !empty($sitesPayload['visits_deferred']);
    $columnPrefs = $sitesPayload['columns'] ?? \App\HomeUserSitesPreference::defaultColumns();
    $hasAnySites = $sitesTotal > 0 || $archivedTotal > 0 || $hiddenTotal > 0
        || count($sites) > 0 || count($archivedSites) > 0 || count($hiddenSites) > 0;

    $visitColumnsMeta = [
        ['key' => 'visits_today', 'label' => __('Visits today short'), 'title' => __('Visits today')],
        ['key' => 'visits_yesterday', 'label' => __('Visits yesterday short'), 'title' => __('Visits yesterday')],
        ['key' => 'visits_sum7', 'label' => __('Visits sum 7 short'), 'title' => __('Visits sum 7')],
        ['key' => 'visits_avg7', 'label' => __('Visits avg 7 short'), 'title' => __('Visits avg 7')],
        ['key' => 'visits_sum30', 'label' => __('Visits sum 30 short'), 'title' => __('Visits sum 30')],
        ['key' => 'visits_avg30', 'label' => __('Visits avg 30 short'), 'title' => __('Visits avg 30')],
    ];
@endphp

<section class="cabinet-home-sites mb-4" id="cabinet-home-sites" aria-labelledby="cabinet-home-sites-title"
         data-archive-url="{{ route('home.sites.archive') }}"
         data-hide-url="{{ route('home.sites.hide') }}"
         data-restore-url="{{ route('home.sites.restore') }}"
         data-columns-url="{{ route('home.sites.columns') }}"
         data-visits-url="{{ route('home.sites.visits') }}"
         data-visits-deferred="{{ $visitsDeferred ? '1' : '0' }}"
         data-columns='@json($columnPrefs)'
         data-metrika-connect-url="{{ route('yandex-metrika.connect') }}"
         data-metrika-status-url="{{ route('yandex-metrika.status') }}"
         data-metrika-counters-url="{{ route('yandex-metrika.counters') }}"
         data-metrika-binding-url="{{ route('yandex-metrika.binding') }}"
         data-metrika-bind-url="{{ route('yandex-metrika.bind') }}"
         data-metrika-unbind-url="{{ route('yandex-metrika.unbind') }}"
         data-metrika-return="{{ route('home') }}"
         data-webmaster-connect-url="{{ route('yandex-webmaster.connect') }}"
         data-webmaster-status-url="{{ route('yandex-webmaster.status') }}"
         data-webmaster-hosts-url="{{ route('yandex-webmaster.hosts') }}"
         data-webmaster-binding-url="{{ route('yandex-webmaster.binding') }}"
         data-webmaster-bind-url="{{ route('yandex-webmaster.bind') }}"
         data-webmaster-unbind-url="{{ route('yandex-webmaster.unbind') }}"
         data-webmaster-return="{{ route('home') }}"
         data-gsc-connect-url="{{ route('google-search-console.connect') }}"
         data-gsc-status-url="{{ route('google-search-console.status') }}"
         data-gsc-properties-url="{{ route('google-search-console.properties') }}"
         data-gsc-binding-url="{{ route('google-search-console.binding') }}"
         data-gsc-bind-url="{{ route('google-search-console.bind') }}"
         data-gsc-unbind-url="{{ route('google-search-console.unbind') }}"
         data-gsc-return="{{ route('home') }}">
    <div class="cabinet-home-sites__head">
        <button type="button"
                class="cabinet-home-sites__toggle"
                id="cabinet-home-sites-toggle"
                aria-expanded="true"
                aria-controls="cabinet-home-sites-body">
            <span class="cabinet-home-sites__toggle-main">
                <i class="bi bi-globe2 text-primary" aria-hidden="true"></i>
                <span>
                    <span class="cabinet-home-sites__title" id="cabinet-home-sites-title">{{ __('Your sites') }}</span>
                    <span class="badge text-bg-light text-body-secondary border ms-1" id="cabinet-home-sites-total-badge">{{ $sitesTotal }}</span>
                </span>
            </span>
            <span class="cabinet-home-sites__toggle-meta">
                <span class="text-secondary small d-none d-sm-inline">{{ __('Sites across modules') }}</span>
                <i class="bi bi-chevron-up cabinet-home-sites__chevron" aria-hidden="true"></i>
            </span>
        </button>
    </div>

    <div class="cabinet-home-sites__body" id="cabinet-home-sites-body">
        @if(!$hasAnySites)
            <div class="cabinet-home-sites-empty text-center text-secondary py-4 px-3">
                <i class="bi bi-globe display-6 d-block mb-2 opacity-50"></i>
                <p class="fw-semibold mb-1">{{ __('No sites yet') }}</p>
                <p class="small mb-0">{{ __('No sites yet hint') }}</p>
            </div>
        @else
            <div class="cabinet-home-sites-toolbar mb-3">
                <div class="cabinet-home-sites-visits-meta small text-secondary w-100 mb-2{{ $visitsDeferred ? '' : ((is_array($visitsMeta) && !empty($visitsMeta['as_of_human'])) ? '' : ' d-none') }}"
                     data-sites-visits-meta
                     @if($visitsDeferred) data-loading="1"@endif>
                    @if($visitsDeferred)
                        <span class="cabinet-home-sites-visits-loading" data-sites-visits-loading>
                            <span class="spinner-border spinner-border-sm text-secondary me-1" role="status" aria-hidden="true"></span>
                            {{ __('Metrika visits loading') }}
                        </span>
                        <span class="d-none" data-sites-visits-ready></span>
                    @elseif(is_array($visitsMeta) && !empty($visitsMeta['as_of_human']))
                        <i class="bi bi-graph-up-arrow me-1" aria-hidden="true"></i>
                        {{ __('Metrika visits as of', ['time' => $visitsMeta['as_of_human']]) }}
                        @if(!empty($visitsMeta['next_today_human']))
                            <span class="text-body-secondary">· {{ __('Metrika visits next today', ['time' => $visitsMeta['next_today_human']]) }}</span>
                        @endif
                    @endif
                </div>
                <div class="cabinet-home-sites-legend small text-secondary">
                    <button type="button"
                            class="cabinet-home-sites-legend__item cabinet-home-sites-legend__filter"
                            data-sites-filter-module="on"
                            aria-pressed="false"
                            title="{{ __('Show sites with module presence') }}">
                        <span class="cabinet-home-sites-dot cabinet-home-sites-dot--on" aria-hidden="true"></span>
                        {{ __('Site in module short') }}
                    </button>
                    <button type="button"
                            class="cabinet-home-sites-legend__item cabinet-home-sites-legend__filter"
                            data-sites-filter-module="off"
                            aria-pressed="false"
                            title="{{ __('Show sites with module gaps') }}">
                        <span class="cabinet-home-sites-dot cabinet-home-sites-dot--off" aria-hidden="true"></span>
                        {{ __('Site not in module short') }}
                    </button>
                    <button type="button"
                            class="cabinet-home-sites-legend__item cabinet-home-sites-legend__filter"
                            data-sites-filter-metrika="on"
                            aria-pressed="false"
                            title="{{ __('Show sites with Metrika') }}">
                        <span class="cabinet-home-sites-dot cabinet-home-sites-dot--sync-on" aria-hidden="true"></span>
                        {{ __('Metrika synced short') }}
                    </button>
                    <button type="button"
                            class="cabinet-home-sites-legend__item cabinet-home-sites-legend__filter"
                            data-sites-filter-metrika="off"
                            aria-pressed="false"
                            title="{{ __('Show sites without Metrika') }}">
                        <span class="cabinet-home-sites-dot cabinet-home-sites-dot--sync-off" aria-hidden="true"></span>
                        {{ __('Metrika not synced short') }}
                    </button>
                    <button type="button"
                            class="cabinet-home-sites-legend__item cabinet-home-sites-legend__filter"
                            data-sites-filter-webmaster="on"
                            aria-pressed="false"
                            title="{{ __('Show sites with Webmaster') }}">
                        <span class="cabinet-home-sites-dot cabinet-home-sites-dot--sync-on" aria-hidden="true"></span>
                        {{ __('Webmaster synced short') }}
                    </button>
                    <button type="button"
                            class="cabinet-home-sites-legend__item cabinet-home-sites-legend__filter"
                            data-sites-filter-webmaster="off"
                            aria-pressed="false"
                            title="{{ __('Show sites without Webmaster') }}">
                        <span class="cabinet-home-sites-dot cabinet-home-sites-dot--sync-off" aria-hidden="true"></span>
                        {{ __('Webmaster not synced short') }}
                    </button>
                    <button type="button"
                            class="cabinet-home-sites-legend__item cabinet-home-sites-legend__filter"
                            data-sites-filter-gsc="on"
                            aria-pressed="false"
                            title="{{ __('Show sites with GSC') }}">
                        <span class="cabinet-home-sites-dot cabinet-home-sites-dot--sync-on" aria-hidden="true"></span>
                        {{ __('GSC synced short') }}
                    </button>
                    <button type="button"
                            class="cabinet-home-sites-legend__item cabinet-home-sites-legend__filter"
                            data-sites-filter-gsc="off"
                            aria-pressed="false"
                            title="{{ __('Show sites without GSC') }}">
                        <span class="cabinet-home-sites-dot cabinet-home-sites-dot--sync-off" aria-hidden="true"></span>
                        {{ __('GSC not synced short') }}
                    </button>
                </div>
                <div class="cabinet-home-sites-toolbar__controls">
                    <div class="btn-group btn-group-sm flex-shrink-0" role="group" aria-label="{{ __('Sites list mode') }}">
                        <button type="button"
                                class="btn btn-outline-secondary active"
                                id="cabinet-home-sites-mode-active"
                                data-sites-mode="active">
                            {{ __('Active sites') }}
                            <span class="badge text-bg-light text-dark border ms-1" data-sites-active-count>{{ $sitesTotal }}</span>
                        </button>
                        <button type="button"
                                class="btn btn-outline-secondary"
                                id="cabinet-home-sites-mode-archive"
                                data-sites-mode="archive">
                            {{ __('Archive') }}
                            <span class="badge text-bg-light text-dark border ms-1" data-sites-archived-count>{{ $archivedTotal }}</span>
                        </button>
                        <button type="button"
                                class="btn btn-outline-secondary"
                                id="cabinet-home-sites-mode-hidden"
                                data-sites-mode="hidden">
                            {{ __('Hidden sites') }}
                            <span class="badge text-bg-light text-dark border ms-1" data-sites-hidden-count>{{ $hiddenTotal }}</span>
                        </button>
                    </div>
                    <div class="input-group input-group-sm cabinet-home-sites-toolbar__search">
                        <span class="input-group-text"><i class="bi bi-search" aria-hidden="true"></i></span>
                        <input type="search"
                               class="form-control"
                               id="cabinet-home-sites-search"
                               placeholder="{{ __('Find a site') }}…"
                               autocomplete="off"
                               aria-label="{{ __('Find a site') }}">
                    </div>
                    <div class="dropdown cabinet-home-sites-columns-dropdown">
                        <button type="button"
                                class="btn btn-outline-secondary btn-sm dropdown-toggle"
                                id="cabinet-home-sites-columns-btn"
                                data-bs-toggle="dropdown"
                                data-bs-auto-close="outside"
                                aria-expanded="false">
                            <i class="bi bi-layout-three-columns" aria-hidden="true"></i>
                            <span class="d-none d-md-inline ms-1">{{ __('Columns') }}</span>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end cabinet-home-sites-columns-menu p-2"
                             id="cabinet-home-sites-columns-menu"
                             role="menu"
                             aria-labelledby="cabinet-home-sites-columns-btn">
                            <p class="dropdown-header mb-1 px-2 py-0 small text-uppercase text-secondary">{{ __('Columns') }}</p>
                            @php $modulesCountKey = 'modules_count'; @endphp
                            <label class="dropdown-item-text d-flex align-items-center gap-2 py-1 px-2 mb-0"
                                   for="cabinet-home-sites-col-{{ $modulesCountKey }}">
                                <input type="checkbox"
                                       class="form-check-input m-0 cabinet-home-sites-col-toggle"
                                       id="cabinet-home-sites-col-{{ $modulesCountKey }}"
                                       data-col="{{ $modulesCountKey }}"
                                       @if(!empty($columnPrefs[$modulesCountKey])) checked @endif>
                                <span class="small" title="{{ __('Modules count near domain') }}">{{ __('Modules count short') }}</span>
                            </label>
                            @foreach($visitColumnsMeta as $visitCol)
                                @php $colKey = $visitCol['key']; @endphp
                                <label class="dropdown-item-text d-flex align-items-center gap-2 py-1 px-2 mb-0"
                                       for="cabinet-home-sites-col-{{ $colKey }}">
                                    <input type="checkbox"
                                           class="form-check-input m-0 cabinet-home-sites-col-toggle"
                                           id="cabinet-home-sites-col-{{ $colKey }}"
                                           data-col="{{ $colKey }}"
                                           @if(!empty($columnPrefs[$colKey])) checked @endif>
                                    <span class="small" title="{{ $visitCol['title'] }}">{{ $visitCol['label'] }}</span>
                                </label>
                            @endforeach
                            @foreach($catalog as $catalogItem)
                                @php $colKey = 'mod-' . $catalogItem['key']; @endphp
                                <label class="dropdown-item-text d-flex align-items-center gap-2 py-1 px-2 mb-0"
                                       for="cabinet-home-sites-col-{{ $colKey }}">
                                    <input type="checkbox"
                                           class="form-check-input m-0 cabinet-home-sites-col-toggle"
                                           id="cabinet-home-sites-col-{{ $colKey }}"
                                           data-col="{{ $colKey }}"
                                           @if(!empty($columnPrefs[$colKey])) checked @endif>
                                    <span class="small" title="{{ $catalogItem['title'] }}">{{ $catalogItem['short'] }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            @foreach([
                ['key' => 'active', 'rows' => $sites, 'mode' => 'active', 'empty' => __('No active sites'), 'icon' => 'globe'],
                ['key' => 'archive', 'rows' => $archivedSites, 'mode' => 'archive', 'empty' => __('Archive is empty'), 'icon' => 'archive'],
                ['key' => 'hidden', 'rows' => $hiddenSites, 'mode' => 'hidden', 'empty' => __('Hidden list is empty'), 'icon' => 'eye-slash'],
            ] as $panel)
                <div class="cabinet-home-sites-panel {{ $panel['key'] === 'active' ? '' : 'd-none' }}"
                     data-sites-panel="{{ $panel['key'] }}">
                    @if(count($panel['rows']) === 0)
                        <div class="cabinet-home-sites-empty text-center text-secondary py-4 px-3">
                            <i class="bi bi-{{ $panel['icon'] }} display-6 d-block mb-2 opacity-50"></i>
                            <p class="mb-0">{{ $panel['empty'] }}</p>
                        </div>
                    @else
                        <div class="cabinet-home-sites-table-wrap">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0 cabinet-home-sites-table">
                                    <thead>
                                    <tr>
                                        <th scope="col"
                                            class="cabinet-home-sites-col-domain cabinet-home-sites-th-sort"
                                            data-sites-sort="domain"
                                            title="{{ __('Sort by column') }}">
                                            <button type="button" class="cabinet-home-sites-sort-btn">
                                                <span>{{ __('Domain') }}</span>
                                                <i class="bi bi-arrow-down-up" aria-hidden="true"></i>
                                            </button>
                                        </th>
                                        @foreach($visitColumnsMeta as $visitCol)
                                            @php $colVisible = !empty($columnPrefs[$visitCol['key']]); @endphp
                                            <th scope="col"
                                                class="cabinet-home-sites-col-visits text-end cabinet-home-sites-th-sort{{ $colVisible ? '' : ' is-col-hidden' }}"
                                                data-col="{{ $visitCol['key'] }}"
                                                data-sites-sort="{{ $visitCol['key'] }}"
                                                title="{{ $visitCol['title'] }}">
                                                <button type="button" class="cabinet-home-sites-sort-btn">
                                                    <span class="cabinet-home-sites-th-text">{{ $visitCol['label'] }}</span>
                                                    <i class="bi bi-arrow-down-up" aria-hidden="true"></i>
                                                </button>
                                            </th>
                                        @endforeach
                                        @foreach($catalog as $catalogItem)
                                            @php
                                                $isIntegration = ($catalogItem['kind'] ?? 'module') === 'integration';
                                                $supportsSync = (bool) ($catalogItem['supports_sync'] ?? false);
                                                $modColKey = 'mod-' . $catalogItem['key'];
                                                $colVisible = !empty($columnPrefs[$modColKey]);
                                            @endphp
                                            <th scope="col"
                                                class="cabinet-home-sites-col-mod text-center cabinet-home-sites-th-sort {{ $isIntegration ? 'cabinet-home-sites-col-mod--integration' : '' }} {{ $supportsSync ? 'cabinet-home-sites-col-mod--sync' : '' }}{{ $colVisible ? '' : ' is-col-hidden' }}"
                                                data-col="{{ $modColKey }}"
                                                data-sites-sort="mod-{{ $catalogItem['key'] }}"
                                                title="{{ $catalogItem['title'] }}{{ $supportsSync ? (' — ' . __('Metrika sync planned')) : '' }}">
                                                <button type="button" class="cabinet-home-sites-sort-btn">
                                                    <span class="cabinet-home-sites-th-label">
                                                        <span class="cabinet-home-sites-th-text">{{ $catalogItem['short'] }}</span>
                                                        @if($supportsSync)
                                                            <i class="bi bi-arrow-repeat cabinet-home-sites-sync-mark"
                                                               title="{{ __('Metrika sync planned') }}"
                                                               aria-hidden="true"></i>
                                                        @endif
                                                    </span>
                                                    <i class="bi bi-arrow-down-up" aria-hidden="true"></i>
                                                </button>
                                            </th>
                                        @endforeach
                                        <th scope="col" class="cabinet-home-sites-col-actions text-end">{{ __('Actions') }}</th>
                                    </tr>
                                    </thead>
                                    <tbody class="cabinet-home-sites-tbody">
                                    @foreach($panel['rows'] as $site)
                                        @php
                                            $metrikaSynced = false;
                                            $webmasterSynced = false;
                                            $gscSynced = false;
                                            $modSort = [];
                                            foreach (($site['matrix'] ?? []) as $matrixCell) {
                                                $mk = (string) ($matrixCell['key'] ?? '');
                                                if (($matrixCell['kind'] ?? '') === 'integration') {
                                                    $modSort[$mk] = !empty($matrixCell['synced']) ? 2 : 0;
                                                } else {
                                                    $modSort[$mk] = !empty($matrixCell['present']) ? 1 : 0;
                                                    if (!empty($matrixCell['supports_sync']) && !empty($matrixCell['synced'])) {
                                                        $modSort[$mk] = 2;
                                                    }
                                                }
                                                if ($mk === 'yandex-metrika') {
                                                    $metrikaSynced = !empty($matrixCell['synced']);
                                                }
                                                if ($mk === 'yandex-webmaster') {
                                                    $webmasterSynced = !empty($matrixCell['synced']);
                                                }
                                                if ($mk === 'google-search-console') {
                                                    $gscSynced = !empty($matrixCell['synced']);
                                                }
                                            }
                                            $visits = $site['visits'] ?? null;
                                            $vToday = is_array($visits) && array_key_exists('today', $visits) ? $visits['today'] : null;
                                            $vYesterday = is_array($visits) && array_key_exists('yesterday', $visits) ? $visits['yesterday'] : null;
                                            $vSum7 = is_array($visits) && array_key_exists('sum_7', $visits) ? $visits['sum_7'] : null;
                                            $vAvg7 = is_array($visits) && array_key_exists('avg_7', $visits) ? $visits['avg_7'] : null;
                                            $vSum30 = is_array($visits) && array_key_exists('sum_30', $visits) ? $visits['sum_30'] : null;
                                            $vAvg30 = is_array($visits) && array_key_exists('avg_30', $visits) ? $visits['avg_30'] : null;
                                        @endphp
                                        <tr data-cabinet-site-domain="{{ $site['domain'] }}"
                                            data-cabinet-site-mode="{{ $panel['mode'] }}"
                                            data-metrika-synced="{{ $metrikaSynced ? '1' : '0' }}"
                                            data-webmaster-synced="{{ $webmasterSynced ? '1' : '0' }}"
                                            data-gsc-synced="{{ $gscSynced ? '1' : '0' }}"
                                            data-sort-domain="{{ $site['domain'] }}"
                                            data-sort-visits_today="{{ $vToday === null ? '' : $vToday }}"
                                            data-sort-visits_yesterday="{{ $vYesterday === null ? '' : $vYesterday }}"
                                            data-sort-visits_sum7="{{ $vSum7 === null ? '' : $vSum7 }}"
                                            data-sort-visits_avg7="{{ $vAvg7 === null ? '' : $vAvg7 }}"
                                            data-sort-visits_sum30="{{ $vSum30 === null ? '' : $vSum30 }}"
                                            data-sort-visits_avg30="{{ $vAvg30 === null ? '' : $vAvg30 }}"
                                            @foreach($modSort as $modKey => $modVal) data-sort-mod-{{ $modKey }}="{{ $modVal }}" @endforeach
                                            data-sort-modules="{{ (int) ($site['modules_count'] ?? 0) }}"
                                            data-modules-total="{{ (int) ($site['modules_total'] ?? $modulesTotal) }}">
                                            <td class="cabinet-home-sites-domain">
                                                <a href="https://{{ $site['domain'] }}"
                                                   class="cabinet-home-sites-domain__host"
                                                   target="_blank"
                                                   rel="noopener noreferrer">{{ $site['domain'] }}</a>
                                                <span class="badge text-bg-light border ms-1 cabinet-home-sites-modules-badge{{ !empty($columnPrefs['modules_count']) ? '' : ' is-col-hidden' }}"
                                                      data-col="modules_count"
                                                      title="{{ __('Modules count near domain') }}">{{ $site['modules_count'] }}/{{ $site['modules_total'] ?? $modulesTotal }}</span>
                                            </td>
                                            @foreach([
                                                ['key' => 'visits_today', 'value' => $vToday],
                                                ['key' => 'visits_yesterday', 'value' => $vYesterday],
                                                ['key' => 'visits_sum7', 'value' => $vSum7],
                                                ['key' => 'visits_avg7', 'value' => $vAvg7],
                                                ['key' => 'visits_sum30', 'value' => $vSum30],
                                                ['key' => 'visits_avg30', 'value' => $vAvg30],
                                            ] as $visitCell)
                                                @php
                                                    $visitValue = $visitCell['value'];
                                                    $colVisible = !empty($columnPrefs[$visitCell['key']]);
                                                @endphp
                                                <td class="cabinet-home-sites-col-visits text-end text-nowrap{{ $colVisible ? '' : ' is-col-hidden' }}"
                                                    data-col="{{ $visitCell['key'] }}"
                                                    data-visit-key="{{ $visitCell['key'] }}">
                                                    @if($visitValue === null && $visitsDeferred && $metrikaSynced)
                                                        <span class="cabinet-home-sites-visit-skel" aria-hidden="true"></span>
                                                    @elseif($visitValue === null)
                                                        <span class="text-secondary">—</span>
                                                    @else
                                                        {{ number_format((int) round((float) $visitValue), 0, ',', ' ') }}
                                                    @endif
                                                </td>
                                            @endforeach
                                            @foreach($site['matrix'] as $cell)
                                                @php
                                                    $isIntegration = ($cell['kind'] ?? 'module') === 'integration';
                                                    $supportsSync = (bool) ($cell['supports_sync'] ?? false);
                                                    $modColKey = 'mod-' . ($cell['key'] ?? '');
                                                    $colVisible = !empty($columnPrefs[$modColKey]);
                                                    if ($isIntegration) {
                                                        $dotClass = !empty($cell['synced'])
                                                            ? 'cabinet-home-sites-dot--sync-on'
                                                            : 'cabinet-home-sites-dot--sync-off';
                                                        if (($cell['key'] ?? '') === 'yandex-webmaster') {
                                                            $statusText = !empty($cell['synced'])
                                                                ? __('Webmaster synced')
                                                                : __('Webmaster not synced');
                                                        } elseif (($cell['key'] ?? '') === 'google-search-console') {
                                                            $statusText = !empty($cell['synced'])
                                                                ? __('GSC synced')
                                                                : __('GSC not synced');
                                                        } else {
                                                            $statusText = !empty($cell['synced'])
                                                                ? __('Metrika synced')
                                                                : __('Metrika not synced');
                                                        }
                                                    } else {
                                                        $dotClass = !empty($cell['present'])
                                                            ? 'cabinet-home-sites-dot--on'
                                                            : 'cabinet-home-sites-dot--off';
                                                        $statusText = !empty($cell['present'])
                                                            ? __('Site in module')
                                                            : __('Site not in module');
                                                        if ($supportsSync && !empty($cell['present'])) {
                                                            $statusText .= !empty($cell['synced'])
                                                                ? (' · ' . __('Metrika synced'))
                                                                : (' · ' . __('Metrika sync planned'));
                                                        }
                                                    }
                                                @endphp
                                                <td class="text-center cabinet-home-sites-col-mod {{ $isIntegration ? 'cabinet-home-sites-col-mod--integration' : '' }}{{ $colVisible ? '' : ' is-col-hidden' }}"
                                                    data-col="{{ $modColKey }}">
                                                    @if(($cell['key'] ?? '') === 'yandex-metrika')
                                                        <button type="button"
                                                                class="cabinet-home-sites-dot {{ $dotClass }}"
                                                                data-cabinet-metrika-dot
                                                                data-domain="{{ $site['domain'] }}"
                                                                data-counter-id="{{ (int) ($cell['counter_id'] ?? 0) }}"
                                                                data-synced="{{ !empty($cell['synced']) ? '1' : '0' }}"
                                                                title="{{ $cell['title'] }} — {{ $statusText }}{{ $cell['label'] !== '' ? (': '.$cell['label']) : '' }}">
                                                            <span class="visually-hidden">{{ $cell['short'] }}</span>
                                                        </button>
                                                    @elseif(($cell['key'] ?? '') === 'yandex-webmaster')
                                                        <button type="button"
                                                                class="cabinet-home-sites-dot {{ $dotClass }}"
                                                                data-cabinet-webmaster-dot
                                                                data-domain="{{ $site['domain'] }}"
                                                                data-host-id="{{ $cell['host_id'] ?? '' }}"
                                                                data-synced="{{ !empty($cell['synced']) ? '1' : '0' }}"
                                                                title="{{ $cell['title'] }} — {{ $statusText }}{{ $cell['label'] !== '' ? (': '.$cell['label']) : '' }}">
                                                            <span class="visually-hidden">{{ $cell['short'] }}</span>
                                                        </button>
                                                    @elseif(($cell['key'] ?? '') === 'google-search-console')
                                                        <button type="button"
                                                                class="cabinet-home-sites-dot {{ $dotClass }}"
                                                                data-cabinet-gsc-dot
                                                                data-domain="{{ $site['domain'] }}"
                                                                data-property-id="{{ $cell['property_id'] ?? '' }}"
                                                                data-synced="{{ !empty($cell['synced']) ? '1' : '0' }}"
                                                                title="{{ $cell['title'] }} — {{ $statusText }}{{ $cell['label'] !== '' ? (': '.$cell['label']) : '' }}">
                                                            <span class="visually-hidden">{{ $cell['short'] }}</span>
                                                        </button>
                                                    @else
                                                        <a href="{{ $cell['url'] }}"
                                                           class="cabinet-home-sites-dot {{ $dotClass }} {{ ($supportsSync && !$isIntegration && !empty($cell['present']) && empty($cell['synced'])) ? 'cabinet-home-sites-dot--sync-ready' : '' }}"
                                                           title="{{ $cell['title'] }} — {{ $statusText }}{{ $cell['label'] !== '' ? (': '.$cell['label']) : '' }}">
                                                            <span class="visually-hidden">{{ $cell['short'] }}</span>
                                                        </a>
                                                    @endif
                                                </td>
                                            @endforeach
                                            <td class="text-end cabinet-home-sites-col-actions">
                                                @if($panel['mode'] === 'active')
                                                    <div class="btn-group btn-group-sm" role="group">
                                                        <button type="button"
                                                                class="btn btn-outline-secondary"
                                                                data-cabinet-site-hide
                                                                data-bs-toggle="tooltip"
                                                                data-bs-placement="top"
                                                                data-bs-container="body"
                                                                title="{{ __('Hide site') }}"
                                                                aria-label="{{ __('Hide site') }}">
                                                            <i class="bi bi-eye-slash" aria-hidden="true"></i>
                                                            <span class="d-none d-xxl-inline">{{ __('Hide') }}</span>
                                                        </button>
                                                        <button type="button"
                                                                class="btn btn-outline-secondary"
                                                                data-cabinet-site-archive
                                                                data-bs-toggle="tooltip"
                                                                data-bs-placement="top"
                                                                data-bs-container="body"
                                                                title="{{ __('Move to archive') }}"
                                                                aria-label="{{ __('Move to archive') }}">
                                                            <i class="bi bi-archive" aria-hidden="true"></i>
                                                            <span class="d-none d-xxl-inline">{{ __('To archive') }}</span>
                                                        </button>
                                                    </div>
                                                @else
                                                    <button type="button"
                                                            class="btn btn-sm btn-outline-primary"
                                                            data-cabinet-site-restore
                                                            data-bs-toggle="tooltip"
                                                            data-bs-placement="top"
                                                            data-bs-container="body"
                                                            title="{{ __('Restore site') }}"
                                                            aria-label="{{ __('Restore site') }}">
                                                        <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>
                                                        <span class="d-none d-xl-inline">{{ __('Restore') }}</span>
                                                    </button>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                </div>
            @endforeach

            <div id="cabinet-home-sites-filter-empty" class="cabinet-home-sites-empty text-center text-secondary py-4 px-3 d-none">
                <i class="bi bi-search display-6 d-block mb-2 opacity-50"></i>
                <p class="mb-0">{{ __('No sites match your search.') }}</p>
            </div>

            <div class="cabinet-home-sites-pager mt-3 d-none" data-sites-pager data-page-size="50">
                <p class="cabinet-home-sites-pager__info text-secondary small mb-0" data-sites-pager-info></p>
                <div class="cabinet-home-sites-pager__nav btn-group btn-group-sm" role="group" aria-label="{{ __('Sites pagination') }}">
                    <button type="button" class="btn btn-outline-secondary" data-sites-page="prev" disabled>
                        <i class="bi bi-chevron-left" aria-hidden="true"></i>
                        <span class="d-none d-sm-inline">{{ __('Previous') }}</span>
                    </button>
                    <span class="btn btn-outline-secondary disabled px-3" data-sites-pager-label>—</span>
                    <button type="button" class="btn btn-outline-secondary" data-sites-page="next" disabled>
                        <span class="d-none d-sm-inline">{{ __('Next') }}</span>
                        <i class="bi bi-chevron-right" aria-hidden="true"></i>
                    </button>
                </div>
            </div>

            @if($sitesTotal > $sitesShown)
                <p class="text-secondary small mt-2 mb-0" data-sites-active-hint>
                    {{ __('Showing sites of', ['shown' => $sitesShown, 'total' => $sitesTotal]) }}
                    · {{ __('Sites list hard limit hint') }}
                </p>
            @endif
        @endif
    </div>

    <div class="modal fade" id="cabinet-sites-confirm-modal" tabindex="-1" aria-labelledby="cabinet-sites-confirm-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="cabinet-sites-confirm-title">{{ __('Confirm') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0" data-sites-confirm-text></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                    <button type="button" class="btn btn-primary" data-sites-confirm-ok>{{ __('Confirm') }}</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="cabinet-metrika-modal" tabindex="-1" aria-labelledby="cabinet-metrika-modal-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="cabinet-metrika-modal-title">{{ __('Yandex Metrika') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-secondary mb-2">
                        {{ __('Choose Metrika counter for domain') }}:
                        <strong data-metrika-domain-label>—</strong>
                    </p>
                    <div data-metrika-current class="alert alert-light border py-2 px-3 small d-none mb-3"></div>
                    <div data-metrika-loading class="text-secondary small py-3 d-none">{{ __('Loading counters') }}…</div>
                    <div data-metrika-error class="alert alert-danger py-2 px-3 small d-none"></div>
                    <div data-metrika-auth class="text-center py-3 d-none">
                        <p class="mb-3">{{ __('Connect Yandex Metrika to pick a counter') }}</p>
                        <a href="#" class="btn btn-primary" data-metrika-auth-link>
                            <i class="bi bi-box-arrow-in-right me-1" aria-hidden="true"></i>
                            {{ __('Authorize Yandex Metrika') }}
                        </a>
                    </div>
                    <div data-metrika-search-wrap class="mb-2 d-none">
                        <input type="search"
                               class="form-control form-control-sm"
                               data-metrika-search
                               placeholder="{{ __('Search by site or counter ID') }}"
                               autocomplete="off">
                    </div>
                    <div class="list-group list-group-flush border rounded" data-metrika-list style="max-height: 22rem; overflow: auto;"></div>
                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-outline-danger btn-sm d-none" data-metrika-unbind>
                        {{ __('Unbind counter') }}
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Close') }}</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="cabinet-webmaster-modal" tabindex="-1" aria-labelledby="cabinet-webmaster-modal-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="cabinet-webmaster-modal-title">{{ __('Yandex Webmaster') }}</h5>
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

    <div class="modal fade" id="cabinet-gsc-modal" tabindex="-1" aria-labelledby="cabinet-gsc-modal-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="cabinet-gsc-modal-title">{{ __('Google Search Console') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-secondary mb-2">
                        {{ __('Choose GSC property for domain') }}:
                        <strong data-gsc-domain-label>—</strong>
                    </p>
                    <div data-gsc-current class="alert alert-light border py-2 px-3 small d-none mb-3"></div>
                    <div data-gsc-loading class="text-secondary small py-3 d-none">{{ __('Loading GSC properties') }}…</div>
                    <div data-gsc-error class="alert alert-danger py-2 px-3 small d-none"></div>
                    <div data-gsc-auth class="text-center py-3 d-none">
                        <p class="mb-3">{{ __('Connect Google Search Console to pick a property') }}</p>
                        <a href="#" class="btn btn-primary" data-gsc-auth-link>
                            <i class="bi bi-box-arrow-in-right me-1" aria-hidden="true"></i>
                            {{ __('Authorize Google Search Console') }}
                        </a>
                    </div>
                    <div data-gsc-search-wrap class="mb-2 d-none">
                        <input type="search"
                               class="form-control form-control-sm"
                               data-gsc-search
                               placeholder="{{ __('Search by site or property ID') }}"
                               autocomplete="off">
                    </div>
                    <div class="list-group list-group-flush border rounded" data-gsc-list style="max-height: 22rem; overflow: auto;"></div>
                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-outline-danger btn-sm d-none" data-gsc-unbind>
                        {{ __('Unbind GSC property') }}
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Close') }}</button>
                </div>
            </div>
        </div>
    </div>
</section>
