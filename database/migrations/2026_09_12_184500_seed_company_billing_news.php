<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SeedCompanyBillingNews extends Migration
{
    private const AUTHOR_ID = 4;

    private const PUBLISHED_AT = '2026-09-12 18:45:00';

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
<p><strong>Оплата для юрлиц: счета, акты и автопродление подписки</strong> — в кабинете появился отдельный сценарий пополнения и оплаты тарифа для компаний.</p>
<ul>
<li><strong>Физическое и юридическое лицо</strong> — на странице <a href="/balance">«Баланс»</a> два режима: пополнение личного баланса и работа с реквизитами компании.</li>
<li><strong>Карточка компании</strong> — сохраняете название, ИНН, адреса, email и телефон; можно править данные и смотреть историю изменений по каждой фирме.</li>
<li><strong>Счёт на оплату</strong> — выставляете сумму от 10&nbsp;000&nbsp;₽, скачиваете PDF с реквизитами, печатью, подписью и QR для оплаты в банковском приложении.</li>
<li><strong>Акт</strong> — после зачисления средств по счёту доступен акт выполненных работ.</li>
<li><strong>Баланс компании</strong> — деньги зачисляются на баланс фирмы; с него можно оплатить тариф (на странице <a href="/tariff">«Тарифы»</a> выбираете, с какого кошелька списать — личного или компании).</li>
<li><strong>Автопродление подписки</strong> — если на балансе хватает средств, тариф продлевается сам. Сначала списываем с того кошелька, с которого платили в прошлый раз; если не хватает — недостающую сумму добираем с другого.</li>
</ul>
<p>Начните с раздела <strong><a href="/balance">«Баланс»</a></strong> → вкладка «Юридическое лицо»: добавьте компанию и выставьте счёт. Оплата тарифа — в <strong><a href="/tariff">«Тарифы»</a></strong>.</p>
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
