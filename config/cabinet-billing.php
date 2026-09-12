<?php

return [
    'version' => '1.0.0',

    /** Срок оплаты в днях (текст на счёте). */
    'payment_due_days' => (int) env('BILLING_PAYMENT_DUE_DAYS', 5),

    /** Минимальная сумма счёта для юрлица, ₽. */
    'min_invoice_amount' => (int) env('BILLING_MIN_INVOICE_AMOUNT', 10000),

    /** Текст позиции по умолчанию. */
    'default_service_title' => env(
        'BILLING_DEFAULT_SERVICE_TITLE',
        'Доступ к ПО сервиса Titlo.ru'
    ),

    /** Реквизиты получателя (продавец) для PDF счёта/акта. Источник: https://titlo.ru/contact/ */
    'seller' => [
        'name' => env('BILLING_SELLER_NAME', 'ИП Виленская Юлия Андреевна'),
        'inn' => env('BILLING_SELLER_INN', '362903774541'),
        'kpp' => env('BILLING_SELLER_KPP', ''),
        'account' => env('BILLING_SELLER_ACCOUNT', '40802810300000019189'),
        'bank_name' => env('BILLING_SELLER_BANK_NAME', 'АО «ТИНЬКОФФ БАНК»'),
        'bank_city' => env('BILLING_SELLER_BANK_CITY', 'г. Москва'),
        'bik' => env('BILLING_SELLER_BIK', '044525974'),
        'corr_account' => env('BILLING_SELLER_CORR_ACCOUNT', '30101810145250000974'),
        'address' => env('BILLING_SELLER_ADDRESS', '394030, г. Воронеж, ул. Революции 1905 г., 31Е-174'),
        'sign_name' => env('BILLING_SELLER_SIGN_NAME', 'Виленская Ю. А.'),
        'sign_title' => env('BILLING_SELLER_SIGN_TITLE', 'ИП Виленская Ю. А.'),
        /** Пути относительно public/ (печать и подпись из образца счёта). */
        'stamp' => env('BILLING_SELLER_STAMP', 'img/billing/seller-stamp.png'),
        'signature' => env('BILLING_SELLER_SIGNATURE', 'img/billing/seller-signature.png'),
    ],
];
