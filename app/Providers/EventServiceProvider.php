<?php

namespace App\Providers;

use Gametech\Auto\Events\BatchUser;
use Gametech\Auto\Listeners\BatchUserListen;
use Illuminate\Auth\Events\Registered;
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
        BatchUser::class => [
            BatchUserListen::class,
        ],
        \Codedge\Updater\Events\UpdateAvailable::class => [
            \Codedge\Updater\Listeners\SendUpdateAvailableNotification::class
        ],
        \Codedge\Updater\Events\UpdateSucceeded::class => [
            \Codedge\Updater\Listeners\SendUpdateSucceededNotification::class
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
