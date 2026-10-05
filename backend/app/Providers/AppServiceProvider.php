<?php

namespace App\Providers;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\ServiceProvider;

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
    }
}
