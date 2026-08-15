<?php

namespace App\Http\Controllers;

use App\SeoChecklist\SeoChecklistItem;
use App\SeoChecklist\SeoChecklistItemNote;
use App\SeoChecklist\SeoChecklistProject;
use App\SeoChecklist\SeoChecklistTemplate;
use App\SeoChecklist\SeoChecklistUserPreference;
use App\Services\SeoChecklist\SeoChecklistService;
use App\Support\HomeUserSites;
use App\Support\SeoChecklistDefaultTemplate;
use App\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SeoChecklistController extends Controller
{
    /** @var SeoChecklistService */
    private $service;

    public function __construct(SeoChecklistService $service)
    {
        $this->service = $service;
    }

    /**
     * @return View|RedirectResponse
     */
    public function index(Request $request)
    {
        $userId = (int) Auth::id();
        $this->service->ensureSystemTemplate();

        $projects = $this->service->accessibleProjectsQuery($userId)
            ->where('status', 'active')
            ->with(['ownerUser', 'pmUser', 'template', 'team.members.user'])
            ->orderByDesc('last_activity_at')
            ->orderByDesc('id')
            ->get();

        $archived = SeoChecklistProject::query()
            ->where('user_id', $userId)
            ->where('status', 'archived')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();

        $sitesPayload = HomeUserSites::forUser($userId);
        $ownDomains = SeoChecklistProject::query()
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->pluck('domain')
            ->all();
        $availableDomains = [];
        foreach (($sitesPayload['sites'] ?? []) as $site) {
            $domain = (string) ($site['domain'] ?? '');
            if ($domain !== '' && !in_array($domain, $ownDomains, true)) {
                $availableDomains[] = $domain;
            }
        }

        $templates = $this->service->templatesForUser($userId);
        $accessKinds = [];
        foreach ($projects as $project) {
            $accessKinds[(int) $project->id] = $this->service->accessKind($project, $userId);
        }

        $workPlan = $this->service->workPlanForUser($userId);
        $chronicle = $this->service->chronicleForUser($userId, null, null, true, 1);

        return view('pages.seo-checklist', [
            'projects' => $projects,
            'archived' => $archived,
            'availableDomains' => $availableDomains,
            'templates' => $templates,
            'teamCount' => $this->service->teamsForUser($userId)->count(),
            'myTasksCount' => (int) ($workPlan['count'] ?? 0),
            'reviewCount' => $this->service->reviewQueueForUser($userId)->count(),
            'showReviewTab' => $this->service->canSeeReviewQueue($userId),
            'unreadNotesCount' => (int) ($chronicle['unread_count'] ?? 0),
            'accessKinds' => $accessKinds,
            'stages' => SeoChecklistDefaultTemplate::stages(),
            'teamRoleLabels' => \App\SeoChecklist\SeoChecklistTeam::roleLabels(),
        ]);
    }

    /**
     * План работ: мои ближайшие задачи по всем проектам.
     *
     * @return View|RedirectResponse
     */
    public function myTasks()
    {
        $userId = (int) Auth::id();
        $this->service->ensureSystemTemplate();

        $plan = $this->service->workPlanForUser($userId);
        $projectsCount = $this->service->accessibleProjectsQuery($userId)
            ->where('status', 'active')
            ->count();

        $chronicle = $this->service->chronicleForUser($userId, null, null, true, 1);

        return view('pages.seo-checklist-my-tasks', [
            'plan' => $plan,
            'filterProjects' => $this->service->accessibleProjectsQuery($userId)
                ->where('status', 'active')
                ->orderBy('domain')
                ->get(['id', 'domain', 'title']),
            'roleLabels' => $this->roleLabels(),
            'statusLabels' => $this->statusLabels(),
            'projectsCount' => $projectsCount,
            'teamCount' => $this->service->teamsForUser($userId)->count(),
            'templatesCount' => $this->service->templatesForUser($userId)->count(),
            'myTasksCount' => (int) ($plan['count'] ?? 0),
            'reviewCount' => $this->service->reviewQueueForUser($userId)->count(),
            'showReviewTab' => $this->service->canSeeReviewQueue($userId),
            'unreadNotesCount' => (int) ($chronicle['unread_count'] ?? 0),
        ]);
    }

    /**
     * Очередь «На проверку» для PM / аудиторов.
     *
     * @return View|RedirectResponse
     */
    public function reviewQueue()
    {
        $userId = (int) Auth::id();
        $this->service->ensureSystemTemplate();

        if (!$this->service->canSeeReviewQueue($userId)) {
            return redirect()
                ->route('pages.seo-checklist.my-tasks')
                ->with('error', __('Only PM or auditor can approve'));
        }

        $items = $this->service->reviewQueueForUser($userId);
        $plan = $this->service->workPlanForUser($userId);
        $chronicle = $this->service->chronicleForUser($userId, null, null, true, 1);

        return view('pages.seo-checklist-review', [
            'items' => $items,
            'roleLabels' => $this->roleLabels(),
            'statusLabels' => $this->statusLabels(),
            'projectsCount' => $this->service->accessibleProjectsQuery($userId)->where('status', 'active')->count(),
            'teamCount' => $this->service->teamsForUser($userId)->count(),
            'templatesCount' => $this->service->templatesForUser($userId)->count(),
            'myTasksCount' => (int) ($plan['count'] ?? 0),
            'reviewCount' => $items->count(),
            'showReviewTab' => true,
            'unreadNotesCount' => (int) ($chronicle['unread_count'] ?? 0),
        ]);
    }

    /**
     * Хроника изменений и непрочитанные заметки.
     *
     * @return View|RedirectResponse
     */
    public function chronicle(Request $request)
    {
        $userId = (int) Auth::id();
        $this->service->ensureSystemTemplate();

        $projectIds = $this->requestIdList($request, 'project_ids', 'project_id');
        $authorIds = $this->requestIdList($request, 'author_ids', 'author_id');
        $preset = $this->chroniclePresetFromRequest($request);
        $sort = $this->chronicleSortFromRequest($request);

        $data = $this->service->chronicleForUser($userId, $projectIds, $authorIds, $preset, 80, $sort);
        $plan = $this->service->workPlanForUser($userId);
        $projects = $this->service->accessibleProjectsQuery($userId)
            ->where('status', 'active')
            ->orderBy('domain')
            ->get(['id', 'domain']);

        $feedNoteIds = [];
        foreach (($data['items'] ?? collect()) as $log) {
            if (($log->type ?? '') !== 'note') {
                continue;
            }
            $meta = is_array($log->meta_json) ? $log->meta_json : [];
            $noteId = (int) ($meta['note_id'] ?? 0);
            if ($noteId > 0) {
                $feedNoteIds[] = $noteId;
            }
        }
        $readNoteIds = $this->service->readNoteIdMap($userId, $feedNoteIds);

        return view('pages.seo-checklist-chronicle', [
            'chronicle' => $data,
            'projects' => $projects,
            'authors' => $this->service->chronicleAuthorOptions($userId),
            'filterProjectIds' => $projectIds,
            'filterAuthorIds' => $authorIds,
            'filterPreset' => $preset,
            'filterSort' => $sort,
            'filterUnread' => $preset === 'unread',
            'statusLabels' => $this->statusLabels(),
            'projectsCount' => $projects->count(),
            'teamCount' => $this->service->teamsForUser($userId)->count(),
            'templatesCount' => $this->service->templatesForUser($userId)->count(),
            'myTasksCount' => (int) ($plan['count'] ?? 0),
            'reviewCount' => $this->service->reviewQueueForUser($userId)->count(),
            'showReviewTab' => $this->service->canSeeReviewQueue($userId),
            'unreadNotesCount' => (int) ($data['unread_count'] ?? 0),
            'unreadPrefs' => $data['unread_prefs'] ?? \App\SeoChecklist\SeoChecklistUserPreference::chronicleUnreadPrefsFor($userId),
            'readNoteIds' => $readNoteIds,
            'authUserId' => $userId,
        ]);
    }

    private function chroniclePresetFromRequest(Request $request): string
    {
        // Legacy: ?unread=1
        if ($request->has('unread') && $request->boolean('unread')) {
            return 'unread';
        }

        $preset = (string) $request->input('view', 'unread');
        if (!in_array($preset, ['unread', 'notes', 'all'], true)) {
            return 'unread';
        }

        return $preset;
    }

    private function chronicleSortFromRequest(Request $request): string
    {
        $sort = strtolower((string) $request->input('sort', 'desc'));

        return $sort === 'asc' ? 'asc' : 'desc';
    }

    /**
     * @return JsonResponse|RedirectResponse
     */
    public function markChronicleNotesRead(Request $request)
    {
        $userId = (int) Auth::id();
        if ($request->boolean('all')) {
            $marked = $this->service->markAllUnreadNotesRead($userId);
        } else {
            $noteIds = $request->input('note_ids', []);
            if (!is_array($noteIds)) {
                $noteIds = [];
            }
            $activityIds = $request->input('activity_ids', []);
            if (!is_array($activityIds)) {
                $activityIds = [];
            }
            $marked = $this->service->markNotesRead($userId, $noteIds)
                + $this->service->markActivitiesRead($userId, $activityIds);
        }

        $unreadCount = (int) ($this->service->chronicleForUser($userId, null, null, true, 1)['unread_count'] ?? 0);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'ok' => true,
                'marked' => (int) $marked,
                'unread_count' => $unreadCount,
                'message' => __('Notes marked as read'),
            ]);
        }

        $query = [];
        foreach ($this->requestIdList($request, 'project_ids', 'project_id') as $id) {
            $query['project_ids'][] = $id;
        }
        foreach ($this->requestIdList($request, 'author_ids', 'author_id') as $id) {
            $query['author_ids'][] = $id;
        }
        $preset = $this->chroniclePresetFromRequest($request);
        $query['view'] = $preset;
        $query['sort'] = $this->chronicleSortFromRequest($request);

        return redirect()
            ->route('pages.seo-checklist.chronicle', $query)
            ->with('success', __('Notes marked as read'));
    }

    /**
     * @return JsonResponse|RedirectResponse
     */
    public function markChronicleNotesUnread(Request $request)
    {
        $userId = (int) Auth::id();
        $noteIds = $request->input('note_ids', []);
        if (!is_array($noteIds)) {
            $noteIds = [];
        }
        $activityIds = $request->input('activity_ids', []);
        if (!is_array($activityIds)) {
            $activityIds = [];
        }
        $marked = $this->service->markNotesUnread($userId, $noteIds)
            + $this->service->markActivitiesUnread($userId, $activityIds);
        $unreadCount = (int) ($this->service->chronicleForUser($userId, null, null, true, 1)['unread_count'] ?? 0);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'ok' => true,
                'marked' => (int) $marked,
                'unread_count' => $unreadCount,
                'message' => __('Notes marked as unread'),
            ]);
        }

        $query = [];
        foreach ($this->requestIdList($request, 'project_ids', 'project_id') as $id) {
            $query['project_ids'][] = $id;
        }
        foreach ($this->requestIdList($request, 'author_ids', 'author_id') as $id) {
            $query['author_ids'][] = $id;
        }
        $preset = $this->chroniclePresetFromRequest($request);
        $query['view'] = $preset;
        $query['sort'] = $this->chronicleSortFromRequest($request);

        return redirect()
            ->route('pages.seo-checklist.chronicle', $query)
            ->with('success', __('Notes marked as unread'));
    }

    public function updateChronicleUnreadPrefs(Request $request): RedirectResponse
    {
        $prefs = SeoChecklistUserPreference::saveChronicleUnreadPrefs((int) Auth::id(), [
            'notes' => $request->boolean('unread_notes'),
            'review' => $request->boolean('unread_review'),
            'created' => $request->boolean('unread_created'),
        ]);

        $query = ['view' => 'unread'];
        foreach ($this->requestIdList($request, 'project_ids', 'project_id') as $id) {
            $query['project_ids'][] = $id;
        }
        foreach ($this->requestIdList($request, 'author_ids', 'author_id') as $id) {
            $query['author_ids'][] = $id;
        }
        if ($request->filled('sort')) {
            $query['sort'] = $this->chronicleSortFromRequest($request);
        }

        $enabled = [];
        if ($prefs['notes']) {
            $enabled[] = __('Chronicle unread pref notes');
        }
        if ($prefs['review']) {
            $enabled[] = __('Chronicle unread pref review');
        }
        if ($prefs['created']) {
            $enabled[] = __('Chronicle unread pref created');
        }
        $message = $enabled === []
            ? __('Chronicle unread prefs cleared')
            : __('Chronicle unread prefs saved');

        return redirect()
            ->route('pages.seo-checklist.chronicle', $query)
            ->with('success', $message);
    }

    public function updateModuleTitle(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'module_title' => 'nullable|string|max:40',
        ]);

        SeoChecklistUserPreference::saveModuleTitle((int) Auth::id(), $data['module_title'] ?? null);
        // Меню сайдбара кэширует подписи в сессии
        session()->forget([
            'cabinet_menu_modules_v9',
            'cabinet_menu_modules_v9_stamp',
            'cabinet_menu_modules_v10',
            'cabinet_menu_modules_v10_stamp',
            'cabinet_home_modules_flat',
            'cabinet_home_modules_flat_v2',
        ]);

        return back()->with('success', __('Section name saved'));
    }

    /**
     * Учёт времени по дням.
     *
     * @return View|RedirectResponse
     */
    public function timesheet(Request $request)
    {
        $userId = (int) Auth::id();
        $this->service->ensureSystemTemplate();

        [$from, $to, $period] = $this->timesheetPeriodFromRequest($request);
        $projectIds = $this->requestIdList($request, 'project_ids', 'project_id');
        $userIds = $this->requestIdList($request, 'user_ids', 'user_id');
        $groupBy = (string) $request->input('group', 'day');
        if (!in_array($groupBy, ['day', 'task'], true)) {
            $groupBy = 'day';
        }

        $canTeam = $this->service->canViewTeamTimesheet($userId);
        $authors = $canTeam ? $this->service->timesheetAuthorOptions($userId) : collect();
        $data = $this->service->timesheetForUser($userId, $from, $to, $projectIds, $groupBy, $userIds);
        $plan = $this->service->workPlanForUser($userId);
        $projects = $this->service->accessibleProjectsQuery($userId)
            ->where('status', 'active')
            ->orderBy('domain')
            ->get(['id', 'domain']);
        $chronicle = $this->service->chronicleForUser($userId, null, null, true, 1);

        return view('pages.seo-checklist-timesheet', [
            'timesheet' => $data,
            'projects' => $projects,
            'authors' => $authors,
            'canTeamTimesheet' => $canTeam,
            'filterFrom' => $from,
            'filterTo' => $to,
            'filterPeriod' => $period,
            'filterProjectIds' => $projectIds,
            'filterUserIds' => $userIds,
            'filterGroup' => $groupBy,
            'projectsCount' => $projects->count(),
            'teamCount' => $this->service->teamsForUser($userId)->count(),
            'templatesCount' => $this->service->templatesForUser($userId)->count(),
            'myTasksCount' => (int) ($plan['count'] ?? 0),
            'reviewCount' => $this->service->reviewQueueForUser($userId)->count(),
            'showReviewTab' => $this->service->canSeeReviewQueue($userId),
            'unreadNotesCount' => (int) ($chronicle['unread_count'] ?? 0),
        ]);
    }

    public function timesheetExport(Request $request)
    {
        $userId = (int) Auth::id();
        $this->service->ensureSystemTemplate();

        [$from, $to] = $this->timesheetPeriodFromRequest($request);
        $projectIds = $this->requestIdList($request, 'project_ids', 'project_id');
        $userIds = $this->requestIdList($request, 'user_ids', 'user_id');
        $rows = $this->service->timesheetExportRows($userId, $from, $to, $projectIds, $userIds);

        $filename = 'timesheet-' . $from . '-' . $to . '.csv';
        $callback = static function () use ($rows) {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($out, ['date', 'user', 'domain', 'task', 'seconds', 'duration', 'pct'], ';');
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['date'],
                    $row['user'],
                    $row['domain'],
                    $row['title'],
                    $row['seconds'],
                    $row['duration'],
                    $row['pct'],
                ], ';');
            }
            fclose($out);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * @return array{0:string,1:string,2:string}
     */
    private function timesheetPeriodFromRequest(Request $request): array
    {
        $period = (string) $request->input('period', '');
        if (!in_array($period, ['today', 'week', 'month', '30d', 'custom'], true)) {
            $period = $request->filled('from') || $request->filled('to') ? 'custom' : '30d';
        }

        if ($period === 'today') {
            $day = now()->toDateString();

            return [$day, $day, $period];
        }
        if ($period === 'week') {
            return [now()->startOfWeek()->toDateString(), now()->toDateString(), $period];
        }
        if ($period === 'month') {
            return [now()->startOfMonth()->toDateString(), now()->toDateString(), $period];
        }
        if ($period === '30d') {
            return [now()->subDays(30)->toDateString(), now()->toDateString(), $period];
        }

        $from = $request->input('from') ?: now()->subDays(30)->toDateString();
        $to = $request->input('to') ?: now()->toDateString();

        return [$from, $to, 'custom'];
    }

    public function itemTimeBreakdown(int $id, int $itemId): JsonResponse
    {
        $project = $this->findAccessibleProject($id);
        if (!$project) {
            return response()->json(['ok' => false, 'message' => __('Project not found')], 404);
        }
        $item = SeoChecklistItem::query()
            ->where('project_id', $project->id)
            ->where('id', $itemId)
            ->whereNull('parent_id')
            ->first();
        if (!$item) {
            return response()->json(['ok' => false, 'message' => __('Task not found')], 404);
        }

        $data = $this->service->itemTimeByDay($item);

        return response()->json([
            'ok' => true,
            'total' => $data['total'],
            'total_formatted' => SeoChecklistService::formatDuration((int) $data['total']),
            'days' => $data['days'],
        ]);
    }

    /**
     * @return View|RedirectResponse
     */
    public function team()
    {
        $userId = (int) Auth::id();
        $this->service->ensureSystemTemplate();

        $projectsCount = $this->service->accessibleProjectsQuery($userId)
            ->where('status', 'active')
            ->count();

        $teams = $this->service->teamsForUser($userId);
        $projects = SeoChecklistProject::query()
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->with(['team', 'ownerUser', 'pmUser'])
            ->orderBy('domain')
            ->get();

        return view('pages.seo-checklist-team', [
            'teams' => $teams,
            'projects' => $projects,
            'projectsCount' => $projectsCount,
            'templatesCount' => $this->service->templatesForUser($userId)->count(),
            'myTasksCount' => (int) ($this->service->dueAlertsForUser($userId)['count'] ?? 0),
            'reviewCount' => $this->service->reviewQueueForUser($userId)->count(),
            'showReviewTab' => $this->service->canSeeReviewQueue($userId),
            'unreadNotesCount' => (int) ($this->service->chronicleForUser($userId, null, null, true, 1)['unread_count'] ?? 0),
            'teamRoleLabels' => \App\SeoChecklist\SeoChecklistTeam::roleLabels(),
            'teamCandidates' => $this->service->teamCandidates($userId),
        ]);
    }

    public function storeTeam(Request $request): RedirectResponse
    {
        $userId = (int) Auth::id();
        $result = $this->service->createTeam(
            $userId,
            (string) $request->input('title', ''),
            $request->input('description')
        );

        if (empty($result['ok']) || empty($result['team'])) {
            return $this->teamRedirect(
                $request,
                'error',
                $result['message'] ?? __('Error')
            );
        }

        $memberErrors = [];
        $members = $request->input('members', []);
        if (! is_array($members)) {
            $members = [];
        }

        // Один участник из «черновика» формы, если JS не успел сложить в members[]
        if ($members === [] && ($request->filled('user_id') || $request->filled('email'))) {
            $members[] = [
                'user_id' => $request->input('user_id'),
                'email' => $request->input('email'),
                'role' => $request->input('role', 'participant'),
            ];
        }

        foreach ($members as $row) {
            if (! is_array($row)) {
                continue;
            }
            $role = (string) ($row['role'] ?? 'participant');
            $email = trim((string) ($row['email'] ?? ''));
            $memberUserId = (int) ($row['user_id'] ?? 0);

            if ($memberUserId > 0) {
                $add = $this->service->addTeamMember($result['team'], $memberUserId, $role);
            } elseif ($email !== '') {
                $add = $this->service->addTeamMemberByEmail($result['team'], $email, $role);
            } else {
                continue;
            }

            if (empty($add['ok'])) {
                $who = $email !== '' ? $email : ('#' . $memberUserId);
                $memberErrors[] = $who . ': ' . ($add['message'] ?? __('Error'));
            }
        }

        if ($memberErrors !== []) {
            return $this->teamRedirect(
                $request,
                'error',
                __('SEO checklist team created') . ' Часть участников не добавлена: ' . implode('; ', $memberErrors)
            );
        }

        return $this->teamRedirect($request, 'success', __('SEO checklist team created'));
    }

    public function updateTeamMeta(Request $request, int $teamId): RedirectResponse
    {
        $team = $this->service->findOwnedTeam((int) Auth::id(), $teamId);
        if (!$team) {
            return $this->teamRedirect($request, 'error', __('Team not found'));
        }

        $result = $this->service->updateTeam(
            $team,
            (string) $request->input('title', ''),
            $request->input('description')
        );

        return $this->teamRedirect(
            $request,
            $result['ok'] ? 'success' : 'error',
            $result['ok'] ? __('Saved') : ($result['message'] ?? __('Error'))
        );
    }

    public function destroyTeam(Request $request, int $teamId): RedirectResponse
    {
        $team = $this->service->findOwnedTeam((int) Auth::id(), $teamId);
        if (!$team) {
            return $this->teamRedirect($request, 'error', __('Team not found'));
        }

        $result = $this->service->deleteTeam($team);

        return $this->teamRedirect(
            $request,
            $result['ok'] ? 'success' : 'error',
            $result['ok'] ? __('SEO checklist team deleted') : ($result['message'] ?? __('Error'))
        );
    }

    public function storeTeamMember(Request $request, int $teamId): RedirectResponse
    {
        $team = $this->service->findOwnedTeam((int) Auth::id(), $teamId);
        if (!$team) {
            return $this->teamRedirect($request, 'error', __('Team not found'));
        }

        $role = (string) $request->input('role', 'participant');
        $email = trim((string) $request->input('email', ''));
        if ($email !== '') {
            $result = $this->service->addTeamMemberByEmail($team, $email, $role);
        } elseif ($request->filled('user_id')) {
            $result = $this->service->addTeamMember($team, (int) $request->input('user_id'), $role);
        } else {
            $result = ['ok' => false, 'message' => __('User not found')];
        }

        return $this->teamRedirect(
            $request,
            $result['ok'] ? 'success' : 'error',
            $result['ok'] ? __('Member added') : ($result['message'] ?? __('Error'))
        );
    }

    public function updateTeamMember(Request $request, int $teamId, int $memberId): RedirectResponse
    {
        $team = $this->service->findOwnedTeam((int) Auth::id(), $teamId);
        if (!$team) {
            return $this->teamRedirect($request, 'error', __('Team not found'));
        }

        $result = $this->service->updateTeamMemberRole($team, $memberId, (string) $request->input('role', 'participant'));

        return $this->teamRedirect(
            $request,
            $result['ok'] ? 'success' : 'error',
            $result['ok'] ? __('Saved') : ($result['message'] ?? __('Error'))
        );
    }

    public function destroyTeamMember(Request $request, int $teamId, int $memberId): RedirectResponse
    {
        $team = $this->service->findOwnedTeam((int) Auth::id(), $teamId);
        if (!$team) {
            return $this->teamRedirect($request, 'error', __('Team not found'));
        }

        $result = $this->service->removeTeamMember($team, $memberId);

        return $this->teamRedirect(
            $request,
            $result['ok'] ? 'success' : 'error',
            $result['ok'] ? __('Member removed') : ($result['message'] ?? __('Error'))
        );
    }

    public function assignProjectTeam(Request $request, int $id): RedirectResponse
    {
        $project = $this->findManageableProject($id);
        if (!$project) {
            return $this->teamRedirect($request, 'error', __('Project not found'));
        }

        $teamId = $request->filled('team_id') ? (int) $request->input('team_id') : null;
        if ($teamId === 0) {
            $teamId = null;
        }
        $result = $this->service->assignTeamToProject($project, $teamId);

        if ((string) $request->input('return_to') === 'show') {
            return redirect()
                ->route('pages.seo-checklist.show', ['id' => $project->id])
                ->with($result['ok'] ? 'success' : 'error', $result['ok'] ? __('SEO checklist team assigned') : ($result['message'] ?? __('Error')));
        }

        return $this->teamRedirect(
            $request,
            $result['ok'] ? 'success' : 'error',
            $result['ok'] ? __('SEO checklist team assigned') : ($result['message'] ?? __('Error'))
        );
    }

    private function teamRedirect(Request $request, string $flashKey, string $message): RedirectResponse
    {
        $returnTo = (string) $request->input('return_to');

        if ($returnTo === 'profile') {
            return redirect()
                ->to(route('profile.index') . '#team')
                ->with($flashKey === 'success' ? 'status' : $flashKey, $message);
        }

        if ($returnTo === 'site-audit') {
            return redirect()
                ->to(route('pages.site-audit') . '#sa-projects')
                ->with($flashKey === 'success' ? 'status' : 'error', $message);
        }

        return redirect()
            ->route('pages.seo-checklist.team')
            ->with($flashKey, $message);
    }

    public function store(Request $request): RedirectResponse
    {
        $userId = (int) Auth::id();
        $domain = (string) $request->input('domain', '');
        $templateId = $request->filled('template_id') ? (int) $request->input('template_id') : null;
        $result = $this->service->createProject($userId, $domain, null, null, $templateId);

        if (empty($result['ok']) || empty($result['project'])) {
            return redirect()
                ->route('pages.seo-checklist')
                ->with('error', $result['message'] ?? __('Could not create SEO checklist'));
        }

        return redirect()
            ->route('pages.seo-checklist.show', ['id' => $result['project']->id])
            ->with('success', __('SEO checklist created'));
    }

    /**
     * @return View|RedirectResponse
     */
    public function templates()
    {
        $userId = (int) Auth::id();
        $templates = $this->service->templatesForUser($userId);

        $projectsCount = $this->service->accessibleProjectsQuery($userId)
            ->where('status', 'active')
            ->count();

        return view('pages.seo-checklist-templates', [
            'templates' => $templates,
            'systemTemplate' => SeoChecklistTemplate::systemDefault(),
            'projectsCount' => $projectsCount,
            'teamCount' => $this->service->teamsForUser($userId)->count(),
            'myTasksCount' => (int) ($this->service->dueAlertsForUser($userId)['count'] ?? 0),
            'reviewCount' => $this->service->reviewQueueForUser($userId)->count(),
            'showReviewTab' => $this->service->canSeeReviewQueue($userId),
            'unreadNotesCount' => (int) ($this->service->chronicleForUser($userId, null, null, true, 1)['unread_count'] ?? 0),
        ]);
    }

    public function storeTemplate(Request $request): RedirectResponse
    {
        $userId = (int) Auth::id();
        $title = (string) $request->input('title', '');
        $preset = (string) $request->input('preset', 'skeleton');
        $sourceId = $request->filled('source_id') ? (int) $request->input('source_id') : null;

        if ($preset === 'clone' || $sourceId) {
            $result = $this->service->cloneTemplate($userId, $title, $sourceId);
        } else {
            $result = $this->service->createEmptyTemplate(
                $userId,
                $title,
                $request->input('description'),
                $preset === 'empty' ? 'empty' : 'skeleton'
            );
        }

        if (empty($result['ok']) || empty($result['template'])) {
            return redirect()
                ->route('pages.seo-checklist.templates')
                ->with('error', $result['message'] ?? __('Could not create template'));
        }

        return redirect()
            ->route('pages.seo-checklist.templates.edit', ['templateId' => $result['template']->id])
            ->with('success', __('SEO checklist template created'));
    }

    public function duplicateTemplate(int $templateId): RedirectResponse
    {
        $userId = (int) Auth::id();
        $source = $this->findUsableTemplateModel($templateId);
        if (!$source) {
            return redirect()->route('pages.seo-checklist.templates')->with('error', __('Template not found'));
        }

        $title = trim($source->title) . ' (' . __('Copy') . ')';
        $result = $this->service->cloneTemplate($userId, $title, (int) $source->id);

        if (empty($result['ok']) || empty($result['template'])) {
            return redirect()
                ->route('pages.seo-checklist.templates')
                ->with('error', $result['message'] ?? __('Could not create template'));
        }

        return redirect()
            ->route('pages.seo-checklist.templates.edit', ['templateId' => $result['template']->id])
            ->with('success', __('SEO checklist template created'));
    }

    /**
     * @return View|RedirectResponse
     */
    public function editTemplate(int $templateId)
    {
        $this->service->ensureSystemTemplate();

        $template = $this->findUsableTemplateModel($templateId);
        if (!$template) {
            return redirect()->route('pages.seo-checklist.templates')->with('error', __('Template not found'));
        }

        $template->load(['tasks' => function ($q) {
            $q->whereNull('parent_id')->with('children');
        }]);
        $stagesMeta = $this->service->resolveTemplateStages($template);
        $grouped = [];
        foreach ($stagesMeta as $key => $meta) {
            $grouped[$key] = [
                'key' => $key,
                'title' => $meta['title'],
                'lead' => $meta['lead'] ?? null,
                'sort' => (int) $meta['sort'],
                'tasks' => [],
            ];
        }
        foreach ($template->tasks as $task) {
            $key = $task->stage_key;
            if ($key === 'connect') {
                $key = 'access';
            }
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'key' => $key,
                    'title' => $stagesMeta[$key]['title'] ?? SeoChecklistDefaultTemplate::stageTitle($key),
                    'lead' => $stagesMeta[$key]['lead'] ?? null,
                    'sort' => (int) ($stagesMeta[$key]['sort'] ?? $task->stage_sort),
                    'tasks' => [],
                ];
            }
            $grouped[$key]['tasks'][] = $task;
        }
        foreach ($grouped as &$stageGroup) {
            usort($stageGroup['tasks'], static function ($a, $b) {
                $bySort = ((int) $a->sort) <=> ((int) $b->sort);
                return $bySort !== 0 ? $bySort : ((int) $a->id <=> (int) $b->id);
            });
        }
        unset($stageGroup);
        uasort($grouped, static function ($a, $b) {
            return $a['sort'] <=> $b['sort'];
        });

        $userId = (int) Auth::id();

        return view('pages.seo-checklist-template-edit', [
            'template' => $template,
            'stages' => array_values($grouped),
            'stagesMeta' => $stagesMeta,
            'roleLabels' => $this->roleLabels(),
            'readOnly' => !$this->service->canEditTemplate($template),
            'projectsCount' => SeoChecklistProject::query()
                ->where('user_id', $userId)
                ->where('status', 'active')
                ->count(),
            'teamCount' => $this->service->teamsForUser($userId)->count(),
            'templatesCount' => $this->service->templatesForUser($userId)->count(),
            'myTasksCount' => (int) ($this->service->dueAlertsForUser($userId)['count'] ?? 0),
            'reviewCount' => $this->service->reviewQueueForUser($userId)->count(),
            'showReviewTab' => $this->service->canSeeReviewQueue($userId),
            'unreadNotesCount' => (int) ($this->service->chronicleForUser($userId, null, null, true, 1)['unread_count'] ?? 0),
            'usageCount' => SeoChecklistProject::query()->where('template_id', $template->id)->count(),
            'isAdmin' => \App\User::isUserAdmin(),
        ]);
    }

    public function updateTemplate(Request $request, int $templateId): RedirectResponse
    {
        $template = $this->findOwnedCustomTemplate($templateId);
        if (!$template) {
            return redirect()->route('pages.seo-checklist.templates')->with('error', __('Template not found'));
        }

        $result = $this->service->updateTemplate(
            $template,
            (string) $request->input('title', ''),
            $request->input('description'),
            $request->boolean('skip_weekends')
        );

        return redirect()
            ->route('pages.seo-checklist.templates.edit', ['templateId' => $template->id])
            ->with($result['ok'] ? 'success' : 'error', $result['ok'] ? __('Saved') : ($result['message'] ?? __('Error')));
    }

    public function destroyTemplate(int $templateId): RedirectResponse
    {
        $template = $this->findOwnedCustomTemplate($templateId);
        if (!$template) {
            return redirect()->route('pages.seo-checklist.templates')->with('error', __('Template not found'));
        }

        $result = $this->service->deleteTemplate($template);

        return redirect()
            ->route('pages.seo-checklist.templates')
            ->with($result['ok'] ? 'success' : 'error', $result['ok'] ? __('SEO checklist template deleted') : ($result['message'] ?? __('Error')));
    }

    public function updateTemplateTask(Request $request, int $templateId, int $taskId)
    {
        $template = $this->findOwnedCustomTemplate($templateId);
        if (!$template) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['ok' => false, 'message' => __('Template not found')], 404);
            }

            return redirect()->route('pages.seo-checklist.templates')->with('error', __('Template not found'));
        }

        // Быстрый toggle «В отчёты» для пунктов шаблона (без полной формы).
        if (($request->ajax() || $request->wantsJson())
            && $request->exists('include_in_report')
            && !$request->exists('title')
        ) {
            /** @var \App\SeoChecklist\SeoChecklistTemplateTask|null $task */
            $task = $template->tasks()->where('id', $taskId)->first();
            if (!$task) {
                return response()->json(['ok' => false, 'message' => __('Task not found')], 404);
            }
            if ($template->is_system && !\App\User::isUserAdmin()) {
                return response()->json(['ok' => false, 'message' => __('System template is read-only')], 403);
            }
            $task->forceFill([
                'include_in_report' => (bool) $request->input('include_in_report'),
            ])->save();

            return response()->json([
                'ok' => true,
                'include_in_report' => (bool) $task->include_in_report,
            ]);
        }

        $result = $this->service->updateTemplateTask($template, $taskId, [
            'title' => $request->input('title'),
            'help' => $request->input('help'),
            'role' => $request->input('role'),
            'is_important' => $request->has('is_important'),
            'include_in_report' => $request->has('include_in_report'),
            'repeat_rule' => $request->input('repeat_rule'),
            'due_days_from_start' => $request->input('due_days_from_start'),
        ]);

        return redirect()
            ->route('pages.seo-checklist.templates.edit', ['templateId' => $template->id])
            ->with($result['ok'] ? 'success' : 'error', $result['ok'] ? __('Saved') : ($result['message'] ?? __('Error')));
    }

    public function destroyTemplateTask(Request $request, int $templateId, int $taskId)
    {
        $template = $this->findOwnedCustomTemplate($templateId);
        if (!$template) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['ok' => false, 'message' => __('Template not found')], 404);
            }

            return redirect()->route('pages.seo-checklist.templates')->with('error', __('Template not found'));
        }

        $result = $this->service->deleteTemplateTask($template, $taskId);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'ok' => !empty($result['ok']),
                'message' => $result['message'] ?? null,
            ], !empty($result['ok']) ? 200 : 422);
        }

        return redirect()
            ->route('pages.seo-checklist.templates.edit', ['templateId' => $template->id])
            ->with($result['ok'] ? 'success' : 'error', $result['ok'] ? __('Task deleted') : ($result['message'] ?? __('Error')));
    }

    public function storeTemplateSubtask(Request $request, int $templateId, int $taskId): JsonResponse
    {
        $template = $this->findOwnedCustomTemplate($templateId);
        if (!$template) {
            return response()->json(['ok' => false, 'message' => __('Template not found')], 404);
        }

        $result = $this->service->addTemplateSubtask(
            $template,
            $taskId,
            (string) $request->input('title', ''),
            ['include_in_report' => $request->boolean('include_in_report')]
        );

        if (empty($result['ok']) || empty($result['task'])) {
            return response()->json(['ok' => false, 'message' => $result['message'] ?? __('Error')], 422);
        }

        $child = $result['task'];

        return response()->json([
            'ok' => true,
            'task' => [
                'id' => $child->id,
                'title' => $child->title,
                'parent_id' => $child->parent_id,
                'include_in_report' => (bool) $child->include_in_report,
                'update_url' => route('pages.seo-checklist.templates.task.update', [
                    'templateId' => $template->id,
                    'taskId' => $child->id,
                ]),
                'delete_url' => route('pages.seo-checklist.templates.task.delete', [
                    'templateId' => $template->id,
                    'taskId' => $child->id,
                ]),
            ],
        ]);
    }

    public function moveTemplateTask(Request $request, int $templateId, int $taskId)
    {
        $template = $this->findOwnedCustomTemplate($templateId);
        if (!$template) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['ok' => false, 'message' => __('Template not found')], 404);
            }

            return redirect()->route('pages.seo-checklist.templates')->with('error', __('Template not found'));
        }

        $orderedIds = $request->input('ordered_ids');
        if (is_array($orderedIds)) {
            $result = $this->service->reorderTemplateTasksByIds($template, $taskId, $orderedIds);
        } elseif ($request->exists('before_id')) {
            $beforeRaw = $request->input('before_id');
            $beforeId = ($beforeRaw === null || $beforeRaw === '' || $beforeRaw === 'null')
                ? null
                : (int) $beforeRaw;
            $result = $this->service->reorderTemplateTask($template, $taskId, $beforeId);
        } else {
            $result = $this->service->moveTemplateTask(
                $template,
                $taskId,
                (string) $request->input('direction', '')
            );
        }

        if ($request->ajax() || $request->wantsJson()) {
            if (empty($result['ok'])) {
                return response()->json(['ok' => false, 'message' => $result['message'] ?? __('Error')], 422);
            }

            return response()->json([
                'ok' => true,
                'task_id' => $taskId,
                'direction' => (string) $request->input('direction', ''),
                'before_id' => $request->exists('before_id') ? $request->input('before_id') : null,
                'ordered_ids' => is_array($orderedIds) ? array_values(array_map('intval', $orderedIds)) : null,
            ]);
        }

        return redirect()
            ->to(route('pages.seo-checklist.templates.edit', ['templateId' => $template->id]) . '#tpl-task-' . $taskId)
            ->with($result['ok'] ? 'success' : 'error', $result['ok'] ? __('Order updated') : ($result['message'] ?? __('Error')));
    }

    public function storeTemplateTask(Request $request, int $templateId)
    {
        $template = $this->findOwnedCustomTemplate($templateId);
        if (!$template) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['ok' => false, 'message' => __('Template not found')], 404);
            }

            return redirect()->route('pages.seo-checklist.templates')->with('error', __('Template not found'));
        }

        $result = $this->service->addTemplateTask($template, [
            'title' => $request->input('title'),
            'help' => $request->input('help'),
            'stage_key' => $request->input('stage_key'),
            'role' => $request->input('role'),
            'is_important' => $request->boolean('is_important') || $request->has('is_important'),
            'include_in_report' => $request->boolean('include_in_report') || $request->has('include_in_report'),
            'allows_subtasks' => $request->has('allows_subtasks'),
            'repeat_rule' => $request->input('repeat_rule'),
            'due_days_from_start' => $request->input('due_days_from_start'),
        ]);

        if ($request->ajax() || $request->wantsJson()) {
            if (empty($result['ok']) || empty($result['task'])) {
                return response()->json(['ok' => false, 'message' => $result['message'] ?? __('Error')], 422);
            }

            /** @var \App\SeoChecklist\SeoChecklistTemplateTask $task */
            $task = $result['task'];
            $task->setRelation('children', collect());

            $stageKey = (string) $task->stage_key;
            $siblings = $template->tasks()
                ->where('stage_key', $stageKey)
                ->whereNull('parent_id')
                ->orderBy('sort')
                ->orderBy('id')
                ->get(['id']);
            $ids = $siblings->pluck('id')->map(static function ($id) {
                return (int) $id;
            })->values()->all();
            $taskIndex = array_search((int) $task->id, $ids, true);
            if ($taskIndex === false) {
                $taskIndex = max(0, count($ids) - 1);
            }

            $html = view('pages.partials.seo-checklist-template-task-row', [
                'template' => $template,
                'task' => $task,
                'taskIndex' => (int) $taskIndex,
                'isFirstInStage' => (int) $taskIndex === 0,
                'isLastInStage' => (int) $taskIndex === count($ids) - 1,
                'roleLabels' => $this->roleLabels(),
                'stageSearch' => '',
                'readOnly' => false,
            ])->render();

            return response()->json([
                'ok' => true,
                'message' => __('Task added'),
                'task_id' => (int) $task->id,
                'stage_key' => $stageKey,
                'stage_task_count' => count($ids),
                'html' => $html,
            ]);
        }

        return redirect()
            ->route('pages.seo-checklist.templates.edit', ['templateId' => $template->id])
            ->with($result['ok'] ? 'success' : 'error', $result['ok'] ? __('Task added') : ($result['message'] ?? __('Error')));
    }

    public function storeTemplateStage(Request $request, int $templateId): RedirectResponse
    {
        $template = $this->findOwnedCustomTemplate($templateId);
        if (!$template) {
            return redirect()->route('pages.seo-checklist.templates')->with('error', __('Template not found'));
        }

        $result = $this->service->addTemplateStage(
            $template,
            (string) $request->input('title', ''),
            $request->input('lead')
        );

        return redirect()
            ->route('pages.seo-checklist.templates.edit', ['templateId' => $template->id])
            ->with($result['ok'] ? 'success' : 'error', $result['ok'] ? __('Stage added') : ($result['message'] ?? __('Error')));
    }

    public function applyTemplateSkeleton(int $templateId): RedirectResponse
    {
        $template = $this->findOwnedCustomTemplate($templateId);
        if (!$template) {
            return redirect()->route('pages.seo-checklist.templates')->with('error', __('Template not found'));
        }

        $result = $this->service->applySkeletonStages($template);

        return redirect()
            ->route('pages.seo-checklist.templates.edit', ['templateId' => $template->id])
            ->with($result['ok'] ? 'success' : 'error', $result['ok'] ? __('SEO skeleton applied') : ($result['message'] ?? __('Error')));
    }

    public function updateTemplateStage(Request $request, int $templateId, string $stageKey)
    {
        $template = $this->findOwnedCustomTemplate($templateId);
        if (!$template) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['ok' => false, 'message' => __('Template not found')], 404);
            }

            return redirect()->route('pages.seo-checklist.templates')->with('error', __('Template not found'));
        }

        $result = $this->service->updateTemplateStage(
            $template,
            $stageKey,
            (string) $request->input('title', ''),
            $request->input('lead')
        );

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'ok' => !empty($result['ok']),
                'message' => $result['message'] ?? null,
            ], !empty($result['ok']) ? 200 : 422);
        }

        return redirect()
            ->route('pages.seo-checklist.templates.edit', ['templateId' => $template->id])
            ->with($result['ok'] ? 'success' : 'error', $result['ok'] ? __('Saved') : ($result['message'] ?? __('Error')));
    }

    public function moveTemplateStage(Request $request, int $templateId, string $stageKey)
    {
        $template = $this->findOwnedCustomTemplate($templateId);
        if (!$template) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['ok' => false, 'message' => __('Template not found')], 404);
            }

            return redirect()->route('pages.seo-checklist.templates')->with('error', __('Template not found'));
        }

        $result = $this->service->moveTemplateStage(
            $template,
            $stageKey,
            (string) $request->input('direction', '')
        );

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'ok' => !empty($result['ok']),
                'message' => $result['message'] ?? null,
                'direction' => (string) $request->input('direction', ''),
            ], !empty($result['ok']) ? 200 : 422);
        }

        return redirect()
            ->route('pages.seo-checklist.templates.edit', ['templateId' => $template->id])
            ->with($result['ok'] ? 'success' : 'error', $result['ok'] ? __('Order updated') : ($result['message'] ?? __('Error')));
    }

    public function destroyTemplateStage(Request $request, int $templateId, string $stageKey)
    {
        $template = $this->findOwnedCustomTemplate($templateId);
        if (!$template) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['ok' => false, 'message' => __('Template not found')], 404);
            }

            return redirect()->route('pages.seo-checklist.templates')->with('error', __('Template not found'));
        }

        $result = $this->service->deleteTemplateStage($template, $stageKey);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'ok' => !empty($result['ok']),
                'message' => $result['message'] ?? null,
            ], !empty($result['ok']) ? 200 : 422);
        }

        return redirect()
            ->route('pages.seo-checklist.templates.edit', ['templateId' => $template->id])
            ->with($result['ok'] ? 'success' : 'error', $result['ok'] ? __('Stage deleted') : ($result['message'] ?? __('Error')));
    }

    /**
     * @return View|RedirectResponse
     */
    public function show(int $id)
    {
        $authId = (int) Auth::id();
        $project = $this->findAccessibleProject($id);
        if (!$project) {
            return redirect()->route('pages.seo-checklist')->with('error', __('Project not found'));
        }

        $this->service->syncAutoStatuses($project, $authId);
        $project->refresh();
        $project->load(['ownerUser', 'pmUser', 'team.members.user']);
        $noteWith = ['notes.user'];
        if (\App\SeoChecklist\SeoChecklistNoteRead::tableReady()) {
            $noteWith['notes.reads'] = function ($q) use ($authId) {
                $q->where('user_id', $authId);
            };
        }
        $items = $project->items()->whereNull('parent_id')->with(array_merge($noteWith, [
            'createdByUser',
            'doneByUser',
            'children.createdByUser',
            'children.doneByUser',
            'children.timeLogs' => function ($q) use ($authId) {
                $q->where('user_id', $authId)->whereNull('ended_at')->orderByDesc('id');
            },
            'timeLogs' => function ($q) use ($authId) {
                $q->where('user_id', $authId)->whereNull('ended_at')->orderByDesc('id');
            },
        ]))->get();
        $this->service->attachStatusAuditLabels($items);
        $project->loadMissing('template');
        $stagesMeta = $this->service->resolveTemplateStages($project->template);
        $grouped = [];
        $roleStats = [
            'owner' => ['done' => 0, 'total' => 0],
            'pm' => ['done' => 0, 'total' => 0],
            'shared' => ['done' => 0, 'total' => 0],
            'any' => ['done' => 0, 'total' => 0],
        ];
        $projectReviewCount = 0;
        foreach ($items as $item) {
            $key = $item->stage_key === 'connect' ? 'access' : $item->stage_key;
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'key' => $key,
                    'title' => $stagesMeta[$key]['title'] ?? SeoChecklistDefaultTemplate::stageTitle($key),
                    'lead' => $stagesMeta[$key]['lead'] ?? null,
                    'sort' => (int) ($stagesMeta[$key]['sort'] ?? $item->stage_sort),
                    'items' => [],
                    'done' => 0,
                    'total' => 0,
                ];
            }
            $grouped[$key]['items'][] = $item;
            $grouped[$key]['total']++;
            if (in_array($item->status, \App\SeoChecklist\SeoChecklistItem::CLOSED_STATUSES, true)) {
                $grouped[$key]['done']++;
            }
            if ($item->status === 'review') {
                $projectReviewCount++;
            }

            $role = in_array($item->role, ['owner', 'pm', 'shared', 'any'], true) ? $item->role : 'any';
            $roleStats[$role]['total']++;
            if (in_array($item->status, \App\SeoChecklist\SeoChecklistItem::CLOSED_STATUSES, true)) {
                $roleStats[$role]['done']++;
            }
        }
        uasort($grouped, static function ($a, $b) {
            return $a['sort'] <=> $b['sort'];
        });

        $myRoles = $this->service->myTaskRoles($project, $authId);
        $canManage = $this->service->canManageProject($project, $authId);
        $accessKind = $this->service->accessKind($project, $authId);

        $teamCandidates = $this->service->teamCandidates((int) $project->user_id);
        foreach ([$project->ownerUser, $project->pmUser] as $extra) {
            if ($extra && !$teamCandidates->contains('id', $extra->id)) {
                $teamCandidates->push($extra);
            }
        }

        $teams = $canManage ? $this->service->teamsForUser($authId) : collect();
        $plan = $this->service->workPlanForUser($authId);
        $chronicle = $this->service->chronicleForUser($authId, null, null, true, 1);
        $projectsCount = $this->service->accessibleProjectsQuery($authId)
            ->where('status', 'active')
            ->count();

        return view('pages.seo-checklist-show', [
            'project' => $project,
            'stages' => array_values($grouped),
            'stagesMeta' => $stagesMeta,
            'statusLabels' => $this->statusLabels(),
            'roleLabels' => $this->roleLabels(),
            'roleStats' => $roleStats,
            'myRoles' => $myRoles,
            'teamCandidates' => $teamCandidates,
            'canManage' => $canManage,
            'canApproveReview' => $this->service->canApproveReview($project, $authId),
            'accessKind' => $accessKind,
            'teams' => $teams,
            'teamRoleLabels' => \App\SeoChecklist\SeoChecklistTeam::roleLabels(),
            'timerUserId' => $authId,
            'projectsCount' => $projectsCount,
            'teamCount' => $this->service->teamsForUser($authId)->count(),
            'templatesCount' => $this->service->templatesForUser($authId)->count(),
            'myTasksCount' => (int) ($plan['count'] ?? 0),
            'reviewCount' => $this->service->reviewQueueForUser($authId)->count(),
            'projectReviewCount' => $projectReviewCount,
            'showReviewTab' => $this->service->canSeeReviewQueue($authId),
            'unreadNotesCount' => (int) ($chronicle['unread_count'] ?? 0),
        ]);
    }

    public function updateTeam(Request $request, int $id): RedirectResponse
    {
        $project = $this->findManageableProject($id);
        if (!$project) {
            return redirect()->route('pages.seo-checklist')->with('error', __('Project not found'));
        }

        $ownerId = $project->owner_user_id ?: $project->user_id;
        $ownerEmail = trim((string) $request->input('owner_email', ''));
        if ($ownerEmail !== '') {
            $owner = User::query()->where('email', $ownerEmail)->first();
            if (!$owner) {
                return redirect()
                    ->route('pages.seo-checklist.show', ['id' => $project->id])
                    ->with('error', __('SEO checklist owner user not found'));
            }
            $ownerId = (int) $owner->id;
        } elseif ($request->filled('owner_user_id')) {
            $ownerId = (int) $request->input('owner_user_id');
            if (!User::query()->whereKey($ownerId)->exists()) {
                return redirect()
                    ->route('pages.seo-checklist.show', ['id' => $project->id])
                    ->with('error', __('SEO checklist owner user not found'));
            }
        }

        $pmId = null;
        $pmEmail = trim((string) $request->input('pm_email', ''));
        if ($pmEmail !== '') {
            $pm = User::query()->where('email', $pmEmail)->first();
            if (!$pm) {
                return redirect()
                    ->route('pages.seo-checklist.show', ['id' => $project->id])
                    ->with('error', __('SEO checklist PM user not found'));
            }
            $pmId = (int) $pm->id;
        } elseif ($request->has('pm_user_id')) {
            $pmId = (int) $request->input('pm_user_id');
            if ($pmId > 0 && !User::query()->whereKey($pmId)->exists()) {
                return redirect()
                    ->route('pages.seo-checklist.show', ['id' => $project->id])
                    ->with('error', __('SEO checklist PM user not found'));
            }
            if ($pmId <= 0) {
                $pmId = null;
            }
        }

        $project->forceFill([
            'owner_user_id' => $ownerId,
            'pm_user_id' => $pmId,
            'last_activity_at' => now(),
        ])->save();

        $redirectTo = (string) $request->input('return_to', '');
        if ($redirectTo === 'team') {
            return redirect()
                ->route('pages.seo-checklist.team')
                ->with('success', __('SEO checklist team saved'));
        }

        return redirect()
            ->route('pages.seo-checklist.show', ['id' => $project->id])
            ->with('success', __('SEO checklist team saved'));
    }

    public function updateItemStatus(Request $request, int $id, int $itemId): JsonResponse
    {
        $project = $this->findAccessibleProject($id);
        if (!$project) {
            return response()->json(['ok' => false, 'message' => __('Project not found')], 404);
        }

        /** @var SeoChecklistItem|null $item */
        $item = $project->items()->where('id', $itemId)->first();
        if (!$item) {
            return response()->json(['ok' => false, 'message' => __('Task not found')], 404);
        }

        $status = (string) $request->input('status', '');
        if (in_array($status, ['skip', 'blocked', 'clarify'], true)) {
            $note = trim((string) $request->input('note', ''));
            if ($note === '') {
                return response()->json([
                    'ok' => false,
                    'message' => __('Comment required for this status'),
                ], 422);
            }
            $createdNote = SeoChecklistItemNote::query()->create([
                'item_id' => $item->id,
                'user_id' => (int) Auth::id(),
                'body' => $note,
            ]);
            $this->service->logActivity((int) $project->id, (int) $item->id, (int) Auth::id(), 'note', [
                'note_id' => $createdNote->id,
                'body' => $note,
                'title' => $item->title,
            ]);
        }

        $changed = $this->service->changeItemStatus($item, $status, (int) Auth::id());
        if (empty($changed['ok'])) {
            return response()->json(['ok' => false, 'message' => $changed['message'] ?? __('Invalid status')], 422);
        }
        $item->refresh();
        $item->loadMissing(['createdByUser', 'doneByUser']);

        $project->refresh();

        return response()->json([
            'ok' => true,
            'item' => [
                'id' => $item->id,
                'status' => $item->status,
                'done_at' => $item->done_at ? $item->done_at->format('d.m.Y H:i') : null,
                'time_spent_seconds' => (int) $item->time_spent_seconds,
                'display_seconds' => $item->displayTimeSpentSeconds((int) Auth::id()),
                'timer_running' => false,
                'timer_started_at' => null,
                'audit' => $this->itemAuditPayload($item),
            ],
            'active' => (($active = $this->service->activeTimerForUser((int) Auth::id()))
                ? $this->activeTimerState($active)
                : null),
            'progress' => [
                'done' => (int) $project->progress_done,
                'total' => (int) $project->progress_total,
            ],
        ]);
    }

    public function updateItem(Request $request, int $id, int $itemId): JsonResponse
    {
        $project = $this->findAccessibleProject($id);
        if (!$project || $project->status === 'archived') {
            return response()->json(['ok' => false, 'message' => __('Project not found')], 404);
        }

        $item = $project->items()->where('id', $itemId)->first();
        if (!$item) {
            return response()->json(['ok' => false, 'message' => __('Task not found')], 404);
        }

        $payload = [];
        if ($request->exists('title')) {
            $payload['title'] = $request->input('title');
        }
        if ($request->exists('help')) {
            $payload['help'] = $request->input('help');
        }
        if ($request->exists('role')) {
            $payload['role'] = $request->input('role');
        }
        if ($request->exists('is_important')) {
            $payload['is_important'] = (bool) $request->input('is_important');
        }
        if ($request->exists('include_in_report')) {
            $payload['include_in_report'] = (bool) $request->input('include_in_report');
        }
        if ($request->exists('allows_subtasks')) {
            $payload['allows_subtasks'] = (bool) $request->input('allows_subtasks');
        }
        if ($request->exists('repeat_rule')) {
            $payload['repeat_rule'] = $request->input('repeat_rule');
        }

        $result = $this->service->updateProjectItem($item, $payload);
        if (empty($result['ok'])) {
            return response()->json(['ok' => false, 'message' => $result['message'] ?? __('Error')], 422);
        }

        $item->refresh();

        return response()->json([
            'ok' => true,
            'item' => [
                'id' => $item->id,
                'title' => $item->title,
                'help' => $item->help,
                'role' => $item->role,
                'is_important' => (bool) $item->is_important,
                'include_in_report' => (bool) $item->include_in_report,
                'allows_subtasks' => (bool) $item->allows_subtasks,
                'repeat_rule' => $item->repeat_rule,
            ],
        ]);
    }

    public function destroyItem(Request $request, int $id, int $itemId)
    {
        $project = $this->findAccessibleProject($id);
        if (!$project || $project->status === 'archived') {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['ok' => false, 'message' => __('Project not found')], 404);
            }

            return redirect()->route('pages.seo-checklist')->with('error', __('Project not found'));
        }

        $item = $project->items()->where('id', $itemId)->first();
        if (!$item) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['ok' => false, 'message' => __('Task not found')], 404);
            }

            return redirect()->route('pages.seo-checklist.show', ['id' => $id])->with('error', __('Task not found'));
        }

        // Основные задачи — только менеджер; подзадачи может удалить любой с доступом к проекту
        if (!$item->isSubtask() && !$this->service->canManageProject($project, (int) Auth::id())) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['ok' => false, 'message' => __('Only PM or auditor can approve')], 403);
            }

            return redirect()->route('pages.seo-checklist.show', ['id' => $id])->with('error', __('Project not found'));
        }

        $result = $this->service->deleteProjectItem($item);
        $project->refresh();

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'ok' => !empty($result['ok']),
                'progress' => [
                    'done' => (int) $project->progress_done,
                    'total' => (int) $project->progress_total,
                ],
            ]);
        }

        return redirect()
            ->route('pages.seo-checklist.show', ['id' => $project->id])
            ->with('success', __('Task deleted'));
    }

    public function addNote(Request $request, int $id, int $itemId): JsonResponse
    {
        $project = $this->findAccessibleProject($id);
        if (!$project) {
            return response()->json(['ok' => false, 'message' => __('Project not found')], 404);
        }

        $item = $project->items()->where('id', $itemId)->first();
        if (!$item) {
            return response()->json(['ok' => false, 'message' => __('Task not found')], 404);
        }

        $body = trim((string) $request->input('body', ''));
        if ($body === '') {
            return response()->json(['ok' => false, 'message' => __('Note cannot be empty')], 422);
        }

        $note = SeoChecklistItemNote::query()->create([
            'item_id' => $item->id,
            'user_id' => (int) Auth::id(),
            'body' => $body,
        ]);
        $note->load('user');
        $project->forceFill(['last_activity_at' => now()])->save();
        $this->service->logActivity((int) $project->id, (int) $item->id, (int) Auth::id(), 'note', [
            'note_id' => $note->id,
            'body' => $body,
            'title' => $item->title,
        ]);

        return response()->json([
            'ok' => true,
            'note' => [
                'id' => $note->id,
                'body' => $note->body,
                'body_html' => \App\Support\TextAutoLinker::format((string) $note->body),
                'author' => $note->authorLabel(),
                'created_at' => $note->created_at->format('d.m.Y H:i'),
            ],
        ]);
    }

    public function addSubtask(Request $request, int $id, int $itemId): JsonResponse
    {
        $project = $this->findAccessibleProject($id);
        if (!$project) {
            return response()->json(['ok' => false, 'message' => __('Project not found')], 404);
        }

        /** @var SeoChecklistItem|null $parent */
        $parent = $project->items()->where('id', $itemId)->whereNull('parent_id')->first();
        if (!$parent) {
            return response()->json(['ok' => false, 'message' => __('Task not found')], 404);
        }
        if ($project->status === 'archived') {
            return response()->json(['ok' => false, 'message' => __('Project not found')], 404);
        }

        $title = trim((string) $request->input('title', ''));
        if ($title === '') {
            return response()->json(['ok' => false, 'message' => __('Title required')], 422);
        }

        if (!$parent->allows_subtasks) {
            $parent->forceFill(['allows_subtasks' => true])->save();
        }

        $sort = (int) $project->items()->where('parent_id', $parent->id)->max('sort') + 10;
        $child = SeoChecklistItem::query()->create([
            'project_id' => $project->id,
            'parent_id' => $parent->id,
            'code' => $parent->code . '_sub_' . time() . '_' . mt_rand(100, 999),
            'stage_key' => $parent->stage_key,
            'stage_sort' => $parent->stage_sort,
            'sort' => $sort,
            'title' => $title,
            'help' => null,
            'role' => $parent->role,
            'is_important' => false,
            'include_in_report' => $request->boolean('include_in_report'),
            'allows_subtasks' => false,
            'status' => 'todo',
            'links_json' => [],
            'created_by' => (int) Auth::id(),
        ]);
        $project->forceFill(['last_activity_at' => now()])->save();
        $child->loadMissing(['createdByUser', 'doneByUser', 'parent:id,title']);
        $this->service->logActivity(
            (int) $project->id,
            (int) $child->id,
            (int) Auth::id(),
            'item_created',
            $this->service->itemActivitySnapshot($child)
        );

        return response()->json([
            'ok' => true,
            'item' => [
                'id' => $child->id,
                'title' => $child->title,
                'status' => $child->status,
                'parent_id' => $parent->id,
                'include_in_report' => (bool) $child->include_in_report,
                'created_by' => (int) $child->created_by,
                'audit' => $this->itemAuditPayload($child),
            ],
        ]);
    }

    public function reorderSubtasks(Request $request, int $id, int $itemId): JsonResponse
    {
        $project = $this->findAccessibleProject($id);
        if (!$project) {
            return response()->json(['ok' => false, 'message' => __('Project not found')], 404);
        }

        $orderedIds = $request->input('ordered_ids');
        if (!is_array($orderedIds)) {
            return response()->json(['ok' => false, 'message' => __('Error')], 422);
        }

        $result = $this->service->reorderSubtasksByIds($project, $itemId, $orderedIds);
        if (empty($result['ok'])) {
            return response()->json(['ok' => false, 'message' => $result['message'] ?? __('Error')], 422);
        }

        return response()->json([
            'ok' => true,
            'ordered_ids' => array_values(array_map('intval', $orderedIds)),
        ]);
    }

    public function archive(int $id): RedirectResponse
    {
        $project = $this->findManageableProject($id);
        if (!$project) {
            return redirect()->route('pages.seo-checklist')->with('error', __('Project not found'));
        }

        $project->status = 'archived';
        $project->save();

        return redirect()->route('pages.seo-checklist')->with('success', __('SEO checklist archived'));
    }

    public function restore(int $id): RedirectResponse
    {
        $project = $this->findManageableProject($id);
        if (!$project) {
            return redirect()->route('pages.seo-checklist')->with('error', __('Project not found'));
        }

        $this->service->restoreProject($project);

        return redirect()
            ->route('pages.seo-checklist.show', ['id' => $project->id])
            ->with('success', __('SEO checklist restored'));
    }

    public function destroy(int $id): RedirectResponse
    {
        $project = $this->findManageableProject($id);
        if (!$project) {
            return redirect()->route('pages.seo-checklist')->with('error', __('Project not found'));
        }

        $domain = $project->domain;
        $result = $this->service->deleteProject($project);
        if (empty($result['ok'])) {
            return redirect()
                ->route('pages.seo-checklist.show', ['id' => $id])
                ->with('error', $result['message'] ?? __('Could not delete project'));
        }

        return redirect()
            ->route('pages.seo-checklist')
            ->with('success', __('SEO checklist deleted', ['domain' => $domain]));
    }

    /**
     * @return JsonResponse|RedirectResponse
     */
    public function storeItem(Request $request, int $id)
    {
        $project = $this->findManageableProject($id);
        if (!$project || $project->status === 'archived') {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['ok' => false, 'message' => __('Project not found')], 404);
            }

            return redirect()->route('pages.seo-checklist')->with('error', __('Project not found'));
        }

        $result = $this->service->addProjectTask($project, [
            'title' => $request->input('title'),
            'help' => $request->input('help'),
            'stage_key' => $request->input('stage_key'),
            'role' => $request->input('role'),
            'is_important' => $request->has('is_important') || (bool) $request->input('is_important'),
            'include_in_report' => $request->has('include_in_report') || (bool) $request->input('include_in_report'),
            'allows_subtasks' => $request->has('allows_subtasks') || (bool) $request->input('allows_subtasks'),
            'repeat_rule' => $request->input('repeat_rule'),
        ]);

        if (empty($result['ok']) || empty($result['item'])) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'ok' => false,
                    'message' => $result['message'] ?? __('Error'),
                ], 422);
            }

            return redirect()
                ->route('pages.seo-checklist.show', ['id' => $project->id])
                ->with('error', $result['message'] ?? __('Error'));
        }

        if ($request->ajax() || $request->wantsJson()) {
            $item = $result['item'];
            $project->refresh();
            $project->loadMissing('template');
            $stagesMeta = $this->service->resolveTemplateStages($project->template);
            $stageKey = $item->stage_key === 'connect' ? 'access' : $item->stage_key;

            return response()->json([
                'ok' => true,
                'item' => [
                    'id' => $item->id,
                    'title' => $item->title,
                    'status' => $item->status,
                    'role' => $item->role,
                    'stage_key' => $item->stage_key,
                    'stage_title' => $stagesMeta[$stageKey]['title']
                        ?? SeoChecklistDefaultTemplate::stageTitle($stageKey),
                    'is_important' => (bool) $item->is_important,
                    'allows_subtasks' => (bool) $item->allows_subtasks,
                    'repeat_rule' => $item->repeat_rule,
                    'help' => $item->help,
                ],
                'progress' => [
                    'done' => (int) $project->progress_done,
                    'total' => (int) $project->progress_total,
                ],
            ]);
        }

        return redirect()
            ->route('pages.seo-checklist.show', ['id' => $project->id])
            ->with('success', __('Task added'));
    }

    public function startItemTimer(int $id, int $itemId): JsonResponse
    {
        $project = $this->findAccessibleProject($id);
        if (!$project || $project->status === 'archived') {
            return response()->json(['ok' => false, 'message' => __('Project not found')], 404);
        }

        /** @var SeoChecklistItem|null $item */
        $item = $project->items()->where('id', $itemId)->first();
        if (!$item) {
            return response()->json(['ok' => false, 'message' => __('Task not found')], 404);
        }

        $result = $this->service->startItemTimer($item, (int) Auth::id());
        if (empty($result['ok'])) {
            return response()->json(['ok' => false, 'message' => $result['message'] ?? __('Error')], 422);
        }

        return response()->json($this->timerPayload($result, $project));
    }

    public function stopItemTimer(int $id, int $itemId): JsonResponse
    {
        $project = $this->findAccessibleProject($id);
        if (!$project) {
            return response()->json(['ok' => false, 'message' => __('Project not found')], 404);
        }

        /** @var SeoChecklistItem|null $item */
        $item = $project->items()->where('id', $itemId)->first();
        if (!$item) {
            return response()->json(['ok' => false, 'message' => __('Task not found')], 404);
        }

        $result = $this->service->stopItemTimer($item, (int) Auth::id());
        if (empty($result['ok'])) {
            return response()->json(['ok' => false, 'message' => $result['message'] ?? __('Error')], 422);
        }

        return response()->json($this->timerPayload($result, $project));
    }

    public function stopActiveTimer(): JsonResponse
    {
        $result = $this->service->stopActiveTimerForUser((int) Auth::id());
        if (empty($result['ok'])) {
            return response()->json(['ok' => false, 'message' => $result['message'] ?? __('Error')], 422);
        }

        $item = $result['item'] ?? null;
        $project = $item ? $item->project : null;

        return response()->json([
            'ok' => true,
            'active' => null,
            'item' => $item ? $this->itemTimerState($item, (int) Auth::id()) : null,
            'progress' => $project ? [
                'done' => (int) $project->progress_done,
                'total' => (int) $project->progress_total,
            ] : null,
        ]);
    }

    /**
     * @param array{ok:bool,item?:SeoChecklistItem,log?:\App\SeoChecklist\SeoChecklistItemTimeLog,stopped_item_id?:int} $result
     * @return array<string, mixed>
     */
    private function timerPayload(array $result, SeoChecklistProject $project): array
    {
        $userId = (int) Auth::id();
        $item = $result['item'] ?? null;
        $project->refresh();
        $active = $this->service->activeTimerForUser($userId);

        return [
            'ok' => true,
            'item' => $item ? $this->itemTimerState($item, $userId) : null,
            'stopped_item_id' => $result['stopped_item_id'] ?? null,
            'stopped_item' => !empty($result['stopped_item_id'])
                ? $this->itemTimerStateById((int) $result['stopped_item_id'], $userId)
                : null,
            'active' => $active ? $this->activeTimerState($active) : null,
            'progress' => [
                'done' => (int) $project->progress_done,
                'total' => (int) $project->progress_total,
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function itemTimerStateById(int $itemId, int $userId): ?array
    {
        $item = SeoChecklistItem::query()->find($itemId);
        if (!$item) {
            return null;
        }

        return $this->itemTimerState($item, $userId);
    }

    /**
     * @return array<string, mixed>
     */
    private function itemTimerState(SeoChecklistItem $item, int $userId): array
    {
        $running = $item->runningTimeLog($userId);

        return [
            'id' => $item->id,
            'status' => $item->status,
            'time_spent_seconds' => (int) $item->time_spent_seconds,
            'display_seconds' => $item->displayTimeSpentSeconds($userId),
            'timer_running' => (bool) $running,
            'timer_started_at' => $running && $running->started_at
                ? $running->started_at->toIso8601String()
                : null,
        ];
    }

    /**
     * @param array{log:\App\SeoChecklist\SeoChecklistItemTimeLog,item:SeoChecklistItem,project:SeoChecklistProject} $active
     * @return array<string, mixed>
     */
    private function activeTimerState(array $active): array
    {
        $log = $active['log'];
        $item = $active['item'];
        $project = $active['project'];

        $anchorId = $item->parent_id ? (int) $item->parent_id : (int) $item->id;

        return [
            'item_id' => $item->id,
            'project_id' => $project->id,
            'domain' => $project->domain,
            'title' => $item->title,
            'url' => route('pages.seo-checklist.show', ['id' => $project->id]) . '#sc-item-' . $anchorId,
            'started_at' => $log->started_at ? $log->started_at->toIso8601String() : null,
            'elapsed_seconds' => $log->elapsedSeconds(),
            'time_spent_seconds' => (int) $item->time_spent_seconds,
            'display_seconds' => $item->displayTimeSpentSeconds((int) $log->user_id),
            'stop_url' => route('pages.seo-checklist.timer.stop-active'),
        ];
    }

    /**
     * @return Response|RedirectResponse
     */
    public function pdf(int $id)
    {
        $project = $this->findAccessibleProject($id);
        if (!$project) {
            return redirect()->route('pages.seo-checklist')->with('error', __('Project not found'));
        }

        $project->load(['ownerUser', 'pmUser', 'template']);
        $items = $project->items()->whereNull('parent_id')->orderBy('stage_sort')->orderBy('sort')->get();
        $stagesMeta = $this->service->resolveTemplateStages($project->template);
        $grouped = [];
        foreach ($items as $item) {
            $key = $item->stage_key === 'connect' ? 'access' : $item->stage_key;
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'key' => $key,
                    'title' => $stagesMeta[$key]['title'] ?? SeoChecklistDefaultTemplate::stageTitle($key),
                    'sort' => (int) ($stagesMeta[$key]['sort'] ?? $item->stage_sort),
                    'items' => [],
                    'done' => 0,
                    'total' => 0,
                ];
            }
            $grouped[$key]['items'][] = $item;
            $grouped[$key]['total']++;
            if (in_array($item->status, ['done', 'skip'], true)) {
                $grouped[$key]['done']++;
            }
        }
        uasort($grouped, static function ($a, $b) {
            return $a['sort'] <=> $b['sort'];
        });

        $pct = $project->progress_total > 0
            ? (int) round(100 * $project->progress_done / $project->progress_total)
            : 0;

        $pdf = \PDF::loadView('pages.seo-checklist-pdf', [
            'project' => $project,
            'stages' => array_values($grouped),
            'statusLabels' => $this->statusLabels(),
            'roleLabels' => $this->roleLabels(),
            'pct' => $pct,
            'generatedAt' => now()->format('d.m.Y H:i'),
        ])->setPaper('a4', 'portrait');

        $file = 'seo-checklist-' . preg_replace('/[^a-z0-9\-\.]+/i', '-', $project->domain) . '.pdf';

        return $pdf->download($file);
    }

    private function findAccessibleProject(int $id): ?SeoChecklistProject
    {
        return $this->service->findAccessibleProject((int) Auth::id(), $id);
    }

    /**
     * @return array{created_label:?string,done_label:?string,status_label:?string}
     */
    private function itemAuditPayload(SeoChecklistItem $item): array
    {
        $createdBy = $this->userShortLabel($item->createdByUser);
        $createdAt = $this->formatAuditAt($item->created_at);
        $createdLabel = null;
        if ($createdBy || $createdAt) {
            $createdLabel = $createdBy
                ? __('Created by :name on :date', [
                    'name' => $createdBy,
                    'date' => $createdAt ?: '—',
                ])
                : __('Created on :date', [
                    'date' => $createdAt ?: '—',
                ]);
        }

        $doneLabel = null;
        if ($item->done_at) {
            $doneBy = $this->userShortLabel($item->doneByUser);
            $doneLabel = __('Completed by :name on :date', [
                'name' => $doneBy ?: '—',
                'date' => $this->formatAuditAt($item->done_at) ?: '—',
            ]);
        }

        $statusLabels = $this->service->lastStatusChangeLabelsByItemIds([(int) $item->id]);

        return [
            'created_label' => $createdLabel,
            'done_label' => $doneLabel,
            'status_label' => $statusLabels[(int) $item->id] ?? null,
        ];
    }

    /** Дата+время без переноса между ними (nbsp). */
    private function formatAuditAt($dt): ?string
    {
        if (!$dt) {
            return null;
        }

        return $dt->format('d.m.Y') . "\xc2\xa0" . $dt->format('H:i');
    }

    private function userShortLabel($user): ?string
    {
        if (!$user) {
            return null;
        }
        $name = trim(($user->name ?? '') . ' ' . ($user->last_name ?? ''));

        return $name !== '' ? $name : (string) ($user->email ?: null);
    }

    private function findManageableProject(int $id): ?SeoChecklistProject
    {
        $project = $this->findAccessibleProject($id);
        if (!$project || !$this->service->canManageProject($project, (int) Auth::id())) {
            return null;
        }

        return $project;
    }

    private function findUsableTemplateModel(int $templateId): ?SeoChecklistTemplate
    {
        return $this->service->findUsableTemplate((int) Auth::id(), $templateId);
    }

    private function findOwnedCustomTemplate(int $templateId): ?SeoChecklistTemplate
    {
        $template = SeoChecklistTemplate::query()->where('id', $templateId)->first();
        if (!$template) {
            return null;
        }
        if (!$this->service->canEditTemplate($template)) {
            return null;
        }

        return $template;
    }

    private function moduleTitle(): string
    {
        return SeoChecklistUserPreference::moduleTitleFor((int) Auth::id());
    }

    /**
     * @return array<int, int>
     */
    private function requestIdList(Request $request, string $pluralKey, string $singularKey): array
    {
        $raw = $request->input($pluralKey, $request->input($singularKey));
        if ($raw === null || $raw === '' || $raw === []) {
            return [];
        }
        if (!is_array($raw)) {
            $raw = [$raw];
        }
        $ids = [];
        foreach ($raw as $value) {
            $id = (int) $value;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }

    private function statusLabels(): array
    {
        return [
            'todo' => __('Status new'),
            'doing' => __('Status doing'),
            'rework' => __('Status rework'),
            'clarify' => __('Status clarify'),
            'review' => __('Status review'),
            'done' => __('Status done'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function roleLabels(): array
    {
        return [
            'owner' => __('SEO role owner'),
            'pm' => __('SEO role PM'),
            'shared' => __('SEO role shared'),
            'any' => __('SEO role any'),
        ];
    }
}
