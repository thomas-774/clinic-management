<?php

use App\Models\Patient;
use App\Models\Payment;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Support\Facades\DB;

/*
 * Visits and balances on the doctor's and the patient's pages (T5-07).
 */

beforeEach(function () {
    $this->withHeader('Accept-Language', 'en');
    $this->doctor = User::factory()->doctor()->create();
    $this->patient = Patient::factory()->create();

    $visit = fn (string $date, string $total, array $paid = []) => tap(
        Visit::factory()->for($this->patient)->create(['visit_date' => $date, 'total_amount' => $total, 'work_done' => "Work on {$date}"]),
        fn (Visit $v) => collect($paid)->each(fn ($amount, $i) => Payment::factory()->for($v)->create(['amount' => $amount, 'paid_at' => "{$date} 18:0{$i}:00"])),
    );

    $this->old = $visit('2026-08-01', '1500.00', ['1000.00', '200.00']); // 300.00 left
    $this->paid = $visit('2026-09-01', '500.00', ['500.00']);          // paid
    $this->latest = $visit('2026-10-01', '750.50');                   // 750.50 left, unpaid
    Visit::factory()->create(['total_amount' => '9999.00']);           // someone else's
});

function doctorView()
{
    app('auth')->forgetGuards();

    return test()->actingAs(test()->doctor)->getJson('/api/v1/doctor/patients/'.test()->patient->id)->assertOk();
}

function patientView()
{
    app('auth')->forgetGuards();

    return test()->actingAs(test()->patient->user)->getJson('/api/v1/patient/profile')->assertOk();
}

describe('GET /doctor/patients/{id}', function () {
    it('lists visits newest first with payments, paid, remaining and status', function () {
        $response = doctorView();

        expect($response->json('data.visits.*.id'))->toBe([$this->latest->id, $this->paid->id, $this->old->id]);
        $response
            ->assertJsonPath('data.visits.2.work_done', 'Work on 2026-08-01')
            ->assertJsonPath('data.visits.2.total_amount', '1500.00')
            ->assertJsonPath('data.visits.2.paid', '1200.00')
            ->assertJsonPath('data.visits.2.remaining', '300.00')
            ->assertJsonPath('data.visits.2.payment_status', 'partially_paid')
            ->assertJsonCount(2, 'data.visits.2.payments')
            ->assertJsonPath('data.visits.2.payments.0.amount', '1000.00')
            ->assertJsonPath('data.visits.1.payment_status', 'paid')
            ->assertJsonPath('data.visits.0.payment_status', 'unpaid')
            ->assertJsonPath('data.outstanding_balance', '1050.50');
    });

    it('does not run more queries as visits grow (no N+1)', function () {
        $count = function () {
            DB::flushQueryLog();
            DB::enableQueryLog();
            doctorView();
            patientView();

            return count(DB::getQueryLog());
        };

        $count(); // warm-up: the first request does one-off lookups
        $before = $count();
        Visit::factory()->count(5)->for($this->patient)->has(Payment::factory()->count(2))->create();

        expect($count())->toBe($before);
    });
});

describe('GET /patient/profile', function () {
    it('shows the outstanding balance and the visits still owed, without work notes', function () {
        $response = patientView()
            ->assertJsonPath('data.outstanding_balance', '1050.50')
            ->assertJsonPath('data.unpaid_visits', [
                ['id' => $this->latest->id, 'visit_date' => '2026-10-01', 'remaining' => '750.50'],
                ['id' => $this->old->id, 'visit_date' => '2026-08-01', 'remaining' => '300.00'],
            ]);

        expect($response->getContent())->not->toContain('Work on')
            ->and($response->json('data'))->not->toHaveKey('visits');
    });

    it('has an empty list and 0.00 when everything is paid', function () {
        Payment::factory()->for($this->old)->create(['amount' => '300.00']);
        Payment::factory()->for($this->latest)->create(['amount' => '750.50']);

        patientView()
            ->assertJsonPath('data.outstanding_balance', '0.00')
            ->assertJsonPath('data.unpaid_visits', []);
    });
});

it('shows the same outstanding balance to the doctor and to the patient', function () {
    expect(doctorView()->json('data.outstanding_balance'))->toBe(patientView()->json('data.outstanding_balance'))->toBe('1050.50');

    // After an installment both move together.
    app('auth')->forgetGuards();
    $this->actingAs($this->doctor)->postJson("/api/v1/doctor/visits/{$this->old->id}/payments", ['amount' => 300])->assertCreated();

    expect(doctorView()->json('data.outstanding_balance'))->toBe(patientView()->json('data.outstanding_balance'))->toBe('750.50');
});

it('shows the patient the remaining amount right after the doctor saves a partially paid visit (T5-11)', function () {
    $this->travelTo('2026-10-05 17:00:00');
    app('auth')->forgetGuards();
    $this->actingAs($this->doctor)->postJson('/api/v1/doctor/visits', [
        'patient_id' => $this->patient->id,
        'work_done' => 'Crown',
        'total_amount' => 2000,
        'paid_now' => 1500,
    ])->assertCreated();

    patientView()
        ->assertJsonPath('data.outstanding_balance', '1550.50') // 1050.50 before + 500.00
        ->assertJsonPath('data.unpaid_visits.0.visit_date', '2026-10-05')
        ->assertJsonPath('data.unpaid_visits.0.remaining', '500.00');
});
