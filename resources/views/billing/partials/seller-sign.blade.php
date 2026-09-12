@php
    $sellerName = $seller['name'] ?? '';
    $signName = $seller['sign_name'] ?: $sellerName;
    $stampSrc = $seller['stamp_src'] ?? null;
    $signatureSrc = $seller['signature_src'] ?? null;
@endphp
<div class="sign">
    <table style="width: 100%; border-collapse: collapse;">
        <tr>
            <td style="width: 28%; vertical-align: top; padding-top: 8px;">
                @if(!empty($seller['sign_title']))
                    {{ $seller['sign_title'] }}
                @endif
            </td>
            <td style="width: 36%; vertical-align: bottom; position: relative; height: 105px;">
                @if($stampSrc)
                    <img src="{{ $stampSrc }}" alt="" width="100" height="100"
                         style="position: absolute; left: 0; top: 0; width: 100px; height: 100px; z-index: 1;">
                @endif
                @if($signatureSrc)
                    <img src="{{ $signatureSrc }}" alt="" width="95" height="76"
                         style="position: absolute; left: 78px; top: 22px; width: 95px; height: auto; z-index: 2;">
                @endif
                <div style="border-bottom: 1px solid #000; width: 200px; height: 72px; margin-left: 70px;"></div>
                <div class="muted" style="margin-left: 70px;">(подпись)</div>
            </td>
            <td style="width: 36%; vertical-align: bottom; padding-bottom: 2px;">
                <div style="border-bottom: 1px solid #000; width: 200px; text-align: center; padding-bottom: 2px;">
                    /{{ $signName }}/
                </div>
                <div class="muted">(расшифровка подписи)</div>
            </td>
        </tr>
    </table>
</div>
