<div class="card mb-3" id="supervisor-log-panel">
    <div class="card-header py-2 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <strong>{{ __('Supervisor log tail') }}: {{ $logTail['program'] ?? $logProgram }}</strong>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.supervisor.index', ['log' => $logProgram]) }}" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i>{{ __('Refresh') }}
            </a>
            <a href="{{ route('admin.supervisor.index') }}" class="btn btn-sm btn-outline-secondary">{{ __('Close') }}</a>
        </div>
    </div>
    <div class="card-body p-0">
        @if($logTail['exists'] ?? false)
            @if($logTail['empty'] ?? true)
                <p class="text-secondary small p-3 mb-0">
                    {{ __('Supervisor log empty') }}
                    @if(($logTail['size_bytes'] ?? 0) === 0)
                        {{ __('Supervisor log empty hint') }}
                    @endif
                </p>
            @else
                <pre class="cabinet-supervisor-admin-log mb-0">{{ $logTail['tail'] }}</pre>
            @endif
            <p class="small text-secondary px-3 pb-2 mb-0">
                {{ $logTail['path'] }}
                @if(($logTail['size_bytes'] ?? 0) > 0)
                    · {{ number_format($logTail['size_bytes'] / 1024, 1, '.', ' ') }} KB
                @endif
            </p>
        @else
            <p class="text-secondary small p-3 mb-0">{{ __('Supervisor log missing') }}</p>
        @endif
    </div>
</div>
