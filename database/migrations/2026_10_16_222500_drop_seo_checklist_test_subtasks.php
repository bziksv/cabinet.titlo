<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DropSeoChecklistTestSubtasks extends Migration
{
    public function up()
    {
        $titles = ['11111', '222'];

        if (Schema::hasTable('seo_checklist_template_tasks')) {
            DB::table('seo_checklist_template_tasks')
                ->whereNotNull('parent_id')
                ->whereIn('title', $titles)
                ->delete();
        }

        if (!Schema::hasTable('seo_checklist_items')) {
            return;
        }

        $itemIds = DB::table('seo_checklist_items')
            ->whereNotNull('parent_id')
            ->whereIn('title', $titles)
            ->pluck('id');
        if ($itemIds->isEmpty()) {
            return;
        }

        $projectIds = DB::table('seo_checklist_items')
            ->whereIn('id', $itemIds)
            ->pluck('project_id')
            ->unique()
            ->values();

        if (Schema::hasTable('seo_checklist_item_notes')) {
            $noteIds = DB::table('seo_checklist_item_notes')->whereIn('item_id', $itemIds)->pluck('id');
            if ($noteIds->isNotEmpty() && Schema::hasTable('seo_checklist_note_reads')) {
                DB::table('seo_checklist_note_reads')->whereIn('note_id', $noteIds)->delete();
            }
            if ($noteIds->isNotEmpty() && Schema::hasTable('seo_checklist_note_revisions')) {
                DB::table('seo_checklist_note_revisions')->whereIn('note_id', $noteIds)->delete();
            }
            DB::table('seo_checklist_item_notes')->whereIn('item_id', $itemIds)->delete();
        }
        if (Schema::hasTable('seo_checklist_item_time_logs')) {
            DB::table('seo_checklist_item_time_logs')->whereIn('item_id', $itemIds)->delete();
        }
        if (Schema::hasTable('seo_checklist_item_attachments')) {
            DB::table('seo_checklist_item_attachments')->whereIn('item_id', $itemIds)->delete();
        }
        if (Schema::hasTable('seo_checklist_activity_logs')) {
            $activityIds = DB::table('seo_checklist_activity_logs')->whereIn('item_id', $itemIds)->pluck('id');
            if ($activityIds->isNotEmpty() && Schema::hasTable('seo_checklist_activity_reads')) {
                DB::table('seo_checklist_activity_reads')->whereIn('activity_id', $activityIds)->delete();
            }
            DB::table('seo_checklist_activity_logs')->whereIn('item_id', $itemIds)->delete();
        }

        DB::table('seo_checklist_items')->whereIn('id', $itemIds)->delete();

        foreach ($projectIds as $projectId) {
            $total = (int) DB::table('seo_checklist_items')
                ->where('project_id', $projectId)
                ->whereNull('parent_id')
                ->count();
            $done = (int) DB::table('seo_checklist_items')
                ->where('project_id', $projectId)
                ->whereNull('parent_id')
                ->whereIn('status', ['done', 'skip'])
                ->count();
            DB::table('seo_checklist_projects')->where('id', $projectId)->update([
                'progress_total' => $total,
                'progress_done' => $done,
            ]);
        }
    }

    public function down()
    {
    }
}
