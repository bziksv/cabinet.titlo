<?php

namespace App\Http\Middleware;

use App\Classes\Tariffs\Facades\Tariffs;
use App\TariffPay;
use App\User;
use Carbon\Carbon;
use Closure;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

class DeleteTariffByUsers
{
    protected $user;
    protected $tariff;

    public function __construct(User $user, TariffPay $tariff)
    {
        $this->user = $user;
        $this->tariff = $tariff;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (app()->environment('local') || env('SKIP_HEAVY_WEB_MIDDLEWARE', false)) {
            return $next($request);
        }

        // Spatie teams: без team_id=1 removeRole не трогает роли из model_has_roles.
        // apply_global_team_permissions() при госте team_id не выставляет — фиксируем явно.
        app(PermissionRegistrar::class)->setPermissionsTeamId(1);

        $now = Carbon::now();

        // Только оплаченные (sum > 0). Ручные назначения из админки (sum = 0) не сбрасываем.
        $tariffs = $this->tariff->newQuery()
            ->active()
            ->where('sum', '>', 0)
            ->where('active_to', '<', $now)
            ->get();

        foreach ($tariffs as $tariff) {
            $this->expirePaidTariff($tariff);
        }

        $this->healOrphanPaidRoles($now);

        return $next($request);
    }

    /**
     * Снять роль и деактивировать запись. Роль — до status=false, иначе при сбое
     * middleware больше не видит подписку (active scope) и роль «залипает».
     */
    protected function expirePaidTariff(TariffPay $tariff): void
    {
        if (! is_string($tariff->class_tariff) || ! class_exists($tariff->class_tariff)) {
            $tariff->update(['status' => false]);

            return;
        }

        $user = $this->user->find($tariff->user_id);
        if (! $user) {
            $tariff->update(['status' => false]);

            return;
        }

        try {
            $class = new $tariff->class_tariff;
            $code = $class->code();

            $user->unsetRelation('roles');
            $user->unsetRelation('permissions');

            if ($user->hasRole($code)) {
                $user->removeRole($code);
            }

            $user->unsetRelation('roles');
            if (! $user->hasRole('Free') && ! $this->userHasAnyPaidTariffRole($user)) {
                $user->assignRole('Free');
            }

            $tariff->update(['status' => false]);
        } catch (Throwable $e) {
            Log::warning('DeleteTariffByUsers: failed to expire tariff pay', [
                'tariff_pay_id' => $tariff->id,
                'user_id' => $tariff->user_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Добить случаи, когда status уже 0, а платная роль осталась (старый баг порядка update/removeRole).
     * Не трогаем пользователей с ручным назначением (есть pay с sum = 0).
     */
    protected function healOrphanPaidRoles(Carbon $now): void
    {
        $paidCodes = $this->paidTariffRoleCodes();
        if ($paidCodes === []) {
            return;
        }

        $userIds = User::query()
            ->role($paidCodes)
            ->whereDoesntHave('pay', static function ($q) use ($now) {
                $q->where('status', true)
                    ->where(static function ($inner) use ($now) {
                        $inner->where('sum', 0)
                            ->orWhere('active_to', '>', $now);
                    });
            })
            ->whereDoesntHave('pay', static function ($q) {
                $q->where('sum', 0);
            })
            ->whereHas('pay', static function ($q) use ($now) {
                $q->where('sum', '>', 0)
                    ->where('active_to', '<', $now);
            })
            ->limit(50)
            ->pluck('id');

        foreach ($userIds as $userId) {
            $user = $this->user->find($userId);
            if (! $user) {
                continue;
            }

            try {
                $user->unsetRelation('roles');
                $user->unsetRelation('permissions');

                foreach ($paidCodes as $code) {
                    if ($user->hasRole($code)) {
                        $user->removeRole($code);
                    }
                }

                $user->unsetRelation('roles');
                if (! $user->hasRole('Free')) {
                    $user->assignRole('Free');
                }
            } catch (Throwable $e) {
                Log::warning('DeleteTariffByUsers: failed to heal orphan role', [
                    'user_id' => $userId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * @return string[]
     */
    protected function paidTariffRoleCodes(): array
    {
        $codes = [];
        foreach ((new Tariffs())->getTariffs() as $tariff) {
            $codes[] = $tariff->code();
        }

        return $codes;
    }

    protected function userHasAnyPaidTariffRole(User $user): bool
    {
        foreach ($this->paidTariffRoleCodes() as $code) {
            if ($user->hasRole($code)) {
                return true;
            }
        }

        return false;
    }
}
