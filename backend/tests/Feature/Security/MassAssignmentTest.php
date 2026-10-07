<?php

use App\Models\Appointment;
use App\Models\Drug;
use App\Models\MedicalHistoryEntry;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Prescription;
use App\Models\User;
use App\Models\Visit;
use Database\Seeders\DoctorSeeder;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

/*
 * T11-07 (ASVS V4.1.2, V5.1.3, NFR-S.7): every write route takes only the
 * fields its Form Request lists. Each route gets a valid body plus fields a
 * client must never set (role, owner ids, money, timestamps, a password); the
 * request succeeds and none of the extra fields reaches the database.
 *
 * Models keep fields like `role` and `doctor_id` in #[Fillable] for the
 * server's own code; no controller passes request input to a model except
 * through validated() / safe()->only().
 */

const SMUGGLED_ID = 987654;
const SMUGGLED_TIME = '2001-01-01 00:00:00';
const SMUGGLED_PASSWORD = 'smuggled-password-123';

beforeEach(function () {
    $this->withHeader('Accept-Language', 'en');
    config()->set('clinic.doctor', ['name' => 'Dr', 'phone' => '01000000000', 'email' => null, 'password' => 'password']);
    $this->seed(DoctorSeeder::class);
    $this->travelTo('2026-10-05 08:00:00');

    $this->doctor = clinicDoctor();
    $this->otherDoctor = User::factory()->doctor()->create();
    $this->assistant = User::factory()->assistant()->create();
    $this->me = Patient::factory()->create();
    $this->patient = Patient::factory()->create();
    $this->freePatient = Patient::factory()->create();
    $this->otherPatient = Patient::factory()->create();

    $this->appointment = Appointment::factory()->for($this->doctor, 'doctor')->for($this->patient)->at('2026-10-05 17:00')->create();
    $this->visit = Visit::factory()->for($this->patient)->create(['visit_date' => '2026-10-05', 'total_amount' => 1000]);
    $this->entry = MedicalHistoryEntry::factory()->for($this->patient)->create();
    $this->drug = Drug::factory()->create();
    $this->prescription = Prescription::factory()->for($this->patient)->create(['doctor_id' => $this->doctor->id]);

    $this->extras = [
        'id' => SMUGGLED_ID,
        'role' => 'doctor',
        'is_active' => false,
        'doctor_id' => $this->otherDoctor->id,
        'patient_id' => $this->otherPatient->id,
        'user_id' => $this->otherPatient->user_id,
        'recorded_by' => $this->otherDoctor->id,
        'total_amount' => 99999,
        'password' => SMUGGLED_PASSWORD,
        'password_confirmation' => SMUGGLED_PASSWORD,
        'created_at' => SMUGGLED_TIME,
        'updated_at' => SMUGGLED_TIME,
    ];

    $this->usersBefore = User::query()->orderBy('id')->get(['id', 'role', 'is_active', 'password'])->keyBy('id');
});

/**
 * Every write route as "METHOD uri" => [actor, url, body]. The body's own
 * fields win over the extras where a route really takes one of them.
 *
 * @return array<string, Closure>
 */
function writeRoutes(): array
{
    return [
        'POST api/v1/auth/register' => fn () => [null, '/api/v1/auth/register', [
            'name' => 'New Patient', 'phone' => '01099990000', 'address' => 'Giza',
            'password' => 'secret-pass-1', 'password_confirmation' => 'secret-pass-1',
        ]],

        'PATCH api/v1/patient/profile' => fn () => [test()->me->user, '/api/v1/patient/profile', ['address' => 'New address']],
        'POST api/v1/patient/appointments' => fn () => [test()->me->user, '/api/v1/patient/appointments', ['start_at' => '2026-10-06T17:45:00+03:00']],
        'PATCH api/v1/patient/appointments/{appointment}/cancel' => function () {
            $mine = Appointment::factory()->for(test()->doctor, 'doctor')->for(test()->me)->at('2026-10-06 19:15')->create();

            return [test()->me->user, "/api/v1/patient/appointments/{$mine->id}/cancel", []];
        },

        'POST api/v1/doctor/patients' => fn () => [test()->doctor, '/api/v1/doctor/patients', ['name' => 'Walk In', 'phone' => '01099991111', 'address' => 'Cairo']],
        'PUT api/v1/doctor/patients/{patient}' => fn () => [test()->doctor, '/api/v1/doctor/patients/'.test()->patient->id, [
            'name' => 'Renamed', 'phone' => test()->patient->user->phone, 'address' => 'Cairo',
        ]],
        'POST api/v1/doctor/patients/{patient}/history' => fn () => [test()->doctor, '/api/v1/doctor/patients/'.test()->patient->id.'/history', [
            'type' => 'allergy', 'title' => 'Penicillin', 'recorded_on' => '2026-10-01',
        ]],
        'PUT api/v1/doctor/patients/{patient}/history/{medicalHistoryEntry}' => fn () => [test()->doctor, '/api/v1/doctor/patients/'.test()->patient->id.'/history/'.test()->entry->id, [
            'type' => 'note', 'title' => 'Changed', 'recorded_on' => '2026-10-01',
        ]],
        'POST api/v1/doctor/appointments' => fn () => [test()->doctor, '/api/v1/doctor/appointments', ['patient_id' => test()->freePatient->id, 'start_at' => '2026-10-06T17:45:00+03:00']],
        'PATCH api/v1/doctor/appointments/{appointment}/status' => fn () => [test()->doctor, '/api/v1/doctor/appointments/'.test()->appointment->id.'/status', ['status' => 'checked_in']],
        'POST api/v1/doctor/visits' => fn () => [test()->doctor, '/api/v1/doctor/visits', [
            'patient_id' => test()->patient->id, 'work_done' => 'Filling', 'total_amount' => 500, 'paid_now' => 100,
        ]],
        'PUT api/v1/doctor/visits/{visit}' => fn () => [test()->doctor, '/api/v1/doctor/visits/'.test()->visit->id, ['work_done' => 'Changed', 'total_amount' => 1200]],
        'POST api/v1/doctor/visits/{visit}/payments' => fn () => [test()->doctor, '/api/v1/doctor/visits/'.test()->visit->id.'/payments', ['amount' => 100]],
        'PUT api/v1/doctor/settings' => fn () => [test()->doctor, '/api/v1/doctor/settings', ['clinic_name' => 'Smile']],
        'PUT api/v1/doctor/working-hours' => fn () => [test()->doctor, '/api/v1/doctor/working-hours', [
            'days' => [['day_of_week' => 6, 'ranges' => [['start_time' => '17:00', 'end_time' => '21:00']]]],
        ]],
        'POST api/v1/doctor/staff' => fn () => [test()->doctor, '/api/v1/doctor/staff', ['name' => 'New Assistant', 'phone' => '01099992222']],
        'PUT api/v1/doctor/staff/{staff}' => fn () => [test()->doctor, '/api/v1/doctor/staff/'.test()->assistant->id, ['name' => 'Renamed', 'is_active' => true]],
        'POST api/v1/doctor/blocked-times' => fn () => [test()->doctor, '/api/v1/doctor/blocked-times', ['date' => '2026-10-10', 'reason' => 'Holiday']],
        'POST api/v1/doctor/drugs' => fn () => [test()->doctor, '/api/v1/doctor/drugs', [
            'trade_name' => 'New Drug', 'form' => 'tablets', 'category' => 'antibiotic',
            'active_ingredients' => [['name' => 'Amoxicillin']], 'uses' => 'Infections', 'is_active' => true,
        ]],
        'PUT api/v1/doctor/drugs/{drug}' => fn () => [test()->doctor, '/api/v1/doctor/drugs/'.test()->drug->id, ['trade_name' => 'Renamed', 'is_active' => true]],
        'POST api/v1/doctor/patients/{patient}/prescriptions' => fn () => [test()->doctor, '/api/v1/doctor/patients/'.test()->patient->id.'/prescriptions', [
            'items' => [['drug_name' => 'Brufen 400 mg', 'instructions' => 'After meals']],
        ]],
        'PUT api/v1/doctor/prescriptions/{prescription}' => fn () => [test()->doctor, '/api/v1/doctor/prescriptions/'.test()->prescription->id, [
            'items' => [['drug_name' => 'Brufen 600 mg', 'instructions' => 'After meals']],
        ]],

        'POST api/v1/assistant/patients' => fn () => [test()->assistant, '/api/v1/assistant/patients', ['name' => 'Desk Patient', 'phone' => '01099993333', 'address' => 'Cairo']],
        'PUT api/v1/assistant/patients/{patient}' => fn () => [test()->assistant, '/api/v1/assistant/patients/'.test()->patient->id, [
            'name' => 'Renamed', 'phone' => test()->patient->user->phone, 'address' => 'Cairo',
        ]],
        'POST api/v1/assistant/visits/{visit}/payments' => fn () => [test()->assistant, '/api/v1/assistant/visits/'.test()->visit->id.'/payments', ['amount' => 100]],
        'POST api/v1/assistant/appointments' => fn () => [test()->assistant, '/api/v1/assistant/appointments', ['patient_id' => test()->freePatient->id, 'start_at' => '2026-10-06T17:45:00+03:00']],
        'PATCH api/v1/assistant/appointments/{appointment}/status' => fn () => [test()->assistant, '/api/v1/assistant/appointments/'.test()->appointment->id.'/status', ['status' => 'checked_in']],
    ];
}

/**
 * Write routes with no body a model could take: login checks a password,
 * logout deletes the current token.
 */
const BODYLESS_WRITES = ['POST api/v1/auth/login', 'POST api/v1/auth/logout'];

it('covers every POST, PUT and PATCH route', function () {
    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RoutingRoute $route) => str_starts_with($route->uri(), 'api/v1/'))
        ->flatMap(fn (RoutingRoute $route) => collect($route->methods())
            ->intersect(['POST', 'PUT', 'PATCH'])
            ->map(fn ($method) => "{$method} {$route->uri()}"))
        ->diff(BODYLESS_WRITES)
        ->sort()->values()->all();

    expect($routes)->toBe(collect(writeRoutes())->keys()->sort()->values()->all());
});

it('ignores fields the client must not set', function (string $route) {
    [$actor, $url, $body] = writeRoutes()[$route]();
    $method = strtolower(strtok($route, ' ')).'Json';

    $request = $actor ? $this->actingAs($actor) : $this;
    $request->{$method}($url, [...$this->extras, ...$body])->assertSuccessful();

    // Nobody's role, status or password changed, and no new account is a doctor.
    $after = User::query()->orderBy('id')->get(['id', 'role', 'is_active', 'password'])->keyBy('id');
    foreach ($this->usersBefore as $id => $user) {
        expect($after[$id]->only(['role', 'is_active', 'password']))->toBe($user->only(['role', 'is_active', 'password']), "user {$id}");
    }
    $after->except($this->usersBefore->keys()->all())->each(function (User $new) {
        expect($new->role->value)->not->toBe('doctor')
            ->and($new->is_active)->toBeTrue()
            ->and(Hash::check(SMUGGLED_PASSWORD, $new->password))->toBeFalse();
    });

    // No row took the client's id, timestamps or another owner.
    foreach (['users', 'patients', 'appointments', 'visits', 'payments', 'medical_history_entries', 'prescriptions', 'prescription_items', 'drugs', 'blocked_times', 'working_hours', 'doctor_settings'] as $table) {
        expect(DB::table($table)->where('id', SMUGGLED_ID)->exists())->toBeFalse("{$table}.id")
            ->and(DB::table($table)->where('created_at', SMUGGLED_TIME)->orWhere('updated_at', SMUGGLED_TIME)->exists())->toBeFalse("{$table} timestamps");
    }
    foreach (['appointments', 'prescriptions', 'blocked_times', 'working_hours', 'doctor_settings'] as $table) {
        expect(DB::table($table)->where('doctor_id', $this->otherDoctor->id)->exists())->toBeFalse("{$table}.doctor_id");
    }
    foreach (['appointments', 'visits', 'medical_history_entries', 'prescriptions'] as $table) {
        expect(DB::table($table)->where('patient_id', $this->otherPatient->id)->exists())->toBeFalse("{$table}.patient_id");
    }
    expect(Patient::query()->where('user_id', $this->otherPatient->user_id)->count())->toBe(1)
        ->and(Payment::query()->where('recorded_by', $this->otherDoctor->id)->exists())->toBeFalse()
        ->and(Visit::query()->where('total_amount', 99999)->exists())->toBeFalse();
})->with(fn () => array_keys(writeRoutes()));
