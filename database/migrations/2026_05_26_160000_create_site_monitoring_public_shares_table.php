<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSiteMonitoringPublicSharesTable extends Migration
{
    public function up()
    {
        Schema::create('site_monitoring_public_shares', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('domain_monitoring_id');
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

            $table->foreign('domain_monitoring_id')
                ->references('id')
                ->on('domain_monitoring')
                ->onDelete('cascade');

            $table->index(['domain_monitoring_id', 'revoked_at'], 'sm_public_shares_project_revoked');
            $table->index(['user_id', 'revoked_at'], 'sm_public_shares_user_revoked');
            $table->index('expires_at', 'sm_public_shares_expires');
        });
    }

    public function down()
    {
        Schema::dropIfExists('site_monitoring_public_shares');
    }
}
