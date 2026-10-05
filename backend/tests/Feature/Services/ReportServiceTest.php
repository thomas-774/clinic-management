<?php

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Visit;
use App\Services\ReportService;
use App\Support\Period;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/*
 * ReportService (T6-02): PR-4 revenue by payment date, PR-5 patients seen,
 * outstanding, the payments table and the daily revenue series.
 */

beforeEach(function () {
    $this->reports = app(ReportService::class);
});

function payOn(Visit $visit, string|int $amount, string $paidAt): Payment
{
    return Payment::factory()->for($visit)->create([
        'amount' => $amount,
        'paid_at' => Carbon::parse($paidAt, 'Africa/Cairo'),
    ]);
}

function monthOf(string $date): array
{
    return Period::for('month', Carbon::parse($date, 'Africa/Cairo'));
}

describe('revenue (PR-4)', function () {
    it('counts an installment paid in November in November, not in the visit month', function () {
        $visit = Visit::factory()->create(['visit_date' => '2026-10-20', 'total_amount' => 1500]);
        payOn($visit, 1000, '2026-10-20 18:00');
        payOn($visit, 500, '2026-11-03 11:00');

        expect($this->reports->revenue(...monthOf('2026-10-01')))->toBe('1000.00')
            ->and($this->reports->revenue(...monthOf('2026-11-01')))->toBe('500.00');
    });

    it('includes 23:59:59 on the last day and 00:00 on the first, and nothing outside', function () {
        $visit = Visit::factory()->create(['total_amount' => 5000]);
        payOn($visit, 100, '2026-09-30 23:59:59'); // September
        payOn($visit, 200, '2026-10-01 00:00:00'); // October, first second
        payOn($visit, 300, '2026-10-31 23:59:59'); // October, last second
        payOn($visit, 400, '2026-11-01 00:00:00'); // November

        expect($this->reports->revenue(...monthOf('2026-10-15')))->toBe('500.00');
    });

    it('splits a day at midnight', function () {
        $visit = Visit::factory()->create(['total_amount' => 5000]);
        payOn($visit, 100, '2026-10-05 23:59:59');
        payOn($visit, 200, '2026-10-06 00:00:00');

        expect($this->reports->revenue(...Period::for('day', Carbon::parse('2026-10-05 12:00', 'Africa/Cairo'))))->toBe('100.00')
            ->and($this->reports->revenue(...Period::for('day', Carbon::parse('2026-10-06 12:00', 'Africa/Cairo'))))->toBe('200.00');
    });

    it('sums piastres exactly', function () {
        $visit = Visit::factory()->create(['total_amount' => 1]);
        payOn($visit, '0.10', '2026-10-05 10:00');
        payOn($visit, '0.20', '2026-10-05 11:00');

        expect($this->reports->revenue(...monthOf('2026-10-05')))->toBe('0.30');
    });

    it('is 0.00 for a period with no payments', function () {
        expect($this->reports->revenue(...monthOf('2026-10-05')))->toBe('0.00');
    });
});

describe('patients seen (PR-5)', function () {
    it('counts each patient once, completed appointments and walk-ins only', function () {
        [$a, $b, $c] = Patient::factory()->count(3)->create();

        // A: two visits this month → counted once.
        Visit::factory()->for($a)->create(['visit_date' => '2026-10-02']);
        Visit::factory()->for($a)->create([
            'visit_date' => '2026-10-05',
            'appointment_id' => Appointment::factory()->for($a)->completed()->at('2026-10-05 10:00')->create()->id,
        ]);
        // B: visit linked to an appointment that is not completed → not counted.
        Visit::factory()->for($b)->create([
            'visit_date' => '2026-10-06',
            'appointment_id' => Appointment::factory()->for($b)->checkedIn()->at('2026-10-06 10:00')->create()->id,
        ]);
        // C: walk-in last month → not counted in October.
        Visit::factory()->for($c)->create(['visit_date' => '2026-09-30']);

        expect($this->reports->patientsSeen(...monthOf('2026-10-15')))->toBe(1)
            ->and($this->reports->patientsSeen(...monthOf('2026-09-15')))->toBe(1);
    });

    it('uses the visit date for the period edges', function () {
        Visit::factory()->create(['visit_date' => '2026-10-31']);
        Visit::factory()->create(['visit_date' => '2026-11-01']);

        expect($this->reports->patientsSeen(...monthOf('2026-10-01')))->toBe(1)
            ->and($this->reports->patientsSeen(...Period::for('day', Carbon::parse('2026-11-01 00:00', 'Africa/Cairo'))))->toBe(1);
    });
});

describe('outstanding', function () {
    it('is the total remaining across all visits, whatever the period', function () {
        $paid = Visit::factory()->create(['total_amount' => 1000]);
        payOn($paid, 1000, '2025-01-01 10:00');
        $partly = Visit::factory()->create(['total_amount' => 1500]);
        payOn($partly, 1000, '2026-10-05 10:00');
        Visit::factory()->create(['total_amount' => '250.50']); // unpaid

        expect($this->reports->outstanding())->toBe('750.50');
    });

    it('is 0.00 with no visits', function () {
        expect($this->reports->outstanding())->toBe('0.00');
    });
});

describe('payments table', function () {
    it('lists the payments in the period, newest first, with visit total, paid and remaining', function () {
        $patient = Patient::factory()->create();
        $patient->user->update(['name' => 'Mona Adel']);
        $visit = Visit::factory()->for($patient)->create(['visit_date' => '2026-10-02', 'total_amount' => 1500]);
        $first = payOn($visit, 1000, '2026-10-02 18:00');
        $second = payOn($visit, 200, '2026-10-20 09:30');
        payOn(Visit::factory()->create(['total_amount' => 900]), 300, '2026-11-01 00:00'); // next month

        $rows = $this->reports->payments(...monthOf('2026-10-10'))->get()->map(fn ($p) => $this->reports->paymentRow($p));

        expect($rows)->toHaveCount(2)
            ->and($rows->pluck('id')->all())->toBe([$second->id, $first->id])
            ->and($rows[0])->toMatchArray([
                'patient_id' => $patient->id,
                'patient_name' => 'Mona Adel',
                'visit_id' => $visit->id,
                'visit_date' => '2026-10-02',
                'visit_total' => '1500.00',
                'paid' => '200.00',
                'remaining' => '300.00',
            ])
            ->and($rows[0]['paid_at']->format('Y-m-d H:i'))->toBe('2026-10-20 09:30')
            ->and($rows[1]['paid'])->toBe('1000.00');
    });

    it('loads each visit and patient once (no N+1)', function () {
        foreach (range(1, 5) as $i) {
            payOn(Visit::factory()->create(['total_amount' => 1000]), 100, "2026-10-0{$i} 10:00");
        }

        DB::enableQueryLog();
        $this->reports->payments(...monthOf('2026-10-10'))->get()->each(fn ($p) => $this->reports->paymentRow($p));

        expect(DB::getQueryLog())->toHaveCount(4); // payments, visits, patients, users
    });
});

describe('daily revenue', function () {
    it('has one row per day of the month with 0.00 on quiet days', function () {
        $visit = Visit::factory()->create(['total_amount' => 5000]);
        payOn($visit, 100, '2026-10-01 00:00:00');
        payOn($visit, 150, '2026-10-01 23:59:59');
        payOn($visit, '99.50', '2026-10-31 20:00');
        payOn($visit, 999, '2026-11-01 00:00:00');

        $rows = $this->reports->dailyRevenue(Carbon::parse('2026-10-17', 'Africa/Cairo'));

        expect($rows)->toHaveCount(31)
            ->and($rows[0])->toBe(['date' => '2026-10-01', 'revenue' => '250.00'])
            ->and($rows[1])->toBe(['date' => '2026-10-02', 'revenue' => '0.00'])
            ->and($rows[30])->toBe(['date' => '2026-10-31', 'revenue' => '99.50']);
    });

    it('adds up to the month revenue', function () {
        $visit = Visit::factory()->create(['total_amount' => 5000]);
        payOn($visit, 300, '2026-02-03 10:00');
        payOn($visit, 700, '2026-02-28 21:00');

        $rows = $this->reports->dailyRevenue(Carbon::parse('2026-02-01', 'Africa/Cairo'));
        $sum = array_reduce($rows, fn ($s, $r) => bcadd($s, $r['revenue'], 2), '0.00');

        expect($rows)->toHaveCount(28)
            ->and($sum)->toBe($this->reports->revenue(...monthOf('2026-02-10')));
    });
});
