<?php

namespace App;

use App\Support\HomeUserSites;
use App\Support\SchemaMemo;
use Illuminate\Database\Eloquent\Model;

class GoogleSearchConsoleDomainProperty extends Model
{
    protected $table = 'google_search_console_domain_properties';

    protected $fillable = [
        'user_id',
        'domain',
        'property_id',
        'property_url',
        'verified',
    ];

    protected $casts = [
        'verified' => 'boolean',
    ];

    public static function tableReady(): bool
    {
        return SchemaMemo::hasTable('google_search_console_domain_properties');
    }

    /**
     * @return \Illuminate\Support\Collection<int, self>
     */
    public static function forUser(int $userId)
    {
        if ($userId < 1 || !self::tableReady()) {
            return collect();
        }

        return self::query()
            ->where('user_id', $userId)
            ->orderBy('domain')
            ->get();
    }

    public static function bind(
        int $userId,
        string $rawDomain,
        string $propertyId,
        ?string $propertyUrl = null,
        bool $verified = false
    ): ?self {
        if ($userId < 1 || !self::tableReady()) {
            return null;
        }

        $domain = HomeUserSites::normalizeDomain($rawDomain);
        $propertyId = trim($propertyId);
        if ($domain === '' || $propertyId === '') {
            return null;
        }

        return self::query()->updateOrCreate(
            ['user_id' => $userId, 'domain' => $domain],
            [
                'property_id' => $propertyId,
                'property_url' => $propertyUrl,
                'verified' => $verified,
            ]
        );
    }

    public static function unbind(int $userId, string $rawDomain): bool
    {
        if ($userId < 1 || !self::tableReady()) {
            return false;
        }

        $domain = HomeUserSites::normalizeDomain($rawDomain);
        if ($domain === '') {
            return false;
        }

        return (bool) self::query()
            ->where('user_id', $userId)
            ->where('domain', $domain)
            ->delete();
    }
}
