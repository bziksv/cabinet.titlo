<?php

namespace App\Services\Finance;

use App\Balance;
use App\CompanyInvoice;
use App\Services\Billing\CompanyInvoicePdfService;
use App\Support\UserSmartSearch;
use App\User;
use App\UserCompany;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FinanceAdminService
{
    /**
     * @return array{
     *     top_up_sum: int,
     *     top_up_count: int,
     *     expense_sum: int,
     *     expense_count: int,
     *     failed_sum: int,
     *     failed_count: int,
     *     users_with_balance: int,
     *     total_user_balance: int,
     *     operations_total: int,
     *     unique_payers: int
     * }
     */
    public function summary(bool $excludeAdmins = true): array
    {
        $query = DB::table('balances');
        $this->applyExcludeAdminsToBalancesQuery($query, $excludeAdmins);

        $rows = (clone $query)
            ->selectRaw('status, COUNT(*) as cnt, COALESCE(SUM(sum), 0) as total')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $topUp = $rows->get(1);
        $expense = $rows->get(2);
        $failed = $rows->get(0);

        return [
            'top_up_sum' => (int) ($topUp->total ?? 0),
            'top_up_count' => (int) ($topUp->cnt ?? 0),
            'expense_sum' => (int) ($expense->total ?? 0),
            'expense_count' => (int) ($expense->cnt ?? 0),
            'failed_sum' => (int) ($failed->total ?? 0),
            'failed_count' => (int) ($failed->cnt ?? 0),
            'users_with_balance' => (int) DB::table('users')->where('balance', '>', 0)->count(),
            'total_user_balance' => (int) DB::table('users')->sum('balance'),
            'operations_total' => (int) (clone $query)->count(),
            'unique_payers' => (int) (clone $query)->where('status', 1)->distinct('user_id')->count('user_id'),
        ];
    }

    /**
     * @return list<array{
     *     user_id: int,
     *     name: string,
     *     email: string,
     *     balance: int,
     *     top_up_sum: int,
     *     top_up_count: int,
     *     expense_sum: int,
     *     expense_count: int,
     *     last_at: ?string
     * }>
     */
    public function topUsers(int $limit = 15, bool $excludeAdmins = true): array
    {
        $query = DB::table('balances as b')
            ->join('users as u', 'u.id', '=', 'b.user_id')
            ->whereIn('b.status', [1, 2]);
        $this->applyExcludeAdminsToUserQuery($query, 'u.id', $excludeAdmins);

        $rows = $query
            ->groupBy('u.id', 'u.name', 'u.last_name', 'u.email', 'u.balance')
            ->selectRaw('u.id as user_id')
            ->selectRaw("TRIM(CONCAT(COALESCE(u.name, ''), ' ', COALESCE(u.last_name, ''))) as name")
            ->selectRaw('u.email as email')
            ->selectRaw('u.balance as balance')
            ->selectRaw('SUM(CASE WHEN b.status = 1 THEN b.sum ELSE 0 END) as top_up_sum')
            ->selectRaw('SUM(CASE WHEN b.status = 1 THEN 1 ELSE 0 END) as top_up_count')
            ->selectRaw('SUM(CASE WHEN b.status = 2 THEN b.sum ELSE 0 END) as expense_sum')
            ->selectRaw('SUM(CASE WHEN b.status = 2 THEN 1 ELSE 0 END) as expense_count')
            ->selectRaw('MAX(b.created_at) as last_at')
            ->orderByDesc('top_up_sum')
            ->limit($limit)
            ->get();

        return $rows->map(static function ($row) {
            return [
                'user_id' => (int) $row->user_id,
                'name' => trim((string) $row->name) !== '' ? trim((string) $row->name) : (string) $row->email,
                'email' => (string) $row->email,
                'balance' => (int) $row->balance,
                'top_up_sum' => (int) $row->top_up_sum,
                'top_up_count' => (int) $row->top_up_count,
                'expense_sum' => (int) $row->expense_sum,
                'expense_count' => (int) $row->expense_count,
                'last_at' => $row->last_at,
            ];
        })->all();
    }

    /**
     * @return array{labels: list<string>, top_up: list<int>, expense: list<int>}
     */
    public function monthlyChart(bool $excludeAdmins = true): array
    {
        $query = DB::table('balances')
            ->whereIn('status', [1, 2]);
        $this->applyExcludeAdminsToBalancesQuery($query, $excludeAdmins);

        $rows = $query
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month")
            ->selectRaw('status')
            ->selectRaw('COALESCE(SUM(sum), 0) as total')
            ->groupBy('month', 'status')
            ->orderBy('month')
            ->get();

        $months = $rows->pluck('month')->unique()->sort()->values();

        $topUpByMonth = $rows->where('status', 1)->keyBy('month');
        $expenseByMonth = $rows->where('status', 2)->keyBy('month');

        $labels = [];
        $topUp = [];
        $expense = [];

        foreach ($months as $month) {
            $labels[] = $this->formatMonthLabel((string) $month);
            $topUp[] = (int) ($topUpByMonth->get($month)->total ?? 0);
            $expense[] = (int) ($expenseByMonth->get($month)->total ?? 0);
        }

        return [
            'labels' => $labels,
            'top_up' => $topUp,
            'expense' => $expense,
        ];
    }

    /**
     * @return list<array{id: int, text: string, email: string, balance: int, name: string}>
     */
    public function searchUsersForSelect(string $q): array
    {
        $q = trim($q);
        if (mb_strlen($q) < 2) {
            return [];
        }

        $query = User::query()
            ->select(['id', 'name', 'last_name', 'email', 'balance']);

        UserSmartSearch::apply($query, $q, 'users');

        return $query
            ->orderByDesc('users.id')
            ->limit(20)
            ->get()
            ->map(static function (User $user) {
                $name = trim(($user->name ?? '') . ' ' . ($user->last_name ?? ''));
                if ($name === '') {
                    $name = (string) $user->email;
                }

                return [
                    'id' => (int) $user->id,
                    'text' => sprintf(
                        '#%d · %s · %s · %s',
                        $user->id,
                        $name,
                        $user->email,
                        self::formatMoney((int) $user->balance)
                    ),
                    'email' => (string) $user->email,
                    'balance' => (int) $user->balance,
                    'name' => $name,
                ];
            })
            ->values()
            ->all();
    }

    public function creditUser(int $userId, int $sum, User $admin, ?string $comment = null): Balance
    {
        return DB::transaction(function () use ($userId, $sum, $admin, $comment) {
            /** @var User $user */
            $user = User::query()->lockForUpdate()->findOrFail($userId);

            $balance = $user->balances()->create([
                'sum' => $sum,
                'status' => 1,
                'source' => self::manualCreditSource($admin, $comment),
            ]);

            $balance->counting = 1;
            $balance->save();

            $user->increment('balance', $sum);

            return $balance->fresh(['user']);
        });
    }

    /**
     * Зачисление на баланс компании по неоплаченному счёту + акт PDF.
     */
    public function creditCompanyInvoice(CompanyInvoice $invoice, User $admin, ?string $comment = null): Balance
    {
        return DB::transaction(function () use ($invoice, $admin, $comment) {
            /** @var CompanyInvoice $locked */
            $locked = CompanyInvoice::query()->lockForUpdate()->findOrFail($invoice->id);
            if (!$locked->isPending()) {
                throw ValidationException::withMessages([
                    'company_invoice_id' => ['Счёт уже оплачен или отменён.'],
                ]);
            }

            /** @var UserCompany $company */
            $company = UserCompany::query()->lockForUpdate()->findOrFail($locked->user_company_id);
            $sum = (int) $locked->amount;

            $locked->status = CompanyInvoice::STATUS_PAID;
            $locked->paid_at = Carbon::now();
            $locked->credited_by_admin_id = (int) $admin->id;
            $locked->act_number = $locked->number;
            $locked->act_issued_at = Carbon::now();
            $locked->save();

            $source = self::manualCreditSource($admin, $comment);
            $source .= ' · счёт ' . $locked->number . ' · ' . $company->name;

            $balance = Balance::query()->create([
                'user_id' => (int) $locked->user_id,
                'user_company_id' => (int) $company->id,
                'company_invoice_id' => (int) $locked->id,
                'sum' => $sum,
                'status' => 1,
                'source' => $source,
                'counting' => 1,
            ]);

            $company->increment('balance', $sum);

            app(CompanyInvoicePdfService::class)->storeActPdf($locked->fresh());

            return $balance->fresh(['user', 'company', 'companyInvoice']);
        });
    }

    public function companiesForSelect(int $userId): array
    {
        return UserCompany::query()
            ->where('user_id', $userId)
            ->orderBy('name')
            ->get()
            ->map(static function (UserCompany $c) {
                return [
                    'id' => (int) $c->id,
                    'text' => $c->label() . ' · ' . self::formatMoney((int) $c->balance),
                    'balance' => (int) $c->balance,
                ];
            })
            ->values()
            ->all();
    }

    public function pendingInvoicesForSelect(int $companyId, int $userId): array
    {
        return CompanyInvoice::query()
            ->where('user_company_id', $companyId)
            ->where('user_id', $userId)
            ->where('status', CompanyInvoice::STATUS_PENDING)
            ->orderByDesc('id')
            ->get()
            ->map(static function (CompanyInvoice $inv) {
                return [
                    'id' => (int) $inv->id,
                    'text' => '№ ' . $inv->number . ' · ' . self::formatMoney((int) $inv->amount),
                    'amount' => (int) $inv->amount,
                ];
            })
            ->values()
            ->all();
    }

    public static function manualCreditSource(User $admin, ?string $comment = null): string
    {
        $adminName = trim(($admin->name ?? '') . ' ' . ($admin->last_name ?? ''));
        if ($adminName === '') {
            $adminName = (string) $admin->email;
        }

        $source = sprintf(
            'Ручное начисление · админ #%d %s',
            (int) $admin->id,
            $adminName
        );

        $comment = trim((string) $comment);
        if ($comment !== '') {
            $source .= ' · ' . $comment;
        }

        return $source;
    }

    /**
     * @param array{status?: string, q?: string, period?: string} $filters
     */
    public function transactions(array $filters, int $perPage = 25, bool $excludeAdmins = true): LengthAwarePaginator
    {
        $query = Balance::query()
            ->select('balances.*')
            ->selectRaw(
                'SUM(CASE '
                . 'WHEN balances.user_company_id IS NOT NULL THEN 0 '
                . 'WHEN balances.status = 1 THEN balances.sum '
                . 'WHEN balances.status = 2 THEN -balances.sum '
                . 'ELSE 0 END) '
                . 'OVER (PARTITION BY balances.user_id ORDER BY balances.created_at ASC, balances.id ASC) '
                . 'AS ledger_balance_after'
            )
            ->with(['user:id,name,last_name,email', 'promoCode:id,code', 'company:id,name,inn,balance'])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $this->applyFilters($query, $filters);
        $this->applyExcludeAdminsToBalancesQuery($query, $excludeAdmins);

        return $query->paginate($perPage)->appends(array_filter(array_merge($filters, [
            'exclude_admins' => $excludeAdmins ? '1' : '0',
        ]), static function ($value) {
            return $value !== '' && $value !== 'all';
        }));
    }

    /**
     * @param array{status?: string, q?: string, period?: string} $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        $status = $filters['status'] ?? 'all';
        if ($status !== '' && $status !== 'all') {
            $query->where('status', (int) $status);
        }

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $query->whereHas('user', static function (Builder $userQuery) use ($q) {
                UserSmartSearch::apply($userQuery, $q, 'users');
            });
        }

        $period = (string) ($filters['period'] ?? 'all');
        if ($period !== 'all' && ctype_digit($period)) {
            $query->where('created_at', '>=', Carbon::now()->subDays((int) $period));
        }
    }

    private function formatMonthLabel(string $ym): string
    {
        try {
            return Carbon::createFromFormat('Y-m', $ym)->locale(app()->getLocale())->isoFormat('MMM YYYY');
        } catch (\Throwable $e) {
            return $ym;
        }
    }

    public static function formatMoney(int $amount): string
    {
        return number_format($amount, 0, '.', ' ') . ' ₽';
    }

    /**
     * @return list<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            'all' => __('Finance filter all statuses'),
            '1' => __('Finance status top up'),
            '2' => __('Finance status expense'),
            '0' => __('Finance status failed'),
        ];
    }

    /**
     * @return list<string, string>
     */
    public static function periodOptions(): array
    {
        return [
            'all' => __('Finance period all time'),
            '30' => __('Finance period 30 days'),
            '90' => __('Finance period 90 days'),
            '365' => __('Finance period year'),
        ];
    }

    public static function resolveExcludeAdminsFromRequest(Request $request): bool
    {
        $sessionKey = (string) config('cabinet-finance-admin.exclude_admin_stats_session_key', 'finance_admin_exclude_admins');
        $default = (bool) config('cabinet-finance-admin.exclude_admin_stats_default', true);

        if ($request->has('exclude_admins')) {
            $value = $request->boolean('exclude_admins');
            session([$sessionKey => $value]);

            return $value;
        }

        return (bool) session($sessionKey, $default);
    }

    /**
     * @param \Illuminate\Database\Query\Builder $query
     */
    private function applyExcludeAdminsToBalancesQuery($query, bool $excludeAdmins): void
    {
        if (!$excludeAdmins) {
            return;
        }

        $roles = config('cabinet-finance-admin.exclude_admin_roles', ['admin', 'Super Admin']);

        // Личные операции админов скрываем; пополнения/списания по фирмам (счета) оставляем.
        $query->where(function ($outer) use ($roles) {
            $outer->whereNotNull('balances.user_company_id')
                ->orWhereNotExists(function ($sub) use ($roles) {
                    $sub->select(DB::raw('1'))
                        ->from('model_has_roles as mhr')
                        ->join('roles as r', 'r.id', '=', 'mhr.role_id')
                        ->whereColumn('mhr.model_id', 'balances.user_id')
                        ->where('mhr.model_type', User::class)
                        ->whereIn('r.name', $roles);
                });
        });
    }

    /**
     * @param \Illuminate\Database\Query\Builder $query
     */
    private function applyExcludeAdminsToUserQuery($query, string $userIdColumn, bool $excludeAdmins): void
    {
        if (!$excludeAdmins) {
            return;
        }

        $roles = config('cabinet-finance-admin.exclude_admin_roles', ['admin', 'Super Admin']);

        $query->whereNotExists(function ($sub) use ($userIdColumn, $roles) {
            $sub->select(DB::raw('1'))
                ->from('model_has_roles as mhr')
                ->join('roles as r', 'r.id', '=', 'mhr.role_id')
                ->whereColumn('mhr.model_id', $userIdColumn)
                ->where('mhr.model_type', User::class)
                ->whereIn('r.name', $roles);
        });
    }

    public function simulateTopUp(User $user, int $paidSum, ?string $promoCodeRaw, User $admin, PromoCodeService $promos): Balance
    {
        return DB::transaction(function () use ($user, $paidSum, $promoCodeRaw, $admin, $promos) {
            $bonusSum = 0;
            $promoCodeId = null;
            $promoEntity = null;

            if ($promoCodeRaw !== null && trim($promoCodeRaw) !== '') {
                $resolved = $promos->resolveForPayment($user, $promoCodeRaw, $paidSum);
                if (!$resolved['valid']) {
                    throw ValidationException::withMessages([
                        'promo_code' => [$resolved['message']],
                    ]);
                }
                $bonusSum = (int) $resolved['bonus_sum'];
                $promoCodeId = (int) $resolved['promo_code']->id;
                $promoEntity = $resolved['promo_code'];
            }

            $source = $promoEntity
                ? __('Balance top up simulate promo', [
                    'payment' => number_format($paidSum, 0, '.', ' '),
                    'bonus' => number_format($bonusSum, 0, '.', ' '),
                    'code' => $promoEntity->code,
                    'admin' => (int) $admin->id,
                ])
                : __('Balance top up simulate plain', [
                    'payment' => number_format($paidSum, 0, '.', ' '),
                    'admin' => (int) $admin->id,
                ]);

            /** @var User $lockedUser */
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);

            $balance = $lockedUser->balances()->create([
                'sum' => $paidSum + $bonusSum,
                'paid_sum' => $paidSum,
                'bonus_sum' => $bonusSum,
                'promo_code_id' => $promoCodeId,
                'source' => $source,
                'status' => 1,
                'credited_at' => now(),
            ]);

            $lockedUser->increment('balance', $paidSum + $bonusSum);
            $promos->recordRedemption($balance);

            return $balance->fresh(['promoCode']);
        });
    }
}
