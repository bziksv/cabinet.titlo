<?php

namespace App\Services;

use App\HtmlEditorPreset;
use Illuminate\Support\Facades\Schema;

class HtmlEditorPresetService
{
    public static function maxUserPresets(): int
    {
        return (int) config('cabinet-html-editor.limits.max_user_presets', 20);
    }

    /**
     * @return array<int, array{id: string, name: string, html: string, builtin: bool}>
     */
    public static function payloadForUser(int $userId): array
    {
        $builtin = array_map(static function (array $preset) {
            return [
                'id' => 'builtin:' . $preset['id'],
                'name' => __($preset['name_key']),
                'html' => $preset['html'],
                'builtin' => true,
            ];
        }, config('cabinet-html-editor.presets.builtin', []));

        $user = [];
        if (Schema::hasTable('html_editor_presets')) {
            $user = HtmlEditorPreset::query()
                ->where('user_id', $userId)
                ->orderByDesc('id')
                ->get(['id', 'name', 'html'])
                ->map(static function (HtmlEditorPreset $preset) {
                    return [
                        'id' => 'user:' . $preset->id,
                        'name' => $preset->name,
                        'html' => $preset->html,
                        'builtin' => false,
                    ];
                })
                ->all();
        }

        return [
            'presets' => array_merge($builtin, $user),
            'max_user_presets' => self::maxUserPresets(),
            'user_preset_count' => count($user),
            'can_save_preset' => count($user) < self::maxUserPresets(),
        ];
    }
}
