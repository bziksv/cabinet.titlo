<?php

namespace App;

use App\Support\DomainInformationPublicShareTtl;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class DomainInformationPublicShare extends Model
{
    protected $table = 'domain_information_public_shares';

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
                self::$tableAvailable = Schema::hasTable('domain_information_public_shares');
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

    public function project(): BelongsTo
    {
        return $this->belongsTo(DomainInformation::class, 'domain_information_id');
    }

    public function scopeActive($query)
    {
        return $query
            ->whereNull('revoked_at')
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', Carbon::now());
            });
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function isUnlimited(): bool
    {
        return $this->expires_at === null;
    }

    public function expiresLabel(): string
    {
        if ($this->isUnlimited()) {
            return (string) __('Domain information share ttl unlimited');
        }

        return __('Valid until') . ' ' . $this->expires_at->format('d.m.Y H:i');
    }

    public function ttlDaysFromPayload(): int
    {
        $meta = $this->decodedPayload()['meta'] ?? [];
        if (isset($meta['ttl_days'])) {
            return DomainInformationPublicShareTtl::normalize($meta['ttl_days']);
        }

        return $this->isUnlimited() ? DomainInformationPublicShareTtl::UNLIMITED : 30;
    }

    public function publicUrl(): string
    {
        return url('/public/share/domain-information/' . $this->token);
    }

    /**
     * @return array{report: array, meta: array}
     */
    public function decodedPayload(): array
    {
        $data = json_decode((string) $this->payload, true);

        if (!is_array($data)) {
            return ['report' => [], 'meta' => []];
        }

        return [
            'report' => is_array($data['report'] ?? null) ? $data['report'] : [],
            'meta' => is_array($data['meta'] ?? null) ? $data['meta'] : [],
        ];
    }

    public static function reportHash(array $report): string
    {
        return hash('sha256', json_encode([
            'project_id' => $report['project']['id'] ?? 0,
            'total_checks' => $report['summary']['total_checks'] ?? 0,
            'last_check' => $report['summary']['last_check'] ?? null,
            'currently_broken' => $report['summary']['currently_broken'] ?? null,
        ]));
    }

    public static function activeForProject(int $projectId, int $userId): ?self
    {
        if (!self::tableAvailable()) {
            return null;
        }

        return static::query()
            ->where('domain_information_id', $projectId)
            ->where('user_id', $userId)
            ->active()
            ->orderByDesc('id')
            ->first();
    }

    public static function issueForProject(
        int $userId,
        int $projectId,
        array $report,
        array $meta,
        int $ttlDays = 30
    ): ?self {
        if (!self::tableAvailable()) {
            return null;
        }

        $ttlDays = DomainInformationPublicShareTtl::normalize($ttlDays);
        $hash = self::reportHash($report);

        static::query()
            ->where('domain_information_id', $projectId)
            ->where('user_id', $userId)
            ->active()
            ->where('snapshot_hash', $hash)
            ->update(['revoked_at' => Carbon::now()]);

        return static::create([
            'user_id' => $userId,
            'domain_information_id' => $projectId,
            'token' => Str::random(48),
            'payload' => json_encode([
                'report' => $report,
                'meta' => array_merge($meta, ['ttl_days' => $ttlDays]),
            ], JSON_UNESCAPED_UNICODE),
            'snapshot_hash' => $hash,
            'expires_at' => DomainInformationPublicShareTtl::resolveExpiresAt($ttlDays),
        ]);
    }

    public static function revokeForProject(int $userId, int $projectId): int
    {
        if (!self::tableAvailable()) {
            return 0;
        }

        return static::query()
            ->where('domain_information_id', $projectId)
            ->where('user_id', $userId)
            ->active()
            ->update(['revoked_at' => Carbon::now()]);
    }

    public static function registerUrl(): string
    {
        $query = http_build_query([
            'module' => 'domain-information',
            'from' => 'domain-information-public-share',
        ]);

        return route('register') . '?' . $query;
    }
}
