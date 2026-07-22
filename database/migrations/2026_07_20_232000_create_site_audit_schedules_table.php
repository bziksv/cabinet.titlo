<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSiteAuditSchedulesTable extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('site_audit_schedules')) {
            return;
        }

        Schema::create('site_audit_schedules', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('project_id')->index();
            $table->string('domain', 255);
            $table->boolean('enabled')->default(true)->index();
            // daily|weekly
            $table->string('frequency', 16)->default('weekly');
            $table->json('settings_json')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['user_id', 'project_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_audit_schedules');
    }
}
