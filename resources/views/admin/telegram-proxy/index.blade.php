@extends('layouts.app')

@section('title', __('Telegram proxy management'))

@section('css')
    <link rel="stylesheet" href="{{ asset('css/cabinet-telegram-proxy.css') }}?v={{ @filemtime(public_path('css/cabinet-telegram-proxy.css')) ?: time() }}">
@endsection

@section('content')
    @php
        $status = session('telegram_proxy_status') ?? $status;
        $direct = $status['direct'] ?? [];
        $proxyRows = $status['proxies'] ?? [];
        $sendOrder = $status['send_order'] ?? [];
    @endphp

    <div class="cabinet-telegram-proxy-page">
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
            <div>
                <h2 class="h4 mb-2">
                    <i class="bi bi-shield-lock me-2 text-primary" aria-hidden="true"></i>{{ __('Telegram proxy management') }}
                </h2>
                <p class="text-secondary small mb-0" style="max-width: 44rem;">
                    {{ __('Telegram proxy admin lead') }}
                </p>
            </div>
            <form action="{{ route('admin.telegram-proxy.refresh') }}" method="post" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i>{{ __('Telegram proxy refresh check') }}
                </button>
            </form>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-12 col-md-4">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="info-box mb-0">
                            <span class="info-box-icon text-bg-{{ !empty($status['token_configured']) ? 'success' : 'danger' }} shadow-sm">
                                <i class="bi bi-key" aria-hidden="true"></i>
                            </span>
                            <div class="info-box-content">
                                <span class="info-box-text">{{ __('Telegram proxy token') }}</span>
                                <span class="info-box-number small">
                                    {{ !empty($status['token_configured']) ? __('Configured') : __('Not configured') }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="info-box mb-0">
                            <span class="info-box-icon text-bg-{{ !empty($status['proxy_configured']) ? 'primary' : 'secondary' }} shadow-sm">
                                <i class="bi bi-hdd-network" aria-hidden="true"></i>
                            </span>
                            <div class="info-box-content">
                                <span class="info-box-text">{{ __('Telegram proxy list count') }}</span>
                                <span class="info-box-number small text-break">
                                    @if(($status['proxy_count'] ?? 0) > 0)
                                        {{ $status['proxy_count'] }} — <code>{{ $status['proxy_masked'] }}</code>
                                    @elseif(!empty($status['env_proxy_masked']))
                                        .env: <code>{{ $status['env_proxy_masked'] }}</code>
                                    @else
                                        {{ __('Telegram proxy not set') }}
                                    @endif
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="info-box mb-0">
                            <span class="info-box-icon text-bg-{{ ($telegramConnected ?? false) ? 'success' : 'warning' }} shadow-sm">
                                <i class="bi bi-telegram" aria-hidden="true"></i>
                            </span>
                            <div class="info-box-content">
                                <span class="info-box-text">{{ __('Telegram proxy your bot') }}</span>
                                <span class="info-box-number small">
                                    @if($telegramConnected ?? false)
                                        {{ __('Connected') }}
                                    @else
                                        <a href="{{ route('profile.index') }}#telegram">{{ __('Connect Telegram in profile') }}</a>
                                    @endif
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h3 class="card-title h6 mb-0">{{ __('Telegram proxy list title') }}</h3>
                <form action="{{ route('admin.telegram-proxy.import-env') }}" method="post" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-box-arrow-in-down me-1" aria-hidden="true"></i>{{ __('Telegram proxy import env') }}
                    </button>
                </form>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0 align-middle">
                        <thead class="table-light">
                        <tr>
                            <th>{{ __('Name') }}</th>
                            <th>{{ __('Priority') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th class="text-center">{{ __('HTTP') }}</th>
                            <th>{{ __('Time') }}</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($proxyRows as $row)
                            @php
                                $probe = $row['probe'] ?? [];
                                $editRow = ($proxyRegistry[$row['id']] ?? null);
                                $editCollapseId = 'tg-proxy-edit-' . $row['id'];
                            @endphp
                            <tr class="{{ empty($row['enabled']) ? 'table-secondary' : '' }}">
                                <td>
                                    <span class="fw-medium">{{ $row['label'] }}</span>
                                    <div class="small"><code>{{ $row['url_masked'] }}</code></div>
                                </td>
                                <td class="small">{{ $row['priority'] }}</td>
                                <td class="small">
                                    @if(!empty($row['enabled']))
                                        <span class="badge text-bg-success">{{ __('Enabled') }}</span>
                                    @else
                                        <span class="badge text-bg-secondary">{{ __('Disabled') }}</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if(!empty($probe['http_code']))
                                        <span class="badge text-bg-{{ !empty($probe['ok']) ? 'success' : 'danger' }}">{{ $probe['http_code'] }}</span>
                                    @else
                                        <span class="badge text-bg-danger">—</span>
                                    @endif
                                </td>
                                <td class="small text-secondary">{{ $probe['elapsed_ms'] ?? 0 }} ms</td>
                                <td class="text-end text-nowrap">
                                    @if($editRow)
                                        <button type="button"
                                                class="btn btn-sm btn-outline-primary"
                                                title="{{ __('Edit') }}"
                                                data-bs-toggle="collapse"
                                                data-bs-target="#{{ $editCollapseId }}"
                                                aria-expanded="false"
                                                aria-controls="{{ $editCollapseId }}">
                                            <i class="bi bi-pencil" aria-hidden="true"></i>
                                        </button>
                                    @endif
                                    <form action="{{ route('admin.telegram-proxy.proxies.destroy', $row['id']) }}" method="post" class="d-inline" onsubmit='return confirm(@json(__('Telegram proxy delete confirm')))'>
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="{{ __('Delete') }}">
                                            <i class="bi bi-trash" aria-hidden="true"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            @if($editRow)
                                <tr class="collapse cabinet-tg-proxy-edit-row" id="{{ $editCollapseId }}">
                                    <td colspan="6" class="bg-body-tertiary border-top-0 pt-0 pb-3">
                                        <form action="{{ route('admin.telegram-proxy.proxies.update', $row['id']) }}" method="post" class="row g-2 align-items-end mt-2">
                                            @csrf
                                            @method('PUT')
                                            <div class="col-12">
                                                <span class="small fw-semibold text-secondary">{{ __('Telegram proxy edit') }}</span>
                                            </div>
                                            <div class="col-12 col-md-3">
                                                <label class="form-label small mb-1">{{ __('Name') }}</label>
                                                <input type="text" class="form-control form-control-sm" name="label" required maxlength="120" value="{{ $editRow['label'] }}">
                                            </div>
                                            <div class="col-12 col-md-5">
                                                <label class="form-label small mb-1">URL</label>
                                                <input type="text" class="form-control form-control-sm font-monospace" name="url" required maxlength="500" value="{{ $editRow['url'] }}">
                                            </div>
                                            <div class="col-6 col-md-2">
                                                <label class="form-label small mb-1">{{ __('Priority') }}</label>
                                                <input type="number" class="form-control form-control-sm" name="priority" value="{{ $editRow['priority'] }}" min="0" max="999">
                                            </div>
                                            <div class="col-6 col-md-2">
                                                <div class="form-check mb-2">
                                                    <input class="form-check-input" type="checkbox" name="enabled" value="1" id="tg-proxy-enabled-{{ $row['id'] }}" @if(!empty($editRow['enabled'])) checked @endif>
                                                    <label class="form-check-label small" for="tg-proxy-enabled-{{ $row['id'] }}">{{ __('Enabled') }}</label>
                                                </div>
                                                <button type="submit" class="btn btn-sm btn-primary w-100">
                                                    <i class="bi bi-check-lg me-1" aria-hidden="true"></i>{{ __('Save') }}
                                                </button>
                                            </div>
                                        </form>
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr>
                                <td colspan="6" class="small text-secondary p-3">
                                    {{ __('Telegram proxy list empty') }}
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-body border-top">
                <h4 class="h6 mb-3">{{ __('Telegram proxy add') }}</h4>
                <form action="{{ route('admin.telegram-proxy.proxies.store') }}" method="post" class="row g-2 align-items-end">
                    @csrf
                    <div class="col-12 col-md-3">
                        <label class="form-label small mb-1" for="tg-proxy-label">{{ __('Name') }}</label>
                        <input type="text" class="form-control form-control-sm" id="tg-proxy-label" name="label" required maxlength="120" placeholder="s3 backup">
                    </div>
                    <div class="col-12 col-md-5">
                        <label class="form-label small mb-1" for="tg-proxy-url">URL</label>
                        <input type="text" class="form-control form-control-sm font-monospace" id="tg-proxy-url" name="url" required maxlength="500" placeholder="socks5h://user:pass@host:port">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small mb-1" for="tg-proxy-priority">{{ __('Priority') }}</label>
                        <input type="number" class="form-control form-control-sm" id="tg-proxy-priority" name="priority" value="50" min="0" max="999">
                    </div>
                    <div class="col-6 col-md-2">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="enabled" value="1" id="tg-proxy-enabled" checked>
                            <label class="form-check-label small" for="tg-proxy-enabled">{{ __('Enabled') }}</label>
                        </div>
                        <button type="submit" class="btn btn-sm btn-primary w-100">
                            <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>{{ __('Add') }}
                        </button>
                    </div>
                </form>
            </div>
            <div class="card-footer small text-secondary">
                {{ __('Telegram proxy list hint') }}
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-header">
                <h3 class="card-title h6 mb-0">{{ __('Telegram proxy connectivity') }}</h3>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0 align-middle">
                        <thead class="table-light">
                        <tr>
                            <th>{{ __('Channel') }}</th>
                            <th class="text-center">{{ __('HTTP') }}</th>
                            <th>{{ __('Time') }}</th>
                            <th>{{ __('Result') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        <tr>
                            <td>{{ __('Telegram proxy direct') }}</td>
                            <td class="text-center">
                                @if(!empty($direct['http_code']))
                                    <span class="badge text-bg-{{ !empty($direct['ok']) ? 'success' : 'danger' }}">{{ $direct['http_code'] }}</span>
                                @else
                                    <span class="badge text-bg-danger">—</span>
                                @endif
                            </td>
                            <td class="small text-secondary">{{ $direct['elapsed_ms'] ?? 0 }} ms</td>
                            <td class="small">
                                @if(!empty($direct['curl_error']))
                                    <span class="text-danger">{{ $direct['curl_error'] }}</span>
                                @elseif(!empty($direct['ok']))
                                    <span class="text-success">{{ __('OK') }}</span>
                                @else
                                    <span class="text-warning">{{ __('Telegram proxy timeout hint') }}</span>
                                @endif
                            </td>
                        </tr>
                        @if($sendOrder !== [])
                            <tr class="table-light">
                                <td colspan="4" class="small">
                                    <strong>{{ __('Telegram proxy send order') }}:</strong>
                                    <code>{{ implode(' → ', $sendOrder) }}</code>
                                </td>
                            </tr>
                        @endif
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer small text-secondary">
                {{ __('Telegram proxy env hint') }}
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h3 class="card-title h6 mb-0">{{ __('Telegram proxy tests') }}</h3>
            </div>
            <div class="card-body d-flex flex-wrap gap-2">
                <form action="{{ route('admin.telegram-proxy.test-notify') }}" method="post" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-outline-primary" @if(!($telegramConnected ?? false)) disabled @endif>
                        <i class="bi bi-send me-1" aria-hidden="true"></i>{{ __('Telegram proxy test simple') }}
                    </button>
                </form>
                <form action="{{ route('admin.telegram-proxy.test-backlink') }}"
                      method="post"
                      class="d-inline"
                      onsubmit='return confirm(@json(__('Backlink test telegram confirm')))'>
                    @csrf
                    <button type="submit"
                            class="btn btn-outline-secondary"
                            @if(!($telegramConnected ?? false)) disabled @endif
                            title="{{ __('Backlink test telegram hint') }}">
                        <i class="bi bi-link-45deg me-1" aria-hidden="true"></i>{{ __('Telegram proxy test backlink') }}
                        @if(($brokenLinksCount ?? 0) > 0)
                            <span class="badge text-bg-danger ms-1">{{ $brokenLinksCount }}</span>
                        @endif
                    </button>
                </form>
            </div>
            <div class="card-footer small text-secondary mb-0">
                {{ __('Telegram proxy tests hint') }}
            </div>
        </div>

        @include('admin.telegram-proxy.partials.admin-debug-log', ['debugLogText' => $debugLogText ?? ''])

        <div class="card shadow-sm">
            <div class="card-header">
                <h3 class="card-title h6 mb-0">{{ __('Telegram proxy modules checklist') }}</h3>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0 align-middle cabinet-telegram-modules-table">
                        <thead class="table-light">
                        <tr>
                            <th>{{ __('Module') }}</th>
                            <th>{{ __('Page') }}</th>
                            <th>Cron / API</th>
                            <th class="text-center">{{ __('Uses proxy') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($modules as $module)
                            <tr>
                                <td class="fw-medium">{{ $module['title'] }}</td>
                                <td class="small">
                                    @if(!empty($module['url']))
                                        <a href="{{ $module['url'] }}">{{ $module['url'] }}</a>
                                    @else
                                        <span class="text-secondary">—</span>
                                    @endif
                                </td>
                                <td class="small text-secondary"><code>{{ $module['cron'] ?? '—' }}</code></td>
                                <td class="text-center">
                                    <span class="badge text-bg-success" title="{{ $module['entry'] ?? '' }}">TelegramBotService</span>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer small text-secondary">
                {{ __('Telegram proxy modules footnote multi') }}
            </div>
        </div>
    </div>
@endsection

@section('js')
<script>
(function () {
    var $pre = $('#cabinet-tg-proxy-debug-log');
    $('#cabinet-tg-proxy-debug-copy').on('click', function () {
        var text = $pre.text();
        if (!text) return;
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text);
        } else {
            var ta = document.createElement('textarea');
            ta.value = text;
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
        }
    });
})();
</script>
@endsection
