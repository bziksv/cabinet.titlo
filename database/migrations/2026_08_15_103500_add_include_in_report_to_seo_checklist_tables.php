<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddIncludeInReportToSeoChecklistTables extends Migration
{
    public function up()
    {
        if (Schema::hasTable('seo_checklist_items') && !Schema::hasColumn('seo_checklist_items', 'include_in_report')) {
            Schema::table('seo_checklist_items', function (Blueprint $table) {
                $table->boolean('include_in_report')->default(true)->after('is_important');
            });
            // Пункты (подзадачи) — opt-in; родительские задачи оставляем в отчётах как раньше.
            DB::table('seo_checklist_items')->whereNotNull('parent_id')->update(['include_in_report' => false]);
        }

        if (Schema::hasTable('seo_checklist_template_tasks') && !Schema::hasColumn('seo_checklist_template_tasks', 'include_in_report')) {
            Schema::table('seo_checklist_template_tasks', function (Blueprint $table) {
                $table->boolean('include_in_report')->default(true)->after('is_important');
            });
            DB::table('seo_checklist_template_tasks')->whereNotNull('parent_id')->update(['include_in_report' => false]);
        }
    }

    public function down()
    {
        if (Schema::hasTable('seo_checklist_items') && Schema::hasColumn('seo_checklist_items', 'include_in_report')) {
            Schema::table('seo_checklist_items', function (Blueprint $table) {
                $table->dropColumn('include_in_report');
            });
        }
        if (Schema::hasTable('seo_checklist_template_tasks') && Schema::hasColumn('seo_checklist_template_tasks', 'include_in_report')) {
            Schema::table('seo_checklist_template_tasks', function (Blueprint $table) {
                $table->dropColumn('include_in_report');
            });
        }
    }
}
