<div class="modal fade" id="cabinet-sr-gsc-modal" tabindex="-1" aria-labelledby="cabinet-sr-gsc-modal-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="cabinet-sr-gsc-modal-title">{{ __('Google Search Console') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
            </div>
            <div class="modal-body">
                <p class="small text-secondary mb-2">
                    {{ __('Choose GSC property for domain') }}:
                    <strong data-gsc-domain-label>—</strong>
                </p>
                <div data-gsc-current class="alert alert-light border py-2 px-3 small d-none mb-3"></div>
                <div data-gsc-loading class="text-secondary small py-3 d-none">{{ __('Loading GSC properties') }}…</div>
                <div data-gsc-error class="alert alert-danger py-2 px-3 small d-none"></div>
                <div data-gsc-auth class="text-center py-3 d-none">
                    <p class="mb-3">{{ __('Connect Google Search Console to pick a property') }}</p>
                    <a href="#" class="btn btn-primary" data-gsc-auth-link>
                        <i class="bi bi-box-arrow-in-right me-1" aria-hidden="true"></i>
                        {{ __('Authorize Google Search Console') }}
                    </a>
                </div>
                <div data-gsc-search-wrap class="mb-2 d-none">
                    <input type="search"
                           class="form-control form-control-sm"
                           data-gsc-search
                           placeholder="{{ __('Search by site or property ID') }}"
                           autocomplete="off">
                </div>
                <div class="list-group list-group-flush border rounded" data-gsc-list style="max-height: 22rem; overflow: auto;"></div>
            </div>
            <div class="modal-footer justify-content-between">
                <button type="button" class="btn btn-outline-danger btn-sm d-none" data-gsc-unbind>
                    {{ __('Unbind GSC property') }}
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Close') }}</button>
            </div>
        </div>
    </div>
</div>
