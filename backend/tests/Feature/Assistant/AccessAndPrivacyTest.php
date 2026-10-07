<?php

use App\Models\Appointment;
use App\Models\MedicalHistoryEntry;
use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;
use Database\Seeders\DoctorSeeder;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;

/*
 * T8-07: assistant permissions and privacy (FR-I.1 – I.6, §9.1, §9.2).
 * Deactivated accounts are covered in Auth/AccountStatusTest.php.
 */

beforeEach(function () {
    $this->withHeader('Accept-Language', 'en');
    config()->set('clinic.doctor', ['name' => 'Dr', 'phone' => '01000000000', 'email' => null, 'password' => 'password']);
    $this->seed(DoctorSeeder::class);
    $this->travelTo('2026-10-05 08:00:00');
    $this->doctor = clinicDoctor();
    $this->assistant = User::factory()->assistant()->create(['name' => 'Amal Saad']);
});

/**
 * Every real API route under /api/v1/{$area}/ as [method, uri with ids = 1].
 *
 * @return list<array{0: string, 1: string}>
 */
function routesOf(string $area): array
{
    return collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RoutingRoute $route) => str_starts_with($route->uri(), "api/v1/{$area}/"))
        ->flatMap(fn (RoutingRoute $route) => collect($route->methods())
            ->reject(fn ($method) => $method === 'HEAD')
            ->map(fn ($method) => [$method, '/'.preg_replace('/\{[^}]+\}/', '1', $route->uri())]))
        ->values()
        ->all();
}

it('forbids the assistant every doctor and patient route', function () {
    $routes = [...routesOf('doctor'), ...routesOf('patient')];
    expect($routes)->not->toBeEmpty();

    foreach ($routes as [$method, $uri]) {
        $this->actingAs($this->assistant)->json($method, $uri)
            ->assertStatus(403, "{$method} {$uri} should be forbidden for the assistant");
    }
});

it('forbids the doctor and patients every assistant route', function () {
    $routes = routesOf('assistant');
    expect($routes)->not->toBeEmpty();

    foreach ([$this->doctor, Patient::factory()->create()->user] as $user) {
        foreach ($routes as [$method, $uri]) {
            $this->actingAs($user)->json($method, $uri)
                ->assertStatus(403, "{$method} {$uri} should be forbidden for {$user->role->value}");
        }
    }
});

it('never sends medical data from any assistant endpoint', function () {
    $patient = Patient::factory()->create(['current_illness' => 'SECRET-ILLNESS']);
    MedicalHistoryEntry::factory()->private()->for($patient)->create(['title' => 'SECRET-HISTORY', 'details' => 'SECRET-DETAILS']);
    MedicalHistoryEntry::factory()->visible()->for($patient)->create(['title' => 'SECRET-SIMPLE', 'details' => 'SECRET-SIMPLE']);
    $appointment = Appointment::factory()->for($this->doctor, 'doctor')->for($patient)->at('2026-10-05 17:00')->create();
    $visit = Visit::factory()->for($patient)->create(['visit_date' => '2026-10-05', 'total_amount' => 900, 'work_done' => 'SECRET-WORK']);

    $responses = [
        $this->actingAs($this->assistant)->getJson('/api/v1/assistant/patients'),
        $this->actingAs($this->assistant)->getJson("/api/v1/assistant/patients/{$patient->id}"),
        $this->actingAs($this->assistant)->putJson("/api/v1/assistant/patients/{$patient->id}", [
            'name' => 'New Name', 'phone' => $patient->user->phone, 'address' => 'x',
        ]),
        $this->actingAs($this->assistant)->getJson('/api/v1/assistant/visits/unpaid'),
        $this->actingAs($this->assistant)->postJson("/api/v1/assistant/visits/{$visit->id}/payments", ['amount' => 100]),
        $this->actingAs($this->assistant)->getJson('/api/v1/assistant/appointments'),
        $this->actingAs($this->assistant)->patchJson("/api/v1/assistant/appointments/{$appointment->id}/status", ['status' => 'checked_in']),
    ];

    foreach ($responses as $response) {
        $response->assertSuccessful();
        expect($response->getContent())
            ->not->toContain('SECRET')
            ->not->toContain('current_illness')
            ->not->toContain('work_done')
            ->not->toContain('history');
    }
});

it('runs the front-desk day end to end', function () {
    // 1. The assistant registers a walk-in patient.
    $patientId = $this->actingAs($this->assistant)->postJson('/api/v1/assistant/patients', [
        'name' => 'Sara Adel', 'phone' => '01555556666', 'address' => '5 Nile St',
    ])->assertCreated()->json('data.id');

    // 2. Books today's 17:45 slot and marks her Arrived.
    $appointmentId = $this->actingAs($this->assistant)->postJson('/api/v1/assistant/appointments', [
        'patient_id' => $patientId, 'start_at' => '2026-10-05T17:45:00+03:00',
    ])->assertCreated()->json('data.id');

    $this->travelTo('2026-10-05 17:40:00');
    $this->actingAs($this->assistant)->patchJson("/api/v1/assistant/appointments/{$appointmentId}/status", ['status' => 'checked_in'])
        ->assertJsonPath('data.status', 'checked_in');

    // 3. The doctor sees her and sets the total; nothing paid yet.
    $this->travelTo('2026-10-05 18:10:00');
    $visitId = $this->actingAs($this->doctor)->postJson('/api/v1/doctor/visits', [
        'patient_id' => $patientId, 'appointment_id' => $appointmentId,
        'work_done' => 'Root canal', 'total_amount' => 1500, 'paid_now' => 0,
    ])->assertCreated()->json('data.id');

    // 4. She is waiting to pay; she pays 1000 at the desk.
    $this->actingAs($this->assistant)->getJson('/api/v1/assistant/visits/unpaid')
        ->assertJsonPath('data.0.id', $visitId)
        ->assertJsonPath('data.0.remaining', '1500.00');

    $this->actingAs($this->assistant)->postJson("/api/v1/assistant/visits/{$visitId}/payments", ['amount' => 1000])
        ->assertCreated()
        ->assertJsonPath('data.remaining', '500.00');

    $this->actingAs($this->assistant)->getJson("/api/v1/assistant/patients/{$patientId}")
        ->assertJsonPath('data.outstanding_balance', '500.00');

    // 5. The doctor's report shows the money and who took it.
    $this->actingAs($this->doctor)->getJson('/api/v1/doctor/reports/payments?from=2026-10-05&to=2026-10-05')
        ->assertJsonPath('data.0.paid', '1000.00')
        ->assertJsonPath('data.0.remaining', '500.00')
        ->assertJsonPath('data.0.recorded_by_name', 'Amal Saad');
});
