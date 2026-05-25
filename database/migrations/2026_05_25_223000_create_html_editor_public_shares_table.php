<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateHtmlEditorPublicSharesTable extends Migration
{
    public function up()
    {
        Schema::create('html_editor_public_shares', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('description_id');
            $table->string('token', 64)->unique();
            $table->longText('payload');
            $table->string('content_hash', 64);
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['description_id', 'revoked_at']);
            $table->index('expires_at');
            $table->index('user_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('html_editor_public_shares');
    }
}
