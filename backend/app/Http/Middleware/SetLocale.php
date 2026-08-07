<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets the application locale from the request's `Accept-Language` header (ADR-49).
 *
 * `Accept-Language` rather than a header of this project's own invention: it is on the CORS
 * safelist, so it crosses the origin boundary without a preflight and without a change to
 * `config/cors.php`.
 *
 * The header is client input and is never handed to `App::setLocale()` as it arrives. It is
 * matched against a whitelist and anything else — an unknown language, a malformed value, no
 * header at all — leaves the locale configured in `config/app.php` untouched.
 */
class SetLocale
{
    /**
     * Locales this application has translations for. Not read from config: a locale belongs
     * here once `lang/<locale>/` exists, and that is a fact about the repository rather than
     * about the environment. `en` has no `lang/en/` because it IS the framework's own
     * language; `bg` and `fr` each have a hand-written partial file (ADR-49).
     *
     * `fr` was added together with `lang/fr/validation.php`, ahead of the frontend (ADR-50).
     * It is inert until `routing.ts` learns the locale: nothing sends `Accept-Language: fr`
     * before then, so this entry changes no existing response. It is listed here rather than
     * later so that `lang/fr/` and the whitelist can never disagree — that disagreement is
     * silent in both directions, and it is the trap `docs/pitfalls.md` describes.
     */
    private const SUPPORTED = ['bg', 'en', 'fr'];

    public function handle(Request $request, Closure $next): Response
    {
        // getLanguages() parses the whole header and returns the tags in q-value order, so a
        // browser's `bg-BG,bg;q=0.9,en;q=0.8` is honoured rather than only an exact `bg`.
        foreach ($request->getLanguages() as $language) {
            // Symfony normalises `bg-BG` to `bg_BG`; the region is dropped because the
            // translations are language-level.
            $candidate = strtolower(explode('_', $language)[0]);

            if (in_array($candidate, self::SUPPORTED, true)) {
                App::setLocale($candidate);

                return $next($request);
            }
        }

        return $next($request);
    }
}
