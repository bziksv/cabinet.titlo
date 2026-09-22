<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Раньше блок «принять приглашение» в UI был мёртв, а invite ставил approved=0.
 * В списке v2 снова показали pending — всплыли десятки старых «приглашений».
 * Все уже существующие membership с approved=0 считаем принятыми; новые invite
 * по-прежнему создаются с approved=0 и проходят через баннер.
 */
class ApproveLegacyMonitoringProjectInvites extends Migration
{
    public function up()
    {
        DB::table('monitoring_project_user')
            ->where('approved', 0)
            ->update([
                'approved' => 1,
                'updated_at' => now(),
            ]);
    }

    public function down()
    {
        // Не откатываем: исходное approved=0 было «забытым», не осознанным pending.
    }
}
