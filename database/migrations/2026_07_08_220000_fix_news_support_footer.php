<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class FixNewsSupportFooter extends Migration
{
    private const FOOTER = <<<'HTML'
<p>При обнаружении ошибок просим писать в <a href="/support">службу поддержки</a>. Если есть идеи по улучшению — в <a href="/ideas">раздел идей</a>.</p>
HTML;

    public function up(): void
    {
        $this->patchNews('2026-07-08 21:15:00', [
            ['search' => 'Кратко, что внутри (Станислав):', 'replace' => 'Кратко, что внутри:'],
            [
                'search' => '<p>Вопросы и предложения — <a href="mailto:info@titlo.ru">info@titlo.ru</a>.</p>',
                'replace' => self::FOOTER,
            ],
        ]);

        $this->patchNews('2026-07-08 10:40:00', [
            ['search' => 'Кратко по изменениям (Станислав):', 'replace' => 'Кратко по изменениям:'],
            [
                'search' => '<p>Вопросы — <a href="mailto:info@titlo.ru">info@titlo.ru</a>.</p>',
                'replace' => self::FOOTER,
            ],
        ]);
    }

    /**
     * @param array<int, array{search: string, replace: string}> $replacements
     */
    private function patchNews(string $publishedAt, array $replacements): void
    {
        $row = DB::table('news')->where('created_at', $publishedAt)->first();
        if ($row === null || empty($row->content)) {
            return;
        }

        $content = (string) $row->content;
        $changed = false;

        foreach ($replacements as $replacement) {
            if (strpos($content, $replacement['search']) === false) {
                continue;
            }
            $content = str_replace($replacement['search'], $replacement['replace'], $content);
            $changed = true;
        }

        if (! $changed) {
            return;
        }

        DB::table('news')->where('id', $row->id)->update([
            'content' => $content,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Не откатываем — правка текста новостей.
    }
}
