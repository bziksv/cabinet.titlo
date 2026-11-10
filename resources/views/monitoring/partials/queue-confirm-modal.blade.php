<div class="modal fade cabinet-mon-queue-confirm-modal" id="cabinetMonQueueConfirmModal" tabindex="-1"
     aria-labelledby="cabinetMonQueueConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title" id="cabinetMonQueueConfirmModalLabel"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
            </div>
            <div class="modal-body pt-2">
                <p class="text-secondary small mb-2" data-mon-queue-limits-hint></p>
                <p class="small mb-3" data-mon-queue-async>{{ __('Monitoring occurrence queue async hint') }}</p>

                <div class="alert alert-warning py-2 px-3 small mb-3" data-mon-queue-duration-wrap>
                    <i class="bi bi-clock-history me-1" aria-hidden="true"></i>
                    <span data-mon-queue-duration></span>
                </div>

                <div class="d-none mb-3" data-mon-queue-regions-wrap>
                    <label class="form-label small fw-semibold mb-2">{{ __('Monitoring position pick regions') }}</label>
                    <div class="cabinet-mon-queue-confirm-modal__regions" data-mon-queue-regions-list></div>
                </div>

                <div class="d-none mb-3" data-mon-queue-region-wrap>
                    <label class="form-label small fw-semibold mb-1" for="monQueueRegion">{{ __('Search engine') }}</label>
                    <select class="form-select form-select-sm" data-mon-queue-region id="monQueueRegion"></select>
                </div>

                <div class="d-none mb-3" data-mon-queue-google-depth-wrap>
                    <label class="form-label small fw-semibold mb-1" for="monQueueGoogleDepth">{{ __('Monitoring google depth label') }}</label>
                    <select class="form-select form-select-sm" data-mon-queue-google-depth id="monQueueGoogleDepth">
                        @foreach(\App\Classes\Monitoring\MonitoringGoogleDepth::options() as $depth)
                            <option value="{{ $depth }}">{{ __('Monitoring google depth top', ['n' => $depth]) }}</option>
                        @endforeach
                    </select>
                    <p class="text-secondary small mb-0 mt-1" data-mon-queue-google-depth-hint>{{ __('Monitoring google depth hint scan') }}</p>
                </div>

                <div class="mb-3" data-mon-queue-when-wrap>
                    <label class="form-label small fw-semibold mb-2">{{ __('Monitoring deferred start label') }}</label>
                    <div class="cabinet-mon-queue-confirm-modal__option">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="queue_confirm_when" id="queueConfirmWhenNow" value="now" checked>
                            <label class="form-check-label" for="queueConfirmWhenNow">{{ __('Monitoring deferred start now') }}</label>
                        </div>
                    </div>
                    <div class="cabinet-mon-queue-confirm-modal__option">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="queue_confirm_when" id="queueConfirmWhenToday" value="today">
                            <label class="form-check-label" for="queueConfirmWhenToday">{{ __('Monitoring deferred start today') }}</label>
                        </div>
                    </div>
                    <div class="cabinet-mon-queue-confirm-modal__option">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="queue_confirm_when" id="queueConfirmWhenTomorrow" value="tomorrow">
                            <label class="form-check-label" for="queueConfirmWhenTomorrow">{{ __('Monitoring deferred start tomorrow') }}</label>
                        </div>
                    </div>
                    <div class="mt-2 d-none" data-mon-queue-when-time-wrap>
                        <label class="form-label small fw-semibold mb-1" for="monQueueStartTime">{{ __('Monitoring deferred start time label') }}</label>
                        <input type="text" class="form-control form-control-sm" id="monQueueStartTime" data-mon-queue-start-time
                               inputmode="numeric" placeholder="01:00" autocomplete="off" maxlength="5">
                        <div class="invalid-feedback d-block d-none" data-mon-queue-when-error></div>
                    </div>
                </div>

                <div data-mon-queue-mode="all">
                    <div class="cabinet-mon-queue-confirm-modal__option">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="queue_confirm_scope" id="queueConfirmScopeAll" value="all" checked>
                            <label class="form-check-label" for="queueConfirmScopeAll">
                                <span class="cabinet-mon-queue-confirm-modal__option-title">{{ __('Monitoring queue scope all') }}</span>
                                <span class="cabinet-mon-queue-confirm-modal__option-meta" data-mon-queue-all-meta></span>
                            </label>
                        </div>
                    </div>
                    <div class="cabinet-mon-queue-confirm-modal__option">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="queue_confirm_scope" id="queueConfirmScopeMissing" value="missing">
                            <label class="form-check-label" for="queueConfirmScopeMissing">
                                <span class="cabinet-mon-queue-confirm-modal__option-title">{{ __('Monitoring queue scope missing') }}</span>
                                <span class="cabinet-mon-queue-confirm-modal__option-meta" data-mon-queue-missing-meta></span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="d-none" data-mon-queue-mode="selected">
                    <p class="mb-2" data-mon-queue-selected-summary></p>
                    <p class="mb-0 fw-semibold">
                        {{ __('Monitoring parse limits confirm') }}
                        <span data-mon-queue-selected-limits></span>
                    </p>
                </div>
            </div>
            <div class="modal-footer border-top d-flex flex-wrap justify-content-end gap-2">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                <button type="button" class="btn btn-primary" data-mon-queue-confirm>{{ __('Monitoring queue confirm button') }}</button>
            </div>
        </div>
    </div>
</div>
