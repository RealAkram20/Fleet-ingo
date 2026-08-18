<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
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
         * Shared hosts commonly terminate TLS at a proxy (Cloudflare, a load
         * balancer) and hand PHP a plain-HTTP request. Generated URLs would then
         * be http://, which the browser blocks as mixed content on an https page.
         * APP_URL is the operator's statement of how the site is reached, so its
         * scheme wins.
         */
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

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
