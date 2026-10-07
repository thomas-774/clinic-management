<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Models\Payment;
use App\Models\Visit;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Dashboard and report numbers (Module H). Periods come from Period::for()
 * and are inclusive: [from, to] with to = the last second of the period.
 * Amounts are EGP strings with two decimals, summed with bcmath (PR-6).
 *
 * Client decision (2026-10-05), replacing PR-4 / PR-5: a visit's money counts
 * on the visit's date — all of its payments, including installments paid
 * later — and the cards count visits, not distinct patients.
 */
class ReportService
{
    public function __construct(private readonly PaymentService $payments) {}

    /**
     * All payments of the visits dated in the period, whenever they were paid.
     */
    public function revenue(CarbonInterface $from, CarbonInterface $to): string
    {
        return PaymentService::money($this->paymentsOfVisitsBetween($from, $to)->sum('amount'));
    }

    /**
     * Visits dated in the period whose appointment is completed, plus walk-in
     * visits (no appointment). A patient seen twice counts twice.
     */
    public function visitsCount(CarbonInterface $from, CarbonInterface $to): int
    {
        return $this->visitsBetween($from, $to)
            ->where(fn (Builder $q) => $q
                ->whereNull('appointment_id')
                ->orWhereHas('appointment', fn (Builder $a) => $a->where('status', AppointmentStatus::Completed)))
            ->count();
    }

    /**
     * FR-H.2: what patients still owe, over all visits. Payments can never go
     * over a visit's total (PR-2), so this is all totals minus all payments.
     */
    public function outstanding(): string
    {
        return bcsub(
            PaymentService::money(Visit::query()->sum('total_amount')),
            PaymentService::money(Payment::query()->sum('amount')),
            2,
        );
    }

    /**
     * Who owes what: one row per patient with an unpaid balance, largest
     * first. outstanding = sum of remaining over the patient's unpaid visits.
     *
     * @return list<array{patient_id: int, patient_name: string, phone: string, outstanding: string, unpaid_visits: int, oldest_visit_date: string}>
     */
    public function outstandingByPatient(): array
    {
        // Every visit has to be checked, so (T11-08): only the columns used
        // below, read from the (visit_date, patient_id, total_amount) index in
        // date order, and the payments summed once per visit in one pass
        // rather than by a subquery per visit.
        $paid = Payment::query()
            ->select('visit_id')
            ->selectRaw('SUM(amount) as payments_sum_amount')
            ->groupBy('visit_id');

        $unpaid = Visit::query()
            ->select(['visits.id', 'visits.patient_id', 'visits.visit_date', 'visits.total_amount', 'paid.payments_sum_amount'])
            ->leftJoinSub($paid, 'paid', 'paid.visit_id', '=', 'visits.id')
            ->whereRaw('visits.total_amount > COALESCE(paid.payments_sum_amount, 0)')
            ->with('patient.user:id,name,phone')
            ->orderBy('visits.visit_date')
            ->get();

        $rows = $unpaid->groupBy('patient_id')->map(fn ($visits) => [
            'patient_id' => $visits->first()->patient_id,
            'patient_name' => $visits->first()->patient->user->name,
            'phone' => $visits->first()->patient->user->phone,
            'outstanding' => $this->payments->sumRemaining($visits),
            'unpaid_visits' => $visits->count(),
            'oldest_visit_date' => $visits->first()->visit_date->format('Y-m-d'),
        ]);

        return $rows->sort(fn ($a, $b) => bccomp($b['outstanding'], $a['outstanding'], 2) ?: strcmp($a['patient_name'], $b['patient_name']))
            ->values()
            ->all();
    }

    /**
     * FR-H.3: the payments of the visits dated in the period, newest visit
     * first, with what the table needs loaded. Returned as a query so the
     * caller can paginate; turn each model into a row with paymentRow().
     *
     * @return Builder<Payment>
     */
    public function payments(CarbonInterface $from, CarbonInterface $to): Builder
    {
        return $this->paymentsOfVisitsBetween($from, $to)
            ->with(['visit' => fn ($q) => $q->withPaid(), 'visit.patient.user:id,name', 'recordedBy:id,name'])
            ->orderByDesc(Visit::query()->select('visit_date')->whereColumn('visits.id', 'payments.visit_id'))
            ->orderByDesc('paid_at')
            ->orderByDesc('id');
    }

    /**
     * The table's totals row over the whole period (not one page): paid = the
     * period's revenue; remaining counts each visit once, however many
     * payments it has.
     *
     * @return array{count: int, paid: string, remaining: string}
     */
    public function paymentTotals(CarbonInterface $from, CarbonInterface $to): array
    {
        $visits = Visit::query()
            ->withPaid()
            ->whereIn('id', $this->paymentsOfVisitsBetween($from, $to)->select('visit_id'))
            ->get();

        return [
            'count' => $this->paymentsOfVisitsBetween($from, $to)->count(),
            'paid' => $this->revenue($from, $to),
            'remaining' => $this->payments->sumRemaining($visits),
        ];
    }

    /**
     * One table row: paid = this payment, remaining = what the visit still owes now.
     *
     * @return array{id: int, paid_at: Carbon, patient_id: int, patient_name: string, visit_id: int, visit_date: string, visit_total: string, paid: string, remaining: string}
     */
    public function paymentRow(Payment $payment): array
    {
        $visit = $payment->visit;

        return [
            'id' => $payment->id,
            'paid_at' => $payment->paid_at,
            'patient_id' => $visit->patient_id,
            'patient_name' => $visit->patient->user->name,
            'visit_id' => $visit->id,
            'visit_date' => $visit->visit_date->format('Y-m-d'),
            'visit_total' => PaymentService::money($visit->total_amount),
            'paid' => PaymentService::money($payment->amount),
            'remaining' => $this->payments->remaining($visit),
            'recorded_by_name' => $payment->recordedBy?->name,
        ];
    }

    /**
     * FR-H.4: revenue for every day of $month's month by visit date, 0.00 on
     * days with no paid visits.
     *
     * @return list<array{date: string, revenue: string}>
     */
    public function dailyRevenue(CarbonInterface $month): array
    {
        $month = self::local($month);
        $from = $month->copy()->startOfMonth();
        $to = $month->copy()->endOfMonth();

        $byDay = Payment::query()
            ->join('visits', 'visits.id', '=', 'payments.visit_id')
            ->whereBetween('visits.visit_date', [$from->format('Y-m-d'), $to->format('Y-m-d')])
            ->selectRaw('visits.visit_date as day, SUM(payments.amount) as revenue')
            ->groupBy('visits.visit_date')
            ->pluck('revenue', 'day');

        $rows = [];
        for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
            $date = $day->format('Y-m-d');
            $rows[] = ['date' => $date, 'revenue' => PaymentService::money($byDay[$date] ?? 0)];
        }

        return $rows;
    }

    /**
     * @return Builder<Visit>
     */
    private function visitsBetween(CarbonInterface $from, CarbonInterface $to): Builder
    {
        return Visit::query()->whereBetween('visit_date', [
            self::local($from)->format('Y-m-d'),
            self::local($to)->format('Y-m-d'),
        ]);
    }

    /**
     * @return Builder<Payment>
     */
    private function paymentsOfVisitsBetween(CarbonInterface $from, CarbonInterface $to): Builder
    {
        return Payment::query()->whereIn('visit_id', $this->visitsBetween($from, $to)->select('id'));
    }

    /**
     * Dates are stored in clinic time, so compare in clinic time.
     */
    private static function local(CarbonInterface $at): Carbon
    {
        return Carbon::instance($at)->setTimezone(config('app.timezone'));
    }
}
