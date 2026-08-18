<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Reads the admin-editable settings, falling back to config/ingo.php.
 *
 * Every accessor is safe to call before the settings table exists, because the
 * service provider that applies these runs during `migrate` too.
 */
class Settings
{
    /** Setting key => config/ingo.php path for its default. */
    public const MAP = [
        'app_name' => 'ingo.branding.app_name',
        'tagline' => 'ingo.branding.tagline',
        'logo_path' => 'ingo.branding.logo_path',
        'icon_path' => 'ingo.branding.icon_path',

        'default_service_interval_km' => 'ingo.fleet.default_service_interval_km',
        'default_service_interval_months' => 'ingo.fleet.default_service_interval_months',
        'due_soon_km' => 'ingo.fleet.due_soon_km',
        'due_soon_days' => 'ingo.fleet.due_soon_days',
        'licence_warn_days' => 'ingo.fleet.licence_warn_days',

        'timezone' => 'ingo.operations.timezone',

        'mail_host' => 'ingo.mail.host',
        'mail_port' => 'ingo.mail.port',
        'mail_username' => 'ingo.mail.username',
        'mail_password' => 'ingo.mail.password',
        'mail_encryption' => 'ingo.mail.encryption',
        'mail_from_address' => 'ingo.mail.from_address',
        'mail_from_name' => 'ingo.mail.from_name',
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        $fallback = $default ?? (isset(self::MAP[$key]) ? config(self::MAP[$key]) : null);

        if (! self::available()) {
            return $fallback;
        }

        return Setting::get($key, $fallback);
    }

    public static function int(string $key): int
    {
        return (int) self::get($key);
    }

    /** @return array<string, mixed> */
    public static function allWithDefaults(): array
    {
        $out = [];

        foreach (array_keys(self::MAP) as $key) {
            $out[$key] = self::get($key);
        }

        return $out;
    }

    /**
     * The settings table is missing during the first migrate, and during
     * `migrate:fresh` in tests. Anything reading settings has to survive that.
     */
    public static function available(): bool
    {
        try {
            return Schema::hasTable('settings');
        } catch (Throwable) {
            return false;
        }
    }
}
