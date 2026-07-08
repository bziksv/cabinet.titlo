<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class UpdateIndexCheckFreeLimitToFive extends Migration
{
    public function up(): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('tariff_settings')) {
            return;
        }

        $settingId = DB::table('tariff_settings')->where('code', 'IndexCheck')->value('id');
        if (! $settingId) {
            return;
        }

        DB::table('tariff_setting_values')
            ->where('tariff_setting_id', $settingId)
            ->where('tariff', 'Free')
            ->update([
                'value' => 5,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('tariff_settings')) {
            return;
        }

        $settingId = DB::table('tariff_settings')->where('code', 'IndexCheck')->value('id');
        if (! $settingId) {
            return;
        }

        DB::table('tariff_setting_values')
            ->where('tariff_setting_id', $settingId)
            ->where('tariff', 'Free')
            ->update([
                'value' => 3,
                'updated_at' => now(),
            ]);
    }
}
