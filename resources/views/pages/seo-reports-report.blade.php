@component('component.card', [
    'title' => __('Report') . ' · ' . $project->domain,
    'titleHtml' => e(__('Report') . ' · ' . $project->domain) . view('partials.cabinet-module-version-badge', ['configKey' => 'cabinet-seo-reports'])->render(),
    'documentTitle' => __('Report') . ' · ' . $project->domain,
])
    @slot('css')
        <link rel="stylesheet" href="{{ asset('css/cabinet-seo-reports.css') }}?v={{ @filemtime(public_path('css/cabinet-seo-reports.css')) ?: time() }}">
    @endslot

    <div class="cabinet-sr-page"
         data-sr-status-url="{{ $statusUrl }}"
         data-sr-report-status="{{ $report->status }}">
        @include('pages.partials.seo-reports-nav', [
            'srTab' => 'report',
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
                <h1 class="cabinet-sr-hero__title">
                    {{ optional($report->period_from)->format('d.m.Y') }}
                    —
                    {{ optional($report->period_to)->format('d.m.Y') }}
                </h1>
                <p class="cabinet-sr-hero__lead">
                    {{ __('Status') }}: <span data-sr-status-label>{{ $report->statusLabel() }}</span>
                    @php
                        $wf = is_array($report->comments_json) ? ($report->comments_json['workflow_status'] ?? null) : null;
                        $wfLabel = [
                            'draft' => __('Manager draft'),
                            'review' => __('Team lead review'),
                            'client' => __('Ready for client'),
                        ][$wf] ?? null;
                    @endphp
                    @if($wfLabel)
                        · <span class="cabinet-sr-badge cabinet-sr-badge--manual">{{ $wfLabel }}</span>
                    @endif
                    @if(!empty($snapshot['progress']))
                        ·
                        <span data-sr-progress>
                        @foreach($snapshot['progress'] as $src => $st)
                            <span class="cabinet-sr-badge {{ $st === 'ok' ? 'cabinet-sr-badge--ok' : 'cabinet-sr-badge--warn' }}">{{ $src }}: {{ $st }}</span>
                        @endforeach
                        </span>
                    @endif
                </p>
            </div>
            @php
                $needsPublish = in_array($report->status, ['ready', 'approved_by_client'], true)
                    && !empty($snapshot['requires_publish'])
                    && empty($snapshot['published_at']);
            @endphp
            <div class="cabinet-sr-actions">
                @if(!empty($canEdit) && in_array($report->status, ['ready', 'approved_by_client', 'failed'], true))
                    <form method="post"
                          action="{{ route('pages.seo-reports.report.regenerate', ['id' => $project->id, 'reportId' => $report->id]) }}"
                          data-sr-busy-form
                          data-sr-busy-label="{{ __('Generating report…') }}">
                        @csrf
                        <button type="submit" class="btn btn-outline-primary btn-sm">{{ __('Regenerate') }}</button>
                    </form>
                    <form method="post" action="{{ route('pages.seo-reports.report.clone', ['id' => $project->id, 'reportId' => $report->id]) }}">
                        @csrf
                        <button type="submit" class="btn btn-outline-secondary btn-sm">{{ __('Clone report') }}</button>
                    </form>
                @endif
                @if(in_array($report->status, ['ready', 'approved_by_client'], true))
                    @if(!empty($canEdit) && $needsPublish)
                        <form method="post" action="{{ route('pages.seo-reports.report.publish', ['id' => $project->id, 'reportId' => $report->id]) }}">
                            @csrf
                            <button type="submit" class="btn btn-primary btn-sm">{{ __('Publish') }}</button>
                        </form>
                    @endif
                    <a class="btn btn-outline-secondary btn-sm" href="{{ $pdfUrl }}">{{ __('Download PDF') }}</a>
                    <a class="btn btn-outline-secondary btn-sm"
                       href="{{ route('pages.seo-reports.report.docx', ['id' => $project->id, 'reportId' => $report->id]) }}">
                        {{ __('Download DOCX') }}
                    </a>
                    <a class="btn btn-outline-secondary btn-sm" href="{{ $packUrl }}">{{ __('Download pack') }}</a>
                    <a class="btn btn-outline-secondary btn-sm"
                       href="{{ route('pages.seo-reports.report.positions-csv', ['id' => $project->id, 'reportId' => $report->id]) }}">
                        {{ __('Positions CSV') }}
                    </a>
                @endif
                @if($presentUrl && in_array($report->status, ['ready', 'approved_by_client'], true) && empty($needsPublish))
                    <a class="btn btn-outline-secondary btn-sm" href="{{ $presentUrl }}" target="_blank" rel="noopener">{{ __('Presentation mode') }}</a>
                @endif
            </div>
        </div>

        @if($report->status === 'generating')
            <div class="cabinet-sr-generating" data-sr-generating>
                <div class="cabinet-sr-spinner" role="status" aria-hidden="true"></div>
                <div>
                    <div class="fw-semibold">{{ __('Generating report…') }}</div>
                    <div class="small text-secondary">{{ __('SEO report generating hint') }}</div>
                </div>
            </div>
            <div class="cabinet-sr-skeleton" aria-hidden="true">
                <div class="cabinet-sr-skeleton__block"></div>
                <div class="cabinet-sr-skeleton__row">
                    <i></i><i></i><i></i><i></i>
                </div>
                <div class="cabinet-sr-skeleton__block"></div>
                <div class="cabinet-sr-skeleton__block cabinet-sr-skeleton__block--short"></div>
            </div>
        @elseif($report->status === 'failed')
            <div class="alert alert-danger py-2 px-3 small">
                {{ __('SEO report generation failed') }}
                @if($report->fail_reason): {{ $report->fail_reason }}@endif
            </div>
        @endif

        @if($publicUrl && in_array($report->status, ['ready', 'approved_by_client'], true))
            <div class="cabinet-sr-share mb-3">
                <div class="cabinet-sr-share__label">{{ __('Public link') }}</div>
                <div class="cabinet-sr-share__row">
                    <input type="text" class="form-control form-control-sm" readonly value="{{ $publicUrl }}"
                           data-sr-public-url onclick="this.select()">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-sr-copy>{{ __('Copy link') }}</button>
                    <a class="btn btn-sm btn-primary" href="{{ $publicUrl }}" target="_blank" rel="noopener">{{ __('Open') }}</a>
                </div>
                @if($qrUrl)
                    <div class="cabinet-sr-share__qr mt-2">
                        <img src="{{ $qrUrl }}" alt="QR" width="120" height="120">
                        <span class="small text-secondary">{{ __('Scan to open report') }}</span>
                    </div>
                @endif
                @if(!empty($snapshot['requires_publish']) && empty($snapshot['published_at']))
                    <p class="small text-warning mt-2 mb-0">{{ __('Publish report to open public link for client') }}</p>
                @elseif($publicUrl)
                    <p class="small mt-2 mb-0">
                        <a href="{{ $publicUrl }}?lite=1" target="_blank" rel="noopener">{{ __('Lite client dashboard') }}</a>
                    </p>
                @endif
                <form method="post" action="{{ route('pages.seo-reports.report.share', ['id' => $project->id, 'reportId' => $report->id]) }}" class="cabinet-sr-share__pin mt-2">
                    @csrf
                    <label class="form-label small mb-1">{{ __('PIN (optional)') }}</label>
                    <div class="d-flex gap-2">
                        <input type="text" class="form-control form-control-sm" name="public_pin"
                               value="{{ $report->public_pin }}"
                               placeholder="1234" maxlength="8" inputmode="numeric" style="max-width:8rem">
                        <button type="submit" class="btn btn-outline-secondary btn-sm">{{ __('Save') }}</button>
                    </div>
                </form>
                <form method="post" action="{{ route('pages.seo-reports.report.email', ['id' => $project->id, 'reportId' => $report->id]) }}" class="mt-3">
                    @csrf
                    <label class="form-label small mb-1">{{ __('Send link by email') }}</label>
                    <input type="text" class="form-control form-control-sm mb-2" name="emails"
                           placeholder="client@example.com, manager@agency.ru" required>
                    <textarea class="form-control form-control-sm mb-2" name="message" rows="2"
                              placeholder="{{ __('Optional message to client') }}"></textarea>
                    <button type="submit" class="btn btn-sm btn-primary">{{ __('Send') }}</button>
                </form>
            </div>
        @endif

        @if(in_array($report->status, ['ready', 'approved_by_client', 'failed', 'draft'], true))
            @php
                $dq = is_array($snapshot['data_quality'] ?? null) ? $snapshot['data_quality'] : null;
                $dqFlags = is_array($dq['flags'] ?? null) ? $dq['flags'] : [];
                $dqLevel = $dq['level'] ?? ($snapshot['quality'] ?? null);
            @endphp
            @php
                $commentsJson = is_array($report->comments_json) ? $report->comments_json : [];
                $clientReactions = is_array($commentsJson['client_reactions'] ?? null)
                    ? $commentsJson['client_reactions']
                    : [];
                $auditLog = is_array($snapshot['audit_log'] ?? null) ? $snapshot['audit_log'] : [];
                $kpiHistory = [];
                foreach (($kpiHistoryReports ?? []) as $hr) {
                    $hs = is_array($hr->snapshot_json) ? $hr->snapshot_json : [];
                    if (!empty($hs['kpi_goals'])) {
                        $kpiHistory[] = [
                            'period' => optional($hr->period_to)->format('Y-m'),
                            'goals' => $hs['kpi_goals'],
                        ];
                    }
                }
            @endphp

            @if($clientReactions !== [])
                @php
                    $sectionCatalog = \App\SeoReports\SeoReportSectionRegistry::all();
                    $reactionTypeLabels = [
                        'like' => __('Looks good'),
                        'question' => __('Ask a question'),
                        'clarify' => __('Need clarification'),
                    ];
                @endphp
                <div class="cabinet-sr-client-feedback mb-3" id="sr-client-feedback">
                    <div class="cabinet-sr-client-feedback__head">
                        <div>
                            <div class="cabinet-sr-client-feedback__title">{{ __('Client reactions') }}</div>
                            <p class="cabinet-sr-client-feedback__hint mb-0">{{ __('SEO report client reactions next step') }}</p>
                        </div>
                        <span class="cabinet-sr-client-feedback__count">{{ count($clientReactions) }}</span>
                    </div>
                    <ul class="cabinet-sr-client-feedback__list">
                        @foreach(array_reverse($clientReactions) as $reaction)
                            @php
                                $secKey = (string) ($reaction['section'] ?? '');
                                $secTitle = $sectionCatalog[$secKey]['title'] ?? ($secKey !== '' ? $secKey : '—');
                                $typeKey = (string) ($reaction['type'] ?? '');
                                $typeTitle = $reactionTypeLabels[$typeKey] ?? $typeKey;
                                $at = !empty($reaction['at'])
                                    ? \Carbon\Carbon::parse($reaction['at'])->format('d.m.Y H:i')
                                    : '';
                            @endphp
                            <li class="cabinet-sr-client-feedback__item cabinet-sr-client-feedback__item--{{ $typeKey }}">
                                <div class="cabinet-sr-client-feedback__meta">
                                    <strong>{{ $secTitle }}</strong>
                                    <span>{{ $typeTitle }}</span>
                                    @if($at !== '')
                                        <time>{{ $at }}</time>
                                    @endif
                                </div>
                                @if(!empty($reaction['text']))
                                    <div class="cabinet-sr-client-feedback__text">{{ $reaction['text'] }}</div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if($auditLog !== [])
                <details class="cabinet-sr-dq mb-3">
                    <summary class="fw-semibold">{{ __('Audit log') }}</summary>
                    <ul class="cabinet-sr-dq__list mt-2">
                        @foreach(array_reverse($auditLog) as $row)
                            <li>{{ $row['at'] ?? '' }} · {{ $row['action'] ?? '' }}
                                @if(!empty($row['section'])) · {{ $row['section'] }} @endif
                            </li>
                        @endforeach
                    </ul>
                </details>
            @endif

            @if($kpiHistory !== [])
                <details class="cabinet-sr-dq mb-3">
                    <summary class="fw-semibold">{{ __('KPI goals history') }}</summary>
                    <div class="table-responsive mt-2">
                        <table class="cabinet-sr-data-table" data-sr-sortable>
                            <thead>
                            <tr>
                                <th>{{ __('Month') }}</th>
                                <th>{{ __('Goal') }}</th>
                                <th>%</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($kpiHistory as $month)
                                @foreach($month['goals'] as $g)
                                    <tr>
                                        <td>{{ $month['period'] }}</td>
                                        <td>{{ $g['label'] ?? '' }}</td>
                                        <td>{{ $g['pct'] !== null ? $g['pct'] . '%' : '—' }}</td>
                                    </tr>
                                @endforeach
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </details>
            @endif

            @if($dqLevel || $dqFlags !== [])
                <div class="cabinet-sr-dq mb-3">
                    <div class="cabinet-sr-dq__head">
                        <span class="fw-semibold">{{ __('Data quality') }}</span>
                        @if($dqLevel)
                            <span class="cabinet-sr-badge {{ $dqLevel === 'full' ? 'cabinet-sr-badge--ok' : 'cabinet-sr-badge--warn' }}">
                                {{ $dqLevel === 'full' ? __('Full data') : ($dqLevel === 'partial' ? __('Partial data') : __('No data')) }}
                            </span>
                        @endif
                    </div>
                    @if($dqFlags !== [])
                        <ul class="cabinet-sr-dq__list">
                            @foreach($dqFlags as $flag)
                                @php
                                    $st = (string) ($flag['status'] ?? 'error');
                                    $stLabel = [
                                        'not_connected' => __('Not connected'),
                                        'error' => __('Source error'),
                                        'empty' => __('No data'),
                                    ][$st] ?? $st;
                                @endphp
                                <li>
                                    <span class="cabinet-sr-dq__status cabinet-sr-dq__status--{{ $st }}">{{ $stLabel }}</span>
                                    <strong>{{ $flag['title'] ?? $flag['section'] }}</strong>
                                    @if(!empty($flag['message']))
                                        — {{ $flag['message'] }}
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="small text-secondary mb-0">{{ __('All enabled data sources collected successfully') }}</p>
                    @endif
                </div>
            @endif

            @include('pages.partials.seo-reports-report-body', [
                'project' => $project,
                'report' => $report,
                'snapshot' => $snapshot,
                'sections' => $sections,
                'isPublicView' => false,
            ])

            @php $comments = is_array($report->comments_json) ? $report->comments_json : []; @endphp
            @if(!empty($canEdit))
            <form class="cabinet-sr-texts mt-4" method="post" data-sr-after-report
                  action="{{ route('pages.seo-reports.report.texts', ['id' => $project->id, 'reportId' => $report->id]) }}">
                @csrf
                <h2 class="h6">{{ __('Edit report texts') }}</h2>
                <p class="small text-secondary">{{ __('SEO report texts hint') }}</p>
                <div class="mb-2">
                    <label class="form-label small">{{ __('Workflow status') }}</label>
                    @php $workflow = old('workflow_status', $comments['workflow_status'] ?? 'draft'); @endphp
                    <select class="form-select form-select-sm" name="workflow_status" style="max-width:18rem">
                        <option value="draft" @if($workflow === 'draft') selected @endif>{{ __('Manager draft') }}</option>
                        <option value="review" @if($workflow === 'review') selected @endif>{{ __('Team lead review') }}</option>
                        <option value="client" @if($workflow === 'client') selected @endif>{{ __('Ready for client') }}</option>
                    </select>
                </div>
                <div class="mb-2">
                    <label class="form-label small">{{ __('Report summary') }}</label>
                    <textarea class="form-control form-control-sm" name="summary_text" rows="4" data-sr-text="summary">{{ old('summary_text', $report->summary_text) }}</textarea>
                </div>
                <div class="mb-2">
                    <label class="form-label small">{{ __('Work done') }}</label>
                    <div class="cabinet-sr-phrases mb-1" data-sr-phrases="work_done">
                        @foreach(($phraseLibrary['work_done'] ?? []) as $phrase)
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-sr-phrase="{{ e($phrase['body']) }}">{{ $phrase['title'] }}</button>
                        @endforeach
                    </div>
                    <textarea class="form-control form-control-sm" name="work_done_text" rows="4" data-sr-text="work_done">{{ old('work_done_text', $report->work_done_text) }}</textarea>
                    <div class="form-text">{{ __('Work done editor hint checklist') }}</div>
                </div>
                <div class="mb-2">
                    <label class="form-label small">{{ __('Work plan') }}</label>
                    <div class="cabinet-sr-phrases mb-1" data-sr-phrases="work_plan">
                        @foreach(($phraseLibrary['work_plan'] ?? []) as $phrase)
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-sr-phrase="{{ e($phrase['body']) }}">{{ $phrase['title'] }}</button>
                        @endforeach
                    </div>
                    <textarea class="form-control form-control-sm" name="work_plan_text" rows="4" data-sr-text="work_plan">{{ old('work_plan_text', $report->work_plan_text) }}</textarea>
                    <div class="form-text">{{ __('Work plan editor hint checklist') }}</div>
                </div>
                <div class="mb-2">
                    <label class="form-label small">{{ __('Traffic comment') }}</label>
                    <textarea class="form-control form-control-sm" name="comment_traffic" rows="2">{{ old('comment_traffic', $comments['traffic'] ?? '') }}</textarea>
                </div>
                <div class="mb-2">
                    <label class="form-label small">{{ __('Positions comment') }}</label>
                    <textarea class="form-control form-control-sm" name="comment_positions" rows="2">{{ old('comment_positions', $comments['positions'] ?? '') }}</textarea>
                </div>
                <div class="mb-2">
                    <label class="form-label small">{{ __('Conversions comment') }}</label>
                    <textarea class="form-control form-control-sm" name="comment_conversions" rows="2">{{ old('comment_conversions', $comments['conversions'] ?? '') }}</textarea>
                </div>
                <div class="mb-2">
                    <label class="form-label small">{{ __('Recommendations') }}</label>
                    <div class="cabinet-sr-phrases mb-1" data-sr-phrases="recommendations">
                        @foreach(($phraseLibrary['recommendations'] ?? []) as $phrase)
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-sr-phrase="{{ e($phrase['body']) }}">{{ $phrase['title'] }}</button>
                        @endforeach
                    </div>
                    <textarea class="form-control form-control-sm" name="recommendations_text" rows="3" data-sr-text="recommendations">{{ old('recommendations_text', $comments['recommendations'] ?? '') }}</textarea>
                </div>
                <button type="submit" class="btn btn-primary btn-sm">{{ __('Save texts') }}</button>
            </form>
            @endif
        @endif
    </div>

    @slot('js')
        <script src="{{ asset('plugins/chart.js/3.9.1/chart.js') }}"></script>
        <script src="{{ asset('js/cabinet-seo-reports-charts.js') }}?v={{ @filemtime(public_path('js/cabinet-seo-reports-charts.js')) ?: time() }}"></script>
        <script src="{{ asset('js/cabinet-seo-reports-toc.js') }}?v={{ @filemtime(public_path('js/cabinet-seo-reports-toc.js')) ?: time() }}"></script>
        <script>
            (function () {
                var page = document.querySelector('[data-sr-status-url]');
                if (!page) return;
                var copyBtn = page.querySelector('[data-sr-copy]');
                var urlInput = page.querySelector('[data-sr-public-url]');
                if (copyBtn && urlInput) {
                    copyBtn.addEventListener('click', function () {
                        urlInput.select();
                        try { document.execCommand('copy'); } catch (e) {}
                        if (navigator.clipboard) navigator.clipboard.writeText(urlInput.value);
                        copyBtn.textContent = @json(__('Copied'));
                        setTimeout(function () { copyBtn.textContent = @json(__('Copy link')); }, 1500);
                    });
                }

                page.querySelectorAll('[data-sr-phrase]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        var group = btn.closest('[data-sr-phrases]');
                        var key = group ? group.getAttribute('data-sr-phrases') : '';
                        var area = page.querySelector('[data-sr-text="' + key + '"]');
                        if (!area) return;
                        var phrase = btn.getAttribute('data-sr-phrase') || '';
                        area.value = (area.value ? area.value.replace(/\s+$/, '') + "\n\n" : '') + phrase;
                        area.focus();
                    });
                });

                document.querySelectorAll('[data-sr-busy-form]').forEach(function (form) {
                    form.addEventListener('submit', function () {
                        var btn = form.querySelector('[type="submit"]');
                        if (!btn || btn.disabled) return;
                        btn.disabled = true;
                        btn.setAttribute('aria-busy', 'true');
                        var label = form.getAttribute('data-sr-busy-label') || btn.textContent;
                        btn.innerHTML = '<span class="cabinet-sr-spinner cabinet-sr-spinner--sm" role="status" aria-hidden="true"></span>'
                            + label;
                    });
                });

                var status = page.getAttribute('data-sr-report-status');
                if (status !== 'generating') return;
                var url = page.getAttribute('data-sr-status-url');
                var label = page.querySelector('[data-sr-status-label]');
                var tries = 0;
                var timer = setInterval(function () {
                    tries++;
                    fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                        .then(function (r) { return r.json(); })
                        .then(function (data) {
                            if (!data || !data.ok) return;
                            if (label) label.textContent = data.label || data.status;
                            if (data.ready || data.failed || tries > 120) {
                                clearInterval(timer);
                                window.location.reload();
                            }
                        })
                        .catch(function () {});
                }, 2500);
            })();
        </script>
    @endslot
@endcomponent
