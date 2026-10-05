<?php

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Support\Carbon;

/*
 * Report endpoints (T6-03). "Now" is Monday 2026-10-05 17:20 (Cairo), so the
 * week is Saturday 10-03 … Friday 10-09. Money counts on the visit's date and
 * the cards count visits (client decision, 2026-10-05).
 *
 *   visit  patient  date   total  payments                  remaining
 *   v1     A        10-05  1500   1000 @ 10-05 17:00         500
 *   v2     B        10-03   800    300 @ 10-03, 200 @ 10-05  300
 *   v3     C        10-01   600    600 @ 10-01               0
 *   v4     A        09-15   400    —                         400
 */

beforeEach(function () {
    $this->withHeader('Accept-Language', 'en');
    Carbon::setTestNow(Carbon::parse('2026-10-05 17:20', 'Africa/Cairo'));
    $this->doctor = User::factory()->doctor()->create();

    [$a, $b, $c] = Patient::factory()->count(3)->create();
    $a->user->update(['name' => 'Ahmed Hassan']);
    $this->a = $a;

    $paid = fn (Visit $v, $amount, string $at) => Payment::factory()->for($v)->create([
        'amount' => $amount, 'paid_at' => Carbon::parse($at, 'Africa/Cairo'),
    ]);

    $this->v1 = Visit::factory()->for($a)->create(['visit_date' => '2026-10-05', 'total_amount' => 1500]);
    $paid($this->v1, 1000, '2026-10-05 17:00');
    $this->v2 = Visit::factory()->for($b)->create(['visit_date' => '2026-10-03', 'total_amount' => 800]);
    $paid($this->v2, 300, '2026-10-03 10:00');
    $paid($this->v2, 200, '2026-10-05 09:00');
    $this->v3 = Visit::factory()->for($c)->create(['visit_date' => '2026-10-01', 'total_amount' => 600]);
    $paid($this->v3, 600, '2026-10-01 12:00');
    Visit::factory()->for($a)->create(['visit_date' => '2026-09-15', 'total_amount' => 400]);
});

afterEach(fn () => Carbon::setTestNow());

describe('GET /doctor/reports/summary', function () {
    it('gives visits, revenue and outstanding for each period', function (string $period, string $from, string $to, int $visits, string $revenue) {
        $this->actingAs($this->doctor)->getJson("/api/v1/doctor/reports/summary?period={$period}")
            ->assertOk()
            ->assertExactJson(['data' => [
                'period' => $period,
                'from' => $from,
                'to' => $to,
                'visits' => $visits,
                'revenue' => $revenue,
                'outstanding' => '1200.00',
            ], 'message' => null]);
    })->with([
        'today' => ['day', '2026-10-05', '2026-10-05', 1, '1000.00'],
        'this week' => ['week', '2026-10-03', '2026-10-09', 2, '1500.00'],
        'this month' => ['month', '2026-10-01', '2026-10-31', 3, '2100.00'],
    ]);

    it('updates today\'s revenue after a payment is recorded', function () {
        $this->actingAs($this->doctor)
            ->postJson("/api/v1/doctor/visits/{$this->v1->id}/payments", ['amount' => 250])
            ->assertCreated();

        $this->getJson('/api/v1/doctor/reports/summary?period=day')
            ->assertJsonPath('data.revenue', '1250.00')
            ->assertJsonPath('data.outstanding', '950.00');
    });

    it('rejects a missing or unknown period with 422', function (string $query) {
        $this->actingAs($this->doctor)->getJson("/api/v1/doctor/reports/summary{$query}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('period');
    })->with(['', '?period=year', '?period=']);

    it('counts a visit for a 24-10 appointment on 24-10, not on the day it was recorded', function () {
        $appointment = Appointment::factory()->for($this->a)->checkedIn()->at('2026-10-24 17:45')->create();

        $this->actingAs($this->doctor)->postJson('/api/v1/doctor/visits', [
            'patient_id' => $this->a->id,
            'appointment_id' => $appointment->id,
            'work_done' => 'Filling',
            'total_amount' => 5000,
            'paid_now' => 5000,
        ])->assertCreated()->assertJsonPath('data.visit_date', '2026-10-24');

        $this->getJson('/api/v1/doctor/reports/summary?period=day')
            ->assertJsonPath('data.revenue', '1000.00')
            ->assertJsonPath('data.visits', 1);
        $this->getJson('/api/v1/doctor/reports/daily-revenue?month=2026-10')
            ->assertJsonPath('data.23', ['date' => '2026-10-24', 'revenue' => '5000.00']);
    });
});

describe('GET /doctor/reports/payments', function () {
    it('lists the range\'s payments newest first with totals', function () {
        $res = $this->actingAs($this->doctor)
            ->getJson('/api/v1/doctor/reports/payments?from=2026-10-01&to=2026-10-31')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'paid_at', 'patient_id', 'patient_name', 'visit_id', 'visit_date', 'visit_total', 'paid', 'remaining']],
                'links', 'meta' => ['current_page', 'last_page', 'total', 'range', 'totals'], 'message',
            ])
            ->assertJsonPath('meta.range', ['from' => '2026-10-01', 'to' => '2026-10-31'])
            ->assertJsonPath('meta.totals', ['count' => 4, 'paid' => '2100.00', 'remaining' => '800.00']);

        expect($res->json('data.*.paid'))->toBe(['1000.00', '200.00', '300.00', '600.00'])
            ->and($res->json('data.0'))->toMatchArray([
                'patient_id' => $this->a->id,
                'patient_name' => 'Ahmed Hassan',
                'visit_id' => $this->v1->id,
                'visit_date' => '2026-10-05',
                'visit_total' => '1500.00',
                'remaining' => '500.00',
                'paid_at' => '2026-10-05T17:00:00+03:00',
            ]);
    });

    it('defaults to today', function () {
        $this->actingAs($this->doctor)->getJson('/api/v1/doctor/reports/payments')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.range', ['from' => '2026-10-05', 'to' => '2026-10-05'])
            ->assertJsonPath('meta.totals.paid', '1000.00');
    });

    it('includes both end dates', function () {
        $this->actingAs($this->doctor)->getJson('/api/v1/doctor/reports/payments?from=2026-10-03&to=2026-10-05')
            ->assertJsonPath('meta.totals.count', 3);
    });

    it('pages 20 rows but totals the whole range', function () {
        $visit = Visit::factory()->create(['visit_date' => '2026-10-02', 'total_amount' => 5000]);
        Payment::factory()->for($visit)->count(21)->create([
            'amount' => 10, 'paid_at' => Carbon::parse('2026-10-02 10:00', 'Africa/Cairo'),
        ]);

        $this->actingAs($this->doctor)->getJson('/api/v1/doctor/reports/payments?from=2026-10-01&to=2026-10-31')
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('meta.total', 25)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.totals.count', 25)
            ->assertJsonPath('meta.totals.paid', '2310.00');
    });

    it('rejects bad dates with 422', function (string $query, string $field) {
        $this->actingAs($this->doctor)->getJson("/api/v1/doctor/reports/payments?{$query}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);
    })->with([
        'to before from' => ['from=2026-10-05&to=2026-10-01', 'to'],
        'not a date' => ['from=05/10/2026', 'from'],
    ]);
});

describe('GET /doctor/reports/outstanding', function () {
    it('lists each patient who owes money, largest balance first, with the total', function () {
        $b = $this->v2->patient;

        $this->actingAs($this->doctor)->getJson('/api/v1/doctor/reports/outstanding')
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    ['patient_id' => $this->a->id, 'patient_name' => 'Ahmed Hassan', 'phone' => $this->a->user->phone, 'outstanding' => '900.00', 'unpaid_visits' => 2, 'oldest_visit_date' => '2026-09-15'],
                    ['patient_id' => $b->id, 'patient_name' => $b->user->name, 'phone' => $b->user->phone, 'outstanding' => '300.00', 'unpaid_visits' => 1, 'oldest_visit_date' => '2026-10-03'],
                ],
                'meta' => ['total' => '1200.00'],
                'message' => null,
            ]);
    });

    it('drops a patient once they have paid everything', function () {
        $this->actingAs($this->doctor)->postJson("/api/v1/doctor/visits/{$this->v2->id}/payments", ['amount' => 300])->assertCreated();

        $this->getJson('/api/v1/doctor/reports/outstanding')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.patient_name', 'Ahmed Hassan')
            ->assertJsonPath('meta.total', '900.00');
    });

    it('is empty when nobody owes anything', function () {
        Payment::query()->delete();
        Visit::query()->delete();

        $this->actingAs($this->doctor)->getJson('/api/v1/doctor/reports/outstanding')
            ->assertExactJson(['data' => [], 'meta' => ['total' => '0.00'], 'message' => null]);
    });
});

describe('GET /doctor/reports/daily-revenue', function () {
    it('gives every day of the month with 0.00 on quiet days', function () {
        $res = $this->actingAs($this->doctor)->getJson('/api/v1/doctor/reports/daily-revenue?month=2026-10')
            ->assertOk()
            ->assertJsonCount(31, 'data')
            ->assertJsonPath('meta.month', '2026-10');

        $byDate = collect($res->json('data'))->pluck('revenue', 'date');
        expect($byDate['2026-10-01'])->toBe('600.00')
            ->and($byDate['2026-10-02'])->toBe('0.00')
            ->and($byDate['2026-10-03'])->toBe('500.00') // v2, including the 200 paid on 10-05
            ->and($byDate['2026-10-05'])->toBe('1000.00')
            ->and($byDate->reduce(fn ($s, $r) => bcadd($s, $r, 2), '0.00'))->toBe('2100.00');
    });

    it('defaults to this month', function () {
        $this->actingAs($this->doctor)->getJson('/api/v1/doctor/reports/daily-revenue')
            ->assertJsonPath('meta.month', '2026-10')
            ->assertJsonPath('data.0', ['date' => '2026-10-01', 'revenue' => '600.00']);
    });

    it('returns all zeros for a month without payments', function () {
        $res = $this->actingAs($this->doctor)->getJson('/api/v1/doctor/reports/daily-revenue?month=2026-02');

        expect($res->json('data'))->toHaveCount(28)
            ->and(collect($res->json('data'))->pluck('revenue')->unique()->all())->toBe(['0.00']);
    });

    it('rejects a bad month with 422', function (string $month) {
        $this->actingAs($this->doctor)->getJson("/api/v1/doctor/reports/daily-revenue?month={$month}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('month');
    })->with(['2026-13', '10-2026', '2026-10-05']);
});

describe('permissions', function () {
    it('forbids patients', function (string $uri) {
        $this->actingAs($this->a->user)->getJson($uri)->assertForbidden();
    })->with([
        '/api/v1/doctor/reports/summary?period=day',
        '/api/v1/doctor/reports/payments',
        '/api/v1/doctor/reports/daily-revenue',
        '/api/v1/doctor/reports/outstanding',
    ]);

    it('needs a token', function () {
        $this->getJson('/api/v1/doctor/reports/summary?period=day')->assertUnauthorized();
    });
});
