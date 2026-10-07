<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security headers on every response, errors included (NFR-S.1, §9.2).
 * Responses for a logged-in user also get `no-store`, so medical data never
 * stays in the shared clinic PC's browser cache.
 */
class SecurityHeaders
{
    public const HEADERS = [
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy' => 'no-referrer',
        'X-Frame-Options' => 'DENY',
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
    ];

    public const HSTS = 'max-age=31536000; includeSubDomains';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->add(self::HEADERS);

        // auth:sanctum has resolved the user by now; guest routes never ask.
        if (Auth::guard('sanctum')->hasUser()) {
            $response->headers->set('Cache-Control', 'no-store, private');
            $response->headers->set('Pragma', 'no-cache');
        }

        if (! app()->environment('local', 'testing')) {
            $response->headers->set('Strict-Transport-Security', self::HSTS);
        }

        return $response;
    }
}
