<?php

use App\Providers\AppServiceProvider;
use App\Providers\RateLimitServiceProvider;
use App\Providers\SettingsServiceProvider;

return [
    AppServiceProvider::class,
    RateLimitServiceProvider::class,
    SettingsServiceProvider::class,
];
