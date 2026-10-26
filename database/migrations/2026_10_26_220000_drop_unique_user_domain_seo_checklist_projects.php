<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DropUniqueUserDomainSeoChecklistProjects extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('seo_checklist_projects')) {
            return;
        }

        $indexes = collect(DB::select('SHOW INDEX FROM seo_checklist_projects'))
            ->pluck('Key_name')
            ->unique()
            ->all();

        Schema::table('seo_checklist_projects', function (Blueprint $table) use ($indexes) {
            if (in_array('seo_checklist_projects_user_id_domain_unique', $indexes, true)) {
                $table->dropUnique('seo_checklist_projects_user_id_domain_unique');
            }
            if (!in_array('seo_checklist_projects_user_id_domain_index', $indexes, true)) {
                $table->index(['user_id', 'domain'], 'seo_checklist_projects_user_id_domain_index');
            }
        });
    }

    public function down()
    {
        if (!Schema::hasTable('seo_checklist_projects')) {
            return;
        }

        $indexes = collect(DB::select('SHOW INDEX FROM seo_checklist_projects'))
            ->pluck('Key_name')
            ->unique()
            ->all();

        Schema::table('seo_checklist_projects', function (Blueprint $table) use ($indexes) {
            if (in_array('seo_checklist_projects_user_id_domain_index', $indexes, true)) {
                $table->dropIndex('seo_checklist_projects_user_id_domain_index');
            }
            if (!in_array('seo_checklist_projects_user_id_domain_unique', $indexes, true)) {
                $table->unique(['user_id', 'domain'], 'seo_checklist_projects_user_id_domain_unique');
            }
        });
    }
}
