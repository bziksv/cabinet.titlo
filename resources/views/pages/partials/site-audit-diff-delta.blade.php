@php
    $delta = (int) ($delta ?? 0);
    $invert = ! empty($invert); // для findings: рост = плохо → красный
    if ($delta === 0) {
        $cls = 'cabinet-sa-delta--zero';
        $text = '0';
    } elseif ($delta > 0) {
        $cls = $invert ? 'cabinet-sa-delta--bad' : 'cabinet-sa-delta--good';
        $text = '+' . $delta;
    } else {
        $cls = $invert ? 'cabinet-sa-delta--good' : 'cabinet-sa-delta--bad';
        $text = (string) $delta;
    }
@endphp
<span class="cabinet-sa-delta {{ $cls }}">{{ $text }}</span>
