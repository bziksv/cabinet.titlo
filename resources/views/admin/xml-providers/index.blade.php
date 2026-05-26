@extends('layouts.app')

@section('title', __('XML services management'))

@section('css')
    <link rel="stylesheet" href="{{ asset('css/cabinet-xml-providers.css') }}?v={{ @filemtime(public_path('css/cabinet-xml-providers.css')) ?: time() }}">
@endsection

@section('content')
    <div class="cabinet-xml-providers-page">
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
            <div>
                <h2 class="h4 mb-2 d-flex flex-wrap align-items-center gap-1">
                    <i class="bi bi-cloud-haze2 text-primary" aria-hidden="true"></i>
                    <span>{{ __('XML services management') }}</span>
                    @include('partials.cabinet-module-version-badge', ['configKey' => 'cabinet-xml-providers'])
                </h2>
                <p class="text-secondary small mb-0" style="max-width: 44rem;">
                    {{ __('Balances from provider APIs. Keys are stored in .env (XML_STOCK_*, XML_PROXY_*, XML_RIVER_*). Module mapping is defined in config/cabinet-xml-providers.php.') }}
                </p>
            </div>
            <button type="button" class="btn btn-sm btn-outline-primary" id="cabinet-xml-refresh-balances">
                <i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i>{{ __('Refresh balances') }}
            </button>
        </div>

        <div class="row g-3 mb-4" id="cabinet-xml-provider-cards">
            @foreach($providers as $provider)
                @php
                    $b = $provider['balance'];
                    $ok = !empty($b['ok']);
                @endphp
                <div class="col-12 col-md-6 col-xl-4">
                    <div class="card shadow-sm h-100 cabinet-xml-provider-card {{ $ok ? 'border-success' : 'border-warning' }}">
                        <div class="card-header d-flex justify-content-between align-items-center py-2">
                            <h3 class="card-title h6 mb-0">{{ $provider['title'] }}</h3>
                            <a href="{{ $provider['cabinet_url'] }}" class="small" target="_blank" rel="noopener noreferrer">
                                {{ __('Cabinet') }} <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>
                            </a>
                        </div>
                        <div class="card-body">
                            <div class="info-box mb-0">
                                <span class="info-box-icon text-bg-{{ $ok ? 'success' : 'warning' }} shadow-sm">
                                    <i class="bi bi-wallet2" aria-hidden="true"></i>
                                </span>
                                <div class="info-box-content">
                                    <span class="info-box-text">{{ __('Balance') }}</span>
                                    <span class="info-box-number cabinet-xml-balance-value" data-provider="{{ $provider['id'] }}">
                                        @if($ok)
                                            {{ $b['balance_formatted'] ?? $b['balance'] }}
                                        @else
                                            <span class="text-warning">{{ __('Unavailable') }}</span>
                                        @endif
                                    </span>
                                    @if($ok && !empty($b['user_masked']))
                                        <span class="info-box-meta text-secondary small">{{ __('User') }}: {{ $b['user_masked'] }}</span>
                                    @endif
                                </div>
                            </div>

                            @if($ok && !empty($b['extra']))
                                <ul class="list-unstyled small text-secondary mt-3 mb-0">
                                    @if(isset($b['extra']['outgo_day']))
                                        <li>{{ __('Spent today') }}: {{ number_format((float) $b['extra']['outgo_day'], 2, '.', ' ') }} ₽</li>
                                    @endif
                                    @if(isset($b['extra']['outgo_month']))
                                        <li>{{ __('Spent this month') }}: {{ number_format((float) $b['extra']['outgo_month'], 2, '.', ' ') }} ₽</li>
                                    @endif
                                    @if(isset($b['extra']['cur_cost']))
                                        <li>{{ __('Rate per 1K requests') }}: {{ $b['extra']['cur_cost'] }} / {{ $b['extra']['max_cost'] ?? '—' }} ₽</li>
                                    @endif
                                </ul>
                            @endif

                            @if(!$ok)
                                <p class="small text-warning mb-0 mt-2 cabinet-xml-balance-error" data-provider="{{ $provider['id'] }}">
                                    @if(($b['code'] ?? '') === 'credentials_missing')
                                        {{ __('Credentials are not set in .env') }}
                                    @elseif(!empty($b['message']))
                                        {{ $b['message'] }}
                                    @elseif(!empty($b['raw']))
                                        {{ is_string($b['raw']) ? $b['raw'] : json_encode($b['raw'], JSON_UNESCAPED_UNICODE) }}
                                    @else
                                        {{ __('Could not load balance') }} ({{ $b['code'] ?? 'error' }})
                                    @endif
                                </p>
                            @endif
                        </div>
                        <div class="card-footer small text-secondary py-2">
                            @php
                                $envPrefix = [
                                    'xmlstock' => 'XML_STOCK',
                                    'xmlproxy' => 'XML_PROXY',
                                    'xmlriver' => 'XML_RIVER',
                                ][$provider['id']] ?? strtoupper($provider['id']);
                            @endphp
                            {{ __('Env') }}: <code>{{ $envPrefix }}_USER</code>, <code>{{ $envPrefix }}_KEY</code>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="card shadow-sm">
            <div class="card-header">
                <h3 class="card-title h6 mb-0">{{ __('Which module uses which service') }}</h3>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0 cabinet-xml-modules-table">
                        <thead>
                        <tr>
                            <th>{{ __('Module') }}</th>
                            <th>{{ __('Providers (order)') }}</th>
                            <th>{{ __('PS') }}</th>
                            <th>{{ __('Implementation') }}</th>
                            <th class="text-end">{{ __('Open') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($modules as $module)
                            <tr>
                                <td class="fw-semibold">{{ $module['title'] }}</td>
                                <td>
                                    @foreach($module['providers'] as $pid)
                                        @php $pTitle = config("cabinet-xml-providers.providers.{$pid}.title", $pid); @endphp
                                        <span class="badge text-bg-light border me-1 mb-1">{{ $pTitle }}</span>
                                    @endforeach
                                    @if(!empty($module['extra_providers']))
                                        @foreach($module['extra_providers'] as $extra)
                                            <span class="badge text-bg-info me-1 mb-1" title="{{ $extra['note'] ?? '' }}">
                                                {{ config("cabinet-xml-providers.providers.{$extra['provider']}.title", $extra['provider']) }}
                                                <span class="opacity-75">({{ __('wordstat') }})</span>
                                            </span>
                                        @endforeach
                                    @endif
                                </td>
                                <td class="small">
                                    @if(!empty($module['engines']))
                                        {{ implode(', ', $module['engines']) }}
                                    @else
                                        <span class="text-secondary">—</span>
                                    @endif
                                </td>
                                <td class="small text-secondary">
                                    <div>{{ $module['facade'] ?? '—' }}</div>
                                    <div class="mt-1">{{ $module['usage'] ?? '' }}</div>
                                </td>
                                <td class="text-end">
                                    @if(!empty($module['route']) && \Illuminate\Support\Facades\Route::has($module['route']))
                                        <a href="{{ route($module['route']) }}" class="btn btn-xs btn-outline-secondary btn-sm">
                                            <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <p class="text-secondary small mt-3 mb-0">
            <i class="bi bi-info-circle me-1" aria-hidden="true"></i>
            {{ __('Balances are cached for :sec seconds.', ['sec' => $cacheSeconds]) }}
            {{ __('XMLStock error 300 usually means an account or tariff issue — check the provider cabinet.') }}
        </p>
    </div>
@endsection

@section('js')
    <script>
        (function () {
            var refreshUrl = @json(route('admin.xml-providers.refresh'));
            var token = @json(csrf_token());

            document.getElementById('cabinet-xml-refresh-balances').addEventListener('click', function () {
                var btn = this;
                btn.disabled = true;
                fetch(refreshUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': token,
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (!data.providers) return;
                        Object.keys(data.providers).forEach(function (id) {
                            var row = data.providers[id];
                            var el = document.querySelector('.cabinet-xml-balance-value[data-provider="' + id + '"]');
                            var err = document.querySelector('.cabinet-xml-balance-error[data-provider="' + id + '"]');
                            if (!el) return;
                            if (row.ok) {
                                el.innerHTML = row.balance_formatted || row.balance;
                                if (err) err.textContent = '';
                            } else if (err) {
                                el.innerHTML = '<span class="text-warning">{{ __('Unavailable') }}</span>';
                                err.textContent = row.message || row.code || '{{ __('Could not load balance') }}';
                            }
                        });
                    })
                    .catch(function () {
                        alert('{{ __('Could not refresh balances') }}');
                    })
                    .finally(function () {
                        btn.disabled = false;
                    });
            });
        })();
    </script>
@endsection
