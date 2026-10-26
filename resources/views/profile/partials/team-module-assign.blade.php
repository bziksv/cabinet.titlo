@php
    $returnTo = 'profile';
    $teams = $teams ?? collect();
    $authUserId = (int) ($authUserId ?? auth()->id());
    $checklistProjects = $checklistProjects ?? collect();
    $seoReportProjects = $seoReportProjects ?? collect();
    $siteAuditProjects = $siteAuditProjects ?? collect();
    $rows = collect();
    foreach ($checklistProjects as $p) {
        $rows->push([
            'module' => __('SEO Checklist'),
            'module_class' => 'checklist',
            'domain' => $p->domain,
            'url' => route('pages.seo-checklist.show', ['id' => $p->id]),
            'action' => route('pages.seo-checklist.assign-team', ['id' => $p->id]),
            'team_id' => (int) ($p->team_id ?? 0),
            'team_title' => optional($p->team)->title,
            'search' => mb_strtolower(trim($p->domain . ' checklist ' . (optional($p->team)->title ?? ''))),
        ]);
    }
    foreach ($seoReportProjects as $p) {
        $rows->push([
            'module' => __('SEO Reports'),
            'module_class' => 'reports',
            'domain' => $p->domain,
            'url' => route('pages.seo-reports.show', ['id' => $p->id]),
            'action' => route('pages.seo-reports.assign-team', ['id' => $p->id]),
            'team_id' => (int) ($p->team_id ?? 0),
            'team_title' => optional($p->team)->title,
            'search' => mb_strtolower(trim($p->domain . ' reports ' . (optional($p->team)->title ?? ''))),
        ]);
    }
    foreach ($siteAuditProjects as $p) {
        $rows->push([
            'module' => __('Site audit'),
            'module_class' => 'audit',
            'domain' => $p->domain,
            'url' => route('pages.site-audit'),
            'action' => route('pages.site-audit.project.team', ['id' => $p->id]),
            'team_id' => (int) ($p->team_id ?? 0),
            'team_title' => optional($p->team)->title,
            'search' => mb_strtolower(trim($p->domain . ' audit ' . (optional($p->team)->title ?? ''))),
        ]);
    }
    $rows = $rows->sortBy('domain')->values();
@endphp

@if($rows->isNotEmpty() && $teams->isNotEmpty())
    <div class="cabinet-sc-section-label mt-3">
        <span>{{ __('Connect team to modules') }}</span>
        <span class="cabinet-sc-section-label__count">{{ $rows->count() }}</span>
    </div>
    <div class="cabinet-sc-panel cabinet-sc-panel--assign" data-sc-assign-panel>
        <div class="cabinet-sc-panel__head">
            <p class="small text-secondary mb-0">{{ __('Profile team modules hint') }}</p>
            <div class="cabinet-sc-toolbar cabinet-sc-toolbar--compact">
                <input type="search"
                       class="form-control form-control-sm cabinet-sc-search"
                       placeholder="{{ __('Search projects') }}…"
                       data-sc-assign-project-search
                       autocomplete="off">
            </div>
        </div>
        <div class="cabinet-sc-assign-list" data-sc-assign-list>
            @foreach($rows as $row)
                <form method="post"
                      action="{{ $row['action'] }}"
                      class="cabinet-sc-assign-row cabinet-sc-assign-row--team"
                      data-sc-assign-row
                      data-search="{{ e($row['search']) }}">
                    @csrf
                    <input type="hidden" name="return_to" value="{{ $returnTo }}">
                    <div class="cabinet-sc-assign-row__domain">
                        <a href="{{ $row['url'] }}">{{ $row['domain'] }}</a>
                        <span class="cabinet-sc-role cabinet-sc-role--shared">{{ $row['module'] }}</span>
                        @if($row['team_title'])
                            <span class="cabinet-sc-role cabinet-sc-role--any">{{ $row['team_title'] }}</span>
                        @else
                            <span class="cabinet-sc-role cabinet-sc-role--shared">{{ __('No team') }}</span>
                        @endif
                    </div>
                    <label>
                        <span>{{ __('Team') }}</span>
                        <select name="team_id" class="form-select form-select-sm">
                            <option value="0">{{ __('No team') }}</option>
                            @foreach($teams as $team)
                                <option value="{{ $team->id }}" @if($row['team_id'] === (int) $team->id) selected @endif>
                                    {{ $team->title }}@if((int) $team->user_id !== $authUserId) ({{ __('Team member badge') }})@endif
                                </option>
                            @endforeach
                        </select>
                    </label>
                    <button type="submit" class="btn btn-sm btn-primary">{{ __('Save') }}</button>
                </form>
            @endforeach
        </div>
        <p class="cabinet-sc-empty-filter small text-secondary d-none mb-0 mt-2" data-sc-assign-empty>{{ __('No projects match filters') }}</p>
    </div>
@elseif($teams->isNotEmpty())
    <div class="cabinet-sc-empty mb-0 mt-3">
        <p class="small text-secondary mb-0">{{ __('Profile team modules empty') }}</p>
    </div>
@endif
