@php
    $metaDiffFields = $metaDiffFields ?? config('cabinet-meta-tags.compare_meta_fields', []);
@endphp
<table class="table table-sm table-bordered table-hover align-middle mb-0">
    <thead class="table-light">
    <tr>
        <th style="width: 8rem">{{ __('Tag') }}</th>
        <th>{{ __('Content') }}</th>
        <th style="width: 3rem">{{ __('Count') }}</th>
        <th style="width: 9rem">{{ __('Main problems') }}</th>
    </tr>
    </thead>
    <tbody>
    @foreach($tags as $tag => $value)
        @php
            $compareMode = !empty($compareMode);
            $diffTags = $diffTags ?? [];
            $peerTags = $peerTags ?? null;
            $peerVal = null;
            $isMetaField = in_array($tag, $metaDiffFields, true);

            if ($peerTags !== null) {
                if (is_array($peerTags)) {
                    $peerVal = $peerTags[$tag] ?? null;
                } elseif (is_object($peerTags)) {
                    $peerVal = $peerTags->{$tag} ?? null;
                }
            }

            $isDiff = !empty($diffTags[$tag]);
            if (!$isDiff && $compareMode && $peerTags !== null) {
                $isDiff = json_encode($value) !== json_encode($peerVal);
            }

            $rowDiffClass = '';
            if ($isDiff) {
                $rowDiffClass = $isMetaField ? 'cabinet-mt-compare-row--diff-meta' : 'cabinet-mt-compare-row--diff-other';
            }

            $changedBadgeClass = $isMetaField ? 'cabinet-mt-diff-badge--meta' : 'cabinet-mt-diff-badge--other';

            $err = $errors->{$tag} ?? [];
            $errList = is_array($err) ? $err : ($err !== '' && $err !== null ? [$err] : []);
            $noProblemNeedle = __('No problem');
            $validationHtml = [];

            foreach ($errList as $e) {
                if (!is_string($e) || $e === '') {
                    continue;
                }
                if ($compareMode && (
                    strpos($e, 'badge-success') !== false
                    || strpos($e, 'text-bg-success') !== false
                    || strpos($e, $noProblemNeedle) !== false
                )) {
                    continue;
                }
                $validationHtml[] = $e;
            }
        @endphp
        <tr class="{{ $rowDiffClass }}">
            <td><span class="badge text-bg-success">&lt; {{ $tag }} &gt;</span></td>
            <td>
                @if(is_array($value))
                    <textarea class="form-control form-control-sm" rows="3" readonly>{!! implode(', ' . PHP_EOL, $value) !!}</textarea>
                @else
                    <span class="badge text-bg-danger">{{ $value }}</span>
                @endif
            </td>
            <td class="text-center">
                @if(is_array($value))
                    <span class="badge text-bg-secondary">{{ count($value) }}</span>
                @endif
            </td>
            <td class="small">
                @if($compareMode)
                    @if($isDiff)
                        <span class="badge {{ $changedBadgeClass }}">{{ __('Meta tags compare tag changed') }}</span>
                    @endif
                    @if(count($validationHtml))
                        @if($isDiff)<br>@endif
                        {!! implode('<br />', $validationHtml) !!}
                    @elseif(!$isDiff)
                        <span class="text-secondary">—</span>
                    @endif
                @else
                    @if($isDiff)
                        <span class="badge {{ $changedBadgeClass }} d-inline-block mb-1">{{ __('Meta tags compare tag changed') }}</span>
                    @endif
                    @if(count($validationHtml))
                        @if($isDiff)<br>@endif
                        {!! implode('<br />', $validationHtml) !!}
                    @elseif(!$isDiff)
                        {!! implode('<br />', $errList) !!}
                    @endif
                @endif
            </td>
        </tr>
    @endforeach
    </tbody>
</table>
