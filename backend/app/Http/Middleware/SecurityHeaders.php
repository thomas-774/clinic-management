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

    /**
     * API responses are data, never a page (ASVS 14.4.2, 14.4.3): a browser
     * that opens one directly saves it instead of rendering it.
     */
    public const API_CSP = "default-src 'none'; frame-ancestors 'none'";

    public const API_DISPOSITION = 'attachment; filename="api.json"';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->add(self::HEADERS);

        if ($request->is('api/*')) {
            $response->headers->set('Content-Security-Policy', self::API_CSP);
            // The visit file keeps its own name (T10-05).
            if (! $response->headers->has('Content-Disposition')) {
                $response->headers->set('Content-Disposition', self::API_DISPOSITION);
            }
        }

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
