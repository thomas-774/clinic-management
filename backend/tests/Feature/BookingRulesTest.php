<?php

use App\Models\Appointment;
use App\Models\Patient;
use Database\Seeders\DoctorSeeder;
use Illuminate\Support\Facades\DB;

/*
 * Booking rules and double booking over HTTP (T4-05, §4.2, §9.1).
 * Seeded clinic: Sat–Thu 17:00–21:00, 45 minutes, 2 h cut-off.
 * "Now" is Monday 2026-10-05 08:00; Tuesday 2026-10-06 is a working day.
 */

beforeEach(function () {
    $this->withHeader('Accept-Language', 'en');
    config()->set('clinic.doctor', ['name' => 'Dr', 'phone' => '01000000000', 'email' => null, 'password' => 'password']);
    $this->seed(DoctorSeeder::class);
    $this->travelTo('2026-10-05 08:00:00');
    $this->doctor = clinicDoctor();
    $this->alice = Patient::factory()->create();
    $this->bob = Patient::factory()->create();
});

function bookAs(Patient $patient, string $startAt)
{
    app('auth')->forgetGuards();

    return test()->actingAs($patient->user)->postJson('/api/v1/patient/appointments', ['start_at' => $startAt]);
}

function cancelAs(Patient $patient, int $id)
{
    app('auth')->forgetGuards();

    return test()->actingAs($patient->user)->patchJson("/api/v1/patient/appointments/{$id}/cancel");
}

describe('BR-1 / BR-3: the slot', function () {
    it('books a valid slot with end_at = start + duration', function () {
        bookAs($this->alice, '2026-10-06 18:30')
            ->assertCreated()
            ->assertJsonPath('data.start_at', '2026-10-06T18:30:00+03:00')
            ->assertJsonPath('data.end_at', '2026-10-06T19:15:00+03:00');
    });

    it('uses the duration at booking time', function () {
        $this->doctor->doctorSetting->update(['slot_duration_minutes' => 60]);

        bookAs($this->alice, '2026-10-06 18:00')->assertCreated()->assertJsonPath('data.end_at', '2026-10-06T19:00:00+03:00');
    });

    it('returns 409 for a time that is not a generated slot', function () {
        bookAs($this->alice, '2026-10-06 17:10')->assertConflict()->assertJsonPath('message', 'Slot no longer available.');
    });

    it('returns 409 for a past slot or a day off', function (string $start) {
        bookAs($this->alice, $start)->assertConflict();
    })->with([
        'yesterday' => '2026-10-04 17:00',
        'earlier today' => '2026-10-05 07:00',
        'Friday (day off)' => '2026-10-09 17:00',
    ]);

    it('returns 409 for a slot that started a moment ago', function () {
        $this->travelTo('2026-10-06 17:01:00');

        bookAs($this->alice, '2026-10-06 17:00')->assertConflict();
        bookAs($this->alice, '2026-10-06 17:45')->assertCreated();
    });
});

describe('BR-4: one active future appointment', function () {
    it('returns 422 for a second future booking by the same patient', function () {
        bookAs($this->alice, '2026-10-06 17:00')->assertCreated();

        bookAs($this->alice, '2026-10-07 17:00')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'You already have an upcoming appointment.');
    });

    it('follows clinic.max_active_appointments', function () {
        config()->set('clinic.max_active_appointments', 2);

        bookAs($this->alice, '2026-10-06 17:00')->assertCreated();
        bookAs($this->alice, '2026-10-07 17:00')->assertCreated();
        bookAs($this->alice, '2026-10-08 17:00')->assertUnprocessable();
    });

    it('lets the patient book again after cancelling', function () {
        $id = bookAs($this->alice, '2026-10-06 17:00')->json('data.id');
        cancelAs($this->alice, $id)->assertOk();

        bookAs($this->alice, '2026-10-07 17:00')->assertCreated();
    });
});

describe('BR-2: no double booking', function () {
    it('gives the first patient 201 and the second 409 for the same slot', function () {
        bookAs($this->alice, '2026-10-06 17:00')->assertCreated();
        bookAs($this->bob, '2026-10-06 17:00')->assertConflict();

        expect(Appointment::count())->toBe(1);
    });

    it('turns a race lost at the unique index into 409, not 500', function () {
        // Bob's request passes the slot check, then Alice's row lands before Bob's insert.
        $alice = $this->alice;
        Appointment::creating(function (Appointment $appointment) use ($alice) {
            if ($appointment->patient_id !== $alice->id) {
                DB::table('appointments')->insert([
                    'doctor_id' => $appointment->doctor_id,
                    'patient_id' => $alice->id,
                    'start_at' => $appointment->start_at,
                    'end_at' => $appointment->end_at,
                    'status' => 'booked',
                ]);
            }
        });

        bookAs($this->bob, '2026-10-06 17:00')
            ->assertConflict()
            ->assertJsonPath('message', 'Slot no longer available.');

        expect($this->bob->appointments()->count())->toBe(0);
    });

    it('lets another patient rebook a cancelled slot', function () {
        $id = bookAs($this->alice, '2026-10-06 17:00')->json('data.id');
        cancelAs($this->alice, $id)->assertOk();

        bookAs($this->bob, '2026-10-06 17:00')->assertCreated();
    });
});

describe('BR-5: cancellation cut-off (2 h)', function () {
    beforeEach(function () {
        $this->appointmentId = bookAs($this->alice, '2026-10-06 17:00')->assertCreated()->json('data.id');
    });

    it('lets the patient cancel 3 h before the start', function () {
        $this->travelTo('2026-10-06 14:00:00');

        cancelAs($this->alice, $this->appointmentId)->assertOk()->assertJsonPath('data.status', 'cancelled');
    });

    it('lets the patient cancel exactly at the cut-off', function () {
        $this->travelTo('2026-10-06 15:00:00');

        cancelAs($this->alice, $this->appointmentId)->assertOk();
    });

    it('refuses 1 h before the start and after the start', function (string $now) {
        $this->travelTo($now);

        cancelAs($this->alice, $this->appointmentId)
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Too late to cancel online, please call the clinic.');
    })->with(['1 h before' => '2026-10-06 16:00:00', 'after the start' => '2026-10-06 17:05:00']);

    it('follows the doctor setting', function () {
        $this->doctor->doctorSetting->update(['cancel_cutoff_hours' => 4]);
        $this->travelTo('2026-10-06 14:00:00'); // 3 h before

        cancelAs($this->alice, $this->appointmentId)->assertUnprocessable();
    });

    it('still lets the doctor cancel inside the cut-off window', function () {
        $this->travelTo('2026-10-06 16:00:00');

        $this->actingAs($this->doctor)->patchJson("/api/v1/doctor/appointments/{$this->appointmentId}/status", ['status' => 'cancelled'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
    });
});
