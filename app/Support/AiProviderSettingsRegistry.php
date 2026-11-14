<?php

namespace App\Support;

use App\AiProviderSetting;
use Illuminate\Support\Facades\Schema;

class AiProviderSettingsRegistry
{
    /**
     * @return array{default_provider: string, module_providers: array<string, string>, providers: array<string, array>, modules: array<string, array>}
     */
    public static function forAdmin(): array
    {
        $cfg = config('cabinet-ai-providers', []);
        $providers = is_array($cfg['providers'] ?? null) ? $cfg['providers'] : [];
        $modules = is_array($cfg['modules'] ?? null) ? $cfg['modules'] : [];
        $default = (string) ($cfg['default_provider'] ?? 'plusvibeapi');
        $moduleMap = [];

        foreach (array_keys($modules) as $code) {
            $moduleMap[$code] = $default;
        }

        $row = self::model();
        if ($row !== null) {
            if (is_string($row->default_provider) && $row->default_provider !== '') {
                $default = $row->default_provider;
            }
            $saved = $row->module_providers;
            if (is_array($saved)) {
                foreach ($saved as $code => $provider) {
                    if (isset($modules[$code]) && isset($providers[$provider])) {
                        $moduleMap[$code] = $provider;
                    }
                }
            }
        }

        $providerStatus = [];
        foreach ($providers as $code => $meta) {
            $token = self::tokenForProvider($code);
            $providerStatus[$code] = [
                'title' => (string) ($meta['title'] ?? $code),
                'hint' => (string) ($meta['hint'] ?? ''),
                'token_set' => $token !== null && $token !== '',
                'token_masked' => self::maskSecret($token),
                'model' => (string) config((string) ($meta['model'] ?? ''), ''),
                'fallback_model' => ($meta['fallback_model'] ?? null)
                    ? (string) config((string) $meta['fallback_model'], '')
                    : null,
                'base_uri' => self::baseUriForProvider($code),
            ];
        }

        return [
            'default_provider' => $default,
            'module_providers' => $moduleMap,
            'providers' => $providerStatus,
            'modules' => $modules,
            'updated_at' => optional($row->updated_at ?? null)->toDateTimeString(),
        ];
    }

    /**
     * @param array{default_provider?: string, module_providers?: array<string, string>} $data
     */
    public static function save(array $data): void
    {
        $cfg = config('cabinet-ai-providers', []);
        $providers = is_array($cfg['providers'] ?? null) ? $cfg['providers'] : [];
        $modules = is_array($cfg['modules'] ?? null) ? $cfg['modules'] : [];

        $default = (string) ($data['default_provider'] ?? ($cfg['default_provider'] ?? 'plusvibeapi'));
        if (! isset($providers[$default])) {
            $default = (string) ($cfg['default_provider'] ?? 'plusvibeapi');
        }

        $map = [];
        $incoming = is_array($data['module_providers'] ?? null) ? $data['module_providers'] : [];
        foreach (array_keys($modules) as $code) {
            $provider = (string) ($incoming[$code] ?? $default);
            if (! isset($providers[$provider])) {
                $provider = $default;
            }
            $map[$code] = $provider;
        }

        if (! Schema::hasTable('ai_provider_settings')) {
            return;
        }

        $row = self::model();
        if ($row === null) {
            $row = new AiProviderSetting();
            $row->id = AiProviderSetting::PRIMARY_ID;
        }
        $row->default_provider = $default;
        $row->module_providers = $map;
        $row->save();
    }

    public static function providerForModule(string $module): string
    {
        $cfg = config('cabinet-ai-providers', []);
        $default = (string) ($cfg['default_provider'] ?? 'plusvibeapi');
        $row = self::model();
        if ($row === null) {
            return $default;
        }
        $map = $row->module_providers;
        if (is_array($map) && isset($map[$module]) && is_string($map[$module]) && $map[$module] !== '') {
            return $map[$module];
        }
        if (is_string($row->default_provider) && $row->default_provider !== '') {
            return $row->default_provider;
        }

        return $default;
    }

    public static function tokenForProvider(string $provider): ?string
    {
        $meta = config('cabinet-ai-providers.providers.' . $provider);
        if (! is_array($meta)) {
            return null;
        }
        $key = (string) ($meta['config_token'] ?? '');
        if ($key === '') {
            return null;
        }
        $token = config($key);
        if (! is_string($token) || trim($token) === '') {
            return null;
        }

        return trim($token);
    }

    public static function baseUriForProvider(string $provider): string
    {
        $meta = config('cabinet-ai-providers.providers.' . $provider);
        if (! is_array($meta)) {
            return 'https://api.deepseek.com';
        }
        $cfgKey = $meta['base_uri'] ?? null;
        if (is_string($cfgKey) && $cfgKey !== '') {
            $uri = config($cfgKey);
            if (is_string($uri) && trim($uri) !== '') {
                return rtrim(trim($uri), '/');
            }
        }
        if ($provider === 'plusvibeapi') {
            return 'https://plusvibeapi.ru/v1';
        }

        return 'https://api.deepseek.com';
    }

    /**
     * @return list<string>
     */
    public static function modelsForProvider(string $provider): array
    {
        $meta = config('cabinet-ai-providers.providers.' . $provider);
        $models = [];
        if (is_array($meta)) {
            $modelKey = $meta['model'] ?? null;
            if (is_string($modelKey) && $modelKey !== '') {
                $m = config($modelKey);
                if (is_string($m) && trim($m) !== '') {
                    $models[] = trim($m);
                }
            }
            $fbKey = $meta['fallback_model'] ?? null;
            if (is_string($fbKey) && $fbKey !== '') {
                $fb = config($fbKey);
                if (is_string($fb) && trim($fb) !== '' && ! in_array(trim($fb), $models, true)) {
                    $models[] = trim($fb);
                }
            }
        }
        if ($models === []) {
            $models[] = $provider === 'plusvibeapi' ? 'deepseek-v4.1-flash-1017' : 'deepseek-chat';
        }

        return $models;
    }

    public static function model(): ?AiProviderSetting
    {
        if (! Schema::hasTable('ai_provider_settings')) {
            return null;
        }

        return AiProviderSetting::query()->find(AiProviderSetting::PRIMARY_ID);
    }

    protected static function maskSecret(?string $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }
        $len = mb_strlen($value);
        if ($len <= 8) {
            return str_repeat('•', $len);
        }

        return mb_substr($value, 0, 4) . str_repeat('•', max(4, $len - 8)) . mb_substr($value, -4);
    }
}
