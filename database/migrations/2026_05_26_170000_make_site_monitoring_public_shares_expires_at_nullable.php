<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MakeSiteMonitoringPublicSharesExpiresAtNullable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('site_monitoring_public_shares')) {
            return;
        }

        DB::statement('ALTER TABLE site_monitoring_public_shares MODIFY expires_at TIMESTAMP NULL');
    }

    public function down()
    {
        if (!Schema::hasTable('site_monitoring_public_shares')) {
            return;
        }

        DB::statement('ALTER TABLE site_monitoring_public_shares MODIFY expires_at TIMESTAMP NOT NULL');
    }
}
