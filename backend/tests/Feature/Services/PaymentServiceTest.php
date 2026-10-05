<?php

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Visit;
use App\Services\PaymentService;
use Illuminate\Validation\ValidationException;

/*
 * PaymentService (T5-02): PR-1 – PR-3, PR-6 and FR-D.7, using the §8 Phase 5
 * example (total 1500, paid 1000).
 */

beforeEach(function () {
    app()->setLocale('en');
    $this->service = new PaymentService;
    $this->visit = Visit::factory()->create(['total_amount' => '1500.00']);
});

/** The visit as a list would load it. */
function reloaded(Visit $visit): Visit
{
    return Visit::query()->withPaid()->findOrFail($visit->id);
}

it('total 1500, paid 1000 → remaining 500, partially paid', function () {
    $this->service->addPayment($this->visit, 1000);

    $visit = reloaded($this->visit);
    expect($this->service->paid($visit))->toBe('1000.00')
        ->and($this->service->remaining($visit))->toBe('500.00')
        ->and($this->service->status($visit))->toBe(PaymentStatus::PartiallyPaid);
});

it('a second payment of 500 → remaining 0, paid', function () {
    $this->service->addPayment($this->visit, 1000);
    $this->service->addPayment($this->visit, '500', PaymentMethod::Card);

    $visit = reloaded($this->visit);
    expect($this->service->remaining($visit))->toBe('0.00')
        ->and($this->service->status($visit))->toBe(PaymentStatus::Paid)
        ->and($visit->payments()->pluck('method')->all())->toBe([PaymentMethod::Cash, PaymentMethod::Card]);
});

it('rejects a payment of 600 when 500 remains', function () {
    $this->service->addPayment($this->visit, 1000);

    expect(fn () => $this->service->addPayment($this->visit, 600))
        ->toThrow(ValidationException::class, 'The payment cannot be more than the remaining 500.00 EGP.');
    expect($this->visit->payments()->count())->toBe(1);
});

it('accepts a payment of exactly the remaining amount', function () {
    $this->service->addPayment($this->visit, '1499.99');

    expect(fn () => $this->service->addPayment($this->visit, '0.02'))->toThrow(ValidationException::class);
    $this->service->addPayment($this->visit, '0.01');
    expect($this->service->status(reloaded($this->visit)))->toBe(PaymentStatus::Paid);
});

it('rejects a payment of zero or a negative amount', function (string|int $amount) {
    expect(fn () => $this->service->addPayment($this->visit, $amount))
        ->toThrow(ValidationException::class, 'The payment must be more than zero.');
    expect($this->visit->payments()->count())->toBe(0);
})->with([0, '0.00', -100, '-0.01']);

it('rejects lowering the total below the amount already paid', function () {
    $this->service->addPayment($this->visit, 1000);
    $visit = reloaded($this->visit);

    expect(fn () => $this->service->assertTotalAllowed($visit, '999.99'))
        ->toThrow(ValidationException::class, 'The total cannot be lower than the 1000.00 EGP already paid.');

    $this->service->assertTotalAllowed($visit, 1000); // equal is fine
    $this->service->assertTotalAllowed($visit, 2000);
});

it('no payments → unpaid', function () {
    $visit = reloaded($this->visit);

    expect($this->service->paid($visit))->toBe('0.00')
        ->and($this->service->remaining($visit))->toBe('1500.00')
        ->and($this->service->status($visit))->toBe(PaymentStatus::Unpaid);
});

it('a free visit (total 0) counts as paid', function () {
    $visit = Visit::factory()->create(['total_amount' => 0]);

    expect($this->service->status(reloaded($visit)))->toBe(PaymentStatus::Paid);
});

it('adds 0.10 + 0.20 to exactly 0.30', function () {
    $visit = Visit::factory()->create(['total_amount' => '0.30']);
    $this->service->addPayment($visit, 0.1);
    $this->service->addPayment($visit, '0.20');

    $visit = reloaded($visit);
    expect($this->service->paid($visit))->toBe('0.30')
        ->and($this->service->remaining($visit))->toBe('0.00')
        ->and($this->service->status($visit))->toBe(PaymentStatus::Paid);
});

it('outstanding across 3 visits = the sum of their remaining amounts', function () {
    $patient = Patient::factory()->create();
    $a = Visit::factory()->for($patient)->create(['total_amount' => '1500.00']); // 500 left
    $b = Visit::factory()->for($patient)->create(['total_amount' => '750.50']);  // 750.50 left
    $c = Visit::factory()->for($patient)->create(['total_amount' => '300.00']);  // paid
    Payment::factory()->for($a)->create(['amount' => '1000.00']);
    Payment::factory()->for($c)->create(['amount' => '300.00']);
    Visit::factory()->create(['total_amount' => '9999.00']); // someone else's

    expect($this->service->outstandingFor($patient))->toBe('1250.50');
});

it('is 0.00 for a patient with no visits', function () {
    expect($this->service->outstandingFor(Patient::factory()->create()))->toBe('0.00');
});
