<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Site Audit лимиты (локальный MVP / волна 2).
 * SiteAudit — макс. страниц за один краул.
 * SiteAuditCrawls — краулов в календарный месяц.
 */
class AddSiteAuditTariffLimits extends Migration
{
    private const PAGE_LIMITS = [
        'Free' => 500,
        'Optimal' => 5000,
        'Ultimate' => 20000,
        'Maximum' => 50000,
    ];

    private const CRAWL_LIMITS = [
        'Free' => 1,
        'Optimal' => 4,
        'Ultimate' => 8,
        'Maximum' => 12,
    ];

    public function up(): void
    {
        if (! Schema::hasTable('tariff_settings')) {
            return;
        }

        $sort = (int) (DB::table('tariff_setting_values')->max('sort') ?: 600);

        $this->upsertSetting(
            'SiteAudit',
            'Аудит сайта — страниц за краул',
            'Максимум URL в одном запуске аудита (sitemap/импорт обрезается по лимиту).',
            'Лимит страниц за краул аудита сайта исчерпан ({VALUE}). Увеличьте тариф.',
            self::PAGE_LIMITS,
            $sort + 1
        );

        $this->upsertSetting(
            'SiteAuditCrawls',
            'Аудит сайта — краулов в месяц',
            'Сколько полных запусков аудита можно сделать за календарный месяц.',
            'Лимит запусков аудита сайта на месяц исчерпан ({VALUE}). Увеличьте тариф или дождитесь нового периода.',
            self::CRAWL_LIMITS,
            $sort + 2
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('tariff_settings')) {
            return;
        }

        foreach (['SiteAudit', 'SiteAuditCrawls'] as $code) {
            $id = DB::table('tariff_settings')->where('code', $code)->value('id');
            if (! $id) {
                continue;
            }
            if (Schema::hasTable('tariff_setting_user_values') && Schema::hasTable('tariff_setting_values')) {
                $valueIds = DB::table('tariff_setting_values')
                    ->where('tariff_setting_id', $id)
                    ->pluck('id');
                if ($valueIds->isNotEmpty()) {
                    DB::table('tariff_setting_user_values')
                        ->whereIn('tariff_setting_value_id', $valueIds)
                        ->delete();
                }
            }
            DB::table('tariff_setting_values')->where('tariff_setting_id', $id)->delete();
            DB::table('tariff_settings')->where('id', $id)->delete();
        }
    }

    private function upsertSetting(
        string $code,
        string $name,
        string $description,
        string $message,
        array $limits,
        int $sort
    ): void {
        $id = DB::table('tariff_settings')->where('code', $code)->value('id');

        if ($id) {
            DB::table('tariff_settings')->where('id', $id)->update([
                'name' => $name,
                'description' => $description,
                'message' => $message,
                'updated_at' => now(),
            ]);
        } else {
            $id = DB::table('tariff_settings')->insertGetId([
                'name' => $name,
                'code' => $code,
                'description' => $description,
                'message' => $message,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach ($limits as $tariff => $value) {
            $existing = DB::table('tariff_setting_values')
                ->where('tariff_setting_id', $id)
                ->where('tariff', $tariff)
                ->first();

            if ($existing) {
                DB::table('tariff_setting_values')->where('id', $existing->id)->update([
                    'value' => $value,
                    'sort' => $sort,
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('tariff_setting_values')->insert([
                    'tariff_setting_id' => $id,
                    'tariff' => $tariff,
                    'value' => $value,
                    'sort' => $sort,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
