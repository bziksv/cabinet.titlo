<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 18mm 12mm 16mm 12mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #000; line-height: 1.25; }
        .muted { color: #333; font-size: 9px; }
        .right { text-align: right; }
        .center { text-align: center; }
        table { border-collapse: collapse; }
        .bank { width: 100%; margin-top: 4px; }
        .bank td { border: 1px solid #000; padding: 3px 5px; vertical-align: top; }
        .bank .lbl { font-size: 9px; }
        .title {
            font-size: 14px;
            font-weight: bold;
            margin: 16px 0 10px;
            padding-bottom: 6px;
            border-bottom: 2px solid #000;
        }
        .payer { margin: 10px 0 12px; font-size: 10px; }
        .items { width: 100%; }
        .items th, .items td { border: 1px solid #000; padding: 4px 5px; }
        .items th { font-weight: bold; text-align: center; vertical-align: middle; font-size: 9px; }
        .items td { vertical-align: top; }
        .totals { width: 260px; margin-left: auto; margin-top: 6px; }
        .totals td { padding: 2px 0 2px 8px; font-size: 10px; }
        .totals .sum { text-align: right; white-space: nowrap; width: 90px; }
        .hr { border: 0; border-top: 1px solid #000; margin: 18px 0 12px; }
        .sign { margin-top: 8px; }
        .mp { font-size: 10px; margin-top: 4px; }
        .seller-foot { margin-top: 10px; font-size: 9px; color: #222; }
    </style>
</head>
<body>
@php
    $sellerName = $seller['name'] ?? '';
    $sellerInn = $seller['inn'] ?? '';
    $sellerKpp = $seller['kpp'] ?? '';
    $sellerBank = trim(($seller['bank_name'] ?? '') . (!empty($seller['bank_city']) ? ', ' . $seller['bank_city'] : ''));
@endphp

<div class="muted">Образец для заполнения платежного поручения</div>

<table class="bank" style="width: 100%; table-layout: fixed;">
    <tr>
        <td style="padding: 0; border: 1px solid #000; vertical-align: top;">
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td colspan="2" style="border-bottom: 1px solid #000; border-right: 1px solid #000; padding: 3px 5px; vertical-align: top;">
                        <span class="lbl">ИНН</span> {{ $sellerInn }}
                        &nbsp;&nbsp;&nbsp;&nbsp;
                        <span class="lbl">КПП</span> {{ $sellerKpp }}
                        <br>
                        <span class="lbl">Получатель</span><br>
                        <strong>{{ $sellerName }}</strong>
                    </td>
                    <td style="width: 42px; border-bottom: 1px solid #000; border-right: 1px solid #000; padding: 3px 5px;" class="lbl">Сч. №</td>
                    <td style="width: 145px; border-bottom: 1px solid #000; padding: 3px 5px;">{{ $seller['account'] ?? '' }}</td>
                </tr>
                <tr>
                    <td style="width: 42px; border-bottom: 1px solid #000; border-right: 1px solid #000; padding: 3px 5px;" class="lbl">БИК</td>
                    <td style="border-bottom: 1px solid #000; border-right: 1px solid #000; padding: 3px 5px;">{{ $seller['bik'] ?? '' }}</td>
                    <td rowspan="2" style="border-right: 1px solid #000; padding: 3px 5px;" class="lbl">Сч. №</td>
                    <td rowspan="2" style="padding: 3px 5px;">{{ $seller['corr_account'] ?? '' }}</td>
                </tr>
                <tr>
                    <td colspan="2" style="border-right: 1px solid #000; padding: 3px 5px;">
                        <span class="lbl">Банк получателя</span><br>
                        {{ $sellerBank }}
                    </td>
                </tr>
            </table>
        </td>
        @if(!empty($paymentQrSrc))
            <td style="width: 90px; text-align: center; vertical-align: middle; padding: 4px;">
                <img src="{{ $paymentQrSrc }}" alt="QR" width="80" height="80" style="width: 80px; height: 80px;">
            </td>
        @endif
    </tr>
</table>

<div class="title">{{ $title }}</div>

<div class="payer">
    <strong>Плательщик:</strong> {{ $payerLine }}
</div>

<table class="items">
    <thead>
    <tr>
        <th style="width: 24px;">№</th>
        <th>Наименование товара, работ, услуг</th>
        <th style="width: 44px;">Ед.<br>изм.</th>
        <th style="width: 44px;">Кол-во</th>
        <th style="width: 78px;">Цена без<br>НДС, руб.</th>
        <th style="width: 78px;">Сумма без<br>НДС, руб.</th>
    </tr>
    </thead>
    <tbody>
    <tr>
        <td class="center">1</td>
        <td>{{ $serviceTitle }}</td>
        <td class="center">услуга</td>
        <td class="center">1</td>
        <td class="right">{{ $amountFormatted }},00</td>
        <td class="right">{{ $amountFormatted }},00</td>
    </tr>
    </tbody>
</table>

<table class="totals">
    <tr>
        <td>Итого без НДС</td>
        <td class="sum">{{ $amountFormatted }},00</td>
    </tr>
    <tr>
        <td><strong>Всего к оплате</strong></td>
        <td class="sum"><strong>{{ $amountFormatted }},00</strong></td>
    </tr>
</table>

<p style="margin: 10px 0 4px;">Всего наименований 1, на сумму {{ $amountFormatted }},00 руб. без НДС</p>
<p style="margin: 0 0 10px;"><strong>{{ $amountWords }}</strong></p>

<p style="margin: 0 0 2px;"><strong>Дополнительная информация:</strong></p>
<p class="muted" style="margin: 0;">
    Оплатить в течение {{ $dueDays }} {{ $dueDays === 1 ? 'дня' : 'дней' }}
</p>

<hr class="hr">

@include('billing.partials.seller-sign', ['seller' => $seller])

<div class="mp">М.П.</div>

@if(!empty($seller['address']))
    <div class="seller-foot">
        {{ $sellerName }}<br>
        {{ $seller['address'] }}
    </div>
@endif
</body>
</html>
