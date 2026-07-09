<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddEseninTextCheckSessions extends Migration
{
    public function up(): void
    {
        Schema::create('esenin_text_check_sessions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->string('name', 120);
            $table->string('source', 16)->default('text');
            $table->string('source_url', 2048)->nullable();
            $table->string('tbclass', 255)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'updated_at']);
        });

        Schema::create('esenin_text_check_versions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('session_id');
            $table->longText('text');
            $table->longText('result_json')->nullable();
            $table->unsignedSmallInteger('risk_score')->nullable();
            $table->string('risk_level', 64)->nullable();
            $table->boolean('is_check')->default(false);
            $table->timestamps();

            $table->index(['session_id', 'id']);
            $table->foreign('session_id', 'esenin_versions_session_fk')
                ->references('id')
                ->on('esenin_text_check_sessions')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('esenin_text_check_versions');
        Schema::dropIfExists('esenin_text_check_sessions');
    }
}
