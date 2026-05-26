<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TelegramProxy extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'telegram_proxies';

    protected $fillable = [
        'id',
        'label',
        'url',
        'priority',
        'enabled',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'priority' => 'integer',
    ];
}
