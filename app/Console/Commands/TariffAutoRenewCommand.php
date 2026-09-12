<?php

namespace App\Console\Commands;

use App\Services\Billing\TariffAutoRenewalService;
use App\TariffPay;
use App\Http\Middleware\DeleteTariffByUsers;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Spatie\Permission\PermissionRegistrar;

class TariffAutoRenewCommand extends Command
{
    protected $signature = 'tariff:auto-renew
                            {--dry-run : Только показать истёкшие подписки без списания}';

    protected $description = 'Автопродление истёкших платных тарифов с баланса (личный / компания)';

    public function handle(TariffAutoRenewalService $renewal, DeleteTariffByUsers $expireMiddleware): int
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId(1);

        $now = Carbon::now();
        $expired = TariffPay::query()
            ->active()
            ->where('sum', '>', 0)
            ->where('active_to', '<', $now)
            ->orderBy('id')
            ->get();

        $renewed = 0;
        $expiredCount = 0;

        foreach ($expired as $pay) {
            if ($this->option('dry-run')) {
                $this->line("would process tariff_pay #{$pay->id} user={$pay->user_id}");
                continue;
            }

            if ($renewal->tryRenew($pay)) {
                $renewed++;
                $this->info("renewed #{$pay->id} user={$pay->user_id}");
                continue;
            }

            // Просрочка без средств — через тот же путь, что и middleware.
            $expireMiddleware->expirePaidTariff($pay);
            $expiredCount++;
            $this->warn("expired #{$pay->id} user={$pay->user_id}");
        }

        $this->line("done: renewed={$renewed} expired={$expiredCount} total=" . $expired->count());

        return 0;
    }
}
