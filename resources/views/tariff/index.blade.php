@php
    use App\Classes\Tariffs\FreeTariff;
    use Illuminate\Support\Str;

    $currentTariffName = $actual->isNotEmpty() ? ($actual['info'][0]['value'] ?? null) : null;
    $selectedCode = array_key_first($select['tariffs']);
    $freeTariffPlan = new FreeTariff();

    $planCardHighlightCodes = [
        'RelevanceAnalysis',
        'TextAnalyzer',
        'CompetitorAnalysisPhrases',
        'IndexCheck',
        'Clusters',
        'domainMonitoringProject',
        'monitoring',
    ];

    $plans = [];
    foreach ($tariffsArray as $tariff) {
        $code = array_search($tariff['name'], $select['tariffs'], true);
        if ($code === false && $tariff['name'] === $freeTariffPlan->name()) {
            $code = $freeTariffPlan->code();
        }
        if ($code === false) {
            continue;
        }
        $priceSetting = collect($tariff['settings'])->first(function ($s) {
            $name = $s['name'] ?? '';
            return $name === 'Цена тарифа' || Str::contains($name, 'Цена');
        });
        $plans[] = [
            'code' => $code,
            'name' => $tariff['name'],
            'settings' => $tariff['settings'],
            'price' => $priceSetting['value'] ?? 0,
            'is_current' => $currentTariffName && $currentTariffName === $tariff['name'],
            'is_popular' => Str::contains(mb_strtolower($tariff['name']), 'ультимат')
                || Str::contains(mb_strtolower($tariff['name']), 'ultimate'),
        ];
    }

    $featureRows = [];
    foreach ($tariffsArray as $tariff) {
        foreach ($tariff['settings'] as $setting) {
            $name = $setting['name'] ?? '';
            if ($name === '' || $name === 'Цена тарифа') {
                continue;
            }
            if (!isset($featureRows[$name])) {
                $featureRows[$name] = [
                    'slug' => Str::limit(md5($name), 10, ''),
                    'values' => [],
                ];
            }
        }
    }
    foreach ($plans as $plan) {
        foreach ($featureRows as $featureName => &$row) {
            $match = collect($plan['settings'])->firstWhere('name', $featureName);
            $row['values'][$plan['code']] = $match ?? null;
        }
        unset($row);
    }

    $makeEmailAlertsRow = static function (string $slug) use ($plans): array {
        $row = [
            'slug' => $slug,
            'synthetic_email_alerts' => true,
            'values' => [],
        ];
        foreach ($plans as $plan) {
            $row['values'][$plan['code']] = [
                'is_free' => $plan['code'] === 'Free',
            ];
        }

        return $row;
    };

    $siteMonEmailLabel = __('Site monitoring email alerts');
    $siteMonEmailRow = $makeEmailAlertsRow('site-mon-email');
    $domainInfoEmailLabel = __('Domain information email alerts');
    $domainInfoEmailRow = $makeEmailAlertsRow('domain-info-email');
    $backlinkEmailLabel = __('Backlink email alerts');
    $backlinkEmailRow = $makeEmailAlertsRow('backlink-email');

    $orderedFeatureRows = [];
    $siteMonInserted = false;
    $domainInfoInserted = false;
    $backlinkInserted = false;
    foreach ($featureRows as $featureName => $row) {
        $orderedFeatureRows[$featureName] = $row;
        $featureLower = mb_strtolower($featureName);
        if (!$siteMonInserted && (
            Str::contains($featureLower, 'мониторинг сайтов')
            || Str::contains($featureLower, 'domain monitoring')
            || Str::contains($featureName, 'domainMonitoring')
        )) {
            $orderedFeatureRows[$siteMonEmailLabel] = $siteMonEmailRow;
            $siteMonInserted = true;
        }
        if (!$domainInfoInserted && (
            Str::contains($featureLower, 'срока регистрации')
            || Str::contains($featureLower, 'domain information')
            || Str::contains($featureName, 'DomainInformation')
        )) {
            $orderedFeatureRows[$domainInfoEmailLabel] = $domainInfoEmailRow;
            $domainInfoInserted = true;
        }
        if (!$backlinkInserted && (
            Str::contains($featureLower, 'размещенных ссылок')
            || Str::contains($featureLower, 'размещённых ссылок')
            || Str::contains($featureName, 'Backlink')
        )) {
            $orderedFeatureRows[$backlinkEmailLabel] = $backlinkEmailRow;
            $backlinkInserted = true;
        }
    }
    if (!$siteMonInserted) {
        $orderedFeatureRows[$siteMonEmailLabel] = $siteMonEmailRow;
    }
    if (!$domainInfoInserted) {
        $orderedFeatureRows[$domainInfoEmailLabel] = $domainInfoEmailRow;
    }
    if (!$backlinkInserted) {
        $orderedFeatureRows[$backlinkEmailLabel] = $backlinkEmailRow;
    }
    $featureRows = $orderedFeatureRows;
@endphp

@component('component.card', ['title' => __('Tariff')])
    @slot('css')
        <link rel="stylesheet" href="{{ asset('plugins/toastr/toastr.min.css') }}">
        <link rel="stylesheet" href="{{ asset('css/cabinet-tariff.css') }}">
    @endslot

    <div class="cabinet-tariff-page">
        @if(session('info'))
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <i class="bi bi-info-circle me-2"></i>{{ session('info') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('Close') }}"></button>
            </div>
        @endif

        <div class="text-center mb-4">
            <p class="text-secondary mb-0">
                {{ __('Choose a plan for your team. Prices depend on the billing period.') }}
            </p>
        </div>

        <div class="row g-4 row-cols-1 row-cols-sm-2 row-cols-xl-{{ min(count($plans), 4) }} mb-4">
            @foreach($plans as $plan)
                <div class="col">
                    <div class="card cabinet-tariff-plan h-100 {{ $plan['is_current'] ? 'is-current border-success' : '' }} {{ $selectedCode === $plan['code'] ? 'is-selected border-primary shadow-sm' : '' }} {{ $plan['is_popular'] && !$plan['is_current'] ? 'border-primary' : '' }}"
                         data-tariff-code="{{ $plan['code'] }}"
                         role="button"
                         tabindex="0">
                        @if($plan['is_popular'])
                            <span class="badge text-bg-primary position-absolute top-0 start-50 translate-middle">
                                {{ __('Popular') }}
                            </span>
                        @endif
                        @if($plan['is_current'])
                            <span class="badge text-bg-success position-absolute top-0 end-0 m-2">
                                {{ __('Your tariff') }}
                            </span>
                        @endif
                        <div class="card-body p-4">
                            <h5 class="fw-semibold mb-1">{{ $plan['name'] }}</h5>
                            <div class="mb-3">
                                @if((int) $plan['price'] === 0)
                                    <span class="display-6 fw-bold">0</span>
                                    <span class="text-secondary">₽</span>
                                @else
                                    <span class="display-6 fw-bold">{{ number_format((int) $plan['price'], 0, '.', ' ') }}</span>
                                    <span class="text-secondary">₽ / {{ __('day') }}</span>
                                @endif
                            </div>
                            <button type="button"
                                    class="btn {{ $selectedCode === $plan['code'] ? 'btn-primary' : 'btn-outline-primary' }} w-100 btn-select-tariff"
                                    data-tariff-code="{{ $plan['code'] }}">
                                {{ __('Select') }}
                            </button>
                            <ul class="list-unstyled small mb-0 mt-3">
                                @foreach(collect($planCardHighlightCodes)->map(function ($code) use ($plan) {
                                    return $plan['settings'][$code] ?? null;
                                })->filter() as $setting)
                                    @php $fname = $setting['name'] ?? ''; @endphp
                                    @if($fname !== '' && $fname !== 'Цена тарифа')
                                        <li class="mb-2 d-flex align-items-start gap-2">
                                            @if(($setting['value'] ?? 0) == 0)
                                                <i class="bi bi-dash-circle text-secondary flex-shrink-0" aria-hidden="true"></i>
                                                <span class="text-secondary">{{ $fname }}</span>
                                            @elseif((int) $setting['value'] === 1000000)
                                                <i class="bi bi-check-circle-fill text-success flex-shrink-0" aria-hidden="true"></i>
                                                <span>{{ $fname }}: <i class="bi bi-infinity" title="{{ __('Unlimited') }}"></i></span>
                                            @else
                                                <i class="bi bi-check-circle-fill text-success flex-shrink-0" aria-hidden="true"></i>
                                                <span>{{ $fname }}: <strong>{{ number_format((int) $setting['value'], 0, '.', ' ') }}</strong></span>
                                            @endif
                                        </li>
                                    @endif
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="row g-4 mb-4">
            <div class="col-lg-{{ $actual->isNotEmpty() ? '5' : '12' }}">
                <div class="card card-outline card-primary h-100">
                    <div class="card-header">
                        <h3 class="card-title mb-0">
                            <i class="bi bi-cart-plus me-1"></i>{{ __('Purchase tariff') }}
                        </h3>
                    </div>
                    {!! Form::open(['method' => 'POST', 'route' => ['tariff.store']]) !!}
                    <div class="card-body">
                        @if(session('error'))
                            <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
                        @endif

                        @include('tariff.partials._form')
                        <hr class="my-4">
                        <h6 class="text-secondary text-uppercase small fw-semibold mb-3">{{ __('Order summary') }}</h6>
                        @include('tariff.partials._table', ['id' => 'total'])
                    </div>
                    <div class="card-footer d-grid gap-2">
                        {!! Form::submit(__('Buy'), ['class' => 'btn btn-success btn-lg']) !!}
                        <a href="{{ route('balance.index') }}" class="btn btn-outline-primary">
                            <i class="bi bi-wallet2 me-1"></i>{{ __('Top up your balance') }}
                        </a>
                    </div>
                    {!! Form::close() !!}
                </div>
            </div>
            @if($actual->isNotEmpty())
                <div class="col-lg-7">
                    @include('tariff.subscribe')
                </div>
            @endif
        </div>

        @if(count($featureRows) > 0)
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title mb-0">
                        <i class="bi bi-table me-1"></i>{{ __('Compare features') }}
                    </h3>
                    <p class="text-secondary small mb-0 mt-2">
                        {{ __('Site monitoring tariff compare footnote') }}
                        {{ __('Index check tariff compare footnote') }}
                    </p>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 cabinet-tariff-compare">
                            <thead class="table-light">
                            <tr>
                                <th scope="col" style="min-width: 12rem">{{ __('Feature') }}</th>
                                @foreach($plans as $plan)
                                    <th scope="col" class="text-center text-nowrap">{{ $plan['name'] }}</th>
                                @endforeach
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($featureRows as $featureName => $row)
                                <tr data-feature="{{ $row['slug'] }}">
                                    <td class="fw-medium">{{ $featureName }}</td>
                                    @foreach($plans as $plan)
                                        @php $cell = $row['values'][$plan['code']] ?? null; @endphp
                                        <td class="text-center col-tariff-{{ $plan['code'] }} {{ $selectedCode === $plan['code'] ? 'col-highlight' : '' }}">
                                            @if($row['synthetic_email_alerts'] ?? false)
                                                @if($cell['is_free'] ?? false)
                                                    <span class="d-inline-block small text-secondary">
                                                        <i class="bi bi-telegram text-primary" aria-hidden="true"></i>
                                                        {{ __('Site monitoring compare telegram only') }}
                                                    </span>
                                                @else
                                                    <span class="d-inline-block small">
                                                        <i class="bi bi-check-circle-fill text-success" aria-hidden="true"></i>
                                                        {{ __('Site monitoring compare email telegram') }}
                                                    </span>
                                                @endif
                                            @elseif(!$cell || (int) ($cell['value'] ?? 0) === 0)
                                                <span class="text-secondary">
                                                    <i class="bi bi-x-lg" aria-hidden="true"></i>
                                                    <span class="visually-hidden">{{ __('Not available') }}</span>
                                                </span>
                                            @elseif((int) $cell['value'] === 1000000)
                                                <i class="bi bi-infinity text-success" title="{{ __('Unlimited') }}"></i>
                                            @else
                                                <strong>{{ number_format((int) $cell['value'], 0, '.', ' ') }}</strong>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif
    </div>

    @slot('js')
        <script src="{{ asset('plugins/toastr/toastr.min.js') }}"></script>
        <script>
            (function () {
                var $tariff = $('#tariff');
                var $period = $('#period');

                function highlightPlan(code) {
                    document.querySelectorAll('.cabinet-tariff-plan').forEach(function (el) {
                        var match = el.getAttribute('data-tariff-code') === code;
                        el.classList.toggle('is-selected', match);
                        el.classList.toggle('border-primary', match);
                        el.classList.toggle('shadow-sm', match);
                        var btn = el.querySelector('.btn-select-tariff');
                        if (btn) {
                            btn.classList.toggle('btn-primary', match);
                            btn.classList.toggle('btn-outline-primary', !match);
                        }
                    });
                    document.querySelectorAll('.cabinet-tariff-compare .col-highlight').forEach(function (el) {
                        el.classList.remove('col-highlight');
                    });
                    document.querySelectorAll('.col-tariff-' + code).forEach(function (el) {
                        el.classList.add('col-highlight');
                    });
                }

                function selectTariff(code) {
                    if (!code || !$tariff.length) {
                        return;
                    }
                    $tariff.val(code);
                    highlightPlan(code);
                    $tariff.trigger('change');
                }

                document.querySelectorAll('.cabinet-tariff-plan, .btn-select-tariff').forEach(function (el) {
                    el.addEventListener('click', function (e) {
                        e.preventDefault();
                        e.stopPropagation();
                        var code = el.getAttribute('data-tariff-code')
                            || el.closest('[data-tariff-code]')?.getAttribute('data-tariff-code');
                        selectTariff(code);
                    });
                    el.addEventListener('keydown', function (e) {
                        if (e.key === 'Enter' || e.key === ' ') {
                            e.preventDefault();
                            var code = el.getAttribute('data-tariff-code');
                            selectTariff(code);
                        }
                    });
                });

                toastr.options = {preventDuplicates: true, timeOut: 1500};

                function loadTotal() {
                    axios.request({
                        url: '/tariff/total',
                        method: 'POST',
                        data: {
                            name: $tariff.val(),
                            period: $period.val(),
                        },
                    }).then(function (response) {
                        var total = $('#total tbody');
                        total.find('tr').remove();
                        $.each(response.data, function (i, val) {
                            var tr = $('<tr />');
                            tr.append(
                                $('<th />').addClass('text-secondary fw-normal').text(val.title),
                                $('<td />').addClass('fw-semibold text-end').text(val.value)
                            );
                            total.append(tr);
                        });
                    }).catch(function (error) {
                        if (error.response) {
                            toastr.error(error.response.data.message);
                        }
                    });
                }

                $tariff.add($period).on('change', function () {
                    highlightPlan($tariff.val());
                    loadTotal();
                });

                highlightPlan($tariff.val());
            })();

            $('#unsubscribe').on('click', function () {
                axios.request({
                    url: @json(route('tariff.unsubscribe', ['confirm'])),
                    method: 'GET',
                }).then(function (response) {
                    var msg = 'Вам будет начислено ' + response.data.prices.priceWithDiscount
                        + ' баллов за ' + response.data.active_days
                        + ' дней, по текущей ставке тарифа ' + response.data.prices.percent
                        + '%. Вы уверены, что хотите отписаться от тарифа?';
                    if (confirm(msg)) {
                        axios.get(@json(route('tariff.unsubscribe', ['canceled'])))
                            .then(function () {
                                window.location.reload();
                            })
                            .catch(function (error) {
                                if (error.response) {
                                    toastr.error(error.response.data.message);
                                }
                            });
                    }
                }).catch(function (error) {
                    if (error.response) {
                        toastr.error(error.response.data.message);
                    }
                });
            });
        </script>
    @endslot
@endcomponent
