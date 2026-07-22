<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class SiteAuditPage extends Model
{
    protected $table = 'site_audit_pages';

    protected $fillable = [
        'crawl_id',
        'url',
        'url_hash',
        'final_url',
        'status_code',
        'redirect_chain',
        'size_bytes',
        'content_type',
        'title',
        'title_hash',
        'description',
        'description_hash',
        'h1',
        'h1_count',
        'h2_count',
        'canonical',
        'robots_meta',
        'noindex',
        'word_count',
        'text_len',
        'content_hash',
        'simhash',
        'out_links_json',
        'click_depth',
        'img_count',
        'img_without_alt',
        'unique_img_src_count',
        'strong_count',
        'em_count',
        'nausea_classic',
        'nausea_academic',
        'top_word',
        'top_word_count',
        'top_bigram',
        'top_bigram_count',
        'noindex_text_len',
        'charset',
        'html_storage_key',
        'html_bytes_gz',
    ];

    protected $casts = [
        'redirect_chain' => 'array',
        'out_links_json' => 'array',
        'noindex' => 'boolean',
    ];

    public function crawl()
    {
        return $this->belongsTo(SiteAuditCrawl::class, 'crawl_id');
    }
}
