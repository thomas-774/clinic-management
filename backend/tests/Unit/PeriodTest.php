<?php

use App\Support\Period;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/*
 * T6-01: report periods in Cairo time, weeks starting on Saturday.
 */

uses(TestCase::class);

function cairo(string $at): Carbon
{
    return Carbon::parse($at, 'Africa/Cairo');
}

function span(array $period): array
{
    return [$period[0]->format('Y-m-d H:i:s'), $period[1]->format('Y-m-d H:i:s')];
}

it('reads the week start and currency from config/clinic.php', function () {
    expect(config('clinic.week_start'))->toBe(Carbon::SATURDAY)
        ->and(config('clinic.currency'))->toBe('EGP');
});

it('gives the whole day', function () {
    expect(span(Period::for('day', cairo('2026-10-05 17:20'))))
        ->toBe(['2026-10-05 00:00:00', '2026-10-05 23:59:59']);
});

it('puts a Friday in the week that started the previous Saturday', function () {
    // Friday 2026-10-09 → Saturday 2026-10-03 … Friday 2026-10-09.
    expect(span(Period::for('week', cairo('2026-10-09 22:00'))))
        ->toBe(['2026-10-03 00:00:00', '2026-10-09 23:59:59']);
});

it('starts a new week on Saturday', function () {
    expect(span(Period::for('week', cairo('2026-10-10 00:00'))))
        ->toBe(['2026-10-10 00:00:00', '2026-10-16 23:59:59']);
});

it('handles a week that crosses a month end', function () {
    // Tuesday 2026-11-03 → Saturday 2026-10-31.
    expect(span(Period::for('week', cairo('2026-11-03 09:00'))))
        ->toBe(['2026-10-31 00:00:00', '2026-11-06 23:59:59']);
});

it('ends the month on the 31st and starts the next on the 1st', function () {
    expect(span(Period::for('month', cairo('2026-10-31 23:59:59'))))
        ->toBe(['2026-10-01 00:00:00', '2026-10-31 23:59:59'])
        ->and(span(Period::for('month', cairo('2026-11-01 00:00:00'))))
        ->toBe(['2026-11-01 00:00:00', '2026-11-30 23:59:59']);
});

it('handles February', function () {
    expect(span(Period::for('month', cairo('2028-02-10'))))
        ->toBe(['2028-02-01 00:00:00', '2028-02-29 23:59:59']);
});

it('uses Cairo time even when the reference is in UTC', function () {
    // 22:30 UTC on Oct 31 is already Nov 1 in Cairo (UTC+2).
    $ref = Carbon::parse('2026-10-31 22:30', 'UTC');

    [$from, $to] = Period::for('month', $ref);

    expect($from->timezoneName)->toBe('Africa/Cairo')
        ->and(span([$from, $to]))->toBe(['2026-11-01 00:00:00', '2026-11-30 23:59:59']);
});

it('defaults to now', function () {
    Carbon::setTestNow(cairo('2026-10-05 17:20'));

    expect(span(Period::for('day')))->toBe(['2026-10-05 00:00:00', '2026-10-05 23:59:59']);

    Carbon::setTestNow();
});

it('rejects an unknown period', function () {
    Period::for('year');
})->throws(InvalidArgumentException::class);
