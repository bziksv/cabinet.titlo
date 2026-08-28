@php
    $u = $uniqueness ?? null;
    $e = $esenin ?? null;
    $hasUniq = !empty($u) && empty($u['error']);
    $hasUniqError = !empty($u['error']);
    $hasEsenin = !empty($e) && empty($e['error']);
    $hasEseninError = !empty($e['error']);
    $showCombined = $hasUniq || $hasUniqError || $hasEsenin || $hasEseninError;
@endphp
@if($showCombined)
    @php
        $blockLabels = [
            'risk' => __('Text analyzer esenin risk'),
            'frequency' => __('Text analyzer esenin block repeats'),
            'style' => __('Text analyzer esenin block style'),
            'keywords' => __('Text analyzer esenin block queries'),
            'formality' => __('Text analyzer esenin water'),
            'readability' => __('Text analyzer esenin readability'),
        ];
        $detailsByBlock = [];
        foreach ($e['details'] ?? [] as $row) {
            $code = (string) ($row['block'] ?? $row['code'] ?? '');
            if ($code !== '') {
                $detailsByBlock[$code] = $row;
            }
        }
        $scoreFor = function (string $code) use ($e, $detailsByBlock): int {
            if ($code === 'risk') {
                return (int) ($e['risk'] ?? 0);
            }
            if (isset($e['blocks'][$code]['score'])) {
                return (int) $e['blocks'][$code]['score'];
            }
            if (isset($detailsByBlock[$code])) {
                return (int) ($detailsByBlock[$code]['sum'] ?? $detailsByBlock[$code]['local_sum'] ?? 0);
            }

            return 0;
        };
        $badgeClass = function (int $score): string {
            if ($score >= 8) {
                return 'danger';
            }
            if ($score >= 5) {
                return 'warning';
            }

            return 'success';
        };
        $navOrder = ['risk', 'frequency', 'style', 'keywords', 'formality', 'readability'];
        $highlights = $e['highlights'] ?? [];
        $uniqPct = $u['uniqueness_pct'] ?? 0;
        $uniqBadge = ($uniqPct < 50) ? 'danger' : (($uniqPct < 85) ? 'warning' : 'success');
        $webSourcesCount = (int) ($u['web_sources_count'] ?? count(array_filter($u['sources'] ?? [], static function ($s) {
            return empty($s['is_own']);
        })));
        $defaultTab = $hasUniq ? 'uniqueness' : 'risk';
        if ($hasUniq) {
            $activeHtml = $u['highlighted_html'] ?? nl2br(e($u['text'] ?? ''));
        } else {
            $activeHtml = $highlights['risk'] ?? ($e['highlighted_html'] ?? nl2br(e($e['text'] ?? '')));
        }
        $plainText = (string) ($u['text'] ?? $e['text'] ?? '');
        $title = ($hasUniq || $hasUniqError) && ($hasEsenin || $hasEseninError)
            ? __('Text analyzer uniq esenin title')
            : (($hasUniq || $hasUniqError) ? __('Text uniqueness') : __('Esenin text check'));
    @endphp

    <div class="card shadow-sm mb-3 cabinet-ta-uniq-esenin cabinet-ta-esenin-like" id="cabinet-ta-uniq-esenin-results"
         data-cabinet-ta-combined
         data-default-tab="{{ $defaultTab }}">
        <div class="card-header py-2 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <h3 class="card-title h6 mb-0">
                <i class="fas fa-fingerprint me-1 text-primary"></i>{{ $title }}
            </h3>
            @if($hasEsenin && !empty($e['module_url']))
                <a href="{{ $e['module_url'] }}" class="small" target="_blank" rel="noopener">{{ __('Text analyzer esenin open full') }}</a>
            @endif
        </div>
        <div class="card-body">
            @if($hasUniqError)
                <div class="alert alert-warning py-2 mb-3">{{ $u['message'] ?? __('Text uniqueness fetch failed') }}</div>
            @endif
            @if($hasEseninError)
                <div class="alert alert-warning py-2 mb-3">{{ $e['message'] ?? __('Text analyzer esenin failed') }}</div>
            @endif

            @if($hasUniq && !empty($u['no_significant_matches']))
                <div class="alert alert-warning py-2 mb-3">
                    {{ __('Text analyzer uniqueness no matches warning', [
                        'probes' => (int) ($u['xml_requests'] ?? 0),
                        'pages' => (int) ($u['pages_fetched'] ?? 0),
                    ]) }}
                </div>
            @elseif($hasUniq && isset($u['own_match_pct']) && ($u['own_match_pct'] > 0 || !empty($u['own_url'])))
                <div class="alert alert-info py-2 mb-3">
                    {{ __('Text analyzer own match banner', [
                        'pct' => $u['own_match_pct'] ?? 0,
                        'url' => $u['own_url'] ?? '',
                    ]) }}
                </div>
            @endif

            @if($hasUniq || $hasEsenin)
                <div class="row g-3">
                    <div class="col-lg-2">
                        <div class="cabinet-esenin-score-nav" data-combined-nav>
                            @if($hasUniq)
                                <button type="button"
                                        class="cabinet-esenin-score-btn {{ $defaultTab === 'uniqueness' ? 'active' : '' }}"
                                        data-combined-tab="uniqueness"
                                        aria-pressed="{{ $defaultTab === 'uniqueness' ? 'true' : 'false' }}">
                                    <span class="cabinet-esenin-score-btn__title">
                                        {{ __('Text analyzer uniq nav overview') }}
                                        @include('text-analyse.partials.tip', [
                                            'tip' => __('Text analyzer uniq tip uniqueness scores'),
                                            'tipSide' => 'right',
                                        ])
                                    </span>
                                    <span class="cabinet-esenin-score-btn__value">
                                        {{ $uniqPct }}%
                                        <span class="badge text-bg-{{ $uniqBadge }} cabinet-esenin-score-btn__badge">
                                            {{ $u['matched_pct'] ?? 0 }}%
                                        </span>
                                    </span>
                                </button>
                            @endif

                            @if($hasEsenin)
                                @foreach($navOrder as $code)
                                    @php $sc = $scoreFor($code); @endphp
                                    <button type="button"
                                            class="cabinet-esenin-score-btn {{ $defaultTab === $code ? 'active' : '' }}"
                                            data-combined-tab="{{ $code }}"
                                            aria-pressed="{{ $defaultTab === $code ? 'true' : 'false' }}">
                                        <span class="cabinet-esenin-score-btn__title">{{ $blockLabels[$code] ?? $code }}</span>
                                        <span class="cabinet-esenin-score-btn__value">
                                            {{ $sc }}
                                            <span class="badge text-bg-{{ $badgeClass($sc) }} cabinet-esenin-score-btn__badge">
                                                {{ $code === 'risk' ? ($e['level'] ?? '') : $sc }}
                                            </span>
                                        </span>
                                    </button>
                                @endforeach
                            @endif
                        </div>
                    </div>

                    <div class="col-lg-7">
                        <div class="cabinet-esenin-text-view card shadow-sm mb-0">
                            <div class="card-body">
                                <div class="cabinet-esenin-text-view__wrap">
                                    <div class="cabinet-esenin-legend small text-secondary mb-3" data-combined-legend>
                                        @if($defaultTab === 'uniqueness')
                                            {{ __('Text analyzer uniqueness text legend') }}
                                        @else
                                            {{ __('Text analyzer esenin text legend') }}
                                        @endif
                                    </div>
                                    <div class="cabinet-esenin-text-view__content cabinet-esenin-text-view__content--editable"
                                         data-combined-highlight
                                         contenteditable="true"
                                         spellcheck="true"
                                         aria-label="{{ __('Text analyzer combined edit label') }}">
                                        {!! $activeHtml !!}
                                    </div>
                                    <div class="small text-secondary mt-2" data-combined-footer>
                                        @if($hasUniq && $defaultTab === 'uniqueness')
                                            {{ __('Text analyzer uniqueness probes used', ['n' => (int) ($u['xml_requests'] ?? 0)]) }}
                                            · {{ __('Text uniqueness matched title') }}: {{ ($u['shingles_matched'] ?? 0) }} / {{ ($u['shingles_total'] ?? 0) }}
                                        @elseif($hasEsenin && !empty($e['stats']))
                                            {{ __('Number of characters') }}: {{ number_format((int) ($e['stats']['chars'] ?? 0), 0, ',', ' ') }}
                                            · {{ __('Number of words') }}: {{ number_format((int) ($e['stats']['words'] ?? 0), 0, ',', ' ') }}
                                        @endif
                                    </div>
                                    <p class="small text-muted mb-0 mt-2">{{ __('Text analyzer combined edit hint') }}</p>
                                </div>
                            </div>
                        </div>
                        @if($hasUniq)
                            <script type="application/json" id="cabinet-ta-uniq-highlight">{!! json_encode([
                                'html' => $u['highlighted_html'] ?? '',
                                'text' => $u['text'] ?? $plainText,
                                'legend' => (string) __('Text analyzer uniqueness text legend'),
                                'footer' => (string) __('Text analyzer uniqueness probes used', ['n' => (int) ($u['xml_requests'] ?? 0)])
                                    . ' · ' . (string) __('Text uniqueness matched title') . ': '
                                    . (int) ($u['shingles_matched'] ?? 0) . ' / ' . (int) ($u['shingles_total'] ?? 0),
                            ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!}</script>
                        @endif
                        @if($hasEsenin)
                            <script type="application/json" id="cabinet-ta-esenin-highlights">{!! json_encode($highlights, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!}</script>
                            <script type="application/json" id="cabinet-ta-esenin-meta">{!! json_encode([
                                'legend' => (string) __('Text analyzer esenin text legend'),
                                'fallback' => (string) ($e['highlighted_html'] ?? ''),
                                'text' => (string) ($e['text'] ?? $plainText),
                                'stats_footer' => !empty($e['stats'])
                                    ? ((string) __('Number of characters') . ': ' . number_format((int) ($e['stats']['chars'] ?? 0), 0, ',', ' ')
                                        . ' · ' . (string) __('Number of words') . ': ' . number_format((int) ($e['stats']['words'] ?? 0), 0, ',', ' '))
                                    : '',
                            ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!}</script>
                        @endif
                    </div>

                    <div class="col-lg-3">
                        <div class="card shadow-sm h-100 mb-0">
                            <div class="card-body d-flex flex-column">
                                <h6 class="fw-semibold mb-2" data-combined-side-title>
                                    @if($defaultTab === 'uniqueness')
                                        {{ __('Text analyzer uniq nav overview') }}
                                    @else
                                        {{ $blockLabels[$defaultTab] ?? __('Text analyzer esenin risk') }}
                                    @endif
                                </h6>

                                <div data-combined-side="uniqueness" class="{{ $defaultTab === 'uniqueness' ? '' : 'd-none' }}">
                                    @if($hasUniq)
                                        <table class="table table-sm mb-3">
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

                                        <div class="cabinet-ta-combined-right-uniq mb-3" data-combined-right-uniq>
                                            <div class="cabinet-ta-side-stat mb-2">
                                                <div class="cabinet-ta-side-stat__title">
                                                    {{ __('Text analyzer uniq nav sources') }}
                                                    @include('text-analyse.partials.tip', [
                                                        'tip' => __('Text analyzer uniq tip sources'),
                                                        'tipSide' => 'left',
                                                    ])
                                                </div>
                                                <div class="cabinet-ta-side-stat__value">
                                                    <span>{{ $webSourcesCount }}</span>
                                                    <span class="badge text-bg-secondary">{{ __('Text analyzer uniq sources short') }}</span>
                                                </div>
                                            </div>
                                            <div class="cabinet-ta-side-stat mb-2">
                                                <div class="cabinet-ta-side-stat__title">
                                                    {{ __('Text analyzer uniq nav matches') }}
                                                    @include('text-analyse.partials.tip', [
                                                        'tip' => __('Text analyzer uniq tip matches'),
                                                        'tipSide' => 'left',
                                                    ])
                                                </div>
                                                <div class="cabinet-ta-side-stat__value">
                                                    <span>{{ (int) ($u['shingles_matched'] ?? 0) }}</span>
                                                    <span class="badge text-bg-danger">/ {{ (int) ($u['shingles_total'] ?? 0) }}</span>
                                                </div>
                                            </div>
                                        </div>

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

                                        @if(!empty($u['matched_samples']))
                                            <h6 class="fw-semibold mt-3 mb-2">{{ __('Text uniqueness matched title') }}</h6>
                                            <div class="cabinet-ta-uniq-chips">
                                                @foreach(array_slice($u['matched_samples'], 0, 24) as $sample)
                                                    <span class="cabinet-ta-uniq-chip">{{ $sample }}</span>
                                                @endforeach
                                            </div>
                                        @endif
                                    @endif
                                </div>

                                <div data-combined-side="esenin" class="{{ $defaultTab !== 'uniqueness' ? '' : 'd-none' }}">
                                    @if($hasEsenin)
                                        <div class="table-responsive">
                                            <table class="table table-sm mb-0">
                                                <tbody>
                                                @if(isset($e['metrics']['academic_nausea']))
                                                    <tr>
                                                        <td>{{ __('Text analyzer esenin nausea') }}</td>
                                                        <td class="text-end">{{ $e['metrics']['academic_nausea'] }}</td>
                                                    </tr>
                                                @endif
                                                @if(isset($e['metrics']['wateriness']))
                                                    <tr>
                                                        <td>{{ __('Text analyzer esenin water') }}</td>
                                                        <td class="text-end">{{ $e['metrics']['wateriness'] }}</td>
                                                    </tr>
                                                @endif
                                                @if(isset($e['metrics']['readability_index']))
                                                    <tr>
                                                        <td>{{ __('Text analyzer esenin readability') }}</td>
                                                        <td class="text-end">{{ $e['metrics']['readability_index'] }}</td>
                                                    </tr>
                                                @endif
                                                @if(isset($e['metrics']['informative_share']))
                                                    <tr>
                                                        <td>{{ __('Text analyzer esenin informative') }}</td>
                                                        <td class="text-end">{{ $e['metrics']['informative_share'] }}</td>
                                                    </tr>
                                                @endif
                                                @foreach($e['details'] ?? [] as $row)
                                                    <tr>
                                                        <td>{{ $row['label'] ?? ($row['block'] ?? '—') }}</td>
                                                        <td class="text-end">{{ $row['sum'] ?? ($row['local_sum'] ?? 0) }}</td>
                                                    </tr>
                                                @endforeach
                                                </tbody>
                                            </table>
                                        </div>
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
