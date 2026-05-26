<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDomainInformationCheckLogsTable extends Migration
{
    public function up()
    {
        Schema::create('domain_information_check_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('domain_information_id');
            $table->unsignedBigInteger('user_id');
            $table->boolean('broken')->default(false);
            $table->text('info_snapshot')->nullable();
            $table->boolean('dns_changed')->default(false);
            $table->string('source', 16)->default('cron');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['domain_information_id', 'created_at'], 'di_check_logs_project_created');
            $table->index(['user_id', 'created_at'], 'di_check_logs_user_created');

            $table->foreign('domain_information_id')
                ->references('id')
                ->on('domain_information')
                ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('domain_information_check_logs');
    }
}
