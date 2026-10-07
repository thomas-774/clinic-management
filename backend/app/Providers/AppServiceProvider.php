<?php

namespace App\Providers;

use Carbon\CarbonInterface;
use Closure;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Dates in JSON are ISO 8601 in the app time zone (Africa/Cairo), e.g.
        // 2026-10-06T17:00:00+03:00, instead of Laravel's default UTC string (§6.2).
        Date::serializeUsing(
            fn (CarbonInterface $date) => $date->copy()->setTimezone(config('app.timezone'))->toIso8601String(),
        );

        // NFR-P.2: a relation read without with() / load() throws outside
        // production, so the test suite catches every N+1 query.
        Model::preventLazyLoading(! $this->app->isProduction());

        $this->configureRateLimiting();

        // A deactivated account's tokens stop working at once, even before
        // they are revoked (FR-I.1).
        Sanctum::authenticateAccessTokensUsing(
            fn (PersonalAccessToken $token, bool $isValid) => $isValid && $token->tokenable?->is_active,
        );
    }

    /**
     * Per minute, numbers in config/clinic.php (§9.2, NFR-S.2): login per
     * account and IP, register per IP, writes and searches per user.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(config('clinic.rate_limits.login'))
            ->by(Str::lower((string) $request->input('login')).'|'.$request->ip())
            ->response($this->tooManyRequests('auth.throttle')));

        RateLimiter::for('register', fn (Request $request) => Limit::perMinute(config('clinic.rate_limits.register'))
            ->by($request->ip())
            ->response($this->tooManyRequests()));

        // Applied to whole route groups: reads pass, every write is counted.
        RateLimiter::for('writes', fn (Request $request) => $request->isMethodSafe()
            ? Limit::none()
            : Limit::perMinute(config('clinic.rate_limits.writes'))->by($request->user()?->id ?: $request->ip())
                ->response($this->tooManyRequests()));

        RateLimiter::for('search', fn (Request $request) => Limit::perMinute(config('clinic.rate_limits.search'))
            ->by($request->user()?->id ?: $request->ip())
            ->response($this->tooManyRequests()));
    }

    /**
     * 429 as { message } in the request language, with Retry-After.
     */
    private function tooManyRequests(string $message = 'Too many requests. Please try again in :seconds seconds.'): Closure
    {
        return fn (Request $request, array $headers) => response()->json(
            ['message' => __($message, ['seconds' => $headers['Retry-After'] ?? 60])],
            429,
            $headers,
        );
    }
}
