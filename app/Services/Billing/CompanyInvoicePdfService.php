<?php

namespace App\Services\Billing;

use App\CompanyInvoice;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class CompanyInvoicePdfService
{
    public function russianDate(?Carbon $at = null): string
    {
        $at = $at ?: Carbon::now();
        $months = [
            1 => 'января', 2 => 'февраля', 3 => 'марта', 4 => 'апреля',
            5 => 'мая', 6 => 'июня', 7 => 'июля', 8 => 'августа',
            9 => 'сентября', 10 => 'октября', 11 => 'ноября', 12 => 'декабря',
        ];

        return $at->day . ' ' . $months[(int) $at->month] . ' ' . $at->year . ' г.';
    }

    public function documentTitle(CompanyInvoice $invoice, string $kind = 'invoice'): string
    {
        $at = $invoice->issued_at ?: $invoice->created_at ?: Carbon::now();
        $prefix = $kind === 'act' ? 'Акт №' : 'Счёт на оплату №';
        $number = $kind === 'act' && $invoice->act_number
            ? $invoice->act_number
            : $invoice->number;

        return $prefix . ' ' . $number . ' от ' . $this->russianDate($at);
    }

    public function viewData(CompanyInvoice $invoice, string $kind = 'invoice'): array
    {
        $seller = $this->sellerWithAssets(config('cabinet-billing.seller', []));
        $issuedAt = $invoice->issued_at ?: $invoice->created_at ?: Carbon::now();
        $amount = (int) $invoice->amount;
        $title = $this->documentTitle($invoice, $kind);

        $paymentQrSrc = null;
        if ($kind === 'invoice') {
            $purpose = 'Оплата по счёту № ' . $invoice->number . ' от ' . $this->russianDate($issuedAt) . ' Без НДС.';
            $paymentQrSrc = app(RussianPaymentQrService::class)->dataUri($seller, $amount, $purpose, 260);
        }

        return [
            'kind' => $kind,
            'invoice' => $invoice,
            'seller' => $seller,
            'payerLine' => $invoice->payerLine(),
            'payer' => is_array($invoice->payer_snapshot) ? $invoice->payer_snapshot : [],
            'title' => $title,
            'serviceTitle' => (string) ($invoice->service_title ?: config('cabinet-billing.default_service_title')),
            'amount' => $amount,
            'amountFormatted' => number_format($amount, 0, '', ' '),
            'amountWords' => RussianMoneyInWords::rubles($amount),
            'dueDays' => (int) config('cabinet-billing.payment_due_days', 5),
            'issuedAt' => $issuedAt,
            'russianDate' => $this->russianDate($issuedAt),
            'paymentQrSrc' => $paymentQrSrc,
        ];
    }

    public function renderPdf(CompanyInvoice $invoice, string $kind = 'invoice')
    {
        $view = $kind === 'act'
            ? 'billing.company-act-pdf'
            : 'billing.company-invoice-pdf';

        return \PDF::loadView($view, $this->viewData($invoice, $kind))
            ->setPaper('a4', 'portrait');
    }

    public function storeInvoicePdf(CompanyInvoice $invoice): string
    {
        $relative = $this->relativePath($invoice, 'invoice');
        Storage::disk('local')->put($relative, $this->renderPdf($invoice, 'invoice')->output());
        $invoice->invoice_pdf_path = $relative;
        $invoice->save();

        return $relative;
    }

    public function storeActPdf(CompanyInvoice $invoice): string
    {
        $relative = $this->relativePath($invoice, 'act');
        Storage::disk('local')->put($relative, $this->renderPdf($invoice, 'act')->output());
        $invoice->act_pdf_path = $relative;
        $invoice->save();

        return $relative;
    }

    public function absolutePath(?string $relative): ?string
    {
        if (!$relative) {
            return null;
        }

        return storage_path('app/' . ltrim($relative, '/'));
    }

    private function relativePath(CompanyInvoice $invoice, string $kind): string
    {
        return sprintf(
            'company-invoices/%d/%d-%s.pdf',
            (int) $invoice->user_id,
            (int) $invoice->id,
            $kind
        );
    }

    /**
     * @param  array<string, mixed>  $seller
     * @return array<string, mixed>
     */
    private function sellerWithAssets(array $seller): array
    {
        $seller['stamp_src'] = $this->publicImageDataUri($seller['stamp'] ?? null);
        $seller['signature_src'] = $this->publicImageDataUri($seller['signature'] ?? null);

        return $seller;
    }

    private function publicImageDataUri(?string $relative): ?string
    {
        $relative = trim((string) $relative);
        if ($relative === '') {
            return null;
        }

        $path = public_path(ltrim($relative, '/'));
        if (!is_readable($path)) {
            return null;
        }

        $ext = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        $mime = [
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
        ][$ext] ?? null;

        if ($mime === null) {
            return null;
        }

        return 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($path));
    }
}
