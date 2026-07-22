<?php

namespace App\Support;

use App\MainProject;

/**
 * Иконки пунктов меню (main_projects.icon) — Font Awesome 6 solid.
 * Обновлять: php artisan cabinet:apply-menu-icons
 */
class MainProjectMenuIcons
{
    /** @var array<int, string> id main_projects => HTML иконки */
    public const MAP = [
        // Главная
        37 => '<i class="fas fa-home"></i>',
        // Анализатор релевантности
        30 => '<i class="fas fa-bullseye"></i>',
        // Анализ конкурентов по ключам
        28 => '<i class="fas fa-user-friends"></i>',
        // Анализ текста
        15 => '<i class="fas fa-file-lines"></i>',
        // Кластеризатор (было ок)
        34 => '<i class="fas fa-circle-nodes"></i>',
        // Поведенческие факторы
        1 => '<i class="fas fa-hand-pointer"></i>',
        // Удаление дубликатов
        8 => '<i class="fas fa-clone"></i>',
        // Сравнение списков
        5 => '<i class="fas fa-columns"></i>',
        // Уникальные слова
        6 => '<i class="fas fa-highlighter"></i>',
        // Подсчёт длины текста
        4 => '<i class="fas fa-text-width"></i>',
        // HTML-редактор
        7 => '<i class="fas fa-code"></i>',
        // Мониторинг позиций (было ок)
        32 => '<i class="fas fa-chart-line"></i>',
        // Мониторинг позиций v2 (рядом в меню)
        39 => '<i class="fas fa-chart-line"></i>',
        // Мониторинг сайтов (было fa-edit — как у HTML)
        13 => '<i class="fas fa-heartbeat"></i>',
        // Срок регистрации домена (было ок)
        14 => '<i class="fas fa-clock"></i>',
        // Мониторинг мета-тегов
        19 => '<i class="fas fa-heading"></i>',
        // Бэклинки (было ок)
        12 => '<i class="fas fa-link"></i>',
        // HTTP-заголовки (было fa-globe — как у конкурентов)
        11 => '<i class="fas fa-server"></i>',
        // Проверка индексации
        40 => '<i class="fas fa-magnifying-glass-chart"></i>',
        // Проверка текста Есенин
        41 => '<i class="fas fa-spell-check"></i>',
        // Сбор поисковых подсказок
        42 => '<i class="fas fa-lightbulb"></i>',
        // Записи домена (id после миграции может быть 43)
        43 => '<i class="fas fa-globe"></i>',
        // Типы сайтов в выдаче
        44 => '<i class="fas fa-layer-group"></i>',
        // Гео / локализация / коммерция
        45 => '<i class="fas fa-map-marked-alt"></i>',
        // Уникальность текста (id после миграции может быть 46)
        46 => '<i class="fas fa-fingerprint"></i>',
        // Аудит сайта
        49 => '<i class="fas fa-tasks"></i>',
        // UTM-метки
        9 => '<i class="fas fa-tags"></i>',
        // Генератор паролей
        3 => '<i class="fas fa-key"></i>',
        // Генератор ключевых слов
        2 => '<i class="fas fa-keyboard"></i>',
        // ROI-калькулятор
        10 => '<i class="fas fa-calculator"></i>',
        // Настройка меню (битая иконка)
        36 => '<i class="fas fa-sliders"></i>',
    ];

    public static function apply(): int
    {
        $updated = 0;

        foreach (self::MAP as $id => $icon) {
            $project = MainProject::query()->find($id);
            if ($project === null) {
                continue;
            }
            if (trim((string) $project->icon) === $icon) {
                continue;
            }
            $project->icon = $icon;
            $project->save();
            $updated++;
        }

        return $updated;
    }
}
