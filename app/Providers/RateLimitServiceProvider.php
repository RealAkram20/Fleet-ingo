<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Named rate limits. Kept together so the whole budget for the app can be read
 * in one place rather than hunted through the routes file.
 */
class RateLimitServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        /*
         * Sign-in attempts, per IP.
         *
         * Breeze already limits attempts per email+IP, which stops one account
         * being ground down. This is the other half: it stops one host spraying
         * a common password across many different addresses, which the per-email
         * limiter never sees because each attempt uses a fresh key.
         */
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(10)->by($request->ip()),
            Limit::perHour(40)->by($request->ip()),
        ]);

        // Password reset emails. Slow enough that the app cannot be turned into
        // a way of mailbombing somebody.
        RateLimiter::for('password-reset', fn (Request $request) => [
            Limit::perMinute(3)->by($request->ip()),
            Limit::perHour(10)->by($request->ip()),
        ]);

        /*
         * Anything that writes, per signed-in user. A yard clerk logs a handful
         * of readings a day, so this is far above real use — it exists to cap a
         * runaway script or a stolen session, not to pace anybody.
         */
        RateLimiter::for('writes', fn (Request $request) => Limit::perMinute(60)
            ->by($request->user()?->id ?: $request->ip()));

        // Sending mail from the settings screen. Tight, because it is the one
        // control in the app that makes it send to an arbitrary address.
        RateLimiter::for('test-email', fn (Request $request) => [
            Limit::perMinute(2)->by($request->user()?->id ?: $request->ip()),
            Limit::perHour(10)->by($request->user()?->id ?: $request->ip()),
        ]);

        // A ceiling over everything else, so no single host can flood the box.
        RateLimiter::for('global', fn (Request $request) => Limit::perMinute(300)
            ->by($request->user()?->id ?: $request->ip()));
    }
}
