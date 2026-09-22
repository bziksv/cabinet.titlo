<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddClientHelpToSeoChecklistTables extends Migration
{
    public function up()
    {
        if (Schema::hasTable('seo_checklist_template_tasks') && !Schema::hasColumn('seo_checklist_template_tasks', 'client_help')) {
            Schema::table('seo_checklist_template_tasks', function (Blueprint $table) {
                $table->text('client_help')->nullable()->after('help');
            });
        }

        if (Schema::hasTable('seo_checklist_items') && !Schema::hasColumn('seo_checklist_items', 'client_help')) {
            Schema::table('seo_checklist_items', function (Blueprint $table) {
                $table->text('client_help')->nullable()->after('help');
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('seo_checklist_template_tasks') && Schema::hasColumn('seo_checklist_template_tasks', 'client_help')) {
            Schema::table('seo_checklist_template_tasks', function (Blueprint $table) {
                $table->dropColumn('client_help');
            });
        }

        if (Schema::hasTable('seo_checklist_items') && Schema::hasColumn('seo_checklist_items', 'client_help')) {
            Schema::table('seo_checklist_items', function (Blueprint $table) {
                $table->dropColumn('client_help');
            });
        }
    }
}
