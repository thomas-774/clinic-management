<?php

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;

/*
 * Visits (T5-03 …). "Now" is Monday 2026-10-05 17:20 (Cairo).
 */

beforeEach(function () {
    $this->withHeader('Accept-Language', 'en');
    $this->travelTo('2026-10-05 17:20:00');
    $this->doctor = User::factory()->doctor()->create();
    $this->patient = Patient::factory()->create();
    $this->appointment = Appointment::factory()->for($this->doctor, 'doctor')->for($this->patient)
        ->at('2026-10-05 17:00')->checkedIn()->create();
});

function visitBody(array $overrides = []): array
{
    return [
        'patient_id' => test()->patient->id,
        'appointment_id' => test()->appointment->id,
        'work_done' => 'Root canal, session 1 of 2',
        'total_amount' => 1500.00,
        'paid_now' => 1000.00,
        ...$overrides,
    ];
}

function postVisit(array $body)
{
    return test()->actingAs(test()->doctor)->postJson('/api/v1/doctor/visits', $body);
}

describe('POST /doctor/visits', function () {
    it('returns the §6.4 example and completes the appointment', function () {
        $response = postVisit(visitBody())
            ->assertCreated()
            ->assertJsonPath('message', 'Visit saved.')
            ->assertJsonPath('data.patient_id', $this->patient->id)
            ->assertJsonPath('data.appointment_id', $this->appointment->id)
            ->assertJsonPath('data.visit_date', '2026-10-05')
            ->assertJsonPath('data.work_done', 'Root canal, session 1 of 2')
            ->assertJsonPath('data.total_amount', '1500.00')
            ->assertJsonPath('data.paid', '1000.00')
            ->assertJsonPath('data.remaining', '500.00')
            ->assertJsonPath('data.payment_status', 'partially_paid')
            ->assertJsonCount(1, 'data.payments')
            ->assertJsonPath('data.payments.0.amount', '1000.00')
            ->assertJsonPath('data.payments.0.method', 'cash')
            ->assertJsonPath('data.payments.0.paid_at', '2026-10-05T17:20:00+03:00');

        expect($this->appointment->fresh()->status)->toBe(AppointmentStatus::Completed)
            ->and(Visit::sole()->id)->toBe($response->json('data.id'));
    });

    it('records the payment method', function () {
        postVisit(visitBody(['method' => 'wallet']))->assertCreated()->assertJsonPath('data.payments.0.method', 'wallet');
    });

    it('creates no payment when nothing is paid now', function (?int $paidNow) {
        postVisit(visitBody(['paid_now' => $paidNow]))
            ->assertCreated()
            ->assertJsonPath('data.paid', '0.00')
            ->assertJsonPath('data.remaining', '1500.00')
            ->assertJsonPath('data.payment_status', 'unpaid')
            ->assertJsonCount(0, 'data.payments');
    })->with([0, null]);

    it('checks in a booked appointment on the way to completed (§4.3)', function () {
        $booked = Appointment::factory()->for($this->doctor, 'doctor')->for($this->patient)->at('2026-10-05 18:30')->create();

        postVisit(visitBody(['appointment_id' => $booked->id]))->assertCreated();

        $booked->refresh();
        expect($booked->status)->toBe(AppointmentStatus::Completed)
            ->and($booked->checked_in_at?->toDateTimeString())->toBe('2026-10-05 17:20:00');
    });

    it('rejects paying more than the total', function () {
        postVisit(visitBody(['paid_now' => 1500.01]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['paid_now' => 'The amount paid cannot be more than the total.']);

        expect(Visit::count())->toBe(0);
    });

    it('rejects an appointment of another patient, a closed one, or one with a visit', function (string $case) {
        $appointment = match ($case) {
            'other patient' => Appointment::factory()->for($this->doctor, 'doctor')->checkedIn()->create(),
            'cancelled' => Appointment::factory()->for($this->doctor, 'doctor')->for($this->patient)->cancelled()->create(),
            'no-show' => Appointment::factory()->for($this->doctor, 'doctor')->for($this->patient)->noShow()->create(),
            'has a visit' => tap($this->appointment, fn ($a) => Visit::factory()->for($this->patient)->create(['appointment_id' => $a->id])),
        };

        postVisit(visitBody(['appointment_id' => $appointment->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['appointment_id']);
    })->with(['other patient', 'cancelled', 'no-show', 'has a visit']);

    it('validates the fields', function (array $overrides, string $field) {
        postVisit(visitBody($overrides))->assertUnprocessable()->assertJsonValidationErrors([$field]);
    })->with([
        [['patient_id' => 999999], 'patient_id'],
        [['appointment_id' => 999999], 'appointment_id'],
        [['work_done' => ''], 'work_done'],
        [['total_amount' => null], 'total_amount'],
        [['total_amount' => -1], 'total_amount'],
        [['total_amount' => '12.345'], 'total_amount'],
        [['paid_now' => -5], 'paid_now'],
        [['method' => 'cheque'], 'method'],
    ]);
});
