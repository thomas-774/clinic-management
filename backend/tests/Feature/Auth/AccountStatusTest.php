<?php

use App\Enums\UserRole;
use App\Models\User;

// T8-01: the assistant role and deactivated accounts (FR-I.1).

beforeEach(function () {
    $this->withHeader('Accept-Language', 'en');
});

it('lets an assistant log in and reports the assistant role', function () {
    User::factory()->assistant()->create(['phone' => '01022223333', 'password' => 'secret-pass']);

    $token = $this->postJson('/api/v1/auth/login', ['login' => '01022223333', 'password' => 'secret-pass'])
        ->assertOk()
        ->assertJsonPath('data.user.role', 'assistant')
        ->assertJsonMissingPath('data.user.patient_id')
        ->json('data.token');

    $this->withToken($token)->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.role', 'assistant');
});

it('creates active users by default', function () {
    $user = User::factory()->assistant()->create();

    expect($user->isAssistant())->toBeTrue()
        ->and($user->role)->toBe(UserRole::Assistant)
        ->and($user->is_active)->toBeTrue()
        ->and($user->refresh()->is_active)->toBeTrue();
});

it('refuses to log in an inactive account with the wrong-password message', function () {
    User::factory()->assistant()->inactive()->create(['phone' => '01022223333', 'password' => 'secret-pass']);

    $this->postJson('/api/v1/auth/login', ['login' => '01022223333', 'password' => 'secret-pass'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['login' => __('auth.failed')]);
});

it('rejects the token of an account deactivated after login', function () {
    $assistant = User::factory()->assistant()->create();
    $token = $assistant->createToken('api')->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/me')->assertOk();

    $assistant->update(['is_active' => false]);
    app('auth')->forgetGuards();

    $this->withToken($token)->getJson('/api/v1/me')
        ->assertUnauthorized()
        ->assertJsonPath('message', 'Unauthenticated.');
});

it('keeps the assistant out of the patient and doctor areas', function () {
    $assistant = User::factory()->assistant()->create();

    $this->actingAs($assistant)->getJson('/api/v1/patient/profile')->assertForbidden();
    $this->actingAs($assistant)->getJson('/api/v1/doctor/patients')->assertForbidden();
});
