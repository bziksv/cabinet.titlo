<form action="{{ route('keywords.update', $keyword->id) }}" method="PATCH" class="cabinet-mon-keyword-modal__form">

    @include('monitoring.keywords.partials.modal-header', ['title' => __('Edit keyword')])

    <div class="modal-body pt-3">
        @can('form_keyword_monitoring')
            <div class="mb-3">
                <label class="form-label" for="cabinet-mon-kw-query">{{ __('Query') }}</label>
                <input type="text" class="form-control" id="cabinet-mon-kw-query" name="query" value="{{ $keyword->query }}">
                <div class="invalid-feedback query">
                    {{ __('Please add queries') }}
                </div>
            </div>
        @endcan

        @can('form_relative_url_monitoring')
            <div class="mb-3">
                <label class="form-label" for="cabinet-mon-kw-page">{{ __('Relative URL') }}</label>
                <input type="text" class="form-control" id="cabinet-mon-kw-page" name="page" value="{{ $keyword->page }}">
            </div>
        @endcan

        @can('form_target_monitoring')
            <div class="mb-3">
                <label class="form-label" for="cabinet-mon-kw-target">{{ __('Target') }}</label>
                {{ Form::select('target', [1 => 1, 3 => 3, 5 => 5, 10 => 10, 50 => 50, 100 => 100], $keyword->target, ['class' => 'form-select', 'id' => 'cabinet-mon-kw-target']) }}
            </div>
        @endcan

        <div class="mb-3">
            <label class="form-label" for="cabinet-mon-kw-group">{{ __('Group') }}</label>
            {{ Form::select('monitoring_group_id', $keyword->project->groups->pluck('name', 'id'), $keyword->monitoring_group_id, ['class' => 'form-select', 'id' => 'cabinet-mon-kw-group']) }}
        </div>

        @can('form_group_monitoring')
            <div class="mb-0">
                <label class="form-label" for="cabinet-mon-kw-new-group">{{ __('Name of group') }}</label>
                <div class="input-group">
                    <input type="text"
                           id="cabinet-mon-kw-new-group"
                           data-id="{{ $keyword->project->id }}"
                           placeholder="{{ __('Name of group') }}"
                           class="form-control">
                    <button type="button" class="btn btn-outline-secondary" id="create-group">
                        <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>{{ __('Create a new group') }}
                    </button>
                </div>
            </div>
        @endcan
    </div>

    <div class="modal-footer border-top d-flex flex-wrap align-items-center gap-2">
        @can('delete_query_monitoring')
            <button type="button"
                    class="btn btn-outline-danger cabinet-mon-keyword-delete"
                    data-id="{{ $keyword->id }}">
                <i class="bi bi-trash me-1" aria-hidden="true"></i>{{ __('Delete') }}
            </button>
        @endcan
        <div class="ms-auto d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Close') }}</button>
            <button type="button" class="btn btn-primary save-modal">{{ __('Save') }}</button>
        </div>
    </div>

</form>
