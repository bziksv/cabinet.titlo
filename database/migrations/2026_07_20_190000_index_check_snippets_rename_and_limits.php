<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Переименование модуля + лимиты 5/30/50/100 + история со сниппетами.
 */
class IndexCheckSnippetsRenameAndLimits extends Migration
{
    private const TARIFF_LIMITS = [
        'Free' => 5,
        'Optimal' => 30,
        'Ultimate' => 50,
        'Maximum' => 100,
    ];

    private const OLD_LIMITS = [
        'Free' => 5,
        'Optimal' => 600,
        'Ultimate' => 1500,
        'Maximum' => 2400,
    ];

    public function up(): void
    {
        if (! Schema::hasTable('index_check_histories')) {
            Schema::create('index_check_histories', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('user_id');
                $table->string('url', 2048);
                $table->boolean('check_yandex')->default(false);
                $table->boolean('check_google')->default(false);
                $table->json('result');
                $table->timestamps();

                $table->index(['user_id', 'id']);
            });
        }

        $this->updateTariffSetting();
        $this->updateMenu();
    }

    public function down(): void
    {
        Schema::dropIfExists('index_check_histories');

        $settingId = DB::table('tariff_settings')->where('code', 'IndexCheck')->value('id');
        if ($settingId) {
            DB::table('tariff_settings')->where('id', $settingId)->update([
                'name' => 'Проверка индексации (проверки)',
                'description' => '1 URL в одной поисковой системе = 1 лимит. Яндекс и Google считаются отдельно.',
                'message' => 'Лимит проверок индексации исчерпан ({VALUE} в месяц). Увеличьте тариф или дождитесь нового периода.',
                'updated_at' => now(),
            ]);
            foreach (self::OLD_LIMITS as $tariff => $value) {
                DB::table('tariff_setting_values')
                    ->where('tariff_setting_id', $settingId)
                    ->where('tariff', $tariff)
                    ->update(['value' => $value, 'updated_at' => now()]);
            }
        }

        if (Schema::hasTable('main_projects')) {
            DB::table('main_projects')
                ->where('link', 'like', '%/index-check%')
                ->update([
                    'description' => 'Проверка индексации страниц в Яндексе и Google',
                    'updated_at' => now(),
                ]);
        }
    }

    private function updateTariffSetting(): void
    {
        if (! Schema::hasTable('tariff_settings')) {
            return;
        }

        $settingId = DB::table('tariff_settings')->where('code', 'IndexCheck')->value('id');
        if (! $settingId) {
            return;
        }

        DB::table('tariff_settings')->where('id', $settingId)->update([
            'name' => 'Проверка индексации и сниппетов (проверки)',
            'description' => '1 URL в одной ПС = 1 лимит. Сохраняются title и сниппет из выдачи для анализа.',
            'message' => 'Лимит проверок индексации и сниппетов исчерпан ({VALUE} в месяц). Увеличьте тариф или дождитесь нового периода.',
            'updated_at' => now(),
        ]);

        foreach (self::TARIFF_LIMITS as $tariff => $value) {
            DB::table('tariff_setting_values')
                ->where('tariff_setting_id', $settingId)
                ->where('tariff', $tariff)
                ->update(['value' => $value, 'updated_at' => now()]);
        }
    }

    private function updateMenu(): void
    {
        if (! Schema::hasTable('main_projects')) {
            return;
        }

        DB::table('main_projects')
            ->where('link', 'like', '%/index-check%')
            ->update([
                'description' => 'Проверка индексации и сниппетов в Яндексе и Google',
                'updated_at' => now(),
            ]);
    }
}
