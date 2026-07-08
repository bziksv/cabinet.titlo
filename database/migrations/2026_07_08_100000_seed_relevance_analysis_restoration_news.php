<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class SeedRelevanceAnalysisRestorationNews extends Migration
{
    private const AUTHOR_ID = 4;

    private const PUBLISHED_AT = '2026-07-08 10:40:00';

    public function up(): void
    {
        DB::table('news')->insert([
            'user_id' => self::AUTHOR_ID,
            'content' => <<<'HTML'
<p>Доброго дня!</p>
<p><strong>Восстановили и доработали анализ релевантности</strong> — отчёт снова собирается корректно, таблицы и облака отображаются как надо. Кратко по изменениям:</p>
<ul>
<li><strong>TLPs (топ фраз)</strong> — осмысленные n-граммы 2–4 слова с лемматизацией, гибридные TF-IDF и BM25; убран мусор из футеров и дублей вложенных фраз; сортировка по TF-IDF ТОП.</li>
<li><strong>Униграммы и фразы</strong> — единая вёрстка таблиц, липкая шапка, фильтры по колонкам, подпись «включая все словоформы».</li>
<li><strong>Проанализированные сайты</strong> — исправлены заголовки, границы таблицы, типографика (как в остальных таблицах кабинета), выравнивание колонки «Домен».</li>
<li><strong>Облака конкурентов</strong> — снова открываются по кнопке; у каждого конкурента своё облако TF-IDF (раньше все были одинаковые); ссылки над облаками крупнее.</li>
<li><strong>Стабильность</strong> — убран «вечный» спиннер при открытии истории; пересборка тяжёлых блоков только при устаревших данных в архиве.</li>
</ul>
<p>Извиняемся за неудобства во время работ. <strong>Всем пользователям с активной платной подпиской начислены +2 дня</strong> к сроку тарифа в качестве компенсации — продление уже применено автоматически.</p>
<p>Если открываете старый отчёт из истории — обновите страницу с полной перезагрузкой (<strong>Ctrl+Shift+R</strong> / <strong>Cmd+Shift+R</strong>), чтобы подтянуть актуальные стили. Для свежих TLPs по проекту при необходимости запустите анализ заново.</p>
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
