<?php

use App\Services\SlotService;
use Database\Seeders\DoctorSeeder;
use Illuminate\Support\Carbon;

it('generates the seeded 45-minute slots for a working day and none for Friday', function () {
    config()->set('clinic.doctor', ['name' => 'Dr', 'phone' => '01000000000', 'email' => null, 'password' => 'password']);
    $this->seed(DoctorSeeder::class);
    $this->travelTo('2026-10-05 08:00:00'); // Monday

    $service = SlotService::forClinic();
    $times = fn (string $date) => collect($service->generate(Carbon::parse($date)))->map(fn ($s) => $s[0]->format('H:i'))->all();

    expect($times('2026-10-06'))->toBe(['17:00', '17:45', '18:30', '19:15', '20:00'])
        ->and($times('2026-10-09'))->toBe([]) // Friday
        ->and($service->isAvailable(Carbon::parse('2026-10-06 17:45')))->toBeTrue()
        ->and($service->isAvailable(Carbon::parse('2026-10-06 17:50')))->toBeFalse()
        ->and(clinicDoctor()->isDoctor())->toBeTrue();
});
