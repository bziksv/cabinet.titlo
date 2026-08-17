@component('component.card', [
    'title' => $project->domain,
    'titleHtml' => e($project->domain) . view('partials.cabinet-module-version-badge', ['configKey' => 'cabinet-seo-reports'])->render(),
    'documentTitle' => $project->domain . ' · ' . __('Reports'),
])
    @slot('css')
        <link rel="stylesheet" href="{{ asset('css/cabinet-seo-reports.css') }}?v={{ @filemtime(public_path('css/cabinet-seo-reports.css')) ?: time() }}">
    @endslot

    <div class="cabinet-sr-page">
        @include('pages.partials.seo-reports-nav', [
            'srTab' => 'project',
            'srContextProject' => $project,
            'srCanEditSettings' => !empty($isOwner),
        ])

        @if(session('success'))
            <div class="alert alert-success py-2 px-3 small">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger py-2 px-3 small">{{ session('error') }}</div>
        @endif

        <div class="cabinet-sr-hero">
            <div>
                <h1 class="cabinet-sr-hero__title">{{ $project->domain }}</h1>
                <p class="cabinet-sr-hero__lead">
                    {{ $project->title ?: __('SEO report project') }}
                </p>
            </div>
            <div class="cabinet-sr-actions">
                @if(!empty($isOwner))
                    <form method="post" action="{{ route('pages.seo-reports.demo') }}" class="d-inline">
                        @csrf
                        <input type="hidden" name="project_id" value="{{ $project->id }}">
                        <button type="submit" class="btn btn-outline-secondary btn-sm">{{ __('Create demo report') }}</button>
                    </form>
                @endif
                @if(!empty($canEdit))
                    <button type="button"
                            class="btn btn-primary btn-sm"
                            data-bs-toggle="modal"
                            data-bs-target="#cabinetSrGenerateModal">
                        {{ __('Generate report') }}
                    </button>
                @elseif(!empty($shareRole))
                    <span class="cabinet-sr-badge cabinet-sr-badge--manual">{{ __('Read only') }}</span>
                @endif
                @if(empty($isOwner) && !empty($assignedTeam))
                    <span class="cabinet-sr-badge cabinet-sr-badge--ok" title="{{ __('Checklist team') }}">
                        {{ $assignedTeam->title }}
                    </span>
                @endif
                @if(!empty($isOwner))
                    <form method="post" action="{{ route('pages.seo-reports.archive', ['id' => $project->id]) }}" class="d-inline">
                        @csrf
                        <button type="submit"
                                class="btn btn-outline-secondary btn-sm cabinet-sr-archive-btn"
                                data-confirm="{{ __('Archive this project?') }}"
                                onclick="return confirm(this.dataset.confirm);">
                            {{ __('Archive') }}
                        </button>
                    </form>
                @endif
            </div>
        </div>

        @if(!empty($generationWarnings))
            <div class="alert alert-warning py-2 px-3 small mb-3">
                <div class="fw-semibold mb-1">{{ __('Before generate') }}</div>
                <ul class="mb-0 ps-3">
                    @foreach($generationWarnings as $w)
                        <li>{{ $w }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if(!empty($isOwner))
            <div class="cabinet-sr-dq mb-3">
                <div class="cabinet-sr-dq__head">
                    <span class="fw-semibold">{{ __('Checklist team') }}</span>
                </div>
                @if(empty($teamAccessReady))
                    <p class="small text-secondary mb-0">{{ __('Teams are not available') }}</p>
                @else
                    <p class="small text-secondary mb-2">{{ __('SEO reports team assign hint') }}</p>
                    <form method="post" action="{{ route('pages.seo-reports.assign-team', ['id' => $project->id]) }}" class="mb-2">
                        @csrf
                        <div class="d-flex flex-wrap gap-2 align-items-end">
                            <div style="min-width:14rem">
                                <label class="form-label small mb-1">{{ __('Team') }}</label>
                                <select name="team_id" class="form-select form-select-sm">
                                    <option value="0">{{ __('No team') }}</option>
                                    @foreach(($checklistTeams ?? []) as $team)
                                        <option value="{{ $team->id }}" @if((int) $project->team_id === (int) $team->id) selected @endif>
                                            {{ $team->title }}
                                            @if(isset($team->members_count))
                                                · {{ (int) $team->members_count }}
                                            @endif
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <button type="submit" class="btn btn-outline-primary btn-sm">{{ __('Save') }}</button>
                            <a class="btn btn-link btn-sm" href="{{ route('profile.index') }}#team">{{ __('Manage teams') }}</a>
                        </div>
                    </form>
                    @if(!empty($assignedTeam))
                        <p class="small mb-0 text-secondary">
                            {{ __('Assigned team') }}:
                            <strong>{{ $assignedTeam->title }}</strong>
                            @if($assignedTeam->relationLoaded('members') || $assignedTeam->members)
                                · {{ $assignedTeam->members->count() }} {{ __('members') }}
                            @endif
                        </p>
                    @endif
                @endif
            </div>

            <div class="cabinet-sr-dq mb-3">
                <div class="cabinet-sr-dq__head">
                    <span class="fw-semibold">{{ __('Project sharing') }}</span>
                </div>
                <form method="post" action="{{ route('pages.seo-reports.share', ['id' => $project->id]) }}" class="mb-2">
                    @csrf
                    <div class="d-flex flex-wrap gap-2 align-items-end">
                        <div>
                            <label class="form-label small mb-1">{{ __('Email') }}</label>
                            <input type="email" name="email" class="form-control form-control-sm" required placeholder="user@example.com">
                        </div>
                        <div>
                            <label class="form-label small mb-1">{{ __('Access role') }}</label>
                            <select name="role" class="form-select form-select-sm">
                                <option value="read">{{ __('Read only') }}</option>
                                <option value="edit">{{ __('Editor') }}</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-outline-primary btn-sm">{{ __('Share') }}</button>
                    </div>
                </form>
                @if(($sharedUsers ?? collect())->isNotEmpty())
                    <ul class="cabinet-sr-dq__list mb-0">
                        @foreach($sharedUsers as $su)
                            <li class="d-flex justify-content-between gap-2">
                                <span>{{ $su->email }} · {{ ($su->pivot->role ?? 'read') === 'edit' ? __('Editor') : __('Read only') }}</span>
                                <form method="post" action="{{ route('pages.seo-reports.unshare', ['id' => $project->id]) }}">
                                    @csrf
                                    <input type="hidden" name="user_id" value="{{ $su->id }}">
                                    <button type="submit" class="btn btn-link btn-sm p-0">{{ __('Revoke') }}</button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endif

        <div class="cabinet-sr-board">
            <section class="cabinet-sr-board__reports">
                <div class="cabinet-sr-board__head">
                    <h2 class="cabinet-sr-board__title">{{ __('Reports') }}</h2>
                    @if(!empty($canEdit))
                        <button type="button"
                                class="btn btn-primary btn-sm"
                                data-bs-toggle="modal"
                                data-bs-target="#cabinetSrGenerateModal">
                            {{ __('Generate report') }}
                        </button>
                    @endif
                </div>
                @if($reports->isEmpty())
                    <div class="cabinet-sr-empty py-4">
                        <p class="mb-2">{{ __('No reports yet') }}</p>
                        @if(!empty($canEdit))
                            <button type="button"
                                    class="btn btn-primary btn-sm"
                                    data-bs-toggle="modal"
                                    data-bs-target="#cabinetSrGenerateModal">
                                {{ __('Generate report') }}
                            </button>
                        @endif
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="cabinet-sr-table">
                            <thead>
                            <tr>
                                <th>{{ __('Period') }}</th>
                                <th>{{ __('Generated at') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th></th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($reports as $report)
                                @php
                                    $isArchive = !empty($report->archived_from_report_id);
                                    $generatedAt = $report->generated_at ?: $report->updated_at ?: $report->created_at;
                                @endphp
                                <tr @if(!$isArchive) class="is-current" @endif>
                                    <td>
                                        {{ optional($report->period_from)->format('d.m.Y') }}
                                        —
                                        {{ optional($report->period_to)->format('d.m.Y') }}
                                        @if($isArchive)
                                            <span class="cabinet-sr-badge cabinet-sr-badge--warn">{{ __('Previous version') }}</span>
                                        @else
                                            <span class="cabinet-sr-badge cabinet-sr-badge--ok">{{ __('Current version') }}</span>
                                        @endif
                                    </td>
                                    <td class="cabinet-sr-table__muted">
                                        @if($generatedAt)
                                            <time datetime="{{ $generatedAt->toIso8601String() }}">
                                                {{ $generatedAt->format('d.m.Y H:i') }}
                                            </time>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td>
                                        <span class="cabinet-sr-badge cabinet-sr-badge--manual">{{ $report->statusLabel() }}</span>
                                    </td>
                                    <td class="text-end">
                                        <a class="btn btn-outline-primary btn-sm"
                                           href="{{ route('pages.seo-reports.report', ['id' => $project->id, 'reportId' => $report->id]) }}">
                                            {{ __('Open') }}
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            @php
                $sectionEnabled = collect($sections)->where('enabled', true)->values();
                $sectionOff = collect($sections)->where('enabled', false)->values();
                $secOk = $sectionEnabled->where('source_status', 'ok')->count()
                    + $sectionEnabled->where('source_status', 'manual')->count();
                $secNeed = $sectionEnabled->filter(function ($s) {
                    $connect = $s['connect'] ?? null;
                    $isDev = is_array($connect) && ($connect['kind'] ?? '') === 'dev';
                    return !$isDev && in_array($s['source_status'] ?? '', ['not_connected', 'error', 'empty'], true);
                })->count();
                $secDev = $sectionEnabled->filter(function ($s) {
                    $connect = $s['connect'] ?? null;
                    return is_array($connect) && ($connect['kind'] ?? '') === 'dev';
                })->count();
            @endphp
            <section class="cabinet-sr-board__sections">
                <div class="cabinet-sr-board__head">
                    <div>
                        <h2 class="cabinet-sr-board__title">{{ __('Report sections') }}</h2>
                        <p class="cabinet-sr-board__hint mb-0">{{ __('SEO report sections manager hint') }}</p>
                    </div>
                    @if(!empty($project->template_id))
                        <a class="btn btn-outline-secondary btn-sm"
                           href="{{ route('pages.seo-reports.templates.edit', ['id' => $project->template_id]) }}">
                            {{ __('Edit template') }}
                        </a>
                    @endif
                </div>
                <div class="cabinet-sr-section-summary">
                    <span class="is-ok">{{ $secOk }} {{ __('ready') }}</span>
                    @if($secNeed > 0)
                        <span class="is-need">{{ $secNeed }} {{ __('need connection') }}</span>
                    @endif
                    @if($secDev > 0)
                        <span class="is-dev">{{ $secDev }} {{ __('in development short') }}</span>
                    @endif
                    @if($sectionOff->count() > 0)
                        <span class="is-off">{{ $sectionOff->count() }} {{ __('off') }}</span>
                    @endif
                </div>
                <div class="cabinet-sr-section-grid">
                    @foreach($sectionEnabled as $section)
                        @php
                            $connect = $section['connect'] ?? null;
                            $isDev = is_array($connect) && ($connect['kind'] ?? '') === 'dev';
                            $originKind = (string) ($section['origin_kind'] ?? 'manual');
                            $dead = in_array($section['source_status'], ['not_connected', 'error', 'empty'], true);
                            $cls = $isDev ? 'is-dev' : ($dead ? 'is-dead' : 'is-ok');
                            if ($section['source_status'] === 'manual') {
                                $cls = $originKind === 'auto' ? 'is-auto' : 'is-manual';
                            }
                            if ($originKind === 'titlo' && !$isDev && !$dead) {
                                $cls = 'is-titlo';
                            }
                            $badgeText = __('Connected');
                            if ($originKind === 'auto') {
                                $badgeText = __('Auto');
                            } elseif ($section['source_status'] === 'manual') {
                                $badgeText = __('Manual');
                            } elseif ($isDev) {
                                $badgeText = __('In development');
                            } elseif ($dead) {
                                $badgeText = __('Not connected');
                            }
                            $actionClass = 'cabinet-sr-section-chip__action';
                            if (in_array((string) ($section['source'] ?? ''), ['site_audit', 'seo_checklist', 'relevance', 'site_monitoring'], true)) {
                                $actionClass .= ' cabinet-sr-section-chip__action--module';
                            }
                        @endphp
                        <div class="cabinet-sr-section-chip {{ $cls }}">
                            <div class="cabinet-sr-section-chip__main">
                                <span class="cabinet-sr-section-chip__title">{{ $section['title'] }}</span>
                                <span class="cabinet-sr-section-chip__badge">{{ $badgeText }}</span>
                            </div>
                            <div class="cabinet-sr-section-chip__origin">{{ $section['origin'] ?? '' }}</div>
                            <div class="cabinet-sr-section-chip__meta">
                                @if($section['enabled'] && !$section['client_visible'] && !$isDev)
                                    <span class="cabinet-sr-section-chip__note">{{ __('Hidden for client') }}</span>
                                @endif
                                @if(is_array($connect) && ($connect['kind'] ?? '') === 'link' && !empty($connect['url']))
                                    <a class="{{ $actionClass }}" href="{{ $connect['url'] }}">
                                        {{ $connect['label'] ?? __('Change') }}
                                    </a>
                                @elseif($isDev)
                                    <span class="cabinet-sr-section-chip__note">{{ __('Soon') }}</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
                @if($sectionOff->isNotEmpty())
                    <details class="cabinet-sr-section-off">
                        <summary>{{ __('Disabled sections') }} ({{ $sectionOff->count() }})</summary>
                        <div class="cabinet-sr-section-grid cabinet-sr-section-grid--off">
                            @foreach($sectionOff as $section)
                                <div class="cabinet-sr-section-chip is-off">
                                    <div class="cabinet-sr-section-chip__main">
                                        <span class="cabinet-sr-section-chip__title">{{ $section['title'] }}</span>
                                        <span class="cabinet-sr-section-chip__badge">{{ __('Off') }}</span>
                                    </div>
                                    @if(!empty($section['origin']))
                                        <div class="cabinet-sr-section-chip__origin">{{ $section['origin'] }}</div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </details>
                @endif
            </section>
        </div>
    </div>

    @if(!empty($canEdit))
        <div class="modal fade" id="cabinetSrGenerateModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
            <div class="modal-dialog">
                <form class="modal-content" method="post"
                      action="{{ route('pages.seo-reports.reports.store', ['id' => $project->id]) }}"
                      data-sr-generate-form>
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">{{ __('Generate report') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" data-sr-generate-dismiss></button>
                    </div>
                    @php
                        $genSettings = method_exists($project, 'reportSettings')
                            ? $project->reportSettings()
                            : (is_array($project->settings_json) ? $project->settings_json : []);
                        $genPeriod = $genSettings['default_period'] ?? 'prev_month';
                        $genCompareMode = $genSettings['compare_mode'] ?? 'previous_period';
                        $genAutoCompare = array_key_exists('auto_compare', $genSettings)
                            ? !empty($genSettings['auto_compare'])
                            : true;
                    @endphp
                    <div class="modal-body" data-sr-generate-period>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Period preset') }}</label>
                            <select class="form-select" name="period_preset" data-sr-period-preset>
                                <option value="prev_month" @if($genPeriod === 'prev_month') selected @endif>{{ __('Previous calendar month') }}</option>
                                <option value="last_30" @if($genPeriod === 'last_30') selected @endif>{{ __('Last 30 days') }}</option>
                                <option value="calendar_month" @if($genPeriod === 'calendar_month') selected @endif>{{ __('Specific calendar month') }}</option>
                                <option value="custom" @if($genPeriod === 'custom') selected @endif>{{ __('Custom dates') }}</option>
                            </select>
                        </div>
                        <div class="mb-3" data-sr-period-month @if($genPeriod !== 'calendar_month') hidden @endif>
                            <label class="form-label">{{ __('Report month') }}</label>
                            <input type="month" class="form-control" name="period_month"
                                   value="{{ $genSettings['default_period_month'] ?? '' }}">
                        </div>
                        <div class="row g-2 mb-3" data-sr-period-custom @if($genPeriod !== 'custom') hidden @endif>
                            <div class="col-6">
                                <label class="form-label">{{ __('Date from') }}</label>
                                <input type="date" class="form-control" name="period_from"
                                       value="{{ $genSettings['default_period_from'] ?? '' }}">
                            </div>
                            <div class="col-6">
                                <label class="form-label">{{ __('Date to') }}</label>
                                <input type="date" class="form-control" name="period_to"
                                       value="{{ $genSettings['default_period_to'] ?? '' }}">
                            </div>
                        </div>
                        <label class="cabinet-sr-toggle-row mb-2">
                            <input type="hidden" name="auto_compare" value="0">
                            <input type="checkbox" name="auto_compare" value="1" data-sr-auto-compare
                                @if($genAutoCompare) checked @endif>
                            <span>{{ __('Compare with another period') }}</span>
                        </label>
                        <div data-sr-compare-fields @if(!$genAutoCompare) hidden @endif>
                            <div class="mb-3">
                                <label class="form-label">{{ __('Compare mode') }}</label>
                                <select class="form-select" name="compare_mode" data-sr-compare-mode>
                                    <option value="previous_period" @if($genCompareMode === 'previous_period') selected @endif>{{ __('Compare previous equal period') }}</option>
                                    <option value="previous_calendar_month" @if($genCompareMode === 'previous_calendar_month') selected @endif>{{ __('Compare previous calendar month') }}</option>
                                    <option value="same_month_last_year" @if($genCompareMode === 'same_month_last_year') selected @endif>{{ __('Compare same month last year') }}</option>
                                    <option value="calendar_month" @if($genCompareMode === 'calendar_month') selected @endif>{{ __('Compare specific calendar month') }}</option>
                                    <option value="custom" @if($genCompareMode === 'custom') selected @endif>{{ __('Compare custom dates') }}</option>
                                </select>
                            </div>
                            <div class="mb-3" data-sr-compare-month @if($genCompareMode !== 'calendar_month') hidden @endif>
                                <label class="form-label">{{ __('Compare month') }}</label>
                                <input type="month" class="form-control" name="compare_month"
                                       value="{{ $genSettings['compare_month'] ?? '' }}">
                            </div>
                            <div class="row g-2 mb-3" data-sr-compare-custom @if($genCompareMode !== 'custom') hidden @endif>
                                <div class="col-6">
                                    <label class="form-label">{{ __('Compare from') }}</label>
                                    <input type="date" class="form-control" name="compare_from"
                                           value="{{ $genSettings['default_compare_from'] ?? '' }}">
                                </div>
                                <div class="col-6">
                                    <label class="form-label">{{ __('Compare to') }}</label>
                                    <input type="date" class="form-control" name="compare_to"
                                           value="{{ $genSettings['default_compare_to'] ?? '' }}">
                                </div>
                            </div>
                        </div>
                        <div class="mt-3">
                            <label class="form-label">{{ __('PIN (optional)') }}</label>
                            <input type="text" class="form-control" name="public_pin" maxlength="8" inputmode="numeric" placeholder="1234">
                        </div>
                        <p class="small text-secondary mb-0 mt-2" data-sr-generate-hint>{{ __('SEO report generate hint') }}</p>
                        <div class="cabinet-sr-generate-busy" data-sr-generate-busy hidden>
                            <div class="cabinet-sr-spinner" role="status" aria-hidden="true"></div>
                            <div>
                                <div class="fw-semibold">{{ __('Generating report…') }}</div>
                                <div class="small text-secondary">{{ __('SEO report generate wait hint') }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" data-sr-generate-cancel>{{ __('Cancel') }}</button>
                        <button type="submit" class="btn btn-primary" data-sr-generate-submit>
                            <span class="cabinet-sr-generate-submit__idle" data-sr-generate-idle>{{ __('Generate report') }}</span>
                            <span class="cabinet-sr-generate-submit__busy" data-sr-generate-busy-label hidden>
                                <span class="cabinet-sr-spinner cabinet-sr-spinner--sm" role="status" aria-hidden="true"></span>
                                {{ __('Generating report…') }}
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @slot('js')
        <script>
            (function () {
                var box = document.querySelector('[data-sr-generate-period]');
                if (!box) return;
                var periodPreset = box.querySelector('[data-sr-period-preset]');
                var periodMonth = box.querySelector('[data-sr-period-month]');
                var periodCustom = box.querySelector('[data-sr-period-custom]');
                var autoCompare = box.querySelector('[data-sr-auto-compare]');
                var compareFields = box.querySelector('[data-sr-compare-fields]');
                var compareMode = box.querySelector('[data-sr-compare-mode]');
                var compareMonth = box.querySelector('[data-sr-compare-month]');
                var compareCustom = box.querySelector('[data-sr-compare-custom]');

                function sync() {
                    var p = periodPreset ? periodPreset.value : 'prev_month';
                    if (periodMonth) periodMonth.hidden = p !== 'calendar_month';
                    if (periodCustom) periodCustom.hidden = p !== 'custom';
                    var on = !autoCompare || autoCompare.checked;
                    if (compareFields) compareFields.hidden = !on;
                    var m = compareMode ? compareMode.value : 'previous_period';
                    if (compareMonth) compareMonth.hidden = !on || m !== 'calendar_month';
                    if (compareCustom) compareCustom.hidden = !on || m !== 'custom';
                }
                if (periodPreset) periodPreset.addEventListener('change', sync);
                if (autoCompare) autoCompare.addEventListener('change', sync);
                if (compareMode) compareMode.addEventListener('change', sync);
                sync();

                var form = document.querySelector('[data-sr-generate-form]');
                if (!form || form._srGenerateBound) return;
                form._srGenerateBound = true;
                var submitted = false;
                form.addEventListener('submit', function () {
                    if (submitted) return false;
                    submitted = true;

                    var busy = form.querySelector('[data-sr-generate-busy]');
                    var hint = form.querySelector('[data-sr-generate-hint]');
                    var idle = form.querySelector('[data-sr-generate-idle]');
                    var busyLabel = form.querySelector('[data-sr-generate-busy-label]');
                    var submit = form.querySelector('[data-sr-generate-submit]');
                    var cancel = form.querySelector('[data-sr-generate-cancel]');
                    var dismiss = form.querySelector('[data-sr-generate-dismiss]');

                    form.classList.add('is-generating');
                    if (busy) busy.hidden = false;
                    if (hint) hint.hidden = true;
                    if (idle) idle.hidden = true;
                    if (busyLabel) busyLabel.hidden = false;
                    if (submit) {
                        submit.disabled = true;
                        submit.setAttribute('aria-busy', 'true');
                    }
                    if (cancel) cancel.disabled = true;
                    if (dismiss) {
                        dismiss.disabled = true;
                        dismiss.setAttribute('disabled', 'disabled');
                    }

                    form.querySelectorAll('input, select, textarea, button').forEach(function (el) {
                        if (el === submit) return;
                        if (el.type === 'hidden') return;
                        el.setAttribute('data-sr-was-disabled', el.disabled ? '1' : '0');
                        if (el.tagName === 'BUTTON' && el.type !== 'submit') {
                            el.disabled = true;
                        } else if (el.tagName !== 'BUTTON') {
                            el.readOnly = true;
                            if (el.tagName === 'SELECT') el.disabled = true;
                        }
                    });
                });
            })();
        </script>
    @endslot
@endcomponent
