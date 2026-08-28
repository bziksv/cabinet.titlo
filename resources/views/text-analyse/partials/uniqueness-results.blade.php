@php $u = $uniqueness ?? null; @endphp
@if(!empty($u))
    <div class="card shadow-sm mb-3 cabinet-ta-uniqueness cabinet-ta-esenin-like" id="cabinet-ta-uniqueness-results">
        <div class="card-header py-2">
            <h3 class="card-title h6 mb-0">
                <i class="fas fa-fingerprint me-1 text-primary"></i>{{ __('Text uniqueness') }}
            </h3>
        </div>
        <div class="card-body">
            @if(!empty($u['error']))
                <div class="alert alert-warning mb-0">{{ $u['message'] ?? __('Text uniqueness fetch failed') }}</div>
            @else
                @if(!empty($u['no_significant_matches']))
                    <div class="alert alert-warning py-2 mb-3">
                        {{ __('Text analyzer uniqueness no matches warning', [
                            'probes' => (int) ($u['xml_requests'] ?? 0),
                            'pages' => (int) ($u['pages_fetched'] ?? 0),
                        ]) }}
                    </div>
                @elseif(isset($u['own_match_pct']) && ($u['own_match_pct'] > 0 || !empty($u['own_url'])))
                    <div class="alert alert-info py-2 mb-3">
                        {{ __('Text analyzer own match banner', [
                            'pct' => $u['own_match_pct'] ?? 0,
                            'url' => $u['own_url'] ?? '',
                        ]) }}
                    </div>
                @endif

                <div class="row g-3 cabinet-ta-uniq-grid" data-cabinet-ta-uniq-panel>
                    <div class="col-lg-2">
                        <div class="cabinet-ta-uniq-nav cabinet-esenin-score-nav">
                            <button type="button"
                                    class="cabinet-esenin-score-btn active"
                                    data-uniq-tab="overview"
                                    aria-pressed="true">
                                <span class="cabinet-esenin-score-btn__title">
                                    {{ __('Text analyzer uniq nav overview') }}
                                    @include('text-analyse.partials.tip', [
                                        'tip' => __('Text analyzer uniq tip uniqueness scores'),
                                        'tipSide' => 'right',
                                    ])
                                </span>
                                <span class="cabinet-esenin-score-btn__value">
                                    {{ $u['uniqueness_pct'] ?? 0 }}%
                                    <span class="badge text-bg-{{ ($u['uniqueness_pct'] ?? 100) < 50 ? 'danger' : (($u['uniqueness_pct'] ?? 100) < 85 ? 'warning' : 'success') }} cabinet-esenin-score-btn__badge">
                                        {{ ($u['matched_pct'] ?? 0) }}%
                                    </span>
                                </span>
                            </button>
                            <button type="button"
                                    class="cabinet-esenin-score-btn"
                                    data-uniq-tab="sources"
                                    aria-pressed="false">
                                <span class="cabinet-esenin-score-btn__title">
                                    {{ __('Text analyzer uniq nav sources') }}
                                    @include('text-analyse.partials.tip', [
                                        'tip' => __('Text analyzer uniq tip sources'),
                                        'tipSide' => 'right',
                                    ])
                                </span>
                                <span class="cabinet-esenin-score-btn__value">
                                    {{ (int) ($u['web_sources_count'] ?? count(array_filter($u['sources'] ?? [], static function ($s) { return empty($s['is_own']); }))) }}
                                    <span class="badge text-bg-secondary cabinet-esenin-score-btn__badge">{{ __('Text analyzer uniq sources short') }}</span>
                                </span>
                            </button>
                            <button type="button"
                                    class="cabinet-esenin-score-btn"
                                    data-uniq-tab="matches"
                                    aria-pressed="false">
                                <span class="cabinet-esenin-score-btn__title">
                                    {{ __('Text analyzer uniq nav matches') }}
                                    @include('text-analyse.partials.tip', [
                                        'tip' => __('Text analyzer uniq tip matches'),
                                        'tipSide' => 'right',
                                    ])
                                </span>
                                <span class="cabinet-esenin-score-btn__value">
                                    {{ (int) ($u['shingles_matched'] ?? 0) }}
                                    <span class="badge text-bg-danger cabinet-esenin-score-btn__badge">/ {{ (int) ($u['shingles_total'] ?? 0) }}</span>
                                </span>
                            </button>
                            @if(!empty($u['own_url']) || (isset($u['own_match_pct']) && (float) $u['own_match_pct'] > 0))
                                <button type="button"
                                        class="cabinet-esenin-score-btn"
                                        data-uniq-tab="own"
                                        aria-pressed="false">
                                    <span class="cabinet-esenin-score-btn__title">{{ __('Text analyzer uniq nav own') }}</span>
                                    <span class="cabinet-esenin-score-btn__value">
                                        {{ $u['own_match_pct'] ?? 0 }}%
                                        <span class="badge text-bg-info cabinet-esenin-score-btn__badge">{{ __('Text analyzer own source badge') }}</span>
                                    </span>
                                </button>
                            @endif
                        </div>
                    </div>

                    <div class="col-lg-7">
                        <div class="cabinet-esenin-text-view card shadow-sm mb-0">
                            <div class="card-body">
                                <div class="cabinet-esenin-text-view__wrap">
                                    <div class="cabinet-esenin-legend small text-secondary mb-3">
                                        {{ __('Text analyzer uniqueness text legend') }}
                                    </div>
                                    <div class="cabinet-esenin-text-view__content cabinet-esenin-text-view__content--readonly cabinet-ta-uniq-text"
                                         data-uniq-highlight>
                                        {!! $u['highlighted_html'] ?? nl2br(e($u['text'] ?? '')) !!}
                                    </div>
                                    <div class="small text-secondary mt-3">
                                        {{ __('Text analyzer uniqueness probes used', ['n' => (int) ($u['xml_requests'] ?? 0)]) }}
                                        · {{ __('Text uniqueness matched title') }}: {{ ($u['shingles_matched'] ?? 0) }} / {{ ($u['shingles_total'] ?? 0) }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-3">
                        <div class="card shadow-sm h-100 mb-0">
                            <div class="card-body">
                                <div data-uniq-panel="overview" class="cabinet-ta-uniq-side">
                                    <h6 class="fw-semibold mb-2">{{ __('Text analyzer uniq nav overview') }}</h6>
                                    <table class="table table-sm mb-0">
                                        <tbody>
                                        <tr>
                                            <td>{{ __('Text analyzer web uniqueness') }}</td>
                                            <td class="text-end fw-semibold">{{ $u['uniqueness_pct'] ?? 0 }}%</td>
                                        </tr>
                                        <tr>
                                            <td>{{ __('Text uniqueness matched title') }}</td>
                                            <td class="text-end">{{ $u['matched_pct'] ?? 0 }}%</td>
                                        </tr>
                                        <tr>
                                            <td>{{ __('Text analyzer own match') }}</td>
                                            <td class="text-end">{{ $u['own_match_pct'] ?? 0 }}%</td>
                                        </tr>
                                        <tr>
                                            <td>{{ __('Text analyzer uniqueness probes used', ['n' => (int) ($u['xml_requests'] ?? 0)]) }}</td>
                                            <td class="text-end">{{ (int) ($u['pages_fetched'] ?? 0) }} {{ __('Text analyzer uniq pages short') }}</td>
                                        </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <div data-uniq-panel="sources" class="cabinet-ta-uniq-side d-none">
                                    <h6 class="fw-semibold mb-2">{{ __('Text uniqueness sources title') }}</h6>
                                    @if(!empty($u['sources']))
                                        <ul class="list-unstyled cabinet-ta-uniq-source-list mb-0">
                                            @foreach($u['sources'] as $src)
                                                <li class="mb-2 pb-2 border-bottom">
                                                    @if(!empty($src['is_own']))
                                                        <span class="badge text-bg-primary mb-1">{{ __('Text analyzer own source badge') }}</span>
                                                    @endif
                                                    <div class="small fw-semibold">{{ ($src['overlap_pct'] ?? 0) }}%</div>
                                                    @if(!empty($src['url']))
                                                        <a class="small text-break" href="{{ $src['url'] }}" target="_blank" rel="noopener noreferrer">{{ $src['url'] }}</a>
                                                    @endif
                                                    @if(!empty($src['error']))
                                                        <div class="small text-danger">{{ __('Text analyzer page fetch failed') }}</div>
                                                    @elseif(!empty($src['samples']))
                                                        <div class="small text-muted mt-1">{{ implode(' · ', array_slice($src['samples'], 0, 3)) }}</div>
                                                    @endif
                                                </li>
                                            @endforeach
                                        </ul>
                                    @else
                                        <p class="small text-muted mb-0">{{ __('Text analyzer uniq no sources') }}</p>
                                    @endif
                                    @if(!empty($u['weak_sources']))
                                        <h6 class="fw-semibold mt-3 mb-2 text-muted">{{ __('Text analyzer weak sources title') }}</h6>
                                        <p class="small text-muted">{{ __('Text analyzer weak sources hint') }}</p>
                                        <ul class="list-unstyled mb-0">
                                            @foreach($u['weak_sources'] as $src)
                                                <li class="mb-2 small">
                                                    <span class="text-muted">{{ ($src['overlap_pct'] ?? 0) }}%</span>
                                                    @if(!empty($src['url']))
                                                        <a class="text-break d-block" href="{{ $src['url'] }}" target="_blank" rel="noopener noreferrer">{{ $src['url'] }}</a>
                                                    @endif
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </div>

                                <div data-uniq-panel="matches" class="cabinet-ta-uniq-side d-none">
                                    <h6 class="fw-semibold mb-2">{{ __('Text uniqueness matched title') }}</h6>
                                    @if(!empty($u['matched_samples']))
                                        <div class="cabinet-ta-uniq-chips">
                                            @foreach($u['matched_samples'] as $sample)
                                                <span class="cabinet-ta-uniq-chip">{{ $sample }}</span>
                                            @endforeach
                                        </div>
                                    @else
                                        <p class="small text-muted mb-0">{{ __('Text analyzer uniq no matches list') }}</p>
                                    @endif
                                </div>

                                <div data-uniq-panel="own" class="cabinet-ta-uniq-side d-none">
                                    <h6 class="fw-semibold mb-2">{{ __('Text analyzer uniq nav own') }}</h6>
                                    <p class="mb-1">
                                        <strong>{{ $u['own_match_pct'] ?? 0 }}%</strong>
                                        {{ __('Text analyzer own match') }}
                                    </p>
                                    @if(!empty($u['own_url']))
                                        <a class="small text-break" href="{{ $u['own_url'] }}" target="_blank" rel="noopener noreferrer">{{ $u['own_url'] }}</a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endif
