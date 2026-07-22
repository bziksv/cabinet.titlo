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
