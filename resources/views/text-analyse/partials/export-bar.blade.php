<div class="cabinet-ta-actions-panel mb-3" id="cabinet-ta-export-bar">
    <div class="cabinet-ta-actions-panel__grid">
        <section class="cabinet-ta-action-card cabinet-ta-action-card--export" aria-label="{{ __('Export and actions') }}">
            <header class="cabinet-ta-action-card__head">
                <span class="cabinet-ta-action-card__icon cabinet-ta-action-card__icon--export" aria-hidden="true">
                    <i class="bi bi-box-arrow-down"></i>
                </span>
                <div class="cabinet-ta-action-card__titles">
                    <h6 class="cabinet-ta-action-card__title">{{ __('Export and actions') }}</h6>
                    <p class="cabinet-ta-action-card__subtitle">{{ __('Download Excel') }} · {{ __('Download PDF report') }}</p>
                </div>
            </header>
            <div class="cabinet-ta-action-card__actions">
                <form method="post" action="{{ route('text.analyzer.export.excel') }}" class="cabinet-ta-action-card__form">
                    @csrf
                    <button type="submit" class="btn cabinet-ta-btn-export cabinet-ta-btn-export--excel">
                        <span class="cabinet-ta-btn-export__icon"><i class="bi bi-file-earmark-spreadsheet"></i></span>
                        <span class="cabinet-ta-btn-export__label">{{ __('Download Excel') }}</span>
                    </button>
                </form>
                <form method="post" action="{{ route('text.analyzer.export.pdf') }}" class="cabinet-ta-action-card__form">
                    @csrf
                    <button type="submit" class="btn cabinet-ta-btn-export cabinet-ta-btn-export--pdf">
                        <span class="cabinet-ta-btn-export__icon"><i class="bi bi-file-earmark-pdf"></i></span>
                        <span class="cabinet-ta-btn-export__label">{{ __('Download PDF report') }}</span>
                    </button>
                </form>
            </div>
        </section>

        <section class="cabinet-ta-action-card cabinet-ta-action-card--share cabinet-ta-public-share flex-grow-1"
                 id="cabinet-ta-public-share"
                 data-create-url="{{ route('text.analyzer.public.share.create') }}"
                 data-revoke-url="{{ route('text.analyzer.public.share.revoke') }}">
            <script>
                window.cabinetTaShareLabels = {
                    refresh: @json(__('Refresh public link')),
                    validUntil: @json(__('Valid until')),
                    revokeConfirm: @json(__('Revoke public link') . '?'),
                    copied: @json(__('Copied'))
                };
            </script>
            <header class="cabinet-ta-action-card__head">
                <span class="cabinet-ta-action-card__icon cabinet-ta-action-card__icon--share" aria-hidden="true">
                    <i class="bi bi-share"></i>
                </span>
                <div class="cabinet-ta-action-card__titles">
                    <h6 class="cabinet-ta-action-card__title">{{ __('Public link without registration') }}</h6>
                    <p class="cabinet-ta-action-card__subtitle">{{ __('Create a public link to copy it here') }}</p>
                    <p class="cabinet-ta-action-card__hint">{{ __('Text analyzer public link persist hint') }}</p>
                </div>
                @if(!empty($publicShare))
                    <span class="badge rounded-pill text-bg-success cabinet-ta-share-badge" id="cabinet-ta-public-share-expires">
                        {{ __('Valid until') }} {{ $publicShare->expires_at->format('d.m.Y H:i') }}
                    </span>
                @else
                    <span class="badge rounded-pill text-bg-secondary cabinet-ta-share-badge cabinet-ta-share-badge--empty d-none" id="cabinet-ta-public-share-expires"></span>
                @endif
            </header>

            <div class="cabinet-ta-share-url-row input-group">
                <span class="input-group-text cabinet-ta-share-url-prefix"><i class="bi bi-link-45deg"></i></span>
                <input type="text"
                       class="form-control cabinet-ta-share-url-input font-monospace"
                       id="cabinet-ta-public-share-url"
                       readonly
                       placeholder="{{ __('Create a public link to copy it here') }}"
                       value="{{ isset($publicShare) ? $publicShare->publicUrl() : '' }}">
                <button type="button"
                        class="btn btn-primary cabinet-ta-share-copy"
                        id="cabinet-ta-public-share-copy"
                        @if(empty($publicShare)) disabled @endif
                        title="{{ __('Copy') }}">
                    <i class="bi bi-clipboard"></i>
                    <span class="d-none d-md-inline ms-1">{{ __('Copy') }}</span>
                </button>
            </div>

            <div class="cabinet-ta-share-toolbar">
                <button type="button"
                        class="btn btn-primary btn-sm cabinet-ta-share-btn-create"
                        id="cabinet-ta-public-share-create">
                    <i class="bi bi-link-45deg me-1"></i>
                    {{ isset($publicShare) ? __('Refresh public link') : __('Create public link') }}
                </button>
                <button type="button"
                        class="btn btn-sm cabinet-ta-share-btn-revoke"
                        id="cabinet-ta-public-share-revoke"
                        @if(empty($publicShare)) disabled @endif>
                    <i class="bi bi-x-circle me-1"></i>{{ __('Revoke public link') }}
                </button>
            </div>
        </section>
    </div>
</div>
