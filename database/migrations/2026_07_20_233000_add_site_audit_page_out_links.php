<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSiteAuditPageOutLinks extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('site_audit_pages') && ! Schema::hasColumn('site_audit_pages', 'out_links_json')) {
            Schema::table('site_audit_pages', function (Blueprint $table) {
                $table->json('out_links_json')->nullable()->after('simhash');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('site_audit_pages') && Schema::hasColumn('site_audit_pages', 'out_links_json')) {
            Schema::table('site_audit_pages', function (Blueprint $table) {
                $table->dropColumn('out_links_json');
            });
        }
    }
}
