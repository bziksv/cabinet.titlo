<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SeedMonitoringMaintenanceNoticeNews extends Migration
{
    private const AUTHOR_ID = 4;

    private const PUBLISHED_AT = '2026-08-21 20:40:00';

    public function up(): void
    {
        if (!Schema::hasTable('news')) {
            return;
        }

        $exists = DB::table('news')
            ->where('user_id', self::AUTHOR_ID)
            ->where('created_at', self::PUBLISHED_AT)
            ->exists();
        if ($exists) {
            return;
        }

        DB::table('news')->insert([
            'user_id' => self::AUTHOR_ID,
            'content' => <<<'HTML'
<p>Доброго дня!</p>
<p><strong>Сейчас активно дорабатываем модуль «Мониторинг позиций»</strong> — таблицу ключевых слов, фиксированные колонки, фильтры и связанные экраны.</p>
<p>В ближайшее время возможны кратковременные сбои и странности в интерфейсе: таблица может дольше открываться, «прыгать» вёрстка, пропадать колонка «Запрос» или сбрасываться настройки отображения. Это связано с текущими правками, а не с вашими проектами и данными.</p>
<p>Приносим извинения за неудобства. Если что-то выглядит совсем сломанным — обновите страницу с полной перезагрузкой (<strong>Ctrl+Shift+R</strong> / <strong>Cmd+Shift+R</strong>) и напишите в <a href="/support">службу поддержки</a>: разберём точечно.</p>
<p>Идеи по мониторингу, как обычно, можно оставлять в <a href="/ideas">разделе идей</a>.</p>
HTML
            ,
            'files' => null,
            'number_of_likes' => 0,
            'created_at' => self::PUBLISHED_AT,
            'updated_at' => self::PUBLISHED_AT,
        ]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('news')) {
            return;
        }
        DB::table('news')
            ->where('user_id', self::AUTHOR_ID)
            ->where('created_at', self::PUBLISHED_AT)
            ->delete();
    }
}
