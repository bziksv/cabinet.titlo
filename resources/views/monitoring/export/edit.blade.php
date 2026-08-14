<form action="/monitoring/{{ $project->id }}/export" method="GET">
    <div class="modal-header">
        <h4 class="modal-title">{{ __('Export') }}</h4>
        <button type="button" class="close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>

    <div class="modal-body">
        @include('monitoring.export.form-fields')
    </div>

    <div class="modal-footer justify-content-between">
        <button type="button" class="btn btn-default" data-bs-dismiss="modal">{{ __('Close') }}</button>
        <button type="submit" class="btn btn-success save-modal">{{ __('Export') }}</button>
    </div>
</form>
