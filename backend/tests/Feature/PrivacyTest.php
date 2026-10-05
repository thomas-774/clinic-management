<?php

use App\Models\MedicalHistoryEntry;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

/**
 * Medical data privacy (§8 Phase 2 check, §9.2).
 */
beforeEach(function () {
    $this->withHeader('Accept-Language', 'en');
    $this->doctor = User::factory()->doctor()->create();
    $this->patientA = Patient::factory()->create();
    $this->patientB = Patient::factory()->create();
});

/**
 * @return Collection<int, RouteDefinition>
 */
function apiRoutesUnder(string $prefix)
{
    return collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RouteDefinition $route) => str_starts_with($route->uri(), "api/v1/{$prefix}/"));
}

describe('private history entries', function () {
    beforeEach(function () {
        MedicalHistoryEntry::factory()->for($this->patientA)->visible()->create([
            'title' => 'Asthma', 'details' => 'Uses an inhaler.',
        ]);
        MedicalHistoryEntry::factory()->for($this->patientA)->private()->create([
            'title' => 'Suspected anxiety disorder', 'details' => 'Private clinical note',
        ]);
    });

    it('shows the private entry to the doctor', function () {
        $this->actingAs($this->doctor)->getJson("/api/v1/doctor/patients/{$this->patientA->id}")
            ->assertOk()
            ->assertSee('Suspected anxiety disorder')
            ->assertSee('Private clinical note');
    });

    it('never sends any part of the private entry to the patient', function () {
        $this->actingAs($this->patientA->user)->getJson('/api/v1/patient/profile')
            ->assertOk()
            ->assertJsonCount(1, 'data.simple_history')
            ->assertSee('Asthma')
            ->assertDontSee('Suspected anxiety disorder')
            ->assertDontSee('Private clinical note')
            ->assertJsonMissingPath('data.simple_history.0.patient_visible')
            ->assertJsonMissingPath('data.simple_history.0.type');
    });

    it('hides an entry from the patient as soon as the doctor makes it private', function () {
        $entry = $this->patientA->medicalHistoryEntries()->where('title', 'Asthma')->sole();

        $this->actingAs($this->doctor)->putJson(
            "/api/v1/doctor/patients/{$this->patientA->id}/history/{$entry->id}",
            ['type' => 'condition', 'title' => 'Asthma', 'recorded_on' => '2026-01-01', 'patient_visible' => false],
        )->assertOk();

        app('auth')->forgetGuards();
        $this->actingAs($this->patientA->user)->getJson('/api/v1/patient/profile')
            ->assertJsonPath('data.simple_history', []);
    });
});

describe('one patient cannot reach another patient\'s data', function () {
    it('has no patient route that takes an id', function () {
        $routes = apiRoutesUnder('patient');

        expect($routes)->not->toBeEmpty();
        $routes->each(fn (RouteDefinition $route) => expect($route->parameterNames())->toBe([], $route->uri()));
    });

    it('shows each patient only their own profile', function () {
        MedicalHistoryEntry::factory()->for($this->patientB)->visible()->create(['title' => 'B only']);

        $this->actingAs($this->patientA->user)->getJson('/api/v1/patient/profile')
            ->assertJsonPath('data.id', $this->patientA->id)
            ->assertDontSee('B only')
            ->assertDontSee($this->patientB->user->phone);
    });

    it('ignores an id smuggled into the profile request', function () {
        $this->actingAs($this->patientA->user)
            ->getJson("/api/v1/patient/profile?patient_id={$this->patientB->id}&id={$this->patientB->id}")
            ->assertJsonPath('data.id', $this->patientA->id);

        $this->actingAs($this->patientA->user)
            ->patchJson('/api/v1/patient/profile', ['patient_id' => $this->patientB->id, 'address' => 'Changed'])
            ->assertOk();

        expect($this->patientB->fresh()->address)->not->toBe('Changed')
            ->and($this->patientA->fresh()->address)->toBe('Changed');
    });
});

describe('patients and doctor routes', function () {
    it('returns 403 for /doctor/patients', function () {
        $this->actingAs($this->patientA->user)->getJson('/api/v1/doctor/patients')->assertForbidden();
    });

    it('returns 403 for every doctor route, with any method', function () {
        $entry = MedicalHistoryEntry::factory()->for($this->patientA)->create();
        $ids = ['patient' => $this->patientA->id, 'medicalHistoryEntry' => $entry->id];

        apiRoutesUnder('doctor')->each(function (RouteDefinition $route) use ($ids) {
            $uri = preg_replace_callback('/\{(\w+)\??\}/', fn ($m) => $ids[$m[1]] ?? 1, $route->uri());
            $method = collect($route->methods())->reject(fn ($m) => $m === 'HEAD')->first();

            app('auth')->forgetGuards();
            $this->actingAs($this->patientA->user)
                ->json($method, "/{$uri}")
                ->assertForbidden();
        });

        expect($entry->fresh())->not->toBeNull();
    });
});
