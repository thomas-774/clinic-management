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

function putVisit(int $id, array $body)
{
    return test()->actingAs(test()->doctor)->putJson("/api/v1/doctor/visits/{$id}", $body);
}

function payVisit(int $id, array $body)
{
    return test()->actingAs(test()->doctor)->postJson("/api/v1/doctor/visits/{$id}/payments", $body);
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

describe('PUT /doctor/visits/{id}', function () {
    beforeEach(function () {
        $this->visitId = postVisit(visitBody())->assertCreated()->json('data.id'); // 1500 / 1000
    });

    it('updates work done and total and recalculates remaining', function () {
        putVisit($this->visitId, ['work_done' => 'Root canal, both sessions', 'total_amount' => '1800.50'])
            ->assertOk()
            ->assertJsonPath('message', 'Visit updated.')
            ->assertJsonPath('data.work_done', 'Root canal, both sessions')
            ->assertJsonPath('data.total_amount', '1800.50')
            ->assertJsonPath('data.paid', '1000.00')
            ->assertJsonPath('data.remaining', '800.50')
            ->assertJsonPath('data.payment_status', 'partially_paid')
            ->assertJsonCount(1, 'data.payments');
    });

    it('accepts lowering the total to exactly what was paid', function () {
        putVisit($this->visitId, ['work_done' => 'Done', 'total_amount' => 1000])
            ->assertOk()
            ->assertJsonPath('data.remaining', '0.00')
            ->assertJsonPath('data.payment_status', 'paid');
    });

    it('returns 422 when the total goes below the amount already paid', function () {
        putVisit($this->visitId, ['work_done' => 'Done', 'total_amount' => '999.99'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['total_amount' => 'The total cannot be lower than the 1000.00 EGP already paid.']);

        expect(Visit::find($this->visitId)->total_amount)->toBe('1500.00');
    });

    it('validates the fields and returns 404 for an unknown visit', function () {
        putVisit($this->visitId, ['total_amount' => 'abc'])->assertUnprocessable()->assertJsonValidationErrors(['work_done', 'total_amount']);
        putVisit(999999, ['work_done' => 'x', 'total_amount' => 1])->assertNotFound();
    });
});

describe('POST /doctor/visits/{id}/payments', function () {
    beforeEach(function () {
        // An old walk-in visit from last month: 1500 total, 1000 paid then.
        // (A visit for an appointment takes the appointment's date instead.)
        $this->travelTo('2026-09-10 18:00:00');
        $this->visitId = postVisit(visitBody(['appointment_id' => null]))->assertCreated()->json('data.id');
        $this->travelTo('2026-10-05 17:20:00');
    });

    it('records an installment on an old visit and lowers its remaining balance', function () {
        payVisit($this->visitId, ['amount' => 200, 'method' => 'card'])
            ->assertCreated()
            ->assertJsonPath('message', 'Payment recorded.')
            ->assertJsonPath('data.visit_date', '2026-09-10')
            ->assertJsonPath('data.paid', '1200.00')
            ->assertJsonPath('data.remaining', '300.00')
            ->assertJsonPath('data.payment_status', 'partially_paid')
            ->assertJsonCount(2, 'data.payments')
            ->assertJsonPath('data.payments.1.amount', '200.00')
            ->assertJsonPath('data.payments.1.method', 'card')
            ->assertJsonPath('data.payments.1.paid_at', '2026-10-05T17:20:00+03:00'); // counts in October (PR-4)
    });

    it('pays off the rest → paid', function () {
        payVisit($this->visitId, ['amount' => '500.00'])
            ->assertCreated()
            ->assertJsonPath('data.remaining', '0.00')
            ->assertJsonPath('data.payment_status', 'paid')
            ->assertJsonPath('data.payments.1.method', 'cash');
    });

    it('keeps a given paid_at', function () {
        payVisit($this->visitId, ['amount' => 100, 'paid_at' => '2026-10-01T12:00:00+03:00'])
            ->assertCreated()
            ->assertJsonPath('data.payments.1.paid_at', '2026-10-01T12:00:00+03:00');
    });

    it('rejects more than the remaining amount with 422', function () {
        payVisit($this->visitId, ['amount' => 600])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount' => 'The payment cannot be more than the remaining 500.00 EGP.']);

        expect(Visit::find($this->visitId)->payments()->count())->toBe(1);
    });

    it('validates the fields', function (array $body, string $field) {
        payVisit($this->visitId, $body)->assertUnprocessable()->assertJsonValidationErrors([$field]);
    })->with([
        [[], 'amount'],
        [['amount' => 0], 'amount'],
        [['amount' => -5], 'amount'],
        [['amount' => '1.234'], 'amount'],
        [['amount' => 10, 'method' => 'cheque'], 'method'],
        [['amount' => 10, 'paid_at' => '2026-10-06 10:00'], 'paid_at'],
    ]);

    it('returns 404 for an unknown visit', function () {
        payVisit(999999, ['amount' => 10])->assertNotFound();
    });
});
