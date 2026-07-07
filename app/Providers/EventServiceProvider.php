<?php

namespace App\Providers;

use App\Events\MonitoringProjectBeforeDelete;
use App\Events\MonitoringProjectCreated;
use App\Listeners\AssignAdminMonitoringRoleForAuthUser;
use App\Listeners\AssignRoleRegisteredUser;
use App\Listeners\RefreshMonitoringProjectFavicon;
use App\Listeners\RemoveAllRolesMonitoringProjectUsers;
use App\Listeners\RecordQueueJobFailed;
use App\Listeners\RecordQueueJobProcessed;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\Event;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        Verified::class => [
            AssignRoleRegisteredUser::class,
        ],
        MonitoringProjectCreated::class => [
            AssignAdminMonitoringRoleForAuthUser::class,
            RefreshMonitoringProjectFavicon::class,
        ],
        MonitoringProjectBeforeDelete::class => [
            RemoveAllRolesMonitoringProjectUsers::class,
        ],
        JobProcessed::class => [
            RecordQueueJobProcessed::class,
        ],
        JobFailed::class => [
            RecordQueueJobFailed::class,
        ],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();

        //
    }
}
