<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class SiteAuditProject extends Model
{
    protected $table = 'site_audit_projects';

    protected $fillable = [
        'user_id',
        'domain',
        'name',
        'settings_json',
    ];

    protected $casts = [
        'settings_json' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function crawls()
    {
        return $this->hasMany(SiteAuditCrawl::class, 'project_id');
    }

    public function setting(string $key, $default = null)
    {
        return data_get($this->settings_json, $key, $default);
    }
}
