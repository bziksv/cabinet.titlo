<?php

return [
    [
        'id' => 'landing-block',
        'name_key' => 'Preset: landing block',
        'html' => <<<'HTML'
<h2>Заголовок блока</h2>
<p>Краткий текст посадочной: <strong>преимущества</strong>, условия и призыв к действию.</p>
<ul>
    <li>Первое преимущество</li>
    <li>Второе преимущество</li>
    <li>Третье преимущество</li>
</ul>
<p><a href="#">Узнать подробнее</a></p>
HTML,
    ],
    [
        'id' => 'html-page',
        'name_key' => 'Preset: HTML page skeleton',
        'html' => <<<'HTML'
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Название страницы</title>
</head>
<body>
    <header>
        <h1>Заголовок сайта</h1>
        <nav>
            <a href="#">Главная</a>
            <a href="#">Услуги</a>
            <a href="#">Контакты</a>
        </nav>
    </header>
    <main>
        <h2>Основной контент</h2>
        <p>Текст страницы.</p>
    </main>
    <footer>
        <p>&copy; 2026 Компания</p>
    </footer>
</body>
</html>
HTML,
    ],
    [
        'id' => 'table-5x5',
        'name_key' => 'Preset: table 5×5',
        'html' => <<<'HTML'
<table border="1" cellpadding="8" cellspacing="0" style="border-collapse: collapse; width: 100%;">
    <thead>
        <tr>
            <th>Столбец 1</th>
            <th>Столбец 2</th>
            <th>Столбец 3</th>
            <th>Столбец 4</th>
            <th>Столбец 5</th>
        </tr>
    </thead>
    <tbody>
        <tr><td>Ячейка 1</td><td>Ячейка 2</td><td>Ячейка 3</td><td>Ячейка 4</td><td>Ячейка 5</td></tr>
        <tr><td>Ячейка 1</td><td>Ячейка 2</td><td>Ячейка 3</td><td>Ячейка 4</td><td>Ячейка 5</td></tr>
        <tr><td>Ячейка 1</td><td>Ячейка 2</td><td>Ячейка 3</td><td>Ячейка 4</td><td>Ячейка 5</td></tr>
        <tr><td>Ячейка 1</td><td>Ячейка 2</td><td>Ячейка 3</td><td>Ячейка 4</td><td>Ячейка 5</td></tr>
        <tr><td>Ячейка 1</td><td>Ячейка 2</td><td>Ячейка 3</td><td>Ячейка 4</td><td>Ячейка 5</td></tr>
    </tbody>
</table>
HTML,
    ],
    [
        'id' => 'two-columns',
        'name_key' => 'Preset: two columns',
        'html' => <<<'HTML'
<table style="width: 100%; border-collapse: collapse;" cellpadding="0" cellspacing="0">
    <tr>
        <td style="width: 50%; vertical-align: top; padding-right: 16px;">
            <h3>Левая колонка</h3>
            <p>Текст или список в первой колонке.</p>
        </td>
        <td style="width: 50%; vertical-align: top; padding-left: 16px;">
            <h3>Правая колонка</h3>
            <p>Текст или изображение во второй колонке.</p>
        </td>
    </tr>
</table>
HTML,
    ],
    [
        'id' => 'faq',
        'name_key' => 'Preset: FAQ block',
        'html' => <<<'HTML'
<h2>Частые вопросы</h2>
<h3>Вопрос 1?</h3>
<p>Ответ на первый вопрос.</p>
<h3>Вопрос 2?</h3>
<p>Ответ на второй вопрос.</p>
<h3>Вопрос 3?</h3>
<p>Ответ на третий вопрос.</p>
HTML,
    ],
    [
        'id' => 'cta',
        'name_key' => 'Preset: CTA block',
        'html' => <<<'HTML'
<div style="padding: 24px; text-align: center; background: #f5f5f5; border-radius: 8px;">
    <h2>Готовы начать?</h2>
    <p>Оставьте заявку — перезвоним в течение часа.</p>
    <p><a href="#" style="display: inline-block; padding: 12px 24px; background: #0d6efd; color: #fff; text-decoration: none; border-radius: 6px;">Оставить заявку</a></p>
</div>
HTML,
    ],
    [
        'id' => 'article',
        'name_key' => 'Preset: article with headings',
        'html' => <<<'HTML'
<h1>Заголовок статьи</h1>
<p>Вступительный абзац с основной мыслью материала.</p>
<h2>Раздел 1</h2>
<p>Текст первого раздела.</p>
<h2>Раздел 2</h2>
<p>Текст второго раздела.</p>
<h3>Подраздел</h3>
<p>Детали подраздела.</p>
HTML,
    ],
];
