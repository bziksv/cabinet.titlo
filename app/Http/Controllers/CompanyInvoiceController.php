<?php

namespace App\Http\Controllers;

use App\CompanyInvoice;
use App\Services\Billing\CompanyInvoicePdfService;
use App\Services\Billing\CompanyInvoiceService;
use App\UserCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class CompanyInvoiceController extends Controller
{
    public function store(Request $request, CompanyInvoiceService $invoices): RedirectResponse
    {
        $user = Auth::user();
        $minSum = (int) config('cabinet-billing.min_invoice_amount', 10000);

        if ($request->has('sum')) {
            $request->merge([
                'sum' => (int) preg_replace('/\D+/', '', (string) $request->input('sum')),
            ]);
        }

        $data = $request->validate([
            'user_company_id' => ['required', 'integer', 'exists:user_companies,id'],
            'sum' => ['required', 'integer', 'min:' . $minSum, 'max:10000000'],
        ], [
            'sum.min' => __('Invoice min sum', ['min' => number_format($minSum, 0, '', ' ')]),
            'sum.required' => __('Sum must be positive'),
        ]);

        /** @var UserCompany $company */
        $company = UserCompany::query()->findOrFail((int) $data['user_company_id']);
        abort_unless((int) $company->user_id === (int) $user->id, 403);

        $invoice = $invoices->createInvoice($user, $company, (int) $data['sum']);

        flash()->success(__('Invoice created', ['number' => $invoice->number]));

        return redirect()->route('balance.invoice.download', $invoice);
    }

    public function cancel(CompanyInvoice $company_invoice, CompanyInvoiceService $invoices): RedirectResponse
    {
        abort_unless((int) $company_invoice->user_id === (int) Auth::id(), 403);
        $invoices->cancel($company_invoice);
        flash()->success(__('Invoice cancelled'));

        return redirect()->route('balance.index', ['tab' => 'legal']);
    }

    public function download(CompanyInvoice $company_invoice, CompanyInvoicePdfService $pdf): Response
    {
        abort_unless((int) $company_invoice->user_id === (int) Auth::id() || Auth::user()->hasAnyRole(['admin', 'Super Admin']), 403);

        // Всегда пересобираем PDF — шаблон/печать/подпись могут обновиться.
        $pdf->storeInvoicePdf($company_invoice);
        $company_invoice->refresh();

        $filename = 'schet-' . preg_replace('/[^\d.\-\/]+/u', '_', $company_invoice->number) . '.pdf';

        return response(Storage::disk('local')->get($company_invoice->invoice_pdf_path), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function downloadAct(CompanyInvoice $company_invoice, CompanyInvoicePdfService $pdf): Response
    {
        abort_unless((int) $company_invoice->user_id === (int) Auth::id() || Auth::user()->hasAnyRole(['admin', 'Super Admin']), 403);
        abort_unless($company_invoice->isPaid(), 404);

        // Всегда пересобираем PDF — шаблон/печать/подпись могут обновиться.
        $pdf->storeActPdf($company_invoice);
        $company_invoice->refresh();

        $filename = 'akt-' . preg_replace('/[^\d.\-\/]+/u', '_', $company_invoice->act_number ?: $company_invoice->number) . '.pdf';

        return response(Storage::disk('local')->get($company_invoice->act_pdf_path), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
