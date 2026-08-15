@component('component.card', [
    'title' => __('Compare reports') . ' · ' . $project->domain,
    'titleHtml' => e(__('Compare reports') . ' · ' . $project->domain) . view('partials.cabinet-module-version-badge', ['configKey' => 'cabinet-seo-reports'])->render(),
    'documentTitle' => __('Compare reports') . ' · ' . $project->domain,
])
    @slot('css')
        <link rel="stylesheet" href="{{ asset('css/cabinet-seo-reports.css') }}?v={{ @filemtime(public_path('css/cabinet-seo-reports.css')) ?: time() }}">
    @endslot

    <div class="cabinet-sr-page">
        @include('pages.partials.seo-reports-nav', [
            'srTab' => 'compare',
            'srContextProject' => $project,
            'srCanEditSettings' => $project->isOwnedBy((int) Auth::id()),
        ])

        <div class="cabinet-sr-hero">
            <div>
                <h1 class="cabinet-sr-hero__title">{{ __('Compare reports') }}</h1>
                <p class="cabinet-sr-hero__lead">{{ __('Compare two ready reports side by side') }}</p>
            </div>
        </div>

        <form method="get" class="cabinet-sr-compare-pick mb-3">
            <div class="row g-2 align-items-end">
                <div class="col-md-5">
                    <label class="form-label small">A</label>
                    <select class="form-select form-select-sm" name="a">
                        @foreach($readyReports as $r)
                            <option value="{{ $r->id }}" @if($left && (int)$left->id === (int)$r->id) selected @endif>
                                {{ optional($r->period_from)->format('d.m.Y') }} — {{ optional($r->period_to)->format('d.m.Y') }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label small">B</label>
                    <select class="form-select form-select-sm" name="b">
                        @foreach($readyReports as $r)
                            <option value="{{ $r->id }}" @if($right && (int)$right->id === (int)$r->id) selected @endif>
                                {{ optional($r->period_from)->format('d.m.Y') }} — {{ optional($r->period_to)->format('d.m.Y') }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100">{{ __('Compare') }}</button>
                </div>
            </div>
        </form>

        @if(!$left || !$right)
            <div class="cabinet-sr-empty py-4">{{ __('Need at least two ready reports to compare') }}</div>
        @else
            @php
                $fmt = static function ($snap, $path, $decimals = 0) {
                    $parts = explode('.', $path);
                    $cur = $snap;
                    foreach ($parts as $p) {
                        if (!is_array($cur) || !array_key_exists($p, $cur)) {
                            return null;
                        }
                        $cur = $cur[$p];
                    }
                    if ($cur === null || $cur === '') {
                        return null;
                    }
                    return number_format((float) $cur, $decimals, ',', ' ');
                };
                $rows = [
                    ['Visits', 'traffic.kpis.visits.value', 0],
                    ['Users', 'traffic.kpis.users.value', 0],
                    ['Bounce rate', 'traffic.kpis.bounce_rate.value', 1],
                    ['TOP-10', 'positions.summary.top10', 0],
                    ['Improved', 'positions.dynamics.improved', 0],
                    ['Worsened', 'positions.dynamics.worsened', 0],
                ];
            @endphp
            <div class="table-responsive">
                <table class="cabinet-sr-data-table">
                    <thead>
                    <tr>
                        <th>{{ __('Metric') }}</th>
                        <th>
                            {{ optional($left->period_from)->format('d.m.Y') }}
                            — {{ optional($left->period_to)->format('d.m.Y') }}
                        </th>
                        <th>
                            {{ optional($right->period_from)->format('d.m.Y') }}
                            — {{ optional($right->period_to)->format('d.m.Y') }}
                        </th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($rows as [$label, $path, $dec])
                        @php
                            $a = $fmt($leftSnapshot, $path, $dec);
                            $b = $fmt($rightSnapshot, $path, $dec);
                        @endphp
                        <tr>
                            <td>{{ __($label) }}</td>
                            <td>{{ $a !== null ? $a : '—' }}</td>
                            <td>{{ $b !== null ? $b : '—' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <div class="row g-3 mt-2">
                <div class="col-md-6">
                    <h2 class="h6">A · {{ __('KPI goals') }}</h2>
                    @forelse(($leftSnapshot['kpi_goals'] ?? []) as $g)
                        <div class="cabinet-sr-goal-card cabinet-sr-goal-card--{{ $g['tone'] ?? 'yellow' }}">
                            <strong>{{ $g['label'] }}</strong>
                            <span>{{ $g['pct'] !== null ? $g['pct'] . '%' : '—' }}</span>
                            <small>{{ $g['why'] ?? '' }}</small>
                        </div>
                    @empty
                        <p class="small text-secondary">{{ __('No KPI goals') }}</p>
                    @endforelse
                </div>
                <div class="col-md-6">
                    <h2 class="h6">B · {{ __('KPI goals') }}</h2>
                    @forelse(($rightSnapshot['kpi_goals'] ?? []) as $g)
                        <div class="cabinet-sr-goal-card cabinet-sr-goal-card--{{ $g['tone'] ?? 'yellow' }}">
                            <strong>{{ $g['label'] }}</strong>
                            <span>{{ $g['pct'] !== null ? $g['pct'] . '%' : '—' }}</span>
                            <small>{{ $g['why'] ?? '' }}</small>
                        </div>
                    @empty
                        <p class="small text-secondary">{{ __('No KPI goals') }}</p>
                    @endforelse
                </div>
            </div>
        @endif
    </div>
@endcomponent
