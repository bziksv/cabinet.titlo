@component('component.card', [
    'titleHtml' => cabinet_sc_module_title_html(),
    'documentTitle' => cabinet_sc_document_title(__('Time log')),
])
    @slot('css')
        <link rel="stylesheet" href="{{ asset('plugins/select2/css/select2.min.css') }}">
        <link rel="stylesheet" href="{{ asset('plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css') }}">
        <link rel="stylesheet" href="{{ asset('css/cabinet-seo-checklist.css') }}?v={{ @filemtime(public_path('css/cabinet-seo-checklist.css')) ?: time() }}">
    @endslot

    @php
        $filterProjectIds = array_map('intval', $filterProjectIds ?? []);
        $filterUserIds = array_map('intval', $filterUserIds ?? []);
        $filterGroup = in_array(($filterGroup ?? 'day'), ['day', 'task'], true) ? $filterGroup : 'day';
        $filterPeriod = in_array(($filterPeriod ?? '30d'), ['today', 'week', 'month', '30d', 'custom'], true)
            ? $filterPeriod
            : '30d';
        $summary = $timesheet['summary'] ?? [];
        $chart = $timesheet['chart'] ?? [];
        $showUser = !empty($timesheet['show_user']);
        $activeItemId = (int) ($timesheet['active_item_id'] ?? 0);

        $timesheetQuery = function (array $extra = []) use ($filterFrom, $filterTo, $filterProjectIds, $filterUserIds, $filterGroup, $filterPeriod) {
            $q = array_merge([
                'from' => $filterFrom,
                'to' => $filterTo,
                'group' => $filterGroup,
                'period' => $filterPeriod,
            ], $extra);
            if ($filterProjectIds !== []) {
                $q['project_ids'] = $filterProjectIds;
            }
            if ($filterUserIds !== []) {
                $q['user_ids'] = $filterUserIds;
            }
            foreach (['from', 'to', 'period'] as $key) {
                if (array_key_exists($key, $extra) && $extra[$key] === null) {
                    unset($q[$key]);
                }
            }

            return $q;
        };
    @endphp

    <div class="cabinet-sc-page" data-sc-hub="timesheet">
        @include('pages.partials.seo-checklist-nav', [
            'scTab' => 'timesheet',
            'scMyTasksCount' => $myTasksCount ?? null,
            'scReviewCount' => $reviewCount ?? null,
            'scShowReviewTab' => $showReviewTab ?? false,
            'scUnreadNotesCount' => $unreadNotesCount ?? null,
            'scProjectsCount' => $projectsCount ?? null,
            'scTeamCount' => $teamCount ?? null,
            'scTemplatesCount' => $templatesCount ?? null,
        ])

        <div class="cabinet-sc-plan-head">
            <div>
                <h2 class="cabinet-sc-plan-head__title">{{ __('Time log') }}</h2>
                <p class="cabinet-sc-plan-head__hint">{{ __('Time log hint') }}</p>
            </div>
            <div class="cabinet-sc-plan-head__meta">
                <a href="{{ route('pages.seo-checklist.timesheet.export', $timesheetQuery()) }}"
                   class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-download" aria-hidden="true"></i>
                    {{ __('Export CSV') }}
                </a>
            </div>
        </div>

        <div class="cabinet-sc-timesheet-summary mb-3">
            <div class="cabinet-sc-timesheet-summary__card">
                <span class="cabinet-sc-timesheet-summary__label">{{ __('Total') }}</span>
                <strong class="cabinet-sc-timesheet-summary__value">{{ $summary['formatted_total'] ?? '0:00' }}</strong>
            </div>
            <div class="cabinet-sc-timesheet-summary__card">
                <span class="cabinet-sc-timesheet-summary__label">{{ __('Avg per day') }}</span>
                <strong class="cabinet-sc-timesheet-summary__value">{{ $summary['formatted_avg'] ?? '0:00' }}</strong>
                <span class="cabinet-sc-timesheet-summary__sub">{{ __('Days with time') }}: {{ (int) ($summary['days_count'] ?? 0) }}</span>
            </div>
            <div class="cabinet-sc-timesheet-summary__card cabinet-sc-timesheet-summary__card--wide">
                <span class="cabinet-sc-timesheet-summary__label">{{ __('Top tasks') }}</span>
                @if(!empty($summary['top_tasks']))
                    <ol class="cabinet-sc-timesheet-summary__top">
                        @foreach($summary['top_tasks'] as $top)
                            <li>
                                <span class="cabinet-sc-timesheet-summary__top-main">
                                    <span class="cabinet-sc-timesheet__domain">{{ $top['domain'] }}</span>
                                    <span>{{ \Illuminate\Support\Str::limit($top['title'], 48) }}</span>
                                </span>
                                <span class="cabinet-sc-timesheet-summary__top-meta">
                                    {{ $top['formatted'] }}
                                    <span class="cabinet-sc-timesheet__pct">{{ $top['pct'] }}%</span>
                                </span>
                            </li>
                        @endforeach
                    </ol>
                @else
                    <span class="small text-secondary">{{ __('No time logged yet') }}</span>
                @endif
            </div>
        </div>

        @if(!empty($chart))
            <div class="cabinet-sc-timesheet-chart mb-3" aria-label="{{ __('Hours by day chart') }}">
                <div class="cabinet-sc-timesheet-chart__bars">
                    @foreach($chart as $point)
                        <div class="cabinet-sc-timesheet-chart__col"
                             title="{{ $point['label'] }}: {{ $point['formatted'] }}">
                            <div class="cabinet-sc-timesheet-chart__bar-wrap">
                                <span class="cabinet-sc-timesheet-chart__bar" style="height: {{ max(4, (int) $point['pct_bar']) }}%"></span>
                            </div>
                            <span class="cabinet-sc-timesheet-chart__label">{{ $point['label'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="cabinet-sc-chronicle-presets mb-2" role="tablist" aria-label="{{ __('Timesheet period') }}">
            @foreach([
                'today' => __('Today'),
                'week' => __('This week'),
                'month' => __('This month'),
                '30d' => __('Last 30 days'),
            ] as $periodKey => $periodLabel)
                <a href="{{ route('pages.seo-checklist.timesheet', $timesheetQuery(['period' => $periodKey, 'from' => null, 'to' => null])) }}"
                   class="cabinet-sc-chronicle-presets__item @if($filterPeriod === $periodKey) is-active @endif"
                   @if($filterPeriod === $periodKey) aria-current="page" @endif>
                    {{ $periodLabel }}
                </a>
            @endforeach
        </div>

        <div class="cabinet-sc-chronicle-presets mb-2" role="tablist" aria-label="{{ __('Timesheet group by') }}">
            <a href="{{ route('pages.seo-checklist.timesheet', $timesheetQuery(['group' => 'day'])) }}"
               class="cabinet-sc-chronicle-presets__item @if($filterGroup === 'day') is-active @endif"
               @if($filterGroup === 'day') aria-current="page" @endif>
                {{ __('Timesheet by days') }}
            </a>
            <a href="{{ route('pages.seo-checklist.timesheet', $timesheetQuery(['group' => 'task'])) }}"
               class="cabinet-sc-chronicle-presets__item @if($filterGroup === 'task') is-active @endif"
               @if($filterGroup === 'task') aria-current="page" @endif>
                {{ __('Timesheet by tasks') }}
            </a>
        </div>

        <form method="get" action="{{ route('pages.seo-checklist.timesheet') }}" class="cabinet-sc-chronicle-filters mb-3">
            <input type="hidden" name="group" value="{{ $filterGroup }}">
            <input type="hidden" name="period" value="custom">
            <input type="date" name="from" class="form-control form-control-sm" value="{{ $filterFrom }}" aria-label="{{ __('Date from') }}">
            <input type="date" name="to" class="form-control form-control-sm" value="{{ $filterTo }}" aria-label="{{ __('Date to') }}">
            <select name="project_ids[]"
                    class="form-select form-select-sm cabinet-sc-multi"
                    multiple
                    data-placeholder="{{ __('All projects') }}"
                    aria-label="{{ __('Projects') }}">
                @foreach($projects as $p)
                    <option value="{{ $p->id }}" @if(in_array((int) $p->id, $filterProjectIds, true)) selected @endif>{{ $p->domain }}</option>
                @endforeach
            </select>
            @if(!empty($canTeamTimesheet) && ($authors ?? collect())->isNotEmpty())
                <select name="user_ids[]"
                        class="form-select form-select-sm cabinet-sc-multi"
                        multiple
                        data-placeholder="{{ __('All authors') }}"
                        aria-label="{{ __('Who') }}">
                    @foreach($authors as $author)
                        @php
                            $authorName = trim(($author->name ?? '') . ' ' . ($author->last_name ?? '')) ?: $author->email;
                        @endphp
                        <option value="{{ $author->id }}" @if(in_array((int) $author->id, $filterUserIds, true)) selected @endif>
                            {{ $authorName }}
                        </option>
                    @endforeach
                </select>
            @endif
            <button type="submit" class="btn btn-sm btn-outline-secondary">{{ __('Apply') }}</button>
            <a href="{{ route('pages.seo-checklist.timesheet') }}" class="btn btn-sm btn-link">{{ __('Reset') }}</a>
        </form>

        @php
            $days = $timesheet['days'] ?? [];
            $tasks = $timesheet['tasks'] ?? [];
            $isEmpty = $filterGroup === 'task' ? empty($tasks) : empty($days);
        @endphp

        @if(!$isEmpty)
            <div class="cabinet-sc-timesheet-toolbar mb-2">
                <input type="search"
                       class="form-control form-control-sm"
                       data-sc-timesheet-search
                       placeholder="{{ __('Search tasks') }}…"
                       aria-label="{{ __('Search tasks') }}">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-sc-timesheet-expand>{{ __('Expand all') }}</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-sc-timesheet-collapse>{{ __('Collapse all') }}</button>
            </div>
            <p class="cabinet-sc-empty-filter small text-secondary d-none mb-2" data-sc-timesheet-empty-filter>{{ __('No tasks match filters') }}</p>
        @endif

        @if($isEmpty)
            <div class="cabinet-sc-empty">
                <i class="bi bi-clock-history display-6 d-block mb-2 opacity-50"></i>
                <p class="fw-semibold mb-1">{{ __('No time logged yet') }}</p>
                <p class="small text-secondary mb-0">{{ __('No time logged hint') }}</p>
            </div>
        @elseif($filterGroup === 'task')
            <div class="cabinet-sc-timesheet">
                @foreach($tasks as $idx => $task)
                    @php
                        $isActiveTask = !empty($task['is_active']) || ((int) ($task['item_id'] ?? 0) === $activeItemId);
                        $searchHay = mb_strtolower(($task['domain'] ?? '') . ' ' . ($task['title'] ?? '') . ' ' . ($task['user_label'] ?? ''));
                    @endphp
                    <details class="cabinet-sc-timesheet__day @if($isActiveTask) is-active-timer @endif"
                             data-sc-timesheet-block
                             @if($idx < 5 || $isActiveTask) open @endif>
                        <summary class="cabinet-sc-timesheet__day-head">
                            <div class="cabinet-sc-timesheet__task-head">
                                @if(!empty($task['project_id']) && !empty($task['item_id']))
                                    <a href="{{ route('pages.seo-checklist.show', ['id' => $task['project_id']]) }}#sc-item-{{ $task['anchor_item_id'] ?? $task['item_id'] }}"
                                       class="cabinet-sc-timesheet__domain"
                                       onclick="event.stopPropagation()">{{ $task['domain'] }}</a>
                                    <a href="{{ route('pages.seo-checklist.show', ['id' => $task['project_id']]) }}#sc-item-{{ $task['anchor_item_id'] ?? $task['item_id'] }}"
                                       class="cabinet-sc-timesheet__task-title"
                                       onclick="event.stopPropagation()">{{ $task['title'] }}</a>
                                @else
                                    <span class="cabinet-sc-timesheet__domain">{{ $task['domain'] }}</span>
                                    <span class="cabinet-sc-timesheet__task-title">{{ $task['title'] }}</span>
                                @endif
                                @if($showUser)
                                    <span class="cabinet-sc-timesheet__who">{{ $task['user_label'] }}</span>
                                @endif
                                @if($isActiveTask)
                                    <span class="cabinet-sc-timesheet__live">{{ __('Timer running') }}</span>
                                @endif
                            </div>
                            <span class="cabinet-sc-timesheet__head-meta">
                                <span class="cabinet-sc-timesheet__pct">{{ $task['pct'] }}%</span>
                                <strong>{{ $task['formatted'] }}</strong>
                            </span>
                        </summary>
                        <div class="cabinet-sc-timesheet__share" aria-hidden="true">
                            <span style="width: {{ min(100, (float) $task['pct']) }}%"></span>
                        </div>
                        <ul class="cabinet-sc-timesheet__list cabinet-sc-timesheet__list--by-task">
                            @foreach($task['entries'] as $entry)
                                <li data-sc-timesheet-row data-search="{{ e($searchHay . ' ' . ($entry['label'] ?? '')) }}">
                                    <span class="cabinet-sc-timesheet__task">{{ $entry['label'] }}</span>
                                    <span class="cabinet-sc-timesheet__dur-wrap">
                                        <span class="cabinet-sc-timesheet__pct">{{ $entry['pct'] }}%</span>
                                        <span class="cabinet-sc-timesheet__dur">{{ $entry['formatted'] }}</span>
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </details>
                @endforeach
            </div>
        @else
            <div class="cabinet-sc-timesheet">
                @foreach($days as $idx => $day)
                    <details class="cabinet-sc-timesheet__day"
                             data-sc-timesheet-block
                             @if($idx < 3) open @endif>
                        <summary class="cabinet-sc-timesheet__day-head">
                            <h3>{{ $day['label'] }}</h3>
                            <span class="cabinet-sc-timesheet__head-meta">
                                <span class="cabinet-sc-timesheet__pct">{{ $day['pct'] }}%</span>
                                <strong>{{ $day['formatted'] }}</strong>
                            </span>
                        </summary>
                        <div class="cabinet-sc-timesheet__share" aria-hidden="true">
                            <span style="width: {{ min(100, (float) $day['pct']) }}%"></span>
                        </div>
                        <ul class="cabinet-sc-timesheet__list">
                            @foreach($day['entries'] as $entry)
                                @php
                                    $rowActive = !empty($entry['is_active']) || ((int) ($entry['item_id'] ?? 0) === $activeItemId);
                                    $rowSearch = mb_strtolower(($entry['domain'] ?? '') . ' ' . ($entry['title'] ?? '') . ' ' . ($entry['user_label'] ?? '') . ' ' . ($day['label'] ?? ''));
                                @endphp
                                <li class="@if($rowActive) is-active-timer @endif"
                                    data-sc-timesheet-row
                                    data-search="{{ e($rowSearch) }}">
                                    @if(!empty($entry['project_id']) && !empty($entry['item_id']))
                                        <a href="{{ route('pages.seo-checklist.show', ['id' => $entry['project_id']]) }}#sc-item-{{ $entry['anchor_item_id'] ?? $entry['item_id'] }}"
                                           class="cabinet-sc-timesheet__domain">{{ $entry['domain'] }}</a>
                                    @else
                                        <span class="cabinet-sc-timesheet__domain">{{ $entry['domain'] }}</span>
                                    @endif
                                    <span class="cabinet-sc-timesheet__task">
                                        {{ $entry['title'] }}
                                        @if($showUser)
                                            <span class="cabinet-sc-timesheet__who">· {{ $entry['user_label'] }}</span>
                                        @endif
                                        @if($rowActive)
                                            <span class="cabinet-sc-timesheet__live">{{ __('Timer running') }}</span>
                                        @endif
                                    </span>
                                    <span class="cabinet-sc-timesheet__dur-wrap">
                                        <span class="cabinet-sc-timesheet__pct">{{ $entry['pct'] }}%</span>
                                        <span class="cabinet-sc-timesheet__dur">{{ $entry['formatted'] }}</span>
                                    </span>
                                    <div class="cabinet-sc-timesheet__row-share" aria-hidden="true">
                                        <span style="width: {{ min(100, (float) $entry['pct']) }}%"></span>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </details>
                @endforeach
            </div>
        @endif
    </div>

    @slot('js')
        <script src="{{ asset('plugins/select2/js/select2.full.min.js') }}"></script>
        <script src="{{ asset('js/cabinet-seo-checklist-timesheet.js') }}?v={{ @filemtime(public_path('js/cabinet-seo-checklist-timesheet.js')) ?: time() }}"></script>
        <script>
            (function () {
                if (!window.jQuery || !jQuery.fn.select2) return;
                jQuery('.cabinet-sc-multi').each(function () {
                    var $el = jQuery(this);
                    $el.select2({
                        theme: 'bootstrap4',
                        width: '100%',
                        placeholder: $el.data('placeholder') || '',
                        allowClear: true,
                        closeOnSelect: false
                    });
                });
            })();
        </script>
    @endslot
@endcomponent
