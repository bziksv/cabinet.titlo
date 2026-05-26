<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDomainMonitoringCheckLogsTable extends Migration
{
    public function up()
    {
        Schema::create('domain_monitoring_check_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('domain_monitoring_id');
            $table->unsignedBigInteger('user_id');
            $table->boolean('broken')->default(false);
            $table->string('status', 64)->nullable();
            $table->unsignedSmallInteger('http_code')->nullable();
            $table->float('uptime_percent')->nullable();
            $table->string('source', 16)->default('cron');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['domain_monitoring_id', 'created_at'], 'dm_check_logs_project_created');
            $table->index(['user_id', 'created_at'], 'dm_check_logs_user_created');

            $table->foreign('domain_monitoring_id')
                ->references('id')
                ->on('domain_monitoring')
                ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('domain_monitoring_check_logs');
    }
}
