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
 */
class ReportService
{
    public function __construct(private readonly PaymentService $payments) {}

    /**
     * PR-4: money actually received — payments whose paid_at is in the
     * period, whatever the date of the visit they belong to.
     */
    public function revenue(CarbonInterface $from, CarbonInterface $to): string
    {
        return PaymentService::money($this->paymentsBetween($from, $to)->sum('amount'));
    }

    /**
     * PR-5: distinct patients with a visit in the period whose appointment is
     * completed, plus walk-in visits (no appointment).
     */
    public function patientsSeen(CarbonInterface $from, CarbonInterface $to): int
    {
        return Visit::query()
            ->whereBetween('visit_date', [self::local($from)->format('Y-m-d'), self::local($to)->format('Y-m-d')])
            ->where(fn (Builder $q) => $q
                ->whereNull('appointment_id')
                ->orWhereHas('appointment', fn (Builder $a) => $a->where('status', AppointmentStatus::Completed)))
            ->distinct()
            ->count('patient_id');
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
     * FR-H.3: payments in the period, newest first, with what the table needs
     * loaded. Returned as a query so the caller can paginate; turn each model
     * into a row with paymentRow().
     *
     * @return Builder<Payment>
     */
    public function payments(CarbonInterface $from, CarbonInterface $to): Builder
    {
        return $this->paymentsBetween($from, $to)
            ->with(['visit' => fn ($q) => $q->withPaid(), 'visit.patient.user:id,name'])
            ->orderByDesc('paid_at')
            ->orderByDesc('id');
    }

    /**
     * The table's totals row over the whole period (not one page): paid = the
     * period's revenue; remaining counts each visit once, however many of its
     * payments fall in the period.
     *
     * @return array{count: int, paid: string, remaining: string}
     */
    public function paymentTotals(CarbonInterface $from, CarbonInterface $to): array
    {
        $visits = Visit::query()
            ->withPaid()
            ->whereIn('id', $this->paymentsBetween($from, $to)->select('visit_id'))
            ->get();

        return [
            'count' => $this->paymentsBetween($from, $to)->count(),
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
        ];
    }

    /**
     * FR-H.4: revenue for every day of $month's month, 0.00 on days with no
     * payments.
     *
     * @return list<array{date: string, revenue: string}>
     */
    public function dailyRevenue(CarbonInterface $month): array
    {
        $month = self::local($month);
        $from = $month->copy()->startOfMonth();
        $to = $month->copy()->endOfMonth();

        $byDay = $this->paymentsBetween($from, $to)
            ->selectRaw('DATE(paid_at) as day, SUM(amount) as revenue')
            ->groupBy('day')
            ->pluck('revenue', 'day');

        $rows = [];
        for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
            $date = $day->format('Y-m-d');
            $rows[] = ['date' => $date, 'revenue' => PaymentService::money($byDay[$date] ?? 0)];
        }

        return $rows;
    }

    /**
     * @return Builder<Payment>
     */
    private function paymentsBetween(CarbonInterface $from, CarbonInterface $to): Builder
    {
        return Payment::query()->whereBetween('paid_at', [
            self::local($from)->format('Y-m-d H:i:s'),
            self::local($to)->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Dates are stored in clinic time, so compare in clinic time.
     */
    private static function local(CarbonInterface $at): Carbon
    {
        return Carbon::instance($at)->setTimezone(config('app.timezone'));
    }
}
