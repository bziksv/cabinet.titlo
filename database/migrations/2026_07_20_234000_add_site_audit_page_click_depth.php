<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSiteAuditPageClickDepth extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('site_audit_pages')) {
            return;
        }
        if (! Schema::hasColumn('site_audit_pages', 'click_depth')) {
            Schema::table('site_audit_pages', function (Blueprint $table) {
                $table->unsignedSmallInteger('click_depth')->nullable()->after('out_links_json')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('site_audit_pages') && Schema::hasColumn('site_audit_pages', 'click_depth')) {
            Schema::table('site_audit_pages', function (Blueprint $table) {
                $table->dropColumn('click_depth');
            });
        }
    }
}
