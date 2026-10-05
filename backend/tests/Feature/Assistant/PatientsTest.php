<?php

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\MedicalHistoryEntry;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

// T8-04: the assistant's patients API — contact info and money only (FR-I.2, FR-I.3).

beforeEach(function () {
    $this->withHeader('Accept-Language', 'en');
    Carbon::setTestNow(Carbon::parse('2026-10-05 17:20', 'Africa/Cairo'));
    $this->doctor = User::factory()->doctor()->create();
    $this->assistant = User::factory()->assistant()->create(['name' => 'Amal Saad']);
});

function assistantPatient(string $name, string $phone, array $attributes = []): Patient
{
    return Patient::factory()->for(User::factory()->create(['name' => $name, 'phone' => $phone]))->create($attributes);
}

describe('GET /assistant/patients', function () {
    it('lists and searches like the doctor list', function () {
        assistantPatient('Mona Ali', '01011112222');
        assistantPatient('Ahmed Hassan', '01233334444');

        $this->actingAs($this->assistant)->getJson('/api/v1/assistant/patients')
            ->assertOk()
            ->assertJsonPath('data.*.name', ['Ahmed Hassan', 'Mona Ali'])
            ->assertJsonStructure(['data' => [['id', 'name', 'phone', 'last_visit_date']], 'meta' => ['current_page', 'last_page', 'total']]);

        $this->actingAs($this->assistant)->getJson('/api/v1/assistant/patients?search=1111')
            ->assertJsonPath('data.*.name', ['Mona Ali']);
    });
});

describe('POST /assistant/patients', function () {
    it('registers a new patient without an illness and returns the password once', function () {
        $response = $this->actingAs($this->assistant)->postJson('/api/v1/assistant/patients', [
            'name' => 'Sara Adel',
            'phone' => '0155 555 6666',
            'address' => '5 Nile St',
            'date_of_birth' => '1990-04-01',
            'gender' => 'female',
            'current_illness' => 'should be ignored',
        ])
            ->assertCreated()
            ->assertJsonPath('message', 'Patient account created.')
            ->assertJsonPath('data.name', 'Sara Adel')
            ->assertJsonPath('data.phone', '01555556666')
            ->assertJsonPath('data.outstanding_balance', '0.00')
            ->assertJsonMissingPath('data.current_illness');

        $patient = Patient::sole();
        expect($patient->current_illness)->toBeNull()
            ->and($patient->user->role)->toBe(UserRole::Patient)
            ->and(Hash::check($response->json('data.initial_password'), $patient->user->password))->toBeTrue();
    });

    it('rejects a phone that already exists', function () {
        assistantPatient('Mona Ali', '01011112222');

        $this->actingAs($this->assistant)->postJson('/api/v1/assistant/patients', [
            'name' => 'Mona Again', 'phone' => '01011112222', 'address' => 'x',
        ])->assertJsonValidationErrors(['phone']);
    });
});

describe('GET /assistant/patients/{id}', function () {
    beforeEach(function () {
        $this->patient = assistantPatient('Mona Ali', '01011112222', ['current_illness' => 'SECRET-ILLNESS']);
        MedicalHistoryEntry::factory()->for($this->patient)->create(['title' => 'SECRET-HISTORY', 'details' => 'SECRET-HISTORY']);

        $this->visit = Visit::factory()->for($this->patient)->create([
            'visit_date' => '2026-10-05', 'total_amount' => 1500, 'work_done' => 'SECRET-WORK',
        ]);
        Payment::factory()->for($this->visit)->create(['amount' => 1000, 'paid_at' => now(), 'recorded_by' => $this->assistant->id]);
        Visit::factory()->for($this->patient)->create(['visit_date' => '2026-09-01', 'total_amount' => 400, 'work_done' => 'SECRET-WORK']);
    });

    it('shows contact info, visits money and the balance', function () {
        $this->actingAs($this->assistant)->getJson("/api/v1/assistant/patients/{$this->patient->id}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Mona Ali')
            ->assertJsonPath('data.outstanding_balance', '900.00')
            ->assertJsonPath('data.visits.0.visit_date', '2026-10-05')
            ->assertJsonPath('data.visits.0.paid', '1000.00')
            ->assertJsonPath('data.visits.0.remaining', '500.00')
            ->assertJsonPath('data.visits.0.payment_status', 'partially_paid')
            ->assertJsonPath('data.visits.0.payments.0.recorded_by_name', 'Amal Saad')
            ->assertJsonPath('data.visits.1.payment_status', 'unpaid');
    });

    it('never sends medical data', function () {
        $response = $this->actingAs($this->assistant)->getJson("/api/v1/assistant/patients/{$this->patient->id}")->assertOk();

        expect($response->getContent())
            ->not->toContain('SECRET')
            ->not->toContain('current_illness')
            ->not->toContain('work_done')
            ->not->toContain('history');
    });

    it('shows the next appointment', function () {
        Appointment::factory()->for($this->patient)->create([
            'doctor_id' => $this->doctor->id,
            'start_at' => Carbon::parse('2026-10-06 10:00', 'Africa/Cairo'),
            'end_at' => Carbon::parse('2026-10-06 10:30', 'Africa/Cairo'),
        ]);

        $this->actingAs($this->assistant)->getJson("/api/v1/assistant/patients/{$this->patient->id}")
            ->assertJsonPath('data.next_appointment.start_at', '2026-10-06T10:00:00+03:00');
    });
});

describe('PUT /assistant/patients/{id}', function () {
    it('updates contact info but never the illness', function () {
        $patient = assistantPatient('Mona Ali', '01011112222', ['current_illness' => 'Toothache']);

        $this->actingAs($this->assistant)->putJson("/api/v1/assistant/patients/{$patient->id}", [
            'name' => 'Mona A. Ali',
            'phone' => '01011112222',
            'address' => 'New address',
            'current_illness' => 'changed',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Patient updated.')
            ->assertJsonPath('data.name', 'Mona A. Ali')
            ->assertJsonPath('data.address', 'New address')
            ->assertJsonMissingPath('data.current_illness');

        expect($patient->refresh()->current_illness)->toBe('Toothache');
    });
});

it('is for the assistant only', function (string $role) {
    $user = User::factory()->{$role}()->create();
    $patient = Patient::factory()->create();

    $this->actingAs($user)->getJson('/api/v1/assistant/patients')->assertForbidden();
    $this->actingAs($user)->postJson('/api/v1/assistant/patients', [])->assertForbidden();
    $this->actingAs($user)->getJson("/api/v1/assistant/patients/{$patient->id}")->assertForbidden();
    $this->actingAs($user)->putJson("/api/v1/assistant/patients/{$patient->id}", [])->assertForbidden();
})->with(['doctor', 'patient']);

it('keeps the assistant out of the doctor patient pages and history', function () {
    $patient = Patient::factory()->create();

    $this->actingAs($this->assistant)->getJson("/api/v1/doctor/patients/{$patient->id}")->assertForbidden();
    $this->actingAs($this->assistant)->postJson("/api/v1/doctor/patients/{$patient->id}/history", [])->assertForbidden();
});
