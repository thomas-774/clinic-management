<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Visit;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Balances and payment rules (§4.4). Amounts are EGP strings with two
 * decimals ("1500.00") and all maths uses bcmath, never floats (PR-6).
 */
class PaymentService
{
    private const SCALE = 2;

    /**
     * Sum of the visit's payments. Uses payments_sum_amount when the visit was
     * loaded with Visit::withPaid(), so lists need no extra query per visit.
     */
    public function paid(Visit $visit): string
    {
        if (array_key_exists('payments_sum_amount', $visit->getAttributes())) {
            return self::money($visit->payments_sum_amount ?? 0);
        }

        if ($visit->relationLoaded('payments')) {
            return $visit->payments->reduce(fn (string $sum, Payment $p) => bcadd($sum, self::money($p->amount), self::SCALE), '0.00');
        }

        return self::money($visit->payments()->sum('amount'));
    }

    /**
     * PR-1: remaining = total − paid.
     */
    public function remaining(Visit $visit): string
    {
        return bcsub(self::money($visit->total_amount), $this->paid($visit), self::SCALE);
    }

    /**
     * PR-3: Paid when nothing remains, Unpaid when nothing was paid, else Partially paid.
     */
    public function status(Visit $visit): PaymentStatus
    {
        return match (true) {
            bccomp($this->remaining($visit), '0', self::SCALE) <= 0 => PaymentStatus::Paid,
            bccomp($this->paid($visit), '0', self::SCALE) === 0 => PaymentStatus::Unpaid,
            default => PaymentStatus::PartiallyPaid,
        };
    }

    /**
     * FR-D.7: the patient's balance = sum of remaining across all visits.
     */
    public function outstandingFor(Patient $patient): string
    {
        return $patient->visits()->withPaid()->get()
            ->reduce(fn (string $sum, Visit $visit) => bcadd($sum, $this->remaining($visit), self::SCALE), '0.00');
    }

    /**
     * PR-2: a payment is more than zero and at most the remaining amount.
     *
     * @throws ValidationException on `amount`
     */
    public function assertPaymentAllowed(Visit $visit, string|int|float $amount, string $field = 'amount'): void
    {
        $amount = self::money($amount);

        if (bccomp($amount, '0', self::SCALE) <= 0) {
            throw ValidationException::withMessages([$field => __('The payment must be more than zero.')]);
        }

        $remaining = $this->remaining($visit);
        if (bccomp($amount, $remaining, self::SCALE) > 0) {
            throw ValidationException::withMessages([$field => __('The payment cannot be more than the remaining :amount EGP.', ['amount' => $remaining])]);
        }
    }

    /**
     * PR-2: the total can never be lower than what has already been paid.
     *
     * @throws ValidationException on `total_amount`
     */
    public function assertTotalAllowed(Visit $visit, string|int|float $total): void
    {
        $paid = $this->paid($visit);

        if (bccomp(self::money($total), $paid, self::SCALE) < 0) {
            throw ValidationException::withMessages(['total_amount' => __('The total cannot be lower than the :amount EGP already paid.', ['amount' => $paid])]);
        }
    }

    /**
     * Records an installment (FR-D.6). The visit row is locked so two payments
     * at the same time cannot together go over the remaining amount.
     *
     * @throws ValidationException
     */
    public function addPayment(Visit $visit, string|int|float $amount, PaymentMethod $method = PaymentMethod::Cash, ?CarbonInterface $paidAt = null): Payment
    {
        return DB::transaction(function () use ($visit, $amount, $method, $paidAt) {
            $locked = Visit::query()->lockForUpdate()->findOrFail($visit->getKey());
            $this->assertPaymentAllowed($locked, $amount);

            return $locked->payments()->create([
                'amount' => self::money($amount),
                'method' => $method,
                'paid_at' => $paidAt ?? now(),
            ]);
        });
    }

    /**
     * "1500" / 1500 / 1500.5 / "1500.50" → "1500.50". Floats are only accepted
     * as input (JSON numbers) and are rounded to piastres straight away.
     */
    public static function money(string|int|float|null $value): string
    {
        $value ??= 0;

        return bcadd(is_float($value) ? sprintf('%.2f', $value) : (string) $value, '0', self::SCALE);
    }
}
