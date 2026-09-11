@php
    $total = (int) ($total ?? 0);
    $hidden = max(0, min((int) ($hidden ?? 0), $total));
    $open = max(0, $total - $hidden);
    $sev = (string) ($sev ?? 'info');
    $label = (string) ($label ?? '');
    $fmt = function ($n) {
        return number_format((int) $n, 0, '', ' ');
    };
    $totalFmt = $fmt($total);
    $openFmt = $fmt($open);
    $hiddenFmt = $fmt($hidden);
    if ($hidden > 0) {
        $tip = ($label !== '' ? $label . ': ' : '')
            . 'всего ' . $totalFmt
            . ' · осталось ' . $openFmt
            . ' · скрыто ' . $hiddenFmt
            . ' (игнор или исправлено)';
    } else {
        $tip = $label !== '' ? ($label . ': ' . $totalFmt) : $totalFmt;
    }
@endphp
<td class="cabinet-sa-ht-num {{ $total > 0 ? 'is-' . $sev : 'is-zero' }}{{ $hidden > 0 ? ' has-split' : '' }}"
    data-sa-bucket="{{ $sev }}"
    data-sa-bucket-hidden="{{ $hidden }}"
    title="{{ $tip }}">
    @if($hidden > 0)
        <span class="cabinet-sa-ht-num__trio" aria-label="{{ $tip }}">
            <span class="cabinet-sa-ht-num__t">{{ $totalFmt }}</span>
            <span class="cabinet-sa-ht-num__sep" aria-hidden="true">/</span>
            <span class="cabinet-sa-ht-num__open">{{ $openFmt }}</span>
            <span class="cabinet-sa-ht-num__sep" aria-hidden="true">/</span>
            <span class="cabinet-sa-ht-num__hid">{{ $hiddenFmt }}</span>
        </span>
        <span class="cabinet-sa-ht-num__legend" aria-hidden="true">все / ост. / скр.</span>
    @else
        <span class="cabinet-sa-ht-num__v">{{ $totalFmt }}</span>
    @endif
</td>
