<div id="cabinet-tg-proxy-admin-debug" class="cabinet-tg-proxy-admin-debug card card-outline card-secondary shadow-sm mb-4">
    <div class="card-header py-2 d-flex flex-wrap align-items-center justify-content-between gap-2">
        <h3 class="card-title h6 mb-0">
            <i class="bi bi-bug me-1 text-secondary" aria-hidden="true"></i>{{ __('Telegram proxy debug log') }}
        </h3>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-xs btn-outline-secondary btn-sm" id="cabinet-tg-proxy-debug-copy">
                <i class="bi bi-clipboard me-1" aria-hidden="true"></i>{{ __('Copy') }}
            </button>
            <form action="{{ route('admin.telegram-proxy.clear-debug') }}" method="post" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-xs btn-outline-secondary btn-sm">
                    {{ __('Clear view') }}
                </button>
            </form>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="cabinet-tg-proxy-admin-debug-meta small text-secondary px-3 py-2 border-bottom">
            {{ __('Telegram proxy debug log hint') }}
        </div>
        <pre id="cabinet-tg-proxy-debug-log"
             class="cabinet-tg-proxy-admin-debug-log mb-0"
             aria-live="polite">@if(!empty($debugLogText)){{ $debugLogText }}@else{{ __('Telegram proxy debug log empty') }}@endif</pre>
    </div>
</div>
