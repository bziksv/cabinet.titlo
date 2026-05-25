@php
    $presetsPayload = $presetsPayload ?? ['presets' => [], 'max_user_presets' => 20, 'user_preset_count' => 0, 'can_save_preset' => true];
    $compact = !empty($compact);
@endphp

<div class="cabinet-he-presets {{ $compact ? 'cabinet-he-presets--compact mb-0' : 'mb-4' }}" data-he-presets
     data-he-presets-store-url="{{ route('html-editor.presets.store') }}"
     data-he-presets-destroy-url="{{ route('html-editor.presets.destroy', ['id' => '__ID__']) }}"
     data-he-presets-confirm="{{ __('Replace current content with this preset?') }}"
     data-he-presets-confirm-append="{{ __('Append this preset to the end?') }}"
     data-he-presets-saved="{{ __('Preset saved') }}"
     data-he-presets-deleted="{{ __('Preset deleted') }}"
     data-he-presets-delete-confirm="{{ __('Delete preset') }}"
     data-he-preset-name-required="{{ __('Enter preset name') }}">
    <script type="application/json" data-he-presets-json>@json($presetsPayload)</script>

    @if(!$compact)
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
        <p class="fw-semibold mb-0">{{ __('HTML presets') }}</p>
        @if($presetsPayload['can_save_preset'] ?? true)
            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#cabinet-he-save-preset-modal">
                <i class="bi bi-bookmark-plus me-1" aria-hidden="true"></i>{{ __('Save as preset') }}
            </button>
        @else
            <span class="small text-muted">{{ __('Preset limit reached') }} ({{ $presetsPayload['max_user_presets'] ?? 20 }})</span>
        @endif
    </div>
    @else
    <div class="d-flex flex-wrap justify-content-end gap-2 mb-2">
        @if($presetsPayload['can_save_preset'] ?? true)
            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#cabinet-he-save-preset-modal">
                <i class="bi bi-bookmark-plus me-1" aria-hidden="true"></i>{{ __('Save as preset') }}
            </button>
        @endif
    </div>
    @endif

    <p class="small text-secondary mb-3 {{ $compact ? 'mb-2' : '' }}">{{ __('Insert a ready-made block or your saved template. Hold Shift to append instead of replace.') }}</p>

    <div class="cabinet-he-preset-group mb-3">
        <p class="cabinet-he-preset-group-title mb-2">{{ __('Popular presets') }}</p>
        <div class="d-flex flex-wrap gap-2" data-he-preset-list="builtin"></div>
    </div>

    <div class="cabinet-he-preset-group" data-he-user-presets-wrap @if(empty($presetsPayload['user_preset_count'])) hidden @endif>
        <p class="cabinet-he-preset-group-title mb-2">{{ __('My presets') }}</p>
        <div class="d-flex flex-wrap gap-2" data-he-preset-list="user"></div>
    </div>
    <p class="small text-muted mb-0 mt-2" data-he-user-presets-empty @if(!empty($presetsPayload['user_preset_count'])) hidden @endif>{{ __('No saved presets yet. Save the current HTML as a preset.') }}</p>
</div>

<div class="modal fade" id="cabinet-he-save-preset-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ __('Save as preset') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Cancel') }}"></button>
            </div>
            <div class="modal-body">
                <label class="form-label" for="cabinet-he-preset-name">{{ __('Preset name') }}</label>
                <input type="text" class="form-control" id="cabinet-he-preset-name" maxlength="120" placeholder="{{ __('For example: Landing summer sale') }}">
                <p class="small text-danger mb-0 mt-2 d-none" data-he-preset-save-error role="alert"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-he-preset-save-submit>{{ __('Save preset') }}</button>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
            </div>
        </div>
    </div>
</div>
