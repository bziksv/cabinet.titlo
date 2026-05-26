<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddErrorsCountToMetaTagsHistoriesTable extends Migration
{
    public function up()
    {
        Schema::table('meta_tags_histories', function (Blueprint $table) {
            $table->unsignedInteger('errors_count')->nullable()->after('quantity');
        });
    }

    public function down()
    {
        Schema::table('meta_tags_histories', function (Blueprint $table) {
            $table->dropColumn('errors_count');
        });
    }
}
