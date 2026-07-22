<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Метрики тошноты / биграмм / текста в noindex (parse-time, HTML дальше не храним).
 */
class AddSiteAuditPageTextMetrics extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('site_audit_pages')) {
            return;
        }

        Schema::table('site_audit_pages', function (Blueprint $table) {
            if (! Schema::hasColumn('site_audit_pages', 'nausea_classic')) {
                $table->decimal('nausea_classic', 6, 2)->nullable()->after('em_count');
            }
            if (! Schema::hasColumn('site_audit_pages', 'nausea_academic')) {
                $table->decimal('nausea_academic', 6, 2)->nullable()->after('nausea_classic');
            }
            if (! Schema::hasColumn('site_audit_pages', 'top_word')) {
                $table->string('top_word', 64)->nullable()->after('nausea_academic');
            }
            if (! Schema::hasColumn('site_audit_pages', 'top_word_count')) {
                $table->unsignedInteger('top_word_count')->default(0)->after('top_word');
            }
            if (! Schema::hasColumn('site_audit_pages', 'top_bigram')) {
                $table->string('top_bigram', 128)->nullable()->after('top_word_count');
            }
            if (! Schema::hasColumn('site_audit_pages', 'top_bigram_count')) {
                $table->unsignedInteger('top_bigram_count')->default(0)->after('top_bigram');
            }
            if (! Schema::hasColumn('site_audit_pages', 'noindex_text_len')) {
                $table->unsignedInteger('noindex_text_len')->default(0)->after('top_bigram_count');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('site_audit_pages')) {
            return;
        }

        Schema::table('site_audit_pages', function (Blueprint $table) {
            foreach ([
                'nausea_classic',
                'nausea_academic',
                'top_word',
                'top_word_count',
                'top_bigram',
                'top_bigram_count',
                'noindex_text_len',
            ] as $col) {
                if (Schema::hasColumn('site_audit_pages', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
}
