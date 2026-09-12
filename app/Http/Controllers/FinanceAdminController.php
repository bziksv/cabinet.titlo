<?php

namespace App\Http\Controllers;

use App\PromoCode;
use App\Services\Finance\FinanceAdminService;
use App\Services\Finance\PromoCodeService;
use App\Services\Finance\TriggerCampaignMailService;
use App\Services\Finance\TriggerCampaignService;
use App\TriggerCampaign;
use App\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class FinanceAdminController extends Controller
{
    public function __construct()
    {
        $this->middleware(['role:Super Admin|admin']);
    }

    public function index(Request $request, FinanceAdminService $finance, PromoCodeService $promos, TriggerCampaignService $triggerCampaigns): View
    {
        $filters = [
            'status' => (string) $request->get('status', 'all'),
            'q' => (string) $request->get('q', ''),
            'period' => (string) $request->get('period', 'all'),
        ];

        $excludeAdmins = FinanceAdminService::resolveExcludeAdminsFromRequest($request);
        $campaignFilter = $request->get('campaign');
        $campaignId = is_numeric($campaignFilter) ? (int) $campaignFilter : null;

        $promoFilters = [
            'promo_q' => trim((string) $request->get('promo_q', '')),
            'promo_status' => (string) $request->get('promo_status', 'all'),
            'promo_source' => (string) $request->get('promo_source', 'all'),
        ];

        $triggerCampaignList = $triggerCampaigns->listForAdmin();
        $triggerStats = [];
        foreach ($triggerCampaignList as $campaign) {
            $triggerStats[$campaign->id] = $triggerCampaigns->statsForCampaign($campaign);
        }

        return view('admin.finance.index', [
            'summary' => $finance->summary($excludeAdmins),
            'topUsers' => $finance->topUsers(15, $excludeAdmins),
            'chart' => $finance->monthlyChart($excludeAdmins),
            'transactions' => $finance->transactions($filters, 25, $excludeAdmins),
            'filters' => $filters,
            'excludeAdmins' => $excludeAdmins,
            'statusOptions' => FinanceAdminService::statusOptions(),
            'periodOptions' => FinanceAdminService::periodOptions(),
            'creditPresets' => [500, 1000, 3000, 5000, 10000],
            'promoCodes' => $promos->paginateForAdmin($promoFilters, 25),
            'promoFilters' => $promoFilters,
            'triggerCampaigns' => $triggerCampaignList,
            'triggerStats' => $triggerStats,
            'triggerCampaignFilter' => $campaignId,
        ]);
    }

    public function searchUsers(Request $request, FinanceAdminService $finance): JsonResponse
    {
        return response()->json([
            'results' => $finance->searchUsersForSelect((string) $request->get('q', '')),
        ]);
    }

    public function credit(Request $request, FinanceAdminService $finance): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'wallet' => ['nullable', 'string', 'in:personal,company'],
            'user_company_id' => ['nullable', 'integer', 'exists:user_companies,id'],
            'company_invoice_id' => ['nullable', 'integer', 'exists:company_invoices,id'],
            'sum' => ['nullable', 'integer', 'min:1', 'max:10000000'],
            'comment' => ['nullable', 'string', 'max:500'],
        ]);

        /** @var User $admin */
        $admin = Auth::user();
        $user = User::query()->findOrFail((int) $data['user_id']);
        $wallet = $data['wallet'] ?? 'personal';

        if ($wallet === 'company') {
            $invoiceId = (int) ($data['company_invoice_id'] ?? 0);
            if ($invoiceId < 1) {
                throw ValidationException::withMessages([
                    'company_invoice_id' => [__('Finance credit invoice placeholder')],
                ]);
            }

            $invoice = \App\CompanyInvoice::query()->findOrFail($invoiceId);
            if ((int) $invoice->user_id !== (int) $user->id) {
                abort(403);
            }
            if (!empty($data['user_company_id']) && (int) $invoice->user_company_id !== (int) $data['user_company_id']) {
                throw ValidationException::withMessages([
                    'company_invoice_id' => ['Счёт не относится к выбранной компании.'],
                ]);
            }

            $balance = $finance->creditCompanyInvoice($invoice, $admin, $data['comment'] ?? null);
            $company = $balance->company;

            flash()->overlay(
                __('Finance credit company success', [
                    'sum' => FinanceAdminService::formatMoney((int) $balance->sum),
                    'company' => $company ? $company->name : '',
                ]),
                ' '
            )->success();

            return redirect()->route('admin.finance.index', [
                'q' => (string) $user->email,
                'status' => '1',
                'period' => 'all',
            ]);
        }

        $sum = (int) ($data['sum'] ?? 0);
        if ($sum < 1) {
            throw ValidationException::withMessages([
                'sum' => [__('Sum must be positive')],
            ]);
        }

        $finance->creditUser(
            (int) $data['user_id'],
            $sum,
            $admin,
            $data['comment'] ?? null
        );

        $userName = trim(($user->name ?? '') . ' ' . ($user->last_name ?? ''));
        if ($userName === '') {
            $userName = (string) $user->email;
        }

        flash()->overlay(
            __('Finance credit success', [
                'sum' => FinanceAdminService::formatMoney($sum),
                'user' => $userName,
                'email' => $user->email,
                'balance' => FinanceAdminService::formatMoney((int) $user->fresh()->balance),
            ]),
            ' '
        )->success();

        return redirect()->route('admin.finance.index', [
            'q' => (string) $user->email,
            'status' => '1',
            'period' => 'all',
        ]);
    }

    public function companies(Request $request, FinanceAdminService $finance): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        return response()->json([
            'results' => $finance->companiesForSelect((int) $data['user_id']),
        ]);
    }

    public function invoices(Request $request, FinanceAdminService $finance): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'company_id' => ['required', 'integer', 'exists:user_companies,id'],
        ]);

        return response()->json([
            'results' => $finance->pendingInvoicesForSelect((int) $data['company_id'], (int) $data['user_id']),
        ]);
    }

    public function storePromo(Request $request, PromoCodeService $promos): RedirectResponse
    {
        $request->merge([
            'code' => preg_replace('/\s+/u', '', mb_strtoupper(trim((string) $request->input('code', '')))),
        ]);

        $data = $this->validatePromo($request);

        /** @var User $admin */
        $admin = Auth::user();
        $promos->create($data, $admin);

        flash()->overlay(__('Promo code created'), ' ')->success();

        return redirect()->route('admin.finance.index', ['tab' => 'promo']);
    }

    public function updatePromo(Request $request, PromoCode $promoCode, PromoCodeService $promos): RedirectResponse
    {
        $data = $this->validatePromo($request, $promoCode->id);
        $promos->update($promoCode, $data);

        flash()->overlay(__('Promo code updated'), ' ')->success();

        return redirect()->route('admin.finance.index', ['tab' => 'promo']);
    }

    public function togglePromo(PromoCode $promoCode): RedirectResponse
    {
        $promoCode->is_active = !$promoCode->is_active;
        $promoCode->save();

        flash()->overlay(
            $promoCode->is_active ? __('Promo code activated') : __('Promo code deactivated'),
            ' '
        )->success();

        return redirect()->route('admin.finance.index', ['tab' => 'promo']);
    }

    public function simulateTopUp(Request $request, FinanceAdminService $finance, PromoCodeService $promos): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'sum' => ['required', 'integer', 'min:1', 'max:10000000'],
            'promo_code' => ['nullable', 'string', 'max:64'],
        ]);

        /** @var User $admin */
        $admin = Auth::user();
        $user = User::query()->findOrFail((int) $data['user_id']);

        $balance = $finance->simulateTopUp(
            $user,
            (int) $data['sum'],
            isset($data['promo_code']) ? (string) $data['promo_code'] : null,
            $admin,
            $promos
        );

        flash()->overlay(
            __('Promo simulate success', [
                'paid' => FinanceAdminService::formatMoney((int) ($balance->paid_sum ?? $balance->sum)),
                'bonus' => FinanceAdminService::formatMoney((int) $balance->bonus_sum),
                'total' => FinanceAdminService::formatMoney((int) $balance->sum),
                'email' => $user->email,
                'balance' => FinanceAdminService::formatMoney((int) $user->fresh()->balance),
            ]),
            ' '
        )->success();

        return redirect()->route('admin.finance.index', [
            'tab' => 'promo',
            'q' => (string) $user->email,
            'status' => '1',
        ]);
    }

    public function updateTriggerCampaign(Request $request, TriggerCampaign $triggerCampaign, TriggerCampaignService $campaigns): RedirectResponse
    {
        $rules = [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'trigger_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'send_rate_per_minute' => ['required', 'integer', 'min:1', 'max:' . TriggerCampaign::MAX_SEND_RATE_PER_MINUTE],
            'email_subject' => ['required', 'string', 'max:200'],
            'email_intro' => ['nullable', 'string', 'max:2000'],
            'email_body' => ['nullable', 'string', 'max:5000'],
            'email_subject_en' => ['nullable', 'string', 'max:200'],
            'email_intro_en' => ['nullable', 'string', 'max:2000'],
            'email_body_en' => ['nullable', 'string', 'max:5000'],
        ];

        if ($triggerCampaign->sendsPromo()) {
            $rules['coupon_bonus_type'] = ['required', Rule::in([PromoCode::BONUS_FIXED, PromoCode::BONUS_PERCENT])];
            $rules['coupon_bonus_value'] = ['required', 'integer', 'min:1', 'max:10000000'];
            $rules['coupon_expires_days'] = ['required', 'integer', 'min:1', 'max:365'];
        }

        $data = $request->validate($rules);

        if ($triggerCampaign->sendsPromo()) {
            $bonusType = (string) ($data['coupon_bonus_type'] ?? PromoCode::BONUS_FIXED);
            if ($bonusType === PromoCode::BONUS_PERCENT && (int) $data['coupon_bonus_value'] > 100) {
                throw ValidationException::withMessages([
                    'coupon_bonus_value' => [__('Trigger field coupon bonus percent max')],
                ]);
            }
        }

        $campaigns->updateCampaign($triggerCampaign, $data);

        flash()->overlay(__('Trigger campaign updated'), ' ')->success();

        return redirect()->route('admin.finance.index', ['tab' => 'trigger', 'campaign' => $triggerCampaign->id]);
    }

    public function toggleTriggerCampaign(TriggerCampaign $triggerCampaign, TriggerCampaignService $campaigns): RedirectResponse
    {
        $campaigns->toggleCampaign($triggerCampaign);
        $fresh = $triggerCampaign->fresh();

        flash()->overlay(
            $fresh->is_active ? __('Trigger campaign activated') : __('Trigger campaign deactivated'),
            ' '
        )->success();

        return redirect()->route('admin.finance.index', ['tab' => 'trigger', 'campaign' => $triggerCampaign->id]);
    }

    public function pauseTriggerCampaign(TriggerCampaign $triggerCampaign, TriggerCampaignService $campaigns): RedirectResponse
    {
        if (!$triggerCampaign->is_active) {
            flash()->overlay(__('Trigger campaign pause requires active'), ' ')->warning();

            return redirect()->route('admin.finance.index', ['tab' => 'trigger', 'campaign' => $triggerCampaign->id]);
        }

        $campaigns->pauseCampaign($triggerCampaign);

        flash()->overlay(__('Trigger campaign paused success'), ' ')->success();

        return redirect()->route('admin.finance.index', ['tab' => 'trigger', 'campaign' => $triggerCampaign->id]);
    }

    public function resumeTriggerCampaign(TriggerCampaign $triggerCampaign, TriggerCampaignService $campaigns): RedirectResponse
    {
        if (!$triggerCampaign->is_active) {
            flash()->overlay(__('Trigger campaign resume requires active'), ' ')->warning();

            return redirect()->route('admin.finance.index', ['tab' => 'trigger', 'campaign' => $triggerCampaign->id]);
        }

        $campaigns->resumeCampaign($triggerCampaign);

        flash()->overlay(__('Trigger campaign resumed'), ' ')->success();

        return redirect()->route('admin.finance.index', ['tab' => 'trigger', 'campaign' => $triggerCampaign->id]);
    }

    public function testTriggerCampaign(
        Request $request,
        TriggerCampaign $triggerCampaign,
        TriggerCampaignService $campaigns,
        TriggerCampaignMailService $mail
    ): RedirectResponse {
        $data = $request->validate([
            'lang' => ['required', 'in:ru,en'],
        ]);

        /** @var User $admin */
        $admin = Auth::user();
        $result = $mail->sendTestToAdmin($triggerCampaign, $admin, $campaigns, $data['lang']);

        if ($result['ok']) {
            flash()->overlay($result['message'], ' ')->success();
        } else {
            flash()->overlay($result['message'], ' ')->error();
        }

        return redirect()->route('admin.finance.index', ['tab' => 'trigger', 'campaign' => $triggerCampaign->id]);
    }

    public function triggerCampaignStats(
        Request $request,
        TriggerCampaign $triggerCampaign,
        TriggerCampaignService $campaigns
    ): View {
        $filter = (string) $request->get('filter', 'sent');

        return view('admin.finance.trigger-stats', [
            'campaign' => $triggerCampaign,
            'stats' => $campaigns->statsForCampaign($triggerCampaign),
            'chart' => $campaigns->chartForCampaign($triggerCampaign),
            'dispatches' => $campaigns->dispatchesForCampaignStats(
                $triggerCampaign,
                $filter !== '' ? $filter : null
            ),
            'filter' => $filter,
            'filterOptions' => [
                'sent' => __('Trigger stats filter all sent'),
                '' => __('Trigger dispatches all statuses'),
                'opened' => __('Trigger stats filter opened'),
                'not_opened' => __('Trigger stats filter not opened'),
                'redeemed' => __('Trigger stats filter redeemed'),
                'pending' => __('Trigger stats filter pending'),
                'failed' => __('Trigger stats filter failed'),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePromo(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'code' => [
                'required',
                'string',
                'max:64',
                Rule::unique('promo_codes', 'code')->ignore($ignoreId),
            ],
            'title' => ['nullable', 'string', 'max:120'],
            'bonus_type' => ['required', Rule::in([PromoCode::BONUS_FIXED, PromoCode::BONUS_PERCENT])],
            'bonus_value' => ['required', 'integer', 'min:1', 'max:10000000'],
            'usage_mode' => ['required', Rule::in([PromoCode::USAGE_ONCE, PromoCode::USAGE_MULTI])],
            'redeem_mode' => ['nullable', Rule::in([PromoCode::REDEEM_TOPUP_BONUS, PromoCode::REDEEM_STANDALONE])],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
        ]);
    }
}
