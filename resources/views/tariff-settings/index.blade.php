@extends('layouts.app')

@section('title', __('Tariffs settings'))

@section('css')
    <link rel="stylesheet" href="{{ asset('css/cabinet-tariff-settings.css') }}">
@endsection

@section('content')
    <div class="cabinet-tariff-settings-page">
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
            <div>
                <h2 class="h4 mb-2">
                    <i class="bi bi-sliders me-2 text-primary" aria-hidden="true"></i>{{ __('Tariffs settings') }}
                </h2>
                <p class="text-secondary small mb-0" style="max-width: 42rem;">
                    {{ __('Limits and messages per tariff plan. The code is used in PHP; values apply to Free, paid plans, etc.') }}
                </p>
            </div>
            <a href="{{ route('tariff-settings.create') }}" class="btn btn-sm btn-primary">
                <i class="bi bi-plus-lg me-1"></i>{{ __('Add limit') }}
            </a>
        </div>

        <div class="row g-3 mb-3 cabinet-ts-stat">
            <div class="col-12 col-md-6 d-flex">
                <div class="info-box mb-0 flex-fill">
                    <span class="info-box-icon text-bg-primary shadow-sm"><i class="bi bi-list-check"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ __('Limit properties') }}</span>
                        <span class="info-box-number">{{ $stats['settings'] }}</span>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6 d-flex">
                <div class="info-box mb-0 flex-fill">
                    <span class="info-box-icon text-bg-success shadow-sm"><i class="bi bi-currency-exchange"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ __('Values per tariff') }}</span>
                        <span class="info-box-number">{{ $stats['values'] }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="alert alert-light border small mb-3">
            <p class="mb-2">
                <i class="bi bi-info-circle me-1 text-primary"></i>
                {{ __('Tariff rows are shown in plan order: Free → Optimal → Ultimate → Maximum. Column «Sort» is legacy; display order no longer depends on it.') }}
            </p>
            <p class="mb-0 text-secondary">
                {{ __('Yellow row: limit is lower than on the previous (cheaper) plan — check values in DB (e.g. Maximum should usually be ≥ Ultimate).') }}
            </p>
        </div>

        @include('tariff-settings.partials.limit-catalog')

        <div class="card shadow-sm mb-3">
            <div class="card-body py-2">
                <div class="input-group input-group-sm" style="max-width: 20rem;">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="search"
                           class="form-control"
                           id="cabinet-ts-search"
                           placeholder="{{ __('Search by name or code') }}…"
                           autocomplete="off">
                </div>
            </div>
        </div>

        @forelse($settings as $setting)
            <div class="card shadow-sm mb-3 cabinet-ts-setting-card"
                 id="{{ $setting->code }}"
                 data-ts-search="{{ e(strtolower($setting->name . ' ' . $setting->code)) }}">
                <div class="card-header d-flex flex-wrap align-items-start justify-content-between gap-2">
                    <div class="min-w-0">
                        <h3 class="h6 mb-1">
                            {{ $setting->name ?: __('(no title)') }}
                            <span class="badge text-bg-secondary ms-1">#{{ $setting->id }}</span>
                        </h3>
                        <div class="cabinet-ts-code d-inline-flex align-items-center gap-1 small"
                             role="button"
                             tabindex="0"
                             data-copy-code="{{ e($setting->code) }}"
                             title="{{ __('Copy code') }}">
                            <code>{{ $setting->code }}</code>
                            <i class="bi bi-clipboard text-secondary"></i>
                        </div>
                    </div>
                    <div class="btn-group btn-group-sm flex-shrink-0">
                        <a href="{{ route('tariff-setting-values.create', $setting->id) }}"
                           class="btn btn-outline-primary"
                           title="{{ __('Add tariff value') }}">
                            <i class="bi bi-plus-lg"></i>
                        </a>
                        <a href="{{ route('tariff-settings.edit', $setting->id) }}"
                           class="btn btn-outline-secondary"
                           title="{{ __('Edit') }}">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <form method="POST"
                              action="{{ route('tariff-settings.destroy', $setting->id) }}"
                              class="d-inline cabinet-ts-confirm-form"
                              data-confirm="{{ e(__('Delete this limit property and all tariff values?')) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-outline-danger" title="{{ __('Delete') }}">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>

                @if($setting->description || $setting->message)
                    <div class="card-body border-bottom py-2 small text-secondary">
                        @if($setting->description)
                            <div class="mb-1"><strong>{{ __('Description') }}:</strong> {{ $setting->description }}</div>
                        @endif
                        @if($setting->message)
                            <div class="cabinet-ts-message">
                                <strong>{{ __('User message') }}:</strong>
                                <span class="font-monospace">{{ $setting->message }}</span>
                                <span class="text-muted d-block mt-1">{{ __('Placeholders') }}: <code>{TARIFF}</code>, <code>{VALUE}</code></span>
                            </div>
                        @endif
                    </div>
                @endif

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead class="table-light">
                            <tr>
                                <th scope="col">{{ __('Tariff') }}</th>
                                <th scope="col" class="text-end">{{ __('Value') }}</th>
                                <th scope="col" class="text-center">{{ __('Sort') }}</th>
                                <th scope="col" class="text-end">{{ __('Actions') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @php
                                $tierWarnings = \App\Support\TariffTierOrder::invertedLimitWarnings($setting->fields);
                            @endphp
                            @forelse($setting->fields as $field)
                                <tr class="{{ isset($tierWarnings[$field->id]) ? 'table-warning' : '' }}">
                                    <td>
                                        <span class="fw-semibold">{{ $tariffLabels[$field->tariff] ?? $field->tariff }}</span>
                                        @if(isset($tariffLabels[$field->tariff]))
                                            <code class="small text-secondary ms-1">{{ $field->tariff }}</code>
                                        @endif
                                        @if(isset($tierWarnings[$field->id]))
                                            <div class="small text-warning-emphasis mt-1">
                                                <i class="bi bi-exclamation-triangle me-1"></i>{{ $tierWarnings[$field->id] }}
                                            </div>
                                        @endif
                                    </td>
                                    <td class="text-end font-monospace">{{ number_format($field->value, 0, ',', ' ') }}</td>
                                    <td class="text-center text-secondary">{{ $field->sort }}</td>
                                    <td class="text-end">
                                        <div class="d-inline-flex gap-1">
                                            <a href="{{ route('tariff-setting-values.edit', $field->id) }}"
                                               class="btn btn-sm btn-outline-secondary"
                                               title="{{ __('Edit') }}">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <form method="POST"
                                                  action="{{ route('tariff-setting-values.destroy', $field->id) }}"
                                                  class="d-inline cabinet-ts-confirm-form"
                                                  data-confirm="{{ e(__('Delete this value?')) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="{{ __('Delete') }}">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-secondary py-4">
                                        {{ __('No values for tariffs yet.') }}
                                        <a href="{{ route('tariff-setting-values.create', $setting->id) }}">{{ __('Add the first one') }}</a>
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @empty
            <div class="card shadow-sm">
                <div class="card-body text-center text-secondary py-5">
                    {{ __('No limit properties yet.') }}
                    <a href="{{ route('tariff-settings.create') }}">{{ __('Add limit') }}</a>
                </div>
            </div>
        @endforelse
    </div>
@endsection

@section('js')
    <script>
        (function () {
            var search = document.getElementById('cabinet-ts-search');
            if (search) {
                search.addEventListener('input', function () {
                    var q = search.value.trim().toLowerCase();
                    document.querySelectorAll('.cabinet-ts-setting-card[data-ts-search]').forEach(function (card) {
                        var hay = card.getAttribute('data-ts-search') || '';
                        card.style.display = q === '' || hay.indexOf(q) !== -1 ? '' : 'none';
                    });
                });
            }

            document.querySelectorAll('[data-copy-code]').forEach(function (el) {
                var copy = function () {
                    var code = el.getAttribute('data-copy-code') || '';
                    if (!code) return;
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(code);
                    } else {
                        var ta = document.createElement('textarea');
                        ta.value = code;
                        document.body.appendChild(ta);
                        ta.select();
                        document.execCommand('copy');
                        document.body.removeChild(ta);
                    }
                };
                el.addEventListener('click', copy);
                el.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        copy();
                    }
                });
            });

            if (window.location.hash) {
                var target = document.querySelector(window.location.hash);
                if (target) {
                    target.classList.add('border-primary');
                    target.scrollIntoView({behavior: 'smooth', block: 'start'});
                }
            }

            document.querySelectorAll('form.cabinet-ts-confirm-form[data-confirm]').forEach(function (form) {
                form.addEventListener('submit', function (e) {
                    var msg = form.getAttribute('data-confirm');
                    if (!msg || !window.confirm(msg)) {
                        e.preventDefault();
                    }
                });
            });
        })();
    </script>
@endsection
