<?php

namespace App\Services\Billing;

use App\Balance;
use App\TariffPay;
use App\User;
use App\UserCompany;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class TariffAutoRenewalService
{
    /**
     * Продлить истёкший платный тариф, если хватает средств.
     * Сначала кошелёк последней оплаты, недостачу — с другого.
     */
    public function tryRenew(TariffPay $expiredPay): bool
    {
        if ((int) $expiredPay->sum <= 0 || !(bool) $expiredPay->status) {
            return false;
        }

        if (!is_string($expiredPay->class_tariff) || !class_exists($expiredPay->class_tariff)) {
            return false;
        }

        if (!is_string($expiredPay->class_period) || !class_exists($expiredPay->class_period)) {
            return false;
        }

        try {
            return (bool) DB::transaction(function () use ($expiredPay) {
                /** @var TariffPay|null $locked */
                $locked = TariffPay::query()
                    ->whereKey($expiredPay->id)
                    ->lockForUpdate()
                    ->first();

                if (!$locked || !(bool) $locked->status || (int) $locked->sum <= 0) {
                    return false;
                }

                if ($locked->active_to && $locked->active_to->isFuture()) {
                    return false;
                }

                /** @var User|null $user */
                $user = User::query()->whereKey($locked->user_id)->lockForUpdate()->first();
                if (!$user) {
                    return false;
                }

                $tariff = new $locked->class_tariff;
                $period = new $locked->class_period;
                $tariff->setPeriod($period);
                $price = (int) $tariff->price('priceWithDiscount');
                if ($price < 1) {
                    return false;
                }

                $preferredCompany = $this->resolvePreferredCompany($user, $locked);
                $allocations = $this->allocateFunds($user, $preferredCompany, $price);
                if ($allocations === null) {
                    return false;
                }

                $paidCompanyId = null;
                foreach ($allocations as $chunk) {
                    $amount = (int) $chunk['amount'];
                    if ($amount < 1) {
                        continue;
                    }

                    if ($chunk['wallet'] === 'company') {
                        /** @var UserCompany $company */
                        $company = $chunk['company'];
                        $company = UserCompany::query()->whereKey($company->id)->lockForUpdate()->first();
                        if (!$company || (int) $company->balance < $amount) {
                            return false;
                        }
                        $company->decrement('balance', $amount);
                        $paidCompanyId = (int) $company->id;

                        $user->balances()->create([
                            'sum' => $amount,
                            'source' => 'Автопродление тарифа ' . $tariff->name() . ' · ' . $company->name,
                            'status' => 2,
                            'user_company_id' => (int) $company->id,
                        ]);
                    } else {
                        if ((int) $user->balance < $amount) {
                            return false;
                        }
                        $user->decrement('balance', $amount);

                        $user->balances()->create([
                            'sum' => $amount,
                            'source' => 'Автопродление тарифа ' . $tariff->name(),
                            'status' => 2,
                            'user_company_id' => null,
                        ]);
                    }
                }

                // Предпочтительный кошелёк на следующий раз — как в прошлой оплате.
                $nextCompanyId = $preferredCompany ? (int) $preferredCompany->id : null;
                if ($nextCompanyId === null && $paidCompanyId) {
                    $nextCompanyId = $paidCompanyId;
                }

                $locked->update(['status' => false]);

                $user->pay()->create([
                    'status' => true,
                    'class_tariff' => get_class($tariff),
                    'class_period' => get_class($period),
                    'sum' => $price,
                    'user_company_id' => $nextCompanyId,
                    'auto_renewed' => true,
                    'active_to' => Carbon::now()->addDays((int) $period->days()),
                ]);

                $this->ensureTariffRole($user, $tariff->code());

                return true;
            });
        } catch (Throwable $e) {
            Log::warning('TariffAutoRenewalService: renew failed', [
                'tariff_pay_id' => $expiredPay->id,
                'user_id' => $expiredPay->user_id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function resolvePreferredCompany(User $user, TariffPay $pay): ?UserCompany
    {
        if ($pay->user_company_id) {
            $company = $user->companies()->where('id', (int) $pay->user_company_id)->first();
            if ($company) {
                return $company;
            }
        }

        /** @var Balance|null $lastDebit */
        $lastDebit = $user->balances()
            ->where('status', 2)
            ->where(function ($q) {
                $q->where('source', 'like', 'Оплата тарифа%')
                    ->orWhere('source', 'like', 'Автопродление тарифа%');
            })
            ->orderByDesc('id')
            ->first();

        if ($lastDebit && $lastDebit->user_company_id) {
            return $user->companies()->where('id', (int) $lastDebit->user_company_id)->first();
        }

        return null;
    }

    /**
     * @return array<int, array{wallet:string,amount:int,company?:UserCompany}>|null
     */
    private function allocateFunds(User $user, ?UserCompany $preferredCompany, int $price): ?array
    {
        $personal = (int) $user->balance;
        $companyBalance = $preferredCompany ? (int) $preferredCompany->balance : 0;
        $remaining = $price;
        $chunks = [];

        if ($preferredCompany) {
            $fromCompany = min($companyBalance, $remaining);
            if ($fromCompany > 0) {
                $chunks[] = [
                    'wallet' => 'company',
                    'company' => $preferredCompany,
                    'amount' => $fromCompany,
                ];
                $remaining -= $fromCompany;
            }

            if ($remaining > 0) {
                $fromPersonal = min($personal, $remaining);
                if ($fromPersonal > 0) {
                    $chunks[] = [
                        'wallet' => 'personal',
                        'amount' => $fromPersonal,
                    ];
                    $remaining -= $fromPersonal;
                }
            }
        } else {
            $fromPersonal = min($personal, $remaining);
            if ($fromPersonal > 0) {
                $chunks[] = [
                    'wallet' => 'personal',
                    'amount' => $fromPersonal,
                ];
                $remaining -= $fromPersonal;
            }

            if ($remaining > 0) {
                $fallbackCompany = $this->fallbackCompanyWithFunds($user);
                if ($fallbackCompany) {
                    $fromCompany = min((int) $fallbackCompany->balance, $remaining);
                    if ($fromCompany > 0) {
                        $chunks[] = [
                            'wallet' => 'company',
                            'company' => $fallbackCompany,
                            'amount' => $fromCompany,
                        ];
                        $remaining -= $fromCompany;
                    }
                }
            }
        }

        if ($remaining > 0 || $chunks === []) {
            return null;
        }

        return $chunks;
    }

    private function fallbackCompanyWithFunds(User $user): ?UserCompany
    {
        $lastCompanyId = $user->pay()
            ->whereNotNull('user_company_id')
            ->orderByDesc('id')
            ->value('user_company_id');

        if ($lastCompanyId) {
            $company = $user->companies()->where('id', (int) $lastCompanyId)->first();
            if ($company && (int) $company->balance > 0) {
                return $company;
            }
        }

        return $user->companies()
            ->where('balance', '>', 0)
            ->orderByDesc('balance')
            ->first();
    }

    private function ensureTariffRole(User $user, string $code): void
    {
        $user->unsetRelation('roles');
        $user->unsetRelation('permissions');

        if ($user->hasRole('Free')) {
            $user->removeRole('Free');
        }

        if (!$user->hasRole($code)) {
            $user->assignRole($code);
        }
    }
}
