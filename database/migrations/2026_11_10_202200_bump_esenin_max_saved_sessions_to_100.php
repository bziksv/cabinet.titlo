<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BumpEseninMaxSavedSessionsTo100 extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('esenin_text_check_settings')) {
            return;
        }

        $now = now();
        $exists = DB::table('esenin_text_check_settings')
            ->where('code', 'module.max_saved_sessions')
            ->exists();

        if ($exists) {
            DB::table('esenin_text_check_settings')
                ->where('code', 'module.max_saved_sessions')
                ->update([
                    'value' => '100',
                    'updated_at' => $now,
                ]);
        } else {
            DB::table('esenin_text_check_settings')->insert([
                'code' => 'module.max_saved_sessions',
                'value' => '100',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('esenin_text_check_settings')) {
            return;
        }

        DB::table('esenin_text_check_settings')
            ->where('code', 'module.max_saved_sessions')
            ->update([
                'value' => '50',
                'updated_at' => now(),
            ]);
    }
}
