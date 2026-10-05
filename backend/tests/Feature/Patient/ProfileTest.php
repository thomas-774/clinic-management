<?php

use App\Models\MedicalHistoryEntry;
use App\Models\Patient;
use App\Models\User;

beforeEach(function () {
    $this->withHeader('Accept-Language', 'en');
    $this->patient = Patient::factory()->create([
        'address' => 'Old address',
        'current_illness' => 'Toothache',
    ]);
    $this->user = $this->patient->user;
});

describe('GET /patient/profile', function () {
    it('returns personal info, illness, simple history and placeholders', function () {
        $this->actingAs($this->user)->getJson('/api/v1/patient/profile')
            ->assertOk()
            ->assertJsonPath('data.id', $this->patient->id)
            ->assertJsonPath('data.name', $this->user->name)
            ->assertJsonPath('data.phone', $this->user->phone)
            ->assertJsonPath('data.address', 'Old address')
            ->assertJsonPath('data.current_illness', 'Toothache')
            ->assertJsonPath('data.next_appointment', null)
            ->assertJsonPath('data.outstanding_balance', '0.00')
            ->assertJsonPath('data.simple_history', []);
    });

    it('lists only visible history entries, newest first', function () {
        $factory = MedicalHistoryEntry::factory()->for($this->patient);
        $factory->visible()->create(['title' => 'Old visible', 'recorded_on' => '2024-01-01']);
        $factory->visible()->create(['title' => 'New visible', 'recorded_on' => '2026-01-01']);
        $factory->private()->create(['title' => 'Private note', 'recorded_on' => '2025-01-01']);

        $response = $this->actingAs($this->user)->getJson('/api/v1/patient/profile');

        expect($response->json('data.simple_history.*.title'))->toBe(['New visible', 'Old visible']);
    });
});

describe('PATCH /patient/profile', function () {
    it('updates phone and address', function () {
        $this->actingAs($this->user)
            ->patchJson('/api/v1/patient/profile', ['phone' => '010 9999 8888', 'address' => 'New address'])
            ->assertOk()
            ->assertJsonPath('message', 'Profile updated.')
            ->assertJsonPath('data.phone', '01099998888')
            ->assertJsonPath('data.address', 'New address');

        expect($this->user->fresh()->phone)->toBe('01099998888')
            ->and($this->patient->fresh()->address)->toBe('New address');
    });

    it('ignores name, current_illness and every other field', function () {
        $this->actingAs($this->user)->patchJson('/api/v1/patient/profile', [
            'name' => 'Hacked Name',
            'current_illness' => 'Nothing',
            'role' => 'doctor',
            'address' => 'New address',
        ])->assertOk();

        $user = $this->user->fresh();
        expect($user->name)->not->toBe('Hacked Name')
            ->and($user->isPatient())->toBeTrue()
            ->and($this->patient->fresh()->current_illness)->toBe('Toothache')
            ->and($this->patient->fresh()->address)->toBe('New address');
    });

    it('allows keeping the same phone but rejects one used by another account', function () {
        User::factory()->create(['phone' => '01077777777']);

        $this->actingAs($this->user)->patchJson('/api/v1/patient/profile', ['phone' => $this->user->phone])
            ->assertOk();
        $this->actingAs($this->user)->patchJson('/api/v1/patient/profile', ['phone' => '01077777777'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['phone']);
    });

    it('rejects an empty address', function () {
        $this->actingAs($this->user)->patchJson('/api/v1/patient/profile', ['address' => ''])
            ->assertJsonValidationErrors(['address']);
    });
});

it('is for patients only', function () {
    $this->actingAs(User::factory()->doctor()->create())->getJson('/api/v1/patient/profile')->assertForbidden();
    app('auth')->forgetGuards();
    $this->getJson('/api/v1/patient/profile')->assertUnauthorized();
});
