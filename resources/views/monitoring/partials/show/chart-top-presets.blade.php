<div class="cabinet-mon-top-presets" role="group" aria-label="{{ __('Monitoring child chart series group') }}">
    <span class="cabinet-mon-top-presets__label">{{ __('Monitoring child chart series group') }}</span>
    <div class="cabinet-mon-top-presets__buttons">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-mon-top-preset="1">TOP-1</button>
        <button type="button" class="btn btn-outline-secondary btn-sm" data-mon-top-preset="3">TOP-3</button>
        <button type="button" class="btn btn-outline-secondary btn-sm active" data-mon-top-preset="10">TOP-10</button>
        @empty($regionsMode)
            <button type="button" class="btn btn-outline-secondary btn-sm" data-mon-top-preset="35102030100">3/5/10/20/30/100</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" data-mon-top-preset="all">{{ __('Monitoring child chart preset all') }}</button>
        @endempty
    </div>
</div>
