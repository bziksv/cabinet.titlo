<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSiteAuditShareAndSimhash extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('site_audit_crawls') && ! Schema::hasColumn('site_audit_crawls', 'share_token')) {
            Schema::table('site_audit_crawls', function (Blueprint $table) {
                $table->string('share_token', 64)->nullable()->unique()->after('save_html');
                $table->timestamp('share_enabled_at')->nullable()->after('share_token');
            });
        }

        if (Schema::hasTable('site_audit_pages') && ! Schema::hasColumn('site_audit_pages', 'simhash')) {
            Schema::table('site_audit_pages', function (Blueprint $table) {
                $table->string('simhash', 16)->nullable()->index()->after('content_hash');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('site_audit_crawls') && Schema::hasColumn('site_audit_crawls', 'share_token')) {
            Schema::table('site_audit_crawls', function (Blueprint $table) {
                $table->dropUnique(['share_token']);
                $table->dropColumn(['share_token', 'share_enabled_at']);
            });
        }

        if (Schema::hasTable('site_audit_pages') && Schema::hasColumn('site_audit_pages', 'simhash')) {
            Schema::table('site_audit_pages', function (Blueprint $table) {
                $table->dropColumn('simhash');
            });
        }
    }
}
