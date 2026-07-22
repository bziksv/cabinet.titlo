<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class SiteAuditCrawlStat extends Model
{
    protected $table = 'site_audit_crawl_stats';

    protected $fillable = [
        'crawl_id',
        'bucket',
        'value',
    ];

    public function crawl()
    {
        return $this->belongsTo(SiteAuditCrawl::class, 'crawl_id');
    }
}
