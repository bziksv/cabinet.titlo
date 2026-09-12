<?php

namespace App\Services\Billing;

use Mpdf\QrCode\Output\Png;
use Mpdf\QrCode\QrCode;

class RussianPaymentQrService
{
    /**
     * QR по ГОСТ Р 56042-2014 (ST00012) для оплаты по реквизитам.
     *
     * @param  array<string, mixed>  $seller
     */
    public function dataUri(array $seller, int $amountRub, string $purpose, int $size = 180): ?string
    {
        $payload = $this->payload($seller, $amountRub, $purpose);
        if ($payload === null) {
            return null;
        }

        try {
            $qr = new QrCode($payload);
            $png = (new Png())->output($qr, max(120, $size), [255, 255, 255], [0, 0, 0]);
        } catch (\Throwable $e) {
            return null;
        }

        if ($png === '' || $png === null) {
            return null;
        }

        return 'data:image/png;base64,' . base64_encode($png);
    }

    /**
     * @param  array<string, mixed>  $seller
     */
    public function payload(array $seller, int $amountRub, string $purpose): ?string
    {
        $name = trim((string) ($seller['name'] ?? ''));
        $account = preg_replace('/\D+/', '', (string) ($seller['account'] ?? ''));
        $bik = preg_replace('/\D+/', '', (string) ($seller['bik'] ?? ''));
        $corr = preg_replace('/\D+/', '', (string) ($seller['corr_account'] ?? ''));
        $inn = preg_replace('/\D+/', '', (string) ($seller['inn'] ?? ''));
        $bankName = trim((string) ($seller['bank_name'] ?? ''));
        if (!empty($seller['bank_city'])) {
            $bankName = rtrim($bankName, ', ') . ', ' . trim((string) $seller['bank_city']);
        }

        if ($name === '' || $account === '' || $bik === '' || $corr === '' || $inn === '' || $bankName === '') {
            return null;
        }

        $amountRub = max(0, $amountRub);
        $purpose = trim(preg_replace('/\s+/u', ' ', $purpose));
        if (mb_strlen($purpose) > 210) {
            $purpose = mb_substr($purpose, 0, 210);
        }

        $parts = [
            'ST00012',
            'Name=' . $this->sanitize($name, 160),
            'PersonalAcc=' . $account,
            'BankName=' . $this->sanitize($bankName, 160),
            'BIC=' . $bik,
            'CorrespAcc=' . $corr,
            'PayeeINN=' . $inn,
            'Purpose=' . $this->sanitize($purpose, 210),
            'Sum=' . (string) ($amountRub * 100),
        ];

        $kpp = preg_replace('/\D+/', '', (string) ($seller['kpp'] ?? ''));
        if ($kpp !== '') {
            $parts[] = 'KPP=' . $kpp;
        }

        return implode('|', $parts);
    }

    private function sanitize(string $value, int $maxLen): string
    {
        $value = str_replace(['|', "\r", "\n"], ' ', $value);
        $value = trim(preg_replace('/\s+/u', ' ', $value));

        if (mb_strlen($value) > $maxLen) {
            $value = mb_substr($value, 0, $maxLen);
        }

        return $value;
    }
}
