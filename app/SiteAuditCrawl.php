<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class SiteAuditCrawl extends Model
{
    public const STATUS_QUEUED = 'queued';
    public const STATUS_DISCOVERING = 'discovering';
    public const STATUS_FETCHING = 'fetching';
    public const STATUS_AGGREGATING = 'aggregating';
    public const STATUS_DONE = 'done';
    public const STATUS_FAILED = 'failed';
    public const STATUS_QUEUED_WAIT = 'queued_wait';

    protected $table = 'site_audit_crawls';

    protected $fillable = [
        'project_id',
        'user_id',
        'status',
        'pages_total',
        'pages_fetched',
        'pages_limit',
        'buckets_json',
        'counts_json',
        'progress_json',
        'error',
        'save_html',
        'share_token',
        'share_enabled_at',
        'share_white_label',
        'share_brand_name',
        'share_brand_url',
        'share_brand_logo',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'buckets_json' => 'array',
        'counts_json' => 'array',
        'progress_json' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'share_enabled_at' => 'datetime',
        'share_white_label' => 'boolean',
    ];

    public function project()
    {
        return $this->belongsTo(SiteAuditProject::class, 'project_id');
    }

    public function pages()
    {
        return $this->hasMany(SiteAuditPage::class, 'crawl_id');
    }

    public function findings()
    {
        return $this->hasMany(SiteAuditFinding::class, 'crawl_id');
    }

    public function stats()
    {
        return $this->hasMany(SiteAuditCrawlStat::class, 'crawl_id');
    }

    public function isFinished(): bool
    {
        return in_array($this->status, [self::STATUS_DONE, self::STATUS_FAILED], true);
    }

    public static function statusLabel(?string $status): string
    {
        $map = [
            self::STATUS_QUEUED => 'В очереди',
            self::STATUS_DISCOVERING => 'Сбор URL',
            self::STATUS_FETCHING => 'Сканирование',
            self::STATUS_AGGREGATING => 'Агрегация',
            self::STATUS_DONE => 'Готово',
            self::STATUS_FAILED => 'Ошибка',
            self::STATUS_QUEUED_WAIT => 'Ожидание',
        ];

        return $map[$status] ?? (string) $status;
    }

    public function statusLabelRu(): string
    {
        return self::statusLabel($this->status);
    }

    public function statusCssClass(): string
    {
        if ($this->status === self::STATUS_DONE) {
            return 'done';
        }
        if ($this->status === self::STATUS_FAILED) {
            return 'failed';
        }

        return 'run';
    }

    public function isShared(): bool
    {
        return $this->share_token && $this->share_enabled_at;
    }

    public function publicShareUrl(): ?string
    {
        if (! $this->isShared()) {
            return null;
        }

        return route('site-audit.public.share.view', $this->share_token);
    }

    public function isWhiteLabelShare(): bool
    {
        return (bool) $this->share_white_label;
    }

    /**
     * @return array{enabled:bool,brand_name:?string,brand_url:?string,brand_logo_url:?string}
     */
    public function whiteLabelMeta(): array
    {
        $name = is_string($this->share_brand_name) ? trim($this->share_brand_name) : '';
        $url = is_string($this->share_brand_url) ? trim($this->share_brand_url) : '';
        if ($url !== '' && ! preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }

        $logoUrl = null;
        $logo = is_string($this->share_brand_logo) ? trim($this->share_brand_logo) : '';
        if ($logo !== '' && \Illuminate\Support\Facades\Storage::disk('public')->exists($logo)) {
            $logoUrl = asset('storage/' . ltrim($logo, '/'));
        }

        return [
            'enabled' => $this->isWhiteLabelShare(),
            'brand_name' => $name !== '' ? mb_substr($name, 0, 120) : null,
            'brand_url' => $url !== '' ? mb_substr($url, 0, 255) : null,
            'brand_logo_url' => $logoUrl,
        ];
    }

    public function clearWhiteLabelLogo(): void
    {
        $logo = is_string($this->share_brand_logo) ? trim($this->share_brand_logo) : '';
        if ($logo !== '') {
            try {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($logo);
            } catch (\Throwable $e) {
                // ignore
            }
        }
        $this->share_brand_logo = null;
    }

    /**
     * Битый UTF-8 (часто из monitoring.query) ломал aggregate → краул зависал в «Агрегация».
     */
    public function setProgressJsonAttribute($value): void
    {
        if ($value === null) {
            $this->attributes['progress_json'] = null;

            return;
        }
        if (! is_array($value)) {
            $value = (array) $value;
        }
        $value = \App\Services\SiteAudit\SiteAuditUtf8::scrub($value);
        $flags = JSON_UNESCAPED_UNICODE;
        if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
            $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
        }
        $this->attributes['progress_json'] = json_encode($value, $flags);
    }
}
