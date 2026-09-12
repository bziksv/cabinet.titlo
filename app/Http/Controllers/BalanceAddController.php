<?php

namespace App\Http\Controllers;

use App\Balance;
use App\Classes\Pay\Robokassa\RobokassaPay;
use App\PromoCode;
use App\Services\Finance\PromoCodeService;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BalanceAddController extends Controller
{
    private $robokassa;

    public function __construct()
    {
        $this->robokassa = new RobokassaPay();

        $this->robokassa->setParams('IsTest', 0);
        $this->robokassa->setParams('Description', 'Titlo.ru');
    }

    /**
     * @param Request $request
     */
    public function result(Request $request)
    {
        $params = $request->all();

        if (!$this->robokassa->checkOut($params)) {
            echo "bad sign\n";
            exit();
        }

        $invId = (int) $params['InvId'];

        $balance = Balance::query()->where('id', $invId)->where('status', 0)->first();
        if ($balance === null) {
            echo "OK$invId\n";
            exit();
        }

        $paymentMethod = $params['PaymentMethod'] ?? __('Unknown source');
        $source = (string) $paymentMethod;
        if ((int) $balance->bonus_sum > 0 && $balance->promo_code_id) {
            $promo = PromoCode::query()->find($balance->promo_code_id);
            if ($promo) {
                $source = __('Balance top up with promo source', [
                    'payment' => number_format((int) ($balance->paid_sum ?? $balance->sum), 0, '.', ' '),
                    'bonus' => number_format((int) $balance->bonus_sum, 0, '.', ' '),
                    'code' => $promo->code,
                ]);
            }
        }

        $balance->update([
            'source' => $source,
            'status' => 1,
        ]);

        $this->addBalanceToUser($balance->fresh());

        echo "OK$invId\n";
    }

    /**
     * @param Request $request
     * @return Response
     * @throws ValidationException
     */
    public function store(Request $request, PromoCodeService $promos)
    {
        $this->validate($request, [
            'sum' => ['required', 'integer', 'min:1', 'max:10000000'],
            'promo_code' => ['nullable', 'string', 'max:64'],
        ]);

        $paidSum = (int) $request->input('sum');
        $promoRaw = trim((string) $request->input('promo_code', ''));

        $bonusSum = 0;
        $promoCodeId = null;

        if ($promoRaw !== '') {
            $resolved = $promos->resolveForPayment(Auth::user(), $promoRaw, $paidSum);
            if (!$resolved['valid']) {
                return redirect()
                    ->route('balance.index')
                    ->withInput()
                    ->withErrors(['promo_code' => $resolved['message']]);
            }

            $bonusSum = (int) $resolved['bonus_sum'];
            $promoCodeId = (int) $resolved['promo_code']->id;
        }

        $balance = Auth::user()->balances()->create([
            'sum' => $paidSum + $bonusSum,
            'paid_sum' => $paidSum,
            'bonus_sum' => $bonusSum,
            'promo_code_id' => $promoCodeId,
            'source' => __('Unknown source'),
            'status' => 0,
        ]);

        $this->robokassa->setParams('InvId', $balance->id);
        $this->robokassa->setParams('OutSum', $paidSum);
        $this->robokassa->setParams('Receipt', urlencode('{"items":[{"name":"Доступ к ПО сервиса Titlo.ru","quantity":"1","sum":"'.$paidSum.'","tax":"none"}]}'));

        return redirect($this->robokassa->action());
    }

    protected function addBalanceToUser(Balance $balance): void
    {
        DB::transaction(function () use ($balance) {
            /** @var Balance|null $locked */
            $locked = Balance::query()->lockForUpdate()->find($balance->id);
            if ($locked === null || (int) $locked->status !== 1 || $locked->credited_at !== null) {
                return;
            }

            /** @var User $user */
            $user = User::query()->lockForUpdate()->findOrFail($locked->user_id);
            $user->increment('balance', $locked->sum);

            $locked->credited_at = now();
            $locked->save();

            app(PromoCodeService::class)->recordRedemption($locked);
        });
    }

    public function show($id)
    {
        //
    }

    public function edit($id)
    {
        //
    }

    public function update(Request $request, $id)
    {
        //
    }

    public function destroy($id)
    {
        //
    }
}
