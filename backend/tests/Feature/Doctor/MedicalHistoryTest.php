<?php

use App\Models\MedicalHistoryEntry;
use App\Models\Patient;
use App\Models\User;

beforeEach(function () {
    $this->withHeader('Accept-Language', 'en');
    $this->doctor = User::factory()->doctor()->create();
    $this->patient = Patient::factory()->create();
    $this->url = "/api/v1/doctor/patients/{$this->patient->id}/history";
});

function historyData(array $overrides = []): array
{
    return array_merge([
        'type' => 'allergy',
        'title' => 'Penicillin',
        'details' => 'Rash after the first dose in 2019.',
        'recorded_on' => '2026-10-01',
    ], $overrides);
}

it('adds an entry, private by default', function () {
    $this->actingAs($this->doctor)->postJson($this->url, historyData())
        ->assertCreated()
        ->assertJsonPath('message', 'History entry added.')
        ->assertJsonPath('data.type', 'allergy')
        ->assertJsonPath('data.patient_visible', false)
        ->assertJsonPath('data.patient_id', $this->patient->id);

    expect($this->patient->medicalHistoryEntries()->count())->toBe(1);
});

it('edits an entry and toggles its visibility', function () {
    $entry = MedicalHistoryEntry::factory()->for($this->patient)->private()->create();

    $this->actingAs($this->doctor)
        ->putJson("{$this->url}/{$entry->id}", historyData(['title' => 'Edited', 'type' => 'condition', 'patient_visible' => true]))
        ->assertOk()
        ->assertJsonPath('data.title', 'Edited')
        ->assertJsonPath('data.type', 'condition')
        ->assertJsonPath('data.patient_visible', true);

    $this->actingAs($this->doctor)->putJson("{$this->url}/{$entry->id}", historyData(['patient_visible' => false]))
        ->assertJsonPath('data.patient_visible', false);
});

it('deletes an entry', function () {
    $entry = MedicalHistoryEntry::factory()->for($this->patient)->create();

    $this->actingAs($this->doctor)->deleteJson("{$this->url}/{$entry->id}")
        ->assertOk()
        ->assertExactJson(['data' => null, 'message' => 'History entry deleted.']);

    expect(MedicalHistoryEntry::find($entry->id))->toBeNull();
});

it('returns 404 for an entry of another patient', function () {
    $foreign = MedicalHistoryEntry::factory()->for(Patient::factory())->create(['title' => 'Untouched']);

    $this->actingAs($this->doctor)->putJson("{$this->url}/{$foreign->id}", historyData())->assertNotFound();
    $this->actingAs($this->doctor)->deleteJson("{$this->url}/{$foreign->id}")->assertNotFound();

    expect($foreign->fresh()->title)->toBe('Untouched');
});

it('validates type, title and date', function () {
    $this->actingAs($this->doctor)->postJson($this->url, [
        'type' => 'gossip',
        'title' => str_repeat('x', 151),
        'recorded_on' => now()->addDay()->format('Y-m-d'),
    ])->assertUnprocessable()->assertJsonValidationErrors(['type', 'title', 'recorded_on']);
});

it('is for the doctor only', function () {
    $entry = MedicalHistoryEntry::factory()->for($this->patient)->create();
    $user = $this->patient->user;

    $this->actingAs($user)->postJson($this->url, historyData())->assertForbidden();
    $this->actingAs($user)->putJson("{$this->url}/{$entry->id}", historyData())->assertForbidden();
    $this->actingAs($user)->deleteJson("{$this->url}/{$entry->id}")->assertForbidden();
    expect(MedicalHistoryEntry::count())->toBe(1);
});
