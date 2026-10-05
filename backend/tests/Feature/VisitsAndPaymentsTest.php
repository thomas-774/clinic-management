<?php

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Support\Facades\DB;

/*
 * Visits and payments over HTTP (T5-06, §8 Phase 5, §9.1).
 * "Now" is Monday 2026-10-05 17:20 (Cairo).
 */

beforeEach(function () {
    $this->withHeader('Accept-Language', 'en');
    $this->travelTo('2026-10-05 17:20:00');
    $this->doctor = User::factory()->doctor()->create();
    $this->patient = Patient::factory()->create();
    $this->appointment = Appointment::factory()->for($this->doctor, 'doctor')->for($this->patient)
        ->at('2026-10-05 17:00')->checkedIn()->create();
});

function asDoctor()
{
    app('auth')->forgetGuards();

    return test()->actingAs(test()->doctor);
}

function createVisit(array $overrides = [])
{
    return asDoctor()->postJson('/api/v1/doctor/visits', [
        'patient_id' => test()->patient->id,
        'appointment_id' => test()->appointment->id,
        'work_done' => 'Root canal, session 1 of 2',
        'total_amount' => 1500,
        'paid_now' => 1000,
        ...$overrides,
    ]);
}

it('creates a visit 1500 / 1000 → 201, remaining 500, appointment completed', function () {
    createVisit()
        ->assertCreated()
        ->assertJsonPath('data.remaining', '500.00')
        ->assertJsonPath('data.payment_status', 'partially_paid');

    expect($this->appointment->fresh()->status)->toBe(AppointmentStatus::Completed);
});

it('adds a payment of 500 → paid', function () {
    $id = createVisit()->json('data.id');

    asDoctor()->postJson("/api/v1/doctor/visits/{$id}/payments", ['amount' => 500])
        ->assertCreated()
        ->assertJsonPath('data.remaining', '0.00')
        ->assertJsonPath('data.payment_status', 'paid');
});

it('rejects a payment of 600 on a 500 balance with 422', function () {
    $id = createVisit()->json('data.id');

    asDoctor()->postJson("/api/v1/doctor/visits/{$id}/payments", ['amount' => 600])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['amount']);
});

it('creates a walk-in visit without an appointment → 201', function () {
    createVisit(['appointment_id' => null, 'total_amount' => 300, 'paid_now' => 300])
        ->assertCreated()
        ->assertJsonPath('data.appointment_id', null)
        ->assertJsonPath('data.payment_status', 'paid');

    expect($this->appointment->fresh()->status)->toBe(AppointmentStatus::CheckedIn);
});

it('rejects a second visit for the same appointment with 422', function () {
    createVisit()->assertCreated();

    createVisit()
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['appointment_id']);

    expect(Visit::count())->toBe(1);
});

it('turns a second visit that slips past validation into 422 via the unique index', function () {
    // Another request saves a visit for the same appointment just before ours.
    Visit::creating(function (Visit $visit) {
        if ($visit->appointment_id && ! DB::table('visits')->where('appointment_id', $visit->appointment_id)->exists()) {
            DB::table('visits')->insert([
                'patient_id' => $visit->patient_id,
                'appointment_id' => $visit->appointment_id,
                'visit_date' => '2026-10-05',
                'work_done' => 'Saved by the other request',
                'total_amount' => '100.00',
            ]);
        }
    });

    createVisit()
        ->assertUnprocessable()
        ->assertJsonPath('errors.appointment_id.0', 'This appointment already has a visit.');
});

it('ignores remaining (and paid) sent in the body; the server computes them', function () {
    $id = createVisit(['remaining' => 0, 'paid' => 1500, 'payment_status' => 'paid'])
        ->assertCreated()
        ->assertJsonPath('data.paid', '1000.00')
        ->assertJsonPath('data.remaining', '500.00')
        ->assertJsonPath('data.payment_status', 'partially_paid')
        ->json('data.id');

    asDoctor()->putJson("/api/v1/doctor/visits/{$id}", ['work_done' => 'x', 'total_amount' => 1500, 'remaining' => 0])
        ->assertJsonPath('data.remaining', '500.00');
});

it('forbids a patient token on every /doctor/visits route (403)', function () {
    $visit = Visit::factory()->for($this->patient)->create();
    app('auth')->forgetGuards();
    $patient = $this->actingAs($this->patient->user);

    $patient->postJson('/api/v1/doctor/visits', [])->assertForbidden();
    $patient->putJson("/api/v1/doctor/visits/{$visit->id}", [])->assertForbidden();
    $patient->postJson("/api/v1/doctor/visits/{$visit->id}/payments", ['amount' => 1])->assertForbidden();
    $patient->putJson('/api/v1/doctor/visits/999999', [])->assertForbidden(); // role first: no id probing

    expect($visit->payments()->count())->toBe(0);
});

it('requires login', function () {
    $this->postJson('/api/v1/doctor/visits', [])->assertUnauthorized();
});
