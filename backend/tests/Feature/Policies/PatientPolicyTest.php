<?php

use App\Http\Resources\DetailedHistoryEntryResource;
use App\Http\Resources\PatientResource;
use App\Http\Resources\SimpleHistoryEntryResource;
use App\Models\MedicalHistoryEntry;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    $this->doctor = User::factory()->doctor()->create();
    $this->patient = Patient::factory()->create();
    $this->otherPatient = Patient::factory()->create();
});

describe('PatientPolicy', function () {
    it('lets a patient view and update only their own record', function () {
        $user = $this->patient->user;

        expect(Gate::forUser($user)->allows('view', $this->patient))->toBeTrue()
            ->and(Gate::forUser($user)->allows('update', $this->patient))->toBeTrue()
            ->and(Gate::forUser($user)->allows('view', $this->otherPatient))->toBeFalse()
            ->and(Gate::forUser($user)->allows('update', $this->otherPatient))->toBeFalse();
    });

    it('does not let a patient list, create or manage history', function () {
        $user = $this->patient->user;

        expect(Gate::forUser($user)->allows('viewAny', Patient::class))->toBeFalse()
            ->and(Gate::forUser($user)->allows('create', Patient::class))->toBeFalse()
            ->and(Gate::forUser($user)->allows('manageHistory', $this->patient))->toBeFalse();
    });

    it('lets the doctor do everything on any patient', function () {
        $gate = Gate::forUser($this->doctor);

        expect($gate->allows('viewAny', Patient::class))->toBeTrue()
            ->and($gate->allows('create', Patient::class))->toBeTrue()
            ->and($gate->allows('view', $this->otherPatient))->toBeTrue()
            ->and($gate->allows('update', $this->otherPatient))->toBeTrue()
            ->and($gate->allows('manageHistory', $this->otherPatient))->toBeTrue();
    });
});

describe('patient and history resources', function () {
    it('shows name and phone from the user account', function () {
        $data = PatientResource::make($this->patient)->resolve(new Request);

        expect($data['name'])->toBe($this->patient->user->name)
            ->and($data['phone'])->toBe($this->patient->user->phone)
            ->and($data)->toHaveKeys(['address', 'current_illness', 'date_of_birth', 'gender']);
    });

    it('gives the simple history only date, title and a short description', function () {
        $entry = MedicalHistoryEntry::factory()->visible()->for($this->patient)->create(['details' => str_repeat('a', 500)]);

        $data = SimpleHistoryEntryResource::make($entry)->resolve(new Request);

        expect(array_keys($data))->toBe(['id', 'recorded_on', 'title', 'description'])
            ->and(mb_strlen($data['description']))->toBeLessThanOrEqual(SimpleHistoryEntryResource::DESCRIPTION_LENGTH + 3);
    });

    it('never shows anything of a private entry in the simple history', function () {
        $entry = MedicalHistoryEntry::factory()->private()->for($this->patient)->create([
            'title' => 'HIV positive', 'details' => 'Secret clinical note',
        ]);

        $json = json_encode(SimpleHistoryEntryResource::make($entry)->resolve(new Request));

        expect($json)->not->toContain('Secret clinical note')->not->toContain('HIV positive');
    });

    it('gives the doctor every field', function () {
        $entry = MedicalHistoryEntry::factory()->private()->for($this->patient)->create();

        expect(DetailedHistoryEntryResource::make($entry)->resolve(new Request))
            ->toHaveKeys(['type', 'title', 'details', 'patient_visible', 'recorded_on'])
            ->patient_visible->toBeFalse();
    });
});
