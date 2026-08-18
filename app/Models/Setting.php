<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Contracts\Encryption\DecryptException;

class Setting extends Model
{
    public const CACHE_KEY = 'ingo.settings';

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['key', 'value', 'is_encrypted'];

    protected function casts(): array
    {
        return ['is_encrypted' => 'boolean'];
    }

    /** Values that must never be readable straight out of the database. */
    public const SECRETS = ['mail_password'];

    /**
     * Every setting, decrypted, keyed by name. Cached because the layout reads
     * from this on every request.
     *
     * Deliberately not called all() — that is Eloquent's, and shadowing it
     * would break every ordinary query against this model.
     *
     * @return array<string, string|null>
     */
    public static function values(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            return static::query()->get()->mapWithKeys(function (Setting $setting) {
                $value = $setting->value;

                if ($setting->is_encrypted && $value !== null) {
                    try {
                        $value = Crypt::decryptString($value);
                    } catch (DecryptException) {
                        // A rotated APP_KEY makes old secrets unreadable. Treat
                        // that as "not set" rather than breaking every page.
                        $value = null;
                    }
                }

                return [$setting->key => $value];
            })->all();
        });
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $values = static::values();

        // A stored row is authoritative even when blank: an admin clearing the
        // tagline, or picking "no encryption", must not get the default back.
        return array_key_exists($key, $values) ? $values[$key] : $default;
    }

    public static function put(string $key, mixed $value): void
    {
        $encrypt = in_array($key, self::SECRETS, true);

        static::updateOrCreate(
            ['key' => $key],
            [
                'value' => ($encrypt && $value !== null && $value !== '') ? Crypt::encryptString($value) : $value,
                'is_encrypted' => $encrypt,
            ],
        );

        static::flush();
    }

    /** @param  array<string, mixed>  $values */
    public static function putMany(array $values): void
    {
        foreach ($values as $key => $value) {
            static::put($key, $value);
        }
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    protected static function booted(): void
    {
        static::saved(fn () => static::flush());
        static::deleted(fn () => static::flush());
    }
}
