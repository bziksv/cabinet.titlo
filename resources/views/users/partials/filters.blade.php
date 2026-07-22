<div class="card shadow-sm mb-3 cabinet-users-filters">
    <div class="card-header py-2">
        <button class="btn btn-link text-decoration-none p-0 fw-semibold text-body d-flex align-items-center gap-1"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#cabinet-users-filters-collapse"
                aria-expanded="true">
            <i class="bi bi-funnel"></i>{{ __('Filters') }}
            <span class="badge text-bg-primary d-none" id="cabinet-users-filters-active">0</span>
        </button>
    </div>
    <div id="cabinet-users-filters-collapse" class="collapse show">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12 col-md-6 col-lg-4">
                    <label class="form-label small mb-1" for="filter-verify">{{ __('Email verification') }}</label>
                    <select class="form-select form-select-sm" id="filter-verify" data-filter>
                        <option value="">{{ __('Any') }}</option>
                        <option value="verified">{{ __('Verified user') }}</option>
                        <option value="unverified">{{ __('No verified user') }}</option>
                    </select>
                </div>
                <div class="col-12 col-md-6 col-lg-4">
                    <label class="form-label small mb-1" for="filter-role">{{ __('Role') }}</label>
                    <select class="form-select form-select-sm" id="filter-role" data-filter>
                        <option value="">{{ __('Any role') }}</option>
                        @foreach($roles as $roleName => $roleLabel)
                            <option value="{{ $roleName }}">{{ $roleLabel }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-6 col-lg-4">
                    <label class="form-label small mb-1" for="filter-active-tariffs">{{ __('Active tariff') }}</label>
                    <select class="form-select form-select-sm cabinet-users-filter-tariffs"
                            id="filter-active-tariffs"
                            name="filter_active_tariffs[]"
                            multiple
                            data-filter="multi"
                            data-placeholder="{{ __('Users active tariff filter placeholder') }}">
                        @foreach($tariffSelect['tariff'] as $code => $name)
                            <option value="{{ $code }}">{{ $name }}</option>
                        @endforeach
                        <option value="Free">{{ __('Free') }}</option>
                        <option value="__none__">{{ __('No active tariff') }}</option>
                        <option value="__no_role__">{{ __('Users tariff filter no role') }}</option>
                    </select>
                </div>
                <div class="col-12 col-md-6 col-lg-4">
                    <label class="form-label small mb-1" for="filter-online">{{ __('Was online') }}</label>
                    <select class="form-select form-select-sm" id="filter-online" data-filter>
                        <option value="">{{ __('Any time') }}</option>
                        <option value="7d">{{ __('Last 7 days') }}</option>
                        <option value="30d">{{ __('Last 30 days') }}</option>
                        <option value="inactive30d">{{ __('Inactive over 30 days') }}</option>
                        <option value="inactive180d">{{ __('Inactive over 180 days') }}</option>
                        <option value="inactive360d">{{ __('Inactive over 360 days') }}</option>
                        <option value="inactive2y">{{ __('Inactive over 2 years') }}</option>
                        <option value="never">{{ __('Never') }}</option>
                    </select>
                </div>
                <div class="col-12 col-md-6 col-lg-4">
                    <label class="form-label small mb-1" for="filter-telegram">{{ __('Telegram') }}</label>
                    <select class="form-select form-select-sm" id="filter-telegram" data-filter>
                        <option value="">{{ __('Any') }}</option>
                        <option value="1">{{ __('Telegram connected') }}</option>
                        <option value="0">{{ __('Telegram not connected') }}</option>
                    </select>
                </div>
                <div class="col-12 col-md-6 col-lg-4">
                    <label class="form-label small mb-1" for="filter-statistic">{{ __('Visit statistics') }}</label>
                    <select class="form-select form-select-sm" id="filter-statistic" data-filter>
                        <option value="">{{ __('Any') }}</option>
                        <option value="1">{{ __('Statistics enabled') }}</option>
                        <option value="0">{{ __('Statistics disabled') }}</option>
                    </select>
                </div>
                <div class="col-12 col-md-6 col-lg-4">
                    <label class="form-label small mb-1">{{ __('User ID range') }}</label>
                    <div class="input-group input-group-sm">
                        <input type="number"
                               class="form-control"
                               id="filter-id-from"
                               data-filter
                               placeholder="{{ __('From') }}"
                               min="1">
                        <span class="input-group-text">—</span>
                        <input type="number"
                               class="form-control"
                               id="filter-id-to"
                               data-filter
                               placeholder="{{ __('To') }}"
                               min="1">
                    </div>
                </div>
                <div class="col-12 col-md-6 col-lg-4">
                    <label class="form-label small mb-1" for="filter-stale-monitoring">{{ __('Users stale monitoring filter label') }}</label>
                    <select class="form-select form-select-sm" id="filter-stale-monitoring" data-filter>
                        <option value="">{{ __('Any') }}</option>
                        <option value="1">{{ __('Users stale monitoring filter yes') }}</option>
                    </select>
                </div>
                <div class="col-12 col-md-6 col-lg-4 cabinet-users-filters__actions-col">
                    <div class="cabinet-users-filters__actions d-flex flex-wrap gap-2">
                        <button type="button" class="btn btn-sm btn-primary" id="cabinet-users-filters-apply">
                            <i class="bi bi-check-lg me-1"></i>{{ __('Apply filters') }}
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="cabinet-users-filters-reset">
                            {{ __('Reset') }}
                        </button>
                    </div>
                </div>
                <div class="col-12">
                    <div class="border border-danger-subtle rounded-2 p-2 bg-danger-subtle">
                        <div class="small text-secondary mb-2">
                            <i class="bi bi-exclamation-triangle me-1 text-danger"></i>
                            {{ __('Users inactive purge help') }}
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <button type="button"
                                    class="btn btn-sm btn-outline-danger"
                                    id="cabinet-users-purge-2y"
                                    data-years="2">
                                <i class="bi bi-trash me-1"></i>{{ __('Users inactive purge 2 years') }}
                            </button>
                            <button type="button"
                                    class="btn btn-sm btn-outline-danger"
                                    id="cabinet-users-purge-3y"
                                    data-years="3">
                                <i class="bi bi-trash me-1"></i>{{ __('Users inactive purge 3 years') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
