<?php

/*
 * Defaults for everything the admin can change from Settings. A setting that is
 * missing or blank falls back to the value here, so the app always has a sane
 * configuration even on a fresh database.
 */

return [

    'branding' => [
        'app_name' => 'InGo Fleet Log',
        'tagline' => 'RIDER · MILEAGE · SERVICE TRACKING — HARARE OPS',
        'logo_path' => 'images/ingo-logo.png',
        'icon_path' => 'images/ingo-badge.png',
    ],

    'fleet' => [
        // Offered as the default when adding a bike.
        'default_service_interval_km' => 3000,
        'default_service_interval_months' => 6,

        // How close to due a bike must be before it is flagged.
        'due_soon_km' => 300,
        'due_soon_days' => 30,

        // How close to expiry a licence must be before it is flagged.
        'licence_warn_days' => 30,
    ],

    'operations' => [
        'timezone' => 'Africa/Harare',
    ],

    'mail' => [
        'host' => null,
        'port' => 587,
        'username' => null,
        'password' => null,
        'encryption' => 'tls',
        'from_address' => 'fleet@ingo.local',
        'from_name' => 'InGo Fleet Log',
    ],

];
