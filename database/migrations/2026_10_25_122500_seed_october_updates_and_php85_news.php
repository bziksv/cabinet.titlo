<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SeedOctoberUpdatesAndPhp85News extends Migration
{
    private const AUTHOR_ID = 4;

    private const PUBLISHED_AT = '2026-10-25 12:25:00';

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
<p><strong>Обновления кабинета с 26 сентября — и переход на PHP 8.5.</strong></p>

<p><strong>Инфраструктура.</strong> Кабинет переведён на <strong>PHP 8.5</strong>. Для вас интерфейс тот же, но на сервере — более новая платформа. Мы уже проверили основные модули; если после обновления что-то ведёт себя странно (ошибка, пустая выгрузка, «белый экран») — <strong>напишите тикет в <a href="/support">службу поддержки</a></strong>, разберём точечно.</p>

<p><strong>Аудит сайта.</strong></p>
<ul>
<li>Выгрузка в <strong>Excel</strong>: одна ошибка — одна строка; большие отчёты больше не «падают» по памяти. Исправлен сбой выгрузок DOC/Excel после смены PHP (нужен был модуль zip).</li>
<li><strong>Перепроверка одной страницы</strong> без полного нового обхода.</li>
<li>Удобнее <strong>массовый игнор</strong>, история проверок, лимиты страниц по тарифам (Ultimate / Maximum).</li>
<li>Стабильнее крупные сканы: сводка и корзины не «обнуляются» во время обхода.</li>
</ul>
<p>Раздел: <strong><a href="/site-audit">«Аудит сайта»</a></strong>.</p>

<p><strong>SEO-отчёты.</strong></p>
<ul>
<li>Корректнее работает <strong>период генерации</strong>, версии отчёта и список <strong>закрытых задач чеклиста</strong> в отчёте.</li>
<li>Связка с <strong>Google Search Console</strong> на главной и в отчётах.</li>
</ul>
<p>Раздел: <strong><a href="/seo-reports">«SEO-отчёты»</a></strong>.</p>

<p><strong>SEO-чеклист.</strong></p>
<ul>
<li>Системный шаблон <strong>«Контекст, Я.Директ»</strong>, обновлённые подсказки для сотрудника и клиента.</li>
<li>Архив, заметки, просрочка, удобнее фильтры и хроника.</li>
</ul>
<p>Раздел: <strong><a href="/seo-checklist">«SEO-чеклист»</a></strong>.</p>

<p><strong>Анализ релевантности.</strong> Публичная ссылка на одну проверку — можно отдать коллеге или клиенту без входа в кабинет. Раздел: <strong><a href="/analyze-relevance">«Анализ релевантности»</a></strong>.</p>

<p><strong>Мониторинг позиций.</strong> Доработки таблицы ключей и групп, прав по команде проекта, экспорта и горизонтального скролла (Shift + колесо). Раздел: <strong><a href="/monitoring-v2">«Мониторинг позиций»</a></strong>.</p>

<p><strong>Текст и AI.</strong> Правки анализатора текста / Есенина и доработки генерации под TLP (объём и тон на доработке).</p>

<p>Если интерфейс выглядит по-старому — обновите страницу с полной перезагрузкой (<strong>Ctrl+Shift+R</strong> / <strong>Cmd+Shift+R</strong>).</p>
<p>Ошибки и сбои — в <a href="/support">тикеты поддержки</a>. Идеи по улучшению — в <a href="/ideas">раздел идей</a>.</p>
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
