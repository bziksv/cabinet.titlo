<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class UserCompany extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'inn',
        'kpp',
        'legal_address',
        'postal_address',
        'email',
        'phone',
        'balance',
    ];

    protected $casts = [
        'balance' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function invoices()
    {
        return $this->hasMany(CompanyInvoice::class);
    }

    public function logs()
    {
        return $this->hasMany(UserCompanyLog::class);
    }

    public function balances()
    {
        return $this->hasMany(Balance::class);
    }

    public function label(): string
    {
        $name = trim((string) $this->name);
        $inn = trim((string) $this->inn);

        if ($name !== '' && $inn !== '') {
            return $name . ' · ИНН ' . $inn;
        }

        return $name !== '' ? $name : ('ИНН ' . $inn);
    }

    /** Снимок реквизитов для PDF (не меняется при правке компании). */
    public function toPayerSnapshot(): array
    {
        return [
            'name' => (string) $this->name,
            'inn' => (string) $this->inn,
            'kpp' => (string) ($this->kpp ?? ''),
            'legal_address' => (string) $this->legal_address,
            'postal_address' => (string) ($this->postal_address ?? ''),
            'email' => (string) $this->email,
            'phone' => (string) ($this->phone ?? ''),
        ];
    }
}
