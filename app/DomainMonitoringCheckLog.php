<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DomainMonitoringCheckLog extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'broken' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(DomainMonitoring::class, 'domain_monitoring_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
