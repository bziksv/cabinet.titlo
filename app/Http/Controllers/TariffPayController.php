<?php

namespace App\Http\Controllers;

use App\Classes\Tariffs\Facades\Tariffs;
use App\Classes\Tariffs\FreeTariff;
use App\Classes\Tariffs\Interfaces\Period;
use App\Classes\Tariffs\Tariff;
use App\TariffSetting;
use App\User;
use App\ViewComposers\LimitsComposer;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Prophecy\Exception\Doubler\ClassNotFoundException;

class TariffPayController extends Controller
{
    protected $user;
    protected $active;
    protected $tariffs = [];
    protected $periods = [];
    protected $select = [];

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $this->user = Auth::user();

            if ($this->isSubscribe()) {
                $this->active = $this->user->activeTariffPay();
            }

            return $next($request);
        });

        $tariff = new Tariffs();
        $this->tariffs = $tariff->getTariffs();
        $this->periods = $tariff->getPeriods();
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $actual = collect();
        if ($this->isSubscribe()) {
            $model = $this->active;
            $tariff = new $model->class_tariff;
            $info = [
                ['title' => __('Tariff'), 'value' => $tariff->name()],
            ];
            if ((int) $model->sum === 0) {
                $info[] = ['title' => __('Granted manually'), 'value' => __('Yes')];
                if ($model->active_to) {
                    $info[] = ['title' => __('Valid until'), 'value' => $model->active_to->format('d.m.Y H:i')];
                }
            } else {
                $info[] = ['title' => __('Days left'), 'value' => max(0, $model->active_to->diffInDays())];
                $info[] = ['title' => __('Active to'), 'value' => $model->active_to->format('d.m.Y H:i')];
            }
            $actual->put('info', $info);
            $actual->put('data', $model);
        }

        $roleTariff = $this->user->tariff();
        $staleRoleNotice = null;
        if (! $this->isSubscribe()) {
            // Залипшая платная роль без активной записи
            if ($roleTariff && $roleTariff->code() !== 'Free') {
                $lastPay = $this->user->pay()->orderByDesc('id')->first();
                $staleRoleNotice = [
                    'name' => $roleTariff->name(),
                    'expired_at' => $lastPay && $lastPay->active_to
                        ? $lastPay->active_to->format('d.m.Y')
                        : null,
                ];
            } else {
                // Обычный Free после окончания оплаты — показать когда закончилась
                $staleRoleNotice = $this->user->lastExpiredSubscriptionNotice();
            }
        }

        $total = $this->getTotal();

        foreach ($this->tariffs as $t)
            $this->select['tariffs'][$t->code()] = $t->name();

        foreach ($this->periods as $code => $p)
            $this->select['periods'][$p->code()] = $p->name();

        $select = $this->select;

        $tariffs = new Tariffs();
        $tariffsArray = [];
        foreach ($tariffs->getTariffs() as $tariff)
            $tariffsArray[] = $tariff->getAsArray();

        array_unshift($tariffsArray, (new FreeTariff())->getAsArray());

        foreach ($tariffsArray as $tariffKey => $tariffValue) {
            foreach ($tariffValue['settings'] as $key => $setting) {
                $tariffsArray[$tariffKey]['settings'][$key]['position'] = LimitsComposer::getPosition($key);
            }
            $tariffsArray[$tariffKey]['settings'] = collect($tariffsArray[$tariffKey]['settings'])->sortBy('position')->toArray();
        }

        return view('tariff.index', compact('select', 'total', 'actual', 'tariffsArray', 'staleRoleNotice'));
    }

    public function total(Request $request)
    {
        $name = $request->input('name');
        $period = $request->input('period');

        return $this->getTotal($name, $period);
    }

    protected function getTotal(string $name = null, string $period = null)
    {
        if (is_null($name) && is_null($period)) {
            $tariff = Arr::first($this->tariffs);
            $tariff->setPeriod(Arr::first($this->periods));
        } else {
            $tariff = $this->getTariff($name);
            $tariff->setPeriod($this->getPeriod($period));
        }

        return collect([
            'name' => ['title' => __('Tariff'), 'value' => $tariff->name()],
            'days' => ['title' => __('Days'), 'value' => $this->formatTariffDisplayValue($tariff->getPeriod()->days())],
            'price' => ['title' => __('Price'), 'value' => $this->formatTariffDisplayValue($tariff->price('price'))],
            'discount' => ['title' => __('Discount'), 'value' => $this->formatTariffDisplayValue($tariff->price('discount'))],
            'total' => ['title' => __('Total'), 'value' => $this->formatTariffDisplayValue($tariff->price('priceWithDiscount'))],
        ]);
    }

    private function formatTariffDisplayValue($value): string
    {
        if (is_numeric($value)) {
            return number_format((int) $value, 0, '.', ' ');
        }

        return (string) $value;
    }

    /**
     * @param string $code
     * @return Tariff
     */
    protected function getTariff(string $code): Tariff
    {
        foreach ($this->tariffs as $tariff) {
            if ($tariff->code() === $code)
                return $tariff;
        }

        throw new ClassNotFoundException("Tariffs not found!", Tariff::class);
    }

    /**
     * @param string $code
     * @return Period
     */
    protected function getPeriod(string $code): Period
    {
        foreach ($this->periods as $period) {
            if ($period->code() === $code)
                return $period;
        }

        throw new ClassNotFoundException("Period's tariff not found!", Period::class);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    public function isSubscribe()
    {
        return $this->user->activeTariffPay() !== null;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if ($this->isSubscribe()) {
            Session::flash('error', __('Subscribe has already activated!'));
            return redirect()->route('tariff.index');
        }

        $tariff = $this->getTariff($request->input('tariff'));
        $tariff->setPeriod($this->getPeriod($request->input('period')));

        try {
            $this->user->decrement('balance', $tariff->price('priceWithDiscount'));
        } catch (QueryException $exception) {
            Session::flash('error', __('Replenish the balance!'));
            return redirect()->route('tariff.index');
        }

        $this->user->pay()->create([
            'status' => true,
            'class_tariff' => get_class($tariff),
            'class_period' => get_class($tariff->getPeriod()),
            'sum' => $tariff->price('priceWithDiscount'),
            'active_to' => Carbon::now()->addDays($tariff->getPeriod()->days())
        ]);

        $this->user->balances()->create([
            'sum' => $tariff->price('priceWithDiscount'),
            'source' => "Оплата тарифа " . $tariff->name(),
            'status' => 2
        ]);

        $tariff->assignRole();

        return redirect()->route('tariff.index');
    }

    public function unsubscribe()
    {
        $tariff = $this->calculateCostDaysByActiveTariff();

        $cost = $tariff->price();

        $this->user->increment('balance', $cost['priceWithDiscount']);

        $this->user->balances()->create([
            'sum' => $cost['priceWithDiscount'],
            'source' => "Возврат средств при смене тарифа " . $tariff->name(),
            'status' => 1
        ]);

        $tariff->removeRole();

        $this->active->update(['status' => false]);
    }

    public function confirmUnsubscribe($confirm = null)
    {
        if ($confirm === "confirm") {
            $tariff = $this->calculateCostDaysByActiveTariff();

            return collect([
                'name' => $tariff->name(),
                'prices' => $tariff->price(),
                'active_days' => $tariff->getPeriod()->days()
            ]);
        }

        if ($confirm === "canceled") {
            $this->unsubscribe();
            Session::flash('info', __('Subscribe has been canceled!'));
        }
    }

    /**
     * @param int|null $days
     * @return Tariff|null
     */
    public function calculateCostDaysByActiveTariff(int $days = null): ?Tariff
    {
        if (!$this->isSubscribe())
            return null;

        $model = $this->active;

        /** @var Tariff $tariff */
        $tariff = new $model->class_tariff;

        /** @var Period $period */
        $period = new $model->class_period;

        if (empty($days))
            $days = $model->active_to->diffInDays();

        $period->setMonths(0);
        $period->setDays($days);

        $tariff->setPeriod($period);

        return $tariff;
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
