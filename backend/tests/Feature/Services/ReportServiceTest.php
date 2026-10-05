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
 * ReportService (T6-02, changed by the client on 2026-10-05): a visit's money
 * counts on the visit's date, the cards count visits; plus outstanding, the
 * payments table and the daily revenue series.
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

function visitOn(string $date, string|int $total = 5000): Visit
{
    return Visit::factory()->create(['visit_date' => $date, 'total_amount' => $total]);
}

function monthOf(string $date): array
{
    return Period::for('month', Carbon::parse($date, 'Africa/Cairo'));
}

function dayOf(string $date): array
{
    return Period::for('day', Carbon::parse($date, 'Africa/Cairo'));
}

describe('revenue by visit date', function () {
    it('counts a visit dated 24-10 on 24-10, even when it was paid on 5-10', function () {
        payOn(visitOn('2026-10-24'), 5000, '2026-10-05 14:30');

        expect($this->reports->revenue(...dayOf('2026-10-05')))->toBe('0.00')
            ->and($this->reports->revenue(...dayOf('2026-10-24')))->toBe('5000.00');
    });

    it('counts an installment paid in November in the visit month', function () {
        $visit = visitOn('2026-10-20', 1500);
        payOn($visit, 1000, '2026-10-20 18:00');
        payOn($visit, 500, '2026-11-03 11:00');

        expect($this->reports->revenue(...monthOf('2026-10-01')))->toBe('1500.00')
            ->and($this->reports->revenue(...monthOf('2026-11-01')))->toBe('0.00');
    });

    it('includes the first and last day of the month and nothing outside', function () {
        payOn(visitOn('2026-09-30'), 100, '2026-09-30 23:59:59');
        payOn(visitOn('2026-10-01'), 200, '2026-10-01 00:00:00');
        payOn(visitOn('2026-10-31'), 300, '2026-10-31 23:59:59');
        payOn(visitOn('2026-11-01'), 400, '2026-11-01 00:00:00');

        expect($this->reports->revenue(...monthOf('2026-10-15')))->toBe('500.00');
    });

    it('splits days by the visit date', function () {
        payOn(visitOn('2026-10-05'), 100, '2026-10-05 23:59:59');
        payOn(visitOn('2026-10-06'), 200, '2026-10-06 00:00:00');

        expect($this->reports->revenue(...dayOf('2026-10-05')))->toBe('100.00')
            ->and($this->reports->revenue(...dayOf('2026-10-06')))->toBe('200.00');
    });

    it('sums piastres exactly', function () {
        $visit = visitOn('2026-10-05', 1);
        payOn($visit, '0.10', '2026-10-05 10:00');
        payOn($visit, '0.20', '2026-10-05 11:00');

        expect($this->reports->revenue(...monthOf('2026-10-05')))->toBe('0.30');
    });

    it('is 0.00 for a period with no payments', function () {
        visitOn('2026-10-05'); // unpaid visit
        expect($this->reports->revenue(...monthOf('2026-10-05')))->toBe('0.00');
    });
});

describe('visits count', function () {
    it('counts every visit, completed appointments and walk-ins only', function () {
        [$a, $b, $c] = Patient::factory()->count(3)->create();

        // A: two visits this month → counted twice.
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

        expect($this->reports->visitsCount(...monthOf('2026-10-15')))->toBe(2)
            ->and($this->reports->visitsCount(...monthOf('2026-09-15')))->toBe(1);
    });

    it('uses the visit date for the period edges', function () {
        visitOn('2026-10-31');
        visitOn('2026-11-01');

        expect($this->reports->visitsCount(...monthOf('2026-10-01')))->toBe(1)
            ->and($this->reports->visitsCount(...dayOf('2026-11-01 00:00')))->toBe(1);
    });
});

describe('outstanding', function () {
    it('is the total remaining across all visits, whatever the period', function () {
        payOn(visitOn('2025-01-01', 1000), 1000, '2025-01-01 10:00');
        payOn(visitOn('2026-10-05', 1500), 1000, '2026-10-05 10:00');
        visitOn('2026-10-06', '250.50'); // unpaid

        expect($this->reports->outstanding())->toBe('750.50');
    });

    it('is 0.00 with no visits', function () {
        expect($this->reports->outstanding())->toBe('0.00');
    });
});

describe('payments table', function () {
    it('lists the payments of the visits in the period, newest visit first, with visit total, paid and remaining', function () {
        $patient = Patient::factory()->create();
        $patient->user->update(['name' => 'Mona Adel']);
        $early = Visit::factory()->for($patient)->create(['visit_date' => '2026-10-02', 'total_amount' => 1500]);
        $first = payOn($early, 1000, '2026-10-02 18:00');
        $installment = payOn($early, 200, '2026-11-20 09:30'); // paid next month, still in October
        $late = payOn(visitOn('2026-10-24', 900), 300, '2026-10-05 12:00');
        payOn(visitOn('2026-11-01', 900), 300, '2026-10-30 10:00'); // November visit

        $rows = $this->reports->payments(...monthOf('2026-10-10'))->get()->map(fn ($p) => $this->reports->paymentRow($p));

        expect($rows->pluck('id')->all())->toBe([$late->id, $installment->id, $first->id])
            ->and($rows[1])->toMatchArray([
                'patient_id' => $patient->id,
                'patient_name' => 'Mona Adel',
                'visit_id' => $early->id,
                'visit_date' => '2026-10-02',
                'visit_total' => '1500.00',
                'paid' => '200.00',
                'remaining' => '300.00',
            ])
            ->and($rows[1]['paid_at']->format('Y-m-d H:i'))->toBe('2026-11-20 09:30');
    });

    it('totals the whole period, counting each visit once for remaining', function () {
        $visit = visitOn('2026-10-02', 1500);
        payOn($visit, 1000, '2026-10-02 18:00');
        payOn($visit, 200, '2026-10-20 09:30');
        payOn(visitOn('2026-10-24', 900), 300, '2026-10-05 12:00');

        expect($this->reports->paymentTotals(...monthOf('2026-10-10')))
            ->toBe(['count' => 3, 'paid' => '1500.00', 'remaining' => '900.00']);
    });

    it('loads each visit and patient once (no N+1)', function () {
        foreach (range(1, 5) as $i) {
            payOn(visitOn("2026-10-0{$i}", 1000), 100, "2026-10-0{$i} 10:00");
        }

        DB::enableQueryLog();
        $this->reports->payments(...monthOf('2026-10-10'))->get()->each(fn ($p) => $this->reports->paymentRow($p));

        expect(DB::getQueryLog())->toHaveCount(4); // payments, visits, patients, users
    });
});

describe('daily revenue', function () {
    it('has one row per day of the month by visit date, 0.00 on quiet days', function () {
        $first = visitOn('2026-10-01');
        payOn($first, 100, '2026-10-01 09:00');
        payOn($first, 150, '2026-10-12 09:00'); // installment → still Oct 1
        payOn(visitOn('2026-10-31'), '99.50', '2026-10-05 20:00'); // paid early → Oct 31
        payOn(visitOn('2026-11-01'), 999, '2026-10-20 10:00'); // November visit

        $rows = $this->reports->dailyRevenue(Carbon::parse('2026-10-17', 'Africa/Cairo'));

        expect($rows)->toHaveCount(31)
            ->and($rows[0])->toBe(['date' => '2026-10-01', 'revenue' => '250.00'])
            ->and($rows[4])->toBe(['date' => '2026-10-05', 'revenue' => '0.00'])
            ->and($rows[11])->toBe(['date' => '2026-10-12', 'revenue' => '0.00'])
            ->and($rows[30])->toBe(['date' => '2026-10-31', 'revenue' => '99.50']);
    });

    it('adds up to the month revenue', function () {
        payOn(visitOn('2026-02-03'), 300, '2026-02-03 10:00');
        payOn(visitOn('2026-02-28'), 700, '2026-03-02 21:00');

        $rows = $this->reports->dailyRevenue(Carbon::parse('2026-02-01', 'Africa/Cairo'));
        $sum = array_reduce($rows, fn ($s, $r) => bcadd($s, $r['revenue'], 2), '0.00');

        expect($rows)->toHaveCount(28)
            ->and($sum)->toBe('1000.00')
            ->and($sum)->toBe($this->reports->revenue(...monthOf('2026-02-10')));
    });
});
