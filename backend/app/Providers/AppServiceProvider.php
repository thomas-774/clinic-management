<?php

namespace App\Providers;

use Carbon\CarbonInterface;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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

        $this->configureRateLimiting();
    }

    /**
     * Login: 5 attempts per minute per account and IP (§9.2).
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)
            ->by(Str::lower((string) $request->input('login')).'|'.$request->ip())
            ->response(fn (Request $request, array $headers) => response()->json(
                ['message' => __('auth.throttle', ['seconds' => $headers['Retry-After'] ?? 60])],
                429,
                $headers,
            )));
    }
}
