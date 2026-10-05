<?php

use App\Enums\AppointmentStatus as S;

/*
 * §4.3 lifecycle (T4-04): every one of the 25 from → to pairs.
 */

$allowed = [
    'booked → checked_in' => [S::Booked, S::CheckedIn],
    'booked → cancelled' => [S::Booked, S::Cancelled],
    'booked → no_show' => [S::Booked, S::NoShow],
    'checked_in → completed' => [S::CheckedIn, S::Completed],
    'checked_in → cancelled' => [S::CheckedIn, S::Cancelled], // left without a visit
];

$forbidden = [];
foreach (S::cases() as $from) {
    foreach (S::cases() as $to) {
        $key = "{$from->value} → {$to->value}";
        if (! isset($allowed[$key])) {
            $forbidden[$key] = [$from, $to];
        }
    }
}

it('allows', function (S $from, S $to) {
    expect($from->canTransitionTo($to))->toBeTrue();
})->with($allowed);

it('forbids', function (S $from, S $to) {
    expect($from->canTransitionTo($to))->toBeFalse();
})->with($forbidden);

it('covers all 25 pairs', function () use ($allowed, $forbidden) {
    expect(count($allowed) + count($forbidden))->toBe(25)
        ->and($forbidden)->toHaveCount(20);
});
