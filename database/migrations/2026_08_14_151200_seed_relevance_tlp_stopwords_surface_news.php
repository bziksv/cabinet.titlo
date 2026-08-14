<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SeedRelevanceTlpStopwordsSurfaceNews extends Migration
{
    private const AUTHOR_ID = 4;

    private const PUBLISHED_AT = '2026-08-14 15:12:00';

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
<p><strong>Топ лист словосочетаний (TLPs) стал естественнее</strong> — доработали анализатор релевантности:</p>
<ul>
<li><strong>Предлоги и союзы в словосочетаниях</strong> — даже если в настройках анализа выключено «Учитывать союзы, предлоги, местоимения», в TLP они остаются внутри фразы: «увеличение для диагностики», а не «увеличение диагностика». В униграммах по-прежнему вырезаются, как и раньше.</li>
<li><strong>Без обрывков</strong> — отсекаем хвосты вроде «увеличение для» и начала вроде «для увеличение»: нужна полноценная связка «слово + предлог + слово».</li>
<li><strong>Живые словоформы</strong> — в TLP сохраняется склонение из текста конкурентов («для диагностики», «оптическое увеличение»), а не только словарная форма.</li>
<li><strong>История анализов</strong> — статус «Обрабатывается» больше не съезжает в ячейке; кнопки «Подробная информация» и «Повторить анализ» в одном стиле с кабинетом.</li>
</ul>
<p>Старые отчёты при открытии один раз пересоберут TLP (может занять до минуты). Если связки не появились — запустите <strong>«Повторить анализ»</strong>. Новые прогоны сразу идут с обновлённой логикой.</p>
<p>Если интерфейс выглядит по-старому — обновите страницу с полной перезагрузкой (<strong>Ctrl+Shift+R</strong> / <strong>Cmd+Shift+R</strong>).</p>
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
        if (!Schema::hasTable('news')) {
            return;
        }
        DB::table('news')
            ->where('user_id', self::AUTHOR_ID)
            ->where('created_at', self::PUBLISHED_AT)
            ->delete();
    }
}
