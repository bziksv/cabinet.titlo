<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SeedSiteAuditMonitoringSeptemberNews extends Migration
{
    private const AUTHOR_ID = 4;

    private const PUBLISHED_AT = '2026-09-26 14:30:00';

    public function up(): void
    {
        if (! Schema::hasTable('news')) {
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
<p><strong>Аудит сайта и мониторинг позиций — что обновили с середины сентября.</strong></p>
<p><strong>Аудит сайта.</strong> Раньше разметку страниц мы проверяли «облегчённым» способом: он работал быстро, но часть реальных HTML-ошибок на сайте просто не замечал. Мы <strong>заменили его на полноценную проверку по стандарту HTML5</strong> — так отчёт ближе к тому, что показывают серьёзные валидаторы. Проверка стала тяжелее, поэтому параллельно ускорили сам обход и разгрузку сервера: большие аудиты меньше мешают друг другу, список проектов и статусы проверок открываются заметно быстрее.</p>
<ul>
<li><strong>Массовые действия</strong> — «Исправлено» и «Игнор» сразу по списку URL или по группе одинаковых ошибок, не кликая по одной строке.</li>
<li><strong>Игнор по шаблону URL</strong> — можно закрыть целый тип страниц (например, фильтры или служебные пути), а не каждый адрес вручную.</li>
<li>Удобнее смотреть <strong>историю проверок</strong>, <strong>прогресс</strong> обхода и <strong>группы дублей</strong>.</li>
<li>Крупные отчёты с тысячами HTML-ошибок <strong>больше не подвисают</strong> при открытии.</li>
</ul>
<p>Запуск проверки — в разделе <strong><a href="/site-audit">«Аудит сайта»</a></strong>. Режим проверки разметки выбирается при старте аудита.</p>
<p><strong>Мониторинг позиций.</strong></p>
<ul>
<li>На странице <strong>групп</strong>: нормальные чекбоксы, «Выбрать всё», индикатор загрузки и <strong>графики по регионам</strong>.</li>
<li><strong>Сравнение проектов</strong> в таблице ключей; в экспорте снова корректно уходит URL.</li>
<li>При смене поисковой системы <strong>даты периода не сбрасываются</strong>; на графиках даты идут по порядку.</li>
<li><strong>Приглашения в проект</strong>: видно статус, есть баннер «принять приглашение», починили старые «залипшие» приглашения.</li>
</ul>
<p>Если интерфейс выглядит по-старому — обновите страницу с полной перезагрузкой (<strong>Ctrl+Shift+R</strong> / <strong>Cmd+Shift+R</strong>).</p>
<p>При обнаружении ошибок просим писать в <a href="/support">службу поддержки</a>. Идеи по улучшению — в <a href="/ideas">раздел идей</a>.</p>
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
        if (! Schema::hasTable('news')) {
            return;
        }

        DB::table('news')
            ->where('user_id', self::AUTHOR_ID)
            ->where('created_at', self::PUBLISHED_AT)
            ->delete();
    }
}
