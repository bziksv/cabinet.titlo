<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAdminOnlyToSeoChecklistTemplates extends Migration
{
    public function up()
    {
        if (Schema::hasTable('seo_checklist_templates') && !Schema::hasColumn('seo_checklist_templates', 'admin_only')) {
            Schema::table('seo_checklist_templates', function (Blueprint $table) {
                $table->boolean('admin_only')->default(false)->after('is_system');
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('seo_checklist_templates') && Schema::hasColumn('seo_checklist_templates', 'admin_only')) {
            Schema::table('seo_checklist_templates', function (Blueprint $table) {
                $table->dropColumn('admin_only');
            });
        }
    }
}
