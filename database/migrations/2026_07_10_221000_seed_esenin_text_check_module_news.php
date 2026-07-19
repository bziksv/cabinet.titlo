<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class SeedEseninTextCheckModuleNews extends Migration
{
    private const AUTHOR_ID = 4;

    private const PUBLISHED_AT = '2026-07-10 22:00:00';

    public function up(): void
    {
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
<p><strong>Запустили модуль «Проверка текста Есенин»</strong> — локальная оценка SEO-риска в духе «Баден-Баден»: повторы, стилистика, запросы, водность и удобочитаемость. Кратко, что внутри:</p>
<ul>
<li><strong>Общий риск и вкладки</strong> — балл по категориям с подсветкой проблем в тексте и HTML.</li>
<li><strong>Редактор</strong> — визуальный режим и HTML-код, правки в результатах, автосохранение до 3 версий задания.</li>
<li><strong>Текст и URL</strong> — проверка вставленного текста или страницы по адресу (с селектором контента).</li>
<li><strong>Лимиты</strong> — 1 проверка = 1 лимит в месяц. Бесплатный: 5 · Оптимальный: 100 · Ультимат: 300 · Максимум: 700.</li>
<li><strong>Публичная ссылка</strong> — можно поделиться отчётом с заказчиком (срок по настройке).</li>
</ul>
<p>Модуль в меню кабинета: <strong><a href="https://cabinet.titlo.ru/esenin-text-check">cabinet.titlo.ru/esenin-text-check</a></strong>. На сайте — описание и демо без регистрации (2 проверки в сутки): <strong><a href="https://titlo.ru/proverka-teksta-esenin/">titlo.ru/proverka-teksta-esenin</a></strong>.</p>
<p>Версия модуля в кабинете: <strong>1.3.0</strong>. Если интерфейс выглядит по-старому — обновите страницу с полной перезагрузкой (<strong>Ctrl+Shift+R</strong> / <strong>Cmd+Shift+R</strong>).</p>
<p>При обнаружении ошибок просим писать в <a href="/support">службу поддержки</a>. Идеи по улучшению — в <a href="/ideas">раздел идей</a>.</p>
HTML,
            'files' => null,
            'number_of_likes' => 0,
            'created_at' => self::PUBLISHED_AT,
            'updated_at' => self::PUBLISHED_AT,
        ]);
    }

    public function down(): void
    {
        DB::table('news')
            ->where('user_id', self::AUTHOR_ID)
            ->where('created_at', self::PUBLISHED_AT)
            ->delete();
    }
}
