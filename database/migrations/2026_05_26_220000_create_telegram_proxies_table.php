<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CreateTelegramProxiesTable extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_proxies', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->string('label', 120);
            $table->string('url', 500);
            $table->unsignedSmallInteger('priority')->default(50);
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->index(['enabled', 'priority']);
        });

        $this->importFromLegacyJsonFile();
        $this->seedFromConfigIfEmpty();
        $this->removeLegacyJsonFile();
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_proxies');
    }

    private function importFromLegacyJsonFile(): void
    {
        $path = storage_path('app/telegram-proxies.json');
        if (!is_readable($path)) {
            return;
        }

        $raw = json_decode((string) file_get_contents($path), true);
        if (!is_array($raw) || empty($raw['proxies']) || !is_array($raw['proxies'])) {
            return;
        }

        $now = now();
        foreach ($raw['proxies'] as $row) {
            if (!is_array($row) || empty($row['url'])) {
                continue;
            }
            $id = (string) ($row['id'] ?? (string) Str::uuid());
            DB::table('telegram_proxies')->updateOrInsert(
                ['id' => $id],
                [
                    'label' => (string) ($row['label'] ?? 'Proxy'),
                    'url' => trim((string) $row['url']),
                    'priority' => (int) ($row['priority'] ?? 50),
                    'enabled' => !isset($row['enabled']) || (bool) $row['enabled'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    private function seedFromConfigIfEmpty(): void
    {
        if (DB::table('telegram_proxies')->count() > 0) {
            return;
        }

        $url = trim((string) config('app.telegram_proxy'));
        if ($url === '') {
            return;
        }

        $now = now();
        DB::table('telegram_proxies')->insert([
            'id' => 'primary',
            'label' => 'Из .env (TELEGRAM_PROXY)',
            'url' => $url,
            'priority' => 100,
            'enabled' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /** После импорта в БД legacy-файл не нужен. */
    private function removeLegacyJsonFile(): void
    {
        $path = storage_path('app/telegram-proxies.json');
        if (is_file($path)) {
            @unlink($path);
        }
    }
}
