<?php

use App\Models\Appointment;
use App\Models\Drug;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;

/*
 * T9-07: prescription permissions and privacy (§2, §9.1, §9.2, FR-J.7, RX-3).
 * Only the doctor reaches the drug catalogue and prescriptions in v1.
 */

beforeEach(function () {
    $this->withHeader('Accept-Language', 'en');
    $this->travelTo('2026-10-07 10:00:00');
    $this->doctor = User::factory()->doctor()->create();
    $this->assistant = User::factory()->assistant()->create();
    $this->patient = Patient::factory()->create();

    $this->drug = Drug::factory()->create([
        'trade_name' => 'SECRETDRUG 500 mg',
        'uses' => 'SECRET-USES',
        'warnings' => 'SECRET-WARNINGS',
        'active_ingredients' => [['name' => 'secretamine', 'note' => 'SECRET-NOTE']],
    ]);
    $this->visit = Visit::factory()->for($this->patient)->create(['visit_date' => '2026-10-07', 'total_amount' => 500]);
    $this->prescription = Prescription::factory()->forVisit($this->visit)->for($this->doctor, 'doctor')
        ->create(['notes' => 'SECRET-RX-NOTES']);
    PrescriptionItem::factory()->for($this->prescription)->forDrug($this->drug)
        ->create(['instructions' => 'SECRET-INSTRUCTIONS', 'position' => 1]);
});

/**
 * Every drug and prescription route, as [method, uri template]. The coverage
 * test below fails if a new route is added without being listed here.
 *
 * @return list<array{0: string, 1: string}>
 */
function prescriptionRoutes(): array
{
    return [
        ['GET', '/api/v1/doctor/drugs/search?q=se'],
        ['GET', '/api/v1/doctor/drugs'],
        ['POST', '/api/v1/doctor/drugs'],
        ['GET', '/api/v1/doctor/drugs/{drug}'],
        ['PUT', '/api/v1/doctor/drugs/{drug}'],
        ['GET', '/api/v1/doctor/patients/{patient}/prescriptions'],
        ['POST', '/api/v1/doctor/patients/{patient}/prescriptions'],
        ['GET', '/api/v1/doctor/prescriptions/{prescription}'],
        ['PUT', '/api/v1/doctor/prescriptions/{prescription}'],
        ['DELETE', '/api/v1/doctor/prescriptions/{prescription}'],
    ];
}

dataset('prescription routes', prescriptionRoutes());

/**
 * The template with the ids of this test's real records.
 */
function prescriptionUri(string $template): string
{
    return strtr($template, [
        '{drug}' => test()->drug->id,
        '{patient}' => test()->patient->id,
        '{prescription}' => test()->prescription->id,
    ]);
}

/**
 * Every key in a decoded JSON body, at any depth.
 *
 * @return list<string>
 */
function jsonKeys(mixed $data): array
{
    if (! is_array($data)) {
        return [];
    }

    return collect($data)
        ->flatMap(fn ($value, $key) => [(string) $key, ...jsonKeys($value)])
        ->values()
        ->all();
}

describe('route permissions', function () {
    it('lists every drug and prescription route', function () {
        $registered = collect(Route::getRoutes()->getRoutes())
            ->filter(fn (RoutingRoute $route) => preg_match('#^api/v1/.*(drugs|prescriptions)#', $route->uri()))
            ->flatMap(fn (RoutingRoute $route) => collect($route->methods())
                ->reject(fn ($method) => $method === 'HEAD')
                ->map(fn ($method) => $method.' /'.preg_replace('/\{(\w+)\}/', '{$1}', $route->uri())))
            ->sort()->values()->all();

        $listed = collect(prescriptionRoutes())
            ->map(fn (array $route) => $route[0].' '.strtok($route[1], '?'))
            ->sort()->values()->all();

        expect($registered)->not->toBeEmpty()->and($listed)->toBe($registered);
    });

    it('answers 403 to a patient', function (string $method, string $uri) {
        $this->actingAs($this->patient->user)->json($method, prescriptionUri($uri))->assertForbidden();
    })->with('prescription routes');

    it('answers 403 to the assistant', function (string $method, string $uri) {
        $this->actingAs($this->assistant)->json($method, prescriptionUri($uri))->assertForbidden();
    })->with('prescription routes');

    it('answers 401 to a guest', function (string $method, string $uri) {
        $this->json($method, prescriptionUri($uri))->assertUnauthorized();
    })->with('prescription routes');

    it('lets the doctor in', function (string $method, string $uri) {
        // An empty POST / PUT body may fail validation (422), but is never refused.
        $status = $this->actingAs($this->doctor)->json($method, prescriptionUri($uri))->status();

        expect($status)->toBeIn([200, 422]);
    })->with('prescription routes');

    it('changes nothing when a patient or the assistant tries', function () {
        foreach ([$this->patient->user, $this->assistant] as $user) {
            $this->actingAs($user)->putJson("/api/v1/doctor/drugs/{$this->drug->id}", ['is_active' => false])->assertForbidden();
            $this->actingAs($user)->deleteJson("/api/v1/doctor/prescriptions/{$this->prescription->id}")->assertForbidden();
        }

        expect($this->drug->refresh()->is_active)->toBeTrue()
            ->and(Prescription::count())->toBe(1);
    });
});

describe('privacy', function () {
    it('never sends prescriptions or drug data to the patient or the assistant', function () {
        Appointment::factory()->for($this->doctor, 'doctor')->for($this->patient)->at('2026-10-08 17:00')->create();
        $patientUser = $this->patient->user;

        /** @var list<TestResponse> $responses */
        $responses = [
            $this->actingAs($patientUser)->getJson('/api/v1/patient/profile'),
            $this->actingAs($patientUser)->getJson('/api/v1/patient/appointments'),
            $this->actingAs($this->assistant)->getJson('/api/v1/assistant/patients'),
            $this->actingAs($this->assistant)->getJson("/api/v1/assistant/patients/{$this->patient->id}"),
            $this->actingAs($this->assistant)->getJson('/api/v1/assistant/visits/unpaid'),
            $this->actingAs($this->assistant)->getJson('/api/v1/assistant/appointments?from=2026-10-08&to=2026-10-08'),
        ];

        foreach ($responses as $response) {
            $response->assertSuccessful();
            expect($response->getContent())
                ->not->toContain('SECRET')
                ->not->toContain('secretamine')
                ->not->toContain('prescription')
                ->not->toContain('drug');
        }
    });

    it('shows the doctor the prescriptions count on the patient page only', function () {
        $this->actingAs($this->doctor)->getJson("/api/v1/doctor/patients/{$this->patient->id}")
            ->assertJsonPath('data.prescriptions_count', 1);
    });
});

describe('hidden drugs (RX-3)', function () {
    beforeEach(fn () => $this->drug->update(['is_active' => false]));

    it('are never suggested by the search, by name or ingredient', function () {
        foreach (['secretdrug', 'SECRET', 'secretamine'] as $q) {
            $this->actingAs($this->doctor)->getJson('/api/v1/doctor/drugs/search?q='.$q)
                ->assertOk()
                ->assertJsonCount(0, 'data');
        }
    });

    it('still show on old prescriptions', function () {
        $this->actingAs($this->doctor)->getJson("/api/v1/doctor/prescriptions/{$this->prescription->id}")
            ->assertOk()
            ->assertJsonPath('data.items.0.drug_id', $this->drug->id)
            ->assertJsonPath('data.items.0.drug_name', 'SECRETDRUG 500 mg');

        $this->actingAs($this->doctor)->getJson("/api/v1/doctor/patients/{$this->patient->id}/prescriptions")
            ->assertJsonPath('data.0.drug_names', ['SECRETDRUG 500 mg']);

        $this->actingAs($this->doctor)->getJson("/api/v1/doctor/drugs/{$this->drug->id}")
            ->assertOk()
            ->assertJsonPath('data.is_active', false);
    });
});

describe('no prices', function () {
    it('never includes a price field in a drug or prescription response', function () {
        $drug = [
            'trade_name' => 'Pricetest 1 g', 'form' => 'tablets', 'category' => 'antibiotic',
            'active_ingredients' => [['name' => 'amoxicillin']], 'uses' => 'x', 'price' => 99,
        ];

        $responses = [
            $this->actingAs($this->doctor)->getJson('/api/v1/doctor/drugs/search?q=se'),
            $this->actingAs($this->doctor)->getJson('/api/v1/doctor/drugs?include_hidden=1'),
            $this->actingAs($this->doctor)->getJson("/api/v1/doctor/drugs/{$this->drug->id}"),
            $created = $this->actingAs($this->doctor)->postJson('/api/v1/doctor/drugs', $drug),
            $this->actingAs($this->doctor)->putJson("/api/v1/doctor/drugs/{$created->json('data.id')}", $drug),
            $this->actingAs($this->doctor)->getJson("/api/v1/doctor/prescriptions/{$this->prescription->id}"),
            $this->actingAs($this->doctor)->getJson("/api/v1/doctor/patients/{$this->patient->id}/prescriptions"),
        ];

        foreach ($responses as $response) {
            $response->assertSuccessful();
            expect(collect(jsonKeys($response->json()))->filter(fn ($key) => str_contains(strtolower($key), 'price')))->toBeEmpty();
        }
    });
});
