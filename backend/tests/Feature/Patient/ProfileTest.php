<?php

use App\Models\Appointment;
use App\Models\MedicalHistoryEntry;
use App\Models\Patient;
use App\Models\User;
use Database\Seeders\DoctorSeeder;

beforeEach(function () {
    $this->withHeader('Accept-Language', 'en');
    $this->patient = Patient::factory()->create([
        'address' => 'Old address',
        'current_illness' => 'Toothache',
    ]);
    $this->user = $this->patient->user;
});

describe('GET /patient/profile', function () {
    it('returns personal info, illness, simple history, no next appointment and the balance placeholder', function () {
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

describe('next_appointment (T4-11)', function () {
    beforeEach(function () {
        $this->travelTo('2026-10-05 08:00:00');
        $this->mine = fn (string $start, string $status = 'booked') => Appointment::factory()
            ->for($this->patient)->at($start)->create(['status' => $status]);
    });

    it('is the earliest booked or checked-in appointment still ahead', function () {
        ($this->mine)('2026-10-04 17:00', 'completed');
        ($this->mine)('2026-10-05 17:00', 'cancelled');
        $next = ($this->mine)('2026-10-06 17:00');
        ($this->mine)('2026-10-08 17:00');
        Appointment::factory()->at('2026-10-05 18:00')->create(); // someone else's

        $this->actingAs($this->user)->getJson('/api/v1/patient/profile')
            ->assertJsonPath('data.next_appointment.id', $next->id)
            ->assertJsonPath('data.next_appointment.start_at', '2026-10-06T17:00:00+03:00')
            ->assertJsonPath('data.next_appointment.end_at', '2026-10-06T17:45:00+03:00')
            ->assertJsonPath('data.next_appointment.status', 'booked')
            ->assertJsonPath('data.next_appointment.can_cancel', true);
    });

    it('keeps a checked-in appointment until it ends', function () {
        $now = ($this->mine)('2026-10-05 07:30', 'checked_in'); // ends 08:15

        $this->actingAs($this->user)->getJson('/api/v1/patient/profile')
            ->assertJsonPath('data.next_appointment.id', $now->id)
            ->assertJsonPath('data.next_appointment.status', 'checked_in');
    });

    it('is null when nothing is ahead', function () {
        ($this->mine)('2026-10-04 17:00', 'no_show');
        ($this->mine)('2026-10-06 17:00', 'cancelled');

        $this->actingAs($this->user)->getJson('/api/v1/patient/profile')
            ->assertJsonPath('data.next_appointment', null);
    });

    it('shows the appointment right after booking it', function () {
        config()->set('clinic.doctor', ['name' => 'Dr', 'phone' => '01000000000', 'email' => null, 'password' => 'password']);
        $this->seed(DoctorSeeder::class);

        $this->actingAs($this->user)->postJson('/api/v1/patient/appointments', ['start_at' => '2026-10-06T18:30:00+03:00'])->assertCreated();

        $this->actingAs($this->user)->getJson('/api/v1/patient/profile')
            ->assertJsonPath('data.next_appointment.start_at', '2026-10-06T18:30:00+03:00');
    });
});
