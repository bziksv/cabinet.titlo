<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * В новостях кабинета тарифы пишем по-русски:
 * Бесплатный / Оптимальный / Ультимат / Максимум.
 */
class FixNewsTariffNamesToRussian extends Migration
{
    public function up(): void
    {
        $map = [
            'Maximum' => 'Максимум',
            'Ultimate' => 'Ультимат',
            'Optimal' => 'Оптимальный',
            'Free' => 'Бесплатный',
        ];

        $rows = DB::table('news')
            ->where(function ($q) {
                $q->where('content', 'like', '%Free%')
                    ->orWhere('content', 'like', '%Optimal%')
                    ->orWhere('content', 'like', '%Ultimate%')
                    ->orWhere('content', 'like', '%Maximum%');
            })
            ->get(['id', 'content']);

        foreach ($rows as $row) {
            $content = (string) $row->content;
            $updated = strtr($content, $map);
            if ($updated === $content) {
                continue;
            }

            DB::table('news')->where('id', $row->id)->update([
                'content' => $updated,
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $map = [
            'Максимум' => 'Maximum',
            'Ультимат' => 'Ultimate',
            'Оптимальный' => 'Optimal',
            'Бесплатный' => 'Free',
        ];

        $rows = DB::table('news')
            ->where(function ($q) {
                $q->where('content', 'like', '%Бесплатный%')
                    ->orWhere('content', 'like', '%Оптимальный%')
                    ->orWhere('content', 'like', '%Ультимат%')
                    ->orWhere('content', 'like', '%Максимум%');
            })
            ->get(['id', 'content']);

        foreach ($rows as $row) {
            $content = (string) $row->content;
            $updated = strtr($content, $map);
            if ($updated === $content) {
                continue;
            }

            DB::table('news')->where('id', $row->id)->update([
                'content' => $updated,
                'updated_at' => now(),
            ]);
        }
    }
}
