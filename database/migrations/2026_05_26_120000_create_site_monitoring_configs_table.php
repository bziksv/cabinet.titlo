<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSiteMonitoringConfigsTable extends Migration
{
    public function up()
    {
        Schema::create('site_monitoring_configs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedSmallInteger('repeat_broken_notification_minutes')->default(360);
            $table->boolean('default_send_notification')->default(true);
            $table->boolean('email_notifications_enabled')->default(true);
            $table->boolean('telegram_notifications_enabled')->default(true);
            $table->timestamps();
        });

        \App\SiteMonitoringConfig::query()->create([
            'repeat_broken_notification_minutes' => (int) config('cabinet-site-monitoring.notifications.repeat_broken_minutes', 360),
            'default_send_notification' => true,
            'email_notifications_enabled' => true,
            'telegram_notifications_enabled' => true,
        ]);
    }

    public function down()
    {
        Schema::dropIfExists('site_monitoring_configs');
    }
}
