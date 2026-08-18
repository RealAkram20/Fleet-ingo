<?php

use App\Http\Middleware\BindSessionToDevice;
use App\Http\Middleware\Honeypot;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SecurityHeaders::class,
            BindSessionToDevice::class,
        ]);

        $middleware->alias([
            'honeypot' => Honeypot::class,
        ]);

        // Proxy headers are only trusted from the local reverse proxy, if one is
        // ever put in front. Trusting them from anywhere would let a client set
        // its own X-Forwarded-For and walk straight past every per-IP limit.
        $middleware->trustProxies(at: [
            '127.0.0.1',
            '::1',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
