<?php

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use Database\Seeders\DoctorSeeder;

/*
 * T8-06: the assistant's schedule, booking and check-in (FR-I.6). Seeded
 * clinic: Sat–Thu 17:00–21:00, 45 minutes. "Now" is Monday 2026-10-05 08:00.
 */

beforeEach(function () {
    $this->withHeader('Accept-Language', 'en');
    config()->set('clinic.doctor', ['name' => 'Dr', 'phone' => '01000000000', 'email' => null, 'password' => 'password']);
    $this->seed(DoctorSeeder::class);
    $this->travelTo('2026-10-05 08:00:00');
    $this->doctor = User::clinicDoctor();
    $this->assistant = User::factory()->assistant()->create();
});

function deskAppointment(string $start, ?Patient $patient = null, string $status = 'booked'): Appointment
{
    return Appointment::factory()->for(User::clinicDoctor(), 'doctor')->for($patient ?? Patient::factory()->create())
        ->at($start)->create(['status' => $status]);
}

describe('GET /assistant/appointments', function () {
    it("lists the doctor's appointments for today, or a range, with patient name and phone", function () {
        $patient = Patient::factory()->create(['current_illness' => 'SECRET-ILLNESS']);
        $late = deskAppointment('2026-10-05 20:00', $patient);
        $early = deskAppointment('2026-10-05 17:00', status: 'cancelled');
        $later = deskAppointment('2026-10-07 17:00');

        $response = $this->actingAs($this->assistant)->getJson('/api/v1/assistant/appointments')->assertOk();

        expect($response->json('data.*.id'))->toBe([$early->id, $late->id])
            ->and($response->json('data.1.patient'))->toBe([
                'id' => $patient->id, 'name' => $patient->user->name, 'phone' => $patient->user->phone,
            ])
            ->and($response->getContent())->not->toContain('SECRET');

        $this->actingAs($this->assistant)->getJson('/api/v1/assistant/appointments?from=2026-10-05&to=2026-10-07')
            ->assertJsonPath('data.*.id', [$early->id, $late->id, $later->id]);

        $this->actingAs($this->assistant)->getJson('/api/v1/assistant/appointments?from=2026-10-07&to=2026-10-05')
            ->assertJsonValidationErrors(['to']);
    });
});

describe('POST /assistant/appointments', function () {
    it('books a free slot for a patient with the same rules as every booking', function () {
        $patient = Patient::factory()->create();

        $this->actingAs($this->assistant)->postJson('/api/v1/assistant/appointments', [
            'patient_id' => $patient->id,
            'start_at' => '2026-10-06T17:45:00+03:00',
        ])
            ->assertCreated()
            ->assertJsonPath('message', 'Appointment booked.')
            ->assertJsonPath('data.status', 'booked')
            ->assertJsonPath('data.patient.id', $patient->id);

        expect(Appointment::sole()->doctor_id)->toBe($this->doctor->id);
    });

    it('returns 409 for a taken slot or a time that is not a slot', function () {
        deskAppointment('2026-10-06 17:45');

        $this->actingAs($this->assistant)->postJson('/api/v1/assistant/appointments', [
            'patient_id' => Patient::factory()->create()->id,
            'start_at' => '2026-10-06T17:45:00+03:00',
        ])->assertConflict();

        $this->actingAs($this->assistant)->postJson('/api/v1/assistant/appointments', [
            'patient_id' => Patient::factory()->create()->id,
            'start_at' => '2026-10-06T17:10:00+03:00',
        ])->assertConflict();
    });
});

describe('PATCH /assistant/appointments/{id}/status', function () {
    it('marks Arrived, then Cancelled', function () {
        $appointment = deskAppointment('2026-10-05 17:00');

        $this->actingAs($this->assistant)->patchJson("/api/v1/assistant/appointments/{$appointment->id}/status", ['status' => 'checked_in'])
            ->assertOk()
            ->assertJsonPath('message', 'Status updated.')
            ->assertJsonPath('data.status', 'checked_in');

        expect($appointment->refresh()->checked_in_at)->not->toBeNull();

        $this->actingAs($this->assistant)->patchJson("/api/v1/assistant/appointments/{$appointment->id}/status", ['status' => 'cancelled'])
            ->assertJsonPath('data.status', 'cancelled');
    });

    it('marks No-show, and then refuses another change', function () {
        $appointment = deskAppointment('2026-10-05 17:00');

        $this->actingAs($this->assistant)->patchJson("/api/v1/assistant/appointments/{$appointment->id}/status", ['status' => 'no_show'])
            ->assertJsonPath('data.status', 'no_show');

        $this->actingAs($this->assistant)->patchJson("/api/v1/assistant/appointments/{$appointment->id}/status", ['status' => 'checked_in'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status' => 'This status change is not allowed.']);
    });

    it('cannot complete an appointment — only the doctor\'s visit does', function () {
        $appointment = deskAppointment('2026-10-05 17:00', status: 'checked_in');

        $this->actingAs($this->assistant)->patchJson("/api/v1/assistant/appointments/{$appointment->id}/status", ['status' => 'completed'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        expect($appointment->refresh()->status->value)->toBe('checked_in');
    });

    it("returns 404 for another doctor's appointment", function () {
        $other = Appointment::factory()->for(User::factory()->doctor()->create(), 'doctor')->at('2026-10-05 17:00')->create();

        $this->actingAs($this->assistant)->patchJson("/api/v1/assistant/appointments/{$other->id}/status", ['status' => 'checked_in'])
            ->assertNotFound();
    });
});

it('lets the assistant read free slots', function () {
    $this->actingAs($this->assistant)->getJson('/api/v1/slots?date=2026-10-06')
        ->assertOk()
        ->assertJsonPath('data.0.start_at', '2026-10-06T17:00:00+03:00');
});

it('is for the assistant only', function (string $role) {
    $user = User::factory()->{$role}()->create();
    $appointment = deskAppointment('2026-10-05 17:00');

    $this->actingAs($user)->getJson('/api/v1/assistant/appointments')->assertForbidden();
    $this->actingAs($user)->postJson('/api/v1/assistant/appointments', [])->assertForbidden();
    $this->actingAs($user)->patchJson("/api/v1/assistant/appointments/{$appointment->id}/status", ['status' => 'checked_in'])->assertForbidden();
})->with(['doctor', 'patient']);
