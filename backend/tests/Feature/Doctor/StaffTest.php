<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

// T8-02: Settings → Staff (FR-I.1).

beforeEach(function () {
    $this->withHeader('Accept-Language', 'en');
    $this->doctor = User::factory()->doctor()->create();
});

describe('GET /doctor/staff', function () {
    it('lists only assistants, active first, then by name', function () {
        User::factory()->assistant()->create(['name' => 'Zeinab']);
        User::factory()->assistant()->create(['name' => 'Amal']);
        User::factory()->assistant()->inactive()->create(['name' => 'Basma']);
        User::factory()->patient()->create(['name' => 'Patient']);

        $this->actingAs($this->doctor)->getJson('/api/v1/doctor/staff')
            ->assertOk()
            ->assertJsonPath('data.*.name', ['Amal', 'Zeinab', 'Basma'])
            ->assertJsonPath('data.2.is_active', false)
            ->assertJsonStructure(['data' => [['id', 'name', 'phone', 'email', 'role', 'is_active']], 'message'])
            ->assertJsonMissingPath('data.0.initial_password');
    });
});

describe('POST /doctor/staff', function () {
    it('creates an assistant and returns the initial password once', function () {
        $response = $this->actingAs($this->doctor)->postJson('/api/v1/doctor/staff', [
            'name' => 'Amal Saad',
            'phone' => '010 2222-3333',
            'email' => '',
        ])
            ->assertCreated()
            ->assertJsonPath('message', 'Assistant account created.')
            ->assertJsonPath('data.role', 'assistant')
            ->assertJsonPath('data.phone', '01022223333')
            ->assertJsonPath('data.email', null)
            ->assertJsonPath('data.is_active', true);

        $password = $response->json('data.initial_password');
        $assistant = User::where('phone', '01022223333')->sole();

        expect($password)->toMatch('/^[a-z2-9]{8}$/')
            ->and($assistant->role)->toBe(UserRole::Assistant)
            ->and(Hash::check($password, $assistant->password))->toBeTrue();

        $this->postJson('/api/v1/auth/login', ['login' => '01022223333', 'password' => $password])
            ->assertOk()
            ->assertJsonPath('data.user.role', 'assistant');
    });

    it('validates name and a unique phone', function () {
        User::factory()->create(['phone' => '01022223333']);

        $this->actingAs($this->doctor)->postJson('/api/v1/doctor/staff', ['phone' => '01022223333'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'phone']);
    });

    it('ignores a role sent by the client', function () {
        $this->actingAs($this->doctor)->postJson('/api/v1/doctor/staff', [
            'name' => 'Sneaky',
            'phone' => '01022223333',
            'role' => 'doctor',
        ])->assertCreated();

        expect(User::where('phone', '01022223333')->sole()->role)->toBe(UserRole::Assistant);
    });
});

describe('PUT /doctor/staff/{id}', function () {
    beforeEach(function () {
        $this->assistant = User::factory()->assistant()->create(['name' => 'Amal', 'phone' => '01022223333']);
    });

    it('edits name and phone', function () {
        $this->actingAs($this->doctor)->putJson("/api/v1/doctor/staff/{$this->assistant->id}", [
            'name' => 'Amal Saad',
            'phone' => '01022223333',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Assistant updated.')
            ->assertJsonPath('data.name', 'Amal Saad')
            ->assertJsonMissingPath('data.initial_password');
    });

    it('deactivates the assistant and logs out every session', function () {
        $this->assistant->createToken('api');

        $this->actingAs($this->doctor)->putJson("/api/v1/doctor/staff/{$this->assistant->id}", ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        expect($this->assistant->tokens()->count())->toBe(0);

        $this->actingAs($this->doctor)->putJson("/api/v1/doctor/staff/{$this->assistant->id}", ['is_active' => true])
            ->assertJsonPath('data.is_active', true);
    });

    it('resets the password, returns it once and logs out every session', function () {
        $this->assistant->createToken('api');

        $password = $this->actingAs($this->doctor)
            ->putJson("/api/v1/doctor/staff/{$this->assistant->id}", ['reset_password' => true])
            ->assertOk()
            ->json('data.initial_password');

        expect($password)->toMatch('/^[a-z2-9]{8}$/')
            ->and(Hash::check($password, $this->assistant->refresh()->password))->toBeTrue()
            ->and($this->assistant->tokens()->count())->toBe(0);
    });

    it('cannot edit a patient or the doctor through staff', function () {
        $patient = User::factory()->patient()->create();

        $this->actingAs($this->doctor)->putJson("/api/v1/doctor/staff/{$patient->id}", ['is_active' => false])->assertNotFound();
        $this->actingAs($this->doctor)->putJson("/api/v1/doctor/staff/{$this->doctor->id}", ['is_active' => false])->assertNotFound();

        expect($patient->refresh()->is_active)->toBeTrue();
    });

    it('rejects a phone used by another account', function () {
        User::factory()->create(['phone' => '01055556666']);

        $this->actingAs($this->doctor)->putJson("/api/v1/doctor/staff/{$this->assistant->id}", ['phone' => '01055556666'])
            ->assertJsonValidationErrors(['phone']);
    });
});

it('is for the doctor only', function (string $role) {
    $user = User::factory()->{$role}()->create();
    $assistant = User::factory()->assistant()->create();

    $this->actingAs($user)->getJson('/api/v1/doctor/staff')->assertForbidden();
    $this->actingAs($user)->postJson('/api/v1/doctor/staff', [])->assertForbidden();
    $this->actingAs($user)->putJson("/api/v1/doctor/staff/{$assistant->id}", [])->assertForbidden();
})->with(['patient', 'assistant']);
