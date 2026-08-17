<div class="modal fade" id="cabinet-sr-webmaster-modal" tabindex="-1" aria-labelledby="cabinet-sr-webmaster-modal-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="cabinet-sr-webmaster-modal-title">{{ __('Yandex Webmaster') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
            </div>
            <div class="modal-body">
                <p class="small text-secondary mb-2">
                    {{ __('Choose Webmaster host for domain') }}:
                    <strong data-webmaster-domain-label>—</strong>
                </p>
                <div data-webmaster-current class="alert alert-light border py-2 px-3 small d-none mb-3"></div>
                <div data-webmaster-loading class="text-secondary small py-3 d-none">{{ __('Loading Webmaster hosts') }}…</div>
                <div data-webmaster-error class="alert alert-danger py-2 px-3 small d-none"></div>
                <div data-webmaster-auth class="text-center py-3 d-none">
                    <p class="mb-3">{{ __('Connect Yandex Webmaster to pick a host') }}</p>
                    <a href="#" class="btn btn-primary" data-webmaster-auth-link>
                        <i class="bi bi-box-arrow-in-right me-1" aria-hidden="true"></i>
                        {{ __('Authorize Yandex Webmaster') }}
                    </a>
                </div>
                <div data-webmaster-search-wrap class="mb-2 d-none">
                    <input type="search"
                           class="form-control form-control-sm"
                           data-webmaster-search
                           placeholder="{{ __('Search by site or host ID') }}"
                           autocomplete="off">
                </div>
                <div class="list-group list-group-flush border rounded" data-webmaster-list style="max-height: 22rem; overflow: auto;"></div>
            </div>
            <div class="modal-footer justify-content-between">
                <button type="button" class="btn btn-outline-danger btn-sm d-none" data-webmaster-unbind>
                    {{ __('Unbind Webmaster host') }}
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Close') }}</button>
            </div>
        </div>
    </div>
</div>
