<form action="{{ route('keywords.update.plural') }}" method="POST" class="cabinet-mon-keyword-modal__form">

    @include('monitoring.keywords.partials.modal-header', ['title' => __('Edit keywords')])

    <div class="modal-body pt-3">
        @can('form_relative_url_monitoring')
            <div class="mb-3">
                <label class="form-label" for="cabinet-mon-kw-page">{{ __('Relative URL') }}</label>
                <input type="text" class="form-control" id="cabinet-mon-kw-page" name="page">
            </div>
        @endcan

        @can('form_target_monitoring')
            <div class="mb-3">
                <label class="form-label" for="cabinet-mon-kw-target">{{ __('Target') }}</label>
                {{ Form::select('target', [1 => 1, 3 => 3, 5 => 5, 10 => 10, 50 => 50, 100 => 100], null, ['class' => 'form-select', 'id' => 'cabinet-mon-kw-target', 'placeholder' => __('Select') . '…']) }}
            </div>
        @endcan

        <div class="mb-3">
            <label class="form-label" for="cabinet-mon-kw-group">{{ __('Group') }}</label>
            {{ Form::select('monitoring_group_id', $project->groups->pluck('name', 'id'), null, ['class' => 'form-select', 'id' => 'cabinet-mon-kw-group', 'placeholder' => __('Select') . '…']) }}
        </div>

        @can('form_group_monitoring')
            <div class="mb-0">
                <label class="form-label" for="cabinet-mon-kw-new-group">{{ __('Name of group') }}</label>
                <div class="input-group">
                    <input type="text"
                           id="cabinet-mon-kw-new-group"
                           data-id="{{ $project->id }}"
                           placeholder="{{ __('Name of group') }}"
                           class="form-control">
                    <button type="button" class="btn btn-outline-secondary" id="create-group">
                        <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>{{ __('Create a new group') }}
                    </button>
                </div>
            </div>
        @endcan
    </div>

    <div class="modal-footer border-top d-flex flex-wrap justify-content-end gap-2">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Close') }}</button>
        <button type="button" class="btn btn-primary save-modal">{{ __('Save') }}</button>
    </div>

</form>
