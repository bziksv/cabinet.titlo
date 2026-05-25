<tr class="cabinet-cluster-edit-v2__row" data-phrase="{{ $row['phrase'] }}" data-from="{{ $fromGroup }}">
    <td class="cl-edit-drag-handle text-muted text-center" title="Перетащить" aria-label="Перетащить">
        <i class="fa fa-bars" aria-hidden="true"></i>
    </td>
    <td class="cl-edit-phrase">{{ $row['phrase'] }}</td>
    <td class="text-end text-muted small text-nowrap">
        {{ $row['based'] }} / {{ $row['phrased'] }} / {{ $row['target'] }}
    </td>
    <td class="small">
        @if(!empty($row['url']))
            <a href="{{ $row['url'] }}" target="_blank" rel="noopener" class="text-truncate d-inline-block cl-edit-url" style="max-width:200px">{{ $row['url'] }}</a>
        @else
            <span class="text-muted">—</span>
        @endif
    </td>
    <td>
        <select class="form-select form-select-sm cl-edit-move" data-phrase="{{ $row['phrase'] }}" data-from="{{ $fromGroup }}">
            <option value="">{{ __('Move to cluster') }}…</option>
            @if(!$isSingle)
                <option value="__single__">{{ __('Unallocated words') }}</option>
            @endif
            @foreach($groupNames as $name)
                @if($name !== $fromGroup)
                    <option value="{{ $name }}">{{ $name }}</option>
                @endif
            @endforeach
            <option value="__new__">{{ __('Adding a new group') }}…</option>
        </select>
    </td>
</tr>
