<div class="card shadow-sm cabinet-cluster-edit-v2__sidebar">
    <div class="card-header py-2 d-flex justify-content-between align-items-center">
        <h3 class="card-title h6 mb-0">Группы</h3>
        <span class="badge text-bg-secondary">{{ count($groups) }}</span>
    </div>
    <div class="card-body p-0">
        <div class="p-2 border-bottom bg-body-tertiary">
            <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" id="cl-edit-show-relevance">
                <label class="form-check-label small" for="cl-edit-show-relevance">{{ __('Show relevant') }}</label>
            </div>
            <div class="d-none cl-edit-merge-bar" id="cl-edit-merge-bar">
                <select class="form-select form-select-sm" id="cl-edit-merge-target" aria-label="Куда объединить">
                    <option value="">Объединить в…</option>
                    @foreach($groups as $group)
                        <option value="{{ $group['name'] }}">{{ $group['name'] }}</option>
                    @endforeach
                </select>
                <button type="button" class="btn btn-primary btn-sm w-100 mt-1" id="cl-edit-merge-run" disabled>
                    Объединить выбранные
                </button>
            </div>
        </div>
        <div class="list-group list-group-flush cabinet-cluster-edit-v2__sidebar-list" id="cl-edit-sidebar-groups">
            @if(count($singles))
                <a href="#cl-edit-singles-block"
                   class="list-group-item list-group-item-action py-2 cl-edit-sidebar-link cl-edit-sidebar-drop-target"
                   data-drop-group="__single__">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <span class="fw-semibold small">{{ __('Unallocated words') }}</span>
                        <span class="badge text-bg-warning">{{ count($singles) }}</span>
                    </div>
                </a>
            @endif
            @foreach($groups as $group)
                @php($slug = 'cl-edit-group-' . md5($group['name']))
                <label class="list-group-item list-group-item-action py-2 mb-0 cl-edit-sidebar-item cl-edit-sidebar-drop-target"
                       data-group="{{ $group['name'] }}"
                       data-drop-group="{{ $group['name'] }}">
                    <div class="d-flex gap-2 align-items-start">
                        <input type="checkbox" class="form-check-input mt-1 cl-edit-merge-check flex-shrink-0" value="{{ $group['name'] }}" aria-label="Выбрать группу">
                        <div class="flex-grow-1 min-w-0">
                            <a href="#{{ $slug }}" class="text-decoration-none text-body cl-edit-sidebar-link d-block">
                                <div class="small fw-semibold text-truncate" title="{{ $group['name'] }}">{{ $group['name'] }}</div>
                                <div class="text-muted" style="font-size:0.75rem">
                                    {{ count($group['phrases']) }} фр. ·
                                    {{ $group['totals']['based'] }}/{{ $group['totals']['phrased'] }}/{{ $group['totals']['target'] }}
                                </div>
                            </a>
                            @if(!empty($group['relevance']['url']))
                                <div class="cl-edit-sidebar-rel small text-truncate mt-1 d-none" title="{{ $group['relevance']['url'] }}">
                                    @if($group['relevance']['uniform'])
                                        <i class="fa fa-link text-success me-1" aria-hidden="true"></i>
                                    @else
                                        <i class="fa fa-link text-warning me-1" aria-hidden="true"></i>
                                    @endif
                                    <a href="{{ $group['relevance']['url'] }}" target="_blank" rel="noopener" class="text-muted">{{ $group['relevance']['url'] }}</a>
                                </div>
                            @endif
                        </div>
                    </div>
                </label>
            @endforeach
        </div>
    </div>
</div>
