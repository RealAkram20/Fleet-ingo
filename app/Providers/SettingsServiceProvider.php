<?php

namespace App\Providers;

use App\Support\Settings;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

/**
 * Pushes the admin-editable settings into Laravel's config at boot, so the rest
 * of the app keeps reading config() and never has to know these are editable.
 */
class SettingsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // No early return on a missing settings table: Settings::get() falls back
        // to config/ingo.php, so the app still boots with sane branding and
        // thresholds before the first migration has run.
        config([
            'app.name' => Settings::get('app_name'),
            'app.timezone' => Settings::get('timezone'),
        ]);

        date_default_timezone_set(config('app.timezone'));

        // Only take over the mailer once a host has actually been configured;
        // until then MAIL_MAILER from .env stands, which keeps mail in the log
        // rather than silently failing to send.
        if ($host = Settings::get('mail_host')) {
            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp.host' => $host,
                'mail.mailers.smtp.port' => (int) Settings::get('mail_port'),
                'mail.mailers.smtp.username' => Settings::get('mail_username'),
                'mail.mailers.smtp.password' => Settings::get('mail_password'),
                'mail.mailers.smtp.encryption' => Settings::get('mail_encryption') ?: null,
                'mail.mailers.smtp.scheme' => Settings::get('mail_encryption') === 'ssl' ? 'smtps' : 'smtp',
            ]);
        }

        config([
            'mail.from.address' => Settings::get('mail_from_address'),
            'mail.from.name' => Settings::get('mail_from_name'),
        ]);

        /*
         * A composer rather than View::share, because share() would freeze these
         * at boot: an admin saving new branding would still see the old values
         * on the page they are redirected to.
         *
         * The favicon files keep their names when regenerated, so brandVersion
         * is what actually busts the browser cache for a replaced icon.
         */
        View::composer('*', function ($view) {
            $view->with('branding', [
                'name' => Settings::get('app_name'),
                'tagline' => Settings::get('tagline'),
                'logo' => Settings::get('logo_path'),
                'icon' => Settings::get('icon_path'),
            ])->with('brandVersion', substr(md5((string) Settings::get('icon_path')), 0, 8));
        });
    }
}
