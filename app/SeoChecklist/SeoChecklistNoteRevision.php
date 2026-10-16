<?php

namespace App\SeoChecklist;

use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoChecklistNoteRevision extends Model
{
    protected $table = 'seo_checklist_note_revisions';

    public $timestamps = false;

    protected $fillable = [
        'note_id', 'user_id', 'body', 'created_at',
    ];

    protected $dates = [
        'created_at',
    ];

    public function note(): BelongsTo
    {
        return $this->belongsTo(SeoChecklistItemNote::class, 'note_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
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
