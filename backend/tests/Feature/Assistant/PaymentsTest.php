<?php

use App\Models\Patient;
use App\Models\Payment;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Support\Carbon;

// T8-05: "Waiting to pay" and recording what the patient pays (FR-I.4, FR-I.5).

beforeEach(function () {
    $this->withHeader('Accept-Language', 'en');
    Carbon::setTestNow(Carbon::parse('2026-10-05 17:20', 'Africa/Cairo'));
    $this->assistant = User::factory()->assistant()->create(['name' => 'Amal Saad']);
    $this->mona = Patient::factory()->for(User::factory()->create(['name' => 'Mona Ali']))->create();
    $this->ahmed = Patient::factory()->for(User::factory()->create(['name' => 'Ahmed Hassan']))->create();
});

describe('GET /assistant/visits/unpaid', function () {
    beforeEach(function () {
        // Today: Mona owes 1500 (unpaid), Ahmed owes 300 of 800, Sara has paid in full.
        $this->monaVisit = Visit::factory()->for($this->mona)->create(['visit_date' => '2026-10-05', 'total_amount' => 1500, 'work_done' => 'SECRET-WORK']);
        $this->ahmedVisit = Visit::factory()->for($this->ahmed)->create(['visit_date' => '2026-10-05', 'total_amount' => 800]);
        Payment::factory()->for($this->ahmedVisit)->create(['amount' => 500]);
        $paidUp = Visit::factory()->create(['visit_date' => '2026-10-05', 'total_amount' => 600]);
        Payment::factory()->for($paidUp)->create(['amount' => 600]);
        // Another day.
        Visit::factory()->for($this->mona)->create(['visit_date' => '2026-10-04', 'total_amount' => 200]);
    });

    it("lists today's visits that still owe money, oldest first", function () {
        $this->actingAs($this->assistant)->getJson('/api/v1/assistant/visits/unpaid')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.patient.name', 'Mona Ali')
            ->assertJsonPath('data.0.total_amount', '1500.00')
            ->assertJsonPath('data.0.paid', '0.00')
            ->assertJsonPath('data.0.remaining', '1500.00')
            ->assertJsonPath('data.0.payment_status', 'unpaid')
            ->assertJsonPath('data.1.patient.name', 'Ahmed Hassan')
            ->assertJsonPath('data.1.remaining', '300.00')
            ->assertJsonPath('data.1.payment_status', 'partially_paid');
    });

    it('takes another date', function () {
        $this->actingAs($this->assistant)->getJson('/api/v1/assistant/visits/unpaid?date=2026-10-04')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.remaining', '200.00');

        $this->actingAs($this->assistant)->getJson('/api/v1/assistant/visits/unpaid?date=05-10-2026')
            ->assertJsonValidationErrors(['date']);
    });

    it('never sends the work done', function () {
        $content = $this->actingAs($this->assistant)->getJson('/api/v1/assistant/visits/unpaid')->getContent();

        expect($content)->not->toContain('SECRET')->not->toContain('work_done');
    });

    it('drops a visit from the list once it is paid in full', function () {
        $this->actingAs($this->assistant)->postJson("/api/v1/assistant/visits/{$this->ahmedVisit->id}/payments", ['amount' => 300])
            ->assertCreated();

        $this->actingAs($this->assistant)->getJson('/api/v1/assistant/visits/unpaid')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.patient.name', 'Mona Ali');
    });
});

describe('POST /assistant/visits/{id}/payments', function () {
    beforeEach(function () {
        $this->visit = Visit::factory()->for($this->mona)->create(['visit_date' => '2026-10-05', 'total_amount' => 1500, 'work_done' => 'SECRET-WORK']);
    });

    it('records a partial payment, stores the assistant and returns the new balance', function () {
        $this->actingAs($this->assistant)->postJson("/api/v1/assistant/visits/{$this->visit->id}/payments", [
            'amount' => 1000,
            'method' => 'card',
        ])
            ->assertCreated()
            ->assertJsonPath('message', 'Payment recorded.')
            ->assertJsonPath('data.paid', '1000.00')
            ->assertJsonPath('data.remaining', '500.00')
            ->assertJsonPath('data.payment_status', 'partially_paid')
            ->assertJsonPath('data.payments.0.method', 'card')
            ->assertJsonPath('data.payments.0.recorded_by_name', 'Amal Saad')
            ->assertJsonMissingPath('data.work_done');

        expect(Payment::sole()->recorded_by)->toBe($this->assistant->id);
    });

    it('refuses more than the remaining amount and zero', function () {
        $this->actingAs($this->assistant)->postJson("/api/v1/assistant/visits/{$this->visit->id}/payments", ['amount' => 1600])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount']);

        $this->actingAs($this->assistant)->postJson("/api/v1/assistant/visits/{$this->visit->id}/payments", ['amount' => 0])
            ->assertJsonValidationErrors(['amount']);

        expect(Payment::count())->toBe(0);
    });

    it('cannot change the total', function () {
        $this->actingAs($this->assistant)->postJson("/api/v1/assistant/visits/{$this->visit->id}/payments", [
            'amount' => 100, 'total_amount' => 50,
        ])->assertCreated();

        expect($this->visit->refresh()->total_amount)->toBe('1500.00');
    });
});

it('is for the assistant only', function (string $role) {
    $user = User::factory()->{$role}()->create();
    $visit = Visit::factory()->create(['total_amount' => 100]);

    $this->actingAs($user)->getJson('/api/v1/assistant/visits/unpaid')->assertForbidden();
    $this->actingAs($user)->postJson("/api/v1/assistant/visits/{$visit->id}/payments", ['amount' => 50])->assertForbidden();
})->with(['doctor', 'patient']);

it('keeps the assistant away from creating or editing visits', function () {
    $visit = Visit::factory()->create(['total_amount' => 100]);

    $this->actingAs($this->assistant)->postJson('/api/v1/doctor/visits', [])->assertForbidden();
    $this->actingAs($this->assistant)->putJson("/api/v1/doctor/visits/{$visit->id}", [])->assertForbidden();
    $this->actingAs($this->assistant)->postJson("/api/v1/doctor/visits/{$visit->id}/payments", ['amount' => 50])->assertForbidden();
});
