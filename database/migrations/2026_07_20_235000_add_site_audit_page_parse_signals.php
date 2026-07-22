<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Доп. parse-сигналы волны 2 (h2, text_len, img unique, charset, strong/em).
 */
class AddSiteAuditPageParseSignals extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('site_audit_pages')) {
            return;
        }

        Schema::table('site_audit_pages', function (Blueprint $table) {
            if (! Schema::hasColumn('site_audit_pages', 'h2_count')) {
                $table->unsignedInteger('h2_count')->default(0)->after('h1_count');
            }
            if (! Schema::hasColumn('site_audit_pages', 'text_len')) {
                $table->unsignedInteger('text_len')->nullable()->after('word_count');
            }
            if (! Schema::hasColumn('site_audit_pages', 'unique_img_src_count')) {
                $table->unsignedInteger('unique_img_src_count')->default(0)->after('img_without_alt');
            }
            if (! Schema::hasColumn('site_audit_pages', 'charset')) {
                $table->string('charset', 64)->nullable()->after('content_type');
            }
            if (! Schema::hasColumn('site_audit_pages', 'strong_count')) {
                $table->unsignedInteger('strong_count')->default(0)->after('unique_img_src_count');
            }
            if (! Schema::hasColumn('site_audit_pages', 'em_count')) {
                $table->unsignedInteger('em_count')->default(0)->after('strong_count');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('site_audit_pages')) {
            return;
        }

        Schema::table('site_audit_pages', function (Blueprint $table) {
            foreach (['h2_count', 'text_len', 'unique_img_src_count', 'charset', 'strong_count', 'em_count'] as $col) {
                if (Schema::hasColumn('site_audit_pages', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
}
