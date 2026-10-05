<?php

use App\Models\Patient;
use App\Models\Payment;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Support\Carbon;

// T8-03: every payment stores who took the money (FR-I.5).

beforeEach(function () {
    $this->withHeader('Accept-Language', 'en');
    Carbon::setTestNow(Carbon::parse('2026-10-05 17:20', 'Africa/Cairo'));
    $this->doctor = User::factory()->doctor()->create(['name' => 'Dr. Karim']);
    $this->patient = Patient::factory()->create();
});

it('stores the doctor on the first payment of a new visit', function () {
    $this->actingAs($this->doctor)->postJson('/api/v1/doctor/visits', [
        'patient_id' => $this->patient->id,
        'work_done' => 'Filling',
        'total_amount' => 1500,
        'paid_now' => 1000,
    ])->assertCreated();

    expect(Payment::sole()->recorded_by)->toBe($this->doctor->id);
});

it('stores the doctor on an installment', function () {
    $visit = Visit::factory()->for($this->patient)->create(['total_amount' => 1500]);

    $this->actingAs($this->doctor)->postJson("/api/v1/doctor/visits/{$visit->id}/payments", ['amount' => 500])
        ->assertCreated();

    expect(Payment::sole()->recordedBy->is($this->doctor))->toBeTrue();
});

it('shows who recorded each payment in the payments report, null for older rows', function () {
    $assistant = User::factory()->assistant()->create(['name' => 'Amal Saad']);
    $visit = Visit::factory()->for($this->patient)->create(['visit_date' => '2026-10-05', 'total_amount' => 1500]);
    Payment::factory()->for($visit)->create(['amount' => 300, 'paid_at' => now()->subHours(2)]);
    Payment::factory()->for($visit)->create(['amount' => 1000, 'paid_at' => now()->subHour(), 'recorded_by' => $assistant->id]);

    $this->actingAs($this->doctor)->getJson('/api/v1/doctor/reports/payments?from=2026-10-05&to=2026-10-05')
        ->assertOk()
        ->assertJsonPath('data.0.paid', '1000.00')
        ->assertJsonPath('data.0.recorded_by_name', 'Amal Saad')
        ->assertJsonPath('data.1.recorded_by_name', null);
});

it('keeps the payment when the recording account is deleted', function () {
    $assistant = User::factory()->assistant()->create();
    $visit = Visit::factory()->for($this->patient)->create(['total_amount' => 1500]);
    $payment = Payment::factory()->for($visit)->create(['recorded_by' => $assistant->id]);

    $assistant->delete();

    expect($payment->refresh()->recorded_by)->toBeNull();
});
