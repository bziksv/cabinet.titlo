<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateAiProviderSettingsTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('ai_provider_settings')) {
            return;
        }

        Schema::create('ai_provider_settings', function (Blueprint $table) {
            $table->increments('id');
            $table->string('default_provider', 32)->default('plusvibeapi');
            $table->text('module_providers')->nullable();
            $table->timestamps();
        });

        DB::table('ai_provider_settings')->insert([
            'id' => 1,
            'default_provider' => 'plusvibeapi',
            'module_providers' => json_encode([
                'ai_generation' => 'plusvibeapi',
                'site_audit_action_plan' => 'plusvibeapi',
            ], JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down()
    {
        Schema::dropIfExists('ai_provider_settings');
    }
}
