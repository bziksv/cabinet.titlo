<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateEseninTextCheckPublicSharesTable extends Migration
{
    public function up()
    {
        Schema::create('esenin_text_check_public_shares', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('esenin_text_check_session_id')->nullable();
            $table->string('token', 64)->unique();
            $table->longText('payload');
            $table->string('snapshot_hash', 64);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id', 'esenin_pub_shares_user_fk')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            $table->foreign('esenin_text_check_session_id', 'esenin_pub_shares_session_fk')
                ->references('id')
                ->on('esenin_text_check_sessions')
                ->onDelete('cascade');

            $table->index(['esenin_text_check_session_id', 'revoked_at'], 'esenin_public_shares_session_revoked');
            $table->index(['user_id', 'revoked_at'], 'esenin_public_shares_user_revoked');
            $table->index('expires_at', 'esenin_public_shares_expires');
        });
    }

    public function down()
    {
        Schema::dropIfExists('esenin_text_check_public_shares');
    }
}
