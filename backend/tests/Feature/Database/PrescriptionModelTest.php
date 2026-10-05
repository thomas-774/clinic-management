<?php

use App\Models\Drug;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Support\Facades\Schema;

describe('prescriptions tables (T9-04)', function () {
    it('has the planned columns and the (patient_id, issued_on) index', function () {
        expect(Schema::getColumnListing('prescriptions'))->toEqualCanonicalizing([
            'id', 'patient_id', 'doctor_id', 'visit_id', 'issued_on', 'notes', 'created_at', 'updated_at',
        ])
            ->and(Schema::getColumnListing('prescription_items'))->toEqualCanonicalizing([
                'id', 'prescription_id', 'drug_id', 'drug_name', 'drug_form', 'instructions', 'position', 'created_at', 'updated_at',
            ])
            ->and(Schema::hasIndex('prescriptions', ['patient_id', 'issued_on']))->toBeTrue();
    });

    it('deletes the items with their prescription', function () {
        $prescription = Prescription::factory()->withItems(3)->create();
        $other = Prescription::factory()->withItems(1)->create();

        $prescription->delete();

        expect(PrescriptionItem::count())->toBe(1)
            ->and(PrescriptionItem::sole()->prescription_id)->toBe($other->id);
    });

    it('keeps the prescription when its visit is deleted (visit_id → null)', function () {
        $visit = Visit::factory()->create();
        $prescription = Prescription::factory()->forVisit($visit)->create();

        $visit->delete();

        expect($prescription->refresh()->visit_id)->toBeNull();
    });

    it('keeps the line and its snapshot when the drug row is removed (drug_id → null)', function () {
        $drug = Drug::factory()->create(['trade_name' => 'Flagyl 500 mg', 'form' => 'tablets']);
        $item = PrescriptionItem::factory()->forDrug($drug)->create();

        $drug->delete();

        expect($item->refresh())
            ->drug_id->toBeNull()
            ->drug_name->toBe('Flagyl 500 mg')
            ->drug_form->toBe('tablets');
    });
});

describe('Prescription models (T9-04)', function () {
    it('loads 3 factory items in position order', function () {
        $prescription = Prescription::factory()->create();
        foreach ([3, 1, 2] as $position) {
            PrescriptionItem::factory()->for($prescription)->create(['position' => $position, 'drug_name' => "Line {$position}"]);
        }

        expect($prescription->fresh()->items->pluck('position')->all())->toBe([1, 2, 3])
            ->and($prescription->fresh()->items->pluck('drug_name')->all())->toBe(['Line 1', 'Line 2', 'Line 3']);
    });

    it('resolves patient, doctor, visit, items and drug', function () {
        $doctor = User::factory()->doctor()->create();
        $visit = Visit::factory()->create();
        $drug = Drug::factory()->create();
        $prescription = Prescription::factory()->forVisit($visit)->for($doctor, 'doctor')->create();
        PrescriptionItem::factory()->for($prescription)->forDrug($drug)->create();

        $prescription->refresh();

        expect($prescription->patient)->toBeInstanceOf(Patient::class)
            ->and($prescription->patient->id)->toBe($visit->patient_id)
            ->and($prescription->doctor->id)->toBe($doctor->id)
            ->and($prescription->visit->id)->toBe($visit->id)
            ->and($prescription->issued_on->format('Y-m-d'))->toBe($visit->visit_date->format('Y-m-d'))
            ->and($prescription->items->sole()->drug->id)->toBe($drug->id)
            ->and($visit->prescriptions->pluck('id')->all())->toBe([$prescription->id])
            ->and($visit->patient->prescriptions->pluck('id')->all())->toBe([$prescription->id])
            ->and($drug->prescriptionItems)->toHaveCount(1);
    });
});
