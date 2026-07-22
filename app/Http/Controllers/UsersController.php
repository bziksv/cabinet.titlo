<?php

namespace App\Http\Controllers;

use App\Classes\Tariffs\Facades\Tariffs;
use App\Classes\Tariffs\FreeTariff;
use App\Classes\Tariffs\Period\OneDayTariff;
use App\Classes\Tariffs\Period\PeriodTariff;
use App\Classes\Tariffs\Tariff;
use App\Common;
use App\Exports\FilteredUsersExport;
use App\Exports\VerifiedUsersExport;
use App\MainProject;
use App\Support\MonitoringStaleScheduleReport;
use App\Support\UserStorageFootprintService;
use App\Support\UsersActivityDashboard;
use App\Support\UserVisitStatisticsReport;
use App\Support\UsersVisitStatisticsReport;
use App\User;
use App\VisitStatistic;
use Carbon\Carbon;
use Exception;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Jenssegers\Agent\Agent;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class UsersController extends Controller
{
    protected $tariff;

    public function __construct()
    {

        $this->middleware(['permission:Users']);

        $this->tariff = new Tariffs();
        $this->tariff->setPeriods(new OneDayTariff());
    }

    /**
     * @return void
     */
    public function index(Request $request)
    {
        if ($request->ajax() || $request->has('draw')) {
            return $this->getDataTable($request);
        }

        $tariffSelect = $this->tariffSelectData();

        return view('users.index', [
            'tariffSelect' => $tariffSelect,
            'roles' => Role::orderBy('name')->pluck('name', 'name'),
            'activity' => UsersActivityDashboard::snapshotCached(),
            'stats' => $this->usersIndexStats(),
            'staleMonitoring' => Cache::remember(
                'cabinet.monitoring.stale_schedules.summary',
                now()->addMinutes(5),
                static function () {
                    return MonitoringStaleScheduleReport::summary();
                }
            ),
            'footprintRefreshedAt' => Cache::get('cabinet.users.footprint_refreshed_at'),
        ]);
    }

    /**
     * Select2 AJAX: поиск email для модалки «Назначить тариф» (мин. 2 символа).
     */
    public function searchEmails(Request $request): JsonResponse
    {
        $q = trim((string) $request->get('q', ''));
        if (strlen($q) < 2) {
            return response()->json(['results' => []]);
        }

        $users = User::query()
            ->select(['id', 'email'])
            ->without(['pay', 'roles'])
            ->where('email', 'like', $q . '%')
            ->orderBy('email')
            ->limit(30)
            ->get();

        return response()->json([
            'results' => $users->map(static function (User $user) {
                return ['id' => $user->id, 'text' => $user->email];
            })->values(),
        ]);
    }

    protected function getDataTable(Request $request)
    {
        $start = max(0, (int) $request->get('start', 0));
        $length = (int) $request->get('length', 50);
        $length = $length > 0 ? min($length, 100) : 50;

        $query = User::query()
            ->select([
                'users.id',
                'users.name',
                'users.last_name',
                'users.email',
                'users.email_verified_at',
                'users.read_letter',
                'users.created_at',
                'users.last_online_at',
                'users.telegram_bot_active',
                'users.chat_id',
                'users.metrics',
            ])
            ->without(['pay', 'roles']);

        $this->applyUserListFilters($query, $request);

        $searchRaw = $request->input('filter_q');
        if ($searchRaw === null) {
            $searchBox = $request->get('search');
            $searchRaw = is_array($searchBox) ? ($searchBox['value'] ?? '') : '';
        }
        $this->applyUserSmartSearch($query, trim((string) $searchRaw));

        $recordsTotal = $this->usersRecordsTotal();
        $recordsFiltered = $this->userListHasActiveFilters($request)
            ? (clone $query)->count()
            : $recordsTotal;

        $orderColumn = null;
        $orderDir = 'desc';
        if ($order = Arr::first($request->get('order'))) {
            $columns = $request->get('columns');
            $orderColumn = $columns[$order['column']]['name'] ?? null;
            $orderDir = (string) ($order['dir'] ?? 'desc');
        }

        $userWith = [
            'pay' => static function ($payQuery) {
                $payQuery->where('status', true)
                    ->select(['id', 'user_id', 'status', 'class_tariff', 'active_to']);
            },
            'roles:id,name',
        ];

        if ($orderColumn === 'storage_footprint') {
            $users = $this->usersDataTablePageByFootprintSort($query, $start, $length, $orderDir, $userWith);
        } else {
            if ($orderColumn) {
                $this->applyUserListOrder($query, $orderColumn, $orderDir);
            } else {
                $query->orderByDesc('users.id');
            }

            $users = $query->with($userWith)->skip($start)->take($length)->get();
        }

        $footprints = UserStorageFootprintService::getManyCached($users->pluck('id')->all());

        $data = $users->map(function (User $user) use ($footprints) {
            return $this->formatUserDataTableRow($user, $footprints[(int) $user->id] ?? null);
        })->values()->all();

        return response()->json([
            'draw' => (int) $request->input('draw', 0),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    /**
     * Сортировка «Данные в БД» по кэшированному footprint (без колонки в users).
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array<string, mixed> $userWith
     * @return \Illuminate\Support\Collection<int, User>
     */
    protected function usersDataTablePageByFootprintSort($query, int $start, int $length, string $dir, array $userWith)
    {
        $ids = (clone $query)->pluck('users.id')->map(static function ($id) {
            return (int) $id;
        })->all();

        if ($ids === []) {
            return collect();
        }

        $footprints = UserStorageFootprintService::getManyCached($ids);
        $dirAsc = strtolower($dir) === 'asc';

        usort($ids, static function (int $a, int $b) use ($footprints, $dirAsc) {
            $ra = isset($footprints[$a]) ? (int) ($footprints[$a]['rows'] ?? 0) : null;
            $rb = isset($footprints[$b]) ? (int) ($footprints[$b]['rows'] ?? 0) : null;

            if ($ra === null && $rb === null) {
                return $b <=> $a;
            }
            if ($ra === null) {
                return $dirAsc ? 1 : -1;
            }
            if ($rb === null) {
                return $dirAsc ? -1 : 1;
            }
            if ($ra === $rb) {
                return $b <=> $a;
            }

            return $dirAsc ? ($ra <=> $rb) : ($rb <=> $ra);
        });

        $pageIds = array_slice($ids, $start, $length);
        if ($pageIds === []) {
            return collect();
        }

        $select = [
            'users.id',
            'users.name',
            'users.last_name',
            'users.email',
            'users.email_verified_at',
            'users.read_letter',
            'users.created_at',
            'users.last_online_at',
            'users.telegram_bot_active',
            'users.chat_id',
            'users.metrics',
        ];

        return User::query()
            ->select($select)
            ->whereIn('users.id', $pageIds)
            ->with($userWith)
            ->get()
            ->sortBy(static function (User $user) use ($pageIds) {
                $pos = array_search((int) $user->id, $pageIds, true);

                return $pos === false ? PHP_INT_MAX : $pos;
            })
            ->values();
    }

    /**
     * Компактная строка для DataTables (без лишних полей User и без tariff() на каждую запись).
     *
     * @return array<string, mixed>
     */
    protected function formatUserDataTableRow(User $user, ?array $footprint = null): array
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId(1);
        $tariffName = $this->resolveTariffNameForUser($user);
        $tariff = [];

        if ($pay = $user->pay->first()) {
            $tariff = [
                'name' => $tariffName,
                'active_to' => $pay->active_to ? $pay->active_to->format('d.m.Y H:i') : null,
                'active_to_diffForHumans' => $pay->active_to ? $pay->active_to->diffForHumans() : null,
                'role_only' => false,
                'is_free' => $user->onFreeTariff(),
            ];
        } elseif ($tariffName !== null) {
            $tariff = [
                'name' => $tariffName,
                'active_to' => null,
                'active_to_diffForHumans' => null,
                'role_only' => true,
                'is_free' => $user->onFreeTariff(),
            ];
        }

        $loa = $user->last_online_at;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'email_verified_at' => $user->email_verified_at,
            'read_letter' => $user->read_letter,
            'tariff' => $tariff,
            'created' => $user->created_at->format('d.m.Y H:i:s'),
            'created_diffForHumans' => $user->created_at->diffForHumans(),
            'roles' => $user->roles->map(static function ($role) {
                return ['id' => $role->id, 'name' => $role->name];
            })->values()->all(),
            'last_online_strtotime' => $loa ? $loa->timestamp : 0,
            'last_online' => $loa ? $loa->format('d.m.Y H:i') : null,
            'last_online_diffForHumans' => $loa ? $loa->diffForHumans() : null,
            'telegram_connected' => $user->isTelegramConnected(),
            'telegram_chat_id' => $user->isTelegramConnected() ? (string) $user->chat_id : null,
            'telegram_sort' => $user->isTelegramConnected() ? 1 : 0,
            'metrics' => $user->metrics,
            'storage' => $footprint,
            'storage_sort' => $footprint !== null ? (int) ($footprint['rows'] ?? 0) : null,
        ];
    }

    /**
     * Имя тарифа по ролям (как getTariffByUser, без PermissionRegistrar и setUser).
     */
    protected function resolveTariffNameForUser(User $user): ?string
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId(1);
        if (!$user->relationLoaded('roles')) {
            $user->load('roles');
        }
        $roles = $user->getRoleNames();

        foreach ($this->tariff->getTariffs() as $tariff) {
            if ($roles->contains($tariff->code())) {
                return $tariff->name();
            }
        }

        if ($roles->contains('Free')) {
            return (new FreeTariff())->name();
        }

        return null;
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder $query
     */
    protected function applyUserListOrder($query, ?string $columnName, string $dir): void
    {
        if ($columnName === null || $columnName === '') {
            $query->orderByDesc('users.id');

            return;
        }

        $dir = strtolower($dir) === 'asc' ? 'asc' : 'desc';
        $userClass = str_replace('\\', '\\\\', User::class);

        switch ($columnName) {
            case 'tariff':
                $codes = $this->tariffRoleCodesForSort();
                $in = implode("','", array_map('addslashes', $codes));
                $query->orderByRaw(
                    "(SELECT MIN(r.name) FROM model_has_roles mhr INNER JOIN roles r ON r.id = mhr.role_id "
                    . "WHERE mhr.model_id = users.id AND mhr.model_type = '{$userClass}' AND r.name IN ('{$in}')) {$dir}"
                );
                break;
            case 'roles':
                $query->orderByRaw(
                    "(SELECT MIN(r.name) FROM model_has_roles mhr INNER JOIN roles r ON r.id = mhr.role_id "
                    . "WHERE mhr.model_id = users.id AND mhr.model_type = '{$userClass}') {$dir}"
                );
                break;
            case 'name':
                $query->orderByRaw("CONCAT(COALESCE(users.name, ''), ' ', COALESCE(users.last_name, '')) {$dir}");
                break;
            case 'last_online_at':
                if ($dir === 'asc') {
                    $query->orderByRaw('users.last_online_at IS NULL DESC')
                        ->orderBy('users.last_online_at', 'asc');
                } else {
                    $query->orderByRaw('users.last_online_at IS NULL ASC')
                        ->orderBy('users.last_online_at', 'desc');
                }
                break;
            case 'telegram':
                $query->orderByRaw(
                    '(users.telegram_bot_active = 1 AND users.chat_id IS NOT NULL AND users.chat_id != \'\') ' . $dir
                );
                break;
            case 'id':
            case 'email':
            case 'created_at':
                $query->orderBy('users.' . $columnName, $dir);
                break;
            default:
                $query->orderByDesc('users.id');

                return;
        }

        if ($columnName !== 'id') {
            $query->orderByDesc('users.id');
        }
    }

    /**
     * @return string[]
     */
    protected function tariffRoleCodesForSort(): array
    {
        $codes = array_map(static function (Tariff $tariff) {
            return $tariff->code();
        }, $this->tariff->getTariffs());
        $codes[] = 'Free';

        return $codes;
    }

    /**
     * KPI на /users (кэш 2 мин — не считать 4× COUNT на каждый reload).
     *
     * @return array<string, int>
     */
    protected function usersIndexStats(): array
    {
        $controller = $this;

        return Cache::remember('cabinet.users.index.stats', now()->addMinutes(2), static function () use ($controller) {
            return [
                'total' => User::count(),
                'verified' => User::whereNotNull('email_verified_at')->count(),
                'telegram' => User::telegramConnected()->count(),
                'with_tariff' => $controller->countUsersWithPaidTariff(),
            ];
        });
    }

    protected function countUsersWithPaidTariff(): int
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId(1);
        apply_global_team_permissions();

        $paidCodes = array_map(static function (Tariff $t) {
            return $t->code();
        }, $this->tariff->getTariffs());

        if (count($paidCodes) === 0) {
            return 0;
        }

        $classes = [];
        foreach ($paidCodes as $code) {
            $instance = $this->tariff->getTariffByCode($code);
            if ($instance) {
                $classes[] = get_class($instance);
            }
        }

        return User::query()
            ->where(static function ($q) use ($paidCodes, $classes) {
                $q->whereHas('roles', static function ($rq) use ($paidCodes) {
                    $rq->whereIn('name', $paidCodes);
                });
                if (count($classes) > 0) {
                    $q->orWhereHas('pay', static function ($pq) use ($classes) {
                        $pq->where('status', true)->whereIn('class_tariff', $classes);
                    });
                }
            })
            ->count();
    }

    protected function usersRecordsTotal(): int
    {
        return (int) Cache::remember('cabinet.users.records_total', now()->addMinutes(5), static function () {
            return User::count();
        });
    }

    protected function userListHasActiveFilters(Request $request): bool
    {
        foreach (['filter_role', 'filter_verify', 'filter_online', 'filter_statistic', 'filter_telegram', 'filter_id_from', 'filter_id_to', 'filter_stale_monitoring', 'filter_name', 'filter_email', 'filter_created_from', 'filter_created_to'] as $key) {
            if (trim((string) $request->input($key, '')) !== '') {
                return true;
            }
        }

        if (count($this->parseActiveTariffFilters($request)) > 0) {
            return true;
        }

        $searchRaw = $request->input('filter_q');
        if ($searchRaw === null) {
            $searchBox = $request->get('search');
            $searchRaw = is_array($searchBox) ? ($searchBox['value'] ?? '') : '';
        }

        return trim((string) $searchRaw) !== '';
    }

    /**
     * Поиск по имени, фамилии, полному имени, email и ID.
     */
    protected function applyUserSmartSearch($query, string $search): void
    {
        if ($search === '') {
            return;
        }

        if (ctype_digit($search)) {
            $id = (int) $search;
            $query->where(function ($q) use ($id, $search) {
                $q->where('id', $id)
                    ->orWhere('email', 'like', $search . '%');
            });

            return;
        }

        if (strpos($search, '@') !== false) {
            $query->where('email', 'like', '%' . $search . '%');

            return;
        }

        $terms = preg_split('/\s+/u', $search, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($terms as $term) {
            $like = '%' . $term . '%';
            $query->where(function ($q) use ($like) {
                $q->where('email', 'like', $like)
                    ->orWhere('name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhereRaw("CONCAT(COALESCE(name, ''), ' ', COALESCE(last_name, '')) LIKE ?", [$like])
                    ->orWhereRaw("CONCAT(COALESCE(last_name, ''), ' ', COALESCE(name, '')) LIKE ?", [$like]);
            });
        }
    }

    protected function applyUserListFilters($query, Request $request): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId(1);
        apply_global_team_permissions();

        $role = trim((string) $request->input('filter_role', ''));
        if ($role !== '') {
            $query->whereHas('roles', static function ($q) use ($role) {
                $q->where('name', $role);
            });
        }

        $verify = trim((string) $request->input('filter_verify', ''));
        if ($verify === 'verified') {
            $query->whereNotNull('email_verified_at');
        } elseif ($verify === 'unverified') {
            $query->whereNull('email_verified_at');
        }

        $tariffFilters = $this->parseActiveTariffFilters($request);
        if (count($tariffFilters) > 0) {
            $paidCodes = $this->paidTariffRoleCodes();
            $query->where(function ($outer) use ($tariffFilters, $paidCodes) {
                foreach ($tariffFilters as $tariff) {
                    $outer->orWhere(function ($q) use ($tariff, $paidCodes) {
                        $this->applySingleActiveTariffFilter($q, $tariff, $paidCodes);
                    });
                }
            });
        }

        $online = trim((string) $request->input('filter_online', ''));
        if ($online === 'never') {
            $query->whereNull('last_online_at');
        } elseif ($online === '7d') {
            $query->where('last_online_at', '>=', Carbon::now()->subDays(7));
        } elseif ($online === '30d') {
            $query->where('last_online_at', '>=', Carbon::now()->subDays(30));
        } elseif ($online === 'inactive30d') {
            $query->where(function ($q) {
                $q->whereNull('last_online_at')
                    ->orWhere('last_online_at', '<', Carbon::now()->subDays(30));
            });
        } elseif ($online === 'inactive180d') {
            $query->where(function ($q) {
                $q->whereNull('last_online_at')
                    ->orWhere('last_online_at', '<', Carbon::now()->subDays(180));
            });
        } elseif ($online === 'inactive360d') {
            $query->where(function ($q) {
                $q->whereNull('last_online_at')
                    ->orWhere('last_online_at', '<', Carbon::now()->subDays(360));
            });
        } elseif ($online === 'inactive2y') {
            $query->where(function ($q) {
                $q->whereNull('last_online_at')
                    ->orWhere('last_online_at', '<', Carbon::now()->subYears(2));
            });
        }

        $statistic = trim((string) $request->input('filter_statistic', ''));
        if ($statistic === '1') {
            $query->where('statistic', 1);
        } elseif ($statistic === '0') {
            $query->where(function ($q) {
                $q->where('statistic', 0)->orWhereNull('statistic');
            });
        }

        $idFrom = $request->input('filter_id_from');
        if ($idFrom !== null && $idFrom !== '') {
            $query->where('id', '>=', (int) $idFrom);
        }

        $idTo = $request->input('filter_id_to');
        if ($idTo !== null && $idTo !== '') {
            $query->where('id', '<=', (int) $idTo);
        }

        $telegram = trim((string) $request->input('filter_telegram', ''));
        if ($telegram === '1') {
            $query->telegramConnected();
        } elseif ($telegram === '0') {
            $query->where(static function ($q) {
                $q->where('telegram_bot_active', false)
                    ->orWhereNull('chat_id')
                    ->orWhere('chat_id', '=', '');
            });
        }

        $staleMonitoring = trim((string) $request->input('filter_stale_monitoring', ''));
        if ($staleMonitoring === '1') {
            $staleIds = MonitoringStaleScheduleReport::staleCreatorUserIds();
            if ($staleIds === []) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('id', $staleIds);
            }
        }

        $name = trim((string) $request->input('filter_name', ''));
        if ($name !== '') {
            $like = '%' . $name . '%';
            $query->where(static function ($q) use ($like) {
                $q->where('name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhereRaw("CONCAT(COALESCE(name, ''), ' ', COALESCE(last_name, '')) LIKE ?", [$like]);
            });
        }

        $email = trim((string) $request->input('filter_email', ''));
        if ($email !== '') {
            $query->where('email', 'like', '%' . $email . '%');
        }

        $createdFrom = trim((string) $request->input('filter_created_from', ''));
        if ($createdFrom !== '') {
            try {
                $query->whereDate('created_at', '>=', Carbon::parse($createdFrom)->format('Y-m-d'));
            } catch (\Throwable $e) {
                // ignore invalid date
            }
        }

        $createdTo = trim((string) $request->input('filter_created_to', ''));
        if ($createdTo !== '') {
            try {
                $query->whereDate('created_at', '<=', Carbon::parse($createdTo)->format('Y-m-d'));
            } catch (\Throwable $e) {
                // ignore invalid date
            }
        }
    }

    /**
     * @return string[]
     */
    protected function parseActiveTariffFilters(Request $request): array
    {
        $raw = $request->input('filter_active_tariffs', []);
        if (!is_array($raw)) {
            $raw = array_filter(explode(',', (string) $raw));
        }

        $allowed = array_merge(
            $this->paidTariffRoleCodes(),
            ['Free', '__none__', '__no_role__']
        );

        return array_values(array_unique(array_filter(array_map(static function ($value) use ($allowed) {
            $value = trim((string) $value);

            return in_array($value, $allowed, true) ? $value : null;
        }, $raw))));
    }

    /**
     * @return string[]
     */
    protected function paidTariffRoleCodes(): array
    {
        return array_map(static function (Tariff $t) {
            return $t->code();
        }, $this->tariff->getTariffs());
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder $query
     */
    protected function applySingleActiveTariffFilter($query, string $tariff, array $paidCodes): void
    {
        if ($tariff === '__none__') {
            if (count($paidCodes) > 0) {
                $query->whereDoesntHave('roles', static function ($q) use ($paidCodes) {
                    $q->whereIn('name', $paidCodes);
                });
            }

            return;
        }

        if ($tariff === 'Free') {
            $query->whereHas('roles', static function ($q) {
                $q->where('name', 'Free');
            });
            if (count($paidCodes) > 0) {
                $query->whereDoesntHave('roles', static function ($q) use ($paidCodes) {
                    $q->whereIn('name', $paidCodes);
                });
            }

            return;
        }

        if ($tariff === '__no_role__') {
            $tariffRoles = array_merge($paidCodes, ['Free']);
            if (count($tariffRoles) > 0) {
                $query->whereDoesntHave('roles', static function ($q) use ($tariffRoles) {
                    $q->whereIn('name', $tariffRoles);
                });
            }

            return;
        }

        $tariffInstance = $this->tariff->getTariffByCode($tariff);
        if (!$tariffInstance) {
            $query->whereRaw('1 = 0');

            return;
        }

        $code = $tariffInstance->code();
        $class = get_class($tariffInstance);
        $query->where(static function ($q) use ($code, $class) {
            $q->whereHas('roles', static function ($rq) use ($code) {
                $rq->where('name', $code);
            })->orWhereHas('pay', static function ($pq) use ($class) {
                $pq->where('status', true)->where('class_tariff', $class);
            });
        });
    }

    public function refreshStorageFootprint(Request $request): JsonResponse
    {
        $userId = (int) $request->input('user_id', 0);
        if ($userId > 0) {
            $payload = UserStorageFootprintService::computeForUser($userId);

            return response()->json([
                'success' => true,
                'user_id' => $userId,
                'footprint' => $payload,
                'label' => UserStorageFootprintService::formatBrief($payload),
            ]);
        }

        $userIds = $request->input('user_ids');
        if (is_array($userIds) && count($userIds) > 0) {
            set_time_limit(max(60, (int) ini_get('max_execution_time')));
            $result = UserStorageFootprintService::refreshForUserIds($userIds);

            return response()->json([
                'success' => true,
                'users' => $result['users'],
                'errors' => $result['errors'],
                'refreshed_at' => Cache::get('cabinet.users.footprint_refreshed_at'),
            ]);
        }

        set_time_limit(max(120, (int) ini_get('max_execution_time')));
        $direction = strtolower((string) $request->input('direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        $cursorId = (int) $request->input('cursor_id', $request->input('after_id', 0));
        $result = UserStorageFootprintService::refreshBatch(
            $cursorId,
            (int) $request->input('limit', 15),
            $direction
        );

        return response()->json([
            'success' => true,
            'users' => $result['users'],
            'errors' => $result['errors'],
            'last_id' => $result['last_id'],
            'cursor_id' => $result['last_id'],
            'done' => $result['done'],
            'remaining' => $result['remaining'],
            'direction' => $result['direction'],
            'total' => $result['total'],
            'refreshed_at' => Cache::get('cabinet.users.footprint_refreshed_at'),
        ]);
    }

    public function userStorageFootprint(User $user): JsonResponse
    {
        $payload = UserStorageFootprintService::getCached((int) $user->id);
        if ($payload === null) {
            $payload = UserStorageFootprintService::computeForUser((int) $user->id);
        }

        return response()->json([
            'user_id' => (int) $user->id,
            'footprint' => $payload,
            'label' => UserStorageFootprintService::formatBrief($payload),
        ]);
    }

    public function storeTariff(Request $request)
    {
        foreach ($request['users'] as $user) {
            $user = User::find($user);
            $this->assignTariffByUser($user, $request['tariff'], $request['period']);
        }

        return redirect()->back();
    }

    private function assignTariffByUser(User $user, string $tariffCode, string $periodCode): void
    {
        $tariff = $this->tariff->getTariffByCode($tariffCode);
        $tariff->setPeriod($this->tariff->getPeriodByCode($periodCode));

        $user->pay()->update(['status' => false]);

        $user->pay()->create([
            'status' => true,
            'class_tariff' => get_class($tariff),
            'class_period' => get_class($tariff->getPeriod()),
            'sum' => 0,
            'active_to' => Carbon::now()->addDays($tariff->getPeriod()->days())
        ]);

        $tariff->assignRoleByUser($user);
    }

    private function tariffSelectData(): array
    {
        $select = [
            'tariff' => [],
            'period' => [],
        ];

        /* @var Tariff $tariff */
        foreach ($this->tariff->getTariffs() as $tariff)
            $select['tariff'][$tariff->code()] = $tariff->name();

        /* @var PeriodTariff $period */
        foreach ($this->tariff->getPeriods() as $period)
            $select['period'][$period->code()] = $period->name();

        return $select;
    }

    /**
     * @param $id
     * @return Authenticatable
     */
    public function login($id)
    {
        if (Auth::loginUsingId($id))
            return redirect('/');
        else
            return redirect('users');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        return view('users.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param Request $request
     * @return Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param int $id
     * @return Response
     */
    public function edit(User $user)
    {
        apply_global_team_permissions();

        $user->load([
            'roles',
            'pay' => static function ($payQuery) {
                $payQuery->where('status', true)
                    ->select(['id', 'user_id', 'status', 'active_to']);
            },
        ]);

        $roleOptions = Role::orderBy('name')->pluck('name', 'id')->map(static function ($val) {
            return __($val);
        });

        $lang = collect(Storage::disk('lang')->files())->mapWithKeys(function ($val) {
            $str = Str::before($val, '.');
            return [$str => __($str)];
        });

        $superAdmin = in_array(3, Auth::user()->role->toArray(), true);

        if (!$superAdmin) {
            $roleOptions = $roleOptions->except([3]);
        }

        $activePay = $user->pay->first();
        $tariffName = $activePay ? $this->resolveTariffNameForUser($user) : null;

        return view('users.edit', [
            'user' => $user,
            'roleOptions' => $roleOptions,
            'lang' => $lang,
            'superAdmin' => $superAdmin,
            'canManageStatistic' => User::isUserAdmin(),
            'activePay' => $activePay,
            'tariffName' => $tariffName,
            'telegramConnected' => $user->isTelegramConnected(),
            'storageFootprint' => UserStorageFootprintService::getCached((int) $user->id),
        ]);
    }

    /**
     * @param User $user
     * @param Request $request
     * @return Application|RedirectResponse|Redirector|void
     * @throws ValidationException
     */
    public function update(User $user, Request $request)
    {
        $this->validate($request, [
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'last_name' => ['required', 'string', 'min:3', 'max:255'],
            'email' => ['required', 'string', 'email', 'min:3', 'max:255'],
            'role' => ['required'],
            'password' => ['nullable', 'min:8']
        ]);

        $fields = $request->only(['name', 'last_name', 'email', 'lang']);
        if (User::isUserAdmin()) {
            $fields['statistic'] = (int) $request->input('statistic', 0) === 1;
        }
        $user->update($fields);
        $user->syncRoles($request->input('role'));

        if ($request->input('password') !== null && in_array(3, Auth::user()->role->toArray())) {
            $user->password = Hash::make($request->input('password'));
            $user->setRememberToken(Str::random(60));

            $user->save();
        }

        if ($user->lang == 'en') {
            flash()->overlay('User update successfully', 'Notification')->success();
        } else {
            flash()->overlay('Даные пользователя успешно обновлены', 'Уведомление')->success();
        }

        return redirect()->route('users.index');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param User $user
     * @return Response
     * @throws Exception
     */
    public function destroy(User $user, \App\Support\InactiveUsersPurge $purge)
    {
        if ($user->id == Auth::id()) {
            flash()->overlay(__('You cannot delete yourself'), __('Error user'))->error();

            return;
        }

        try {
            $purge->deleteUserCompletely($user);
            flash()->overlay(__('User deleted successfully'), __('Delete user'))->success();
        } catch (\Throwable $e) {
            flash()->overlay($e->getMessage(), __('Error user'))->error();
        }
    }

    /**
     * Превью: сколько пользователей не заходили N лет (без админов / себя).
     */
    public function inactivePurgePreview(Request $request, \App\Support\InactiveUsersPurge $purge): JsonResponse
    {
        $years = (int) $request->input('years', 0);
        if (! in_array($years, [2, 3], true)) {
            return response()->json(['message' => __('Users inactive purge invalid years')], 422);
        }

        set_time_limit(120);

        return response()->json($purge->preview($years));
    }

    /**
     * Удалить пользователей и связанные данные (FK CASCADE), не заходивших N лет.
     */
    public function inactivePurge(Request $request, \App\Support\InactiveUsersPurge $purge): JsonResponse
    {
        $years = (int) $request->input('years', 0);
        if (! in_array($years, [2, 3], true)) {
            return response()->json(['message' => __('Users inactive purge invalid years')], 422);
        }

        $confirm = trim((string) $request->input('confirm', ''));
        $expected = 'DELETE ' . $years . 'Y';
        if ($confirm !== $expected) {
            return response()->json([
                'message' => __('Users inactive purge confirm mismatch', ['code' => $expected]),
            ], 422);
        }

        set_time_limit(0);
        $result = $purge->purge($years);

        return response()->json($result);
    }

    /**
     * Create a new agent instance from the given session.
     *
     * @param mixed $session
     * @return Agent
     */
    private function createAgent($session)
    {
        return tap(new Agent, function ($agent) use ($session) {
            $agent->setUserAgent($session->user_agent);
        });
    }

    public function getFile($type)
    {
        if (User::isUserAdmin()) {
            $file = Excel::download(new VerifiedUsersExport(), 'verified_users.' . $type);
            Common::fileExport($file, $type, 'verified-users');
        } else {
            abort(403);
        }

    }

    public function filterExportsUsers(Request $request)
    {
        if (User::isUserAdmin()) {
            $file = Excel::download(new FilteredUsersExport($request->all()), 'filtered_users.' . $request->fileType);
            Common::fileExport($file, $request->fileType, 'verified-users');
        } else {
            abort(403);
        }
    }

    public function visitStatistics(User $user, Request $request)
    {
        if (Auth::id() !== $user->id && !User::isUserAdmin()) {
            return abort(403);
        }

        $from = Carbon::now()->subDays(29)->startOfDay();
        $to = Carbon::now()->endOfDay();

        if ($request->filled('from') && $request->filled('to')) {
            $from = Carbon::parse($request->input('from'))->startOfDay();
            $to = Carbon::parse($request->input('to'))->endOfDay();
        }

        if ($to->lessThan($from)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        $hasAnyVisits = VisitStatistic::where('user_id', $user->id)->exists();
        $report = UserVisitStatisticsReport::build($user, $from, $to);

        return view('users.visit-statistics', [
            'user' => $user,
            'report' => $report,
            'hasAnyVisits' => $hasAnyVisits,
            'dateFrom' => $from->format('Y-m-d'),
            'dateTo' => $to->format('Y-m-d'),
            'dateRange' => UserVisitStatisticsReport::dateRangeString($from, $to),
        ]);
    }

    private function getLastActions($start, $end, $userId)
    {
        return VisitStatistic::whereBetween('date', [
            date('Y-m-d', strtotime($start)),
            date('Y-m-d', strtotime($end))
        ])->where('user_id', $userId)
            ->select('project_id', DB::raw('MAX(date) as last_visit'))
            ->groupBy('project_id')
            ->pluck('last_visit', 'project_id');
    }

    public function userActionsHistory(Request $request): JsonResponse
    {
        $user = User::findOrFail((int) $request->input('userId'));

        if (Auth::id() !== $user->id && !User::isUserAdmin()) {
            return abort(403);
        }

        $range = explode(' - ', (string) $request->input('dateRange', ''));
        if (count($range) < 2) {
            return response()->json(['error' => 'Invalid date range'], 422);
        }

        $from = Carbon::parse($range[0])->startOfDay();
        $to = Carbon::parse($range[1])->endOfDay();

        return response()->json(UserVisitStatisticsReport::build($user, $from, $to));
    }

    public function getDateRangeVisitStatistics(User $user): JsonResponse
    {
        if (Auth::id() !== $user->id && !User::isUserAdmin()) {
            return abort(403);
        }

        return response()->json([
            'dates' => VisitStatistic::where('user_id', $user->id)
                ->groupBy('date')
                ->get('date')
                ->toArray()
        ]);
    }

    private function getActions($dateRange, $userId)
    {
        $range = explode(' - ', $dateRange);

        return VisitStatistic::whereBetween('date', [
            date('Y-m-d', strtotime($range[0])),
            date('Y-m-d', strtotime($range[1]))
        ])
            ->where('user_id', $userId)
            ->with('project')
            ->get()
            ->groupBy('project_id')
            ->map(function ($group) {
                $sumActions = $group->sum('actions_counter');
                $sumRefresh = $group->sum('refresh_page_counter');
                $countSeconds = $group->sum('seconds');
                $firstItem = $group->first();
                $firstItem->actionsCounter = $sumActions;
                $firstItem->refreshPageCounter = $sumRefresh;
                $firstItem->time = Common::secondsToDate($countSeconds);

                return $firstItem;
            });
    }

    private function getCounterActions($start, $end, $userId, $encode = true)
    {
        $response = VisitStatistic::whereBetween('date', [
            date('Y-m-d', strtotime($start)),
            date('Y-m-d', strtotime($end))
        ])
            ->where('user_id', $userId)
            ->get(['date', 'refresh_page_counter', 'seconds', 'actions_counter'])
            ->groupBy('date')
            ->map(function ($group) {
                $sumActions = $group->sum('actions_counter');
                $sumRefresh = $group->sum('refresh_page_counter');
                $countSeconds = $group->sum('seconds');
                $firstItem = $group->first();
                $firstItem->actionsCounter = $sumActions;
                $firstItem->refreshPageCounter = $sumRefresh;
                $firstItem->time = $countSeconds;
                unset($firstItem->refresh_page_counter);
                unset($firstItem->actions_counter);
                unset($firstItem->seconds);
                unset($firstItem->date);

                return $firstItem;
            });

        $actions = [];
        $refresh = [];
        $time = [];
        $date = [];

        foreach ($response->toArray() as $key => $item) {
            $actions[] = $item['actionsCounter'];
            $refresh[] = $item['refreshPageCounter'];
            $time[] = $item['time'];
            $date[] = $key;
        }

        if ($encode) {
            return json_encode([
                'actions' => $actions,
                'refresh' => $refresh,
                'time' => $time,
                'data' => $date
            ]);
        }

        return [
            'actions' => $actions,
            'refresh' => $refresh,
            'time' => $time,
            'data' => $date
        ];

    }

    public function userVisitStatistics(Request $request)
    {
        if (!User::isUserAdmin()) {
            return abort(403);
        }

        $limit = min(max((int) $request->input('limit', 500), 50), 2000);

        $from = null;
        $to = null;
        if ($request->filled('from')) {
            $from = Carbon::parse($request->input('from'))->startOfDay();
        }
        if ($request->filled('to')) {
            $to = Carbon::parse($request->input('to'))->endOfDay();
        }
        if ($from && $to && $to->lessThan($from)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        $report = UsersVisitStatisticsReport::build($from, $to, $limit);

        return view('users.visits-statistics', [
            'report' => $report,
            'limit' => $limit,
            'dateFrom' => $from ? $from->format('Y-m-d') : '',
            'dateTo' => $to ? $to->format('Y-m-d') : '',
        ]);
    }

    public static function getRoles(array $array): array
    {
        $roles = [];

        foreach ($array as $item) {
            $roles[] = $item['name'];
        }

        return $roles;
    }
}
