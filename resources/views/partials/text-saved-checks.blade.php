@php
    $canSaveUniquenessHistory = !empty($canSaveUniquenessHistory);
    $uniquenessHistories = $uniquenessHistories ?? collect();
    $histCount = (int) ($uniquenessHistoryCount ?? (is_countable($uniquenessHistories) ? count($uniquenessHistories) : 0));
    $histLimit = (int) ($uniquenessHistoryLimit ?? 0);
    $historyBaseUrl = $historyBaseUrl ?? url('/text-analyzer/uniqueness-history');
    $historyCsrf = $historyCsrf ?? csrf_token();
    $historyModule = $historyModule ?? 'text-analyzer';
@endphp
@if($canSaveUniquenessHistory)
    <div class="card shadow-sm mb-3 cabinet-text-saved-checks"
         data-cabinet-saved-checks
         data-history-url="{{ $historyBaseUrl }}"
         data-csrf="{{ $historyCsrf }}"
         data-module="{{ $historyModule }}">
        <div class="card-header py-2 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <h3 class="card-title h6 mb-0">{{ __('Text uniqueness history title') }}</h3>
            @if($histLimit > 0)
                <span class="small text-muted" data-cabinet-saved-checks-count>{{ $histCount }} / {{ $histLimit }}</span>
            @endif
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0">
                    <thead>
                    <tr>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('Title') }}</th>
                        <th class="text-nowrap">{{ __('Text uniqueness') }}</th>
                        <th class="text-nowrap">{{ __('Esenin text check') }}</th>
                        <th class="text-nowrap">{{ __('Number of characters') }}</th>
                        <th class="text-nowrap">{{ __('Number of words') }}</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody data-cabinet-saved-checks-body>
                    @forelse($uniquenessHistories as $h)
                        @php
                            $hp = is_array($h->params) ? $h->params : [];
                            $hChars = $hp['chars'] ?? ($hp['general']['textLength'] ?? null);
                            $hWords = $hp['words'] ?? ($hp['general']['countWordsAll'] ?? null);
                            $hEsenin = $hp['esenin_risk'] ?? null;
                            $hEseninLevel = $hp['esenin_level'] ?? '';
                            $hUniqNd = !empty($hp['no_significant_matches']);
                            $hHadUniq = array_key_exists('had_uniqueness', $hp)
                                ? (bool) $hp['had_uniqueness']
                                : true;
                            $hUniqPct = $hp['uniqueness_pct'] ?? $h->uniqueness_pct;
                            $hSource = (string) ($hp['source'] ?? '');
                        @endphp
                        <tr data-id="{{ $h->id }}" data-source="{{ $hSource }}">
                            <td class="text-nowrap">{{ optional($h->created_at)->format('d.m.Y H:i') }}</td>
                            <td>{{ $h->title }}</td>
                            <td class="text-nowrap">
                                @if(! $hHadUniq)
                                    —
                                @elseif($hUniqNd)
                                    {{ __('Text analyzer uniqueness nd') }}
                                @else
                                    {{ number_format((float) $hUniqPct, 1, ',', ' ') }}%
                                @endif
                            </td>
                            <td class="text-nowrap">
                                @if($hEsenin === null || $hEsenin === '')
                                    —
                                @else
                                    {{ (int) $hEsenin }}@if($hEseninLevel !== '') <span class="text-muted small">{{ $hEseninLevel }}</span>@endif
                                @endif
                            </td>
                            <td class="text-nowrap">{{ $hChars !== null ? number_format((int) $hChars, 0, '', ' ') : '—' }}</td>
                            <td class="text-nowrap">{{ $hWords !== null ? number_format((int) $hWords, 0, '', ' ') : '—' }}</td>
                            <td class="text-nowrap">
                                <button type="button" class="btn btn-xs btn-outline-primary cabinet-ta-uniq-history-open">{{ __('Open') }}</button>
                                <button type="button" class="btn btn-xs btn-outline-danger cabinet-ta-uniq-history-del">{{ __('Delete') }}</button>
                            </td>
                        </tr>
                    @empty
                        <tr data-cabinet-saved-checks-empty>
                            <td colspan="7" class="text-secondary text-center py-3">{{ __('Text uniqueness history empty') }}</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="d-none" id="cabinet-ta-uniq-history-panel" data-cabinet-saved-checks-panel></div>
@endif
