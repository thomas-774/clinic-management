<?php

namespace App\Services;

use App\Models\Drug;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Writes a prescription and its lines (FR-J.4). Used for both create and
 * update; an update replaces every line.
 */
class PrescriptionService
{
    public const MAX_ITEMS = 15;

    /**
     * @param  array{visit_id?: int|null, issued_on?: string|null, notes?: string|null, items: list<array{drug_id?: int|null, drug_name?: string|null, instructions: string}>}  $data
     *
     * @throws ValidationException when a rule RX-1 or RX-5 is broken
     */
    public function save(Patient $patient, User $doctor, array $data, ?Prescription $prescription = null): Prescription
    {
        // validated() can return the lines with their keys out of order; the keys are the order the doctor wrote.
        $items = $data['items'] ?? [];
        ksort($items);
        $items = array_values($items);
        $this->checkItems($items);
        $visitId = $data['visit_id'] ?? null;
        $this->checkVisit($patient, $visitId);

        return DB::transaction(function () use ($patient, $doctor, $data, $items, $visitId, $prescription) {
            $prescription ??= new Prescription(['patient_id' => $patient->id, 'doctor_id' => $doctor->id]);
            $prescription->fill([
                'visit_id' => $visitId,
                'issued_on' => $data['issued_on'] ?? $prescription->issued_on ?? today(),
                'notes' => $data['notes'] ?? null,
            ])->save();

            $prescription->items()->delete();

            $drugs = Drug::query()->whereIn('id', array_filter(array_column($items, 'drug_id')))->get()->keyBy('id');

            foreach ($items as $index => $item) {
                $drug = isset($item['drug_id']) ? $drugs->get($item['drug_id']) : null;

                $prescription->items()->create([
                    'drug_id' => $drug?->id,
                    // RX-2: a catalogue line copies the drug's name and form as they are now.
                    'drug_name' => $drug?->trade_name ?? trim($item['drug_name']),
                    'drug_form' => $drug?->form,
                    'instructions' => trim($item['instructions']),
                    'position' => $index + 1,
                ]);
            }

            return $prescription->load('items');
        });
    }

    /**
     * RX-1: 1–15 lines, each with a drug_id or a drug_name, and instructions.
     *
     * @param  list<array<string, mixed>>  $items
     */
    private function checkItems(array $items): void
    {
        if ($items === [] || count($items) > self::MAX_ITEMS) {
            throw ValidationException::withMessages([
                'items' => __('A prescription needs 1 to :max lines.', ['max' => self::MAX_ITEMS]),
            ]);
        }

        foreach ($items as $index => $item) {
            if (blank($item['drug_id'] ?? null) && blank($item['drug_name'] ?? null)) {
                throw ValidationException::withMessages([
                    "items.{$index}.drug_name" => __('Choose a drug or write its name.'),
                ]);
            }
            if (blank($item['instructions'] ?? null)) {
                throw ValidationException::withMessages([
                    "items.{$index}.instructions" => __('validation.required', ['attribute' => __('instructions')]),
                ]);
            }
        }
    }

    /**
     * RX-5: a linked visit belongs to the same patient.
     */
    private function checkVisit(Patient $patient, ?int $visitId): void
    {
        if ($visitId !== null && ! Visit::whereKey($visitId)->where('patient_id', $patient->id)->exists()) {
            throw ValidationException::withMessages([
                'visit_id' => __('This visit belongs to another patient.'),
            ]);
        }
    }
}
