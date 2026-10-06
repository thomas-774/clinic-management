<?php

namespace App\Services\VisitReport;

/**
 * Everything the visit report file shows (FR-K.2), already localized and
 * formatted, so the PDF and the Word renderer only lay it out and can never
 * disagree (VR-3). Built by VisitReportService::build().
 *
 * Medical history, current illness and drug notes are never part of it (FR-K.3).
 */
final readonly class VisitReportData
{
    /**
     * @param  array<string, string>  $labels  Section and column labels in $locale.
     * @param  array<string, string>  $header  clinic_name, doctor_name, doctor_title, address, phone — empty parts left out.
     * @param  array{name: string, phone: ?string, age: ?int}  $patient
     * @param  array{id: int, date: string, time: ?string, walk_in: bool, payment_status: string}  $visit
     * @param  array{total: string, paid: string, remaining: string, has_remaining: bool}  $money
     * @param  list<array{date: string, amount: string, method: string, recorded_by: ?string}>  $payments  Oldest first.
     * @param  list<array{issued_on: string, items: list<array{drug_name: string, drug_form: ?string, instructions: string}>}>  $prescriptions
     */
    public function __construct(
        public string $locale,
        public bool $rtl,
        public string $title,
        public array $labels,
        public array $header,
        public array $patient,
        public array $visit,
        public string $workDone,
        public array $money,
        public array $payments,
        public string $outstanding,
        public bool $hasOutstanding,
        public array $prescriptions,
        public string $generatedAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
