<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * В сравнении тарифов IndexCheck — «проверки / сохранения», как у DomainRecords.
 */
class AlignIndexCheckTariffCompareDualLabel extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tariff_settings')) {
            return;
        }

        DB::table('tariff_settings')
            ->where('code', 'IndexCheck')
            ->update([
                'name' => 'Проверка индексации и сниппетов (проверки / сохранения)',
                'description' => '1 URL в одной ПС = 1 проверка. Второе число — сколько результатов с title/сниппетом хранится в истории.',
                'updated_at' => now(),
            ]);

        DB::table('tariff_settings')
            ->where('code', 'IndexCheckHistory')
            ->update([
                'name' => 'Проверка индексации и сниппетов (сохранения)',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('tariff_settings')) {
            return;
        }

        DB::table('tariff_settings')
            ->where('code', 'IndexCheck')
            ->update([
                'name' => 'Проверка индексации и сниппетов (проверки)',
                'updated_at' => now(),
            ]);

        DB::table('tariff_settings')
            ->where('code', 'IndexCheckHistory')
            ->update([
                'name' => 'Проверка индексации и сниппетов — сохранённых проверок',
                'updated_at' => now(),
            ]);
    }
}
