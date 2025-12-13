<?php

namespace App\Providers;

use App\Observers\NotificationObserver;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Broadcast notifications when they are stored in the database
        DatabaseNotification::observe(NotificationObserver::class);
        \App\Models\User::observe(\App\Observers\UserObserver::class);
        \App\Models\Group::observe(\App\Observers\GroupObserver::class);
    }
}
