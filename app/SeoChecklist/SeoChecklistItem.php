<?php

namespace App\SeoChecklist;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SeoChecklistItem extends Model
{
    /** Рабочие статусы (+ skip legacy). blocked → clarify (миграция). */
    public const STATUSES = ['todo', 'doing', 'rework', 'clarify', 'review', 'done', 'skip', 'blocked'];

    public const OPEN_STATUSES = ['todo', 'doing', 'rework', 'clarify', 'review'];

    public const CLOSED_STATUSES = ['done', 'skip'];

    protected $table = 'seo_checklist_items';

    protected $fillable = [
        'project_id', 'parent_id', 'code', 'stage_key', 'stage_sort', 'sort',
        'title', 'help', 'role', 'is_important', 'include_in_report', 'allows_subtasks', 'repeat_rule', 'due_days_from_start', 'due_at', 'links_json',
        'status', 'assignee_user_id', 'done_at', 'done_by', 'created_by', 'time_spent_seconds',
    ];

    protected $casts = [
        'is_important' => 'boolean',
        'include_in_report' => 'boolean',
        'allows_subtasks' => 'boolean',
        'links_json' => 'array',
        'done_at' => 'datetime',
        'due_at' => 'datetime',
        'time_spent_seconds' => 'integer',
    ];

    public function isOpenStatus(): bool
    {
        $status = $this->status === 'blocked' ? 'clarify' : $this->status;

        return in_array($status, self::OPEN_STATUSES, true);
    }

    public function isClosedStatus(): bool
    {
        return in_array($this->status, self::CLOSED_STATUSES, true);
    }

    public function isSubtask(): bool
    {
        return $this->parent_id !== null;
    }

    public function isOverdue(): bool
    {
        if (!$this->due_at || !$this->isOpenStatus()) {
            return false;
        }

        return $this->due_at->isPast();
    }

    public function isDueSoon(int $withinDays = 2): bool
    {
        if (!$this->due_at || !$this->isOpenStatus() || $this->isOverdue()) {
            return false;
        }

        return $this->due_at->lte(now()->addDays($withinDays));
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(SeoChecklistProject::class, 'project_id');
    }

    public function doneByUser(): BelongsTo
    {
        return $this->belongsTo(\App\User::class, 'done_by');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(\App\User::class, 'created_by');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(SeoChecklistItemNote::class, 'item_id')->orderByDesc('id');
    }

    public function timeLogs(): HasMany
    {
        return $this->hasMany(SeoChecklistItemTimeLog::class, 'item_id')->orderByDesc('id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function runningTimeLog(?int $userId = null): ?SeoChecklistItemTimeLog
    {
        $q = $this->timeLogs()->whereNull('ended_at')->orderByDesc('id');
        if ($userId !== null) {
            $q->where('user_id', $userId);
        }

        return $q->first();
    }

    public function displayTimeSpentSeconds(?int $forUserId = null): int
    {
        $base = max(0, (int) $this->time_spent_seconds);
        $running = $this->runningTimeLog($forUserId);
        if ($running) {
            return $base + $running->elapsedSeconds();
        }

        return $base;
    }
}
