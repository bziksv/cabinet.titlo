<?php

namespace App\Services\Billing;

use App\CompanyInvoice;
use App\User;
use App\UserCompany;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompanyInvoiceService
{
    /** @var CompanyInvoiceNumberService */
    private $numbers;

    /** @var CompanyInvoicePdfService */
    private $pdf;

    public function __construct(CompanyInvoiceNumberService $numbers, CompanyInvoicePdfService $pdf)
    {
        $this->numbers = $numbers;
        $this->pdf = $pdf;
    }

    public function createInvoice(User $user, UserCompany $company, int $amount, ?string $serviceTitle = null): CompanyInvoice
    {
        if ((int) $company->user_id !== (int) $user->id) {
            throw ValidationException::withMessages([
                'user_company_id' => ['Компания не принадлежит пользователю.'],
            ]);
        }

        $minAmount = (int) config('cabinet-billing.min_invoice_amount', 10000);
        if ($amount < $minAmount) {
            throw ValidationException::withMessages([
                'sum' => [__('Invoice min sum', ['min' => number_format($minAmount, 0, '', ' ')])],
            ]);
        }

        return DB::transaction(function () use ($user, $company, $amount, $serviceTitle) {
            $now = Carbon::now();
            $number = $this->numbers->nextForUser($user, $now);

            /** @var CompanyInvoice $invoice */
            $invoice = CompanyInvoice::query()->create([
                'user_id' => (int) $user->id,
                'user_company_id' => (int) $company->id,
                'number' => $number,
                'amount' => $amount,
                'status' => CompanyInvoice::STATUS_PENDING,
                'service_title' => $serviceTitle ?: config('cabinet-billing.default_service_title'),
                'payer_snapshot' => $company->toPayerSnapshot(),
                'issued_at' => $now,
            ]);

            $this->pdf->storeInvoicePdf($invoice);

            return $invoice->fresh(['company']);
        });
    }

    public function cancel(CompanyInvoice $invoice): CompanyInvoice
    {
        if (!$invoice->isPending()) {
            throw ValidationException::withMessages([
                'invoice' => ['Отменить можно только неоплаченный счёт.'],
            ]);
        }

        $invoice->status = CompanyInvoice::STATUS_CANCELLED;
        $invoice->save();

        return $invoice;
    }
}
