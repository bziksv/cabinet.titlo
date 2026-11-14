<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class AiProviderSetting extends Model
{
    public const PRIMARY_ID = 1;

    protected $table = 'ai_provider_settings';

    protected $guarded = [];

    protected $casts = [
        'module_providers' => 'array',
    ];
}
