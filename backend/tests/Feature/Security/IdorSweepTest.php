<?php

use App\Models\Appointment;
use App\Models\BlockedTime;
use App\Models\Drug;
use App\Models\MedicalHistoryEntry;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\User;
use App\Models\Visit;
use Database\Seeders\DoctorSeeder;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/*
 * T11-07 IDOR sweep (ASVS V4.2.1, NFR-S.7, extends the Phase 2 and T8-07
 * privacy tests). Every route that takes a record id is listed here. For each
 * one, a user of every role aims it at a record that is not theirs:
 *   - a patient: another patient's record → 403, on every id route;
 *   - the assistant and the doctor: a route of another area → 403, and a
 *     record owned by another doctor or a mismatched parent → 404.
 * In one clinic the doctor and the assistant work on every patient (§2), so
 * those routes are listed in CLINIC_WIDE with the reason instead.
 * After each refused request no medical or money record has changed.
 */

beforeEach(function () {
    $this->withHeader('Accept-Language', 'en');
    config()->set('clinic.doctor', ['name' => 'Dr', 'phone' => '01000000000', 'email' => null, 'password' => 'password']);
    $this->seed(DoctorSeeder::class);
    $this->travelTo('2026-10-05 08:00:00');

    $this->doctor = clinicDoctor();
    $this->otherDoctor = User::factory()->doctor()->create();
    $this->assistant = User::factory()->assistant()->create();
    $this->patientA = Patient::factory()->create();

    // Patient B's records: the targets.
    $b = Patient::factory()->create();
    $this->b = (object) [
        'patient' => $b,
        'entry' => MedicalHistoryEntry::factory()->for($b)->create(),
        'appointment' => Appointment::factory()->for($this->doctor, 'doctor')->for($b)->at('2026-10-05 17:00')->create(),
        'visit' => $visit = Visit::factory()->for($b)->create(['visit_date' => '2026-10-04', 'total_amount' => 1000]),
        'prescription' => $rx = Prescription::factory()->forVisit($visit)->create(['doctor_id' => $this->doctor->id]),
    ];
    Payment::factory()->for($visit)->create(['amount' => 200]);
    PrescriptionItem::factory()->for($rx)->create();

    // Records owned by another doctor.
    $this->foreign = (object) [
        'appointment' => Appointment::factory()->for($this->otherDoctor, 'doctor')->for($b)->at('2026-10-05 18:00')->create(),
        'block' => BlockedTime::query()->create(['doctor_id' => $this->otherDoctor->id, 'date' => '2026-10-10']),
    ];
    $this->drug = Drug::factory()->create();

    $this->before = idorSnapshot();
});

/**
 * Every medical, money and account row, to prove a refused request changed nothing.
 */
function idorSnapshot(): string
{
    return collect(['users', 'patients', 'medical_history_entries', 'appointments', 'visits', 'payments', 'prescriptions', 'prescription_items', 'blocked_times', 'drugs'])
        ->map(fn (string $table) => DB::table($table)->orderBy('id')->get()->toJson())
        ->implode("\n");
}

/**
 * Every route with a record id, aimed at patient B's records: "METHOD uri" => [url, valid body].
 *
 * @return array<string, Closure(): array{0: string, 1: array<string, mixed>}>
 */
function idRoutes(): array
{
    $b = fn () => test()->b;

    return [
        'PATCH api/v1/patient/appointments/{appointment}/cancel' => fn () => ["/api/v1/patient/appointments/{$b()->appointment->id}/cancel", []],

        'GET api/v1/doctor/patients/{patient}' => fn () => ["/api/v1/doctor/patients/{$b()->patient->id}", []],
        'PUT api/v1/doctor/patients/{patient}' => fn () => ["/api/v1/doctor/patients/{$b()->patient->id}", ['name' => 'X', 'phone' => '01099990000', 'address' => 'X']],
        'POST api/v1/doctor/patients/{patient}/history' => fn () => ["/api/v1/doctor/patients/{$b()->patient->id}/history", ['type' => 'note', 'title' => 'X', 'recorded_on' => '2026-10-01']],
        'PUT api/v1/doctor/patients/{patient}/history/{medicalHistoryEntry}' => fn () => ["/api/v1/doctor/patients/{$b()->patient->id}/history/{$b()->entry->id}", ['type' => 'note', 'title' => 'X', 'recorded_on' => '2026-10-01']],
        'DELETE api/v1/doctor/patients/{patient}/history/{medicalHistoryEntry}' => fn () => ["/api/v1/doctor/patients/{$b()->patient->id}/history/{$b()->entry->id}", []],
        'PATCH api/v1/doctor/appointments/{appointment}/status' => fn () => ["/api/v1/doctor/appointments/{$b()->appointment->id}/status", ['status' => 'checked_in']],
        'PUT api/v1/doctor/visits/{visit}' => fn () => ["/api/v1/doctor/visits/{$b()->visit->id}", ['work_done' => 'X', 'total_amount' => 5]],
        'POST api/v1/doctor/visits/{visit}/payments' => fn () => ["/api/v1/doctor/visits/{$b()->visit->id}/payments", ['amount' => 1]],
        'GET api/v1/doctor/visits/{visit}/export' => fn () => ["/api/v1/doctor/visits/{$b()->visit->id}/export?format=pdf", []],
        'PUT api/v1/doctor/staff/{staff}' => fn () => ['/api/v1/doctor/staff/'.test()->assistant->id, ['name' => 'X']],
        'DELETE api/v1/doctor/blocked-times/{blockedTime}' => fn () => ['/api/v1/doctor/blocked-times/'.test()->foreign->block->id, []],
        'GET api/v1/doctor/drugs/{drug}' => fn () => ['/api/v1/doctor/drugs/'.test()->drug->id, []],
        'PUT api/v1/doctor/drugs/{drug}' => fn () => ['/api/v1/doctor/drugs/'.test()->drug->id, ['trade_name' => 'X']],
        'GET api/v1/doctor/patients/{patient}/prescriptions' => fn () => ["/api/v1/doctor/patients/{$b()->patient->id}/prescriptions", []],
        'POST api/v1/doctor/patients/{patient}/prescriptions' => fn () => ["/api/v1/doctor/patients/{$b()->patient->id}/prescriptions", ['items' => [['drug_name' => 'X', 'instructions' => 'X']]]],
        'GET api/v1/doctor/prescriptions/{prescription}' => fn () => ["/api/v1/doctor/prescriptions/{$b()->prescription->id}", []],
        'PUT api/v1/doctor/prescriptions/{prescription}' => fn () => ["/api/v1/doctor/prescriptions/{$b()->prescription->id}", ['items' => [['drug_name' => 'X', 'instructions' => 'X']]]],
        'DELETE api/v1/doctor/prescriptions/{prescription}' => fn () => ["/api/v1/doctor/prescriptions/{$b()->prescription->id}", []],

        'GET api/v1/assistant/patients/{patient}' => fn () => ["/api/v1/assistant/patients/{$b()->patient->id}", []],
        'PUT api/v1/assistant/patients/{patient}' => fn () => ["/api/v1/assistant/patients/{$b()->patient->id}", ['name' => 'X', 'phone' => '01099990000', 'address' => 'X']],
        'POST api/v1/assistant/visits/{visit}/payments' => fn () => ["/api/v1/assistant/visits/{$b()->visit->id}/payments", ['amount' => 1]],
        'PATCH api/v1/assistant/appointments/{appointment}/status' => fn () => ["/api/v1/assistant/appointments/{$b()->appointment->id}/status", ['status' => 'checked_in']],
    ];
}

/**
 * Routes of their own area that the doctor / the assistant may use on any
 * patient of the clinic (§2: one doctor, one clinic; T11-15 for SaaS).
 */
const CLINIC_WIDE = [
    'doctor' => [
        'GET api/v1/doctor/patients/{patient}', 'PUT api/v1/doctor/patients/{patient}', 'POST api/v1/doctor/patients/{patient}/history',
        'PUT api/v1/doctor/visits/{visit}', 'POST api/v1/doctor/visits/{visit}/payments', 'GET api/v1/doctor/visits/{visit}/export',
        'GET api/v1/doctor/drugs/{drug}', 'PUT api/v1/doctor/drugs/{drug}',
        'GET api/v1/doctor/patients/{patient}/prescriptions', 'POST api/v1/doctor/patients/{patient}/prescriptions',
        'GET api/v1/doctor/prescriptions/{prescription}', 'PUT api/v1/doctor/prescriptions/{prescription}', 'DELETE api/v1/doctor/prescriptions/{prescription}',
    ],
    'assistant' => [
        'GET api/v1/assistant/patients/{patient}', 'PUT api/v1/assistant/patients/{patient}', 'POST api/v1/assistant/visits/{visit}/payments',
    ],
];

/**
 * Own-area routes aimed at a record that belongs to another doctor, another
 * account type or another parent: "role | METHOD uri" => [url, body].
 *
 * @return array<string, Closure(): array{0: string, 1: array<string, mixed>}>
 */
function crossOwnerCases(): array
{
    return [
        'doctor | PATCH api/v1/doctor/appointments/{appointment}/status' => fn () => ['/api/v1/doctor/appointments/'.test()->foreign->appointment->id.'/status', ['status' => 'checked_in']],
        'doctor | DELETE api/v1/doctor/blocked-times/{blockedTime}' => fn () => ['/api/v1/doctor/blocked-times/'.test()->foreign->block->id, []],
        'doctor | PUT api/v1/doctor/staff/{staff} (a patient)' => fn () => ['/api/v1/doctor/staff/'.test()->b->patient->user_id, ['name' => 'X', 'is_active' => false]],
        'doctor | PUT api/v1/doctor/staff/{staff} (a doctor)' => fn () => ['/api/v1/doctor/staff/'.test()->otherDoctor->id, ['name' => 'X', 'is_active' => false]],
        'doctor | PUT api/v1/doctor/patients/{patient}/history/{medicalHistoryEntry}' => fn () => ['/api/v1/doctor/patients/'.test()->patientA->id.'/history/'.test()->b->entry->id, ['type' => 'note', 'title' => 'X', 'recorded_on' => '2026-10-01']],
        'doctor | DELETE api/v1/doctor/patients/{patient}/history/{medicalHistoryEntry}' => fn () => ['/api/v1/doctor/patients/'.test()->patientA->id.'/history/'.test()->b->entry->id, []],
        'assistant | PATCH api/v1/assistant/appointments/{appointment}/status' => fn () => ['/api/v1/assistant/appointments/'.test()->foreign->appointment->id.'/status', ['status' => 'checked_in']],
    ];
}

function idorActor(string $role): User
{
    return match ($role) {
        'patient' => test()->patientA->user,
        'doctor' => test()->doctor,
        'assistant' => test()->assistant,
    };
}

it('lists every route that takes a record id', function () {
    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RoutingRoute $route) => str_starts_with($route->uri(), 'api/v1/') && $route->parameterNames() !== [])
        ->flatMap(fn (RoutingRoute $route) => collect($route->methods())->reject(fn ($m) => $m === 'HEAD')->map(fn ($m) => "{$m} {$route->uri()}"))
        ->sort()->values()->all();

    expect($routes)->toBe(collect(idRoutes())->keys()->sort()->values()->all());
});

it('checks each own-area id route of the doctor and the assistant one way or the other', function (string $role) {
    $own = collect(idRoutes())->keys()->filter(fn ($key) => str_contains($key, "api/v1/{$role}/"));
    $crossChecked = collect(crossOwnerCases())->keys()
        ->filter(fn ($key) => str_starts_with($key, "{$role} | "))
        ->map(fn ($key) => preg_replace('/ \(.*\)$/', '', substr($key, strlen("{$role} | "))));

    expect($own->diff([...CLINIC_WIDE[$role], ...$crossChecked])->values()->all())->toBe([]);
})->with(['doctor', 'assistant']);

it('refuses a user any id route outside their area, aimed at patient B', function (string $role) {
    $actor = idorActor($role);
    $routes = collect(idRoutes())->filter(fn ($_, $key) => ! str_contains($key, "api/v1/{$role}/"));
    expect($routes)->not->toBeEmpty();

    foreach ($routes as $key => $target) {
        [$url, $body] = $target();
        app('auth')->forgetGuards();
        $this->actingAs($actor)->json(strtok($key, ' '), $url, $body)->assertStatus(403, "{$role}: {$key}");
    }

    expect(idorSnapshot())->toBe($this->before);
})->with(['patient', 'doctor', 'assistant']);

it("refuses a patient patient B's appointment on their own cancel route", function () {
    [$url] = idRoutes()['PATCH api/v1/patient/appointments/{appointment}/cancel']();

    $this->actingAs($this->patientA->user)->patchJson($url)->assertForbidden();

    expect(idorSnapshot())->toBe($this->before);
});

it("returns 404 for a record that is not the user's own", function (string $case) {
    [$url, $body] = crossOwnerCases()[$case]();
    [$role, $route] = explode(' | ', $case);

    $this->actingAs(idorActor($role))->json(strtok($route, ' '), $url, $body)
        ->assertNotFound()
        ->assertExactJson(['message' => 'Not Found']);

    expect(idorSnapshot())->toBe($this->before);
})->with(fn () => array_keys(crossOwnerCases()));
