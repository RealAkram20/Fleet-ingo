<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Turns away scripted form submissions without putting a puzzle in front of a
 * human. Two checks, both invisible:
 *
 *   1. A field a person never sees and therefore never fills in. Bots that
 *      auto-complete every input in the form give themselves away.
 *   2. How long the form was on screen. A person needs seconds to type an email
 *      and a password; a script posts immediately.
 *
 * Rejections look exactly like a wrong password, so a bot learns nothing about
 * why it failed.
 */
class Honeypot
{
    /** The field that must stay empty. Named to look worth filling in. */
    public const FIELD = 'contact_website';

    /** Holds when the form was rendered. */
    public const TIMER = 'form_started_at';

    /** Seconds a genuine person needs, at an absolute minimum. */
    public const MIN_SECONDS = 2;

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('POST')) {
            return $next($request);
        }

        $trap = $request->input(self::FIELD);
        $startedAt = (int) $request->input(self::TIMER);

        $filledTheTrap = is_string($trap) && trim($trap) !== '';
        $tooFast = $startedAt > 0 && (time() - $startedAt) < self::MIN_SECONDS;

        if ($filledTheTrap || $tooFast) {
            Log::warning('Honeypot rejected a form submission.', [
                'reason' => $filledTheTrap ? 'trap field filled' : 'submitted too fast',
                'path' => $request->path(),
                'ip' => $request->ip(),
            ]);

            return back()
                ->withInput($request->except([self::FIELD, 'password']))
                ->withErrors(['email' => trans('auth.failed')]);
        }

        return $next($request);
    }
}
