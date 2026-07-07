<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateQueueStatsTables extends Migration
{
    public function up(): void
    {
        Schema::create('queue_stats_samples', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->dateTime('sampled_at');
            $table->string('program', 64);
            $table->unsignedInteger('jobs_pending')->default(0);
            $table->unsignedInteger('jobs_reserved')->default(0);
            $table->unsignedSmallInteger('workers_running')->default(0);
            $table->unsignedSmallInteger('workers_total')->default(0);
            $table->string('load', 16)->default('ok');
            $table->index(['sampled_at', 'program']);
        });

        Schema::create('queue_job_hourly', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->date('stat_date');
            $table->unsignedTinyInteger('stat_hour');
            $table->string('queue', 191);
            $table->unsignedInteger('processed')->default(0);
            $table->unsignedInteger('failed')->default(0);
            $table->unique(['stat_date', 'stat_hour', 'queue'], 'queue_job_hourly_unique');
        });

        Schema::create('queue_daily_stats', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->date('stat_date');
            $table->string('program', 64);
            $table->unsignedInteger('jobs_processed')->default(0);
            $table->unsignedInteger('jobs_failed')->default(0);
            $table->unsignedInteger('peak_pending')->default(0);
            $table->unsignedInteger('peak_reserved')->default(0);
            $table->unsignedInteger('peak_total')->default(0);
            $table->unsignedInteger('idle_seconds')->default(0);
            $table->unsignedInteger('stopped_seconds')->default(0);
            $table->unsignedInteger('backlog_seconds')->default(0);
            $table->decimal('workers_running_avg', 6, 2)->default(0);
            $table->unsignedSmallInteger('workers_running_min')->default(0);
            $table->unsignedSmallInteger('workers_running_max')->default(0);
            $table->unsignedInteger('samples_count')->default(0);
            $table->timestamps();
            $table->unique(['stat_date', 'program'], 'queue_daily_stats_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queue_daily_stats');
        Schema::dropIfExists('queue_job_hourly');
        Schema::dropIfExists('queue_stats_samples');
    }
}
