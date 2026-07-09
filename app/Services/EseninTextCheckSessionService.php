<?php

namespace App\Services;

use App\EseninTextCheckSession;
use App\EseninTextCheckVersion;
use Illuminate\Support\Facades\Schema;

class EseninTextCheckSessionService
{
    public static function tablesReady(): bool
    {
        return Schema::hasTable('esenin_text_check_sessions')
            && Schema::hasTable('esenin_text_check_versions');
    }

    public static function maxVersionsPerSession(): int
    {
        return max(1, (int) config('cabinet-esenin-text-check.limits.max_versions_per_session', 3));
    }

    public static function maxSavedSessions(): int
    {
        return max(1, (int) config('cabinet-esenin-text-check.limits.max_saved_sessions', 50));
    }

    public static function findSessionForUser(int $sessionId, int $userId): ?EseninTextCheckSession
    {
        if (! self::tablesReady()) {
            return null;
        }

        return EseninTextCheckSession::query()
            ->where('id', $sessionId)
            ->where('user_id', $userId)
            ->first();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function listSessionsForUser(int $userId, int $limit = 30): array
    {
        if (! self::tablesReady()) {
            return [];
        }

        return EseninTextCheckSession::query()
            ->where('user_id', $userId)
            ->orderByDesc('updated_at')
            ->limit(max(1, $limit))
            ->get()
            ->map(static function (EseninTextCheckSession $session) {
                $latest = $session->versions()->orderByDesc('id')->first();

                return [
                    'id' => (int) $session->id,
                    'name' => $session->name,
                    'source' => $session->source,
                    'updated_at' => optional($session->updated_at)->format('c'),
                    'updated_at_label' => optional($session->updated_at)->format('d.m.Y H:i'),
                    'versions_count' => $session->versions()->count(),
                    'latest_version' => $latest ? self::versionMeta($latest) : null,
                ];
            })
            ->values()
            ->all();
    }

    public static function defaultName(string $text): string
    {
        $plain = preg_replace('/\s+/u', ' ', trim(strip_tags($text))) ?? '';
        if ($plain !== '') {
            if (mb_strlen($plain) > 40) {
                return mb_substr($plain, 0, 40) . '…';
            }

            return $plain;
        }

        return 'Проверка от ' . now()->format('d.m.Y H:i');
    }

    /**
     * @param array{
     *     session_id?: int|null,
     *     name?: ?string,
     *     text: string,
     *     source?: string,
     *     source_url?: ?string,
     *     tbclass?: ?string,
     *     result?: ?array,
     *     is_check?: bool
     * } $payload
     * @return array<string, mixed>
     */
    public static function saveDraft(int $userId, array $payload): array
    {
        if (! self::tablesReady()) {
            throw new \RuntimeException('Хранение версий временно недоступно');
        }

        $text = trim((string) ($payload['text'] ?? ''));
        if ($text === '') {
            throw new \InvalidArgumentException('Текст для сохранения пустой');
        }

        $source = (string) ($payload['source'] ?? 'text');
        if (! in_array($source, ['text', 'url'], true)) {
            $source = 'text';
        }

        $sessionId = isset($payload['session_id']) ? (int) $payload['session_id'] : 0;
        $session = $sessionId > 0 ? self::findSessionForUser($sessionId, $userId) : null;

        if ($session === null) {
            if (EseninTextCheckSession::query()->where('user_id', $userId)->count() >= self::maxSavedSessions()) {
                throw new \InvalidArgumentException(sprintf(
                    'Достигнут лимит сохранённых заданий (%d). Удалите старые или увеличьте тариф.',
                    self::maxSavedSessions()
                ));
            }

            $session = EseninTextCheckSession::query()->create([
                'user_id' => $userId,
                'name' => self::normalizeName((string) ($payload['name'] ?? ''), $text),
                'source' => $source,
                'source_url' => self::nullableString($payload['source_url'] ?? null),
                'tbclass' => self::nullableString($payload['tbclass'] ?? null),
            ]);
        } else {
            $name = self::normalizeName((string) ($payload['name'] ?? ''), $text);
            $session->fill([
                'name' => $name !== '' ? $name : $session->name,
                'source' => $source,
                'source_url' => self::nullableString($payload['source_url'] ?? $session->source_url),
                'tbclass' => self::nullableString($payload['tbclass'] ?? $session->tbclass),
            ]);
            $session->save();
        }

        $version = self::addVersion(
            $session,
            $text,
            isset($payload['result']) && is_array($payload['result']) ? $payload['result'] : null,
            ! empty($payload['is_check'])
        );

        $session->touch();

        return self::sessionPayload($session->fresh(), $version);
    }

    /**
     * @return array<string, mixed>
     */
    public static function sessionPayload(EseninTextCheckSession $session, ?EseninTextCheckVersion $activeVersion = null): array
    {
        $versions = $session->versions()
            ->orderByDesc('id')
            ->limit(self::maxVersionsPerSession())
            ->get();

        if ($activeVersion === null && $versions->isNotEmpty()) {
            $activeVersion = $versions->first();
        }

        return [
            'session_id' => (int) $session->id,
            'name' => $session->name,
            'source' => $session->source,
            'source_url' => $session->source_url,
            'tbclass' => $session->tbclass,
            'max_versions' => self::maxVersionsPerSession(),
            'versions' => $versions->map(static function (EseninTextCheckVersion $version) {
                return self::versionMeta($version);
            })->values()->all(),
            'active_version' => $activeVersion ? self::versionPayload($activeVersion) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function versionMeta(EseninTextCheckVersion $version): array
    {
        return [
            'id' => (int) $version->id,
            'created_at' => optional($version->created_at)->format('c'),
            'created_at_label' => optional($version->created_at)->format('d.m.Y H:i'),
            'risk_score' => $version->risk_score,
            'risk_level' => $version->risk_level,
            'is_check' => (bool) $version->is_check,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function versionPayload(EseninTextCheckVersion $version): array
    {
        $result = null;
        if (! empty($version->result_json)) {
            $decoded = json_decode($version->result_json, true);
            if (is_array($decoded)) {
                $result = $decoded;
            }
        }

        return array_merge(self::versionMeta($version), [
            'text' => $version->text,
            'result' => $result,
        ]);
    }

    private static function addVersion(
        EseninTextCheckSession $session,
        string $text,
        ?array $result,
        bool $isCheck
    ): EseninTextCheckVersion {
        if (! $isCheck) {
            $latest = $session->versions()->orderByDesc('id')->first();
            if ($latest && ! $latest->is_check && $latest->text === $text) {
                return $latest;
            }
        }

        $max = self::maxVersionsPerSession();
        $existingCount = $session->versions()->count();

        if ($existingCount >= $max) {
            $oldestIds = $session->versions()
                ->orderBy('id')
                ->limit($existingCount - $max + 1)
                ->pluck('id');

            if ($oldestIds->isNotEmpty()) {
                EseninTextCheckVersion::query()->whereIn('id', $oldestIds)->delete();
            }
        }

        return $session->versions()->create([
            'text' => $text,
            'result_json' => $result !== null ? json_encode($result, JSON_UNESCAPED_UNICODE) : null,
            'risk_score' => $result !== null ? (int) ($result['risk'] ?? 0) : null,
            'risk_level' => $result !== null ? (string) ($result['level'] ?? '') : null,
            'is_check' => $isCheck,
        ]);
    }

    private static function normalizeName(string $name, string $text): string
    {
        $name = trim($name);
        if ($name === '') {
            return self::defaultName($text);
        }

        if (mb_strlen($name) > 120) {
            return mb_substr($name, 0, 120);
        }

        return $name;
    }

    private static function nullableString($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
