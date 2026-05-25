<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class HtmlEditorPublicShare extends Model
{
    public const TTL_DAYS = 30;

    protected $table = 'html_editor_public_shares';

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
                self::$tableAvailable = Schema::hasTable('html_editor_public_shares');
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
        return url('/public/share/html-editor/' . $this->token);
    }

    /**
     * @return array{html: string, meta: array}
     */
    public function decodedPayload(): array
    {
        $data = json_decode((string) $this->payload, true);

        return is_array($data) ? $data : ['html' => '', 'meta' => []];
    }

    public static function contentHash(string $html): string
    {
        return hash('sha256', $html);
    }

    public static function activeForDescription(int $descriptionId, int $userId): ?self
    {
        if (!self::tableAvailable()) {
            return null;
        }

        return static::query()
            ->where('description_id', $descriptionId)
            ->where('user_id', $userId)
            ->active()
            ->orderByDesc('id')
            ->first();
    }

    public static function issueForDescription(int $userId, int $descriptionId, string $html, array $meta): ?self
    {
        if (!self::tableAvailable()) {
            return null;
        }

        static::query()
            ->where('description_id', $descriptionId)
            ->where('user_id', $userId)
            ->active()
            ->update(['revoked_at' => Carbon::now()]);

        return static::create([
            'user_id' => $userId,
            'description_id' => $descriptionId,
            'token' => Str::random(48),
            'payload' => json_encode([
                'html' => $html,
                'meta' => $meta,
            ], JSON_UNESCAPED_UNICODE),
            'content_hash' => self::contentHash($html),
            'expires_at' => Carbon::now()->addDays(self::TTL_DAYS),
        ]);
    }

    public static function revokeForDescription(int $userId, int $descriptionId): int
    {
        if (!self::tableAvailable()) {
            return 0;
        }

        return static::query()
            ->where('description_id', $descriptionId)
            ->where('user_id', $userId)
            ->active()
            ->update(['revoked_at' => Carbon::now()]);
    }

    public static function registerUrl(): string
    {
        $query = http_build_query([
            'module' => 'html-editor',
            'from' => 'html-editor-public-share',
        ]);

        return route('register') . '?' . $query;
    }
}
