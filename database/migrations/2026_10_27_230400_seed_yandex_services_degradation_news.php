<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SeedYandexServicesDegradationNews extends Migration
{
    private const AUTHOR_ID = 4;

    private const PUBLISHED_AT = '2026-10-27 23:04:00';

    public function up()
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
<p><strong>Сейчас нестабильно работают сервисы Яндекса</strong> — из‑за этого <strong>мониторинг позиций</strong> может получать выдачу не полностью: часть запросов остаётся без позиции или с задержкой.</p>
<p><strong>Что лучше сделать сейчас</strong></p>
<ul>
<li>Переведите снятие позиций на <strong>ночь с 00:00 до 06:00 по Москве</strong>. В эти часы нагрузка на сеть ниже, доступ к выдаче обычно выше, и шанс собрать данные полный — больше.</li>
<li>Кто работает с <strong>анализом релевантности</strong> — пока рекомендуем снимать выдачу <strong>Google</strong>, а не Яндекса.</li>
</ul>
<p>Когда доступ к службам Яндекса снова станет нормальным, <strong>дополнительно сообщим</strong> в новостях.</p>
<p>Если по конкретному проекту позиции «дырявые» или снятие зависает — напишите в <a href="/support">службу поддержки</a>, разберём точечно.</p>
HTML
            ,
            'files' => null,
            'number_of_likes' => 0,
            'created_at' => self::PUBLISHED_AT,
            'updated_at' => self::PUBLISHED_AT,
        ]);
    }

    public function down()
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
