<div class="cabinet-mon-row-actions" role="group" aria-label="{{ __('Actions') }}">
    @can('edit_query_monitoring')
        <button type="button"
                class="btn btn-sm cabinet-mon-row-actions__btn cabinet-mon-keyword-edit"
                data-id="{{ $key->id }}"
                data-type="edit_singular"
                title="{{ __('Edit') }}">
            <i class="bi bi-pencil" aria-hidden="true"></i>
            <span class="visually-hidden">{{ __('Edit') }}</span>
        </button>
    @endcan

    @can('delete_query_monitoring')
        <button type="button"
                class="btn btn-sm cabinet-mon-row-actions__btn cabinet-mon-row-actions__btn--danger delete-keyword"
                data-id="{{ $key->id }}"
                title="{{ __('Delete') }}">
            <i class="bi bi-trash" aria-hidden="true"></i>
            <span class="visually-hidden">{{ __('Delete') }}</span>
        </button>
    @endcan
</div>
