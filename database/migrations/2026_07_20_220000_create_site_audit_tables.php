<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Site Audit — волна 2 (локально / основная MySQL).
 * HTML blob не храним; опциональный html_storage_key позже (remote).
 */
class CreateSiteAuditTables extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('site_audit_projects')) {
            Schema::create('site_audit_projects', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('user_id')->index();
                $table->string('domain', 255);
                $table->string('name', 255)->nullable();
                $table->json('settings_json')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'domain']);
            });
        }

        if (! Schema::hasTable('site_audit_crawls')) {
            Schema::create('site_audit_crawls', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('project_id')->index();
                $table->unsignedBigInteger('user_id')->index();
                $table->string('status', 32)->default('queued')->index();
                // queued|discovering|fetching|aggregating|done|failed|queued_wait
                $table->unsignedInteger('pages_total')->default(0);
                $table->unsignedInteger('pages_fetched')->default(0);
                $table->unsignedInteger('pages_limit')->default(0);
                $table->json('buckets_json')->nullable();
                $table->json('counts_json')->nullable();
                $table->json('progress_json')->nullable();
                $table->text('error')->nullable();
                $table->string('save_html', 16)->default('off');
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('site_audit_pages')) {
            Schema::create('site_audit_pages', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('crawl_id')->index();
                $table->string('url', 2048);
                $table->string('url_hash', 64)->index();
                $table->string('final_url', 2048)->nullable();
                $table->unsignedSmallInteger('status_code')->nullable()->index();
                $table->json('redirect_chain')->nullable();
                $table->unsignedInteger('size_bytes')->nullable();
                $table->string('content_type', 128)->nullable();
                $table->string('title', 1024)->nullable();
                $table->string('title_hash', 64)->nullable()->index();
                $table->string('description', 2048)->nullable();
                $table->string('description_hash', 64)->nullable()->index();
                $table->string('h1', 1024)->nullable();
                $table->unsignedTinyInteger('h1_count')->default(0);
                $table->string('canonical', 2048)->nullable();
                $table->string('robots_meta', 255)->nullable();
                $table->boolean('noindex')->default(false)->index();
                $table->unsignedInteger('word_count')->nullable();
                $table->string('content_hash', 64)->nullable()->index();
                $table->unsignedInteger('img_count')->default(0);
                $table->unsignedInteger('img_without_alt')->default(0);
                $table->string('html_storage_key', 512)->nullable();
                $table->unsignedInteger('html_bytes_gz')->nullable();
                $table->timestamps();

                $table->unique(['crawl_id', 'url_hash']);
            });
        }

        if (! Schema::hasTable('site_audit_findings')) {
            Schema::create('site_audit_findings', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('crawl_id')->index();
                $table->string('code', 64)->index();
                $table->string('severity', 16)->index();
                // critical|other|warning|info
                $table->string('url', 2048)->nullable();
                $table->string('url_hash', 64)->nullable()->index();
                $table->json('meta_json')->nullable();
                $table->timestamps();

                $table->index(['crawl_id', 'code']);
            });
        }

        if (! Schema::hasTable('site_audit_crawl_stats')) {
            Schema::create('site_audit_crawl_stats', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('crawl_id')->index();
                $table->string('bucket', 32);
                $table->unsignedInteger('value')->default(0);
                $table->timestamps();

                $table->unique(['crawl_id', 'bucket']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('site_audit_crawl_stats');
        Schema::dropIfExists('site_audit_findings');
        Schema::dropIfExists('site_audit_pages');
        Schema::dropIfExists('site_audit_crawls');
        Schema::dropIfExists('site_audit_projects');
    }
}
