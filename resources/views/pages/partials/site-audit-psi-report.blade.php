{{-- Rich PageSpeed Insights — по-русски, явно телефон / компьютер --}}
@php
    use App\Services\SiteAudit\SiteAuditPsiMetrics;

    $psiRows = $rows ?? collect();
    $psiSummary = SiteAuditPsiMetrics::summarize($psiRows);
    $avgBand = SiteAuditPsiMetrics::scoreBand($psiSummary['avg']);
    $isDesktop = ($code ?? '') === 'psi_desktop';
    $strategyLabel = SiteAuditPsiMetrics::strategyLabelRu($code ?? 'psi_mobile');
    $strategyHint = SiteAuditPsiMetrics::strategyHintRu($code ?? 'psi_mobile');
    $psiGoogleBase = 'https://pagespeed.web.dev/analysis?url=';
    $showActions = !empty($canNote) || !empty($canIgnore);
    $psiMobileUrl = !empty($crawl)
        ? route('pages.site-audit.report.show', [$crawl->id, 'psi_mobile'])
        : null;
    $psiDesktopUrl = !empty($crawl)
        ? route('pages.site-audit.report.show', [$crawl->id, 'psi_desktop'])
        : null;
    $psiCountSource = is_array($sideCounts ?? null) ? $sideCounts : (is_array($counts ?? null) ? $counts : []);
    $psiMobileCount = (int) ($psiCountSource['psi_mobile'] ?? 0);
    $psiDesktopCount = (int) ($psiCountSource['psi_desktop'] ?? 0);
@endphp

@if($psiRows->isEmpty())
    <div class="text-muted px-3 py-3">
        @if(!empty($probeSkipped))
            Находок нет — проверка ещё не запускалась. Нажмите «Запустить» выше.
        @else
            Находок нет — проверка выполнена, замечаний по этому отчёту нет.
        @endif
    </div>
@else
    <div class="cabinet-sa-psi">
        <div class="cabinet-sa-psi-hero">
            <div class="cabinet-sa-psi-hero__score cabinet-sa-psi-band--{{ $avgBand }}">
                <div class="cabinet-sa-psi-ring" style="--psi: {{ (int) ($psiSummary['avg'] ?? 0) }}">
                    <span class="cabinet-sa-psi-ring__val">{{ $psiSummary['avg'] !== null ? $psiSummary['avg'] : '—' }}</span>
                    <span class="cabinet-sa-psi-ring__lbl">балл</span>
                </div>
                <div class="cabinet-sa-psi-hero__meta">
                    @if($psiMobileUrl && $psiDesktopUrl)
                        <div class="cabinet-sa-psi-switch" role="tablist" aria-label="Устройство замера">
                            <a class="cabinet-sa-psi-switch__btn {{ ! $isDesktop ? 'is-active' : '' }}"
                               href="{{ $psiMobileUrl }}"
                               role="tab"
                               aria-selected="{{ ! $isDesktop ? 'true' : 'false' }}">
                                Телефон
                                @if($psiMobileCount > 0)
                                    <span class="cabinet-sa-psi-switch__n">{{ $psiMobileCount }}</span>
                                @endif
                            </a>
                            <a class="cabinet-sa-psi-switch__btn {{ $isDesktop ? 'is-active' : '' }}"
                               href="{{ $psiDesktopUrl }}"
                               role="tab"
                               aria-selected="{{ $isDesktop ? 'true' : 'false' }}">
                                Компьютер
                                @if($psiDesktopCount > 0)
                                    <span class="cabinet-sa-psi-switch__n">{{ $psiDesktopCount }}</span>
                                @endif
                            </a>
                        </div>
                    @else
                        <div class="cabinet-sa-psi-device {{ $isDesktop ? 'is-desktop' : 'is-mobile' }}">
                            {{ $isDesktop ? 'Компьютер' : 'Телефон' }}
                            <span class="cabinet-sa-psi-device__eng">{{ $isDesktop ? 'desktop' : 'mobile' }}</span>
                        </div>
                    @endif
                    <div class="cabinet-sa-psi-hero__title">
                        Средняя скорость · {{ $strategyLabel }}
                    </div>
                    <div class="cabinet-sa-psi-hero__sub">
                        {{ $strategyHint }}
                        Замерено URL: <strong>{{ $psiSummary['total'] }}</strong>
                        (лаборатория Google PageSpeed Insights / Lighthouse).
                    </div>
                    <div class="cabinet-sa-psi-hero__chips">
                        <span class="cabinet-sa-psi-chip cabinet-sa-psi-chip--good">{{ $psiSummary['good'] }} отлично (90+)</span>
                        <span class="cabinet-sa-psi-chip cabinet-sa-psi-chip--mid">{{ $psiSummary['mid'] }} улучшить (50–89)</span>
                        <span class="cabinet-sa-psi-chip cabinet-sa-psi-chip--poor">{{ $psiSummary['poor'] }} плохо (0–49)</span>
                    </div>
                    @if(!empty($psiSummary['worst_url']) && $psiSummary['worst_pct'] !== null)
                        <div class="cabinet-sa-psi-hero__worst">
                            Самая слабая страница: <strong>{{ $psiSummary['worst_pct'] }}</strong>
                            · <a href="{{ $psiSummary['worst_url'] }}" target="_blank" rel="noopener noreferrer">{{ \Illuminate\Support\Str::limit($psiSummary['worst_url'], 64) }}</a>
                        </div>
                    @endif
                </div>
            </div>
            <div class="cabinet-sa-psi-legend">
                <div class="cabinet-sa-psi-legend__title">Как читать цвета</div>
                <div class="cabinet-sa-psi-legend__row"><span class="cabinet-sa-psi-dot cabinet-sa-psi-dot--good"></span> зелёный — норма Google</div>
                <div class="cabinet-sa-psi-legend__row"><span class="cabinet-sa-psi-dot cabinet-sa-psi-dot--mid"></span> жёлтый — нужно улучшить</div>
                <div class="cabinet-sa-psi-legend__row"><span class="cabinet-sa-psi-dot cabinet-sa-psi-dot--poor"></span> красный — плохо, чинить первым</div>
                <div class="cabinet-sa-psi-legend__note">
                    @if($isDesktop)
                        Сейчас открыт компьютер.
                        @if($psiMobileUrl)
                            <a href="{{ $psiMobileUrl }}">Перейти к телефону →</a>
                        @endif
                    @else
                        Сейчас открыт телефон.
                        @if($psiDesktopUrl)
                            <a href="{{ $psiDesktopUrl }}">Перейти к компьютеру →</a>
                        @endif
                    @endif
                </div>
            </div>
        </div>

        <div class="cabinet-sa-psi-list">
            @foreach($psiRows as $row)
                @php
                    $meta = is_array($row->meta_json ?? null) ? $row->meta_json : [];
                    $pct = isset($meta['score_pct'])
                        ? (int) $meta['score_pct']
                        : (isset($meta['score']) ? (int) round(((float) $meta['score']) * 100) : null);
                    $band = SiteAuditPsiMetrics::scoreBand($pct);
                    $url = (string) ($row->url ?? '');
                    $path = $url;
                    $pu = parse_url($url);
                    if (is_array($pu)) {
                        $path = ($pu['path'] ?? '/') . (isset($pu['query']) ? '?' . $pu['query'] : '');
                        if ($path === '') {
                            $path = '/';
                        }
                    }
                    $opps = is_array($meta['opportunities'] ?? null) ? $meta['opportunities'] : [];
                    $diags = is_array($meta['diagnostics'] ?? null) ? $meta['diagnostics'] : [];
                    $hasRich = !empty($meta['rich']) || $opps !== [] || isset($meta['accessibility_pct']);
                    $cats = SiteAuditPsiMetrics::categoryCards($meta, $pct);
                    $cwv = SiteAuditPsiMetrics::metricCards($meta);
                    $field = is_array($meta['field'] ?? null) ? $meta['field'] : null;
                    $originField = is_array($meta['origin_field'] ?? null) ? $meta['origin_field'] : null;
                    $isIgn = !empty($ignoredMap[(int) ($row->id ?? 0)]);
                    $note = $notesMap[(int) ($row->id ?? 0)] ?? null;
                    $isFixed = is_array($note) && (($note['status'] ?? '') === 'fixed');
                    $noteComment = is_array($note) ? (string) ($note['comment'] ?? '') : '';
                    $cruxLabels = [
                        'LARGEST_CONTENTFUL_PAINT_MS' => 'LCP',
                        'CUMULATIVE_LAYOUT_SHIFT_SCORE' => 'CLS',
                        'INTERACTION_TO_NEXT_PAINT' => 'INP',
                        'FIRST_CONTENTFUL_PAINT_MS' => 'FCP',
                        'EXPERIMENTAL_TIME_TO_FIRST_BYTE' => 'TTFB',
                    ];
                    $psiUrl = $psiGoogleBase . rawurlencode($url) . '&form_factor=' . ($isDesktop ? 'desktop' : 'mobile');
                @endphp
                <article class="cabinet-sa-psi-card cabinet-sa-psi-band--{{ $band }}{{ $isIgn ? ' is-ignored' : '' }}{{ $isFixed ? ' is-fixed' : '' }}">
                    <div class="cabinet-sa-psi-card__top">
                        <div class="cabinet-sa-psi-card__score">
                            <div class="cabinet-sa-psi-ring cabinet-sa-psi-ring--sm" style="--psi: {{ (int) ($pct ?? 0) }}">
                                <span class="cabinet-sa-psi-ring__val">{{ $pct !== null ? $pct : '—' }}</span>
                            </div>
                            <span class="cabinet-sa-psi-card__band">{{ SiteAuditPsiMetrics::bandLabel($band) }}</span>
                        </div>
                        <div class="cabinet-sa-psi-card__url">
                            <div class="cabinet-sa-psi-card__device">
                                {{ $strategyLabel }}
                                <span>· балл скорости {{ $pct !== null ? $pct : '—' }} / 100</span>
                            </div>
                            <a class="cabinet-sa-psi-card__path" href="{{ $url }}" target="_blank" rel="noopener noreferrer" title="{{ $url }}">{{ $path }}</a>
                            <div class="cabinet-sa-psi-card__cats">
                                @foreach($cats as $c)
                                    @if($c['v'] !== null)
                                        <span class="cabinet-sa-psi-cat cabinet-sa-psi-band--{{ $c['band'] }}" title="{{ $c['name'] }}">
                                            {{ $c['name'] }} {{ $c['v'] }}
                                        </span>
                                    @endif
                                @endforeach
                                @if($isIgn)
                                    <span class="badge text-bg-light border">игнор</span>
                                @endif
                                @if($isFixed)
                                    <span class="badge text-bg-success">исправлено</span>
                                @endif
                            </div>
                        </div>
                        <div class="cabinet-sa-psi-card__links">
                            <a class="cabinet-sa-psi-ext" href="{{ $psiUrl }}" target="_blank" rel="noopener noreferrer" title="Открыть тот же URL в Google PageSpeed ({{ $strategyLabel }})">
                                Открыть в Google ↗
                            </a>
                        </div>
                    </div>

                    <div class="cabinet-sa-psi-metrics">
                        @foreach($cwv as $m)
                            <div class="cabinet-sa-psi-metric cabinet-sa-psi-band--{{ $m['band'] }}" title="{{ $m['tip'] }}{{ $m['limit'] !== '' ? ' · ' . $m['limit'] : '' }}">
                                <span class="cabinet-sa-psi-metric__k">{{ $m['k'] }}</span>
                                <span class="cabinet-sa-psi-metric__name">{{ $m['name'] }}</span>
                                <span class="cabinet-sa-psi-metric__v">{{ $m['v'] }}</span>
                                <span class="cabinet-sa-psi-metric__b">
                                    {{ SiteAuditPsiMetrics::bandLabel($m['band']) }}
                                    @if($m['limit'] !== '')
                                        <span class="cabinet-sa-psi-metric__lim">{{ $m['limit'] }}</span>
                                    @endif
                                </span>
                            </div>
                        @endforeach
                    </div>

                    @if($field || $originField)
                        @php $crux = $field ?: $originField; @endphp
                        <div class="cabinet-sa-psi-field">
                            <span class="cabinet-sa-psi-field__lbl">
                                Реальные пользователи (CrUX{{ !$field && $originField ? ', по всему сайту' : '' }})
                            </span>
                            @if(!empty($crux['overall']))
                                <span class="cabinet-sa-psi-chip">итого: {{ SiteAuditPsiMetrics::cruxCategoryRu($crux['overall']) }}</span>
                            @endif
                            @foreach(($crux['metrics'] ?? []) as $mk => $mv)
                                <span class="cabinet-sa-psi-chip" title="{{ $mk }}">
                                    {{ $cruxLabels[$mk] ?? $mk }}
                                    @if(($mv['percentile'] ?? null) !== null)
                                        @if(($cruxLabels[$mk] ?? '') === 'CLS')
                                            {{ number_format((float) $mv['percentile'], 3, ',', '') }}
                                        @else
                                            {{ SiteAuditPsiMetrics::formatMs((float) $mv['percentile']) }}
                                        @endif
                                    @endif
                                    @if(!empty($mv['category']))
                                        · {{ SiteAuditPsiMetrics::cruxCategoryRu($mv['category']) }}
                                    @endif
                                </span>
                            @endforeach
                        </div>
                    @endif

                    @if($opps !== [] || $diags !== [])
                        <details class="cabinet-sa-psi-details" @if($band === 'poor' || $band === 'needs-improvement') open @endif>
                            <summary>
                                Что ускорить
                                @if($opps !== [])
                                    · {{ count($opps) }} рекомендаций
                                @endif
                                @if($diags !== [])
                                    · {{ count($diags) }} диагностик
                                @endif
                            </summary>
                            @if($opps !== [])
                                <p class="cabinet-sa-psi-details__intro">Рекомендации Lighthouse с оценкой выигрыша по времени и весу страницы:</p>
                                <ul class="cabinet-sa-psi-opp">
                                    @foreach($opps as $op)
                                        @php
                                            $opId = (string) ($op['id'] ?? '');
                                            $opTitle = SiteAuditPsiMetrics::opportunityTitleRu($opId, $op['title'] ?? null);
                                            $bytesRu = SiteAuditPsiMetrics::formatBytesRu(isset($op['savings_bytes']) ? (int) $op['savings_bytes'] : null);
                                            $dispRu = SiteAuditPsiMetrics::formatDisplayRu($op['display'] ?? null);
                                        @endphp
                                        <li>
                                            <span class="cabinet-sa-psi-opp__title">{{ $opTitle }}</span>
                                            <span class="cabinet-sa-psi-opp__save">
                                                @if(!empty($op['savings_ms']))
                                                    выигрыш ≈ {{ SiteAuditPsiMetrics::formatMs((float) $op['savings_ms']) }}
                                                @endif
                                                @if($bytesRu !== '')
                                                    · −{{ $bytesRu }}
                                                @endif
                                                @if($dispRu !== '')
                                                    · {{ $dispRu }}
                                                @endif
                                            </span>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                            @if($diags !== [])
                                <p class="cabinet-sa-psi-details__intro">Дополнительная диагностика:</p>
                                <ul class="cabinet-sa-psi-diag">
                                    @foreach($diags as $d)
                                        <li>
                                            <span>{{ SiteAuditPsiMetrics::opportunityTitleRu((string) ($d['id'] ?? ''), $d['title'] ?? null) }}</span>
                                            @if(!empty($d['display']))
                                                <span class="text-muted">{{ SiteAuditPsiMetrics::formatDisplayRu($d['display']) }}</span>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </details>
                    @elseif(!$hasRich)
                        <div class="cabinet-sa-psi-legacy">
                            {{ SiteAuditPsiMetrics::compactLine($meta) }}
                            <span class="text-muted">· полный разбор по-русски (рекомендации, доступность, SEO) появится после следующего прогона PageSpeed</span>
                        </div>
                    @endif

                    @if($showActions && !empty($row->id))
                        <div class="cabinet-sa-psi-card__actions">
                            @if(!empty($canNote) && $noteComment !== '')
                                <div class="cabinet-sa-note-text">{{ $noteComment }}</div>
                            @endif
                            <div class="cabinet-sa-actions">
                                @if(!empty($canNote))
                                    <div class="cabinet-sa-act cabinet-sa-act--note">
                                        <label class="cabinet-sa-act__main" for="sa-note-{{ (int) $row->id }}">
                                            <i class="fa fa-comment" aria-hidden="true"></i><span>Заметка</span>
                                        </label>
                                    </div>
                                    @if(!$isFixed)
                                        <form method="POST" action="{{ route('pages.site-audit.note', $crawl->id) }}" class="cabinet-sa-act-form">
                                            @csrf
                                            <input type="hidden" name="return_url" value="{{ request()->fullUrl() }}">
                                            <input type="hidden" name="finding_id" value="{{ $row->id }}">
                                            <input type="hidden" name="comment" value="{{ $noteComment }}">
                                            <div class="cabinet-sa-act cabinet-sa-act--fixed">
                                                <button type="submit" name="status" value="fixed" class="cabinet-sa-act__main">
                                                    <i class="fa fa-check" aria-hidden="true"></i><span>Исправлено</span>
                                                </button>
                                            </div>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('pages.site-audit.note', $crawl->id) }}" class="cabinet-sa-act-form">
                                            @csrf
                                            <input type="hidden" name="return_url" value="{{ request()->fullUrl() }}">
                                            <input type="hidden" name="finding_id" value="{{ $row->id }}">
                                            <input type="hidden" name="comment" value="{{ $noteComment }}">
                                            <div class="cabinet-sa-act cabinet-sa-act--open">
                                                <button type="submit" name="status" value="open" class="cabinet-sa-act__main">
                                                    <i class="fa fa-undo" aria-hidden="true"></i><span>Открыть</span>
                                                </button>
                                            </div>
                                        </form>
                                    @endif
                                @endif
                                @if(!empty($canIgnore))
                                    @if($isIgn)
                                        <form method="POST" action="{{ route('pages.site-audit.ignore.restore', $crawl->id) }}" class="cabinet-sa-act-form">
                                            @csrf
                                            <input type="hidden" name="return_url" value="{{ request()->fullUrl() }}">
                                            <input type="hidden" name="finding_id" value="{{ $row->id }}">
                                            <div class="cabinet-sa-act cabinet-sa-act--restore">
                                                <button type="submit" class="cabinet-sa-act__main">
                                                    <i class="fa fa-undo" aria-hidden="true"></i><span>Вернуть</span>
                                                </button>
                                            </div>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('pages.site-audit.ignore', $crawl->id) }}" class="cabinet-sa-act-form">
                                            @csrf
                                            <input type="hidden" name="return_url" value="{{ request()->fullUrl() }}">
                                            <input type="hidden" name="finding_id" value="{{ $row->id }}">
                                            <div class="cabinet-sa-act cabinet-sa-act--ignore">
                                                <button type="submit" class="cabinet-sa-act__main">
                                                    <i class="fa fa-ban" aria-hidden="true"></i><span>Игнор</span>
                                                </button>
                                            </div>
                                        </form>
                                    @endif
                                @endif
                                @if(!empty($canNote))
                                    <input type="checkbox" class="cabinet-sa-note-toggle" id="sa-note-{{ (int) $row->id }}">
                                    <div class="cabinet-sa-note-panel">
                                        <form method="POST" action="{{ route('pages.site-audit.note', $crawl->id) }}" class="cabinet-sa-note-form">
                                            @csrf
                                            <input type="hidden" name="return_url" value="{{ request()->fullUrl() }}">
                                            <input type="hidden" name="finding_id" value="{{ $row->id }}">
                                            <textarea name="comment" rows="2" class="form-control form-control-sm"
                                                      placeholder="Текст заметки…">{{ $noteComment }}</textarea>
                                            <button type="submit" name="status" value="{{ $isFixed ? 'fixed' : 'open' }}" class="btn btn-sm btn-primary mt-1">Сохранить</button>
                                        </form>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif
                </article>
            @endforeach
        </div>
    </div>
@endif
