<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SeedMonitoringPositionsAugustNews extends Migration
{
    private const AUTHOR_ID = 4;

    private const PUBLISHED_AT = '2026-08-26 13:15:00';

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
<p><strong>Мониторинг позиций: таблица ключевых слов, ТОП-100 и экспорт — большой блок доработок с 21 августа</strong>. Завершили работы, о которых предупреждали ранее; ниже — что изменилось в кабинете.</p>
<ul>
<li><strong>Колонка «Запрос» и фиксированные столбцы</strong> — «Запрос» больше не пропадает при прокрутке и смене набора колонок; убрали щель между фиксированной и прокручиваемой частью, стабильнее работает горизонтальный скролл и переключение видимости столбцов.</li>
<li><strong>Скорость таблицы ключевых слов</strong> — быстрее открывается список фраз проекта: оптимизировали загрузку дат съёма, добавили кэш ответа таблицы, графики на странице проекта подгружаются отложенно и не блокируют таблицу.</li>
<li><strong>Дни съёма без «дыр»</strong> — календарь колонок строится по фактическим датам проверок; пустые дни в диапазоне не рисуются лишними столбцами.</li>
<li><strong>Длинные периоды в таблице</strong> — при большом диапазоне дат ячейки позиций заполняются поэтапно, без мерцания и скачков вёрстки; динамика считается один раз после подгрузки всех чанков; дней без данных — прочерк «-», а не «…».</li>
<li><strong>ТОП-100</strong> — выбранный период сохраняется в поле дат; колонки календаря как на основном мониторинге; снимки позиций подгружаются пакетами; дни без данных на графике и в таблице не отображаются.</li>
<li><strong>Экспорт отчётов (XLS / finance)</strong> — починили выгрузку на длинных диапазонах дат: финансовый отчёт с колонками «дней в ТОП» и «освоено» снова скачивается без ошибки 500.</li>
</ul>
<p>Откройте любой проект в <strong>«Мониторинг позиций»</strong> → таб «Ключевые слова», проверьте длинный период дат и экспорт через меню «Экспорт». Раздел <strong>ТОП-100</strong> — на странице проекта.</p>
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
        if (!Schema::hasTable('news')) {
            return;
        }

        DB::table('news')
            ->where('user_id', self::AUTHOR_ID)
            ->where('created_at', self::PUBLISHED_AT)
            ->delete();
    }
}
