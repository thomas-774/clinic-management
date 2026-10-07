<?php

use App\Models\Appointment;
use App\Models\Patient;
use Database\Seeders\DoctorSeeder;

/*
 * Doctor schedule and booking on behalf of a patient (T4-03). Seeded clinic:
 * Sat–Thu 17:00–21:00, 45 minutes. "Now" is Monday 2026-10-05 08:00.
 */

beforeEach(function () {
    $this->withHeader('Accept-Language', 'en');
    config()->set('clinic.doctor', ['name' => 'Dr', 'phone' => '01000000000', 'email' => null, 'password' => 'password']);
    $this->seed(DoctorSeeder::class);
    $this->travelTo('2026-10-05 08:00:00');
    $this->doctor = clinicDoctor();
});

function scheduled(string $start, ?Patient $patient = null, string $status = 'booked'): Appointment
{
    return Appointment::factory()->for(clinicDoctor(), 'doctor')->for($patient ?? Patient::factory()->create())
        ->at($start)->create(['status' => $status]);
}

describe('GET /doctor/appointments', function () {
    it("lists today's appointments by time with patient name and phone", function () {
        $patient = Patient::factory()->create();
        $late = scheduled('2026-10-05 20:00', $patient);
        $early = scheduled('2026-10-05 17:00', status: 'cancelled');
        scheduled('2026-10-06 17:00'); // tomorrow
        scheduled('2026-10-04 20:15'); // yesterday

        $response = $this->actingAs($this->doctor)->getJson('/api/v1/doctor/appointments')->assertOk();

        expect($response->json('data.*.id'))->toBe([$early->id, $late->id])
            ->and($response->json('data.1.patient'))->toBe([
                'id' => $patient->id, 'name' => $patient->user->name, 'phone' => $patient->user->phone,
            ])
            ->and($response->json('data.1.status'))->toBe('booked')
            ->and($response->json('data.0.status'))->toBe('cancelled')
            ->and($response->json('data.1'))->not->toHaveKey('can_cancel');
    });

    it('lists an inclusive date range', function () {
        $a = scheduled('2026-10-05 17:00');
        $b = scheduled('2026-10-07 20:00');
        $c = scheduled('2026-10-11 17:00');
        scheduled('2026-10-12 17:00');

        $this->actingAs($this->doctor)->getJson('/api/v1/doctor/appointments?from=2026-10-05&to=2026-10-11')
            ->assertOk()
            ->assertJsonPath('data.*.id', [$a->id, $b->id, $c->id]);
    });

    it('defaults "to" to the "from" day', function () {
        $day = scheduled('2026-10-07 17:00');
        scheduled('2026-10-08 17:00');

        $this->actingAs($this->doctor)->getJson('/api/v1/doctor/appointments?from=2026-10-07')
            ->assertJsonPath('data.*.id', [$day->id]);
    });

    it('validates the range', function (string $query, string $field) {
        $this->actingAs($this->doctor)->getJson("/api/v1/doctor/appointments?{$query}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$field]);
    })->with([
        ['from=05-10-2026', 'from'],
        ['from=2026-10-07&to=2026-10-06', 'to'],
    ]);
});

describe('POST /doctor/appointments', function () {
    it('books a free slot for a patient', function () {
        $patient = Patient::factory()->create();

        $this->actingAs($this->doctor)->postJson('/api/v1/doctor/appointments', [
            'patient_id' => $patient->id,
            'start_at' => '2026-10-06T18:30:00+03:00',
        ])
            ->assertCreated()
            ->assertJsonPath('message', 'Appointment booked.')
            ->assertJsonPath('data.start_at', '2026-10-06T18:30:00+03:00')
            ->assertJsonPath('data.end_at', '2026-10-06T19:15:00+03:00')
            ->assertJsonPath('data.patient.id', $patient->id);

        $this->actingAs($this->doctor)->getJson('/api/v1/doctor/appointments?from=2026-10-06')
            ->assertJsonCount(1, 'data');
    });

    it('applies the same booking rules (409 taken slot, 422 second appointment)', function () {
        $patient = Patient::factory()->create();
        scheduled('2026-10-06 17:00');
        scheduled('2026-10-07 17:00', $patient);

        $this->actingAs($this->doctor)->postJson('/api/v1/doctor/appointments', ['patient_id' => Patient::factory()->create()->id, 'start_at' => '2026-10-06 17:00'])
            ->assertConflict();
        $this->actingAs($this->doctor)->postJson('/api/v1/doctor/appointments', ['patient_id' => $patient->id, 'start_at' => '2026-10-06 17:45'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['start_at']);
    });

    it('validates the patient and start time', function () {
        $this->actingAs($this->doctor)->postJson('/api/v1/doctor/appointments', ['patient_id' => 999999])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['patient_id', 'start_at']);
    });
});

it('is for the doctor only', function () {
    $patient = Patient::factory()->create();

    $this->actingAs($patient->user)->getJson('/api/v1/doctor/appointments')->assertForbidden();
    $this->actingAs($patient->user)->postJson('/api/v1/doctor/appointments', ['patient_id' => $patient->id, 'start_at' => '2026-10-06 17:00'])
        ->assertForbidden();
});
