<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class SeedIndexCheckModuleNews extends Migration
{
    private const AUTHOR_ID = 4;

    private const PUBLISHED_AT = '2026-07-08 21:15:00';

    public function up(): void
    {
        DB::table('news')->insert([
            'user_id' => self::AUTHOR_ID,
            'content' => <<<'HTML'
<p>Доброго дня!</p>
<p><strong>Запустили новый модуль «Проверка индексации страницы (Яндекс и Google)»</strong> — массовая проверка URL через выдачу <code>site:</code>. Кратко, что внутри:</p>
<ul>
<li><strong>Пакетный режим</strong> — до 500 адресов в одном запуске; в таблице результатов — «Да» / «Нет» по каждой поисковой системе.</li>
<li><strong>Яндекс и Google</strong> — можно включить обе ПС или одну; для Google выбирается домен (<code>google.ru</code>, <code>google.com</code> и др.).</li>
<li><strong>Лимиты</strong> — 1 URL в одной ПС = 1 лимит в месяц. Бесплатный: 3 · Оптимальный: 600 · Ультимат: 1500 · Максимум: 2400.</li>
<li><strong>Дубликаты в списке</strong> — повторяющиеся строки подсвечиваются; в проверку идут только уникальные URL (с учётом опции «www/http/https единым URL»).</li>
<li><strong>Экспорт CSV</strong> — для отчёта заказчику или аудита после релиза.</li>
<li><strong>Главная и варианты URL</strong> — для корня сайта учитываются <code>www</code>, <code>http/https</code>, <code>/index.php</code>; глубина разбора выдачи до 100 URL.</li>
</ul>
<p>Модуль в меню кабинета: <strong><a href="https://cabinet.titlo.ru/index-check">cabinet.titlo.ru/index-check</a></strong>. На сайте — описание и демо без регистрации: <strong><a href="https://titlo.ru/proverka-indeksacii/">titlo.ru/proverka-indeksacii</a></strong>.</p>
<p>Версия модуля в кабинете: <strong>1.0.3s</strong>. Если интерфейс выглядит по-старому — обновите страницу с полной перезагрузкой (<strong>Ctrl+Shift+R</strong> / <strong>Cmd+Shift+R</strong>).</p>
<p>При обнаружении ошибок просим писать в <a href="/support">службу поддержки</a>. Если есть идеи по улучшению — в <a href="/ideas">раздел идей</a>.</p>
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
