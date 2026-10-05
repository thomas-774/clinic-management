<?php

use App\Models\MedicalHistoryEntry;
use App\Models\Patient;
use App\Models\User;

beforeEach(function () {
    $this->withHeader('Accept-Language', 'en');
    $this->doctor = User::factory()->doctor()->create();
    $this->patient = Patient::factory()->create(['current_illness' => 'Toothache']);
});

function validUpdate(array $overrides = []): array
{
    return array_merge([
        'name' => 'Updated Name',
        'phone' => '01066667777',
        'address' => 'New address',
        'date_of_birth' => '1985-03-04',
        'gender' => 'male',
        'current_illness' => 'Root canal needed',
    ], $overrides);
}

describe('GET /doctor/patients/{id}', function () {
    it('shows the profile with every history entry, private ones included', function () {
        MedicalHistoryEntry::factory()->for($this->patient)->visible()->create(['title' => 'Asthma', 'recorded_on' => '2025-01-01']);
        MedicalHistoryEntry::factory()->for($this->patient)->private()->create(['title' => 'Private note', 'recorded_on' => '2026-01-01']);
        MedicalHistoryEntry::factory()->for(Patient::factory())->create(['title' => 'Someone else']);

        $this->actingAs($this->doctor)->getJson("/api/v1/doctor/patients/{$this->patient->id}")
            ->assertOk()
            ->assertJsonPath('data.current_illness', 'Toothache')
            ->assertJsonPath('data.history.0.title', 'Private note')
            ->assertJsonPath('data.history.0.patient_visible', false)
            ->assertJsonPath('data.history.1.title', 'Asthma')
            ->assertJsonCount(2, 'data.history')
            ->assertJsonPath('data.visits', []);
    });

    it('returns 404 for an unknown patient', function () {
        $this->actingAs($this->doctor)->getJson('/api/v1/doctor/patients/999999')->assertNotFound();
    });
});

describe('PUT /doctor/patients/{id}', function () {
    it('updates the info and the illness shows up on the patient profile', function () {
        $this->actingAs($this->doctor)->putJson("/api/v1/doctor/patients/{$this->patient->id}", validUpdate())
            ->assertOk()
            ->assertJsonPath('message', 'Patient updated.')
            ->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonPath('data.phone', '01066667777')
            ->assertJsonPath('data.date_of_birth', '1985-03-04');

        app('auth')->forgetGuards();
        $this->actingAs($this->patient->user->fresh())->getJson('/api/v1/patient/profile')
            ->assertJsonPath('data.current_illness', 'Root canal needed')
            ->assertJsonPath('data.name', 'Updated Name');
    });

    it('allows the patient\'s own phone but not another account\'s', function () {
        $own = $this->patient->user->phone;
        User::factory()->create(['phone' => '01088889999']);

        $this->actingAs($this->doctor)->putJson("/api/v1/doctor/patients/{$this->patient->id}", validUpdate(['phone' => $own]))
            ->assertOk();
        $this->actingAs($this->doctor)->putJson("/api/v1/doctor/patients/{$this->patient->id}", validUpdate(['phone' => '01088889999']))
            ->assertJsonValidationErrors(['phone']);
    });

    it('can clear the illness', function () {
        $this->actingAs($this->doctor)->putJson("/api/v1/doctor/patients/{$this->patient->id}", validUpdate(['current_illness' => null]))
            ->assertOk()
            ->assertJsonPath('data.current_illness', null);
    });
});

it('is for the doctor only, even for the patient\'s own id', function () {
    $user = $this->patient->user;

    $this->actingAs($user)->getJson("/api/v1/doctor/patients/{$this->patient->id}")->assertForbidden();
    $this->actingAs($user)->putJson("/api/v1/doctor/patients/{$this->patient->id}", validUpdate())->assertForbidden();
});
