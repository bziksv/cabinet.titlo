<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddNewsCommentTrackingAndUserBlock extends Migration
{
    public function up(): void
    {
        Schema::table('news_notification', function (Blueprint $table) {
            $table->dateTime('last_comment_check')->nullable()->after('last_check');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('news_comments_blocked_at')->nullable()->after('email_verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('news_notification', function (Blueprint $table) {
            $table->dropColumn('last_comment_check');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('news_comments_blocked_at');
        });
    }
}
