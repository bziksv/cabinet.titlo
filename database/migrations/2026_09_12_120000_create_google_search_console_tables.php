<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGoogleSearchConsoleTables extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('google_search_console_user_tokens')) {
            Schema::create('google_search_console_user_tokens', function (Blueprint $table) {
                $table->unsignedBigInteger('user_id')->primary();
                $table->text('access_token');
                $table->text('refresh_token')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->string('google_email', 191)->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('google_search_console_domain_properties')) {
            Schema::create('google_search_console_domain_properties', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('user_id')->index();
                $table->string('domain', 255);
                $table->string('property_id', 255);
                $table->string('property_url', 255)->nullable();
                $table->boolean('verified')->default(false);
                $table->timestamps();

                $table->unique(['user_id', 'domain'], 'gsc_domain_props_user_domain_unique');
                $table->index(['user_id', 'property_id'], 'gsc_domain_props_user_property_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('google_search_console_domain_properties');
        Schema::dropIfExists('google_search_console_user_tokens');
    }
}
