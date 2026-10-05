<?php

use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Support\Facades\Hash;

function patientNamed(string $name, string $phone): Patient
{
    return Patient::factory()->for(User::factory()->create(['name' => $name, 'phone' => $phone]))->create();
}

beforeEach(function () {
    $this->withHeader('Accept-Language', 'en');
    $this->doctor = User::factory()->doctor()->create();
});

describe('GET /doctor/patients', function () {
    it('lists patients sorted by name with their last visit date', function () {
        $mona = patientNamed('Mona Ali', '01011112222');
        patientNamed('Ahmed Hassan', '01233334444');
        Visit::factory()->for($mona)->create(['visit_date' => '2026-09-01']);
        Visit::factory()->for($mona)->create(['visit_date' => '2026-09-20']);

        $this->actingAs($this->doctor)->getJson('/api/v1/doctor/patients')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Ahmed Hassan')
            ->assertJsonPath('data.0.last_visit_date', null)
            ->assertJsonPath('data.1.name', 'Mona Ali')
            ->assertJsonPath('data.1.last_visit_date', '2026-09-20')
            ->assertJsonStructure(['data' => [['id', 'name', 'phone', 'last_visit_date']], 'links', 'meta' => ['current_page', 'last_page', 'total'], 'message']);
    });

    it('finds patients by part of the name or the phone', function () {
        patientNamed('Mona Ali', '01011112222');
        patientNamed('Ahmed Hassan', '01233334444');
        patientNamed('Monir Saad', '01555556666');

        $names = fn (string $search) => $this->actingAs($this->doctor)
            ->getJson('/api/v1/doctor/patients?search='.urlencode($search))
            ->json('data.*.name');

        expect($names('mon'))->toBe(['Mona Ali', 'Monir Saad'])
            ->and($names('3333'))->toBe(['Ahmed Hassan'])
            ->and($names('0101'))->toBe(['Mona Ali'])
            ->and($names('nobody'))->toBe([])
            ->and($names('%'))->toBe([]);
    });

    it('pages 20 patients at a time', function () {
        Patient::factory()->count(25)->create();

        $this->actingAs($this->doctor)->getJson('/api/v1/doctor/patients?page=2')
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.total', 25)
            ->assertJsonPath('meta.per_page', 20);
    });
});

describe('POST /doctor/patients', function () {
    it('creates a patient who can log in with the returned password', function () {
        $response = $this->actingAs($this->doctor)->postJson('/api/v1/doctor/patients', [
            'name' => 'Phone Patient',
            'phone' => '01044445555',
            'address' => 'Giza',
            'gender' => 'female',
            'date_of_birth' => '1990-05-01',
            'current_illness' => 'Gum pain',
        ])->assertCreated()
            ->assertJsonPath('message', 'Patient account created.')
            ->assertJsonPath('data.name', 'Phone Patient')
            ->assertJsonPath('data.current_illness', 'Gum pain')
            ->assertJsonPath('data.date_of_birth', '1990-05-01');

        $password = $response->json('data.initial_password');
        expect($password)->toMatch('/^[a-z0-9]{8}$/');

        $user = User::where('phone', '01044445555')->sole();
        expect($user->isPatient())->toBeTrue()
            ->and($user->password)->not->toBe($password)
            ->and(Hash::check($password, $user->password))->toBeTrue();

        app('auth')->forgetGuards();
        $this->postJson('/api/v1/auth/login', ['login' => '01044445555', 'password' => $password])
            ->assertOk()
            ->assertJsonPath('data.user.role', 'patient');
    });

    it('never returns the initial password again', function () {
        $this->actingAs($this->doctor)->postJson('/api/v1/doctor/patients', [
            'name' => 'P', 'phone' => '01044445555', 'address' => 'Giza',
        ])->assertCreated();

        $this->actingAs($this->doctor)->getJson('/api/v1/doctor/patients')
            ->assertJsonMissingPath('data.0.initial_password');
    });

    it('rejects a duplicate phone and requires name, phone and address', function () {
        User::factory()->create(['phone' => '01044445555']);

        $this->actingAs($this->doctor)->postJson('/api/v1/doctor/patients', ['phone' => '01044445555'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'phone', 'address']);
    });

    it('rejects a birth date in the future and an unknown gender', function () {
        $this->actingAs($this->doctor)->postJson('/api/v1/doctor/patients', [
            'name' => 'P', 'phone' => '01044445555', 'address' => 'Giza',
            'date_of_birth' => now()->addDay()->format('Y-m-d'), 'gender' => 'other',
        ])->assertJsonValidationErrors(['date_of_birth', 'gender']);
    });
});

it('is for the doctor only', function () {
    $patientUser = Patient::factory()->create()->user;

    $this->actingAs($patientUser)->getJson('/api/v1/doctor/patients')->assertForbidden();
    $this->actingAs($patientUser)->postJson('/api/v1/doctor/patients', [
        'name' => 'P', 'phone' => '01044445555', 'address' => 'Giza',
    ])->assertForbidden();

    expect(User::where('phone', '01044445555')->exists())->toBeFalse();
});
