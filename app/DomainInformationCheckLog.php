<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DomainInformationCheckLog extends Model
{
    public $timestamps = false;

    protected $table = 'domain_information_check_logs';

    protected $guarded = [];

    protected $dates = ['created_at'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(DomainInformation::class, 'domain_information_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
