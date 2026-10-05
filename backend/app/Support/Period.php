<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Report periods in clinic time (Africa/Cairo). The week starts on
 * config('clinic.week_start') — Saturday — so a Friday belongs to the week
 * that began the Saturday before.
 */
final class Period
{
    public const NAMES = ['day', 'week', 'month'];

    /**
     * [from, to] for the day / week / month that contains $ref (default now):
     * from = first second, to = last second of the period.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function for(string $period, ?CarbonInterface $ref = null): array
    {
        $ref = Carbon::instance($ref ?? Carbon::now())->setTimezone(config('app.timezone'));

        return match ($period) {
            'day' => [$ref->copy()->startOfDay(), $ref->copy()->endOfDay()],
            'week' => self::week($ref),
            'month' => [$ref->copy()->startOfMonth(), $ref->copy()->endOfMonth()],
            default => throw new InvalidArgumentException("Unknown period [{$period}]."),
        };
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private static function week(Carbon $ref): array
    {
        $start = config('clinic.week_start');
        $from = $ref->copy()->startOfDay()->subDays(($ref->dayOfWeek - $start + 7) % 7);

        return [$from, $from->copy()->addDays(6)->endOfDay()];
    }
}
