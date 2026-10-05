<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets the app locale from the Accept-Language header (§6.2) so validation
 * and error messages come back in the user's language. Arabic is the default.
 */
class SetLocale
{
    /**
     * Supported locales; the first one is the default.
     */
    public const LOCALES = ['ar', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale($request->getPreferredLanguage(self::LOCALES) ?? self::LOCALES[0]);

        return $next($request);
    }
}
