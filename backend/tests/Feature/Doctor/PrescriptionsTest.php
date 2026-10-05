<?php

use App\Models\Drug;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Support\Carbon;

// T9-05: prescriptions API (FR-J.4, J.5, J.7, RX-1, RX-2, RX-5).

beforeEach(function () {
    $this->withHeader('Accept-Language', 'en');
    $this->doctor = User::factory()->doctor()->create(['name' => 'Dr. Mona Adel']);
    $this->doctor->doctorSetting()->create([
        'clinic_name' => 'Smile Dental Clinic',
        'doctor_title' => 'Oral and dental surgeon',
        'clinic_address' => '12 Tahrir St, Giza',
        'clinic_phone' => '0233334444',
        'prescription_footer' => 'Sat–Thu, 5–9 pm',
        'prescription_paper' => 'A5',
    ]);
    $this->patient = Patient::factory()->create(['date_of_birth' => '1990-06-15']);
    $this->patient->user->update(['name' => 'Ahmed Ali']);
    $this->drug = Drug::factory()->create(['trade_name' => 'Augmentin 1 g', 'form' => 'tablets']);
});

function prescriptionPayload(array $overrides = []): array
{
    return [
        'notes' => 'Soft food for two days.',
        'items' => [
            ['drug_id' => test()->drug->id, 'instructions' => '1 tablet every 12 hours for 5 days'],
            ['drug_name' => 'Warm salt water', 'instructions' => 'Rinse 3 times a day'],
        ],
        ...$overrides,
    ];
}

function postPrescription(array $payload, ?Patient $patient = null)
{
    $patient ??= test()->patient;

    return test()->actingAs(test()->doctor)->postJson("/api/v1/doctor/patients/{$patient->id}/prescriptions", $payload);
}

describe('POST /doctor/patients/{patient}/prescriptions', function () {
    it('creates a prescription with catalogue and free-text lines', function () {
        Carbon::setTestNow('2026-10-07 18:00');

        postPrescription(prescriptionPayload())
            ->assertCreated()
            ->assertJsonPath('message', 'Prescription saved.')
            ->assertJsonPath('data.issued_on', '2026-10-07')
            ->assertJsonPath('data.visit_id', null)
            ->assertJsonPath('data.notes', 'Soft food for two days.')
            ->assertJsonPath('data.items.0.position', 1)
            ->assertJsonPath('data.items.0.drug_id', $this->drug->id)
            ->assertJsonPath('data.items.0.drug_name', 'Augmentin 1 g')
            ->assertJsonPath('data.items.0.drug_form', 'tablets')
            ->assertJsonPath('data.items.1.position', 2)
            ->assertJsonPath('data.items.1.drug_id', null)
            ->assertJsonPath('data.items.1.drug_name', 'Warm salt water')
            ->assertJsonPath('data.items.1.drug_form', null);

        $prescription = Prescription::sole();
        expect($prescription->patient_id)->toBe($this->patient->id)
            ->and($prescription->doctor_id)->toBe($this->doctor->id)
            ->and($prescription->items)->toHaveCount(2);
    });

    it('copies the name and form from the catalogue, not from the request (RX-2)', function () {
        postPrescription(prescriptionPayload(['items' => [
            ['drug_id' => $this->drug->id, 'drug_name' => 'Something else', 'instructions' => 'x'],
        ]]))->assertCreated()->assertJsonPath('data.items.0.drug_name', 'Augmentin 1 g');
    });

    it('links a visit of the same patient and takes a given date', function () {
        $visit = Visit::factory()->for($this->patient)->create();

        postPrescription(prescriptionPayload(['visit_id' => $visit->id, 'issued_on' => '2026-09-30']))
            ->assertCreated()
            ->assertJsonPath('data.visit_id', $visit->id)
            ->assertJsonPath('data.issued_on', '2026-09-30');
    });

    it('rejects 0 or 16 lines', function (int $count) {
        $items = array_fill(0, $count, ['drug_name' => 'Panadol', 'instructions' => '1 tablet']);

        postPrescription(prescriptionPayload(['items' => $items]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items']);

        expect(Prescription::count())->toBe(0);
    })->with([0, 16]);

    it('accepts exactly 15 lines', function () {
        $items = array_fill(0, 15, ['drug_name' => 'Panadol', 'instructions' => '1 tablet']);

        postPrescription(prescriptionPayload(['items' => $items]))
            ->assertCreated()
            ->assertJsonPath('data.items.14.position', 15);
    });

    it('rejects a line with neither drug_id nor drug_name', function () {
        postPrescription(prescriptionPayload(['items' => [['instructions' => '1 tablet']]]))
            ->assertUnprocessable()
            ->assertJsonPath('errors', ['items.0.drug_name' => ['Choose a drug or write its name.']]);
    });

    it('validates each line', function (array $item, string $error) {
        postPrescription(prescriptionPayload(['items' => [$item]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$error]);
    })->with([
        'no instructions' => [['drug_name' => 'Panadol', 'instructions' => ''], 'items.0.instructions'],
        'instructions too long' => [['drug_name' => 'Panadol', 'instructions' => str_repeat('a', 256)], 'items.0.instructions'],
        'unknown drug' => [['drug_id' => 999999, 'instructions' => 'x'], 'items.0.drug_id'],
        'name too long' => [['drug_name' => str_repeat('a', 151), 'instructions' => 'x'], 'items.0.drug_name'],
    ]);

    it("rejects another patient's visit (RX-5)", function () {
        $otherVisit = Visit::factory()->create();

        postPrescription(prescriptionPayload(['visit_id' => $otherVisit->id]))
            ->assertUnprocessable()
            ->assertJsonPath('errors', ['visit_id' => ['This visit belongs to another patient.']]);

        expect(Prescription::count())->toBe(0);
    });

    it('validates notes and date', function (array $overrides, string $error) {
        postPrescription(prescriptionPayload($overrides))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$error]);
    })->with([
        'notes too long' => [['notes' => str_repeat('a', 1001)], 'notes'],
        'bad date' => [['issued_on' => '07/10/2026'], 'issued_on'],
    ]);

    it('answers in Arabic', function () {
        $this->actingAs($this->doctor)
            ->postJson("/api/v1/doctor/patients/{$this->patient->id}/prescriptions", prescriptionPayload(['items' => [['instructions' => 'x']]]), ['Accept-Language' => 'ar'])
            ->assertJsonPath('errors', ['items.0.drug_name' => ['اختر دواءً أو اكتب اسمه.']]);
    });
});

describe('snapshot (RX-2)', function () {
    it('keeps the issued line when the drug is renamed or hidden afterwards', function () {
        $id = postPrescription(prescriptionPayload())->json('data.id');

        $this->actingAs($this->doctor)->putJson("/api/v1/doctor/drugs/{$this->drug->id}", [
            'trade_name' => 'Augmentin Forte', 'form' => 'sachets',
        ])->assertOk();
        $this->actingAs($this->doctor)->putJson("/api/v1/doctor/drugs/{$this->drug->id}", ['is_active' => false])->assertOk();

        $this->actingAs($this->doctor)->getJson("/api/v1/doctor/prescriptions/{$id}")
            ->assertOk()
            ->assertJsonPath('data.items.0.drug_name', 'Augmentin 1 g')
            ->assertJsonPath('data.items.0.drug_form', 'tablets')
            ->assertJsonPath('data.items.0.drug_id', $this->drug->id);
    });
});

describe('GET /doctor/patients/{patient}/prescriptions', function () {
    it('lists the patient\'s prescriptions newest first with drug names', function () {
        $old = Prescription::factory()->for($this->patient)->create(['issued_on' => '2026-01-10']);
        PrescriptionItem::factory()->for($old)->create(['drug_name' => 'Flagyl 500 mg', 'position' => 1]);
        $new = Prescription::factory()->for($this->patient)->create(['issued_on' => '2026-09-01']);
        PrescriptionItem::factory()->for($new)->create(['drug_name' => 'Brufen 400 mg', 'position' => 2]);
        PrescriptionItem::factory()->for($new)->create(['drug_name' => 'Augmentin 1 g', 'position' => 1]);
        Prescription::factory()->withItems(1)->create(); // another patient

        $this->actingAs($this->doctor)->getJson("/api/v1/doctor/patients/{$this->patient->id}/prescriptions")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $new->id)
            ->assertJsonPath('data.0.issued_on', '2026-09-01')
            ->assertJsonPath('data.0.drug_names', ['Augmentin 1 g', 'Brufen 400 mg'])
            ->assertJsonPath('data.1.drug_names', ['Flagyl 500 mg']);
    });
});

describe('GET /doctor/prescriptions/{prescription}', function () {
    it('returns the lines, patient name and age, and the print block', function () {
        Carbon::setTestNow('2026-10-07 18:00');
        $id = postPrescription(prescriptionPayload(['issued_on' => '2026-06-14']))->json('data.id');

        $this->actingAs($this->doctor)->getJson("/api/v1/doctor/prescriptions/{$id}")
            ->assertOk()
            ->assertJsonPath('data.patient', ['id' => $this->patient->id, 'name' => 'Ahmed Ali', 'age' => 35])
            ->assertJsonPath('data.print', [
                'doctor_name' => 'Dr. Mona Adel',
                'doctor_title' => 'Oral and dental surgeon',
                'clinic_name' => 'Smile Dental Clinic',
                'clinic_address' => '12 Tahrir St, Giza',
                'clinic_phone' => '0233334444',
                'footer' => 'Sat–Thu, 5–9 pm',
                'paper' => 'A5',
            ])
            ->assertJsonCount(2, 'data.items')
            ->assertJsonMissingPath('data.items.0.warnings');
    });

    it('gives a null age without a date of birth', function () {
        $this->patient->update(['date_of_birth' => null]);
        $id = postPrescription(prescriptionPayload())->json('data.id');

        $this->actingAs($this->doctor)->getJson("/api/v1/doctor/prescriptions/{$id}")
            ->assertJsonPath('data.patient.age', null);
    });

    it('answers 404 for an unknown prescription', function () {
        $this->actingAs($this->doctor)->getJson('/api/v1/doctor/prescriptions/999999')->assertNotFound();
    });
});

describe('PUT /doctor/prescriptions/{prescription}', function () {
    it('replaces the lines and keeps the date when none is sent', function () {
        $id = postPrescription(prescriptionPayload(['issued_on' => '2026-09-30']))->json('data.id');
        $oldItemIds = PrescriptionItem::pluck('id')->all();

        $this->actingAs($this->doctor)->putJson("/api/v1/doctor/prescriptions/{$id}", [
            'notes' => null,
            'items' => [
                ['drug_name' => 'Panadol Extra', 'instructions' => '1 tablet when needed'],
                ['drug_id' => $this->drug->id, 'instructions' => '1 tablet every 12 hours'],
                ['drug_name' => 'Chlorhexidine', 'instructions' => 'Rinse twice a day'],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Prescription updated.')
            ->assertJsonPath('data.issued_on', '2026-09-30')
            ->assertJsonPath('data.notes', null)
            ->assertJsonPath('data.items.*.drug_name', ['Panadol Extra', 'Augmentin 1 g', 'Chlorhexidine'])
            ->assertJsonPath('data.items.*.position', [1, 2, 3]);

        expect(PrescriptionItem::count())->toBe(3)
            ->and(PrescriptionItem::whereIn('id', $oldItemIds)->exists())->toBeFalse();
    });

    it("checks RX-1 and RX-5 against the prescription's patient", function () {
        $id = postPrescription(prescriptionPayload())->json('data.id');
        $otherVisit = Visit::factory()->create();

        $this->actingAs($this->doctor)->putJson("/api/v1/doctor/prescriptions/{$id}", prescriptionPayload(['items' => []]))
            ->assertUnprocessable()->assertJsonValidationErrors(['items']);
        $this->actingAs($this->doctor)->putJson("/api/v1/doctor/prescriptions/{$id}", prescriptionPayload(['visit_id' => $otherVisit->id]))
            ->assertUnprocessable()->assertJsonValidationErrors(['visit_id']);

        $ownVisit = Visit::factory()->for($this->patient)->create();
        $this->actingAs($this->doctor)->putJson("/api/v1/doctor/prescriptions/{$id}", prescriptionPayload(['visit_id' => $ownVisit->id]))
            ->assertOk()->assertJsonPath('data.visit_id', $ownVisit->id);

        expect(PrescriptionItem::count())->toBe(2);
    });
});

describe('DELETE /doctor/prescriptions/{prescription}', function () {
    it('deletes the prescription and its items', function () {
        $id = postPrescription(prescriptionPayload())->json('data.id');
        $other = Prescription::factory()->withItems(2)->create();

        $this->actingAs($this->doctor)->deleteJson("/api/v1/doctor/prescriptions/{$id}")
            ->assertOk()
            ->assertJsonPath('message', 'Prescription deleted.');

        expect(Prescription::pluck('id')->all())->toBe([$other->id])
            ->and(PrescriptionItem::where('prescription_id', $id)->count())->toBe(0)
            ->and(PrescriptionItem::count())->toBe(2)
            ->and(Drug::find($this->drug->id))->not->toBeNull();
    });
});

describe('GET /doctor/patients/{patient}', function () {
    it('includes the prescriptions count', function () {
        $this->actingAs($this->doctor)->getJson("/api/v1/doctor/patients/{$this->patient->id}")
            ->assertJsonPath('data.prescriptions_count', 0);

        Prescription::factory()->count(2)->for($this->patient)->create();
        Prescription::factory()->create();

        $this->actingAs($this->doctor)->getJson("/api/v1/doctor/patients/{$this->patient->id}")
            ->assertOk()
            ->assertJsonPath('data.prescriptions_count', 2);
    });
});
