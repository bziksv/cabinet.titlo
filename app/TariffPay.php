<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TariffPay extends Model
{
    protected $dates = ['active_to'];
    protected $fillable = [
        'status',
        'class_tariff',
        'class_period',
        'sum',
        'user_company_id',
        'auto_renewed',
        'active_to',
    ];

    protected $casts = [
        'auto_renewed' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    public function company()
    {
        return $this->belongsTo(UserCompany::class, 'user_company_id');
    }
}
