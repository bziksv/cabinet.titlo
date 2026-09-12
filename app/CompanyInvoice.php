<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CompanyInvoice extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'user_id',
        'user_company_id',
        'number',
        'amount',
        'status',
        'service_title',
        'payer_snapshot',
        'issued_at',
        'paid_at',
        'credited_by_admin_id',
        'act_number',
        'act_issued_at',
        'invoice_pdf_path',
        'act_pdf_path',
    ];

    protected $casts = [
        'amount' => 'integer',
        'payer_snapshot' => 'array',
        'issued_at' => 'datetime',
        'paid_at' => 'datetime',
        'act_issued_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function company()
    {
        return $this->belongsTo(UserCompany::class, 'user_company_id');
    }

    public function creditedByAdmin()
    {
        return $this->belongsTo(User::class, 'credited_by_admin_id');
    }

    public function balanceRows()
    {
        return $this->hasMany(Balance::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function payerLine(): string
    {
        $s = is_array($this->payer_snapshot) ? $this->payer_snapshot : [];
        $parts = [];
        if (!empty($s['name'])) {
            $parts[] = $s['name'];
        }
        if (!empty($s['legal_address'])) {
            $parts[] = $s['legal_address'];
        }
        if (!empty($s['inn'])) {
            $parts[] = 'ИНН ' . $s['inn'];
        }
        if (!empty($s['kpp'])) {
            $parts[] = 'КПП ' . $s['kpp'];
        }
        if (!empty($s['phone'])) {
            $parts[] = 'тел. ' . $s['phone'];
        }
        if (!empty($s['email'])) {
            $parts[] = $s['email'];
        }

        return implode(', ', $parts);
    }
}
