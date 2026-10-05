<?php

use App\Models\Patient;
use App\Models\User;
use App\Models\WorkingHour;

beforeEach(function () {
    $this->withHeader('Accept-Language', 'en');
    $this->doctor = User::factory()->doctor()->create();
});

/** Sat–Thu 17:00–21:00, Tuesday also 10:00–13:00, Friday off. */
function clinicWeek(): array
{
    return collect([6, 0, 1, 2, 3, 4])->map(fn (int $day) => [
        'day_of_week' => $day,
        'ranges' => $day === 2
            ? [['start_time' => '17:00', 'end_time' => '21:00'], ['start_time' => '10:00', 'end_time' => '13:00']]
            : [['start_time' => '17:00', 'end_time' => '21:00']],
    ])->push(['day_of_week' => 5, 'ranges' => []])->all();
}

it('returns 7 empty days when nothing is set', function () {
    $response = $this->actingAs($this->doctor)->getJson('/api/v1/doctor/working-hours')->assertOk();

    expect($response->json('data'))->toBe(collect(range(0, 6))->map(fn ($d) => ['day_of_week' => $d, 'ranges' => []])->all());
});

it('round-trips a week with a break on Tuesday and Friday off', function () {
    $this->actingAs($this->doctor)->putJson('/api/v1/doctor/working-hours', ['days' => clinicWeek()])
        ->assertOk()
        ->assertJsonPath('message', 'Working hours saved.');

    $week = $this->actingAs($this->doctor)->getJson('/api/v1/doctor/working-hours')->json('data');

    expect($week)->toHaveCount(7)
        ->and($week[2]['ranges'])->toBe([
            ['start_time' => '10:00', 'end_time' => '13:00'],
            ['start_time' => '17:00', 'end_time' => '21:00'],
        ])
        ->and($week[5]['ranges'])->toBe([])
        ->and($week[6]['ranges'])->toBe([['start_time' => '17:00', 'end_time' => '21:00']])
        ->and(WorkingHour::count())->toBe(7);
});

it('replaces the previous week entirely', function () {
    $this->actingAs($this->doctor)->putJson('/api/v1/doctor/working-hours', ['days' => clinicWeek()]);
    $this->actingAs($this->doctor)->putJson('/api/v1/doctor/working-hours', [
        'days' => [['day_of_week' => 1, 'ranges' => [['start_time' => '09:00', 'end_time' => '12:00']]]],
    ])->assertOk();

    expect(WorkingHour::count())->toBe(1)
        ->and(WorkingHour::first()->only(['day_of_week', 'start_time']))->toBe(['day_of_week' => 1, 'start_time' => '09:00:00']);
});

it('allows ranges that only touch', function () {
    $this->actingAs($this->doctor)->putJson('/api/v1/doctor/working-hours', ['days' => [[
        'day_of_week' => 1,
        'ranges' => [['start_time' => '10:00', 'end_time' => '13:00'], ['start_time' => '13:00', 'end_time' => '15:00']],
    ]]])->assertOk();
});

it('rejects an end before the start', function () {
    $this->actingAs($this->doctor)->putJson('/api/v1/doctor/working-hours', ['days' => [[
        'day_of_week' => 1, 'ranges' => [['start_time' => '21:00', 'end_time' => '17:00']],
    ]]])->assertUnprocessable()->assertJsonValidationErrors(['days.0.ranges.0.end_time']);
});

it('rejects overlapping ranges on the same day', function () {
    $this->actingAs($this->doctor)->putJson('/api/v1/doctor/working-hours', ['days' => [[
        'day_of_week' => 2,
        'ranges' => [['start_time' => '17:00', 'end_time' => '21:00'], ['start_time' => '10:00', 'end_time' => '17:30']],
    ]]])->assertUnprocessable()
        ->assertJsonValidationErrors(['days.0.ranges.0.start_time' => 'Time ranges on the same day must not overlap.']);

    expect(WorkingHour::count())->toBe(0);
});

it('rejects duplicate days, bad weekdays and bad times', function () {
    $this->actingAs($this->doctor)->putJson('/api/v1/doctor/working-hours', ['days' => [
        ['day_of_week' => 1, 'ranges' => []],
        ['day_of_week' => 1, 'ranges' => []],
        ['day_of_week' => 7, 'ranges' => [['start_time' => '25:00', 'end_time' => '5pm']]],
    ]])->assertUnprocessable()->assertJsonValidationErrors([
        'days.0.day_of_week', 'days.1.day_of_week', 'days.2.day_of_week',
        'days.2.ranges.0.start_time', 'days.2.ranges.0.end_time',
    ]);
});

it('is for the doctor only', function () {
    $this->actingAs(Patient::factory()->create()->user)
        ->putJson('/api/v1/doctor/working-hours', ['days' => []])
        ->assertForbidden();
});
