<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IndexCheckHistory extends Model
{
    protected $table = 'index_check_histories';

    protected $fillable = [
        'user_id',
        'url',
        'check_yandex',
        'check_google',
        'result',
    ];

    protected $casts = [
        'check_yandex' => 'boolean',
        'check_google' => 'boolean',
        'result' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
