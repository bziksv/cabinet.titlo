@extends('layouts.app')

@section('title', __('Finance and referral management'))

@section('css')
    <link rel="stylesheet" href="{{ asset('plugins/select2/css/select2.css') }}">
    <link rel="stylesheet" href="{{ asset('plugins/select2-bootstrap4-theme/select2-bootstrap4.css') }}">
    <link rel="stylesheet" href="{{ asset('css/cabinet-finance-admin.css') }}?v={{ @filemtime(public_path('css/cabinet-finance-admin.css')) ?: time() }}">
@endsection

@section('content')
    @php
        use App\Services\Finance\FinanceAdminService;

        $netFlow = ($summary['top_up_sum'] ?? 0) - ($summary['expense_sum'] ?? 0);
        $fmt = [FinanceAdminService::class, 'formatMoney'];
    @endphp

    <div class="cabinet-finance-admin-page">
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
            <div>
                <h2 class="h4 mb-2 d-flex flex-wrap align-items-center gap-1">
                    <i class="bi bi-cash-coin text-primary" aria-hidden="true"></i>
                    <span>{{ __('Finance and referral management') }}</span>
                    @include('partials.cabinet-module-version-badge', ['configKey' => 'cabinet-finance-admin'])
                </h2>
                <p class="text-secondary small mb-0" style="max-width: 48rem;">
                    {{ __('Finance admin lead') }}
                </p>
            </div>
            <a href="{{ route('users.index') }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-people me-1" aria-hidden="true"></i>{{ __('Users') }}
            </a>
        </div>

        <div class="card card-outline card-success shadow-sm mb-4 cabinet-finance-credit-card">
            <div class="card-header py-2">
                <h3 class="card-title mb-0">
                    <i class="bi bi-plus-circle me-1"></i>{{ __('Finance credit title') }}
                </h3>
                <div class="card-tools">
                    <button type="button"
                            id="cabinet-finance-credit-toggle"
                            class="btn btn-sm btn-outline-secondary cabinet-finance-credit-toggle"
                            data-bs-toggle="collapse"
                            data-bs-target="#cabinet-finance-credit-collapse"
                            aria-expanded="true"
                            aria-controls="cabinet-finance-credit-collapse"
                            title="{{ __('Finance credit collapse') }}">
                        <span class="cabinet-finance-credit-toggle__label">{{ __('Finance credit collapse') }}</span>
                        <i class="bi bi-chevron-up cabinet-finance-credit-toggle__icon" aria-hidden="true"></i>
                    </button>
                </div>
            </div>
            <div id="cabinet-finance-credit-collapse" class="collapse show">
            <form method="post"
                  action="{{ route('admin.finance.credit') }}"
                  id="cabinet-finance-credit-form"
                  class="card-body border-top">
                @csrf
                <p class="text-secondary small mb-3">{{ __('Finance credit lead') }}</p>

                <div class="row g-3 align-items-end">
                    <div class="col-12 col-lg-5">
                        <label class="form-label" for="finance-credit-user">{{ __('Finance credit user') }}</label>
                        <select name="user_id"
                                id="finance-credit-user"
                                class="form-select @error('user_id') is-invalid @enderror"
                                required
                                data-placeholder="{{ __('Finance credit user placeholder') }}">
                            <option value=""></option>
                        </select>
                        @error('user_id')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                        <div id="finance-credit-user-preview" class="small text-secondary mt-2 d-none"></div>
                    </div>
                    <div class="col-12 col-lg-7">
                        <span class="form-label d-block">{{ __('Finance credit wallet') }}</span>
                        <div class="d-flex flex-wrap gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="wallet" id="finance-wallet-personal" value="personal"
                                       @if(old('wallet', 'personal') === 'personal') checked @endif>
                                <label class="form-check-label" for="finance-wallet-personal">{{ __('Finance credit personal') }}</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="wallet" id="finance-wallet-company" value="company"
                                       @if(old('wallet') === 'company') checked @endif>
                                <label class="form-check-label" for="finance-wallet-company">{{ __('Finance credit company') }}</label>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-lg-5 finance-credit-company-fields d-none">
                        <label class="form-label" for="finance-credit-company">{{ __('Company') }}</label>
                        <select name="user_company_id"
                                id="finance-credit-company"
                                class="form-select @error('user_company_id') is-invalid @enderror"
                                data-placeholder="{{ __('Select company') }}">
                            <option value=""></option>
                        </select>
                        @error('user_company_id')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-12 col-md-6 col-lg-4 finance-credit-company-fields d-none">
                        <label class="form-label" for="finance-credit-invoice">{{ __('Finance credit invoice') }}</label>
                        <select name="company_invoice_id"
                                id="finance-credit-invoice"
                                class="form-select @error('company_invoice_id') is-invalid @enderror"
                                data-placeholder="{{ __('Finance credit invoice placeholder') }}">
                            <option value=""></option>
                        </select>
                        @error('company_invoice_id')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-12 col-md-6 col-lg-3 finance-credit-personal-fields">
                        <label class="form-label" for="finance-credit-sum">{{ __('Sum') }}</label>
                        <div class="input-group">
                            <input type="number"
                                   name="sum"
                                   id="finance-credit-sum"
                                   class="form-control @error('sum') is-invalid @enderror"
                                   min="1"
                                   step="1"
                                   value="{{ old('sum') }}"
                                   placeholder="1000">
                            <span class="input-group-text">₽</span>
                        </div>
                        @error('sum')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                        <div class="d-flex flex-wrap gap-1 mt-2">
                            @foreach($creditPresets as $preset)
                                <button type="button"
                                        class="btn btn-sm btn-outline-secondary cabinet-finance-credit-preset"
                                        data-sum="{{ $preset }}">
                                    {{ number_format($preset, 0, '.', ' ') }} ₽
                                </button>
                            @endforeach
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-lg-4">
                        <label class="form-label" for="finance-credit-comment">{{ __('Finance credit comment') }}</label>
                        <input type="text"
                               name="comment"
                               id="finance-credit-comment"
                               class="form-control @error('comment') is-invalid @enderror"
                               maxlength="500"
                               value="{{ old('comment') }}"
                               placeholder="{{ __('Finance credit comment placeholder') }}">
                        @error('comment')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-wallet2 me-1"></i>{{ __('Finance credit submit') }}
                        </button>
                    </div>
                </div>
            </form>
            </div>
        </div>

        @include('admin.finance.partials.promo-codes')

        @include('admin.finance.partials.trigger-campaigns')

        <div class="row g-3 mb-4 cabinet-finance-stats">
            <div class="col-6 col-xl-4 col-xxl-2 d-flex">
                <div class="info-box mb-0 flex-fill">
                    <span class="info-box-icon text-bg-success shadow-sm"><i class="bi bi-arrow-down-circle"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ __('Finance stat top ups') }}</span>
                        <span class="info-box-number">{{ $fmt($summary['top_up_sum']) }}</span>
                        <span class="info-box-meta">{{ __('Finance stat operations count', ['count' => $summary['top_up_count']]) }}</span>
                    </div>
                </div>
            </div>
            <div class="col-6 col-xl-4 col-xxl-2 d-flex">
                <div class="info-box mb-0 flex-fill">
                    <span class="info-box-icon text-bg-info shadow-sm"><i class="bi bi-arrow-up-circle"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ __('Finance stat expenses') }}</span>
                        <span class="info-box-number">{{ $fmt($summary['expense_sum']) }}</span>
                        <span class="info-box-meta">{{ __('Finance stat operations count', ['count' => $summary['expense_count']]) }}</span>
                    </div>
                </div>
            </div>
            <div class="col-6 col-xl-4 col-xxl-2 d-flex">
                <div class="info-box mb-0 flex-fill">
                    <span class="info-box-icon text-bg-primary shadow-sm"><i class="bi bi-wallet2"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ __('Finance stat on balances') }}</span>
                        <span class="info-box-number">{{ $fmt($summary['total_user_balance']) }}</span>
                        <span class="info-box-meta">{{ __('Finance stat users with balance', ['count' => $summary['users_with_balance']]) }}</span>
                    </div>
                </div>
            </div>
            <div class="col-6 col-xl-4 col-xxl-2 d-flex">
                <div class="info-box mb-0 flex-fill">
                    <span class="info-box-icon text-bg-warning shadow-sm"><i class="bi bi-graph-up-arrow"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ __('Finance stat net flow') }}</span>
                        <span class="info-box-number {{ $netFlow >= 0 ? 'cabinet-finance-net-positive' : 'cabinet-finance-net-negative' }}">
                            {{ ($netFlow >= 0 ? '+' : '−') . number_format(abs($netFlow), 0, '.', ' ') }} ₽
                        </span>
                        <span class="info-box-meta">{{ __('Finance stat net flow hint') }}</span>
                    </div>
                </div>
            </div>
            <div class="col-6 col-xl-4 col-xxl-2 d-flex">
                <div class="info-box mb-0 flex-fill">
                    <span class="info-box-icon text-bg-secondary shadow-sm"><i class="bi bi-person-check"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ __('Finance stat payers') }}</span>
                        <span class="info-box-number">{{ number_format($summary['unique_payers'], 0, '.', ' ') }}</span>
                        <span class="info-box-meta">{{ __('Finance stat payers hint') }}</span>
                    </div>
                </div>
            </div>
            <div class="col-6 col-xl-4 col-xxl-2 d-flex">
                <div class="info-box mb-0 flex-fill">
                    <span class="info-box-icon text-bg-danger shadow-sm"><i class="bi bi-x-circle"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ __('Finance stat failed') }}</span>
                        <span class="info-box-number">{{ $fmt($summary['failed_sum']) }}</span>
                        <span class="info-box-meta">{{ __('Finance stat failed hint', ['count' => $summary['failed_count']]) }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-12 col-xl-8">
                <div class="card shadow-sm h-100">
                    <div class="card-header py-2">
                        <h3 class="card-title h6 mb-0">
                            <i class="bi bi-bar-chart-line me-1"></i>{{ __('Finance chart title') }}
                        </h3>
                    </div>
                    <div class="card-body cabinet-finance-chart-wrap">
                        @if(count($chart['labels'] ?? []) > 0)
                            <canvas id="cabinet-finance-chart" aria-label="{{ __('Finance chart title') }}"></canvas>
                        @else
                            <p class="text-secondary text-center mb-0 py-5">{{ __('Finance chart empty') }}</p>
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-12 col-xl-4">
                <div class="card shadow-sm h-100">
                    <div class="card-header py-2">
                        <h3 class="card-title h6 mb-0">
                            <i class="bi bi-trophy me-1"></i>{{ __('Finance top users title') }}
                        </h3>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0">
                                <thead class="table-light">
                                <tr>
                                    <th scope="col" class="ps-3">#</th>
                                    <th scope="col">{{ __('User') }}</th>
                                    <th scope="col" class="text-end">{{ __('Finance col topped up') }}</th>
                                    <th scope="col" class="text-end pe-3">{{ __('Balance') }}</th>
                                </tr>
                                </thead>
                                <tbody>
                                @forelse($topUsers as $index => $row)
                                    <tr>
                                        <td class="ps-3 cabinet-finance-rank {{ $index < 3 ? 'cabinet-finance-rank--top' : '' }}">
                                            {{ $index + 1 }}
                                        </td>
                                        <td>
                                            <a href="{{ route('users.edit', $row['user_id']) }}"
                                               class="cabinet-finance-user-link d-block text-truncate"
                                               style="max-width: 11rem;"
                                               title="{{ $row['email'] }}">
                                                {{ $row['name'] }}
                                            </a>
                                            <span class="small text-secondary d-block text-truncate" style="max-width: 11rem;">
                                                {{ $row['email'] }}
                                            </span>
                                        </td>
                                        <td class="text-end cabinet-finance-amount cabinet-finance-amount--in">
                                            +{{ number_format($row['top_up_sum'], 0, '.', ' ') }} ₽
                                        </td>
                                        <td class="text-end pe-3 cabinet-finance-amount">
                                            {{ number_format($row['balance'], 0, '.', ' ') }} ₽
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-secondary py-4">{{ __('No records') }}</td>
                                    </tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-3 cabinet-finance-ledger-card">
            <div class="card-header py-2">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 w-100">
                    <h3 class="card-title h6 mb-0 d-flex align-items-center gap-2">
                        <i class="bi bi-list-ul"></i>
                        <span>{{ __('Finance ledger title') }}</span>
                        <span class="badge rounded-pill text-bg-secondary fw-normal">{{ $transactions->total() }}</span>
                    </h3>
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        @if($transactions->hasPages())
                            <span class="text-secondary small mb-0">
                                {{ __('Showing') }}
                                <strong>{{ $transactions->firstItem() }}–{{ $transactions->lastItem() }}</strong>
                                {{ __('of') }}
                                <strong>{{ $transactions->total() }}</strong>
                            </span>
                        @endif
                        <div class="card-tools ms-1">
                            <button type="button"
                                    id="cabinet-finance-ledger-toggle"
                                    class="btn btn-sm btn-outline-secondary cabinet-finance-ledger-toggle"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#cabinet-finance-ledger-collapse"
                                    aria-expanded="true"
                                    aria-controls="cabinet-finance-ledger-collapse"
                                    title="{{ __('Finance credit collapse') }}">
                                <span class="cabinet-finance-ledger-toggle__label">{{ __('Finance credit collapse') }}</span>
                                <i class="bi bi-chevron-up cabinet-finance-ledger-toggle__icon" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div id="cabinet-finance-ledger-collapse" class="collapse show">
            <div class="card-body border-bottom cabinet-finance-filters">
                <form method="get" action="{{ route('admin.finance.index') }}" class="row g-2 align-items-end">
                    @if(request('tab'))
                        <input type="hidden" name="tab" value="{{ request('tab') }}">
                    @endif
                    <div class="col-12 col-md-4 col-lg-3">
                        <label class="form-label" for="finance-filter-q">{{ __('Finance filter user') }}</label>
                        <input type="search"
                               name="q"
                               id="finance-filter-q"
                               class="form-control form-control-sm"
                               value="{{ $filters['q'] }}"
                               placeholder="{{ __('Finance filter user placeholder') }}">
                    </div>
                    <div class="col-6 col-md-3 col-lg-2">
                        <label class="form-label" for="finance-filter-status">{{ __('Status') }}</label>
                        <select name="status" id="finance-filter-status" class="form-select form-select-sm">
                            @foreach($statusOptions as $value => $label)
                                <option value="{{ $value }}" {{ ($filters['status'] ?? '') === (string) $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-3 col-lg-2">
                        <label class="form-label" for="finance-filter-period">{{ __('Period') }}</label>
                        <select name="period" id="finance-filter-period" class="form-select form-select-sm">
                            @foreach($periodOptions as $value => $label)
                                <option value="{{ $value }}" {{ ($filters['period'] ?? '') === (string) $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-lg-3">
                        <div class="form-check form-switch mb-0 pt-lg-4">
                            <input type="hidden" name="exclude_admins" value="0">
                            <input class="form-check-input"
                                   type="checkbox"
                                   role="switch"
                                   name="exclude_admins"
                                   id="finance-exclude-admins"
                                   value="1"
                                   {{ $excludeAdmins ? 'checked' : '' }}>
                            <label class="form-check-label" for="finance-exclude-admins">
                                {{ __('Finance exclude admin stats') }}
                            </label>
                        </div>
                        <p class="form-text mb-0 small">{{ __('Finance exclude admin stats hint') }}</p>
                    </div>
                    <div class="col-12 col-md-auto d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-sm btn-primary">
                            <i class="bi bi-funnel me-1"></i>{{ __('Apply') }}
                        </button>
                        <a href="{{ route('admin.finance.index', ['exclude_admins' => 1]) }}" class="btn btn-sm btn-outline-secondary">
                            {{ __('Reset') }}
                        </a>
                    </div>
                </form>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped align-middle mb-0">
                        <thead class="table-light">
                        <tr>
                            <th scope="col" class="ps-3">{{ __('Date') }}</th>
                            <th scope="col">{{ __('User') }}</th>
                            <th scope="col">{{ __('Status') }}</th>
                            <th scope="col" class="text-end">{{ __('Sum') }}</th>
                            <th scope="col">{{ __('Source') }}</th>
                            <th scope="col" class="text-end pe-3">{{ __('Finance col balance before after') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($transactions as $tx)
                            @php
                                $user = $tx->user;
                                $userName = $user
                                    ? trim(($user->name ?? '') . ' ' . ($user->last_name ?? ''))
                                    : '';
                                if ($userName === '' && $user) {
                                    $userName = $user->email;
                                }
                            @endphp
                            <tr>
                                <td class="ps-3 text-nowrap text-secondary">
                                    <time datetime="{{ $tx->created_at->toIso8601String() }}">
                                        {{ $tx->created_at->format('d.m.Y H:i') }}
                                    </time>
                                    <span class="small d-block">{{ $tx->created_at->diffForHumans() }}</span>
                                </td>
                                <td>
                                    @if($user)
                                        <a href="{{ route('users.edit', $user->id) }}" class="cabinet-finance-user-link fw-semibold">
                                            {{ $userName }}
                                        </a>
                                        <span class="small text-secondary d-block">#{{ $user->id }} · {{ $user->email }}</span>
                                    @else
                                        <span class="text-secondary">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if($tx->isTopUp())
                                        <span class="badge text-bg-success">
                                            <i class="bi bi-plus-circle me-1"></i>{{ __($tx->statuses[1]) }}
                                        </span>
                                    @elseif($tx->isExpense())
                                        <span class="badge text-bg-info">
                                            <i class="bi bi-dash-circle me-1"></i>{{ __($tx->statuses[2]) }}
                                        </span>
                                    @else
                                        <span class="badge text-bg-danger">
                                            <i class="bi bi-x-circle me-1"></i>{{ __($tx->statuses[0]) }}
                                        </span>
                                    @endif
                                    @if($tx->company)
                                        <span class="badge text-bg-primary ms-1">{{ __('Finance wallet company') }}</span>
                                    @endif
                                </td>
                                <td class="text-end cabinet-finance-amount text-nowrap
                                    @if($tx->isTopUp()) cabinet-finance-amount--in @elseif($tx->isExpense()) cabinet-finance-amount--out @else cabinet-finance-amount--fail @endif">
                                    @if($tx->isExpense())
                                        −{{ number_format((float) $tx->sum, 0, '.', ' ') }} ₽
                                    @elseif($tx->isFailed())
                                        {{ number_format((float) $tx->sum, 0, '.', ' ') }} ₽
                                    @else
                                        +{{ number_format((float) $tx->sum, 0, '.', ' ') }} ₽
                                        @if((int) $tx->bonus_sum > 0)
                                            <span class="small d-block text-success">
                                                {{ __('Promo ledger breakdown', [
                                                    'paid' => number_format((int) ($tx->paid_sum ?? ($tx->sum - $tx->bonus_sum)), 0, '.', ' '),
                                                    'bonus' => number_format((int) $tx->bonus_sum, 0, '.', ' '),
                                                    'code' => optional($tx->promoCode)->code,
                                                ]) }}
                                            </span>
                                        @endif
                                    @endif
                                </td>
                                <td class="text-secondary">
                                    {{ __($tx->source) }}
                                    @if($tx->company)
                                        <span class="small d-block">{{ $tx->company->name }} · ИНН {{ $tx->company->inn }}</span>
                                    @endif
                                </td>
                                <td class="text-end pe-3 cabinet-finance-amount text-nowrap small">
                                    @if($tx->company)
                                        <span class="text-secondary">{{ __('Finance company balance now') }}</span>
                                        <span class="fw-semibold d-block">{{ number_format((int) $tx->company->balance, 0, '.', ' ') }} ₽</span>
                                    @else
                                        @php
                                            $balanceBefore = $tx->ledgerBalanceBefore();
                                            $balanceAfter = $tx->ledgerBalanceAfter();
                                        @endphp
                                        @if($balanceBefore !== null && $balanceAfter !== null)
                                            <span class="text-secondary">{{ number_format($balanceBefore, 0, '.', ' ') }}</span>
                                            <span class="text-secondary mx-1" aria-hidden="true">→</span>
                                            <span class="fw-semibold">{{ number_format($balanceAfter, 0, '.', ' ') }} ₽</span>
                                        @else
                                            —
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="cabinet-finance-empty text-center text-secondary">
                                    <i class="bi bi-inbox display-6 d-block mb-2 opacity-50"></i>
                                    {{ __('No records') }}
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($transactions->hasPages())
                <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <span class="text-secondary small mb-0">
                        {{ __('Showing') }}
                        <strong>{{ $transactions->firstItem() }}–{{ $transactions->lastItem() }}</strong>
                        {{ __('of') }}
                        <strong>{{ $transactions->total() }}</strong>
                    </span>
                    {{ $transactions->links('pagination::bootstrap-4') }}
                </div>
            @endif
            </div>
        </div>
    </div>
@endsection

@section('js')
    <script src="{{ asset('plugins/select2/js/select2.js') }}"></script>
    <script>
        (function () {
            var storageKey = 'cabinet-finance-credit-collapsed';
            var collapseEl = document.getElementById('cabinet-finance-credit-collapse');
            var toggleBtn = document.getElementById('cabinet-finance-credit-toggle');
            var collapseLabel = @json(__('Finance credit collapse'));
            var expandLabel = @json(__('Finance credit expand'));

            function syncToggleUi(collapsed) {
                if (!toggleBtn) {
                    return;
                }
                toggleBtn.classList.toggle('collapsed', collapsed);
                toggleBtn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
                toggleBtn.title = collapsed ? expandLabel : collapseLabel;
                var label = toggleBtn.querySelector('.cabinet-finance-credit-toggle__label');
                if (label) {
                    label.textContent = collapsed ? expandLabel : collapseLabel;
                }
            }

            if (collapseEl && toggleBtn && typeof bootstrap !== 'undefined' && bootstrap.Collapse) {
                var forceOpen = @json($errors->hasAny(['user_id', 'sum', 'comment']));
                var savedCollapsed = !forceOpen && localStorage.getItem(storageKey) === '1';
                var collapse = bootstrap.Collapse.getOrCreateInstance(collapseEl, {toggle: false});

                if (savedCollapsed) {
                    collapse.hide();
                    syncToggleUi(true);
                } else {
                    syncToggleUi(false);
                }

                collapseEl.addEventListener('hidden.bs.collapse', function () {
                    localStorage.setItem(storageKey, '1');
                    syncToggleUi(true);
                });

                collapseEl.addEventListener('shown.bs.collapse', function () {
                    localStorage.setItem(storageKey, '0');
                    syncToggleUi(false);
                });
            }

            var $userSelect = $('#finance-credit-user');
            var $preview = $('#finance-credit-user-preview');
            var $sum = $('#finance-credit-sum');
            var $companySelect = $('#finance-credit-company');
            var $invoiceSelect = $('#finance-credit-invoice');
            var previewTpl = @json(__('Finance credit user preview'));

            function financeWalletIsCompany() {
                return document.getElementById('finance-wallet-company')
                    && document.getElementById('finance-wallet-company').checked;
            }

            function syncFinanceWalletUi() {
                var company = financeWalletIsCompany();
                document.querySelectorAll('.finance-credit-company-fields').forEach(function (el) {
                    el.classList.toggle('d-none', !company);
                });
                document.querySelectorAll('.finance-credit-personal-fields').forEach(function (el) {
                    el.classList.toggle('d-none', company);
                });
                if ($sum.length) {
                    $sum.prop('required', !company);
                }
                if ($companySelect.length) {
                    $companySelect.prop('required', company);
                }
                if ($invoiceSelect.length) {
                    $invoiceSelect.prop('required', company);
                }
            }

            function resetCompanyInvoiceSelects() {
                if ($companySelect.length) {
                    $companySelect.empty().append('<option value=""></option>').val(null).trigger('change');
                }
                if ($invoiceSelect.length) {
                    $invoiceSelect.empty().append('<option value=""></option>').val(null).trigger('change');
                }
            }

            function loadFinanceCompanies(userId) {
                resetCompanyInvoiceSelects();
                if (!userId) {
                    return;
                }
                $.getJSON(@json(route('admin.finance.companies')), {user_id: userId}).done(function (data) {
                    (data.results || []).forEach(function (row) {
                        var opt = new Option(row.text, row.id, false, false);
                        $companySelect.append(opt);
                    });
                    $companySelect.trigger('change');
                });
            }

            function loadFinanceInvoices(userId, companyId) {
                $invoiceSelect.empty().append('<option value=""></option>').val(null).trigger('change');
                if (!userId || !companyId) {
                    return;
                }
                $.getJSON(@json(route('admin.finance.invoices')), {
                    user_id: userId,
                    company_id: companyId
                }).done(function (data) {
                    (data.results || []).forEach(function (row) {
                        var opt = new Option(row.text, row.id, false, false);
                        $(opt).attr('data-amount', row.amount);
                        $invoiceSelect.append(opt);
                    });
                    $invoiceSelect.trigger('change');
                });
            }

            if ($userSelect.length && $.fn.select2) {
                $userSelect.select2({
                    theme: 'bootstrap4',
                    width: '100%',
                    allowClear: true,
                    minimumInputLength: 2,
                    placeholder: $userSelect.data('placeholder') || '',
                    ajax: {
                        url: @json(route('admin.finance.users-search')),
                        dataType: 'json',
                        delay: 250,
                        data: function (params) {
                            return {q: params.term || ''};
                        },
                        processResults: function (data) {
                            return {results: data.results || []};
                        },
                        cache: true,
                    },
                });

                $userSelect.on('select2:select', function (e) {
                    var d = e.params.data || {};
                    if (!d.id) {
                        $preview.addClass('d-none').text('');
                        resetCompanyInvoiceSelects();
                        return;
                    }
                    var balance = typeof d.balance === 'number'
                        ? d.balance.toLocaleString('ru-RU') + ' ₽'
                        : '—';
                    $preview
                        .removeClass('d-none')
                        .html(previewTpl
                            .replace(':name', $('<div>').text(d.name || d.text || '').html())
                            .replace(':email', $('<div>').text(d.email || '').html())
                            .replace(':balance', balance));
                    loadFinanceCompanies(d.id);
                });

                $userSelect.on('select2:clear', function () {
                    $preview.addClass('d-none').text('');
                    resetCompanyInvoiceSelects();
                });
            }

            if ($companySelect.length && $.fn.select2) {
                $companySelect.select2({
                    theme: 'bootstrap4',
                    width: '100%',
                    allowClear: true,
                    placeholder: $companySelect.data('placeholder') || '',
                });
                $companySelect.on('change', function () {
                    var userId = $userSelect.val();
                    var companyId = $companySelect.val();
                    loadFinanceInvoices(userId, companyId);
                });
            }

            if ($invoiceSelect.length && $.fn.select2) {
                $invoiceSelect.select2({
                    theme: 'bootstrap4',
                    width: '100%',
                    allowClear: true,
                    placeholder: $invoiceSelect.data('placeholder') || '',
                });
                $invoiceSelect.on('change', function () {
                    var $opt = $invoiceSelect.find('option:selected');
                    var amount = $opt.data('amount');
                    if (amount && $sum.length) {
                        $sum.val(amount);
                    }
                });
            }

            document.querySelectorAll('input[name="wallet"]').forEach(function (radio) {
                radio.addEventListener('change', syncFinanceWalletUi);
            });
            syncFinanceWalletUi();

            document.querySelectorAll('.cabinet-finance-credit-preset').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    if ($sum.length) {
                        $sum.val(btn.getAttribute('data-sum')).trigger('focus');
                    }
                });
            });

            var form = document.getElementById('cabinet-finance-credit-form');
            if (form) {
                form.addEventListener('submit', function (e) {
                    var userData = $userSelect.length ? $userSelect.select2('data')[0] : null;
                    if (!userData) {
                        return;
                    }
                    var userLabel = userData.text || userData.email || ('#' + userData.id);
                    var sumVal = financeWalletIsCompany()
                        ? ($invoiceSelect.find('option:selected').data('amount') || $sum.val())
                        : $sum.val();
                    if (!sumVal) {
                        return;
                    }
                    var msg = @json(__('Finance credit confirm'));
                    msg = msg.replace(':sum', Number(sumVal).toLocaleString('ru-RU') + ' ₽')
                        .replace(':user', userLabel);
                    if (!window.confirm(msg)) {
                        e.preventDefault();
                    }
                });
            }

            var promoStorageKey = 'cabinet-finance-promo-collapsed';
            var promoCollapseEl = document.getElementById('cabinet-finance-promo-collapse');
            var promoToggleBtn = document.getElementById('cabinet-finance-promo-toggle');

            function syncPromoToggleUi(collapsed) {
                if (!promoToggleBtn) {
                    return;
                }
                promoToggleBtn.classList.toggle('collapsed', collapsed);
                promoToggleBtn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
                promoToggleBtn.title = collapsed ? expandLabel : collapseLabel;
                var label = promoToggleBtn.querySelector('.cabinet-finance-promo-toggle__label');
                if (label) {
                    label.textContent = collapsed ? expandLabel : collapseLabel;
                }
            }

            if (promoCollapseEl && promoToggleBtn && typeof bootstrap !== 'undefined' && bootstrap.Collapse) {
                var promoForceOpen = @json(request('tab') === 'promo' && !request('campaign'));
                var promoSavedCollapsed = !promoForceOpen && localStorage.getItem(promoStorageKey) === '1';
                var promoCollapse = bootstrap.Collapse.getOrCreateInstance(promoCollapseEl, {toggle: false});

                if (promoSavedCollapsed) {
                    promoCollapse.hide();
                    syncPromoToggleUi(true);
                } else {
                    syncPromoToggleUi(false);
                }

                promoCollapseEl.addEventListener('hidden.bs.collapse', function () {
                    localStorage.setItem(promoStorageKey, '1');
                    syncPromoToggleUi(true);
                });

                promoCollapseEl.addEventListener('shown.bs.collapse', function () {
                    localStorage.setItem(promoStorageKey, '0');
                    syncPromoToggleUi(false);
                });

                if (promoForceOpen) {
                    promoCollapse.show();
                    document.getElementById('cabinet-finance-promo-panel')?.scrollIntoView({behavior: 'smooth', block: 'start'});
                }
            }

            var $promoSimulateUser = $('#promo-simulate-user');
            if ($promoSimulateUser.length && $.fn.select2) {
                $promoSimulateUser.select2({
                    width: '100%',
                    allowClear: true,
                    minimumInputLength: 2,
                    placeholder: $promoSimulateUser.data('placeholder') || '',
                    ajax: {
                        url: @json(route('admin.finance.users-search')),
                        dataType: 'json',
                        delay: 250,
                        data: function (params) {
                            return {q: params.term || ''};
                        },
                        processResults: function (data) {
                            return {results: data.results || []};
                        },
                        cache: true,
                    },
                });
            }

            var triggerStorageKey = 'cabinet-finance-trigger-collapsed';
            var triggerCollapseEl = document.getElementById('cabinet-finance-trigger-collapse');
            var triggerToggleBtn = document.getElementById('cabinet-finance-trigger-toggle');

            function syncTriggerToggleUi(collapsed) {
                if (!triggerToggleBtn) {
                    return;
                }
                triggerToggleBtn.classList.toggle('collapsed', collapsed);
                triggerToggleBtn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
                triggerToggleBtn.title = collapsed ? expandLabel : collapseLabel;
                var label = triggerToggleBtn.querySelector('.cabinet-finance-trigger-toggle__label');
                if (label) {
                    label.textContent = collapsed ? expandLabel : collapseLabel;
                }
            }

            if (triggerCollapseEl && triggerToggleBtn && typeof bootstrap !== 'undefined' && bootstrap.Collapse) {
                var triggerSavedCollapsed = localStorage.getItem(triggerStorageKey) === '1';
                var triggerCollapse = bootstrap.Collapse.getOrCreateInstance(triggerCollapseEl, {toggle: false});

                if (triggerSavedCollapsed) {
                    triggerCollapse.hide();
                    syncTriggerToggleUi(true);
                } else {
                    syncTriggerToggleUi(false);
                }

                triggerCollapseEl.addEventListener('hidden.bs.collapse', function () {
                    localStorage.setItem(triggerStorageKey, '1');
                    syncTriggerToggleUi(true);
                });

                triggerCollapseEl.addEventListener('shown.bs.collapse', function () {
                    localStorage.setItem(triggerStorageKey, '0');
                    syncTriggerToggleUi(false);
                });

                @if(request('tab') === 'trigger' || request('campaign'))
                window.setTimeout(function () {
                    document.getElementById('cabinet-finance-trigger-panel')?.scrollIntoView({behavior: 'smooth', block: 'start'});
                    @if(request('campaign'))
                    document.getElementById('trigger-campaign-{{ (int) request('campaign') }}')?.scrollIntoView({behavior: 'smooth', block: 'start'});
                    @endif
                }, 150);
                @endif
            }

            document.querySelectorAll('.cabinet-finance-trigger-campaign-card').forEach(function (card) {
                var campaignId = card.getAttribute('id')?.replace('trigger-campaign-', '');
                if (!campaignId) {
                    return;
                }

                var collapseEl = document.getElementById('trigger-campaign-collapse-' + campaignId);
                var toggleBtn = card.querySelector('.cabinet-finance-trigger-campaign-toggle');
                if (!collapseEl || !toggleBtn || typeof bootstrap === 'undefined' || !bootstrap.Collapse) {
                    return;
                }

                var storageKey = 'cabinet-finance-trigger-campaign-' + campaignId + '-collapsed';

                function syncCampaignToggleUi(collapsed) {
                    toggleBtn.classList.toggle('collapsed', collapsed);
                    toggleBtn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
                    toggleBtn.title = collapsed ? expandLabel : collapseLabel;
                    var label = toggleBtn.querySelector('.cabinet-finance-trigger-campaign-toggle__label');
                    if (label) {
                        label.textContent = collapsed ? expandLabel : collapseLabel;
                    }
                }

                var savedCollapsed = localStorage.getItem(storageKey) === '1';
                var campaignCollapse = bootstrap.Collapse.getOrCreateInstance(collapseEl, {toggle: false});

                if (savedCollapsed) {
                    campaignCollapse.hide();
                    syncCampaignToggleUi(true);
                } else {
                    syncCampaignToggleUi(false);
                }

                collapseEl.addEventListener('hidden.bs.collapse', function () {
                    localStorage.setItem(storageKey, '1');
                    syncCampaignToggleUi(true);
                });

                collapseEl.addEventListener('shown.bs.collapse', function () {
                    localStorage.setItem(storageKey, '0');
                    syncCampaignToggleUi(false);
                });
            });

            var ledgerStorageKey = 'cabinet-finance-ledger-collapsed';
            var ledgerCollapseEl = document.getElementById('cabinet-finance-ledger-collapse');
            var ledgerToggleBtn = document.getElementById('cabinet-finance-ledger-toggle');

            function syncLedgerToggleUi(collapsed) {
                if (!ledgerToggleBtn) {
                    return;
                }
                ledgerToggleBtn.classList.toggle('collapsed', collapsed);
                ledgerToggleBtn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
                ledgerToggleBtn.title = collapsed ? expandLabel : collapseLabel;
                var label = ledgerToggleBtn.querySelector('.cabinet-finance-ledger-toggle__label');
                if (label) {
                    label.textContent = collapsed ? expandLabel : collapseLabel;
                }
            }

            if (ledgerCollapseEl && ledgerToggleBtn && typeof bootstrap !== 'undefined' && bootstrap.Collapse) {
                var ledgerForceOpen = @json(
                    ($filters['q'] ?? '') !== ''
                    || ($filters['status'] ?? 'all') !== 'all'
                    || ($filters['period'] ?? 'all') !== 'all'
                );
                var ledgerSavedCollapsed = !ledgerForceOpen && localStorage.getItem(ledgerStorageKey) === '1';
                var ledgerCollapse = bootstrap.Collapse.getOrCreateInstance(ledgerCollapseEl, {toggle: false});

                if (ledgerSavedCollapsed) {
                    ledgerCollapse.hide();
                    syncLedgerToggleUi(true);
                } else {
                    syncLedgerToggleUi(false);
                }

                ledgerCollapseEl.addEventListener('hidden.bs.collapse', function () {
                    localStorage.setItem(ledgerStorageKey, '1');
                    syncLedgerToggleUi(true);
                });

                ledgerCollapseEl.addEventListener('shown.bs.collapse', function () {
                    localStorage.setItem(ledgerStorageKey, '0');
                    syncLedgerToggleUi(false);
                });
            }
        })();
    </script>
    @if(count($chart['labels'] ?? []) > 0)
        <script src="{{ asset('plugins/chart.js/3.9.1/chart.js') }}"></script>
        <script>
            (function () {
                var canvas = document.getElementById('cabinet-finance-chart');
                if (!canvas || typeof Chart === 'undefined') {
                    return;
                }

                new Chart(canvas, {
                    type: 'bar',
                    data: {
                        labels: @json($chart['labels']),
                        datasets: [
                            {
                                label: @json(__('Finance chart top ups')),
                                data: @json($chart['top_up']),
                                backgroundColor: 'rgba(25, 135, 84, 0.75)',
                                borderColor: 'rgb(25, 135, 84)',
                                borderWidth: 1,
                                borderRadius: 4,
                            },
                            {
                                label: @json(__('Finance chart expenses')),
                                data: @json($chart['expense']),
                                backgroundColor: 'rgba(13, 202, 240, 0.65)',
                                borderColor: 'rgb(13, 202, 240)',
                                borderWidth: 1,
                                borderRadius: 4,
                            },
                        ],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        plugins: {
                            legend: { position: 'top' },
                            tooltip: {
                                callbacks: {
                                    label: function (ctx) {
                                        var v = ctx.parsed.y || 0;
                                        return ctx.dataset.label + ': ' + v.toLocaleString('ru-RU') + ' ₽';
                                    },
                                },
                            },
                        },
                        scales: {
                            x: {
                                grid: { display: false },
                                ticks: { maxRotation: 45, minRotation: 0, autoSkip: true, maxTicksLimit: 14 },
                            },
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function (value) {
                                        return value.toLocaleString('ru-RU') + ' ₽';
                                    },
                                },
                            },
                        },
                    },
                });
            })();
        </script>
    @endif
@endsection
