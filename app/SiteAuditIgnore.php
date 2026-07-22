<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class SiteAuditIgnore extends Model
{
    protected $table = 'site_audit_ignores';

    protected $fillable = [
        'project_id',
        'user_id',
        'code',
        'url_hash',
        'url',
        'note',
    ];

    public function project()
    {
        return $this->belongsTo(SiteAuditProject::class, 'project_id');
    }

    public function isCodeWide(): bool
    {
        return $this->url_hash === '' || $this->url_hash === null;
    }
}
