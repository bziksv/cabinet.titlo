<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class SeedOfficialTitloRebrandNews extends Migration
{
    private const AUTHOR_ID = 4;

    private const PUBLISHED_AT = '2026-07-07 00:30:00';

    public function up(): void
    {
        DB::table('news')->insert([
            'user_id' => self::AUTHOR_ID,
            'content' => <<<'HTML'
<p>Доброго дня!</p>
<p><strong>Официально сообщаем: переезд завершён, ребрендинг проекта выполнен полностью.</strong></p>
<p>Сервис продолжает работу под брендом <strong>Titlo</strong>. Личный кабинет доступен по адресу <strong><a href="https://cabinet.titlo.ru">cabinet.titlo.ru</a></strong> — это основной и постоянный адрес. Старый домен lk.redbox.su перенаправляет на новый кабинет; рекомендуем обновить закладки.</p>
<p><strong>Что изменилось:</strong></p>
<ul>
<li>новый логотип и оформление интерфейса;</li>
<li>обновлённые PDF-отчёты и публичные страницы для клиентов;</li>
<li>сайт проекта — <strong><a href="https://titlo.ru">titlo.ru</a></strong> (вместо redbox.su).</li>
</ul>
<p><strong>Что осталось без изменений:</strong></p>
<ul>
<li>ваш логин и пароль;</li>
<li>все проекты, ключевые слова, история съёмов и настройки;</li>
<li>тарифы и баланс лимитов.</li>
</ul>
<p>Переход технически завершён — можно работать через <strong>cabinet.titlo.ru</strong>. По вопросам пишите на <a href="mailto:info@titlo.ru">info@titlo.ru</a>.</p>
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
