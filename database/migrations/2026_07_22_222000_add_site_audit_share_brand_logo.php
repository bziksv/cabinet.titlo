<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSiteAuditShareBrandLogo extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('site_audit_crawls')) {
            return;
        }
        if (Schema::hasColumn('site_audit_crawls', 'share_brand_logo')) {
            return;
        }

        Schema::table('site_audit_crawls', function (Blueprint $table) {
            $table->string('share_brand_logo', 255)->nullable()->after('share_brand_url');
        });
    }

    public function down()
    {
        if (! Schema::hasTable('site_audit_crawls')) {
            return;
        }
        if (! Schema::hasColumn('site_audit_crawls', 'share_brand_logo')) {
            return;
        }

        Schema::table('site_audit_crawls', function (Blueprint $table) {
            $table->dropColumn('share_brand_logo');
        });
    }
}
