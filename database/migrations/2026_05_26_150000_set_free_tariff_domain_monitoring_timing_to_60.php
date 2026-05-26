<?php

use App\Support\SiteMonitoringTiming;
use Illuminate\Database\Migrations\Migration;

class SetFreeTariffDomainMonitoringTimingTo60 extends Migration
{
    public function up(): void
    {
        SiteMonitoringTiming::migrateFreeTariffProjectsToAllowedInterval();
    }

    public function down(): void
    {
        // Не восстанавливаем прежние интервалы — данные неизвестны.
    }
}
