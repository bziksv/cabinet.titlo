@include('monitoring.keywords.partials.modal-header', ['title' => __('No data')])

<div class="modal-body pt-3">
    <div class="alert alert-warning mb-0" role="alert">
        <h6 class="alert-heading mb-1 cabinet-mon-keyword-modal__alert-title"></h6>
        <p class="mb-0 small cabinet-mon-keyword-modal__alert-text"></p>
    </div>
</div>

<div class="modal-footer border-top">
    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Close') }}</button>
</div>
