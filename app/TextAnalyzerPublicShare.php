<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class TextAnalyzerPublicShare extends Model
{
    public const TTL_DAYS = 30;

    protected $table = 'text_analyzer_public_shares';

    protected $guarded = [];

    protected $dates = [
        'expires_at',
        'revoked_at',
    ];

    /** @var bool|null */
    protected static $tableAvailable;

    public static function tableAvailable(): bool
    {
        if (self::$tableAvailable === null) {
            try {
                self::$tableAvailable = Schema::hasTable('text_analyzer_public_shares');
            } catch (\Throwable $e) {
                self::$tableAvailable = false;
            }
        }

        return self::$tableAvailable;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive($query)
    {
        return $query
            ->whereNull('revoked_at')
            ->where('expires_at', '>', Carbon::now());
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null
            && $this->expires_at !== null
            && $this->expires_at->isFuture();
    }

    public function publicUrl(): string
    {
        return url('/public/share/text-analyzer/' . $this->token);
    }

    /**
     * @return array{response: array, request: array, url: mixed, meta: array}
     */
    public function decodedPayload(): array
    {
        $data = json_decode((string) $this->payload, true);

        return is_array($data) ? $data : [];
    }

    public function matchesSnapshot(array $snapshot): bool
    {
        return self::snapshotHash($snapshot) === (string) $this->snapshot_hash;
    }

    public static function snapshotHash(array $snapshot): string
    {
        return hash('sha256', json_encode(self::normalizeSnapshot($snapshot)));
    }

    public static function normalizeSnapshot(array $snapshot): array
    {
        return [
            'response' => $snapshot['response'] ?? [],
            'request' => $snapshot['request'] ?? [],
            'url' => $snapshot['url'] ?? null,
        ];
    }

    public static function activeForUser(int $userId): ?self
    {
        if (!self::tableAvailable()) {
            return null;
        }

        return static::where('user_id', $userId)->active()->orderByDesc('id')->first();
    }

    public static function issueForUser(int $userId, array $snapshot, array $meta): ?self
    {
        if (!self::tableAvailable()) {
            return null;
        }

        // «Обновить ссылку» — только для того же снимка отчёта; другие ссылки не трогаем
        static::where('user_id', $userId)
            ->active()
            ->where('snapshot_hash', self::snapshotHash($snapshot))
            ->update(['revoked_at' => Carbon::now()]);

        $payload = [
            'response' => $snapshot['response'] ?? [],
            'request' => $snapshot['request'] ?? [],
            'url' => $snapshot['url'] ?? null,
            'meta' => $meta,
        ];

        return static::create([
            'user_id' => $userId,
            'token' => Str::random(48),
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'snapshot_hash' => self::snapshotHash($snapshot),
            'expires_at' => Carbon::now()->addDays(self::TTL_DAYS),
        ]);
    }

    public static function revokeActiveForUser(int $userId): int
    {
        if (!self::tableAvailable()) {
            return 0;
        }

        return static::where('user_id', $userId)
            ->active()
            ->update(['revoked_at' => Carbon::now()]);
    }

    public static function revokeForUserSnapshot(int $userId, array $snapshot): int
    {
        if (!self::tableAvailable()) {
            return 0;
        }

        return static::where('user_id', $userId)
            ->active()
            ->where('snapshot_hash', self::snapshotHash($snapshot))
            ->update(['revoked_at' => Carbon::now()]);
    }
}
