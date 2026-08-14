<div class="modal fade" id="cabinet-sr-metrika-modal" tabindex="-1" aria-labelledby="cabinet-sr-metrika-modal-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="cabinet-sr-metrika-modal-title">{{ __('Yandex Metrika') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
            </div>
            <div class="modal-body">
                <p class="small text-secondary mb-2">
                    {{ __('Choose Metrika counter for domain') }}:
                    <strong data-metrika-domain-label>—</strong>
                </p>
                <div data-metrika-current class="alert alert-light border py-2 px-3 small d-none mb-3"></div>
                <div data-metrika-loading class="text-secondary small py-3 d-none">{{ __('Loading counters') }}…</div>
                <div data-metrika-error class="alert alert-danger py-2 px-3 small d-none"></div>
                <div data-metrika-auth class="text-center py-3 d-none">
                    <p class="mb-3">{{ __('Connect Yandex Metrika to pick a counter') }}</p>
                    <a href="#" class="btn btn-primary" data-metrika-auth-link>
                        <i class="bi bi-box-arrow-in-right me-1" aria-hidden="true"></i>
                        {{ __('Authorize Yandex Metrika') }}
                    </a>
                </div>
                <div data-metrika-search-wrap class="mb-2 d-none">
                    <input type="search"
                           class="form-control form-control-sm"
                           data-metrika-search
                           placeholder="{{ __('Search by site or counter ID') }}"
                           autocomplete="off">
                </div>
                <div class="list-group list-group-flush border rounded" data-metrika-list style="max-height: 22rem; overflow: auto;"></div>
            </div>
            <div class="modal-footer justify-content-between">
                <button type="button" class="btn btn-outline-danger btn-sm d-none" data-metrika-unbind>
                    {{ __('Unbind counter') }}
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Close') }}</button>
            </div>
        </div>
    </div>
</div>
