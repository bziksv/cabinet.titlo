<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class UserCompanyLog extends Model
{
    protected $fillable = [
        'user_company_id',
        'user_id',
        'action',
        'company_name',
        'changes',
    ];

    protected $casts = [
        'changes' => 'array',
    ];

    public const ACTION_CREATED = 'created';
    public const ACTION_UPDATED = 'updated';
    public const ACTION_DELETED = 'deleted';

    public function company()
    {
        return $this->belongsTo(UserCompany::class, 'user_company_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function fieldLabels(): array
    {
        return [
            'name' => __('Company name'),
            'inn' => 'ИНН',
            'kpp' => 'КПП',
            'legal_address' => __('Legal address'),
            'postal_address' => __('Postal address'),
            'email' => 'Email',
            'phone' => __('Phone'),
        ];
    }
}
