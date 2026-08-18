<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
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
        /*
         * Riders, bikes and service intervals are the fleet's shape, and only an
         * admin changes them. A clerk can still log readings and mark a bike
         * serviced, which is the whole of the day-to-day yard job.
         */
        Gate::define('manage-fleet', fn (User $user) => $user->isAdmin());

        /*
         * Accounts and application configuration. The same test as manage-fleet
         * today, but kept separate because they are different powers and one may
         * well outlive the other.
         */
        Gate::define('administer', fn (User $user) => $user->isAdmin());
    }
}
