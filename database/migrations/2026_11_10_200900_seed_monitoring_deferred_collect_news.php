<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SeedMonitoringDeferredCollectNews extends Migration
{
    private const AUTHOR_ID = 4;

    private const PUBLISHED_AT = '2026-11-10 20:09:00';

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
<p>Доброго вечера!</p>
<p><strong>Мониторинг позиций: гибче настроили съём</strong> — и в авторасписании, и при ручном запуске.</p>
<ul>
<li><strong>Режим «Периодами»</strong> — теперь можно указать не только интервал в днях (1–31), но и <strong>время съёма</strong> (МСК). Раньше время на сервере было фиксированным.</li>
<li><strong>Ручной съём</strong> — в окне постановки в очередь появились пресеты: <strong>«Сейчас»</strong>, <strong>«Сегодня в …»</strong>, <strong>«Завтра в …»</strong> и поле времени. Например, вечером можно поставить съём на <strong>01:00</strong> следующей ночи. Лимиты списываются сразу при подтверждении, в указанное время стартует только сбор.</li>
</ul>
<p>Настройка автосъёма — в карточке проекта (<a href="/monitoring/create">создание / редактирование</a>, шаг с расписанием). Ручной запуск с отложенным стартом — на странице проекта, кнопка съёма позиций (и частотности).</p>
<p>Если интерфейс выглядит по-старому — обновите страницу с полной перезагрузкой (<strong>Ctrl+Shift+R</strong> / <strong>Cmd+Shift+R</strong>).</p>
<p>Вопросы и ошибки — в <a href="/support">службу поддержки</a>. Идеи — в <a href="/ideas">раздел идей</a>.</p>
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
