<?php

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use Database\Seeders\DoctorSeeder;

beforeEach(function () {
    $this->withHeader('Accept-Language', 'en');
    config()->set('clinic.doctor', ['name' => 'Dr', 'phone' => '01000000000', 'email' => null, 'password' => 'password']);
    $this->seed(DoctorSeeder::class); // Sat–Thu 17:00–21:00, 45 minutes
    $this->travelTo('2026-10-05 08:00:00'); // Monday
    $this->patient = Patient::factory()->create();
});

it('returns the seeded 45-minute slots with start and end in Cairo time', function () {
    $this->actingAs($this->patient->user)->getJson('/api/v1/slots?date=2026-10-06')
        ->assertOk()
        ->assertJsonPath('meta', ['date' => '2026-10-06', 'duration' => 45])
        ->assertJsonCount(5, 'data')
        ->assertJsonPath('data.0', ['start_at' => '2026-10-06T17:00:00+03:00', 'end_at' => '2026-10-06T17:45:00+03:00'])
        ->assertJsonPath('data.4.start_at', '2026-10-06T20:00:00+03:00');
});

it('hides booked slots', function () {
    Appointment::factory()->for(User::clinicDoctor(), 'doctor')->for($this->patient)->at('2026-10-06 17:45')->create();

    $starts = $this->actingAs($this->patient->user)->getJson('/api/v1/slots?date=2026-10-06')->json('data.*.start_at');

    expect($starts)->not->toContain('2026-10-06T17:45:00+03:00')->toHaveCount(4);
});

it('returns an empty list on Friday', function () {
    $this->actingAs($this->patient->user)->getJson('/api/v1/slots?date=2026-10-09')
        ->assertOk()
        ->assertJsonPath('data', []);
});

it('is open to the doctor too', function () {
    $this->actingAs(User::clinicDoctor())->getJson('/api/v1/slots?date=2026-10-06')
        ->assertOk()
        ->assertJsonCount(5, 'data');
});

it('requires a valid date', function (?string $query) {
    $this->actingAs($this->patient->user)->getJson('/api/v1/slots'.($query ?? ''))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['date']);
})->with([null, '?date=', '?date=06-10-2026', '?date=2026-13-01', '?date=tomorrow']);

it('requires login', function () {
    $this->getJson('/api/v1/slots?date=2026-10-06')->assertUnauthorized();
});

it('returns no slots for a date the doctor blocks as a holiday (T3-08)', function () {
    $doctor = User::clinicDoctor();
    $this->actingAs($doctor)->getJson('/api/v1/slots?date=2026-10-06')->assertJsonCount(5, 'data');

    $this->actingAs($doctor)->postJson('/api/v1/doctor/blocked-times', ['date' => '2026-10-06', 'reason' => 'Holiday'])
        ->assertCreated();

    app('auth')->forgetGuards();
    $this->actingAs($this->patient->user)->getJson('/api/v1/slots?date=2026-10-06')
        ->assertOk()
        ->assertJsonPath('data', []);
});
