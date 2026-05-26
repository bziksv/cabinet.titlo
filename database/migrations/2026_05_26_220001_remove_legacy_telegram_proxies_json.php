<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Уже перешли на telegram_proxies (БД): убрать устаревший JSON, если остался на сервере.
 */
class RemoveLegacyTelegramProxiesJson extends Migration
{
    public function up(): void
    {
        $path = storage_path('app/telegram-proxies.json');
        if (is_file($path)) {
            @unlink($path);
        }
    }

    public function down(): void
    {
        // не восстанавливаем
    }
}
