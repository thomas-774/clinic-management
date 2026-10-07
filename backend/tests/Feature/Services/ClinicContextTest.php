<?php

use App\Models\DoctorSetting;
use App\Models\User;
use App\Support\ClinicContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/*
 * NFR-T.1 (T11-15): ClinicContext is the one place that finds the clinic.
 */

it('finds the clinic doctor, its id and its settings', function () {
    $doctor = User::factory()->doctor()->create();
    User::factory()->doctor()->create(); // a later doctor is not the clinic's
    User::factory()->assistant()->create();
    $doctor->doctorSetting()->create(['clinic_name' => 'Smile Dental', 'slot_duration_minutes' => 30]);

    $clinic = app(ClinicContext::class);

    expect($clinic->doctor()->is($doctor))->toBeTrue()
        ->and($clinic->doctorId())->toBe($doctor->id)
        ->and($clinic->settings()->clinic_name)->toBe('Smile Dental')
        ->and($clinic->settings()->slot_duration_minutes)->toBe(30);
});

it('falls back to the default settings when the doctor never saved any', function () {
    User::factory()->doctor()->create();

    $settings = app(ClinicContext::class)->settings();

    expect($settings)->toBeInstanceOf(DoctorSetting::class)
        ->and($settings->exists)->toBeFalse()
        ->and($settings->slot_duration_minutes)->toBe(45);
});

it('fails loudly when the clinic has no doctor yet', function () {
    app(ClinicContext::class)->doctor();
})->throws(ModelNotFoundException::class);

it('is resolved once per request or job and looks the doctor up once', function () {
    User::factory()->doctor()->create();

    $first = app(ClinicContext::class);
    $count = queryCount(function () use ($first) {
        $first->doctor();
        $first->doctorId();
        $first->doctor();
    });

    expect(app(ClinicContext::class))->toBe($first)
        ->and($count)->toBe(1);

    app()->forgetScopedInstances(); // what Octane and the queue worker do between requests / jobs

    expect(app(ClinicContext::class))->not->toBe($first);
});
