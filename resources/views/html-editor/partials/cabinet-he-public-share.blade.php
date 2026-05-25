@php $compact = !empty($compact); @endphp
<section class="cabinet-he-share {{ $compact ? 'cabinet-he-share--compact mb-0' : 'mb-4' }}"
         id="cabinet-he-public-share"
         data-description-id="{{ $descriptionId }}"
         data-create-url="{{ route('html.editor.public.share.create') }}"
         data-revoke-url="{{ route('html.editor.public.share.revoke') }}"
         data-revoke-confirm="{{ __('Revoke public link') }}?"
         data-copied-label="{{ __('Copied') }}"
         data-valid-until-label="{{ __('Valid until') }}"
         data-create-label="{{ __('Create public link') }}"
         data-refresh-label="{{ __('Refresh public link') }}">
    @if(!$compact)
    <header class="cabinet-he-share-head d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h2 class="h6 fw-semibold mb-1">
                <i class="bi bi-share me-1 text-primary" aria-hidden="true"></i>{{ __('Public link without registration') }}
            </h2>
            <p class="small text-secondary mb-0">{{ __('HTML editor public share hint') }}</p>
        </div>
        @if(!empty($publicShare))
            <span class="badge rounded-pill text-bg-success" id="cabinet-he-public-share-expires">
                {{ __('Valid until') }} {{ $publicShare->expires_at->format('d.m.Y H:i') }}
            </span>
        @else
            <span class="badge rounded-pill text-bg-secondary d-none" id="cabinet-he-public-share-expires"></span>
        @endif
    </header>
    @else
        <p class="small text-secondary mb-2">{{ __('HTML editor public share hint') }}</p>
        @if(!empty($publicShare))
            <span class="badge rounded-pill text-bg-success mb-2" id="cabinet-he-public-share-expires">
                {{ __('Valid until') }} {{ $publicShare->expires_at->format('d.m.Y H:i') }}
            </span>
        @else
            <span class="badge rounded-pill text-bg-secondary d-none mb-2" id="cabinet-he-public-share-expires"></span>
        @endif
    @endif

    <div class="input-group input-group-sm mb-2">
        <span class="input-group-text"><i class="bi bi-link-45deg" aria-hidden="true"></i></span>
        <input type="text"
               class="form-control font-monospace"
               id="cabinet-he-public-share-url"
               readonly
               placeholder="{{ __('Create a public link to copy it here') }}"
               value="{{ isset($publicShare) ? $publicShare->publicUrl() : '' }}">
        <button type="button"
                class="btn btn-outline-secondary"
                id="cabinet-he-public-share-copy"
                @if(empty($publicShare)) disabled @endif>
            <i class="bi bi-clipboard me-1" aria-hidden="true"></i>{{ __('Copy') }}
        </button>
    </div>

    <div class="d-flex flex-wrap gap-2">
        <button type="button" class="btn btn-primary btn-sm" id="cabinet-he-public-share-create">
            <i class="bi bi-link-45deg me-1" aria-hidden="true"></i>
            {{ isset($publicShare) ? __('Refresh public link') : __('Create public link') }}
        </button>
        <button type="button"
                class="btn btn-outline-danger btn-sm"
                id="cabinet-he-public-share-revoke"
                @if(empty($publicShare)) disabled @endif>
            <i class="bi bi-x-circle me-1" aria-hidden="true"></i>{{ __('Revoke public link') }}
        </button>
        @if(!empty($publicShare))
            <a href="{{ $publicShare->publicUrl() }}" class="btn btn-outline-secondary btn-sm" target="_blank" rel="noopener">
                <i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i>{{ __('Open public page') }}
            </a>
        @endif
    </div>
</section>
