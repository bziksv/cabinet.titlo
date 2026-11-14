@extends('layouts.app')

@section('title', __('AI management'))

@section('content')
    @php
        $s = $settings ?? [];
        $providers = $s['providers'] ?? [];
        $modules = $s['modules'] ?? [];
        $moduleMap = $s['module_providers'] ?? [];
        $defaultProvider = $s['default_provider'] ?? 'plusvibeapi';
    @endphp

    <div class="cabinet-ai-providers-page">
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
            <div>
                <h2 class="h4 mb-2 d-flex flex-wrap align-items-center gap-1">
                    <i class="bi bi-robot text-primary" aria-hidden="true"></i>
                    <span>{{ __('AI management') }}</span>
                    @include('partials.cabinet-module-version-badge', ['configKey' => 'cabinet-ai-providers'])
                </h2>
                <p class="text-secondary small mb-0" style="max-width: 44rem;">
                    {{ __('AI providers admin lead') }}
                </p>
            </div>
        </div>

        <div class="row g-3 mb-4">
            @foreach($providers as $code => $p)
                <div class="col-12 col-md-6">
                    <div class="card shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                <div>
                                    <div class="fw-semibold">{{ $p['title'] ?? $code }}</div>
                                    <div class="small text-secondary">{{ $p['hint'] ?? '' }}</div>
                                </div>
                                <span class="badge {{ !empty($p['token_set']) ? 'text-bg-success' : 'text-bg-secondary' }}">
                                    {{ !empty($p['token_set']) ? __('AI providers token set') : __('AI providers token missing') }}
                                </span>
                            </div>
                            <dl class="row small mb-0">
                                <dt class="col-4 text-secondary">{{ __('AI providers base uri') }}</dt>
                                <dd class="col-8"><code class="small">{{ $p['base_uri'] ?? '—' }}</code></dd>
                                <dt class="col-4 text-secondary">{{ __('AI providers model') }}</dt>
                                <dd class="col-8"><code class="small">{{ $p['model'] ?: '—' }}</code></dd>
                                @if(!empty($p['fallback_model']))
                                    <dt class="col-4 text-secondary">{{ __('AI providers fallback model') }}</dt>
                                    <dd class="col-8"><code class="small">{{ $p['fallback_model'] }}</code></dd>
                                @endif
                                <dt class="col-4 text-secondary">{{ __('AI providers token') }}</dt>
                                <dd class="col-8"><code class="small">{{ $p['token_masked'] ?? '—' }}</code></dd>
                            </dl>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="card shadow-sm">
            <div class="card-header">
                <h3 class="card-title h6 mb-0">{{ __('AI providers module routing') }}</h3>
            </div>
            <div class="card-body">
                <form method="post" action="{{ route('admin.ai-providers.update') }}">
                    @csrf
                    @method('PUT')

                    <div class="mb-3" style="max-width: 28rem;">
                        <label class="form-label fw-semibold" for="ai-default-provider">{{ __('AI providers default') }}</label>
                        <select name="default_provider" id="ai-default-provider" class="form-select">
                            @foreach($providers as $code => $p)
                                <option value="{{ $code }}" @if($defaultProvider === $code) selected @endif>
                                    {{ $p['title'] ?? $code }}
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text">{{ __('AI providers default hint') }}</div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead>
                            <tr>
                                <th>{{ __('Module') }}</th>
                                <th>{{ __('AI providers provider') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($modules as $code => $mod)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $mod['title'] ?? $code }}</div>
                                        <div class="small text-secondary">{{ $mod['hint'] ?? '' }}</div>
                                        <code class="small text-muted">{{ $code }}</code>
                                    </td>
                                    <td style="min-width: 14rem;">
                                        <select name="module_providers[{{ $code }}]" class="form-select">
                                            @foreach($providers as $pCode => $p)
                                                <option value="{{ $pCode }}"
                                                    @if(($moduleMap[$code] ?? $defaultProvider) === $pCode) selected @endif>
                                                    {{ $p['title'] ?? $pCode }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3 d-flex flex-wrap gap-2 align-items-center">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-1" aria-hidden="true"></i>{{ __('Save') }}
                        </button>
                        @if(!empty($s['updated_at']))
                            <span class="small text-secondary">{{ __('Updated') }}: {{ $s['updated_at'] }}</span>
                        @endif
                    </div>
                </form>
            </div>
        </div>

        <p class="small text-secondary mt-3 mb-0">
            {{ __('AI providers env hint') }}
        </p>
    </div>
@endsection
