<div class="modal fade" id="cabinetUsersInactivePurgeModal" tabindex="-1" aria-labelledby="cabinetUsersInactivePurgeModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header border-danger-subtle">
                <h5 class="modal-title text-danger" id="cabinetUsersInactivePurgeModalTitle">
                    <i class="bi bi-exclamation-triangle me-1"></i>{{ __('Users inactive purge modal title') }}
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
            </div>
            <div class="modal-body">
                <div class="d-none text-center py-4" id="cabinet-users-purge-loading">
                    <div class="spinner-border text-danger" role="status"></div>
                    <div class="small text-secondary mt-2">{{ __('Users inactive purge loading') }}</div>
                </div>
                <div id="cabinet-users-purge-body" class="d-none">
                    <p class="mb-2" id="cabinet-users-purge-summary"></p>
                    <div class="alert alert-warning py-2 small mb-3" id="cabinet-users-purge-storage"></div>
                    <div class="small text-secondary mb-1">{{ __('Users inactive purge modules') }}</div>
                    <ul class="small mb-3" id="cabinet-users-purge-modules"></ul>
                    <div class="small text-secondary mb-1">{{ __('Users inactive purge samples') }}</div>
                    <pre class="small bg-body-tertiary border rounded p-2 mb-3" id="cabinet-users-purge-sample" style="max-height: 8rem; overflow:auto;"></pre>
                    <label class="form-label small" for="cabinet-users-purge-confirm-input">
                        {{ __('Users inactive purge type code') }}
                        <code id="cabinet-users-purge-code"></code>
                    </label>
                    <input type="text"
                           class="form-control"
                           id="cabinet-users-purge-confirm-input"
                           autocomplete="off"
                           spellcheck="false">
                    <div class="form-text text-danger">{{ __('Users inactive purge irreversible') }}</div>
                </div>
                <div class="d-none alert alert-danger mb-0" id="cabinet-users-purge-error"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                <button type="button" class="btn btn-danger" id="cabinet-users-purge-confirm-btn" disabled>
                    <i class="bi bi-trash me-1"></i>{{ __('Users inactive purge confirm btn') }}
                </button>
            </div>
        </div>
    </div>
</div>
