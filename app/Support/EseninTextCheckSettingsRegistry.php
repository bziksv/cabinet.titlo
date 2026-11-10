<?php

namespace App\Support;

use App\EseninTextCheckSetting;
use Illuminate\Support\Facades\Schema;

class EseninTextCheckSettingsRegistry
{
    /** @var array<string, string|null> */
    private static $cache = [];

    /** @var bool|null */
    private static $tableReady;

    public static function tableReady(): bool
    {
        if (self::$tableReady !== null) {
            return self::$tableReady;
        }

        try {
            self::$tableReady = Schema::hasTable('esenin_text_check_settings');
        } catch (\Throwable $e) {
            self::$tableReady = false;
        }

        return self::$tableReady;
    }

    public static function flushCache(): void
    {
        self::$cache = [];
    }

    /**
     * @return array<string, string>
     */
    public static function defaults(): array
    {
        $demo = config('cabinet-esenin-text-check.demo', []);
        $limits = config('cabinet-esenin-text-check.limits', []);
        $lt = config('esenin-external-providers.languagetool', []);
        $turgenev = config('esenin-external-providers.turgenev', []);
        $opencorpora = config('esenin-external-providers.opencorpora', []);
        $learning = config('esenin-external-providers.learning', []);

        return [
            'module.max_chars' => (string) (int) config('cabinet-esenin-text-check.max_chars', 20000),
            'module.cost_per_check' => (string) (int) config('cabinet-esenin-text-check.cost_per_check', 1),
            'module.default_mode' => (string) config('cabinet-esenin-text-check.default_mode', 'risk'),
            'module.analyzer_version' => (string) (int) config('cabinet-esenin-text-check.analyzer_version', 1),
            'module.max_versions_per_session' => (string) (int) ($limits['max_versions_per_session'] ?? 3),
            'module.max_saved_sessions' => (string) (int) ($limits['max_saved_sessions'] ?? 100),
            'module.autosave_debounce_ms' => (string) (int) ($limits['autosave_debounce_ms'] ?? 2500),
            'module.public_share_ttl_days' => json_encode(
                config('cabinet-esenin-text-check.public_share_ttl_days', [30, 90, 180, 365, 0]),
                JSON_UNESCAPED_UNICODE
            ),
            'demo.max_runs_per_day' => (string) (int) ($demo['max_runs_per_day'] ?? 3),
            'demo.max_chars' => (string) (int) ($demo['max_chars'] ?? 5000),
            'demo.min_chars' => (string) (int) ($demo['min_chars'] ?? 100),
            'demo.full_max_chars' => (string) (int) ($demo['full_max_chars'] ?? config('cabinet-esenin-text-check.max_chars', 20000)),
            'provider.languagetool.enabled' => self::boolToStored(! empty($lt['enabled'])),
            'provider.languagetool.url' => rtrim((string) ($lt['url'] ?? 'http://127.0.0.1:8010'), '/'),
            'provider.languagetool.language' => (string) ($lt['language'] ?? 'ru-RU'),
            'provider.languagetool.mother_tongue' => (string) ($lt['mother_tongue'] ?? 'ru-RU'),
            'provider.languagetool.timeout' => (string) (int) ($lt['timeout'] ?? 20),
            'provider.turgenev.enabled' => self::boolToStored(! empty($turgenev['enabled'])),
            'provider.turgenev.url' => (string) ($turgenev['url'] ?? 'https://turgenev.ashmanov.com/'),
            'provider.turgenev.key' => (string) ($turgenev['key'] ?? ''),
            'provider.turgenev.score_blend_percent' => (string) (int) ($turgenev['score_blend_percent'] ?? 100),
            'provider.turgenev.timeout' => (string) (int) ($turgenev['timeout'] ?? 30),
            'provider.opencorpora.enabled' => self::boolToStored(! empty($opencorpora['enabled'])),
            'provider.opencorpora.url' => (string) ($opencorpora['url'] ?? 'https://opencorpora.org/api.php'),
            'provider.opencorpora.timeout' => (string) (int) ($opencorpora['timeout'] ?? 10),
            'learning.enabled' => self::boolToStored(! empty($learning['enabled'])),
            'learning.report_fetch_enabled' => self::boolToStored($learning['report_fetch_enabled'] ?? true),
            'learning.report_timeout' => (string) (int) ($learning['report_timeout'] ?? 25),
            'learning.report_base_url' => rtrim((string) ($learning['report_base_url'] ?? 'https://turgenev.ashmanov.com/'), '/'),
        ];
    }

    public static function moduleInt(string $key, int $fallback = 0): int
    {
        return (int) self::get('module.' . $key, (string) $fallback);
    }

    public static function moduleString(string $key, string $fallback = ''): string
    {
        return (string) self::get('module.' . $key, $fallback);
    }

    public static function moduleBool(string $key, bool $fallback = false): bool
    {
        return self::toBool(self::get('module.' . $key, self::boolToStored($fallback)));
    }

    /**
     * @return array<int, int>
     */
    public static function publicShareTtlDays(): array
    {
        $raw = self::get('module.public_share_ttl_days', '[]');
        $decoded = json_decode((string) $raw, true);

        if (! is_array($decoded) || $decoded === []) {
            return config('cabinet-esenin-text-check.public_share_ttl_days', [30, 90, 180, 365, 0]);
        }

        return array_values(array_map('intval', $decoded));
    }

    /**
     * @return array<string, mixed>
     */
    public static function demoConfig(): array
    {
        $defaults = config('cabinet-esenin-text-check.demo', []);

        return array_merge($defaults, [
            'max_runs_per_day' => (int) self::get('demo.max_runs_per_day', (string) ($defaults['max_runs_per_day'] ?? 3)),
            'max_chars' => (int) self::get('demo.max_chars', (string) ($defaults['max_chars'] ?? 5000)),
            'min_chars' => (int) self::get('demo.min_chars', (string) ($defaults['min_chars'] ?? 100)),
            'full_max_chars' => (int) self::get('demo.full_max_chars', (string) ($defaults['full_max_chars'] ?? self::moduleInt('max_chars', 20000))),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function provider(string $name): array
    {
        $base = config("esenin-external-providers.{$name}", []);
        if (! is_array($base)) {
            $base = [];
        }

        $fields = self::providerFields($name);
        $merged = $base;

        foreach ($fields as $field => $type) {
            $code = "provider.{$name}.{$field}";
            if ($name === 'learning' || $field === 'enabled' && $name === 'learning') {
                continue;
            }

            if (! self::hasStoredValue($code)) {
                continue;
            }

            $raw = self::getRaw($code);
            if ($type === 'bool') {
                $merged[$field] = self::toBool($raw);
            } elseif ($type === 'int') {
                $merged[$field] = (int) $raw;
            } else {
                $merged[$field] = (string) $raw;
            }
        }

        if ($name === 'languagetool' && isset($merged['url'])) {
            $merged['url'] = rtrim((string) $merged['url'], '/');
        }

        return $merged;
    }

    public static function learningEnabled(): bool
    {
        if (self::hasStoredValue('learning.enabled')) {
            return self::toBool(self::getRaw('learning.enabled'));
        }

        return (bool) config('esenin-external-providers.learning.enabled', true);
    }

    /**
     * @return array<string, mixed>
     */
    public static function learningConfig(): array
    {
        $base = config('esenin-external-providers.learning', []);
        if (! is_array($base)) {
            $base = [];
        }

        $base['enabled'] = self::learningEnabled();
        $base['report_fetch_enabled'] = self::learningReportFetchEnabled();
        $base['report_blocks'] = self::learningReportBlocks();
        $base['report_base_url'] = self::get('learning.report_base_url', rtrim((string) ($base['report_base_url'] ?? 'https://turgenev.ashmanov.com/'), '/'));
        $base['report_timeout'] = (int) self::get('learning.report_timeout', (string) ($base['report_timeout'] ?? 25));

        return $base;
    }

    public static function learningReportFetchEnabled(): bool
    {
        if (self::hasStoredValue('learning.report_fetch_enabled')) {
            return self::toBool(self::getRaw('learning.report_fetch_enabled'));
        }

        return (bool) config('esenin-external-providers.learning.report_fetch_enabled', true);
    }

    /**
     * @return array<int, string>
     */
    public static function learningReportBlocks(): array
    {
        $defaults = config('esenin-external-providers.learning.report_blocks', ['style', 'readability']);
        if (! is_array($defaults) || $defaults === []) {
            return ['style', 'readability'];
        }

        return array_values(array_map('strval', $defaults));
    }

    /**
     * @return array<string, string>
     */
    public static function allForAdmin(): array
    {
        $defaults = self::defaults();
        $result = $defaults;

        if (! self::tableReady()) {
            return $result;
        }

        foreach (EseninTextCheckSetting::query()->pluck('value', 'code') as $code => $value) {
            $result[(string) $code] = (string) $value;
        }

        return $result;
    }

    public static function hasSecret(string $code): bool
    {
        return trim((string) self::get($code, '')) !== '';
    }

    public static function set(string $code, $value): void
    {
        if (! self::tableReady()) {
            return;
        }

        EseninTextCheckSetting::query()->updateOrCreate(
            ['code' => $code],
            ['value' => (string) $value]
        );

        self::$cache[$code] = (string) $value;
    }

    /**
     * @param array<string, mixed> $values
     */
    public static function bulkSet(array $values): void
    {
        foreach ($values as $code => $value) {
            self::set($code, $value);
        }
    }

    public static function get(string $code, ?string $fallback = null): string
    {
        $defaults = self::defaults();
        $default = $fallback ?? ($defaults[$code] ?? '');

        if (! self::tableReady()) {
            return (string) $default;
        }

        if (array_key_exists($code, self::$cache)) {
            $cached = self::$cache[$code];

            return $cached !== null ? (string) $cached : (string) $default;
        }

        $stored = EseninTextCheckSetting::query()->where('code', $code)->value('value');
        self::$cache[$code] = $stored;

        return $stored !== null ? (string) $stored : (string) $default;
    }

    private static function getRaw(string $code): ?string
    {
        if (! self::tableReady()) {
            return null;
        }

        if (array_key_exists($code, self::$cache)) {
            return self::$cache[$code];
        }

        $stored = EseninTextCheckSetting::query()->where('code', $code)->value('value');
        self::$cache[$code] = $stored;

        return $stored;
    }

    private static function hasStoredValue(string $code): bool
    {
        return self::getRaw($code) !== null;
    }

    /**
     * @return array<string, string>
     */
    private static function providerFields(string $name): array
    {
        switch ($name) {
            case 'languagetool':
                return [
                    'enabled' => 'bool',
                    'url' => 'string',
                    'language' => 'string',
                    'mother_tongue' => 'string',
                    'timeout' => 'int',
                ];
            case 'turgenev':
                return [
                    'enabled' => 'bool',
                    'url' => 'string',
                    'key' => 'string',
                    'score_blend_percent' => 'int',
                    'timeout' => 'int',
                ];
            case 'opencorpora':
                return [
                    'enabled' => 'bool',
                    'url' => 'string',
                    'timeout' => 'int',
                ];
            default:
                return [];
        }
    }

    private static function boolToStored(bool $value): string
    {
        return $value ? '1' : '0';
    }

    private static function toBool(?string $value): bool
    {
        if ($value === null) {
            return false;
        }

        return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
    }
}
