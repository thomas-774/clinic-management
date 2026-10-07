<?php

use App\Models\Appointment;
use App\Models\Patient;
use Database\Seeders\DoctorSeeder;

/*
 * Patient appointment endpoints (T4-02). Seeded clinic: Sat–Thu 17:00–21:00,
 * 45 minutes, 2 h cancellation cut-off. "Now" is Monday 2026-10-05 08:00.
 */

beforeEach(function () {
    $this->withHeader('Accept-Language', 'en');
    config()->set('clinic.doctor', ['name' => 'Dr', 'phone' => '01000000000', 'email' => null, 'password' => 'password']);
    $this->seed(DoctorSeeder::class);
    $this->travelTo('2026-10-05 08:00:00');
    $this->doctor = clinicDoctor();
    $this->patient = Patient::factory()->create();
    $this->user = $this->patient->user;
});

function myAppointment(Patient $patient, string $start, string $state = 'booked'): Appointment
{
    $factory = Appointment::factory()->for(clinicDoctor(), 'doctor')->for($patient)->at($start);

    return ($state === 'booked' ? $factory : $factory->{$state}())->create();
}

describe('POST /patient/appointments', function () {
    it('books a free slot and returns 201 with the appointment', function () {
        $this->actingAs($this->user)->postJson('/api/v1/patient/appointments', ['start_at' => '2026-10-06T17:45:00+03:00'])
            ->assertCreated()
            ->assertJsonPath('message', 'Appointment booked.')
            ->assertJsonPath('data.start_at', '2026-10-06T17:45:00+03:00')
            ->assertJsonPath('data.end_at', '2026-10-06T18:30:00+03:00')
            ->assertJsonPath('data.status', 'booked')
            ->assertJsonPath('data.can_cancel', true)
            ->assertJsonMissingPath('data.patient');

        expect($this->patient->appointments()->count())->toBe(1);
    });

    it('requires start_at', function (array $body) {
        $this->actingAs($this->user)->postJson('/api/v1/patient/appointments', $body)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['start_at']);
    })->with([[[]], [['start_at' => 'soon']]]);

    it('returns 409 for a time that is not a free slot', function () {
        $this->actingAs($this->user)->postJson('/api/v1/patient/appointments', ['start_at' => '2026-10-06 17:10'])
            ->assertConflict()
            ->assertJsonPath('message', 'Slot no longer available.');
    });

    it('is for patients only', function () {
        $this->actingAs($this->doctor)->postJson('/api/v1/patient/appointments', ['start_at' => '2026-10-06 17:00'])
            ->assertForbidden();
    });
});

describe('GET /patient/appointments', function () {
    it('splits own appointments into upcoming (soonest first) and past (newest first)', function () {
        $next = myAppointment($this->patient, '2026-10-06 17:00');
        $old = myAppointment($this->patient, '2026-09-20 17:00', 'completed');
        $older = myAppointment($this->patient, '2026-09-10 17:00', 'noShow');
        $cancelled = myAppointment($this->patient, '2026-10-07 17:00', 'cancelled');
        myAppointment(Patient::factory()->create(), '2026-10-06 17:45'); // someone else's

        $response = $this->actingAs($this->user)->getJson('/api/v1/patient/appointments')->assertOk();

        expect($response->json('data.upcoming.*.id'))->toBe([$next->id])
            ->and($response->json('data.past.*.id'))->toBe([$cancelled->id, $old->id, $older->id])
            ->and($response->json('message'))->toBeNull();
    });

    it('tells the frontend whether each appointment can still be cancelled (BR-5)', function () {
        $this->travelTo('2026-10-06 14:30:00');
        $early = myAppointment($this->patient, '2026-10-06 17:00'); // 2.5 h ahead
        $late = myAppointment($this->patient, '2026-10-06 15:30');  // 1 h ahead
        $arrived = myAppointment($this->patient, '2026-10-06 14:00', 'checkedIn');

        $upcoming = collect($this->actingAs($this->user)->getJson('/api/v1/patient/appointments')->json('data.upcoming'))
            ->pluck('can_cancel', 'id');

        expect($upcoming->all())->toBe([$arrived->id => false, $late->id => false, $early->id => true]);
    });

    it('returns empty lists for a new patient', function () {
        $this->actingAs($this->user)->getJson('/api/v1/patient/appointments')
            ->assertOk()
            ->assertJsonPath('data', ['upcoming' => [], 'past' => []]);
    });
});

describe('PATCH /patient/appointments/{id}/cancel', function () {
    it('cancels an own booked appointment before the cut-off and frees the slot', function () {
        $appointment = myAppointment($this->patient, '2026-10-06 17:00');

        $this->actingAs($this->user)->patchJson("/api/v1/patient/appointments/{$appointment->id}/cancel")
            ->assertOk()
            ->assertJsonPath('message', 'Appointment cancelled.')
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.cancelled_at', '2026-10-05T08:00:00+03:00')
            ->assertJsonPath('data.can_cancel', false);

        $this->actingAs($this->user)->getJson('/api/v1/slots?date=2026-10-06')->assertJsonCount(5, 'data');
    });

    it('refuses after the cut-off with 422', function () {
        $appointment = myAppointment($this->patient, '2026-10-05 09:00'); // 1 h ahead

        $this->actingAs($this->user)->patchJson("/api/v1/patient/appointments/{$appointment->id}/cancel")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Too late to cancel online, please call the clinic.');

        expect($appointment->fresh()->status->value)->toBe('booked');
    });

    it('refuses an appointment that is not booked any more', function () {
        $appointment = myAppointment($this->patient, '2026-10-06 17:00', 'cancelled');

        $this->actingAs($this->user)->patchJson("/api/v1/patient/appointments/{$appointment->id}/cancel")
            ->assertUnprocessable();
    });

    it("returns 403 for someone else's appointment and 404 for an unknown id", function () {
        $theirs = myAppointment(Patient::factory()->create(), '2026-10-06 17:00');

        $this->actingAs($this->user)->patchJson("/api/v1/patient/appointments/{$theirs->id}/cancel")->assertForbidden();
        $this->actingAs($this->user)->patchJson('/api/v1/patient/appointments/999999/cancel')->assertNotFound();

        expect($theirs->fresh()->status->value)->toBe('booked');
    });
});

it('lets a patient book, list and cancel, then book again', function () {
    $id = $this->actingAs($this->user)->postJson('/api/v1/patient/appointments', ['start_at' => '2026-10-06T17:00:00+03:00'])
        ->assertCreated()->json('data.id');

    $this->actingAs($this->user)->getJson('/api/v1/patient/appointments')->assertJsonPath('data.upcoming.0.id', $id);
    $this->actingAs($this->user)->patchJson("/api/v1/patient/appointments/{$id}/cancel")->assertOk();
    $this->actingAs($this->user)->postJson('/api/v1/patient/appointments', ['start_at' => '2026-10-06T17:00:00+03:00'])
        ->assertCreated();
});
