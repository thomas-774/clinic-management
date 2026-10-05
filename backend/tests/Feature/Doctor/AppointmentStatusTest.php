<?php

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;

/*
 * PATCH /doctor/appointments/{id}/status (T4-04). "Now" is 2026-10-05 17:10.
 */

beforeEach(function () {
    $this->withHeader('Accept-Language', 'en');
    $this->travelTo('2026-10-05 17:10:00');
    $this->doctor = User::factory()->doctor()->create();
});

function withStatus(User $doctor, string $status): Appointment
{
    return Appointment::factory()->for($doctor, 'doctor')->at('2026-10-05 17:00')->create(['status' => $status]);
}

function patchStatus(User $doctor, Appointment $appointment, string $status)
{
    return test()->actingAs($doctor)->patchJson("/api/v1/doctor/appointments/{$appointment->id}/status", ['status' => $status]);
}

it('marks a booked patient as arrived and stamps checked_in_at', function () {
    $appointment = withStatus($this->doctor, 'booked');

    patchStatus($this->doctor, $appointment, 'checked_in')
        ->assertOk()
        ->assertJsonPath('message', 'Status updated.')
        ->assertJsonPath('data.status', 'checked_in')
        ->assertJsonPath('data.checked_in_at', '2026-10-05T17:10:00+03:00')
        ->assertJsonPath('data.patient.id', $appointment->patient_id);
});

it('marks a checked-in appointment as completed', function () {
    patchStatus($this->doctor, withStatus($this->doctor, 'checked_in'), 'completed')
        ->assertOk()
        ->assertJsonPath('data.status', 'completed');
});

it('marks a booked appointment as no-show', function () {
    $appointment = withStatus($this->doctor, 'booked');

    patchStatus($this->doctor, $appointment, 'no_show')->assertOk()->assertJsonPath('data.status', 'no_show');

    // A no-show no longer holds the slot.
    expect(Appointment::query()->whereKey($appointment->id)->value('active_slot'))->toBeNull();
});

it('lets the doctor cancel at any time, even after the patient cut-off', function () {
    $appointment = Appointment::factory()->for($this->doctor, 'doctor')->at('2026-10-05 17:45')->create();

    patchStatus($this->doctor, $appointment, 'cancelled')
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled')
        ->assertJsonPath('data.cancelled_at', '2026-10-05T17:10:00+03:00');
});

it('rejects a transition the lifecycle does not allow with 422', function (string $from, string $to) {
    $appointment = withStatus($this->doctor, $from);

    patchStatus($this->doctor, $appointment, $to)
        ->assertUnprocessable()
        ->assertJsonPath('errors.status.0', 'This status change is not allowed.');

    expect($appointment->fresh()->status->value)->toBe($from);
})->with([
    ['booked', 'completed'],
    ['checked_in', 'cancelled'],
    ['checked_in', 'no_show'],
    ['checked_in', 'checked_in'],
    ['completed', 'cancelled'],
    ['cancelled', 'checked_in'],
    ['no_show', 'checked_in'],
]);

it('only accepts the four target statuses', function (?string $status) {
    patchStatus($this->doctor, withStatus($this->doctor, 'booked'), $status ?? '')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
})->with(['booked', 'arrived', null]);

it("returns 404 for another doctor's appointment and 403 for a patient", function () {
    $other = Appointment::factory()->create();
    $mine = withStatus($this->doctor, 'booked');
    $patient = Patient::factory()->create();

    patchStatus($this->doctor, $other, 'checked_in')->assertNotFound();
    patchStatus($patient->user, $mine, 'checked_in')->assertForbidden();

    expect($mine->fresh()->status)->toBe(AppointmentStatus::Booked);
});
