<section class="cabinet-mon-v2-workspace" aria-label="{{ __('Monitoring v2 workspace title') }}">
    <header class="cabinet-mon-v2-workspace__head">
        <div>
            <h2 class="cabinet-mon-v2-workspace__title mb-0">{{ __('Monitoring v2 workspace title') }}</h2>
            <p class="cabinet-mon-v2-workspace__subtitle mb-0 text-secondary">{{ __('Monitoring v2 workspace subtitle') }}</p>
        </div>
        <div class="cabinet-mon-v2-workspace__head-actions">
            <a href="{{ route('monitoring.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-layout-text-sidebar me-1" aria-hidden="true"></i>{{ __('Monitoring v2 classic ui') }}
            </a>
            <a href="{{ route('monitoring.create') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>{{ __('Create project') }}
            </a>
        </div>
    </header>

    <div class="cabinet-mon-v2-workspace__toolbar">
        <div class="cabinet-mon-v2-workspace__search">
            <i class="bi bi-search" aria-hidden="true"></i>
            <input type="search"
                   id="cabinet-mon-v2-search"
                   class="form-control form-control-sm"
                   placeholder="{{ __('Monitoring v2 search placeholder') }}"
                   autocomplete="off"
                   @if($count < 1) disabled @endif>
        </div>
        <select id="cabinet-mon-v2-status-filter" class="form-select form-select-sm cabinet-mon-v2-workspace__filter" @if($count < 1) disabled @endif>
            <option value="">{{ __('Show all users status') }}</option>
            @foreach($statusOptions as $option)
                <option value="{{ $option['val'] }}">{{ $option['text'] }}</option>
            @endforeach
        </select>
        <span class="badge text-bg-secondary d-none" id="cabinet-mon-v2-selection-badge" @if($count < 1) hidden @endif>0</span>
        <button type="button" class="btn btn-outline-secondary btn-sm" id="cabinet-mon-v2-refresh" @if($count < 1) disabled @endif>
            <i class="bi bi-arrow-clockwise" aria-hidden="true"></i>
            <span class="d-none d-md-inline ms-1">{{ __('Monitoring v2 refresh list') }}</span>
        </button>
        <div class="dropdown cabinet-mon-v2-columns-dropdown" id="cabinet-mon-v2-columns-wrap" @if($count < 1) hidden @endif>
            <button type="button"
                    class="btn btn-outline-secondary btn-sm dropdown-toggle"
                    id="cabinet-mon-v2-columns-btn"
                    data-bs-toggle="dropdown"
                    data-bs-auto-close="outside"
                    aria-expanded="false">
                <i class="bi bi-layout-three-columns" aria-hidden="true"></i>
                <span class="d-none d-md-inline ms-1">{{ __('Monitoring v2 columns menu') }}</span>
            </button>
            <div class="dropdown-menu dropdown-menu-end cabinet-mon-v2-columns-menu p-2" id="cabinet-mon-v2-columns-menu" role="menu" aria-labelledby="cabinet-mon-v2-columns-btn"></div>
        </div>
        <div class="btn-group btn-group-sm cabinet-mon-v2-view-toggle ms-md-auto" role="group" @if($count < 1) hidden @endif>
            <button type="button" class="btn btn-outline-secondary active" id="cabinet-mon-v2-view-table" data-view="table">
                <i class="bi bi-table" aria-hidden="true"></i> {{ __('Monitoring v2 view table') }}
            </button>
            <button type="button" class="btn btn-outline-secondary" id="cabinet-mon-v2-view-cards" data-view="cards">
                <i class="bi bi-grid-3x3-gap" aria-hidden="true"></i> {{ __('Monitoring v2 view cards') }}
            </button>
        </div>
        <button type="button" class="btn btn-outline-secondary btn-sm" id="cabinet-mon-v2-select-all" @if($count < 1) disabled @endif>
            <i class="bi bi-check2-square me-1" aria-hidden="true"></i>{{ __('Select all') }}
        </button>
        <button type="button" class="btn btn-outline-danger btn-sm" id="cabinet-mon-v2-delete-selected" @if($count < 1) disabled @endif>
            <i class="bi bi-trash me-1" aria-hidden="true"></i>{{ __('Delete') }}
        </button>
    </div>

    <div class="alert alert-danger d-none" id="cabinet-mon-v2-load-error" role="alert"></div>

    <div class="cabinet-mon-v2-pending-invites d-none" id="cabinet-mon-v2-pending-invites" aria-live="polite">
        <div class="cabinet-mon-v2-pending-invites__head">
            <i class="bi bi-envelope-exclamation" aria-hidden="true"></i>
            <div>
                <p class="cabinet-mon-v2-pending-invites__title mb-0">{{ __('Monitoring v2 pending invites title') }}</p>
                <p class="cabinet-mon-v2-pending-invites__lead mb-0">{{ __('Monitoring v2 pending invites lead') }}</p>
            </div>
        </div>
        <ul class="cabinet-mon-v2-pending-invites__list mb-0" id="cabinet-mon-v2-pending-invites-list"></ul>
    </div>

    <div class="cabinet-mon-v2-progress d-none" id="cabinet-mon-v2-progress">
        <div class="progress" role="progressbar" aria-valuemin="0" aria-valuemax="100">
            <div class="progress-bar progress-bar-striped progress-bar-animated" style="width: 0%"></div>
        </div>
        <p class="small text-secondary mb-0 mt-1" id="cabinet-mon-v2-progress-label"></p>
    </div>

    @if($count < 1)
        <div class="cabinet-mon-v2-empty">
            <i class="bi bi-folder2-open display-6 text-secondary opacity-50 d-block mb-2" aria-hidden="true"></i>
            <p class="fw-semibold mb-1">{{ __('Monitoring v2 empty title') }}</p>
            <p class="text-secondary small mb-3">{{ __('Monitoring v2 empty hint') }}</p>
            <a href="{{ route('monitoring.create') }}" class="btn btn-primary btn-sm">{{ __('Create project') }}</a>
        </div>
    @else
        <div class="cabinet-mon-v2-table-panel" id="cabinet-mon-v2-table-panel">
            <div class="cabinet-mon-v2-table-scroll">
                <table class="table table-hover cabinet-mon-v2-table w-100 mb-0" id="cabinet-mon-v2-projects">
                    <thead>
                        <tr>
                            <th class="cabinet-mon-v2-table__col-expand" scope="col"></th>
                            <th scope="col">{{ __('Monitoring v2 project column') }}</th>
                            <th scope="col" class="text-end cabinet-mon-v2-table__col-top">{{ __('Monitoring v2 top col 3') }}</th>
                            <th scope="col" class="text-end cabinet-mon-v2-table__col-top">{{ __('Monitoring v2 top col 5') }}</th>
                            <th scope="col" class="text-end cabinet-mon-v2-table__col-top">{{ __('Monitoring v2 top col 10') }}</th>
                            <th scope="col" class="text-end cabinet-mon-v2-table__col-top">{{ __('Monitoring v2 top col 30') }}</th>
                            <th scope="col" class="text-end cabinet-mon-v2-table__col-top">{{ __('Monitoring v2 top col 100') }}</th>
                            <th scope="col">{{ __('Position') }}</th>
                            <th scope="col">{{ __('Words') }}</th>
                            <th scope="col">{{ __('Users') }}</th>
                            <th scope="col" class="text-center cabinet-mon-v2-table__col-engines" title="{{ __('Monitoring v2 engines column hint') }}">ПС</th>
                            <th scope="col">{{ __('Budget') }}</th>
                            <th scope="col">{{ __('Mastered') }}</th>
                            <th scope="col" class="text-end">{{ __('Monitoring v2 col actions') }}</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
            <div class="cabinet-mon-v2-table-footer text-secondary small" id="cabinet-mon-v2-table-info"></div>
        </div>

        <div class="cabinet-mon-v2-cards-panel d-none" id="cabinet-mon-v2-cards-panel">
            <div class="cabinet-mon-v2-skeleton" id="cabinet-mon-v2-skeleton" aria-hidden="false">
                @for($i = 0; $i < min(6, $count); $i++)
                    <div class="cabinet-mon-v2-card cabinet-mon-v2-card--skeleton" aria-hidden="true"></div>
                @endfor
            </div>
            <div class="cabinet-mon-v2-grid d-none" id="cabinet-mon-v2-grid"></div>
        </div>

        <p class="text-secondary small text-center d-none mb-0 mt-3" id="cabinet-mon-v2-no-results">
            {{ __('Monitoring v2 no search results') }}
        </p>
    @endif
</section>
