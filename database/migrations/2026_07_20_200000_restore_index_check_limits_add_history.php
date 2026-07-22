<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Проверки IndexCheck обратно: 5/600/1500/2400.
 * Сохранения сниппетов — отдельный IndexCheckHistory: 5/30/50/100.
 */
class RestoreIndexCheckLimitsAddHistory extends Migration
{
    private const CHECK_LIMITS = [
        'Free' => 5,
        'Optimal' => 600,
        'Ultimate' => 1500,
        'Maximum' => 2400,
    ];

    private const HISTORY_LIMITS = [
        'Free' => 5,
        'Optimal' => 30,
        'Ultimate' => 50,
        'Maximum' => 100,
    ];

    public function up(): void
    {
        if (! Schema::hasTable('tariff_settings')) {
            return;
        }

        $checkId = DB::table('tariff_settings')->where('code', 'IndexCheck')->value('id');
        if ($checkId) {
            DB::table('tariff_settings')->where('id', $checkId)->update([
                'name' => 'Проверка индексации и сниппетов (проверки)',
                'description' => '1 URL в одной ПС = 1 лимит. Title и сниппет сохраняются отдельно (см. лимит сохранений).',
                'message' => 'Лимит проверок индексации и сниппетов исчерпан ({VALUE} в месяц). Увеличьте тариф или дождитесь нового периода.',
                'updated_at' => now(),
            ]);
            foreach (self::CHECK_LIMITS as $tariff => $value) {
                DB::table('tariff_setting_values')
                    ->where('tariff_setting_id', $checkId)
                    ->where('tariff', $tariff)
                    ->update(['value' => $value, 'updated_at' => now()]);
            }
        }

        if (DB::table('tariff_settings')->where('code', 'IndexCheckHistory')->exists()) {
            $historyId = DB::table('tariff_settings')->where('code', 'IndexCheckHistory')->value('id');
            foreach (self::HISTORY_LIMITS as $tariff => $value) {
                DB::table('tariff_setting_values')
                    ->where('tariff_setting_id', $historyId)
                    ->where('tariff', $tariff)
                    ->update(['value' => $value, 'updated_at' => now()]);
            }

            return;
        }

        $sort = (int) (DB::table('tariff_setting_values')
            ->where('tariff_setting_id', $checkId ?: 0)
            ->max('sort') ?: 530);

        $historyId = DB::table('tariff_settings')->insertGetId([
            'name' => 'Проверка индексации и сниппетов — сохранённых проверок',
            'code' => 'IndexCheckHistory',
            'description' => 'Сколько результатов с title/сниппетом хранить в истории модуля.',
            'message' => 'Достигнут лимит сохранённых проверок индексации ({VALUE}). Старые записи удаляются автоматически.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (self::HISTORY_LIMITS as $tariff => $value) {
            DB::table('tariff_setting_values')->insert([
                'tariff_setting_id' => $historyId,
                'tariff' => $tariff,
                'value' => $value,
                'sort' => $sort + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('tariff_settings')) {
            return;
        }

        $historyId = DB::table('tariff_settings')->where('code', 'IndexCheckHistory')->value('id');
        if ($historyId) {
            if (Schema::hasTable('tariff_setting_user_values') && Schema::hasTable('tariff_setting_values')) {
                $valueIds = DB::table('tariff_setting_values')
                    ->where('tariff_setting_id', $historyId)
                    ->pluck('id');
                if ($valueIds->isNotEmpty()) {
                    DB::table('tariff_setting_user_values')
                        ->whereIn('tariff_setting_value_id', $valueIds)
                        ->delete();
                }
            }
            DB::table('tariff_setting_values')->where('tariff_setting_id', $historyId)->delete();
            DB::table('tariff_settings')->where('id', $historyId)->delete();
        }
    }
}
