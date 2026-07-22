<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Игнор false-positive: на уровне project (живёт между краулами).
 * url_hash = '' → игнор всего кода; иначе конкретный URL.
 */
class CreateSiteAuditIgnoresTable extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('site_audit_ignores')) {
            return;
        }

        Schema::create('site_audit_ignores', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('project_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('code', 64)->index();
            $table->string('url_hash', 64)->default('');
            $table->string('url', 2048)->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'code', 'url_hash'], 'site_audit_ignores_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_audit_ignores');
    }
}
