<?php

namespace App\Http\Controllers;

use App\Services\Billing\UserCompanyAuditService;
use App\UserCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class BalanceCompanyController extends Controller
{
    public function store(Request $request, UserCompanyAuditService $audit): RedirectResponse
    {
        $user = Auth::user();
        $data = $this->validatedCompany($request, $user->id);

        $company = UserCompany::query()->create(array_merge($data, [
            'user_id' => (int) $user->id,
            'balance' => 0,
        ]));

        $audit->logCreated($company, $user);

        flash()->success(__('Company saved'));

        return redirect()->route('balance.index', ['tab' => 'legal']);
    }

    public function update(Request $request, UserCompany $user_company, UserCompanyAuditService $audit): RedirectResponse
    {
        $this->authorizeCompany($user_company);
        $data = $this->validatedCompany($request, (int) Auth::id(), (int) $user_company->id);

        $before = $user_company->only([
            'name', 'inn', 'kpp', 'legal_address', 'postal_address', 'email', 'phone',
        ]);

        $user_company->update($data);
        $audit->logUpdated($user_company->fresh(), $before, $data, Auth::user());

        flash()->success(__('Company updated'));

        return redirect()->route('balance.index', ['tab' => 'legal']);
    }

    private function validatedCompany(Request $request, int $userId, ?int $ignoreId = null): array
    {
        $innRule = Rule::unique('user_companies', 'inn')->where(static function ($q) use ($userId) {
            return $q->where('user_id', $userId);
        });
        if ($ignoreId) {
            $innRule = $innRule->ignore($ignoreId);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'inn' => ['required', 'string', 'regex:/^\d{10}(\d{2})?$/', $innRule],
            'legal_address' => ['required', 'string', 'max:500'],
            'postal_address' => ['required', 'string', 'max:500'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:64'],
        ], [
            'name.required' => __('Company name required'),
            'inn.required' => __('Company INN required'),
            'inn.regex' => __('Company INN format'),
            'inn.unique' => __('Company INN unique'),
            'legal_address.required' => __('Legal address required'),
            'postal_address.required' => __('Postal address required'),
            'email.required' => __('Company email required'),
            'email.email' => __('Company email invalid'),
            'phone.required' => __('Company phone required'),
        ], [
            'name' => __('Company name'),
            'inn' => 'ИНН',
            'legal_address' => __('Legal address'),
            'postal_address' => __('Postal address'),
            'email' => 'Email',
            'phone' => __('Phone'),
        ]);

        $data['inn'] = preg_replace('/\D+/', '', $data['inn']);
        $data['kpp'] = null;

        return $data;
    }

    private function authorizeCompany(UserCompany $company): void
    {
        abort_unless((int) $company->user_id === (int) Auth::id(), 403);
    }
}
