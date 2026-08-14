@component('component.card', ['title' => __('Result through analyse')])
    <div class="card mb-0">
        @include('relevance-analysis.partials.module-top-nav', [
            'active' => 'though',
            'admin' => $admin ?? false,
            'project' => $project ?? null,
        ])
        <div class="card-body">
            <div class="cabinet-sc-empty py-5 text-center">
                @if(!empty($error))
                    <p class="fw-semibold mb-2">{{ __('Could not open through analysis') }}</p>
                    <p class="text-secondary mb-3">{{ __('Try again later or restart the through analysis') }}</p>
                @else
                    <img src="{{ asset('/img/1485.gif') }}" alt="" width="40" height="40" class="mb-3">
                    <p class="fw-semibold mb-2">{{ __('Analysis in progress') }}</p>
                    <p class="text-secondary mb-3">
                        {{ __('Through analysis is still running. The page will open when the result is ready.') }}
                    </p>
                    <p class="small text-muted mb-3">
                        #{{ $though->id }}
                        · stage {{ (int) $though->stage }}
                        · {{ optional($though->updated_at)->format('d.m.Y H:i') }}
                    </p>
                    <button type="button" class="btn btn-primary btn-sm" onclick="window.location.reload()">
                        {{ __('Refresh') }}
                    </button>
                @endif
            </div>
        </div>
    </div>
@endcomponent
