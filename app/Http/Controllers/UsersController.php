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

        if ($order = Arr::first($request->get('order'))) {
            $columns = $request->get('columns');
            $columnName = $columns[$order['column']]['name'] ?? null;
            if ($columnName && in_array($columnName, ['id', 'name', 'email', 'created_at', 'last_online_at'], true)) {
                $query->orderBy($columnName, $order['dir']);
            } else {
                $query->orderByDesc('id');
            }
        } else {
            $query->orderByDesc('id');
        }

        $users = $query->with([
            'pay' => static function ($payQuery) {
                $payQuery->where('status', true)
                    ->select(['id', 'user_id', 'status', 'class_tariff', 'active_to']);
            },
            'roles:id,name',
        ])->skip($start)->take($length)->get();

        $data = $users->map(function (User $user) {
            return $this->formatUserDataTableRow($user);
        })->values()->all();

        return response()->json([
            'draw' => (int) $request->input('draw', 0),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    /**
     * Компактная строка для DataTables (без лишних полей User и без tariff() на каждую запись).
     *
     * @return array<string, mixed>
     */
    protected function formatUserDataTableRow(User $user): array
    {
        $tariff = [];
        if ($pay = $user->pay->first()) {
            $tariff = [
                'name' => $this->resolveTariffNameForUser($user),
                'active_to' => $pay->active_to ? $pay->active_to->format('d.m.Y H:i') : null,
                'active_to_diffForHumans' => $pay->active_to ? $pay->active_to->diffForHumans() : null,
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
            'metrics' => $user->metrics,
        ];
    }

    /**
     * Имя тарифа по ролям (как getTariffByUser, без PermissionRegistrar и setUser).
     */
    protected function resolveTariffNameForUser(User $user): ?string
    {
        $roles = $user->relationLoaded('roles') ? $user->getRoleNames() : collect();

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
     * KPI на /users (кэш 2 мин — не считать 4× COUNT на каждый reload).
     *
     * @return array<string, int>
     */
    protected function usersIndexStats(): array
    {
        return Cache::remember('cabinet.users.index.stats', now()->addMinutes(2), static function () {
            return [
                'total' => User::count(),
                'verified' => User::whereNotNull('email_verified_at')->count(),
                'telegram' => User::telegramConnected()->count(),
                'with_tariff' => User::whereHas('pay', static function ($q) {
                    $q->where('status', true);
                })->count(),
            ];
        });
    }

    protected function usersRecordsTotal(): int
    {
        return (int) Cache::remember('cabinet.users.records_total', now()->addMinutes(5), static function () {
            return User::count();
        });
    }

    protected function userListHasActiveFilters(Request $request): bool
    {
        foreach (['filter_role', 'filter_verify', 'filter_tariff', 'filter_online', 'filter_statistic', 'filter_telegram', 'filter_id_from', 'filter_id_to'] as $key) {
            if (trim((string) $request->input($key, '')) !== '') {
                return true;
            }
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

        $tariff = trim((string) $request->input('filter_tariff', ''));
        if ($tariff === 'none') {
            $query->whereDoesntHave('pay', static function ($q) {
                $q->where('status', true);
            });
        } elseif ($tariff !== '') {
            $tariffInstance = $this->tariff->getTariffByCode($tariff);
            if ($tariffInstance) {
                $class = get_class($tariffInstance);
                $query->whereHas('pay', static function ($q) use ($class) {
                    $q->where('status', true)->where('class_tariff', $class);
                });
            }
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
    public function destroy(User $user)
    {
        if ($user->id == Auth::id()) {
            flash()->overlay(__('You cannot delete yourself'), __('Error user'))->error();
        } else {
            $user->delete();
            flash()->overlay(__('User deleted successfully'), __('Delete user'))->success();
        }
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
