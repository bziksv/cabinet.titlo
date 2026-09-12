<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 18mm 12mm 16mm 12mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #000; line-height: 1.25; }
        h1 {
            font-size: 14px;
            font-weight: bold;
            margin: 0 0 12px;
            padding-bottom: 6px;
            border-bottom: 2px solid #000;
        }
        table { border-collapse: collapse; width: 100%; }
        .items th, .items td { border: 1px solid #000; padding: 4px 5px; }
        .items th { font-weight: bold; text-align: center; vertical-align: middle; font-size: 9px; }
        .muted { color: #333; font-size: 9px; }
        .right { text-align: right; }
        .center { text-align: center; }
        .sign { margin-top: 8px; }
        .totals { width: 260px; margin-left: auto; margin-top: 6px; }
        .totals td { padding: 2px 0 2px 8px; font-size: 10px; }
        .totals .sum { text-align: right; white-space: nowrap; width: 90px; }
        .hr { border: 0; border-top: 1px solid #000; margin: 18px 0 12px; }
        .mp { font-size: 10px; margin-top: 4px; }
    </style>
</head>
<body>
@php
    $sellerName = $seller['name'] ?? '';
    $executorParts = array_filter([
        $sellerName,
        !empty($seller['inn']) ? 'ИНН ' . $seller['inn'] : null,
        !empty($seller['address']) ? $seller['address'] : null,
    ]);
@endphp

<h1>{{ $title }}</h1>

<p><strong>Исполнитель:</strong> {{ implode(', ', $executorParts) }}</p>
<p><strong>Заказчик:</strong> {{ $payerLine }}</p>
<p class="muted">К счёту на оплату № {{ $invoice->number }}</p>

<table class="items">
    <thead>
    <tr>
        <th style="width: 24px;">№</th>
        <th>Наименование работ, услуг</th>
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
        <td><strong>Всего</strong></td>
        <td class="sum"><strong>{{ $amountFormatted }},00</strong></td>
    </tr>
</table>

<p style="margin: 10px 0 4px;">Всего оказано услуг на сумму {{ $amountFormatted }},00 руб. без НДС</p>
<p style="margin: 0 0 10px;"><strong>{{ $amountWords }}</strong></p>
<p>Вышеперечисленные услуги выполнены полностью и в срок. Заказчик претензий по объёму, качеству и срокам оказания услуг не имеет.</p>

<hr class="hr">

<p style="margin: 0 0 4px;"><strong>Исполнитель</strong></p>
@include('billing.partials.seller-sign', ['seller' => $seller])
<div class="mp">М.П.</div>

<div class="sign" style="margin-top: 28px;">
    <p style="margin: 0 0 6px;"><strong>Заказчик</strong></p>
    <table style="width: 100%; border-collapse: collapse;">
        <tr>
            <td style="width: 58%; vertical-align: bottom;">
                <div style="border-bottom: 1px solid #000; width: 230px; height: 48px;"></div>
                <div class="muted">(подпись)</div>
            </td>
            <td style="vertical-align: bottom;">
                <div style="border-bottom: 1px solid #000; width: 210px; text-align: center; padding-bottom: 2px;">
                    / {{ $payer['name'] ?? '' }} /
                </div>
                <div class="muted">(расшифровка подписи)</div>
            </td>
        </tr>
    </table>
</div>
</body>
</html>
