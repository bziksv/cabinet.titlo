<form action="{{ route('keywords.store') }}" method="POST" class="cabinet-mon-keyword-modal__form">

    <input type="hidden" name="monitoring_project_id" value="{{ $project->id }}">

    @include('monitoring.keywords.partials.modal-header', ['title' => __('Add keyword')])

    <div class="modal-body pt-3">

        @can('form_keyword_monitoring')
            <div class="mb-3">
                <label class="form-label" for="cabinet-mon-kw-queries">{{ __('Queries') }}</label>
                <textarea name="query" id="cabinet-mon-kw-queries" class="form-control" rows="10" placeholder="{{ __('Monitoring keyword queries placeholder') }}"></textarea>
                <div class="invalid-feedback query">
                    {{ __('Please add queries') }}
                </div>
            </div>

            <div class="input-group mb-3">
                <input type="file" class="form-control" id="upload" aria-label="{{ __('Upload CSV file') }}">
                <button type="button" class="btn btn-outline-secondary" id="upload-queries">
                    <i class="bi bi-upload me-1" aria-hidden="true"></i>{{ __('Upload') }}
                </button>
            </div>

            <p class="text-secondary small mb-0">
                {{ __('Monitoring keyword csv hint') }}
                <a href="/monitoring/create#id={{ $project->id }}">{{ __('Monitoring keyword project edit link') }}</a>
            </p>
        @endcan

        @can('form_relative_url_monitoring')
            <div class="mb-3">
                <label class="form-label" for="cabinet-mon-kw-page">{{ __('Relative URL') }}</label>
                <input type="text" class="form-control" id="cabinet-mon-kw-page" name="page" value="">
            </div>
        @endcan

        @can('form_target_monitoring')
            <div class="mb-3">
                <label class="form-label" for="cabinet-mon-kw-target">{{ __('Target') }}</label>
                {{ Form::select('target', [1 => 1, 3 => 3, 5 => 5, 10 => 10, 50 => 50, 100 => 100], 10, ['class' => 'form-select', 'id' => 'cabinet-mon-kw-target']) }}
            </div>
        @endcan

        @can('form_group_monitoring')
            <div class="mb-3">
                <label class="form-label" for="cabinet-mon-kw-group">{{ __('Group') }}</label>
                {{ Form::select('monitoring_group_id', $project->groups->pluck('name', 'id'), null, ['class' => 'form-select', 'id' => 'cabinet-mon-kw-group']) }}
                <div class="invalid-feedback monitoring_group_id">
                    {{ __('Please add a group') }}
                </div>
            </div>

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
