<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDomainInformationPublicSharesTable extends Migration
{
    public function up()
    {
        Schema::create('domain_information_public_shares', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('domain_information_id');
            $table->string('token', 64)->unique();
            $table->longText('payload');
            $table->string('snapshot_hash', 64);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            $table->foreign('domain_information_id')
                ->references('id')
                ->on('domain_information')
                ->onDelete('cascade');

            $table->index(['domain_information_id', 'revoked_at'], 'di_public_shares_project_revoked');
            $table->index(['user_id', 'revoked_at'], 'di_public_shares_user_revoked');
            $table->index('expires_at', 'di_public_shares_expires');
        });
    }

    public function down()
    {
        Schema::dropIfExists('domain_information_public_shares');
    }
}
