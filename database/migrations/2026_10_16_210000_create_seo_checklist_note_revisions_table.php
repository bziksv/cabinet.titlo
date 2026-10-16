<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSeoChecklistNoteRevisionsTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('seo_checklist_note_revisions')) {
            return;
        }

        Schema::create('seo_checklist_note_revisions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('note_id')->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->text('body');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down()
    {
        Schema::dropIfExists('seo_checklist_note_revisions');
    }
}
