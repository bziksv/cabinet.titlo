<div class="modal fade cabinet-di-stats-modal" id="cabinetDiStatsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <div>
                    <h5 class="modal-title mb-1" id="cabinetDiStatsModalTitle">{{ __('Domain information stats log title') }}</h5>
                    <p class="text-secondary small mb-0" id="cabinetDiStatsModalSubtitle"></p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
            </div>
            <div class="modal-body pt-3" id="cabinetDiStatsModalBody">
                <div class="text-center py-5 text-secondary">
                    <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                    <p class="mt-2 mb-0 small">{{ __('Loading') }}…</p>
                </div>
            </div>
            <div class="modal-footer border-top cabinet-di-stats-modal__footer">
                <div class="cabinet-di-stats-actions w-100">
                    <div class="mb-2">
                        <button type="button"
                                class="btn btn-outline-danger btn-sm"
                                id="cabinetDiStatsPdfBtn">
                            <i class="bi bi-file-earmark-pdf me-1" aria-hidden="true"></i>{{ __('Download PDF report') }}
                        </button>
                    </div>
                    <div class="cabinet-di-stats-share rounded border bg-white p-2"
                         id="cabinetDiStatsShare"
                         data-feature-available="{{ \App\DomainInformationPublicShare::tableAvailable() ? '1' : '0' }}"
                         data-create-url="{{ route('domain.information.public.share.create') }}"
                         data-revoke-url="{{ route('domain.information.public.share.revoke') }}"
                         data-pdf-url="{{ route('domain.information.export.pdf') }}">
                        <div class="alert alert-warning py-2 px-2 small mb-2 d-none" id="cabinetDiStatsShareUnavailable" role="alert">
                            {{ __('Domain information public share unavailable') }}
                        </div>
                        <div class="small fw-semibold mb-2">
                            <i class="bi bi-share me-1" aria-hidden="true"></i>{{ __('Public link without registration') }}
                        </div>
                        <div class="input-group input-group-sm mb-2">
                            <input type="text"
                                   class="form-control font-monospace"
                                   id="cabinetDiStatsShareUrl"
                                   readonly
                                   placeholder="{{ __('Create a public link to copy it here') }}">
                            <button type="button" class="btn btn-primary" id="cabinetDiStatsShareCopy" disabled>
                                <i class="bi bi-clipboard" aria-hidden="true"></i>
                            </button>
                        </div>
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            @php($shareTtlOptions = \App\Support\DomainInformationPublicShareTtl::labelsForUi())
                            <label class="visually-hidden" for="cabinetDiStatsShareTtl">{{ __('Domain information share ttl label') }}</label>
                            <select class="form-select form-select-sm cabinet-di-stats-share__ttl"
                                    id="cabinetDiStatsShareTtl"
                                    aria-label="{{ __('Domain information share ttl label') }}">
                                @foreach($shareTtlOptions as $days => $label)
                                    <option value="{{ $days }}" @if((int) $days === 30) selected @endif>{{ $label }}</option>
                                @endforeach
                            </select>
                            <button type="button" class="btn btn-primary btn-sm" id="cabinetDiStatsShareCreate">
                                <i class="bi bi-link-45deg me-1" aria-hidden="true"></i>{{ __('Create public link') }}
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="cabinetDiStatsShareRevoke" disabled>
                                {{ __('Revoke public link') }}
                            </button>
                            <span class="badge rounded-pill text-bg-secondary d-none" id="cabinetDiStatsShareExpires"></span>
                        </div>
                        <p class="small text-secondary mb-0 mt-2">{{ __('Domain information public share hint ttl') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
