@component('component.card', ['title' => __('Balance')])
    @slot('css')
        <link rel="stylesheet" href="{{ asset('css/cabinet-balance.css') }}?v={{ @filemtime(public_path('css/cabinet-balance.css')) ?: time() }}">
    @endslot

    @php
        $user = Auth::user();
        $balanceFormatted = number_format((float) $user->balance, 0, '.', ' ');
    @endphp

    <div class="cabinet-balance-page">
        <div class="row g-3 mb-4 align-items-stretch cabinet-balance-stats">
            <div class="col-12 col-md-4 d-flex">
                <div class="info-box mb-0 flex-fill">
                    <span class="info-box-icon text-bg-success shadow-sm">
                        <i class="bi bi-wallet2"></i>
                    </span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ __('Your balance') }}</span>
                        <span class="info-box-number">{{ $balanceFormatted }} ₽</span>
                        <span class="info-box-meta invisible" aria-hidden="true">&nbsp;</span>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4 d-flex">
                <div class="info-box mb-0 flex-fill">
                    <span class="info-box-icon text-bg-primary shadow-sm">
                        <i class="bi bi-arrow-down-circle"></i>
                    </span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ __('Top-ups') }}</span>
                        <span class="info-box-number">{{ $topUpsCount }}</span>
                        <span class="info-box-meta invisible" aria-hidden="true">&nbsp;</span>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4 d-flex">
                <div class="info-box mb-0 flex-fill">
                    <span class="info-box-icon text-bg-secondary shadow-sm">
                        <i class="bi bi-clock-history"></i>
                    </span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ __('Last operation') }}</span>
                        <span class="info-box-number">
                            @if($lastTopUp)
                                +{{ number_format((float) $lastTopUp->sum, 0, '.', ' ') }} ₽
                            @else
                                —
                            @endif
                        </span>
                        <span class="info-box-meta text-secondary">
                            @if($lastTopUp)
                                {{ $lastTopUp->created_at->diffForHumans() }}
                            @else
                                —
                            @endif
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card card-outline card-success mb-4">
            <div class="card-header">
                <h3 class="card-title mb-0">
                    <i class="bi bi-plus-circle me-1"></i>{{ __('Top up your balance') }}
                </h3>
            </div>
            {!! Form::open(['method' => 'POST', 'route' => ['balance-add.store']]) !!}
            <div class="card-body">
                <p class="text-secondary small mb-3">
                    {{ __('Funds are credited after successful payment. You can choose a tariff after topping up.') }}
                    <a href="{{ route('tariff.index') }}" class="text-nowrap">{{ __('Tariffs') }}</a>
                </p>

                @if(!empty($promoLock['locked']))
                    <div class="alert alert-warning py-2 px-3 mb-3 small">
                        <i class="bi bi-shield-lock me-1"></i>
                        {{ __('Promo lock active', ['until' => $promoLock['locked_until_human']]) }}
                    </div>
                @endif

                <div class="row g-3 align-items-start cabinet-balance-topup-fields">
                    <div class="col-12 col-lg-4">
                        {!! Form::label('sum', __('Sum'), ['class' => 'form-label']) !!}
                        <div class="input-group input-group-lg">
                            {!! Form::number('sum', old('sum'), [
                                'class' => 'form-control' . ($errors->has('sum') ? ' is-invalid' : ''),
                                'min' => '1',
                                'step' => '1',
                                'id' => 'balance-sum',
                                'placeholder' => '1000',
                            ]) !!}
                            <span class="input-group-text">₽</span>
                        </div>
                        @error('sum')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-12 col-lg-5">
                        <label class="form-label" for="balance-promo-code">
                            <i class="bi bi-ticket-perforated me-1"></i>{{ __('Balance promo label') }}
                        </label>
                        <div class="input-group input-group-lg cabinet-balance-promo-input">
                            <input type="text"
                                   name="promo_code"
                                   id="balance-promo-code"
                                   class="form-control text-uppercase {{ $errors->has('promo_code') ? 'is-invalid' : '' }}"
                                   value="{{ old('promo_code') }}"
                                   maxlength="64"
                                   placeholder="{{ __('Balance promo placeholder') }}"
                                   autocomplete="off"
                                   spellcheck="false"
                                   @if(!empty($promoLock['locked'])) disabled @endif>
                            <button type="button"
                                    class="btn btn-outline-secondary"
                                    id="balance-promo-check"
                                    title="{{ __('Balance promo check') }}"
                                    @if(!empty($promoLock['locked'])) disabled @endif>
                                {{ __('Balance promo check') }}
                            </button>
                        </div>
                        @error('promo_code')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-12 col-lg-3">
                        <label class="form-label cabinet-balance-topup-fields__label-spacer" aria-hidden="true">&nbsp;</label>
                        {!! Form::submit(__('Replenish'), ['class' => 'btn btn-success btn-lg w-100']) !!}
                    </div>
                </div>

                <div class="row g-3 mt-1">
                    <div class="col-12 col-lg-4">
                        <span class="form-label d-block">{{ __('Quick amount') }}</span>
                        <div class="d-flex flex-wrap gap-2">
                            @foreach([500, 1000, 3000, 5000, 10000] as $preset)
                                <button type="button"
                                        class="btn btn-outline-secondary cabinet-balance-preset"
                                        data-sum="{{ $preset }}">
                                    {{ number_format($preset, 0, '.', ' ') }} ₽
                                </button>
                            @endforeach
                        </div>
                    </div>
                    <div class="col-12 col-lg-8">
                        @if(empty($promoLock['locked']) && ($promoLock['failed_in_window'] ?? 0) > 0)
                            <p id="balance-promo-attempts-hint" class="form-text text-warning mb-2">
                                {{ __('Promo lock attempts left', ['count' => $promoLock['attempts_left']]) }}
                            </p>
                        @else
                            <p id="balance-promo-attempts-hint" class="form-text text-warning mb-2 d-none"></p>
                        @endif
                        <p class="form-text mb-2">{{ __('Balance promo hint') }}</p>
                        <p class="form-text mb-2 small">{{ __('Promo standalone hint unified') }}</p>
                        <div id="balance-promo-summary" class="cabinet-balance-promo-summary d-none" aria-live="polite"></div>
                        <div id="balance-promo-error" class="alert alert-danger py-2 px-3 mb-0 d-none small"></div>
                    </div>
                </div>

                <div class="cabinet-balance-standalone-promo mt-4 pt-3 border-top d-none" aria-hidden="true">
                    <h4 class="h6 mb-2">
                        <i class="bi bi-gift me-1"></i>{{ __('Promo standalone title') }}
                    </h4>
                    <p class="text-secondary small mb-3">{{ __('Promo standalone lead') }}</p>
                    <form method="post" action="{{ route('balance.promo.redeem') }}" class="row g-2 align-items-start">
                        @csrf
                        <div class="col-12 col-md-8 col-lg-5">
                            <label class="form-label" for="balance-standalone-promo-code">{{ __('Promo code field code') }}</label>
                            <input type="text"
                                   name="standalone_promo_code"
                                   id="balance-standalone-promo-code"
                                   class="form-control text-uppercase {{ $errors->has('standalone_promo_code') ? 'is-invalid' : '' }}"
                                   value="{{ old('standalone_promo_code') }}"
                                   maxlength="64"
                                   placeholder="{{ __('Promo standalone placeholder') }}"
                                   autocomplete="off"
                                   spellcheck="false"
                                   @if(!empty($promoLock['locked'])) disabled @endif>
                            @error('standalone_promo_code')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12 col-md-4 col-lg-3">
                            <label class="form-label cabinet-balance-topup-fields__label-spacer" aria-hidden="true">&nbsp;</label>
                            <button type="submit" class="btn btn-outline-success w-100" @if(!empty($promoLock['locked'])) disabled @endif>
                                <i class="bi bi-check2-circle me-1"></i>{{ __('Promo standalone submit') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            {!! Form::close() !!}
        </div>

        <div class="card">
            <div class="card-header">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 w-100">
                    <h3 class="card-title mb-0 d-flex align-items-center gap-2">
                        <i class="bi bi-list-ul"></i>
                        <span>{{ __('History') }}</span>
                        <span class="badge rounded-pill text-bg-secondary fw-normal">{{ $balances->total() }}</span>
                    </h3>
                    @if($balances->hasPages())
                        <span class="text-secondary small mb-0">
                            {{ __('Showing') }}
                            <strong>{{ $balances->firstItem() }}–{{ $balances->lastItem() }}</strong>
                            {{ __('of') }}
                            <strong>{{ $balances->total() }}</strong>
                        </span>
                    @endif
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped align-middle mb-0">
                        <thead class="table-light">
                        <tr>
                            <th scope="col">{{ __('Status') }}</th>
                            <th scope="col" class="text-end">{{ __('Sum') }}</th>
                            <th scope="col">{{ __('Source') }}</th>
                            <th scope="col" class="text-end text-nowrap">{{ __('Date') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($balances as $balance)
                            <tr>
                                <td>
                                    @switch($balance->status)
                                        @case(0)
                                            <span class="badge text-bg-danger">
                                                <i class="bi bi-x-circle me-1"></i>{{ __($balance->statuses[$balance->status]) }}
                                            </span>
                                            @break
                                        @case(1)
                                            <span class="badge text-bg-success">
                                                <i class="bi bi-plus-circle me-1"></i>{{ __($balance->statuses[$balance->status]) }}
                                            </span>
                                            @break
                                        @case(2)
                                            <span class="badge text-bg-info">
                                                <i class="bi bi-dash-circle me-1"></i>{{ __($balance->statuses[$balance->status]) }}
                                            </span>
                                            @break
                                    @endswitch
                                </td>
                                <td class="text-end fw-semibold text-nowrap">
                                    @if($balance->status === 2)
                                        −{{ number_format((float) $balance->sum, 0, '.', ' ') }}
                                    @else
                                        +{{ number_format((float) $balance->sum, 0, '.', ' ') }}
                                    @endif
                                    ₽
                                    @if((int) $balance->bonus_sum > 0 && (int) $balance->status === 1)
                                        <span class="small d-block text-success fw-normal">
                                            {{ __('Promo ledger breakdown', [
                                                'paid' => number_format((int) ($balance->paid_sum ?? ($balance->sum - $balance->bonus_sum)), 0, '.', ' '),
                                                'bonus' => number_format((int) $balance->bonus_sum, 0, '.', ' '),
                                                'code' => optional($balance->promoCode)->code,
                                            ]) }}
                                        </span>
                                    @endif
                                </td>
                                <td class="text-secondary">{{ __($balance->source) }}</td>
                                <td class="text-end text-secondary text-nowrap">
                                    <time datetime="{{ $balance->created_at->toIso8601String() }}">
                                        {{ $balance->created_at->diffForHumans() }}
                                    </time>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="cabinet-balance-empty text-center text-secondary">
                                    <i class="bi bi-inbox display-6 d-block mb-2 opacity-50"></i>
                                    {{ __('No records') }}
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($balances->hasPages())
                <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <span class="text-secondary small mb-0">
                        {{ __('Showing') }}
                        <strong>{{ $balances->firstItem() }}–{{ $balances->lastItem() }}</strong>
                        {{ __('of') }}
                        <strong>{{ $balances->total() }}</strong>
                    </span>
                    {{ $balances->links('pagination::bootstrap-4') }}
                </div>
            @endif
        </div>
    </div>

    @if($response == 'success')
        @include('balance.success')
    @endif

    @if($response == 'fail')
        @include('balance.fail')
    @endif

    @slot('js')
        <script>
            (function () {
                var sumInput = document.getElementById('balance-sum');
                var promoInput = document.getElementById('balance-promo-code');
                var checkBtn = document.getElementById('balance-promo-check');
                var summaryBox = document.getElementById('balance-promo-summary');
                var errorBox = document.getElementById('balance-promo-error');
                var previewTimer = null;

                var labels = {
                    pay: @json(__('Balance promo summary pay')),
                    bonus: @json(__('Balance promo summary bonus')),
                    total: @json(__('Balance promo summary total')),
                    needSum: @json(__('Promo preview need sum')),
                    needCode: @json(__('Balance promo need code')),
                    attemptsLeft: @json(__('Promo lock attempts left')),
                    standaloneCredit: @json(__('Promo standalone summary credit')),
                    standaloneActivate: @json(__('Promo standalone submit')),
                };

                var promoLock = @json($promoLock ?? ['locked' => false, 'attempts_left' => 10, 'failed_in_window' => 0]);

                function setPromoLocked(locked) {
                    if (promoInput) {
                        promoInput.disabled = !!locked;
                    }
                    if (checkBtn) {
                        checkBtn.disabled = !!locked;
                    }
                }

                function updateAttemptsHint(lockStatus) {
                    var hint = document.getElementById('balance-promo-attempts-hint');
                    if (!hint) {
                        return;
                    }
                    if (lockStatus.locked || !lockStatus.failed_in_window) {
                        hint.classList.add('d-none');
                        return;
                    }
                    hint.textContent = labels.attemptsLeft.replace(':count', lockStatus.attempts_left);
                    hint.classList.remove('d-none');
                }

                function formatMoney(value) {
                    return Number(value || 0).toLocaleString('ru-RU') + ' ₽';
                }

                function hidePromoMessages() {
                    if (summaryBox) {
                        summaryBox.classList.add('d-none');
                        summaryBox.innerHTML = '';
                    }
                    if (errorBox) {
                        errorBox.classList.add('d-none');
                        errorBox.textContent = '';
                    }
                }

                function showError(message) {
                    hidePromoMessages();
                    if (errorBox) {
                        errorBox.textContent = message;
                        errorBox.classList.remove('d-none');
                    }
                }

                function showSummary(response) {
                    hidePromoMessages();
                    if (!summaryBox) {
                        return;
                    }
                    summaryBox.innerHTML =
                        '<div class="cabinet-balance-promo-summary__row">' +
                        '<span>' + labels.pay + '</span><strong>' + formatMoney(response.paid_sum) + '</strong>' +
                        '</div>' +
                        '<div class="cabinet-balance-promo-summary__row cabinet-balance-promo-summary__row--bonus">' +
                        '<span>' + labels.bonus + ' (' + (response.promo_code || '') + ')</span><strong>+' + formatMoney(response.bonus_sum) + '</strong>' +
                        '</div>' +
                        '<div class="cabinet-balance-promo-summary__row cabinet-balance-promo-summary__row--total">' +
                        '<span>' + labels.total + '</span><strong>' + formatMoney(response.total_sum) + '</strong>' +
                        '</div>';
                    summaryBox.classList.remove('d-none');
                }

                function showStandaloneSummary(response) {
                    hidePromoMessages();
                    if (!summaryBox) {
                        return;
                    }
                    summaryBox.innerHTML =
                        '<div class="cabinet-balance-promo-summary__row cabinet-balance-promo-summary__row--total">' +
                        '<span>' + labels.standaloneCredit + ' (' + (response.promo_code || '') + ')</span>' +
                        '<strong>+' + formatMoney(response.bonus_sum) + '</strong>' +
                        '</div>' +
                        '<p class="small text-secondary mb-2 mt-2">' + (response.message || '') + '</p>' +
                        '<button type="button" class="btn btn-success btn-sm" id="balance-promo-activate-standalone">' +
                        '<i class="bi bi-check2-circle me-1"></i>' + labels.standaloneActivate +
                        '</button>';
                    summaryBox.classList.remove('d-none');

                    var activateBtn = document.getElementById('balance-promo-activate-standalone');
                    if (activateBtn) {
                        activateBtn.addEventListener('click', function () {
                            var form = document.createElement('form');
                            form.method = 'POST';
                            form.action = @json(route('balance.promo.redeem'));

                            var token = document.createElement('input');
                            token.type = 'hidden';
                            token.name = '_token';
                            token.value = $('meta[name="csrf-token"]').attr('content');
                            form.appendChild(token);

                            var codeInput = document.createElement('input');
                            codeInput.type = 'hidden';
                            codeInput.name = 'promo_code';
                            codeInput.value = response.promo_code || '';
                            form.appendChild(codeInput);

                            document.body.appendChild(form);
                            form.submit();
                        });
                    }
                }

                window.cabinetBalancePreviewPromo = function () {
                    if (!promoInput || promoLock.locked) {
                        return;
                    }

                    var sum = sumInput ? parseInt(sumInput.value, 10) : 0;
                    var code = (promoInput.value || '').trim();

                    if (!code) {
                        hidePromoMessages();
                        return;
                    }

                    $.ajax({
                        type: 'POST',
                        dataType: 'json',
                        url: @json(route('balance.promo.preview')),
                        data: {
                            _token: $('meta[name="csrf-token"]').attr('content'),
                            sum: sum > 0 ? sum : 0,
                            promo_code: code,
                        },
                        success: function (response) {
                            if (response.promo_lock) {
                                promoLock = response.promo_lock;
                                setPromoLocked(promoLock.locked);
                                updateAttemptsHint(promoLock);
                            }
                            if (response.valid && response.mode === 'standalone') {
                                showStandaloneSummary(response);
                            } else if (response.valid) {
                                showSummary(response);
                            } else {
                                showError(response.message || labels.needCode);
                            }
                        },
                        error: function () {
                            showError(@json(__('Balance promo check failed')));
                        },
                    });
                };

                function schedulePreview() {
                    clearTimeout(previewTimer);
                    previewTimer = setTimeout(window.cabinetBalancePreviewPromo, 400);
                }

                document.querySelectorAll('.cabinet-balance-preset').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        if (sumInput) {
                            sumInput.value = btn.getAttribute('data-sum');
                            sumInput.focus();
                            if ((promoInput.value || '').trim()) {
                                window.cabinetBalancePreviewPromo();
                            }
                        }
                    });
                });

                if (sumInput) {
                    sumInput.addEventListener('input', schedulePreview);
                }
                if (promoInput) {
                    promoInput.addEventListener('input', schedulePreview);
                    promoInput.addEventListener('keydown', function (e) {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            window.cabinetBalancePreviewPromo();
                        }
                    });
                }
                if (checkBtn) {
                    checkBtn.addEventListener('click', window.cabinetBalancePreviewPromo);
                }

                if (promoInput && (promoInput.value || '').trim()) {
                    window.cabinetBalancePreviewPromo();
                }
            })();
        </script>

        @if($response == 'success')
            <script>
                (function () {
                    var ymCounter = 89500732;

                    function showPaymentSuccessModal() {
                        var modalEl = document.getElementById('balance-success-modal');
                        if (!modalEl) {
                            return;
                        }
                        if (window.bootstrap && bootstrap.Modal) {
                            bootstrap.Modal.getOrCreateInstance(modalEl).show();
                            return;
                        }
                        if (window.jQuery && typeof jQuery(modalEl).modal === 'function') {
                            jQuery(modalEl).modal('show');
                        }
                    }

                    function firePaymentGoal() {
                        if (typeof ym === 'function') {
                            ym(ymCounter, 'reachGoal', 'success_payment_1231');
                        }
                        (window._tmr = window._tmr || []).push({ type: 'reachGoal', id: 3787377, goal: 'oplata' });
                    }

                    var url = new URL(window.location.href);
                    var invId = url.searchParams.get('InvId');
                    var payload = {
                        _token: $('meta[name="csrf-token"]').attr('content'),
                    };
                    if (invId !== null && invId !== '') {
                        payload.id = invId;
                    }

                    $.ajax({
                        type: 'post',
                        dataType: 'json',
                        url: "{{ route('counting.metrics') }}",
                        data: payload,
                        success: function (response) {
                            if (response.click) {
                                firePaymentGoal();
                            }
                            showPaymentSuccessModal();
                        },
                        error: function () {
                            showPaymentSuccessModal();
                        },
                    });
                })();
            </script>
        @endif
    @endslot
@endcomponent
