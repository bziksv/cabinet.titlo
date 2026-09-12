<?php

namespace App\Http\Controllers;

use App\Services\Finance\PromoCodeRateLimitService;
use App\Balance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class BalanceController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index($response = null)
    {
        $user = Auth::user();
        $balances = $user->balances()->with(['promoCode:id,code', 'company:id,name'])->orderBy('id', 'desc')->paginate(10);
        $topUpsCount = $user->balances()->where('status', 1)->count();
        $lastTopUp = $user->balances()->where('status', 1)->orderBy('id', 'desc')->first();
        $promoLock = app(PromoCodeRateLimitService::class)->statusForUser($user);
        $companies = $user->companies()->orderBy('name')->get();
        $companyInvoices = $user->companyInvoices()
            ->with('company:id,name,inn')
            ->orderByDesc('id')
            ->limit(50)
            ->get();
        $balanceTab = request()->get('tab') === 'legal' ? 'legal' : 'person';
        $editCompanyId = (int) request()->get('edit', 0);
        if ($editCompanyId < 1 && old('_company_edit_id')) {
            $editCompanyId = (int) old('_company_edit_id');
        }
        $editCompany = $editCompanyId > 0
            ? $companies->firstWhere('id', $editCompanyId)
            : null;
        $companyLogs = $user->id
            ? \App\UserCompanyLog::query()
                ->where('user_id', (int) $user->id)
                ->orderByDesc('id')
                ->limit(30)
                ->get()
            : collect();

        return view('balance.index', compact(
            'balances',
            'response',
            'topUpsCount',
            'lastTopUp',
            'promoLock',
            'companies',
            'companyInvoices',
            'balanceTab',
            'editCompany',
            'companyLogs'
        ));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param Request $request
     * @return Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param int $id
     * @return Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param Request $request
     * @param int $id
     * @return Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return Response
     */
    public function destroy($id)
    {
        //
    }

    public function countingMetrics(Request $request): JsonResponse
    {
        $userId = Auth::id();
        $invId = $request->input('id');

        $query = Balance::query()
            ->where('user_id', '=', $userId)
            ->where('counting', '=', 0);

        if ($invId !== null && $invId !== '') {
            $balance = (clone $query)->where('id', '=', $invId)->first();
        } else {
            // Без InvId в SuccessURL — берём последнее успешное непомеченное пополнение.
            $balance = (clone $query)
                ->where('status', '=', 1)
                ->orderByDesc('id')
                ->first();
        }

        if ($balance === null) {
            return response()->json([
                'click' => false,
            ]);
        }

        $balance->counting = 1;
        $balance->save();

        return response()->json([
            'click' => true,
            'balance_id' => (int) $balance->id,
        ]);
    }
}
