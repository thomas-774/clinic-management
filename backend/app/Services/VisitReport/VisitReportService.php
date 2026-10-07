<?php

namespace App\Services\VisitReport;

use App\Models\Payment;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\Visit;
use App\Services\PaymentService;
use App\Support\ClinicContext;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Builds the data of a visit's report file (FR-K.2) from the database at the
 * moment it is asked for (VR-1). All amounts come from PaymentService (VR-2).
 * Only the visit, its payments and its own prescriptions are loaded — never
 * medical history, current illness or drug notes (FR-K.3).
 */
class VisitReportService
{
    public const LOCALES = ['ar', 'en'];

    /**
     * Label keys (English source strings in lang/*.json) by name.
     */
    private const LABELS = [
        'title' => 'Visit report',
        'patient' => 'Patient',
        'name' => 'Name',
        'phone' => 'Phone',
        'age' => 'Age',
        'visit' => 'Visit',
        'visit_date' => 'Visit date',
        'time' => 'Time',
        'walk_in' => 'Walk-in',
        'payment_status' => 'Payment status',
        'work_done' => 'Work done today',
        'money' => 'Amounts',
        'total' => 'Total cost',
        'paid' => 'Amount paid',
        'remaining' => 'Remaining',
        'payments' => 'Payments',
        'no_payments' => 'No payments yet.',
        'date' => 'Date',
        'amount' => 'Amount',
        'method' => 'Method',
        'recorded_by' => 'Recorded by',
        'outstanding' => 'Overall outstanding balance',
        'prescriptions' => 'Prescriptions',
        'drug' => 'Drug',
        'form' => 'Form',
        'instructions' => 'Instructions',
        'generated_on' => 'Generated on',
        'signature' => 'Signature',
    ];

    /**
     * PaymentStatus / PaymentMethod values → their label keys.
     */
    private const STATUSES = ['paid' => 'Paid', 'partially_paid' => 'Partially paid', 'unpaid' => 'Unpaid'];

    private const METHODS = ['cash' => 'Cash', 'card' => 'Card', 'wallet' => 'Wallet'];

    public function __construct(
        private readonly PaymentService $payments,
        private readonly ClinicContext $clinic,
    ) {}

    public function build(Visit $visit, string $locale): VisitReportData
    {
        $locale = in_array($locale, self::LOCALES, true) ? $locale : config('app.fallback_locale');
        $visit->loadMissing([
            'patient.user',
            'appointment',
            'payments' => fn ($q) => $q->with('recordedBy')->orderBy('paid_at')->orderBy('id'),
            'prescriptions' => fn ($q) => $q->with('items')->orderBy('issued_on')->orderBy('id'),
        ]);
        $patient = $visit->patient;
        $date = $this->date($visit->visit_date, $locale);
        $remaining = $this->payments->remaining($visit);
        $outstanding = $this->payments->outstandingFor($patient);

        return new VisitReportData(
            locale: $locale,
            rtl: $locale === 'ar',
            title: $this->t('Visit report', $locale).' — '.$date,
            labels: array_map(fn (string $key) => $this->t($key, $locale), self::LABELS),
            header: $this->header($locale),
            patient: [
                'name' => $patient->user->name,
                'phone' => $patient->user->phone ?: null,
                // Age on the visit day; left out without a date of birth.
                'age' => $patient->date_of_birth ? (int) $patient->date_of_birth->diffInYears($visit->visit_date) : null,
            ],
            visit: [
                'id' => $visit->id,
                'date' => $date,
                'time' => $visit->appointment
                    ? $this->time($visit->appointment->start_at).' – '.$this->time($visit->appointment->end_at)
                    : null,
                'walk_in' => $visit->appointment === null,
                'payment_status' => $this->t(self::STATUSES[$this->payments->status($visit)->value], $locale),
            ],
            workDone: (string) $visit->work_done,
            money: [
                'total' => self::money($visit->total_amount, $locale),
                'paid' => self::money($this->payments->paid($visit), $locale),
                'remaining' => self::money($remaining, $locale),
                'has_remaining' => bccomp($remaining, '0', 2) > 0,
            ],
            payments: $visit->payments->map(fn (Payment $p) => [
                'date' => $this->date($p->paid_at, $locale),
                'amount' => self::money($p->amount, $locale),
                'method' => $this->t(self::METHODS[$p->method->value], $locale),
                'recorded_by' => $p->recordedBy?->name,
            ])->values()->all(),
            outstanding: self::money($outstanding, $locale),
            hasOutstanding: bccomp($outstanding, '0', 2) > 0,
            prescriptions: $visit->prescriptions->map(fn (Prescription $rx) => [
                'issued_on' => $this->date($rx->issued_on, $locale),
                // Snapshot fields only (RX-2, RX-4).
                'items' => $rx->items->map(fn (PrescriptionItem $item) => [
                    'drug_name' => $item->drug_name,
                    'drug_form' => $item->drug_form ?: null,
                    'instructions' => $item->instructions,
                ])->values()->all(),
            ])->values()->all(),
            generatedAt: $this->date(now(), $locale).' '.$this->time(now()),
        );
    }

    /**
     * "1500.00" → "1,500.00 EGP" / "1,500.00 ج.م". Grouping is done on the
     * string, so no float ever touches the amount (PR-6, VR-2).
     */
    public static function money(string|int|float|null $amount, string $locale): string
    {
        $value = PaymentService::money($amount);
        $negative = str_starts_with($value, '-');
        [$whole, $fraction] = explode('.', ltrim($value, '-'));
        $grouped = strrev(implode(',', str_split(strrev($whole), 3)));

        return ($negative ? '-' : '').$grouped.'.'.$fraction.' '.__('EGP', [], $locale);
    }

    /**
     * The print header, as on the prescription (FR-J.5); empty parts are left out.
     *
     * @return array<string, string>
     */
    private function header(string $locale): array
    {
        $doctor = $this->clinic->doctor();
        $settings = $this->clinic->settings();

        return array_filter([
            'clinic_name' => $settings->clinic_name,
            'doctor_name' => $doctor->name,
            'doctor_title' => $settings->doctor_title,
            'address' => $settings->clinic_address,
            'phone' => $settings->clinic_phone,
        ], fn (?string $part) => trim((string) $part) !== '');
    }

    private function t(string $key, string $locale): string
    {
        return __($key, [], $locale);
    }

    /**
     * "7 Oct 2026" / "7 أكتوبر 2026" in clinic time, Latin digits (as the app).
     */
    private function date(CarbonInterface $date, string $locale): string
    {
        return Carbon::instance($date)->setTimezone(config('app.timezone'))->locale($locale)->translatedFormat('j M Y');
    }

    private function time(CarbonInterface $time): string
    {
        return Carbon::instance($time)->setTimezone(config('app.timezone'))->format('H:i');
    }
}
