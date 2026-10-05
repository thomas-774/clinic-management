<?php

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Visit;
use App\Services\PaymentService;
use Illuminate\Support\Facades\DB;

/*
 * Quick checks for T5-01; the full case list is in PaymentServiceTest (T5-02).
 */

it('computes paid, remaining and status the same way with and without withPaid()', function () {
    $visit = Visit::factory()->create(['total_amount' => 1500]);
    Payment::factory()->for($visit)->create(['amount' => 1000]);
    $service = new PaymentService;

    $loaded = Visit::query()->withPaid()->find($visit->id);

    foreach ([$visit->fresh(), $loaded, $visit->fresh()->load('payments')] as $v) {
        expect($service->paid($v))->toBe('1000.00')
            ->and($service->remaining($v))->toBe('500.00')
            ->and($service->status($v))->toBe(PaymentStatus::PartiallyPaid);
    }
});

it('reads payments_sum_amount without querying payments again', function () {
    Visit::factory()->count(3)->has(Payment::factory()->count(2))->create();
    $visits = Visit::query()->withPaid()->get();
    $service = new PaymentService;

    DB::enableQueryLog();
    $visits->each(fn (Visit $v) => [$service->paid($v), $service->remaining($v), $service->status($v)]);

    expect(DB::getQueryLog())->toBe([]);
});

it('normalises amounts to two decimals', function (mixed $in, string $out) {
    expect(PaymentService::money($in))->toBe($out);
})->with([
    [1500, '1500.00'],
    ['1500', '1500.00'],
    ['1500.5', '1500.50'],
    [1500.5, '1500.50'],
    [0.1, '0.10'],
    [null, '0.00'],
]);
