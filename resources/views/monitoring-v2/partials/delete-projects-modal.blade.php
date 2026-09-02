{{-- Подтверждение удаления проектов мониторинга v2 — со списком сайтов --}}
<div class="modal fade cabinet-mon-v2-delete-modal" id="cabinetMonV2DeleteProjectsModal" tabindex="-1" aria-labelledby="cabinetMonV2DeleteProjectsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title" id="cabinetMonV2DeleteProjectsModalLabel">{{ __('Monitoring v2 delete projects title') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
            </div>
            <div class="modal-body pt-3">
                <p class="mb-2 text-secondary" id="cabinetMonV2DeleteProjectsLead">{{ __('Monitoring v2 delete projects lead') }}</p>
                <p class="small fw-semibold mb-2" id="cabinetMonV2DeleteProjectsListLabel">{{ __('Monitoring v2 delete projects list label') }}</p>
                <ul class="list-group list-group-flush cabinet-mon-v2-delete-modal__list" id="cabinetMonV2DeleteProjectsList"></ul>
            </div>
            <div class="modal-footer border-top d-flex flex-wrap justify-content-end gap-2">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                <button type="button" class="btn btn-danger" id="cabinetMonV2DeleteProjectsConfirm">
                    <i class="bi bi-trash me-1" aria-hidden="true"></i>{{ __('Delete permanently') }}
                </button>
            </div>
        </div>
    </div>
</div>
