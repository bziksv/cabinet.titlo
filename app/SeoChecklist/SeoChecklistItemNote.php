<?php

namespace App\SeoChecklist;

use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SeoChecklistItemNote extends Model
{
    protected $table = 'seo_checklist_item_notes';

    protected $fillable = [
        'item_id', 'user_id', 'body',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(SeoChecklistItem::class, 'item_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reads(): HasMany
    {
        return $this->hasMany(SeoChecklistNoteRead::class, 'note_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(SeoChecklistItemAttachment::class, 'note_id')->orderBy('id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(SeoChecklistNoteRevision::class, 'note_id')->orderByDesc('id');
    }

    public function wasEdited(): bool
    {
        if ($this->relationLoaded('revisions')) {
            return $this->revisions->isNotEmpty();
        }

        return $this->revisions()->exists();
    }

    public function authorLabel(): string
    {
        $user = $this->user;
        if (!$user) {
            return '—';
        }
        $name = trim(($user->name ?? '') . ' ' . ($user->last_name ?? ''));

        return $name !== '' ? $name : (string) ($user->email ?: '—');
    }
}
