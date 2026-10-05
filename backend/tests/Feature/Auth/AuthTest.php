<?php

use App\Enums\UserRole;
use App\Models\Patient;
use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

function registrationData(array $overrides = []): array
{
    return array_merge([
        'name' => 'Mona Ali',
        'phone' => '01012345678',
        'email' => 'mona@example.com',
        'password' => 'secret-pass',
        'password_confirmation' => 'secret-pass',
        'address' => '12 Tahrir St, Cairo',
    ], $overrides);
}

beforeEach(function () {
    $this->withHeader('Accept-Language', 'en');
});

describe('register', function () {
    it('creates a patient user with a patient record and returns a token', function () {
        $response = $this->postJson('/api/v1/auth/register', registrationData())
            ->assertCreated()
            ->assertJsonPath('message', 'Account created.')
            ->assertJsonPath('data.user.role', 'patient')
            ->assertJsonPath('data.user.phone', '01012345678');

        $user = User::where('phone', '01012345678')->sole();
        expect($user->role)->toBe(UserRole::Patient)
            ->and($user->patient->address)->toBe('12 Tahrir St, Cairo')
            ->and($response->json('data.user.patient_id'))->toBe($user->patient->id)
            ->and($response->json('data.token'))->toBeString();
    });

    it('accepts a patient without an email', function () {
        $this->postJson('/api/v1/auth/register', registrationData(['email' => '']))
            ->assertCreated()
            ->assertJsonPath('data.user.email', null);
    });

    it('always creates a patient, even if a role is sent', function () {
        $this->postJson('/api/v1/auth/register', registrationData(['role' => 'doctor']))->assertCreated();

        expect(User::sole()->role)->toBe(UserRole::Patient);
    });

    it('rejects a duplicate phone, a duplicate email and a bad confirmation', function () {
        User::factory()->create(['phone' => '01012345678', 'email' => 'mona@example.com']);

        $this->postJson('/api/v1/auth/register', registrationData(['password_confirmation' => 'other-pass']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['phone', 'email', 'password']);

        expect(Patient::count())->toBe(0);
    });

    it('requires name, phone, password and address', function () {
        $this->postJson('/api/v1/auth/register', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'phone', 'password', 'address'])
            ->assertJsonMissingValidationErrors(['email']);
    });

    it('rejects a password shorter than 8 characters', function () {
        $this->postJson('/api/v1/auth/register', registrationData(['password' => 'short', 'password_confirmation' => 'short']))
            ->assertJsonValidationErrors(['password']);
    });
});

describe('login', function () {
    beforeEach(function () {
        $this->user = User::factory()->create([
            'phone' => '01012345678',
            'email' => 'mona@example.com',
            'password' => 'secret-pass',
        ]);
        Patient::factory()->for($this->user)->create();
    });

    it('logs in by phone', function () {
        $this->postJson('/api/v1/auth/login', ['login' => '010 1234 5678', 'password' => 'secret-pass'])
            ->assertOk()
            ->assertJsonPath('data.user.id', $this->user->id)
            ->assertJsonPath('data.user.role', 'patient')
            ->assertJsonStructure(['data' => ['token', 'user' => ['id', 'name', 'phone', 'email', 'role', 'patient_id']], 'message']);
    });

    it('logs in by email', function () {
        $this->postJson('/api/v1/auth/login', ['login' => 'mona@example.com', 'password' => 'secret-pass'])
            ->assertOk()
            ->assertJsonPath('data.user.id', $this->user->id);
    });

    it('logs the doctor in and reports the doctor role', function () {
        User::factory()->doctor()->create(['phone' => '01000000000', 'password' => 'doctor-pass']);

        $this->postJson('/api/v1/auth/login', ['login' => '01000000000', 'password' => 'doctor-pass'])
            ->assertOk()
            ->assertJsonPath('data.user.role', 'doctor')
            ->assertJsonMissingPath('data.user.patient_id');
    });

    it('rejects a wrong password without saying which part was wrong', function () {
        $this->postJson('/api/v1/auth/login', ['login' => '01012345678', 'password' => 'wrong-pass'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.login.0', 'These credentials do not match our records.');

        $this->postJson('/api/v1/auth/login', ['login' => '01099999999', 'password' => 'secret-pass'])
            ->assertJsonPath('errors.login.0', 'These credentials do not match our records.');
    });

    it('allows 5 attempts per minute, then returns 429', function () {
        foreach (range(1, 5) as $attempt) {
            $this->postJson('/api/v1/auth/login', ['login' => '01012345678', 'password' => 'wrong-pass'])
                ->assertUnprocessable();
        }

        $this->postJson('/api/v1/auth/login', ['login' => '01012345678', 'password' => 'secret-pass'])
            ->assertTooManyRequests()
            ->assertHeader('Retry-After')
            ->assertJsonStructure(['message']);
    });
});

describe('me and logout', function () {
    beforeEach(function () {
        $this->user = User::factory()->create(['password' => 'secret-pass']);
        $this->patient = Patient::factory()->for($this->user)->create();
        $this->token = $this->user->createToken('api')->plainTextToken;
    });

    it('returns the current user with role and patient id', function () {
        $this->withToken($this->token)->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.id', $this->user->id)
            ->assertJsonPath('data.role', 'patient')
            ->assertJsonPath('data.patient_id', $this->patient->id)
            ->assertJsonMissingPath('data.password');
    });

    it('requires a token for /me', function () {
        $this->getJson('/api/v1/me')->assertUnauthorized();
    });

    it('revokes only the current token on logout', function () {
        $otherDevice = $this->user->createToken('api')->plainTextToken;

        $this->withToken($this->token)->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertExactJson(['data' => null, 'message' => 'Logged out.']);

        expect(PersonalAccessToken::count())->toBe(1);

        app('auth')->forgetGuards();
        $this->withToken($this->token)->getJson('/api/v1/me')->assertUnauthorized();

        app('auth')->forgetGuards();
        $this->withToken($otherDevice)->getJson('/api/v1/me')->assertOk();
    });
});
