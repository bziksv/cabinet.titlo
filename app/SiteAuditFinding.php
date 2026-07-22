<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class SiteAuditFinding extends Model
{
    protected $table = 'site_audit_findings';

    protected $fillable = [
        'crawl_id',
        'code',
        'severity',
        'url',
        'url_hash',
        'meta_json',
    ];

    protected $casts = [
        'meta_json' => 'array',
    ];

    public function crawl()
    {
        return $this->belongsTo(SiteAuditCrawl::class, 'crawl_id');
    }

    public function setMetaJsonAttribute($value): void
    {
        if ($value === null) {
            $this->attributes['meta_json'] = null;

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
        $this->attributes['meta_json'] = json_encode($value, $flags);
    }
}
