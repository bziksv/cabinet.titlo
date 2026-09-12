<?php

namespace App\Services\Billing;

use App\CompanyInvoice;
use App\User;
use Carbon\Carbon;

class CompanyInvoiceNumberService
{
    /**
     * Первый счёт дня: "{userId}"
     * Второй и далее в тот же день: "{userId}/2", "/3", …
     */
    public function nextForUser(User $user, ?Carbon $at = null): string
    {
        $at = $at ?: Carbon::now();
        $base = (string) ((int) $user->id);

        $dayStart = $at->copy()->startOfDay();
        $dayEnd = $at->copy()->endOfDay();

        $count = CompanyInvoice::query()
            ->where('user_id', (int) $user->id)
            ->whereBetween('issued_at', [$dayStart, $dayEnd])
            ->lockForUpdate()
            ->count();

        if ($count === 0) {
            return $base;
        }

        return $base . '/' . ($count + 1);
    }
}
