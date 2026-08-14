<?php

namespace App\Services\SeoChecklist;

use App\DomainMonitoring;
use App\MonitoringProject;
use App\SeoChecklist\SeoChecklistActivityLog;
use App\SeoChecklist\SeoChecklistActivityRead;
use App\SeoChecklist\SeoChecklistItem;
use App\SeoChecklist\SeoChecklistItemNote;
use App\SeoChecklist\SeoChecklistItemTimeLog;
use App\SeoChecklist\SeoChecklistNoteRead;
use App\SeoChecklist\SeoChecklistProject;
use App\SeoChecklist\SeoChecklistTeam;
use App\SeoChecklist\SeoChecklistTeamMember;
use App\SeoChecklist\SeoChecklistTemplate;
use App\SeoChecklist\SeoChecklistTemplateTask;
use App\SeoChecklist\SeoChecklistUserPreference;
use App\SiteAuditProject;
use App\Support\HomeUserSites;
use App\Support\SeoChecklistDefaultTemplate;
use App\User;
use App\YandexMetrikaDomainCounter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SeoChecklistService
{
    public function ensureSystemTemplate(): SeoChecklistTemplate
    {
        $template = SeoChecklistTemplate::systemDefault();
        if (!$template) {
            $template = DB::transaction(function () {
                $templateData = [
                    'user_id' => null,
                    'code' => SeoChecklistDefaultTemplate::CODE,
                    'title' => 'SEO чеклист (стандарт)',
                    'description' => 'Базовый шаблон ведения SEO и UX проекта · v' . SeoChecklistDefaultTemplate::VERSION,
                    'is_system' => true,
                ];
                if (Schema::hasColumn('seo_checklist_templates', 'stages_json')) {
                    $templateData['stages_json'] = SeoChecklistDefaultTemplate::skeletonStagesList();
                }
                $template = SeoChecklistTemplate::query()->create($templateData);

                foreach (SeoChecklistDefaultTemplate::tasks() as $task) {
                    SeoChecklistTemplateTask::query()->create([
                        'template_id' => $template->id,
                        'parent_id' => null,
                        'code' => $task['code'],
                        'stage_key' => $task['stage_key'],
                        'stage_sort' => $task['stage_sort'],
                        'sort' => $task['sort'],
                        'title' => $task['title'],
                        'help' => $task['help'],
                        'role' => $task['role'],
                        'is_important' => !empty($task['is_important']),
                        'allows_subtasks' => !empty($task['allows_subtasks']),
                        'repeat_rule' => $task['repeat_rule'] ?? null,
                        'due_days_from_start' => $task['due_days_from_start'] ?? null,
                        'links_json' => $task['links'] ?? [],
                    ]);
                }

                return $template;
            });
        }

        $this->syncSystemTemplateFromSeedIfNeeded($template);

        if ($template->is_system
            && Schema::hasColumn('seo_checklist_templates', 'stages_json')
            && (!is_array($template->stages_json) || $template->stages_json === [])
        ) {
            $template->forceFill([
                'stages_json' => SeoChecklistDefaultTemplate::skeletonStagesList(),
            ])->save();
        }

        return $template->fresh();
    }

    /**
     * Пересобирает задачи системного шаблона из PHP-seed при смене VERSION.
     */
    public function syncSystemTemplateFromSeedIfNeeded(SeoChecklistTemplate $template): void
    {
        if (!$template->is_system) {
            return;
        }

        $marker = '· v' . SeoChecklistDefaultTemplate::VERSION;
        $description = (string) $template->description;
        if (strpos($description, $marker) !== false
            && $template->tasks()->count() >= count(SeoChecklistDefaultTemplate::tasks())
        ) {
            return;
        }

        $this->rebuildSystemTemplateTasks($template);
    }

    /**
     * Полная пересборка задач system-шаблона из кода (коды сохраняются для upsert).
     */
    public function rebuildSystemTemplateTasks(SeoChecklistTemplate $template): void
    {
        DB::transaction(function () use ($template) {
            $seed = SeoChecklistDefaultTemplate::tasks();
            $seedCodes = [];
            foreach ($seed as $task) {
                $seedCodes[] = $task['code'];
                SeoChecklistTemplateTask::query()->updateOrCreate(
                    [
                        'template_id' => $template->id,
                        'code' => $task['code'],
                    ],
                    [
                        'parent_id' => null,
                        'stage_key' => $task['stage_key'],
                        'stage_sort' => $task['stage_sort'],
                        'sort' => $task['sort'],
                        'title' => $task['title'],
                        'help' => $task['help'],
                        'role' => $task['role'],
                        'is_important' => !empty($task['is_important']),
                        'allows_subtasks' => !empty($task['allows_subtasks']),
                        'repeat_rule' => $task['repeat_rule'] ?? null,
                        'due_days_from_start' => $task['due_days_from_start'] ?? null,
                        'links_json' => $task['links'] ?? [],
                    ]
                );
            }

            // Убрать устаревшие пункты seed, которых больше нет в коде
            SeoChecklistTemplateTask::query()
                ->where('template_id', $template->id)
                ->whereNotIn('code', $seedCodes)
                ->where('code', 'not like', 'custom_%')
                ->delete();

            $fill = [
                'title' => 'SEO чеклист (стандарт)',
                'description' => 'Базовый шаблон ведения SEO и UX проекта · v' . SeoChecklistDefaultTemplate::VERSION,
            ];
            if (Schema::hasColumn('seo_checklist_templates', 'stages_json')) {
                $fill['stages_json'] = SeoChecklistDefaultTemplate::skeletonStagesList();
            }
            $template->forceFill($fill)->save();
        });

        $this->backfillProjectItemDueDates();
    }

    /**
     * Этапы шаблона: title/lead/sort по stage_key.
     * Пустой кастомный шаблон без stages_json → [] (не подставляем весь системный скелет).
     *
     * @return array<string, array{title:string,lead:?string,sort:int}>
     */
    public function resolveTemplateStages(?SeoChecklistTemplate $template): array
    {
        if (!$template) {
            return SeoChecklistDefaultTemplate::skeletonStagesMap();
        }

        if (Schema::hasColumn('seo_checklist_templates', 'stages_json')
            && is_array($template->stages_json)
        ) {
            // Явно пустой список — пустой шаблон без этапов
            if ($template->stages_json === []) {
                return $this->mergeOrphanTaskStages($template, []);
            }
            $map = SeoChecklistDefaultTemplate::stagesMapFromList($template->stages_json);
            if (!empty($map)) {
                return $this->mergeOrphanTaskStages($template, $map);
            }
        }

        if ($template->is_system) {
            return SeoChecklistDefaultTemplate::skeletonStagesMap();
        }

        return $this->deriveStagesFromTasks($template);
    }

    /**
     * @param  array<string, array{title:string,lead:?string,sort:int}>  $map
     * @return array<string, array{title:string,lead:?string,sort:int}>
     */
    private function mergeOrphanTaskStages(SeoChecklistTemplate $template, array $map): array
    {
        $defaults = SeoChecklistDefaultTemplate::skeletonStagesMap();
        $tasks = $template->tasks()
            ->whereNotNull('stage_key')
            ->orderBy('stage_sort')
            ->get(['stage_key', 'stage_sort']);

        foreach ($tasks as $row) {
            $key = (string) $row->stage_key;
            if ($key === '' || $key === 'connect' || isset($map[$key])) {
                continue;
            }
            $map[$key] = [
                'title' => $defaults[$key]['title'] ?? SeoChecklistDefaultTemplate::stageTitle($key),
                'lead' => $defaults[$key]['lead'] ?? null,
                'sort' => (int) ($row->stage_sort ?: ($defaults[$key]['sort'] ?? 999)),
            ];
        }

        return $map;
    }

    /**
     * @return array<string, array{title:string,lead:?string,sort:int}>
     */
    private function deriveStagesFromTasks(SeoChecklistTemplate $template): array
    {
        $defaults = SeoChecklistDefaultTemplate::skeletonStagesMap();
        $map = [];
        $tasks = $template->tasks()
            ->whereNotNull('stage_key')
            ->orderBy('stage_sort')
            ->get(['stage_key', 'stage_sort']);

        foreach ($tasks as $row) {
            $key = (string) $row->stage_key;
            if ($key === '' || $key === 'connect' || isset($map[$key])) {
                continue;
            }
            $map[$key] = [
                'title' => $defaults[$key]['title'] ?? SeoChecklistDefaultTemplate::stageTitle($key),
                'lead' => $defaults[$key]['lead'] ?? null,
                'sort' => (int) ($row->stage_sort ?: ($defaults[$key]['sort'] ?? 0)),
            ];
        }

        return $map;
    }

    /**
     * @return list<array{key:string,title:string,lead:?string,sort:int}>
     */
    public function stagesListForTemplate(SeoChecklistTemplate $template): array
    {
        $map = $this->resolveTemplateStages($template);
        $list = [];
        foreach ($map as $key => $meta) {
            $list[] = [
                'key' => $key,
                'title' => $meta['title'],
                'lead' => $meta['lead'] ?? null,
                'sort' => (int) $meta['sort'],
            ];
        }
        usort($list, static function ($a, $b) {
            return $a['sort'] <=> $b['sort'];
        });

        return $list;
    }

    /**
     * @param  array<string, array{title:string,lead:?string,sort:int}>  $map
     */
    private function persistTemplateStages(SeoChecklistTemplate $template, array $map): void
    {
        if (!Schema::hasColumn('seo_checklist_templates', 'stages_json')) {
            return;
        }

        $list = [];
        foreach ($map as $key => $meta) {
            $list[] = [
                'key' => $key,
                'title' => $meta['title'],
                'lead' => $meta['lead'] ?? null,
                'sort' => (int) $meta['sort'],
            ];
        }
        usort($list, static function ($a, $b) {
            return $a['sort'] <=> $b['sort'];
        });

        $template->forceFill(['stages_json' => $list])->save();

        foreach ($list as $row) {
            $template->tasks()
                ->where('stage_key', $row['key'])
                ->update(['stage_sort' => (int) $row['sort']]);
        }
    }

    /**
     * Проставляет due_days / due_at на существующих задачах проектов по коду из seed.
     */
    public function backfillProjectItemDueDates(): void
    {
        if (!Schema::hasColumn('seo_checklist_items', 'due_at')) {
            return;
        }

        $dueByCode = SeoChecklistDefaultTemplate::dueDaysByCode();
        if (empty($dueByCode)) {
            return;
        }

        SeoChecklistProject::query()
            ->where('status', 'active')
            ->orderBy('id')
            ->chunkById(50, function ($projects) use ($dueByCode) {
                foreach ($projects as $project) {
                    $start = $project->created_at ?: now();
                    $items = SeoChecklistItem::query()
                        ->where('project_id', $project->id)
                        ->whereNull('parent_id')
                        ->whereIn('code', array_keys($dueByCode))
                        ->get();

                    foreach ($items as $item) {
                        $days = (int) ($dueByCode[$item->code] ?? 0);
                        if ($days <= 0) {
                            continue;
                        }
                        // Обновляем по актуальной карте seed (в т.ч. после смены VERSION)
                        $item->forceFill([
                            'due_days_from_start' => $days,
                            'due_at' => $this->dueAtFromProjectStart(
                                $start,
                                $days,
                                (bool) ($project->skip_weekends ?? false)
                            ),
                        ])->save();
                    }
                }
            });
    }

    /**
     * Просроченные / скоро дедлайн задачи для пользователя (виджет и шапка).
     *
     * @return array{count:int,overdue:int,soon:int,items:\Illuminate\Support\Collection}
     */
    public function dueAlertsForUser(int $userId): array
    {
        $empty = [
            'count' => 0,
            'overdue' => 0,
            'soon' => 0,
            'items' => collect(),
        ];

        if (!SeoChecklistProject::tableReady() || !Schema::hasColumn('seo_checklist_items', 'due_at')) {
            return $empty;
        }

        $projectIds = $this->accessibleProjectsQuery($userId)
            ->where('status', 'active')
            ->pluck('id');

        if ($projectIds->isEmpty()) {
            return $empty;
        }

        $soonUntil = now()->addDays(2);
        $items = SeoChecklistItem::query()
            ->whereIn('project_id', $projectIds->all())
            ->whereNull('parent_id')
            ->whereNotNull('due_at')
            ->whereIn('status', SeoChecklistItem::OPEN_STATUSES)
            ->where('due_at', '<=', $soonUntil)
            ->with(['project:id,domain,title,user_id'])
            ->orderBy('due_at')
            ->limit(40)
            ->get();

        $overdue = 0;
        $soon = 0;
        foreach ($items as $item) {
            if ($item->isOverdue()) {
                $overdue++;
            } else {
                $soon++;
            }
        }

        return [
            'count' => $items->count(),
            'overdue' => $overdue,
            'soon' => $soon,
            'items' => $items,
        ];
    }

    /**
     * План работ: мои открытые задачи по срокам (по всем доступным проектам).
     *
     * @return array{
     *   count:int,
     *   overdue:int,
     *   doing:int,
     *   groups:array<string, array{key:string,title:string,items:\Illuminate\Support\Collection}>
     * }
     */
    public function workPlanForUser(int $userId, int $horizonDays = 21, int $limit = 120): array
    {
        $emptyGroups = $this->emptyWorkPlanGroups();
        $empty = [
            'count' => 0,
            'overdue' => 0,
            'doing' => 0,
            'groups' => $emptyGroups,
        ];

        if (!SeoChecklistProject::tableReady()) {
            return $empty;
        }

        $projects = $this->accessibleProjectsQuery($userId)
            ->where('status', 'active')
            ->get(['id', 'domain', 'title', 'user_id', 'owner_user_id', 'pm_user_id', 'team_id']);

        if ($projects->isEmpty()) {
            return $empty;
        }

        $rolesByProject = [];
        foreach ($projects as $project) {
            $rolesByProject[(int) $project->id] = $this->myTaskRoles($project, $userId);
        }

        $projectIds = $projects->pluck('id')->all();
        $horizon = now()->addDays(max(1, $horizonDays))->endOfDay();
        $hasDue = Schema::hasColumn('seo_checklist_items', 'due_at');

        $query = SeoChecklistItem::query()
            ->whereIn('project_id', $projectIds)
            ->whereNull('parent_id')
            ->whereIn('status', SeoChecklistItem::OPEN_STATUSES)
            ->with([
                'project:id,domain,title,user_id,owner_user_id,pm_user_id,team_id,status',
                'notes' => function ($q) {
                    $q->orderByDesc('id')->with('user');
                },
                'children' => function ($q) use ($userId) {
                    $q->orderBy('sort')->orderBy('id')
                        ->with([
                            'createdByUser',
                            'doneByUser',
                            'timeLogs' => function ($tq) use ($userId) {
                                $tq->where('user_id', $userId)->whereNull('ended_at')->orderByDesc('id');
                            },
                        ]);
                },
                'timeLogs' => function ($q) use ($userId) {
                    $q->where('user_id', $userId)->whereNull('ended_at')->orderByDesc('id');
                },
            ]);

        if ($hasDue) {
            $query->where(function ($q) use ($horizon) {
                $q->where(function ($q2) use ($horizon) {
                    $q2->whereNotNull('due_at')->where('due_at', '<=', $horizon);
                })->orWhereIn('status', ['doing', 'rework', 'clarify', 'review'])
                    ->orWhere(function ($q3) {
                        $q3->where('is_important', true)->whereNull('due_at');
                    });
            });
            $query->orderByRaw('CASE WHEN due_at IS NULL THEN 1 ELSE 0 END')
                ->orderBy('due_at')
                ->orderByDesc('is_important')
                ->orderBy('id');
        } else {
            $query->where(function ($q) {
                $q->whereIn('status', ['doing', 'rework', 'clarify', 'review'])->orWhere('is_important', true);
            })
                ->orderByDesc('is_important')
                ->orderBy('id');
        }

        $candidates = $query->limit(400)->get();
        $mine = $candidates->filter(function (SeoChecklistItem $item) use ($rolesByProject) {
            return $this->itemMatchesMyRoles(
                (string) $item->role,
                $rolesByProject[(int) $item->project_id] ?? []
            );
        })->values();

        $groups = $emptyGroups;
        $overdue = 0;
        $doing = 0;
        $today = now()->startOfDay();
        $tomorrow = now()->addDay()->startOfDay();
        $weekEnd = now()->addDays(7)->endOfDay();
        $pinnedIds = [];

        foreach ($mine as $item) {
            $status = $item->status === 'blocked' ? 'clarify' : $item->status;
            if ($status === 'doing') {
                $doing++;
                $groups['doing']['items']->push($item);
                $pinnedIds[(int) $item->id] = true;
            } elseif ($status === 'rework') {
                $groups['rework']['items']->push($item);
                $pinnedIds[(int) $item->id] = true;
            } elseif ($status === 'clarify') {
                $groups['clarify']['items']->push($item);
                $pinnedIds[(int) $item->id] = true;
            } elseif ($status === 'review') {
                $groups['review']['items']->push($item);
                $pinnedIds[(int) $item->id] = true;
            }

            if ($hasDue && $item->due_at) {
                if ($item->isOverdue()) {
                    $groups['overdue']['items']->push($item);
                    $overdue++;
                    continue;
                }
                if ($item->due_at->isSameDay($today)) {
                    $groups['today']['items']->push($item);
                } elseif ($item->due_at->isSameDay($tomorrow)) {
                    $groups['tomorrow']['items']->push($item);
                } elseif ($item->due_at->lte($weekEnd)) {
                    $groups['week']['items']->push($item);
                } else {
                    $groups['later']['items']->push($item);
                }
                continue;
            }

            if (!empty($item->is_important) && empty($pinnedIds[(int) $item->id])) {
                $groups['no_due']['items']->push($item);
            }
        }

        $count = 0;
        foreach ($groups as $key => $group) {
            $groups[$key]['items'] = $group['items']->take($limit)->values();
            if (in_array($key, ['overdue', 'today', 'tomorrow', 'week'], true)) {
                $count += $groups[$key]['items']->count();
            }
        }

        return [
            'count' => $count,
            'overdue' => $overdue,
            'doing' => $doing,
            'groups' => $groups,
        ];
    }

    /**
     * @return array<string, array{key:string,title:string,items:\Illuminate\Support\Collection}>
     */
    private function emptyWorkPlanGroups(): array
    {
        return [
            'doing' => ['key' => 'doing', 'title' => __('In progress'), 'items' => collect()],
            'rework' => ['key' => 'rework', 'title' => __('Status rework'), 'items' => collect()],
            'clarify' => ['key' => 'clarify', 'title' => __('Status clarify'), 'items' => collect()],
            'review' => ['key' => 'review', 'title' => __('Status review'), 'items' => collect()],
            'overdue' => ['key' => 'overdue', 'title' => __('Overdue'), 'items' => collect()],
            'today' => ['key' => 'today', 'title' => __('Today'), 'items' => collect()],
            'tomorrow' => ['key' => 'tomorrow', 'title' => __('Tomorrow'), 'items' => collect()],
            'week' => ['key' => 'week', 'title' => __('Next 7 days'), 'items' => collect()],
            'later' => ['key' => 'later', 'title' => __('Later'), 'items' => collect()],
            'no_due' => ['key' => 'no_due', 'title' => __('Important without due date'), 'items' => collect()],
        ];
    }

    /**
     * @param  list<string>  $myRoles
     */
    private function itemMatchesMyRoles(string $role, array $myRoles): bool
    {
        if ($myRoles === []) {
            return false;
        }
        if ($role === 'shared' || $role === 'any') {
            return true;
        }
        if (in_array($role, $myRoles, true)) {
            return true;
        }

        return ($role === 'owner' && in_array('owner', $myRoles, true))
            || ($role === 'pm' && in_array('pm', $myRoles, true));
    }

    /**
     * @return \Illuminate\Support\Collection<int, SeoChecklistTemplate>
     */
    public function templatesForUser(int $userId)
    {
        $this->ensureSystemTemplate();

        return SeoChecklistTemplate::query()
            ->withCount('tasks')
            ->withCount(['projects as projects_count' => function ($q) use ($userId) {
                $q->where('user_id', $userId);
            }])
            ->where(function ($q) use ($userId) {
                $q->where('is_system', true)
                    ->orWhere('user_id', $userId);
            })
            ->orderByDesc('is_system')
            ->orderBy('title')
            ->get();
    }

    /**
     * Люди, назначенные Owner/PM в чеклистах пользователя.
     *
     * @return array<int, array{user:User,as_owner:int,as_pm:int,projects:array<int,array{id:int,domain:string,role:string}>}>
     */
    public function teamRoster(int $userId): array
    {
        $projects = SeoChecklistProject::query()
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->with(['ownerUser', 'pmUser'])
            ->orderBy('domain')
            ->get();

        $roster = [];
        foreach ($projects as $project) {
            foreach ([['owner', $project->ownerUser], ['pm', $project->pmUser]] as $pair) {
                $role = $pair[0];
                /** @var User|null $user */
                $user = $pair[1];
                if (!$user) {
                    continue;
                }
                $uid = (int) $user->id;
                if (!isset($roster[$uid])) {
                    $roster[$uid] = [
                        'user' => $user,
                        'as_owner' => 0,
                        'as_pm' => 0,
                        'projects' => [],
                    ];
                }
                if ($role === 'owner') {
                    $roster[$uid]['as_owner']++;
                } else {
                    $roster[$uid]['as_pm']++;
                }
                $roster[$uid]['projects'][] = [
                    'id' => (int) $project->id,
                    'domain' => (string) $project->domain,
                    'role' => $role,
                ];
            }
        }

        uasort($roster, static function ($a, $b) {
            return strcasecmp(
                trim(($a['user']->name ?? '') . ' ' . ($a['user']->last_name ?? '')),
                trim(($b['user']->name ?? '') . ' ' . ($b['user']->last_name ?? ''))
            );
        });

        return array_values($roster);
    }

    /**
     * Кандидаты для picker (уникальные user_id из команды + текущий user).
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    public function teamCandidates(int $userId)
    {
        $ids = SeoChecklistProject::query()
            ->where('user_id', $userId)
            ->get(['owner_user_id', 'pm_user_id'])
            ->flatMap(static function ($p) {
                return [(int) $p->owner_user_id, (int) $p->pm_user_id];
            })
            ->filter()
            ->push($userId);

        if (SeoChecklistTeam::tableReady()) {
            $teamIds = SeoChecklistTeam::query()->where('user_id', $userId)->pluck('id');
            $memberIds = SeoChecklistTeamMember::query()
                ->whereIn('team_id', $teamIds)
                ->pluck('user_id');
            $ids = $ids->merge($memberIds);
        }

        $ids = $ids->unique()->values()->all();

        return User::query()
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->orderBy('last_name')
            ->get();
    }

    /**
     * Проекты, доступные пользователю: свои + Owner/PM + член команды.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function accessibleProjectsQuery(int $userId)
    {
        $teamIds = [];
        if (SeoChecklistTeam::tableReady()) {
            $teamIds = SeoChecklistTeamMember::query()
                ->where('user_id', $userId)
                ->pluck('team_id')
                ->map(static function ($id) {
                    return (int) $id;
                })
                ->unique()
                ->values()
                ->all();
        }

        return SeoChecklistProject::query()->where(function ($q) use ($userId, $teamIds) {
            $q->where('user_id', $userId)
                ->orWhere('owner_user_id', $userId)
                ->orWhere('pm_user_id', $userId);
            if ($teamIds !== []) {
                $q->orWhereIn('team_id', $teamIds);
            }
        });
    }

    public function findAccessibleProject(int $userId, int $id): ?SeoChecklistProject
    {
        return $this->accessibleProjectsQuery($userId)
            ->where('id', $id)
            ->first();
    }

    /**
     * Управление проектом (команда, архив, добавление/удаление задач) — только владелец аккаунта.
     */
    public function canManageProject(SeoChecklistProject $project, int $userId): bool
    {
        return (int) $project->user_id === $userId;
    }

    /**
     * Работа со статусами/заметками — владелец аккаунта, Owner/PM или член назначенной команды.
     */
    public function canWorkProject(SeoChecklistProject $project, int $userId): bool
    {
        if ((int) $project->user_id === $userId) {
            return true;
        }
        if ((int) $project->owner_user_id === $userId) {
            return true;
        }
        if ((int) $project->pm_user_id === $userId) {
            return true;
        }
        if ($project->team_id && SeoChecklistTeam::tableReady()) {
            return SeoChecklistTeamMember::query()
                ->where('team_id', (int) $project->team_id)
                ->where('user_id', $userId)
                ->exists();
        }

        return false;
    }

    /**
     * @return string account|owner|pm|auditor|participant|none
     */
    public function accessKind(SeoChecklistProject $project, int $userId): string
    {
        if ((int) $project->user_id === $userId) {
            return 'account';
        }

        if ($project->team_id && SeoChecklistTeam::tableReady()) {
            $member = SeoChecklistTeamMember::query()
                ->where('team_id', (int) $project->team_id)
                ->where('user_id', $userId)
                ->first();
            if ($member && in_array($member->role, SeoChecklistTeam::roleKeys(), true)) {
                return (string) $member->role;
            }
        }

        if ((int) $project->owner_user_id === $userId) {
            return 'owner';
        }
        if ((int) $project->pm_user_id === $userId) {
            return 'pm';
        }

        return 'none';
    }

    /**
     * Роли для фильтра «Мои задачи» по членству в команде / legacy Owner/PM.
     *
     * @return string[]
     */
    public function myTaskRoles(SeoChecklistProject $project, int $userId): array
    {
        $roles = [];
        if ((int) $project->user_id === $userId || (int) $project->owner_user_id === $userId) {
            $roles[] = 'owner';
        }
        if ((int) $project->pm_user_id === $userId) {
            $roles[] = 'pm';
        }

        if ($project->team_id && SeoChecklistTeam::tableReady()) {
            $memberRole = SeoChecklistTeamMember::query()
                ->where('team_id', (int) $project->team_id)
                ->where('user_id', $userId)
                ->value('role');
            if ($memberRole === 'owner') {
                $roles[] = 'owner';
            } elseif ($memberRole === 'pm') {
                $roles[] = 'pm';
            } elseif (in_array($memberRole, ['auditor', 'participant'], true)) {
                $roles[] = 'shared';
            }
        }

        return array_values(array_unique($roles));
    }

    /**
     * @return \Illuminate\Support\Collection<int, SeoChecklistTeam>
     */
    public function teamsForUser(int $userId)
    {
        if (!SeoChecklistTeam::tableReady()) {
            return collect();
        }

        return SeoChecklistTeam::query()
            ->where('user_id', $userId)
            ->with(['members.user'])
            ->withCount(['members', 'projects'])
            ->orderBy('title')
            ->get();
    }

    public function findOwnedTeam(int $userId, int $teamId): ?SeoChecklistTeam
    {
        if (!SeoChecklistTeam::tableReady()) {
            return null;
        }

        return SeoChecklistTeam::query()
            ->where('user_id', $userId)
            ->where('id', $teamId)
            ->first();
    }

    /**
     * @return array{ok:bool,message?:string,team?:SeoChecklistTeam}
     */
    public function createTeam(int $userId, string $title, ?string $description = null): array
    {
        if (!SeoChecklistTeam::tableReady()) {
            return ['ok' => false, 'message' => __('Teams are not available')];
        }

        $title = trim($title);
        if ($title === '') {
            return ['ok' => false, 'message' => __('Title required')];
        }

        $team = SeoChecklistTeam::query()->create([
            'user_id' => $userId,
            'title' => $title,
            'description' => $description !== null ? trim($description) : null,
        ]);

        // Создатель сразу Owner в команде
        SeoChecklistTeamMember::query()->create([
            'team_id' => $team->id,
            'user_id' => $userId,
            'role' => 'owner',
        ]);

        return ['ok' => true, 'team' => $team->fresh(['members.user'])];
    }

    /**
     * @return array{ok:bool,message?:string}
     */
    public function updateTeam(SeoChecklistTeam $team, string $title, ?string $description = null): array
    {
        $title = trim($title);
        if ($title === '') {
            return ['ok' => false, 'message' => __('Title required')];
        }

        $team->forceFill([
            'title' => $title,
            'description' => $description !== null ? trim($description) : $team->description,
        ])->save();

        return ['ok' => true];
    }

    /**
     * @return array{ok:bool,message?:string}
     */
    public function deleteTeam(SeoChecklistTeam $team): array
    {
        $inUse = SeoChecklistProject::query()->where('team_id', $team->id)->exists();
        if (!$inUse && Schema::hasTable('seo_report_projects') && Schema::hasColumn('seo_report_projects', 'team_id')) {
            $inUse = \App\SeoReports\SeoReportProject::query()->where('team_id', $team->id)->exists();
        }
        if ($inUse) {
            return ['ok' => false, 'message' => __('Team is used by projects')];
        }

        DB::transaction(function () use ($team) {
            $team->members()->delete();
            $team->delete();
        });

        return ['ok' => true];
    }

    /**
     * @return array{ok:bool,message?:string,member?:SeoChecklistTeamMember}
     */
    public function addTeamMember(SeoChecklistTeam $team, int $memberUserId, string $role): array
    {
        if (!in_array($role, SeoChecklistTeam::roleKeys(), true)) {
            return ['ok' => false, 'message' => __('Invalid role')];
        }
        if (!User::query()->whereKey($memberUserId)->exists()) {
            return ['ok' => false, 'message' => __('User not found')];
        }

        $member = SeoChecklistTeamMember::query()->updateOrCreate(
            ['team_id' => $team->id, 'user_id' => $memberUserId],
            ['role' => $role]
        );

        $this->syncProjectsDenormFromTeam($team);

        return ['ok' => true, 'member' => $member->fresh('user')];
    }

    /**
     * @return array{ok:bool,message?:string,member?:SeoChecklistTeamMember}
     */
    public function addTeamMemberByEmail(SeoChecklistTeam $team, string $email, string $role): array
    {
        $email = trim($email);
        $user = User::query()->where('email', $email)->first();
        if (!$user) {
            return ['ok' => false, 'message' => __('SEO checklist team member not found')];
        }

        return $this->addTeamMember($team, (int) $user->id, $role);
    }

    /**
     * @return array{ok:bool,message?:string}
     */
    public function updateTeamMemberRole(SeoChecklistTeam $team, int $memberId, string $role): array
    {
        if (!in_array($role, SeoChecklistTeam::roleKeys(), true)) {
            return ['ok' => false, 'message' => __('Invalid role')];
        }

        /** @var SeoChecklistTeamMember|null $member */
        $member = $team->members()->where('id', $memberId)->first();
        if (!$member) {
            return ['ok' => false, 'message' => __('Member not found')];
        }

        $member->role = $role;
        $member->save();
        $this->syncProjectsDenormFromTeam($team);

        return ['ok' => true];
    }

    /**
     * @return array{ok:bool,message?:string}
     */
    public function removeTeamMember(SeoChecklistTeam $team, int $memberId): array
    {
        /** @var SeoChecklistTeamMember|null $member */
        $member = $team->members()->where('id', $memberId)->first();
        if (!$member) {
            return ['ok' => false, 'message' => __('Member not found')];
        }

        $member->delete();
        $this->syncProjectsDenormFromTeam($team);

        return ['ok' => true];
    }

    /**
     * Назначить команду на проект; owner/pm денормализуются для карточек.
     *
     * @return array{ok:bool,message?:string}
     */
    public function assignTeamToProject(SeoChecklistProject $project, ?int $teamId): array
    {
        if ($teamId) {
            $team = $this->findOwnedTeam((int) $project->user_id, $teamId);
            if (!$team) {
                return ['ok' => false, 'message' => __('Team not found')];
            }
            $project->team_id = $team->id;
            $this->applyTeamDenormToProject($project, $team);
        } else {
            $project->team_id = null;
        }

        $project->last_activity_at = now();
        $project->save();

        return ['ok' => true];
    }

    public function syncProjectsDenormFromTeam(SeoChecklistTeam $team): void
    {
        SeoChecklistProject::query()
            ->where('team_id', $team->id)
            ->get()
            ->each(function (SeoChecklistProject $project) use ($team) {
                $this->applyTeamDenormToProject($project, $team);
                $project->save();
            });
    }

    private function applyTeamDenormToProject(SeoChecklistProject $project, SeoChecklistTeam $team): void
    {
        $team->loadMissing('members');
        $ownerMember = $team->members->firstWhere('role', 'owner') ?: $team->members->first();
        $pmMember = $team->members->firstWhere('role', 'pm');

        $project->owner_user_id = $ownerMember ? (int) $ownerMember->user_id : (int) $project->user_id;
        $project->pm_user_id = $pmMember ? (int) $pmMember->user_id : null;
    }

    public function findUsableTemplate(int $userId, ?int $templateId): ?SeoChecklistTemplate
    {
        $this->ensureSystemTemplate();

        if (!$templateId) {
            return SeoChecklistTemplate::systemDefault();
        }

        return SeoChecklistTemplate::query()
            ->where('id', $templateId)
            ->where(function ($q) use ($userId) {
                $q->where('is_system', true)
                    ->orWhere('user_id', $userId);
            })
            ->first();
    }

    /**
     * Пользовательский шаблон без задач.
     * preset: empty | skeleton (этапы SEO без задач).
     *
     * @return array{ok:bool,message?:string,template?:SeoChecklistTemplate}
     */
    public function createEmptyTemplate(
        int $userId,
        string $title,
        ?string $description = null,
        string $preset = 'empty'
    ): array {
        $title = trim($title);
        if ($title === '') {
            return ['ok' => false, 'message' => __('Title required')];
        }

        $preset = in_array($preset, ['empty', 'skeleton'], true) ? $preset : 'empty';

        try {
            $template = DB::transaction(function () use ($userId, $title, $description, $preset) {
                $code = 'user_' . $userId . '_' . substr(md5($title . microtime(true)), 0, 10);
                $data = [
                    'user_id' => $userId,
                    'code' => $code,
                    'title' => $title,
                    'description' => $description !== null ? (trim($description) ?: null) : null,
                    'is_system' => false,
                ];
                if (Schema::hasColumn('seo_checklist_templates', 'stages_json')) {
                    $data['stages_json'] = $preset === 'skeleton'
                        ? SeoChecklistDefaultTemplate::skeletonStagesList()
                        : [];
                }

                return SeoChecklistTemplate::query()->create($data)->fresh(['tasks']);
            });
        } catch (Throwable $e) {
            report($e);

            return ['ok' => false, 'message' => __('Could not create template')];
        }

        return ['ok' => true, 'template' => $template];
    }

    /**
     * @return array{ok:bool,message?:string,template?:SeoChecklistTemplate}
     */
    public function cloneTemplate(int $userId, string $title, ?int $sourceId = null): array
    {
        $title = trim($title);
        if ($title === '') {
            return ['ok' => false, 'message' => __('Title required')];
        }

        $source = $this->findUsableTemplate($userId, $sourceId);
        if (!$source) {
            return ['ok' => false, 'message' => __('Template not found')];
        }

        try {
            $template = DB::transaction(function () use ($userId, $title, $source) {
                $code = 'user_' . $userId . '_' . substr(md5($title . microtime(true)), 0, 10);
                $templateData = [
                    'user_id' => $userId,
                    'code' => $code,
                    'title' => $title,
                    'description' => $source->description,
                    'is_system' => false,
                ];
                if (Schema::hasColumn('seo_checklist_templates', 'skip_weekends')) {
                    $templateData['skip_weekends'] = (bool) ($source->skip_weekends ?? false);
                }
                if (Schema::hasColumn('seo_checklist_templates', 'stages_json')) {
                    $stages = $this->stagesListForTemplate($source);
                    $templateData['stages_json'] = $stages !== []
                        ? $stages
                        : SeoChecklistDefaultTemplate::skeletonStagesList();
                }
                $template = SeoChecklistTemplate::query()->create($templateData);

                foreach ($source->tasks()->whereNull('parent_id')->orderBy('stage_sort')->orderBy('sort')->get() as $task) {
                    $parent = SeoChecklistTemplateTask::query()->create([
                        'template_id' => $template->id,
                        'parent_id' => null,
                        'code' => $task->code,
                        'stage_key' => $task->stage_key,
                        'stage_sort' => $task->stage_sort,
                        'sort' => $task->sort,
                        'title' => $task->title,
                        'help' => $task->help,
                        'role' => $task->role,
                        'is_important' => $task->is_important,
                        'allows_subtasks' => $task->allows_subtasks,
                        'repeat_rule' => $task->repeat_rule,
                        'due_days_from_start' => $task->due_days_from_start,
                        'links_json' => $task->links_json ?: [],
                    ]);

                    foreach ($task->children as $child) {
                        SeoChecklistTemplateTask::query()->create([
                            'template_id' => $template->id,
                            'parent_id' => $parent->id,
                            'code' => $child->code,
                            'stage_key' => $child->stage_key,
                            'stage_sort' => $child->stage_sort,
                            'sort' => $child->sort,
                            'title' => $child->title,
                            'help' => $child->help,
                            'role' => $child->role,
                            'is_important' => false,
                            'allows_subtasks' => false,
                            'repeat_rule' => null,
                            'due_days_from_start' => null,
                            'links_json' => [],
                        ]);
                    }
                }

                return $template->fresh(['tasks']);
            });
        } catch (Throwable $e) {
            report($e);

            return ['ok' => false, 'message' => __('Could not create template')];
        }

        return ['ok' => true, 'template' => $template];
    }

    /**
     * Системный шаблон могут править только админы / Super Admin.
     */
    public function canEditTemplate(SeoChecklistTemplate $template, ?int $userId = null): bool
    {
        if (!$template->is_system) {
            if ($userId === null) {
                $userId = (int) Auth::id();
            }

            return (int) $template->user_id === (int) $userId;
        }

        return User::isUserAdmin();
    }

    /**
     * @return array{ok:bool,message?:string}
     */
    public function updateTemplate(SeoChecklistTemplate $template, string $title, ?string $description, ?bool $skipWeekends = null): array
    {
        if ($template->is_system && !User::isUserAdmin()) {
            return ['ok' => false, 'message' => __('System template is read-only')];
        }

        $title = trim($title);
        if ($title === '') {
            return ['ok' => false, 'message' => __('Title required')];
        }

        $fill = [
            'title' => $title,
            'description' => $description !== null ? trim($description) : $template->description,
        ];
        if ($skipWeekends !== null && Schema::hasColumn('seo_checklist_templates', 'skip_weekends')) {
            $fill['skip_weekends'] = (bool) $skipWeekends;
        }
        $template->forceFill($fill)->save();

        if ($skipWeekends !== null && Schema::hasColumn('seo_checklist_projects', 'skip_weekends')) {
            SeoChecklistProject::query()
                ->where('template_id', $template->id)
                ->where('status', 'active')
                ->update(['skip_weekends' => (bool) $skipWeekends]);

            $this->recalcDueDatesForTemplateProjects((int) $template->id);
        }

        return ['ok' => true];
    }

    /**
     * Пересчёт due_at у активных проектов шаблона (после смены skip_weekends).
     */
    public function recalcDueDatesForTemplateProjects(int $templateId): void
    {
        if (!Schema::hasColumn('seo_checklist_items', 'due_at')) {
            return;
        }

        SeoChecklistProject::query()
            ->where('template_id', $templateId)
            ->where('status', 'active')
            ->orderBy('id')
            ->chunkById(50, function ($projects) {
                foreach ($projects as $project) {
                    $skip = (bool) ($project->skip_weekends ?? false);
                    $start = $project->created_at ?: now();
                    SeoChecklistItem::query()
                        ->where('project_id', $project->id)
                        ->whereNull('parent_id')
                        ->whereNotNull('due_days_from_start')
                        ->orderBy('id')
                        ->each(function (SeoChecklistItem $item) use ($start, $skip) {
                            $item->forceFill([
                                'due_at' => $this->dueAtFromProjectStart(
                                    $start,
                                    (int) $item->due_days_from_start,
                                    $skip
                                ),
                            ])->save();
                        });
                }
            });
    }

    /**
     * @return array{ok:bool,message?:string}
     */
    public function updateTemplateTask(SeoChecklistTemplate $template, int $taskId, array $payload): array
    {
        if ($template->is_system && !User::isUserAdmin()) {
            return ['ok' => false, 'message' => __('System template is read-only')];
        }

        /** @var SeoChecklistTemplateTask|null $task */
        $task = $template->tasks()->where('id', $taskId)->first();
        if (!$task) {
            return ['ok' => false, 'message' => __('Task not found')];
        }

        $title = trim((string) ($payload['title'] ?? $task->title));
        if ($title === '') {
            return ['ok' => false, 'message' => __('Title required')];
        }

        $role = (string) ($payload['role'] ?? $task->role);
        if (!in_array($role, ['owner', 'pm', 'shared', 'any'], true)) {
            $role = $task->role;
        }

        $repeat = $payload['repeat_rule'] ?? $task->repeat_rule;
        if ($repeat === '') {
            $repeat = null;
        }
        $normalizedRepeat = SeoChecklistDefaultTemplate::normalizeRepeatRule($repeat);
        if ($repeat !== null && $normalizedRepeat === null) {
            $normalizedRepeat = $task->repeat_rule;
        }

        $task->forceFill([
            'title' => $title,
            'help' => array_key_exists('help', $payload) ? (trim((string) $payload['help']) ?: null) : $task->help,
            'role' => $role,
            'is_important' => array_key_exists('is_important', $payload)
                ? (bool) $payload['is_important']
                : $task->is_important,
            'allows_subtasks' => array_key_exists('allows_subtasks', $payload)
                ? (bool) $payload['allows_subtasks']
                : $task->allows_subtasks,
            'repeat_rule' => array_key_exists('repeat_rule', $payload) ? $normalizedRepeat : $task->repeat_rule,
            'due_days_from_start' => array_key_exists('due_days_from_start', $payload)
                ? $this->normalizeDueDays($payload['due_days_from_start'])
                : $task->due_days_from_start,
        ])->save();

        if (array_key_exists('allows_subtasks', $payload) && $task->code) {
            SeoChecklistItem::query()
                ->where('code', $task->code)
                ->whereNull('parent_id')
                ->whereHas('project', function ($q) use ($template) {
                    $q->where('template_id', $template->id)->where('status', 'active');
                })
                ->update(['allows_subtasks' => (bool) $task->allows_subtasks]);
        }

        return ['ok' => true];
    }

    /**
     * Переставить задачу внутри этапа (↑ / ↓).
     *
     * @return array{ok:bool,message?:string}
     */
    public function moveTemplateTask(SeoChecklistTemplate $template, int $taskId, string $direction): array
    {
        if ($template->is_system && !User::isUserAdmin()) {
            return ['ok' => false, 'message' => __('System template is read-only')];
        }

        $direction = strtolower(trim($direction));
        if (!in_array($direction, ['up', 'down'], true)) {
            return ['ok' => false, 'message' => __('Invalid direction')];
        }

        /** @var SeoChecklistTemplateTask|null $task */
        $task = $template->tasks()->where('id', $taskId)->first();
        if (!$task) {
            return ['ok' => false, 'message' => __('Task not found')];
        }

        $siblings = $template->tasks()
            ->where('stage_key', $task->stage_key)
            ->whereNull('parent_id')
            ->orderBy('sort')
            ->orderBy('id')
            ->get()
            ->values();

        $index = $siblings->search(static function (SeoChecklistTemplateTask $row) use ($taskId) {
            return (int) $row->id === $taskId;
        });
        if ($index === false) {
            return ['ok' => false, 'message' => __('Task not found')];
        }

        $swapWith = $direction === 'up' ? $index - 1 : $index + 1;
        if ($swapWith < 0 || $swapWith >= $siblings->count()) {
            return ['ok' => true];
        }

        $ordered = $siblings->all();
        $tmp = $ordered[$index];
        $ordered[$index] = $ordered[$swapWith];
        $ordered[$swapWith] = $tmp;

        foreach ($ordered as $i => $row) {
            $newSort = ($i + 1) * 10;
            if ((int) $row->sort !== $newSort) {
                $row->forceFill(['sort' => $newSort])->save();
            }
        }

        return ['ok' => true];
    }

    private function normalizeDueDays($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $days = (int) $value;
        if ($days < 1) {
            return null;
        }
        if ($days > 365) {
            $days = 365;
        }

        return $days;
    }

    private function dueAtFromProjectStart($projectCreatedAt, ?int $dueDays, bool $skipWeekends = false): ?\Carbon\Carbon
    {
        if (!$dueDays || $dueDays < 1) {
            return null;
        }
        $start = $projectCreatedAt ? \Carbon\Carbon::parse($projectCreatedAt) : now();
        if (!$skipWeekends) {
            return $start->copy()->addDays($dueDays);
        }

        return $this->addBusinessDays($start, $dueDays);
    }

    /**
     * Прибавить N рабочих дней (пн–пт). Сб/вс не считаются.
     */
    public function addBusinessDays(\Carbon\Carbon $start, int $days): \Carbon\Carbon
    {
        $date = $start->copy()->startOfDay();
        $left = max(0, $days);
        while ($left > 0) {
            $date->addDay();
            if ($date->isWeekend()) {
                continue;
            }
            $left--;
        }

        return $date;
    }

    /**
     * @return array{ok:bool,message?:string}
     */
    public function deleteTemplateTask(SeoChecklistTemplate $template, int $taskId): array
    {
        if ($template->is_system && !User::isUserAdmin()) {
            return ['ok' => false, 'message' => __('System template is read-only')];
        }

        $task = $template->tasks()->where('id', $taskId)->first();
        if (!$task) {
            return ['ok' => false, 'message' => __('Task not found')];
        }

        SeoChecklistTemplateTask::query()
            ->where('template_id', $template->id)
            ->where('parent_id', $task->id)
            ->delete();
        $task->delete();

        return ['ok' => true];
    }

    /**
     * @return array{ok:bool,message?:string,task?:SeoChecklistTemplateTask}
     */
    public function addTemplateSubtask(SeoChecklistTemplate $template, int $parentTaskId, string $title): array
    {
        if ($template->is_system && !User::isUserAdmin()) {
            return ['ok' => false, 'message' => __('System template is read-only')];
        }

        $title = trim($title);
        if ($title === '') {
            return ['ok' => false, 'message' => __('Title required')];
        }

        /** @var SeoChecklistTemplateTask|null $parent */
        $parent = $template->tasks()->where('id', $parentTaskId)->whereNull('parent_id')->first();
        if (!$parent) {
            return ['ok' => false, 'message' => __('Task not found')];
        }

        if (!$parent->allows_subtasks) {
            $parent->forceFill(['allows_subtasks' => true])->save();
        }

        $sort = (int) $template->tasks()->where('parent_id', $parent->id)->max('sort') + 10;
        $child = SeoChecklistTemplateTask::query()->create([
            'template_id' => $template->id,
            'parent_id' => $parent->id,
            'code' => $parent->code . '_sub_' . substr(md5($title . microtime(true) . mt_rand()), 0, 8),
            'stage_key' => $parent->stage_key,
            'stage_sort' => $parent->stage_sort,
            'sort' => $sort,
            'title' => $title,
            'help' => null,
            'role' => $parent->role,
            'is_important' => false,
            'allows_subtasks' => false,
            'repeat_rule' => null,
            'due_days_from_start' => null,
            'links_json' => [],
        ]);

        return ['ok' => true, 'task' => $child];
    }

    /**
     * @return array{ok:bool,message?:string,task?:SeoChecklistTemplateTask}
     */
    public function addTemplateTask(SeoChecklistTemplate $template, array $payload): array
    {
        if ($template->is_system && !User::isUserAdmin()) {
            return ['ok' => false, 'message' => __('System template is read-only')];
        }

        $title = trim((string) ($payload['title'] ?? ''));
        if ($title === '') {
            return ['ok' => false, 'message' => __('Title required')];
        }

        $stages = $this->resolveTemplateStages($template);
        $stageKey = (string) ($payload['stage_key'] ?? '');
        if ($stageKey === '' || !isset($stages[$stageKey])) {
            return ['ok' => false, 'message' => __('Invalid stage')];
        }

        $role = (string) ($payload['role'] ?? 'owner');
        if (!in_array($role, ['owner', 'pm', 'shared', 'any'], true)) {
            $role = 'owner';
        }

        $repeat = SeoChecklistDefaultTemplate::normalizeRepeatRule($payload['repeat_rule'] ?? null);

        $sort = (int) $template->tasks()->where('stage_key', $stageKey)->whereNull('parent_id')->max('sort') + 10;
        $code = 'custom_' . substr(md5($title . microtime(true) . mt_rand()), 0, 12);

        $task = SeoChecklistTemplateTask::query()->create([
            'template_id' => $template->id,
            'parent_id' => null,
            'code' => $code,
            'stage_key' => $stageKey,
            'stage_sort' => (int) ($stages[$stageKey]['sort'] ?? 0),
            'sort' => $sort,
            'title' => $title,
            'help' => trim((string) ($payload['help'] ?? '')) ?: null,
            'role' => $role,
            'is_important' => !empty($payload['is_important']),
            'allows_subtasks' => true,
            'repeat_rule' => $repeat,
            'due_days_from_start' => $this->normalizeDueDays($payload['due_days_from_start'] ?? null),
            'links_json' => [],
        ]);

        return ['ok' => true, 'task' => $task];
    }

    /**
     * @return array{ok:bool,message?:string}
     */
    public function applySkeletonStages(SeoChecklistTemplate $template): array
    {
        if ($template->is_system && !User::isUserAdmin()) {
            return ['ok' => false, 'message' => __('System template is read-only')];
        }
        if (!Schema::hasColumn('seo_checklist_templates', 'stages_json')) {
            return ['ok' => false, 'message' => __('Error')];
        }

        $map = SeoChecklistDefaultTemplate::skeletonStagesMap();
        // Сохраняем кастомные этапы, которых нет в скелете, если на них уже есть задачи
        foreach ($this->resolveTemplateStages($template) as $key => $meta) {
            if (!isset($map[$key])) {
                $map[$key] = $meta;
            }
        }
        $this->persistTemplateStages($template, $map);

        return ['ok' => true];
    }

    /**
     * @return array{ok:bool,message?:string,stage?:array}
     */
    public function addTemplateStage(SeoChecklistTemplate $template, string $title, ?string $lead = null): array
    {
        if ($template->is_system && !User::isUserAdmin()) {
            return ['ok' => false, 'message' => __('System template is read-only')];
        }

        $title = trim($title);
        if ($title === '') {
            return ['ok' => false, 'message' => __('Title required')];
        }

        $map = $this->resolveTemplateStages($template);
        $maxSort = 0;
        foreach ($map as $meta) {
            $maxSort = max($maxSort, (int) $meta['sort']);
        }
        $key = 'stage_' . substr(md5($title . microtime(true) . mt_rand()), 0, 10);
        $map[$key] = [
            'title' => $title,
            'lead' => $lead !== null && trim($lead) !== '' ? trim($lead) : null,
            'sort' => $maxSort + 10,
        ];
        $this->persistTemplateStages($template, $map);

        return [
            'ok' => true,
            'stage' => [
                'key' => $key,
                'title' => $map[$key]['title'],
                'lead' => $map[$key]['lead'],
                'sort' => $map[$key]['sort'],
            ],
        ];
    }

    /**
     * @return array{ok:bool,message?:string}
     */
    public function updateTemplateStage(
        SeoChecklistTemplate $template,
        string $stageKey,
        string $title,
        ?string $lead = null
    ): array {
        if ($template->is_system && !User::isUserAdmin()) {
            return ['ok' => false, 'message' => __('System template is read-only')];
        }

        $map = $this->resolveTemplateStages($template);
        if (!isset($map[$stageKey])) {
            return ['ok' => false, 'message' => __('Invalid stage')];
        }

        $title = trim($title);
        if ($title === '') {
            return ['ok' => false, 'message' => __('Title required')];
        }

        $map[$stageKey]['title'] = $title;
        $map[$stageKey]['lead'] = $lead !== null && trim($lead) !== '' ? trim($lead) : null;
        $this->persistTemplateStages($template, $map);

        return ['ok' => true];
    }

    /**
     * @return array{ok:bool,message?:string}
     */
    public function moveTemplateStage(SeoChecklistTemplate $template, string $stageKey, string $direction): array
    {
        if ($template->is_system && !User::isUserAdmin()) {
            return ['ok' => false, 'message' => __('System template is read-only')];
        }

        $direction = strtolower(trim($direction));
        if (!in_array($direction, ['up', 'down'], true)) {
            return ['ok' => false, 'message' => __('Error')];
        }

        $list = $this->stagesListForTemplate($template);
        $idx = null;
        foreach ($list as $i => $row) {
            if ($row['key'] === $stageKey) {
                $idx = $i;
                break;
            }
        }
        if ($idx === null) {
            return ['ok' => false, 'message' => __('Invalid stage')];
        }

        $swap = $direction === 'up' ? $idx - 1 : $idx + 1;
        if ($swap < 0 || $swap >= count($list)) {
            return ['ok' => false, 'message' => __('Order unchanged')];
        }

        $tmp = $list[$idx];
        $list[$idx] = $list[$swap];
        $list[$swap] = $tmp;

        $map = [];
        foreach ($list as $i => $row) {
            $map[$row['key']] = [
                'title' => $row['title'],
                'lead' => $row['lead'] ?? null,
                'sort' => ($i + 1) * 10,
            ];
        }
        $this->persistTemplateStages($template, $map);

        return ['ok' => true];
    }

    /**
     * @return array{ok:bool,message?:string}
     */
    public function deleteTemplateStage(SeoChecklistTemplate $template, string $stageKey): array
    {
        if ($template->is_system && !User::isUserAdmin()) {
            return ['ok' => false, 'message' => __('System template is read-only')];
        }

        $map = $this->resolveTemplateStages($template);
        if (!isset($map[$stageKey])) {
            return ['ok' => false, 'message' => __('Invalid stage')];
        }

        $taskCount = $template->tasks()->where('stage_key', $stageKey)->count();
        if ($taskCount > 0) {
            return ['ok' => false, 'message' => __('Stage has tasks')];
        }

        unset($map[$stageKey]);
        $this->persistTemplateStages($template, $map);

        return ['ok' => true];
    }

    /**
     * @return array{ok:bool,message?:string,item?:SeoChecklistItem}
     */
    public function addProjectTask(SeoChecklistProject $project, array $payload): array
    {
        $title = trim((string) ($payload['title'] ?? ''));
        if ($title === '') {
            return ['ok' => false, 'message' => __('Title required')];
        }

        $template = $project->template_id
            ? SeoChecklistTemplate::query()->find($project->template_id)
            : null;
        $stages = $this->resolveTemplateStages($template);
        // В проекте этап мог остаться от старого шаблона — разрешаем уже существующие ключи
        $stageKey = (string) ($payload['stage_key'] ?? '');
        if ($stageKey === '') {
            return ['ok' => false, 'message' => __('Invalid stage')];
        }
        if (!isset($stages[$stageKey])) {
            $existingSort = $project->items()->where('stage_key', $stageKey)->max('stage_sort');
            if ($existingSort === null) {
                return ['ok' => false, 'message' => __('Invalid stage')];
            }
            $stages[$stageKey] = [
                'title' => SeoChecklistDefaultTemplate::stageTitle($stageKey),
                'lead' => null,
                'sort' => (int) $existingSort,
            ];
        }

        $role = (string) ($payload['role'] ?? 'owner');
        if (!in_array($role, ['owner', 'pm', 'shared', 'any'], true)) {
            $role = 'owner';
        }

        $repeat = SeoChecklistDefaultTemplate::normalizeRepeatRule($payload['repeat_rule'] ?? null);
        $dueDays = $this->normalizeDueDays($payload['due_days_from_start'] ?? null);

        $sort = (int) $project->items()->whereNull('parent_id')->where('stage_key', $stageKey)->max('sort') + 10;
        $code = 'custom_' . substr(md5($title . microtime(true) . mt_rand()), 0, 12);

        $item = SeoChecklistItem::query()->create([
            'project_id' => $project->id,
            'parent_id' => null,
            'code' => $code,
            'stage_key' => $stageKey,
            'stage_sort' => (int) ($stages[$stageKey]['sort'] ?? 0),
            'sort' => $sort,
            'title' => $title,
            'help' => trim((string) ($payload['help'] ?? '')) ?: null,
            'role' => $role,
            'is_important' => !empty($payload['is_important']),
            'allows_subtasks' => true,
            'repeat_rule' => $repeat,
            'due_days_from_start' => $dueDays,
            'due_at' => $this->dueAtFromProjectStart(
                $project->created_at,
                $dueDays,
                (bool) ($project->skip_weekends ?? false)
            ),
            'links_json' => [],
            'status' => 'todo',
            'created_by' => Auth::id() ? (int) Auth::id() : null,
        ]);

        $project->recalculateProgress();
        $this->logActivity(
            (int) $project->id,
            (int) $item->id,
            Auth::id() ? (int) Auth::id() : 0,
            'item_created',
            $this->itemActivitySnapshot($item)
        );

        return ['ok' => true, 'item' => $item->fresh()];
    }

    /**
     * @return array{ok:bool,message?:string}
     */
    public function updateProjectItem(SeoChecklistItem $item, array $payload): array
    {
        $title = array_key_exists('title', $payload) ? trim((string) $payload['title']) : null;
        if ($title !== null && $title === '') {
            return ['ok' => false, 'message' => __('Title required')];
        }

        $fill = [];
        if ($title !== null) {
            $fill['title'] = $title;
        }
        if (array_key_exists('help', $payload)) {
            $fill['help'] = trim((string) $payload['help']) ?: null;
        }
        if (array_key_exists('role', $payload)) {
            $role = (string) $payload['role'];
            if (in_array($role, ['owner', 'pm', 'shared', 'any'], true)) {
                $fill['role'] = $role;
            }
        }
        if (array_key_exists('is_important', $payload)) {
            $fill['is_important'] = (bool) $payload['is_important'];
        }
        if (array_key_exists('allows_subtasks', $payload)) {
            $fill['allows_subtasks'] = (bool) $payload['allows_subtasks'];
        }
        if (array_key_exists('repeat_rule', $payload)) {
            $fill['repeat_rule'] = SeoChecklistDefaultTemplate::normalizeRepeatRule($payload['repeat_rule']);
        }

        if ($fill === []) {
            return ['ok' => true];
        }

        $item->forceFill($fill)->save();
        if ($item->project) {
            $item->project->forceFill(['last_activity_at' => now()])->save();
        }

        return ['ok' => true];
    }

    /**
     * @return array{ok:bool,message?:string}
     */
    public function deleteProjectItem(SeoChecklistItem $item): array
    {
        $project = $item->project;
        $childIds = SeoChecklistItem::query()->where('parent_id', $item->id)->pluck('id')->all();
        $allIds = array_merge([(int) $item->id], array_map('intval', $childIds));

        DB::transaction(function () use ($allIds, $item) {
            SeoChecklistItemNote::query()->whereIn('item_id', $allIds)->delete();
            SeoChecklistItem::query()->whereIn('id', $allIds)->where('parent_id', $item->id)->delete();
            $item->delete();
        });

        if ($project) {
            $project->recalculateProgress();
        }

        return ['ok' => true];
    }

    public function restoreProject(SeoChecklistProject $project): void
    {
        $project->forceFill([
            'status' => 'active',
            'last_activity_at' => now(),
        ])->save();
    }

    /**
     * Безвозвратное удаление проекта чеклиста со всеми задачами и заметками.
     *
     * @return array{ok:bool,message?:string}
     */
    public function deleteProject(SeoChecklistProject $project): array
    {
        DB::transaction(function () use ($project) {
            $itemIds = SeoChecklistItem::query()
                ->where('project_id', $project->id)
                ->pluck('id')
                ->all();

            if (!empty($itemIds)) {
                SeoChecklistItemNote::query()->whereIn('item_id', $itemIds)->delete();
                SeoChecklistItem::query()->where('project_id', $project->id)->delete();
            }

            $project->delete();
        });

        return ['ok' => true];
    }

    /**
     * @return array{ok:bool,message?:string}
     */
    public function deleteTemplate(SeoChecklistTemplate $template): array
    {
        if ($template->is_system) {
            return ['ok' => false, 'message' => __('System template is read-only')];
        }

        $inUse = SeoChecklistProject::query()->where('template_id', $template->id)->exists();
        if ($inUse) {
            return ['ok' => false, 'message' => __('Template is used by projects')];
        }

        DB::transaction(function () use ($template) {
            $template->tasks()->delete();
            $template->delete();
        });

        return ['ok' => true];
    }

    /**
     * @return array{ok:bool,message?:string,project?:SeoChecklistProject}
     */
    public function createProject(int $userId, string $rawDomain, ?int $ownerUserId = null, ?int $pmUserId = null, ?int $templateId = null): array
    {
        $domain = HomeUserSites::normalizeDomain($rawDomain);
        if ($domain === '') {
            return ['ok' => false, 'message' => __('Invalid domain')];
        }

        $existing = SeoChecklistProject::query()
            ->where('user_id', $userId)
            ->where('domain', $domain)
            ->first();
        if ($existing) {
            if ($existing->status === 'archived') {
                $this->restoreProject($existing);
            }

            return ['ok' => true, 'project' => $existing->fresh()];
        }

        $template = $this->findUsableTemplate($userId, $templateId);
        if (!$template) {
            return ['ok' => false, 'message' => __('Template not found')];
        }

        try {
            $project = DB::transaction(function () use ($userId, $domain, $ownerUserId, $pmUserId, $template) {
                $skipWeekends = Schema::hasColumn('seo_checklist_projects', 'skip_weekends')
                    ? (bool) ($template->skip_weekends ?? false)
                    : false;

                $projectData = [
                    'user_id' => $userId,
                    'template_id' => $template->id,
                    'domain' => $domain,
                    'title' => 'SEO — ' . $domain,
                    'status' => 'active',
                    'owner_user_id' => $ownerUserId ?: $userId,
                    'pm_user_id' => $pmUserId,
                    'progress_done' => 0,
                    'progress_total' => 0,
                    'last_activity_at' => now(),
                ];
                if (Schema::hasColumn('seo_checklist_projects', 'skip_weekends')) {
                    $projectData['skip_weekends'] = $skipWeekends;
                }
                $project = SeoChecklistProject::query()->create($projectData);

                $tasks = $template->tasks()->whereNull('parent_id')->orderBy('stage_sort')->orderBy('sort')->with('children')->get();
                foreach ($tasks as $task) {
                    $title = $task->title;
                    if ($task->code === 'project_seo_header') {
                        $title = 'Работа с проектом по SEO — ' . $domain;
                    }

                    $parentItem = SeoChecklistItem::query()->create([
                        'project_id' => $project->id,
                        'parent_id' => null,
                        'code' => $task->code,
                        'stage_key' => $task->stage_key,
                        'stage_sort' => $task->stage_sort,
                        'sort' => $task->sort,
                        'title' => $title,
                        'help' => $task->help,
                        'role' => $task->role,
                        'is_important' => $task->is_important,
                        'allows_subtasks' => true,
                        'repeat_rule' => $task->repeat_rule,
                        'due_days_from_start' => $task->due_days_from_start,
                        'due_at' => $this->dueAtFromProjectStart(
                            $project->created_at,
                            $task->due_days_from_start ? (int) $task->due_days_from_start : null,
                            $skipWeekends
                        ),
                        'links_json' => $task->links_json ?: [],
                        'status' => 'todo',
                    ]);

                    foreach ($task->children as $child) {
                        SeoChecklistItem::query()->create([
                            'project_id' => $project->id,
                            'parent_id' => $parentItem->id,
                            'code' => $child->code,
                            'stage_key' => $child->stage_key,
                            'stage_sort' => $child->stage_sort,
                            'sort' => $child->sort,
                            'title' => $child->title,
                            'help' => $child->help,
                            'role' => $child->role ?: $task->role,
                            'is_important' => false,
                            'allows_subtasks' => false,
                            'repeat_rule' => null,
                            'due_days_from_start' => null,
                            'due_at' => null,
                            'links_json' => [],
                            'status' => 'todo',
                        ]);
                    }
                }

                $project->recalculateProgress();

                return $project->fresh();
            });
        } catch (Throwable $e) {
            report($e);

            return ['ok' => false, 'message' => __('Could not create SEO checklist')];
        }

        return ['ok' => true, 'project' => $project];
    }

    public function setItemStatus(SeoChecklistItem $item, string $status, int $userId): bool
    {
        $result = $this->changeItemStatus($item, $status, $userId);

        return !empty($result['ok']);
    }

    /**
     * Смена статуса с проверкой прав (review → done только PM/аудитор/владелец).
     *
     * @return array{ok:bool,message?:string,item?:SeoChecklistItem}
     */
    public function changeItemStatus(SeoChecklistItem $item, string $status, int $userId): array
    {
        if ($status === 'blocked') {
            $status = 'clarify';
        }
        if (!in_array($status, SeoChecklistItem::STATUSES, true) || $status === 'blocked') {
            return ['ok' => false, 'message' => __('Invalid status')];
        }

        $from = $item->status === 'blocked' ? 'clarify' : (string) $item->status;
        if ($from === $status) {
            return ['ok' => true, 'item' => $item];
        }

        $project = $item->project ?: SeoChecklistProject::query()->find($item->project_id);
        if (!$project) {
            return ['ok' => false, 'message' => __('Project not found')];
        }

        if ($status === 'done' || $status === 'skip') {
            if ($item->isSubtask()) {
                // Пункт чеклиста: без «на проверку», но закрыть могут только поставивший / PM / аудитор
                if (!$this->canCloseChecklistItem($item, $project, $userId)) {
                    return ['ok' => false, 'message' => __('Only creator, PM or auditor can close checklist item')];
                }
            } else {
                // Основные задачи — только PM/аудитор и только из «На проверку».
                if ($from !== 'review') {
                    return ['ok' => false, 'message' => __('Send to review first')];
                }
                if (!$this->canApproveReview($project, $userId)) {
                    return ['ok' => false, 'message' => __('Only PM or auditor can approve')];
                }
            }
        }

        if (in_array($status, SeoChecklistItem::CLOSED_STATUSES, true)) {
            $this->stopItemTimer($item, $userId);
        }

        $item->status = $status;
        if (in_array($status, SeoChecklistItem::CLOSED_STATUSES, true)) {
            $item->done_at = now();
            $item->done_by = $userId;
        } else {
            $item->done_at = null;
            $item->done_by = null;
        }
        $item->save();

        $project->recalculateProgress();
        $this->logActivity($project->id, $item->id, $userId, 'status_change', array_merge([
            'from' => $from,
            'to' => $status,
        ], $this->itemActivitySnapshot($item)));

        return ['ok' => true, 'item' => $item];
    }

    /**
     * Закрыть пункт чеклиста (подзадачу): тот кто поставил, PM или аудитор.
     */
    public function canCloseChecklistItem(SeoChecklistItem $item, SeoChecklistProject $project, int $userId): bool
    {
        if ($userId < 1) {
            return false;
        }
        if ((int) $item->created_by === $userId) {
            return true;
        }

        return $this->canApproveReview($project, $userId);
    }

    /**
     * Закрыть задачу с «На проверку» / видеть очередь — только PM или аудитор проекта.
     * Владелец аккаунта сам по себе сюда не попадает (иначе вкладка у всех создателей проектов).
     * Если владелец аккаунта ещё и PM/аудитор в команде проекта — доступ есть.
     */
    public function canApproveReview(SeoChecklistProject $project, int $userId): bool
    {
        if ($userId < 1) {
            return false;
        }
        if ((int) $project->pm_user_id === $userId) {
            return true;
        }
        if ($project->team_id && SeoChecklistTeam::tableReady()) {
            $role = SeoChecklistTeamMember::query()
                ->where('team_id', (int) $project->team_id)
                ->where('user_id', $userId)
                ->value('role');

            return in_array($role, ['pm', 'auditor'], true);
        }

        return false;
    }

    public function canSeeReviewQueue(int $userId): bool
    {
        $projects = $this->accessibleProjectsQuery($userId)
            ->where('status', 'active')
            ->get(['id', 'user_id', 'owner_user_id', 'pm_user_id', 'team_id']);

        foreach ($projects as $project) {
            if ($this->canApproveReview($project, $userId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Очередь «На проверку» для PM/аудиторов.
     *
     * @return \Illuminate\Support\Collection<int, SeoChecklistItem>
     */
    public function reviewQueueForUser(int $userId, int $limit = 100)
    {
        $projects = $this->accessibleProjectsQuery($userId)
            ->where('status', 'active')
            ->get(['id', 'user_id', 'owner_user_id', 'pm_user_id', 'team_id']);

        $allowedIds = [];
        foreach ($projects as $project) {
            if ($this->canApproveReview($project, $userId)) {
                $allowedIds[] = (int) $project->id;
            }
        }
        if ($allowedIds === []) {
            return collect();
        }

        return SeoChecklistItem::query()
            ->whereIn('project_id', $allowedIds)
            ->whereNull('parent_id')
            ->where('status', 'review')
            ->with([
                'project:id,domain,title,user_id,owner_user_id,pm_user_id,team_id,status',
                'notes' => function ($q) {
                    $q->orderByDesc('id')->with('user');
                },
                'children' => function ($q) use ($userId) {
                    $q->orderBy('sort')->orderBy('id')
                        ->with([
                            'createdByUser',
                            'doneByUser',
                            'timeLogs' => function ($tq) use ($userId) {
                                $tq->where('user_id', $userId)->whereNull('ended_at')->orderByDesc('id');
                            },
                        ]);
                },
                'timeLogs' => function ($q) use ($userId) {
                    $q->where('user_id', $userId)->whereNull('ended_at')->orderByDesc('id');
                },
            ])
            ->orderBy('due_at')
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    public function logActivity(
        int $projectId,
        ?int $itemId,
        int $userId,
        string $type,
        array $meta = []
    ): void {
        if (!SeoChecklistActivityLog::tableReady() || $userId < 1) {
            return;
        }
        SeoChecklistActivityLog::query()->create([
            'project_id' => $projectId,
            'item_id' => $itemId,
            'user_id' => $userId,
            'type' => $type,
            'meta_json' => $meta,
        ]);
    }

    /**
     * Снимок задачи для хроники: кто поставил / кто выполнил.
     *
     * @return array<string, mixed>
     */
    public function itemActivitySnapshot(SeoChecklistItem $item): array
    {
        $item->loadMissing(['createdByUser', 'doneByUser', 'parent:id,title']);

        return [
            'item_id' => (int) $item->id,
            'title' => (string) $item->title,
            'parent_id' => $item->parent_id ? (int) $item->parent_id : null,
            'parent_title' => $item->parent ? (string) $item->parent->title : null,
            'is_subtask' => $item->isSubtask(),
            'created_by_name' => $this->userDisplayName($item->createdByUser),
            'created_at' => $item->created_at
                ? $item->created_at->format('d.m.Y') . "\xc2\xa0" . $item->created_at->format('H:i')
                : null,
            'done_by_name' => $this->userDisplayName($item->doneByUser),
            'done_at' => $item->done_at
                ? $item->done_at->format('d.m.Y') . "\xc2\xa0" . $item->done_at->format('H:i')
                : null,
        ];
    }

    private function userDisplayName($user): ?string
    {
        if (!$user) {
            return null;
        }
        $name = trim(($user->name ?? '') . ' ' . ($user->last_name ?? ''));

        return $name !== '' ? $name : (string) ($user->email ?: null);
    }

    /**
     * Хроника: статусы + заметки.
     *
     * @return array{items:\Illuminate\Support\Collection,unread_notes:\Illuminate\Support\Collection,unread_count:int}
     */
    /**
     * Авторы для фильтра хроники: участники команд и PM/владельцы доступных проектов.
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    public function chronicleAuthorOptions(int $userId)
    {
        $projects = $this->accessibleProjectsQuery($userId)
            ->where('status', 'active')
            ->get(['id', 'user_id', 'owner_user_id', 'pm_user_id', 'team_id']);

        $ids = collect();
        foreach ($projects as $project) {
            $ids->push((int) $project->user_id);
            if ($project->owner_user_id) {
                $ids->push((int) $project->owner_user_id);
            }
            if ($project->pm_user_id) {
                $ids->push((int) $project->pm_user_id);
            }
        }
        $teamIds = $projects->pluck('team_id')->filter()->unique()->values();
        if ($teamIds->isNotEmpty() && SeoChecklistTeam::tableReady()) {
            $ids = $ids->merge(
                SeoChecklistTeamMember::query()
                    ->whereIn('team_id', $teamIds->all())
                    ->pluck('user_id')
            );
        }
        $ids = $ids->filter(static function ($id) {
            return (int) $id > 0;
        })->unique()->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return User::query()
            ->whereIn('id', $ids->all())
            ->orderBy('name')
            ->orderBy('email')
            ->get(['id', 'name', 'email']);
    }

    /**
     * Сколько непрочитанных событий в хронике по настройкам пользователя (бейдж).
     */
    public function unreadNotesCountForUser(int $userId): int
    {
        return $this->unreadChronicleCountForUser($userId);
    }

    /**
     * @param  array<int, int>|int|null  $projectFilter
     * @param  array<int, int>|int|null  $authorFilter
     */
    public function unreadChronicleCountForUser(
        int $userId,
        $projectFilter = null,
        $authorFilter = null
    ): int {
        $bundle = $this->collectUnreadChronicle(
            $userId,
            $projectFilter,
            $this->normalizeIdList($authorFilter),
            1,
            'desc',
            true
        );

        return (int) ($bundle['unread_count'] ?? 0);
    }

    /**
     * @param  array<int, int>|int|null  $projectFilter  один id, список id или null
     * @param  array<int, int>|int|null  $authorFilter
     */
    /**
     * Хроника.
     *
     * @param  bool|string  $presetOrUnread  true/false (legacy unreadOnly) или preset: unread|notes|all
     * @return array{items:\Illuminate\Support\Collection,unread_notes:\Illuminate\Support\Collection,unread_events:\Illuminate\Support\Collection,unread_count:int,preset:string,unread_prefs:array}
     */
    public function chronicleForUser(
        int $userId,
        $projectFilter = null,
        $authorFilter = null,
        $presetOrUnread = 'all',
        int $limit = 80,
        string $sort = 'desc'
    ): array {
        if (is_bool($presetOrUnread)) {
            $preset = $presetOrUnread ? 'unread' : 'all';
        } else {
            $preset = (string) $presetOrUnread;
        }
        if (!in_array($preset, ['unread', 'notes', 'all'], true)) {
            $preset = 'all';
        }
        $sort = strtolower($sort) === 'asc' ? 'asc' : 'desc';
        $prefs = SeoChecklistUserPreference::chronicleUnreadPrefsFor($userId);

        $projectIds = $this->accessibleProjectsQuery($userId)
            ->where('status', 'active')
            ->pluck('id');
        $filterProjects = $this->normalizeIdList($projectFilter);
        if ($filterProjects !== []) {
            $projectIds = $projectIds->intersect($filterProjects)->values();
        }

        $empty = [
            'items' => collect(),
            'unread_notes' => collect(),
            'unread_events' => collect(),
            'unread_count' => 0,
            'preset' => $preset,
            'sort' => $sort,
            'unread_prefs' => $prefs,
        ];
        if ($projectIds->isEmpty()) {
            return $empty;
        }

        $authorIds = $this->normalizeIdList($authorFilter);
        $unread = $this->collectUnreadChronicle($userId, $projectIds->all(), $authorIds, 60, $sort, false, $prefs);

        if ($preset === 'unread') {
            return [
                'items' => collect(),
                'unread_notes' => $unread['unread_notes'],
                'unread_events' => $unread['unread_events'],
                'unread_count' => $unread['unread_count'],
                'preset' => $preset,
                'sort' => $sort,
                'unread_prefs' => $prefs,
            ];
        }

        $items = collect();
        if (SeoChecklistActivityLog::tableReady()) {
            $logQuery = SeoChecklistActivityLog::query()
                ->whereIn('project_id', $projectIds->all())
                ->with([
                    'user',
                    'project:id,domain,title',
                    'item:id,title,project_id,parent_id,status,created_by,done_by,created_at,done_at',
                    'item.createdByUser',
                    'item.doneByUser',
                    'item.parent:id,title',
                ])
                ->orderByDesc('id');
            if ($authorIds !== []) {
                $logQuery->whereIn('user_id', $authorIds);
            }
            // «Заметки» — без мусора по сменам статусов
            if ($preset === 'notes') {
                $logQuery->where('type', 'note');
            }
            // Окно — последние N, при asc показываем их от старых к новым
            $items = $logQuery->limit($limit)->get();
            if ($sort === 'asc') {
                $items = $items->sortBy('id')->values();
            }
        }

        return [
            'items' => $items,
            'unread_notes' => $unread['unread_notes'],
            'unread_events' => $unread['unread_events'],
            'unread_count' => $unread['unread_count'],
            'preset' => $preset,
            'sort' => $sort,
            'unread_prefs' => $prefs,
        ];
    }

    /**
     * Собрать непрочитанные события по настройкам пользователя.
     *
     * @param  array<int, int>|int|null  $projectFilter
     * @param  array<int, int>  $authorIds
     * @param  array{notes?:bool,review?:bool,created?:bool}|null  $prefs
     * @return array{unread_notes:\Illuminate\Support\Collection,unread_events:\Illuminate\Support\Collection,unread_count:int}
     */
    private function collectUnreadChronicle(
        int $userId,
        $projectFilter = null,
        array $authorIds = [],
        int $limit = 60,
        string $sort = 'desc',
        bool $countOnly = false,
        ?array $prefs = null
    ): array {
        $empty = [
            'unread_notes' => collect(),
            'unread_events' => collect(),
            'unread_count' => 0,
        ];
        if ($userId < 1) {
            return $empty;
        }

        $prefs = $prefs ?: SeoChecklistUserPreference::chronicleUnreadPrefsFor($userId);
        $wantNotes = !empty($prefs['notes']);
        $wantReview = !empty($prefs['review']);
        $wantCreated = !empty($prefs['created']);
        if (!$wantNotes && !$wantReview && !$wantCreated) {
            return $empty;
        }

        if (is_array($projectFilter)) {
            $projectIds = collect(array_values(array_filter(array_map('intval', $projectFilter))));
            // Список id с вызывающего (уже суженный) — пересечём с доступными на всякий случай.
            $accessible = $this->accessibleProjectsQuery($userId)
                ->where('status', 'active')
                ->pluck('id');
            $projectIds = $projectIds->intersect($accessible)->values();
        } else {
            $projectIds = $this->accessibleProjectsQuery($userId)
                ->where('status', 'active')
                ->pluck('id');
            $filterProjects = $this->normalizeIdList($projectFilter);
            if ($filterProjects !== []) {
                $projectIds = $projectIds->intersect($filterProjects)->values();
            }
        }
        if ($projectIds->isEmpty()) {
            return $empty;
        }

        $notes = collect();
        $noteCount = 0;
        if ($wantNotes && SeoChecklistNoteRead::tableReady()) {
            $noteQuery = SeoChecklistItemNote::query()
                ->where('user_id', '!=', $userId)
                ->whereHas('item', function ($q) use ($projectIds) {
                    $q->whereIn('project_id', $projectIds->all())->whereNull('parent_id');
                })
                ->whereDoesntHave('reads', function ($q) use ($userId) {
                    $q->where('user_id', $userId);
                });
            if ($authorIds !== []) {
                $noteQuery->whereIn('user_id', $authorIds);
            }
            $noteCount = (clone $noteQuery)->count();
            if (!$countOnly) {
                $noteQuery->with(['user', 'item.project:id,domain,title']);
                if ($sort === 'asc') {
                    $noteQuery->orderBy('id');
                } else {
                    $noteQuery->orderByDesc('id');
                }
                $notes = $noteQuery->limit($limit)->get();
            }
        }

        $logs = collect();
        $activityCount = 0;
        if (($wantReview || $wantCreated) && SeoChecklistActivityLog::tableReady() && SeoChecklistActivityRead::tableReady()) {
            $logQuery = SeoChecklistActivityLog::query()
                ->whereIn('project_id', $projectIds->all())
                ->where('user_id', '!=', $userId)
                ->whereDoesntHave('reads', function ($q) use ($userId) {
                    $q->where('user_id', $userId);
                })
                ->where(function ($q) use ($wantReview, $wantCreated) {
                    if ($wantReview) {
                        $q->orWhere(function ($qq) {
                            $qq->where('type', 'status_change')
                                ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(meta_json, '$.to')) = ?", ['review']);
                        });
                    }
                    if ($wantCreated) {
                        $q->orWhere('type', 'item_created');
                    }
                });
            if ($authorIds !== []) {
                $logQuery->whereIn('user_id', $authorIds);
            }
            $activityCount = (clone $logQuery)->count();
            if (!$countOnly) {
                $logQuery->with([
                    'user',
                    'project:id,domain,title',
                    'item:id,title,project_id,parent_id,status,created_by,done_by,created_at,done_at',
                    'item.createdByUser',
                    'item.doneByUser',
                    'item.parent:id,title',
                ]);
                if ($sort === 'asc') {
                    $logQuery->orderBy('id');
                } else {
                    $logQuery->orderByDesc('id');
                }
                $logs = $logQuery->limit($limit)->get();
            }
        }

        $unreadCount = $noteCount + $activityCount;
        if ($countOnly) {
            return [
                'unread_notes' => collect(),
                'unread_events' => collect(),
                'unread_count' => $unreadCount,
            ];
        }

        $events = collect();
        foreach ($notes as $note) {
            $events->push((object) [
                'source' => 'note',
                'sort_key' => (int) $note->id,
                'created_at' => $note->created_at,
                'note' => $note,
                'log' => null,
            ]);
        }
        foreach ($logs as $log) {
            $source = ($log->type === 'item_created') ? 'created' : 'review';
            $events->push((object) [
                'source' => $source,
                'sort_key' => (int) $log->id,
                'created_at' => $log->created_at,
                'note' => null,
                'log' => $log,
            ]);
        }
        if ($sort === 'asc') {
            $events = $events->sortBy(function ($e) {
                return ($e->created_at ? $e->created_at->timestamp : 0) . '-' . $e->sort_key;
            })->values();
        } else {
            $events = $events->sortByDesc(function ($e) {
                return ($e->created_at ? $e->created_at->timestamp : 0) . '-' . $e->sort_key;
            })->values();
        }
        if ($events->count() > $limit) {
            $events = $events->take($limit)->values();
        }

        return [
            'unread_notes' => $notes,
            'unread_events' => $events,
            'unread_count' => $unreadCount,
        ];
    }

    /**
     * @param  array<int, int>|int|string|null  $value
     * @return array<int, int>
     */
    private function normalizeIdList($value): array
    {
        if ($value === null || $value === '' || $value === []) {
            return [];
        }
        if (!is_array($value)) {
            $value = [$value];
        }
        $ids = [];
        foreach ($value as $item) {
            $id = (int) $item;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }

    public function markNotesRead(int $userId, array $noteIds): int
    {
        if (!SeoChecklistNoteRead::tableReady() || $userId < 1) {
            return 0;
        }
        $noteIds = array_values(array_unique(array_filter(array_map('intval', $noteIds))));
        if ($noteIds === []) {
            return 0;
        }
        $n = 0;
        foreach ($noteIds as $noteId) {
            SeoChecklistNoteRead::query()->updateOrCreate(
                ['user_id' => $userId, 'note_id' => $noteId],
                ['read_at' => now()]
            );
            $n++;
        }

        return $n;
    }

    public function markActivitiesRead(int $userId, array $activityIds): int
    {
        if (!SeoChecklistActivityRead::tableReady() || $userId < 1) {
            return 0;
        }
        $activityIds = array_values(array_unique(array_filter(array_map('intval', $activityIds))));
        if ($activityIds === []) {
            return 0;
        }
        $n = 0;
        foreach ($activityIds as $activityId) {
            SeoChecklistActivityRead::query()->updateOrCreate(
                ['user_id' => $userId, 'activity_id' => $activityId],
                ['read_at' => now()]
            );
            $n++;
        }

        return $n;
    }

    public function markAllUnreadNotesRead(int $userId): int
    {
        $data = $this->collectUnreadChronicle($userId, null, [], 500, 'desc', false);
        $marked = 0;
        $noteIds = $data['unread_notes']->pluck('id')->all();
        $marked += $this->markNotesRead($userId, $noteIds);
        $activityIds = [];
        foreach ($data['unread_events'] as $event) {
            if (!empty($event->log) && (int) ($event->log->id ?? 0) > 0) {
                $activityIds[] = (int) $event->log->id;
            }
        }
        $marked += $this->markActivitiesRead($userId, $activityIds);

        return $marked;
    }

    /**
     * Снять отметку «прочитано» — заметка снова в непрочитанных.
     */
    public function markNotesUnread(int $userId, array $noteIds): int
    {
        if (!SeoChecklistNoteRead::tableReady() || $userId < 1) {
            return 0;
        }
        $noteIds = array_values(array_unique(array_filter(array_map('intval', $noteIds))));
        if ($noteIds === []) {
            return 0;
        }

        return (int) SeoChecklistNoteRead::query()
            ->where('user_id', $userId)
            ->whereIn('note_id', $noteIds)
            ->delete();
    }

    public function markActivitiesUnread(int $userId, array $activityIds): int
    {
        if (!SeoChecklistActivityRead::tableReady() || $userId < 1) {
            return 0;
        }
        $activityIds = array_values(array_unique(array_filter(array_map('intval', $activityIds))));
        if ($activityIds === []) {
            return 0;
        }

        return (int) SeoChecklistActivityRead::query()
            ->where('user_id', $userId)
            ->whereIn('activity_id', $activityIds)
            ->delete();
    }

    /**
     * Какие из note_id текущий пользователь уже отметил прочитанными.
     *
     * @param  array<int, int>  $noteIds
     * @return array<int, true>  note_id => true
     */
    public function readNoteIdMap(int $userId, array $noteIds): array
    {
        if (!SeoChecklistNoteRead::tableReady() || $userId < 1 || $noteIds === []) {
            return [];
        }
        $noteIds = array_values(array_unique(array_filter(array_map('intval', $noteIds))));
        if ($noteIds === []) {
            return [];
        }

        $map = [];
        foreach (
            SeoChecklistNoteRead::query()
                ->where('user_id', $userId)
                ->whereIn('note_id', $noteIds)
                ->pluck('note_id') as $noteId
        ) {
            $map[(int) $noteId] = true;
        }

        return $map;
    }

    /**
     * Активный таймер пользователя (одна сессия на весь SEO-чеклист).
     *
     * @return array{log:SeoChecklistItemTimeLog,item:SeoChecklistItem,project:SeoChecklistProject}|null
     */
    public function activeTimerForUser(int $userId): ?array
    {
        if ($userId < 1 || !Schema::hasTable('seo_checklist_item_time_logs')) {
            return null;
        }

        /** @var SeoChecklistItemTimeLog|null $log */
        $log = SeoChecklistItemTimeLog::query()
            ->where('user_id', $userId)
            ->whereNull('ended_at')
            ->orderByDesc('id')
            ->first();
        if (!$log) {
            return null;
        }

        $item = $log->item;
        if (!$item) {
            return null;
        }

        $project = $item->project;
        if (!$project || $project->status === 'archived') {
            return null;
        }

        return [
            'log' => $log,
            'item' => $item,
            'project' => $project,
        ];
    }

    /**
     * @return array{ok:bool,message?:string,item?:SeoChecklistItem,log?:SeoChecklistItemTimeLog,stopped_item_id?:int}
     */
    public function startItemTimer(SeoChecklistItem $item, int $userId): array
    {
        if ($userId < 1) {
            return ['ok' => false, 'message' => __('Unauthorized')];
        }
        if (!Schema::hasTable('seo_checklist_item_time_logs')) {
            return ['ok' => false, 'message' => __('Time tracking unavailable')];
        }

        $project = $item->project;
        if (!$project || $project->status === 'archived') {
            return ['ok' => false, 'message' => __('Project not found')];
        }

        $stoppedItemId = null;
        $active = $this->activeTimerForUser($userId);
        if ($active) {
            if ((int) $active['item']->id === (int) $item->id) {
                return [
                    'ok' => true,
                    'item' => $item->fresh(),
                    'log' => $active['log'],
                ];
            }
            $this->closeTimeLog($active['log']);
            $stoppedItemId = (int) $active['item']->id;
        }

        $startedAt = now();
        $log = SeoChecklistItemTimeLog::query()->create([
            'item_id' => $item->id,
            'user_id' => $userId,
            'work_date' => $startedAt->toDateString(),
            'started_at' => $startedAt,
            'ended_at' => null,
            'duration_seconds' => null,
        ]);

        // Старт таймера = работа началась: «Новая» → «В работе»
        if ($item->status === 'todo' || $item->status === 'blocked') {
            $from = $item->status === 'blocked' ? 'clarify' : 'todo';
            $item->status = 'doing';
            $item->save();
            if ($project) {
                $project->recalculateProgress();
            }
            $this->logActivity((int) $project->id, (int) $item->id, $userId, 'status_change', array_merge([
                'from' => $from,
                'to' => 'doing',
            ], $this->itemActivitySnapshot($item)));
        }

        $project->forceFill(['last_activity_at' => now()])->save();

        return [
            'ok' => true,
            'item' => $item->fresh(),
            'log' => $log,
            'stopped_item_id' => $stoppedItemId,
        ];
    }

    /**
     * @return array{ok:bool,message?:string,item?:SeoChecklistItem}
     */
    public function stopItemTimer(SeoChecklistItem $item, int $userId): array
    {
        if ($userId < 1) {
            return ['ok' => false, 'message' => __('Unauthorized')];
        }
        if (!Schema::hasTable('seo_checklist_item_time_logs')) {
            return ['ok' => false, 'message' => __('Time tracking unavailable')];
        }

        $log = SeoChecklistItemTimeLog::query()
            ->where('item_id', $item->id)
            ->where('user_id', $userId)
            ->whereNull('ended_at')
            ->orderByDesc('id')
            ->first();

        if ($log) {
            $this->closeTimeLog($log);
        }

        if ($item->project) {
            $item->project->forceFill(['last_activity_at' => now()])->save();
        }

        return ['ok' => true, 'item' => $item->fresh()];
    }

    /**
     * @return array{ok:bool,message?:string,item?:SeoChecklistItem|null}
     */
    public function stopActiveTimerForUser(int $userId): array
    {
        $active = $this->activeTimerForUser($userId);
        if (!$active) {
            return ['ok' => true, 'item' => null];
        }

        return $this->stopItemTimer($active['item'], $userId);
    }

    public function closeTimeLog(SeoChecklistItemTimeLog $log): void
    {
        if ($log->ended_at) {
            return;
        }

        $start = $log->started_at ? $log->started_at->copy() : now();
        $end = now();
        if ($end->lte($start)) {
            $end = $start->copy()->addSecond();
        }

        $segments = $this->splitTimeRangeByDay($start, $end);
        if ($segments === []) {
            $log->forceFill([
                'work_date' => $start->toDateString(),
                'ended_at' => $end,
                'duration_seconds' => 0,
            ])->save();

            return;
        }

        $total = 0;
        $first = array_shift($segments);
        $total += (int) $first['duration_seconds'];
        $log->forceFill([
            'work_date' => $first['work_date'],
            'started_at' => $first['started_at'],
            'ended_at' => $first['ended_at'],
            'duration_seconds' => $first['duration_seconds'],
        ])->save();

        foreach ($segments as $segment) {
            $total += (int) $segment['duration_seconds'];
            SeoChecklistItemTimeLog::query()->create([
                'item_id' => $log->item_id,
                'user_id' => $log->user_id,
                'work_date' => $segment['work_date'],
                'started_at' => $segment['started_at'],
                'ended_at' => $segment['ended_at'],
                'duration_seconds' => $segment['duration_seconds'],
            ]);
        }

        $item = $log->item;
        if ($item && $total > 0) {
            $item->forceFill([
                'time_spent_seconds' => max(0, (int) $item->time_spent_seconds) + $total,
            ])->save();
        }
    }

    /**
     * Разбить интервал на куски по календарным дням (локальное время приложения).
     *
     * @return array<int, array{work_date:string,started_at:\Carbon\Carbon,ended_at:\Carbon\Carbon,duration_seconds:int}>
     */
    public function splitTimeRangeByDay($start, $end): array
    {
        $start = \Carbon\Carbon::parse($start);
        $end = \Carbon\Carbon::parse($end);
        if ($end->lte($start)) {
            return [];
        }

        $segments = [];
        $cursor = $start->copy();
        while ($cursor->lt($end)) {
            $nextMidnight = $cursor->copy()->startOfDay()->addDay();
            $segmentEnd = $nextMidnight->lt($end) ? $nextMidnight->copy() : $end->copy();
            $seconds = max(0, (int) $cursor->diffInSeconds($segmentEnd));
            if ($seconds > 0) {
                $segments[] = [
                    'work_date' => $cursor->toDateString(),
                    'started_at' => $cursor->copy(),
                    'ended_at' => $segmentEnd->copy(),
                    'duration_seconds' => $seconds,
                ];
            }
            $cursor = $segmentEnd->copy();
        }

        return $segments;
    }

    /**
     * Время по дням для задачи (закрытые сессии + текущий таймер).
     *
     * @return array{total:int,days:array<int, array{date:string,label:string,seconds:int}>}
     */
    public function itemTimeByDay(SeoChecklistItem $item, ?int $forUserId = null): array
    {
        $byDate = [];
        if (Schema::hasTable('seo_checklist_item_time_logs')) {
            $q = SeoChecklistItemTimeLog::query()
                ->where('item_id', $item->id)
                ->whereNotNull('ended_at');
            if ($forUserId) {
                $q->where('user_id', $forUserId);
            }
            $rows = $q->get(['work_date', 'started_at', 'duration_seconds']);
            foreach ($rows as $row) {
                $date = $row->work_date
                    ? $row->work_date->toDateString()
                    : ($row->started_at ? $row->started_at->toDateString() : null);
                if (!$date) {
                    continue;
                }
                if (!isset($byDate[$date])) {
                    $byDate[$date] = 0;
                }
                $byDate[$date] += max(0, (int) $row->duration_seconds);
            }

            $runningQ = SeoChecklistItemTimeLog::query()
                ->where('item_id', $item->id)
                ->whereNull('ended_at');
            if ($forUserId) {
                $runningQ->where('user_id', $forUserId);
            }
            foreach ($runningQ->get() as $running) {
                $start = $running->started_at ?: now();
                foreach ($this->splitTimeRangeByDay($start, now()) as $segment) {
                    $date = $segment['work_date'];
                    if (!isset($byDate[$date])) {
                        $byDate[$date] = 0;
                    }
                    $byDate[$date] += (int) $segment['duration_seconds'];
                }
            }
        }

        krsort($byDate);
        $days = [];
        $total = 0;
        foreach ($byDate as $date => $seconds) {
            $total += $seconds;
            $days[] = [
                'date' => $date,
                'label' => \Carbon\Carbon::parse($date)->format('d.m.Y'),
                'seconds' => $seconds,
                'formatted' => self::formatDuration($seconds),
            ];
        }

        return ['total' => $total, 'days' => $days];
    }

    /**
     * Учёт времени пользователя по дням (доступные проекты).
     *
     * @return array{
     *   days: array<int, array{date:string,label:string,seconds:int,formatted:string,entries:array}>,
     *   total:int
     * }
     */
    /**
     * @param  array<int, int>|int|null  $projectFilter
     */
    /**
     * Может ли смотреть чужой учёт (PM/аудитор/менеджер хотя бы одного проекта).
     */
    public function canViewTeamTimesheet(int $userId): bool
    {
        if ($userId < 1) {
            return false;
        }
        $projects = $this->accessibleProjectsQuery($userId)
            ->where('status', 'active')
            ->get(['id', 'user_id', 'owner_user_id', 'pm_user_id', 'team_id']);
        foreach ($projects as $project) {
            if ($this->canManageProject($project, $userId) || $this->canApproveReview($project, $userId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Авторы с логами на проектах, где viewer — менеджер/PM/аудитор.
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    public function timesheetAuthorOptions(int $viewerId)
    {
        if (!$this->canViewTeamTimesheet($viewerId) || !Schema::hasTable('seo_checklist_item_time_logs')) {
            return collect();
        }

        $managedIds = [];
        $projects = $this->accessibleProjectsQuery($viewerId)
            ->where('status', 'active')
            ->get(['id', 'user_id', 'owner_user_id', 'pm_user_id', 'team_id']);
        foreach ($projects as $project) {
            if ($this->canManageProject($project, $viewerId) || $this->canApproveReview($project, $viewerId)) {
                $managedIds[] = (int) $project->id;
            }
        }
        if ($managedIds === []) {
            return collect();
        }

        $userIds = SeoChecklistItemTimeLog::query()
            ->whereHas('item', function ($q) use ($managedIds) {
                $q->whereIn('project_id', $managedIds)->whereNull('parent_id');
            })
            ->distinct()
            ->pluck('user_id')
            ->filter()
            ->unique()
            ->values();
        if ($userIds->isEmpty()) {
            return collect();
        }

        return User::query()
            ->whereIn('id', $userIds->all())
            ->orderBy('name')
            ->orderBy('email')
            ->get(['id', 'name', 'email', 'last_name']);
    }

    /**
     * Учёт времени: дни/задачи, сводка, график, % и активный таймер.
     *
     * @param  array<int, int>|int|string|null  $projectFilter
     * @param  array<int, int>|int|string|null  $userFilter
     * @return array<string, mixed>
     */
    public function timesheetForUser(
        int $viewerId,
        ?string $from = null,
        ?string $to = null,
        $projectFilter = null,
        string $groupBy = 'day',
        $userFilter = null
    ): array {
        $groupBy = $groupBy === 'task' ? 'task' : 'day';
        $empty = [
            'group_by' => $groupBy,
            'days' => [],
            'tasks' => [],
            'total' => 0,
            'summary' => [
                'total' => 0,
                'formatted_total' => self::formatDuration(0),
                'days_count' => 0,
                'avg_per_day' => 0,
                'formatted_avg' => self::formatDuration(0),
                'top_tasks' => [],
            ],
            'chart' => [],
            'active_item_id' => null,
            'show_user' => false,
        ];
        if ($viewerId < 1 || !Schema::hasTable('seo_checklist_item_time_logs')) {
            return $empty;
        }

        $projectIds = $this->accessibleProjectsQuery($viewerId)
            ->where('status', 'active')
            ->pluck('id');
        $filterProjects = $this->normalizeIdList($projectFilter);
        if ($filterProjects !== []) {
            $projectIds = $projectIds->intersect($filterProjects)->values();
        }
        if ($projectIds->isEmpty()) {
            return $empty;
        }

        $canTeam = $this->canViewTeamTimesheet($viewerId);
        $filterUsers = $this->normalizeIdList($userFilter);
        if (!$canTeam) {
            $filterUsers = [$viewerId];
        } elseif ($filterUsers === []) {
            $filterUsers = [$viewerId];
        } else {
            $allowedAuthors = $this->timesheetAuthorOptions($viewerId)->pluck('id')->map(static function ($id) {
                return (int) $id;
            })->all();
            $allowedAuthors[] = $viewerId;
            $filterUsers = array_values(array_intersect($filterUsers, array_values(array_unique($allowedAuthors))));
            if ($filterUsers === []) {
                $filterUsers = [$viewerId];
            }
        }
        $showUser = count($filterUsers) > 1 || ($canTeam && !(count($filterUsers) === 1 && (int) $filterUsers[0] === $viewerId));

        $fromDate = $from ? \Carbon\Carbon::parse($from)->startOfDay() : now()->subDays(30)->startOfDay();
        $toDate = $to ? \Carbon\Carbon::parse($to)->endOfDay() : now()->endOfDay();

        $logs = SeoChecklistItemTimeLog::query()
            ->whereIn('user_id', $filterUsers)
            ->whereNotNull('ended_at')
            ->whereBetween('started_at', [$fromDate, $toDate])
            ->whereHas('item', function ($q) use ($projectIds) {
                $q->whereIn('project_id', $projectIds->all());
            })
            ->with([
                'user:id,name,email,last_name',
                'item:id,title,project_id,parent_id',
                'item.project:id,domain,title',
            ])
            ->orderByDesc('started_at')
            ->limit(4000)
            ->get();

        /** @var array<string, array<string, mixed>> $cells */
        $cells = [];
        $addCell = static function (
            array &$cells,
            int $logUserId,
            string $userLabel,
            ?int $itemId,
            string $title,
            string $domain,
            ?int $projectId,
            string $date,
            int $seconds,
            bool $isActive = false,
            ?int $anchorItemId = null
        ): void {
            if ($seconds < 1 || $date === '') {
                return;
            }
            $key = $logUserId . '|' . ($itemId ?: 0) . '|' . $date;
            if (!isset($cells[$key])) {
                $cells[$key] = [
                    'user_id' => $logUserId,
                    'user_label' => $userLabel,
                    'item_id' => $itemId,
                    'anchor_item_id' => $anchorItemId ?: $itemId,
                    'title' => $title,
                    'domain' => $domain,
                    'project_id' => $projectId,
                    'date' => $date,
                    'seconds' => 0,
                    'is_active' => false,
                ];
            }
            $cells[$key]['seconds'] += $seconds;
            if ($isActive) {
                $cells[$key]['is_active'] = true;
            }
        };

        $userLabelOf = static function ($user, int $fallbackId): string {
            if (!$user) {
                return '#' . $fallbackId;
            }
            $name = trim((string) (($user->name ?? '') . ' ' . ($user->last_name ?? '')));

            return $name !== '' ? $name : (string) ($user->email ?: ('#' . $fallbackId));
        };

        foreach ($logs as $log) {
            $date = $log->work_date
                ? $log->work_date->toDateString()
                : ($log->started_at ? $log->started_at->toDateString() : null);
            if (!$date) {
                continue;
            }
            $item = $log->item;
            $project = $item ? $item->project : null;
            $uid = (int) $log->user_id;
            $itemId = $item ? (int) $item->id : null;
            $anchorId = $item && $item->parent_id ? (int) $item->parent_id : $itemId;
            $addCell(
                $cells,
                $uid,
                $userLabelOf($log->user, $uid),
                $itemId,
                $item ? (string) $item->title : '—',
                $project ? (string) $project->domain : '—',
                $project ? (int) $project->id : null,
                $date,
                max(0, (int) $log->duration_seconds),
                false,
                $anchorId
            );
        }

        $activeItemId = null;
        foreach ($filterUsers as $uid) {
            $active = $this->activeTimerForUser((int) $uid);
            if (!$active || !$active['item'] || !$projectIds->contains((int) $active['item']->project_id)) {
                continue;
            }
            if ((int) $uid === $viewerId) {
                $activeItemId = (int) $active['item']->id;
            }
            $start = $active['log']->started_at ?: now();
            $user = $active['log']->relationLoaded('user') ? $active['log']->user : User::query()->find($uid);
            foreach ($this->splitTimeRangeByDay($start, now()) as $segment) {
                $date = $segment['work_date'];
                $dateCarbon = \Carbon\Carbon::parse($date);
                if ($dateCarbon->lt($fromDate->copy()->startOfDay()) || $dateCarbon->gt($toDate->copy()->startOfDay())) {
                    continue;
                }
                if ($filterProjects !== [] && !in_array((int) $active['item']->project_id, $filterProjects, true)) {
                    continue;
                }
                $item = $active['item'];
                $project = $active['project'];
                $anchorId = $item->parent_id ? (int) $item->parent_id : (int) $item->id;
                $addCell(
                    $cells,
                    (int) $uid,
                    $userLabelOf($user, (int) $uid),
                    (int) $item->id,
                    (string) $item->title,
                    $project ? (string) $project->domain : '—',
                    $project ? (int) $project->id : null,
                    $date,
                    (int) $segment['duration_seconds'],
                    true,
                    $anchorId
                );
            }
        }

        $total = 0;
        foreach ($cells as $cell) {
            $total += (int) $cell['seconds'];
        }
        $pctOf = static function (int $seconds) use ($total): float {
            return $total > 0 ? round(100 * $seconds / $total, 1) : 0.0;
        };

        $byDayMap = [];
        $byTaskMap = [];
        foreach ($cells as $cell) {
            $date = $cell['date'];
            if (!isset($byDayMap[$date])) {
                $byDayMap[$date] = ['seconds' => 0, 'entries' => []];
            }
            $byDayMap[$date]['seconds'] += (int) $cell['seconds'];
            $dayEntryKey = $cell['user_id'] . ':' . ($cell['item_id'] ?: 0);
            if (!isset($byDayMap[$date]['entries'][$dayEntryKey])) {
                $byDayMap[$date]['entries'][$dayEntryKey] = [
                    'user_id' => $cell['user_id'],
                    'user_label' => $cell['user_label'],
                    'item_id' => $cell['item_id'],
                    'anchor_item_id' => $cell['anchor_item_id'] ?? $cell['item_id'],
                    'title' => $cell['title'],
                    'domain' => $cell['domain'],
                    'project_id' => $cell['project_id'],
                    'seconds' => 0,
                    'is_active' => false,
                ];
            }
            $byDayMap[$date]['entries'][$dayEntryKey]['seconds'] += (int) $cell['seconds'];
            if (!empty($cell['is_active'])) {
                $byDayMap[$date]['entries'][$dayEntryKey]['is_active'] = true;
            }

            $taskKey = ($cell['item_id'] ?: ('t:' . md5($cell['title'] . '|' . $cell['domain'])))
                . '|u:' . $cell['user_id'];
            if (!isset($byTaskMap[$taskKey])) {
                $byTaskMap[$taskKey] = [
                    'user_id' => $cell['user_id'],
                    'user_label' => $cell['user_label'],
                    'item_id' => $cell['item_id'],
                    'anchor_item_id' => $cell['anchor_item_id'] ?? $cell['item_id'],
                    'title' => $cell['title'],
                    'domain' => $cell['domain'],
                    'project_id' => $cell['project_id'],
                    'seconds' => 0,
                    'is_active' => false,
                    'entries' => [],
                ];
            }
            $byTaskMap[$taskKey]['seconds'] += (int) $cell['seconds'];
            if (!empty($cell['is_active'])) {
                $byTaskMap[$taskKey]['is_active'] = true;
            }
            if (!isset($byTaskMap[$taskKey]['entries'][$date])) {
                $byTaskMap[$taskKey]['entries'][$date] = [
                    'date' => $date,
                    'label' => \Carbon\Carbon::parse($date)->format('d.m.Y'),
                    'seconds' => 0,
                    'is_active' => false,
                ];
            }
            $byTaskMap[$taskKey]['entries'][$date]['seconds'] += (int) $cell['seconds'];
            if (!empty($cell['is_active'])) {
                $byTaskMap[$taskKey]['entries'][$date]['is_active'] = true;
            }
        }

        ksort($byDayMap);
        $chart = [];
        $chartMax = 1;
        foreach ($byDayMap as $date => $bucket) {
            $sec = (int) $bucket['seconds'];
            if ($sec > $chartMax) {
                $chartMax = $sec;
            }
            $chart[] = [
                'date' => $date,
                'label' => \Carbon\Carbon::parse($date)->format('d.m'),
                'seconds' => $sec,
                'formatted' => self::formatDuration($sec),
                'pct_bar' => 0,
            ];
        }
        foreach ($chart as &$point) {
            $point['pct_bar'] = (int) round(100 * $point['seconds'] / $chartMax);
        }
        unset($point);

        krsort($byDayMap);
        $days = [];
        foreach ($byDayMap as $date => $bucket) {
            $entries = array_values($bucket['entries']);
            usort($entries, static function ($a, $b) {
                return ($b['seconds'] <=> $a['seconds']);
            });
            foreach ($entries as &$entry) {
                $entry['formatted'] = self::formatDuration((int) $entry['seconds']);
                $entry['pct'] = $pctOf((int) $entry['seconds']);
            }
            unset($entry);
            $daySec = (int) $bucket['seconds'];
            $days[] = [
                'date' => $date,
                'label' => \Carbon\Carbon::parse($date)->format('d.m.Y'),
                'seconds' => $daySec,
                'formatted' => self::formatDuration($daySec),
                'pct' => $pctOf($daySec),
                'entries' => $entries,
            ];
        }

        uasort($byTaskMap, static function ($a, $b) {
            return ($b['seconds'] <=> $a['seconds']);
        });
        $tasks = [];
        foreach ($byTaskMap as $bucket) {
            $entries = array_values($bucket['entries']);
            usort($entries, static function ($a, $b) {
                return strcmp((string) $b['date'], (string) $a['date']);
            });
            foreach ($entries as &$entry) {
                $entry['formatted'] = self::formatDuration((int) $entry['seconds']);
                $entry['pct'] = $pctOf((int) $entry['seconds']);
            }
            unset($entry);
            $taskSec = (int) $bucket['seconds'];
            $tasks[] = [
                'user_id' => $bucket['user_id'],
                'user_label' => $bucket['user_label'],
                'item_id' => $bucket['item_id'],
                'title' => $bucket['title'],
                'domain' => $bucket['domain'],
                'project_id' => $bucket['project_id'],
                'seconds' => $taskSec,
                'formatted' => self::formatDuration($taskSec),
                'pct' => $pctOf($taskSec),
                'is_active' => !empty($bucket['is_active']),
                'entries' => $entries,
            ];
        }

        // Топ задач без разреза по user (для сводки)
        $topAgg = [];
        foreach ($cells as $cell) {
            $k = (string) ($cell['item_id'] ?: ('t:' . md5($cell['title'] . '|' . $cell['domain'])));
            if (!isset($topAgg[$k])) {
                $topAgg[$k] = [
                    'item_id' => $cell['item_id'],
                    'title' => $cell['title'],
                    'domain' => $cell['domain'],
                    'project_id' => $cell['project_id'],
                    'seconds' => 0,
                ];
            }
            $topAgg[$k]['seconds'] += (int) $cell['seconds'];
        }
        uasort($topAgg, static function ($a, $b) {
            return ($b['seconds'] <=> $a['seconds']);
        });
        $topTasks = [];
        foreach (array_slice(array_values($topAgg), 0, 3) as $row) {
            $topTasks[] = [
                'item_id' => $row['item_id'],
                'title' => $row['title'],
                'domain' => $row['domain'],
                'project_id' => $row['project_id'],
                'seconds' => (int) $row['seconds'],
                'formatted' => self::formatDuration((int) $row['seconds']),
                'pct' => $pctOf((int) $row['seconds']),
            ];
        }

        $daysCount = count($byDayMap);
        $avg = $daysCount > 0 ? (int) round($total / $daysCount) : 0;

        return [
            'group_by' => $groupBy,
            'days' => $days,
            'tasks' => $tasks,
            'total' => $total,
            'summary' => [
                'total' => $total,
                'formatted_total' => self::formatDuration($total),
                'days_count' => $daysCount,
                'avg_per_day' => $avg,
                'formatted_avg' => self::formatDuration($avg),
                'top_tasks' => $topTasks,
            ],
            'chart' => $chart,
            'active_item_id' => $activeItemId,
            'show_user' => $showUser,
        ];
    }

    /**
     * Плоские строки для CSV-экспорта учёта времени.
     *
     * @param  array<int, int>|int|string|null  $projectFilter
     * @param  array<int, int>|int|string|null  $userFilter
     * @return array<int, array<string, string|int>>
     */
    public function timesheetExportRows(
        int $viewerId,
        ?string $from = null,
        ?string $to = null,
        $projectFilter = null,
        $userFilter = null
    ): array {
        $data = $this->timesheetForUser($viewerId, $from, $to, $projectFilter, 'day', $userFilter);
        $rows = [];
        foreach ($data['days'] as $day) {
            foreach ($day['entries'] as $entry) {
                $rows[] = [
                    'date' => $day['date'],
                    'user' => (string) ($entry['user_label'] ?? ''),
                    'domain' => (string) ($entry['domain'] ?? ''),
                    'title' => (string) ($entry['title'] ?? ''),
                    'seconds' => (int) ($entry['seconds'] ?? 0),
                    'duration' => (string) ($entry['formatted'] ?? ''),
                    'pct' => (string) ($entry['pct'] ?? '0'),
                ];
            }
        }

        return $rows;
    }

    public static function formatDuration(int $seconds): string
    {
        $seconds = max(0, $seconds);
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        $s = $seconds % 60;
        if ($h > 0) {
            return sprintf('%d:%02d:%02d', $h, $m, $s);
        }

        return sprintf('%d:%02d', $m, $s);
    }

    /**
     * Автозакрытие задач по внешним фактам (Метрика и т.п.).
     */
    public function syncAutoStatuses(SeoChecklistProject $project, ?int $actorUserId = null): void
    {
        $actorUserId = $actorUserId ?: (int) $project->user_id;
        $userId = (int) $project->user_id;
        $domain = (string) $project->domain;

        $this->autoCompleteIf($project, 'metrika_share', $this->domainHasMetrika($userId, $domain), $actorUserId);
        $this->autoCompleteIf($project, 'positions_monitoring', $this->domainHasMonitoring($userId, $domain), $actorUserId);
        $this->autoCompleteIf($project, 'uptime_monitoring', $this->domainHasUptime($userId, $domain), $actorUserId);
        $this->autoCompleteIf($project, 'top_prime_site_analyzer', $this->domainHasSiteAudit($userId, $domain), $actorUserId);
    }

    public function syncMetrikaForDomain(int $userId, string $rawDomain): void
    {
        $domain = HomeUserSites::normalizeDomain($rawDomain);
        if ($domain === '' || $userId < 1) {
            return;
        }

        SeoChecklistProject::query()
            ->where('user_id', $userId)
            ->where('domain', $domain)
            ->where('status', 'active')
            ->get()
            ->each(function (SeoChecklistProject $project) use ($userId) {
                $this->syncAutoStatuses($project, $userId);
            });
    }

    /**
     * Сброс recurring-задач обратно в todo (месяц / каждые N дней).
     *
     * @return int сколько задач сброшено
     */
    public function resetRecurringDue(): int
    {
        $now = now();
        $monthStart = $now->copy()->startOfMonth();
        $reset = 0;
        $rules = SeoChecklistDefaultTemplate::repeatRuleKeys();

        SeoChecklistItem::query()
            ->whereNotNull('repeat_rule')
            ->whereIn('repeat_rule', $rules)
            ->whereIn('status', ['done', 'skip'])
            ->whereNotNull('done_at')
            ->orderBy('id')
            ->chunkById(200, function ($items) use ($now, $monthStart, &$reset) {
                foreach ($items as $item) {
                    $doneAt = $item->done_at;
                    if (!$doneAt) {
                        continue;
                    }
                    $shouldReset = false;
                    if ($item->repeat_rule === 'monthly') {
                        $shouldReset = $doneAt->lt($monthStart);
                    } else {
                        $interval = SeoChecklistDefaultTemplate::repeatIntervalDays($item->repeat_rule);
                        if ($interval) {
                            $shouldReset = $doneAt->copy()->addDays($interval)->lte($now);
                        }
                    }
                    if (!$shouldReset) {
                        continue;
                    }

                    $item->status = 'todo';
                    $item->done_at = null;
                    $item->done_by = null;
                    $item->save();
                    $reset++;

                    if ($item->project) {
                        $item->project->recalculateProgress();
                    }
                }
            });

        return $reset;
    }

    private function domainHasMetrika(int $userId, string $domain): bool
    {
        if ($userId < 1 || $domain === '' || !YandexMetrikaDomainCounter::tableReady()) {
            return false;
        }

        return YandexMetrikaDomainCounter::query()
            ->where('user_id', $userId)
            ->where('domain', $domain)
            ->exists();
    }

    private function domainHasMonitoring(int $userId, string $domain): bool
    {
        if ($userId < 1 || $domain === '') {
            return false;
        }

        $urls = MonitoringProject::query()
            ->whereHas('users', static function ($q) use ($userId) {
                $q->where('users.id', $userId);
            })
            ->pluck('url');

        foreach ($urls as $url) {
            if (HomeUserSites::normalizeDomain((string) $url) === $domain) {
                return true;
            }
        }

        return false;
    }

    private function domainHasUptime(int $userId, string $domain): bool
    {
        if ($userId < 1 || $domain === '') {
            return false;
        }

        $links = DomainMonitoring::query()->where('user_id', $userId)->pluck('link');
        foreach ($links as $link) {
            if (HomeUserSites::normalizeDomain((string) $link) === $domain) {
                return true;
            }
        }

        return false;
    }

    private function domainHasSiteAudit(int $userId, string $domain): bool
    {
        if ($userId < 1 || $domain === '') {
            return false;
        }

        return SiteAuditProject::query()
            ->where('user_id', $userId)
            ->where('domain', $domain)
            ->exists();
    }

    private function autoCompleteIf(SeoChecklistProject $project, string $code, bool $condition, int $actorUserId): void
    {
        if (!$condition) {
            return;
        }

        /** @var SeoChecklistItem|null $item */
        $item = $project->items()
            ->where('code', $code)
            ->whereNull('parent_id')
            ->whereNotIn('status', ['done', 'skip'])
            ->first();

        if (!$item) {
            return;
        }

        $this->setItemStatus($item, 'done', $actorUserId);
    }
}
