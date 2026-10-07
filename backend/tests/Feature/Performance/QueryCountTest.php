<?php

use App\Enums\AuditAction;
use App\Models\Appointment;
use App\Models\AuditLog;
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
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;

/*
 * T11-08 (NFR-P.2): every list and detail endpoint runs the same number of
 * SQL queries with 5 rows as with 50, so nothing loads a relation per row.
 * Lazy loading also throws outside production (AppServiceProvider).
 * "Now" is Monday 2026-10-05 08:00; the clinic is seeded by DoctorSeeder.
 */

beforeEach(function () {
    $this->withHeader('Accept-Language', 'en');
    config()->set('clinic.doctor', ['name' => 'Dr', 'phone' => '01000000000', 'email' => null, 'password' => 'password']);
    $this->seed(DoctorSeeder::class);
    $this->travelTo('2026-10-05 08:00:00');

    $this->doctor = User::clinicDoctor();
    $this->assistant = User::factory()->assistant()->create();
    // The patient whose own lists grow: history, visits, prescriptions, appointments.
    $this->focus = Patient::factory()->create();
    $this->rows = 0;
});

/**
 * $n more rows in every list: patients with a visit today (part paid) and an
 * appointment today, and for the focus patient one more history entry, past
 * visit, past appointment and prescription. Also drugs, assistants, blocked
 * days and audit rows.
 */
function addRows(int $n): void
{
    $t = test();
    $recorders = [$t->doctor, $t->assistant];

    for ($i = 0; $i < $n; $i++, $t->rows++) {
        $k = $t->rows;

        $patient = Patient::factory()->create();
        $appointment = Appointment::factory()->for($t->doctor, 'doctor')->for($patient)
            ->at(Carbon::parse('2026-10-05 09:00')->addMinutes(10 * $k))
            ->{$k % 2 ? 'completed' : 'checkedIn'}()->create();
        $visit = Visit::factory()->for($patient)->create(['appointment_id' => $appointment->id, 'visit_date' => '2026-10-05', 'total_amount' => 1000]);
        Payment::factory()->for($visit)->create(['amount' => 200, 'recorded_by' => $recorders[$k % 2]->id]);

        MedicalHistoryEntry::factory()->for($t->focus)->{$k % 2 ? 'visible' : 'private'}()->create();
        $past = Appointment::factory()->for($t->doctor, 'doctor')->for($t->focus)
            ->at(Carbon::parse('2026-09-01 09:00')->addMinutes(10 * $k))->completed()->create();
        $focusVisit = Visit::factory()->for($t->focus)->create(['appointment_id' => $past->id, 'visit_date' => '2026-09-01', 'total_amount' => 500]);
        Payment::factory()->for($focusVisit)->create(['amount' => 100, 'recorded_by' => $recorders[$k % 2]->id]);
        $drug = Drug::factory()->create();
        $rx = Prescription::factory()->forVisit($focusVisit)->create(['doctor_id' => $t->doctor->id]);
        PrescriptionItem::factory()->for($rx)->forDrug($drug)->create(['position' => 1]);
        PrescriptionItem::factory()->for($rx)->create(['position' => 2]);

        User::factory()->assistant()->create();
        BlockedTime::query()->create(['doctor_id' => $t->doctor->id, 'date' => Carbon::parse('2026-10-10')->addDays($k)]);
        AuditLog::query()->create([
            'user_id' => $recorders[$k % 2]->id, 'user_role' => $recorders[$k % 2]->role->value,
            'action' => AuditAction::cases()[0], 'auditable_type' => $visit->getMorphClass(), 'auditable_id' => $visit->id,
            'patient_id' => $patient->id, 'ip' => '127.0.0.1',
        ]);
    }
}

/**
 * @return array<string, Closure(): TestResponse>
 */
function listEndpoints(): array
{
    $doctor = fn (string $url) => fn () => test()->actingAs(test()->doctor)->getJson($url);
    $assistant = fn (string $url) => fn () => test()->actingAs(test()->assistant)->getJson($url);
    $focus = fn (string $url) => fn () => test()->actingAs(test()->focus->user)->getJson($url);

    return [
        'doctor: patients list' => $doctor('/api/v1/doctor/patients'),
        'doctor: patients search' => $doctor('/api/v1/doctor/patients?search=a'),
        'doctor: patient details' => fn () => $doctor('/api/v1/doctor/patients/'.test()->focus->id)(),
        'doctor: prescriptions list' => fn () => $doctor('/api/v1/doctor/patients/'.test()->focus->id.'/prescriptions')(),
        'doctor: today\'s queue' => $doctor('/api/v1/doctor/appointments'),
        'doctor: schedule week' => $doctor('/api/v1/doctor/appointments?from=2026-10-05&to=2026-10-11'),
        'doctor: report day' => $doctor('/api/v1/doctor/reports/summary?period=day'),
        'doctor: report week' => $doctor('/api/v1/doctor/reports/summary?period=week'),
        'doctor: report month' => $doctor('/api/v1/doctor/reports/summary?period=month'),
        'doctor: report payments' => $doctor('/api/v1/doctor/reports/payments?from=2026-09-01&to=2026-10-31'),
        'doctor: report outstanding' => $doctor('/api/v1/doctor/reports/outstanding'),
        'doctor: report daily revenue' => $doctor('/api/v1/doctor/reports/daily-revenue?month=2026-10'),
        'doctor: drugs list' => $doctor('/api/v1/doctor/drugs'),
        'doctor: drug search' => $doctor('/api/v1/doctor/drugs/search?q=a'),
        'doctor: staff' => $doctor('/api/v1/doctor/staff'),
        'doctor: blocked times' => $doctor('/api/v1/doctor/blocked-times'),
        'doctor: activity log' => $doctor('/api/v1/doctor/audit-logs'),
        'assistant: patients list' => $assistant('/api/v1/assistant/patients'),
        'assistant: patient' => fn () => $assistant('/api/v1/assistant/patients/'.test()->focus->id)(),
        'assistant: today\'s queue' => $assistant('/api/v1/assistant/appointments'),
        'assistant: waiting to pay' => $assistant('/api/v1/assistant/visits/unpaid'),
        'patient: profile' => $focus('/api/v1/patient/profile'),
        'patient: appointments' => $focus('/api/v1/patient/appointments'),
    ];
}

it('runs the same number of queries for 5 rows as for 50', function (string $endpoint) {
    $call = listEndpoints()[$endpoint];

    addRows(5);
    $call()->assertOk(); // warm-up: anything cached once is not counted
    $five = queryCount(fn () => $call()->assertOk());

    addRows(45);
    $fifty = queryCount(fn () => $call()->assertOk());

    expect($fifty)->toBe($five, "{$endpoint}: {$five} queries with 5 rows, {$fifty} with 50");
})->with(fn () => array_keys(listEndpoints()));
