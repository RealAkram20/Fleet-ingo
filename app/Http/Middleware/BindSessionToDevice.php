<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ties a signed-in session to the browser that created it.
 *
 * A stolen session cookie — lifted off a shared yard machine, or read out of a
 * proxy log — is worthless to anyone replaying it from a different browser: the
 * fingerprint will not match and the session is destroyed on the spot.
 *
 * Deliberately fingerprints the User-Agent and NOT the IP address. Riders and
 * clerks work on mobile data, where the IP changes constantly; binding to it
 * would sign people out several times an hour and teach them to ignore it.
 */
class BindSessionToDevice
{
    public const KEY = '_device';

    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return $next($request);
        }

        $fingerprint = $this->fingerprint($request);
        $stored = $request->session()->get(self::KEY);

        if ($stored === null) {
            // First authenticated request of this session — record the device.
            $request->session()->put(self::KEY, $fingerprint);

            return $next($request);
        }

        if (! hash_equals($stored, $fingerprint)) {
            Log::warning('Session fingerprint mismatch; signing the session out.', [
                'user_id' => Auth::id(),
                'ip' => $request->ip(),
            ]);

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['email' => 'You were signed out because your session was used from a different browser. Sign in again.']);
        }

        return $next($request);
    }

    private function fingerprint(Request $request): string
    {
        return hash_hmac('sha256', (string) $request->userAgent(), config('app.key'));
    }
}
