<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Response headers that shut down whole classes of attack in the browser.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Stops a browser second-guessing a declared content type, which is how
        // an uploaded "image" gets executed as script.
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // No framing at all: the app has no reason to be embedded, and this is
        // what defeats clickjacking.
        $response->headers->set('X-Frame-Options', 'DENY');

        // Do not leak the URL (which carries record ids) to third-party hosts.
        $response->headers->set('Referrer-Policy', 'same-origin');

        // Nothing here uses a camera, microphone or location.
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), interest-cohort=()');

        /*
         * Content-Security-Policy is the real XSS backstop: even if something
         * unescaped slipped through, an injected <script> has no way to run.
         *
         * 'unsafe-inline' is present for styles only — Tailwind is compiled to a
         * file, but Blade still emits a few inline style attributes. Scripts get
         * no such exemption, so inline JS is blocked outright.
         */
        $response->headers->set('Content-Security-Policy', implode('; ', [
            "default-src 'self'",
            "script-src 'self'",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "font-src 'self' https://fonts.gstatic.com data:",
            "img-src 'self' data:",
            "connect-src 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "object-src 'none'",
        ]));

        return $response;
    }
}
