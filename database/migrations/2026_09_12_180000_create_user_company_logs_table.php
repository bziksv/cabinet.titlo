<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserCompanyLogsTable extends Migration
{
    public function up()
    {
        Schema::create('user_company_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_company_id')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->string('action', 32);
            $table->string('company_name', 255)->nullable();
            $table->json('changes')->nullable();
            $table->timestamps();

            $table->foreign('user_company_id')->references('id')->on('user_companies')->onDelete('set null');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_company_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('user_company_logs');
    }
}
