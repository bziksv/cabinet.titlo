<?php

namespace App\SeoChecklist;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SeoChecklistTemplate extends Model
{
    protected $table = 'seo_checklist_templates';

    protected $fillable = [
        'user_id', 'code', 'title', 'description', 'stages_json', 'is_system', 'admin_only', 'skip_weekends',
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'admin_only' => 'boolean',
        'skip_weekends' => 'boolean',
        'stages_json' => 'array',
    ];

    public function tasks(): HasMany
    {
        return $this->hasMany(SeoChecklistTemplateTask::class, 'template_id')
            ->orderBy('stage_sort')
            ->orderBy('sort');
    }

    public function projects(): HasMany
    {
        return $this->hasMany(SeoChecklistProject::class, 'template_id');
    }

    public static function systemDefault(): ?self
    {
        return static::query()->where('code', \App\Support\SeoChecklistDefaultTemplate::CODE)->first();
    }
}
