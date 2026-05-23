@php
    $missing = $limitCatalog['missing'] ?? [];
    $orphans = $limitCatalog['orphans'] ?? [];
    $missingEnforced = array_values(array_filter($missing, static function ($row) {
        return ($row['enforcement'] ?? '') !== \App\Support\TariffLimitRegistry::ENFORCEMENT_DISPLAY_ONLY;
    }));
    $missingDisplayOnly = array_values(array_filter($missing, static function ($row) {
        return ($row['enforcement'] ?? '') === \App\Support\TariffLimitRegistry::ENFORCEMENT_DISPLAY_ONLY;
    }));
@endphp

<div class="card shadow-sm mb-3" id="cabinet-ts-limit-catalog">
    <div class="card-header py-2">
        <h3 class="h6 mb-0">
            <i class="bi bi-journal-code me-1"></i>{{ __('Limit codes reference') }}
        </h3>
    </div>
    <div class="card-body small">
        <p class="text-secondary mb-3">
            {{ __('If a code is missing in the database (no property on this page), the module usually works without a tariff cap — checks in PHP are skipped. Exception: value 0 in DB can block access for strict limits.') }}
        </p>

        @if(count($missingEnforced) > 0)
            <p class="fw-semibold mb-2">{{ __('Known in code, not configured in DB (effectively unlimited)') }}</p>
            <div class="table-responsive mb-3">
                <table class="table table-sm table-bordered align-middle mb-0">
                    <thead class="table-light">
                    <tr>
                        <th scope="col">{{ __('Code') }}</th>
                        <th scope="col">{{ __('Module') }}</th>
                        <th scope="col">{{ __('Behavior') }}</th>
                        <th scope="col" class="text-end">{{ __('Action') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($missingEnforced as $row)
                        <tr>
                            <td><code>{{ $row['code'] }}</code></td>
                            <td>
                                <span class="fw-semibold">{{ $row['module'] }}</span>
                                <div class="text-secondary">{{ $row['hint'] }}</div>
                            </td>
                            <td>
                                <span class="badge text-bg-warning">{{ \App\Support\TariffLimitRegistry::enforcementLabel($row['enforcement']) }}</span>
                            </td>
                            <td class="text-end">
                                <a href="{{ route('tariff-settings.create') }}?code={{ urlencode($row['code']) }}"
                                   class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-plus-lg me-1"></i>{{ __('Add property') }}
                                </a>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-success mb-3">
                <i class="bi bi-check-circle me-1"></i>{{ __('All enforced limit codes from the catalog are present in the database.') }}
            </p>
        @endif

        @if(count($missingDisplayOnly) > 0)
            <details class="mb-3">
                <summary class="fw-semibold mb-2">{{ __('Optional tariff-page limits (not enforced in code)') }} ({{ count($missingDisplayOnly) }})</summary>
                <ul class="mb-0 ps-3 text-secondary">
                    @foreach($missingDisplayOnly as $row)
                        <li class="mb-2">
                            <code>{{ $row['code'] }}</code> — {{ $row['module'] }}
                            <span class="badge text-bg-light text-body border ms-1">{{ \App\Support\TariffLimitRegistry::enforcementLabel($row['enforcement']) }}</span>
                        </li>
                    @endforeach
                </ul>
            </details>
        @endif

        @if(count($orphans) > 0)
            <p class="fw-semibold mb-2 text-warning-emphasis">{{ __('In DB but not in code catalog (check spelling or legacy)') }}</p>
            <ul class="mb-0 ps-3">
                @foreach($orphans as $row)
                    <li>
                        <a href="{{ route('tariff-settings.index') }}#{{ $row['code'] }}"><code>{{ $row['code'] }}</code></a>
                        @if(!empty($row['name']))
                            — {{ $row['name'] }}
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif

        <p class="text-secondary mb-0 mt-3">
            <i class="bi bi-info-circle me-1"></i>
            {{ __('Modules with no row in this catalog and no check in PHP are outside the limit system entirely.') }}
        </p>
    </div>
</div>
