<?php

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;

/*
 * GET /doctor/audit-logs and audit:prune (T11-05, NFR-S.5).
 */

beforeEach(function () {
    $this->doctor = User::factory()->doctor()->create(['name' => 'Dr. Mona']);
    $this->assistant = User::factory()->assistant()->create(['name' => 'Sara']);
    $this->patient = Patient::factory()->for(User::factory()->patient()->state(['name' => 'Ali Hassan']))->create();
    $this->other = Patient::factory()->create();
});

/** A row written straight to the table, at a chosen time. */
function auditRow(User $user, AuditAction $action, Patient $patient, string $at, string $type = Patient::class, ?array $fields = null): int
{
    return DB::table('audit_logs')->insertGetId([
        'user_id' => $user->id,
        'user_role' => $user->role->value,
        'action' => $action->value,
        'auditable_type' => $type,
        'auditable_id' => $patient->id,
        'patient_id' => $patient->id,
        'changed_fields' => $fields ? json_encode($fields) : null,
        'ip' => '10.0.0.7',
        'user_agent' => 'Test',
        'created_at' => $at,
    ]);
}

function activity(array $query = [], string $language = 'en')
{
    return test()->actingAs(test()->doctor)
        ->getJson('/api/v1/doctor/audit-logs?'.http_build_query($query), ['Accept-Language' => $language]);
}

it('is for the doctor only', function () {
    $this->getJson('/api/v1/doctor/audit-logs')->assertUnauthorized();
    $this->actingAs($this->assistant)->getJson('/api/v1/doctor/audit-logs')->assertForbidden();
    $this->actingAs($this->patient->user)->getJson('/api/v1/doctor/audit-logs')->assertForbidden();
});

it('lists rows newest first with user, record, patient and translated field names', function (string $language, string $record, array $labels) {
    auditRow($this->assistant, AuditAction::Viewed, $this->patient, '2026-10-01 09:00:00');
    auditRow($this->doctor, AuditAction::Updated, $this->patient, '2026-10-02 10:30:00', Visit::class, ['work_done', 'total_amount']);

    $response = activity([], $language)->assertOk()->assertJsonCount(2, 'data');

    $response->assertJsonPath('data.0', [
        'id' => $response->json('data.0.id'),
        'created_at' => '2026-10-02T10:30:00+03:00',
        'action' => 'updated',
        'user' => ['id' => $this->doctor->id, 'name' => 'Dr. Mona'],
        'user_role' => 'doctor',
        'record' => ['type' => 'visit', 'id' => $this->patient->id, 'label' => $record],
        'patient' => ['id' => $this->patient->id, 'name' => 'Ali Hassan'],
        'changed_fields' => [
            ['name' => 'work_done', 'label' => $labels[0]],
            ['name' => 'total_amount', 'label' => $labels[1]],
        ],
        'ip' => '10.0.0.7',
    ]);
    $response->assertJsonPath('data.1.action', 'viewed')
        ->assertJsonPath('data.1.user_role', 'assistant')
        ->assertJsonPath('data.1.changed_fields', [])
        ->assertJsonPath('filters.patient', null);
})->with([
    'en' => ['en', 'Visit', ['Work done', 'Total cost']],
    'ar' => ['ar', 'الزيارة', ['العمل المنجز', 'التكلفة الإجمالية']],
]);

it('filters by patient, user, action and date range', function () {
    $a = auditRow($this->doctor, AuditAction::Viewed, $this->patient, '2026-10-01 08:00:00');
    $b = auditRow($this->assistant, AuditAction::Viewed, $this->patient, '2026-10-03 23:59:00');
    $c = auditRow($this->doctor, AuditAction::Updated, $this->other, '2026-10-04 00:00:00');
    $d = auditRow($this->doctor, AuditAction::Exported, $this->patient, '2026-10-05 12:00:00');

    $ids = fn (array $query) => activity($query)->assertOk()->json('data.*.id');

    expect($ids(['patient_id' => $this->patient->id]))->toBe([$d, $b, $a])
        ->and($ids(['user_id' => $this->assistant->id]))->toBe([$b])
        ->and($ids(['action' => 'viewed']))->toBe([$b, $a])
        ->and($ids(['from' => '2026-10-03', 'to' => '2026-10-04']))->toBe([$c, $b])
        ->and($ids(['from' => '2026-10-04']))->toBe([$d, $c])
        ->and($ids(['to' => '2026-10-01']))->toBe([$a])
        ->and($ids(['patient_id' => $this->patient->id, 'user_id' => $this->doctor->id, 'action' => 'exported']))->toBe([$d]);
});

it('names the filtered patient even when they have no rows', function () {
    activity(['patient_id' => $this->patient->id])
        ->assertOk()
        ->assertJsonCount(0, 'data')
        ->assertJsonPath('filters.patient', ['id' => $this->patient->id, 'name' => 'Ali Hassan']);
});

it('rejects an unknown action and a range that ends before it starts', function () {
    activity(['action' => 'stolen'])->assertUnprocessable()->assertJsonValidationErrors(['action']);
    activity(['from' => '2026-10-05', 'to' => '2026-10-01'])->assertUnprocessable()->assertJsonValidationErrors(['to']);
});

it('pages 50 rows at a time', function () {
    foreach (range(1, 51) as $minute) {
        auditRow($this->doctor, AuditAction::Viewed, $this->patient, sprintf('2026-10-01 10:%02d:00', $minute % 60));
    }

    activity()->assertJsonCount(50, 'data')->assertJsonPath('meta.total', 51)->assertJsonPath('meta.per_page', 50);
    activity(['page' => 2])->assertJsonCount(1, 'data');
});

it('logs the doctor viewing a patient, then shows it under Activity', function () {
    $this->actingAs($this->doctor)->getJson("/api/v1/doctor/patients/{$this->patient->id}")->assertOk();

    activity(['patient_id' => $this->patient->id])
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.action', 'viewed')
        ->assertJsonPath('data.0.record.type', 'patient')
        ->assertJsonPath('data.0.user.name', 'Dr. Mona');
});

it('walkthrough: open a patient, edit history, then both rows show under Activity in Arabic and English', function () {
    $entry = $this->patient->medicalHistoryEntries()->create(['type' => 'allergy', 'title' => 'Penicillin', 'recorded_on' => '2026-10-01']);

    $this->actingAs($this->doctor)->getJson("/api/v1/doctor/patients/{$this->patient->id}")->assertOk();
    $this->actingAs($this->doctor)->putJson("/api/v1/doctor/patients/{$this->patient->id}/history/{$entry->id}", [
        'type' => 'allergy', 'title' => 'Penicillin', 'details' => 'Rash', 'recorded_on' => '2026-10-01',
    ])->assertOk();

    activity(['patient_id' => $this->patient->id], 'en')
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.action', 'updated')
        ->assertJsonPath('data.0.record.label', 'History entry')
        ->assertJsonPath('data.0.changed_fields', [['name' => 'details', 'label' => 'Details']])
        ->assertJsonPath('data.1.action', 'viewed')
        ->assertJsonPath('data.1.record.label', 'Patient');

    activity(['patient_id' => $this->patient->id], 'ar')
        ->assertJsonPath('data.0.record.label', 'بند في التاريخ المرضي')
        ->assertJsonPath('data.0.changed_fields.0.label', 'التفاصيل')
        ->assertJsonPath('data.1.record.label', 'المريض');
});

describe('audit:prune', function () {
    it('deletes only rows older than the retention period', function () {
        $this->travelTo('2026-10-07 12:00:00');
        $old = auditRow($this->doctor, AuditAction::Viewed, $this->patient, '2021-10-07 11:59:59');
        $kept = auditRow($this->doctor, AuditAction::Viewed, $this->patient, '2021-10-07 12:00:01');
        $recent = auditRow($this->doctor, AuditAction::Viewed, $this->patient, '2026-10-01 09:00:00');

        $this->artisan('audit:prune')->expectsOutputToContain('Deleted 1 audit log rows')->assertSuccessful();

        expect(AuditLog::pluck('id')->sort()->values()->all())->toBe([$kept, $recent])
            ->and(AuditLog::find($old))->toBeNull();
    });

    it('follows clinic.audit_retention_years', function () {
        $this->travelTo('2026-10-07 12:00:00');
        config(['clinic.audit_retention_years' => 1]);
        auditRow($this->doctor, AuditAction::Viewed, $this->patient, '2025-10-01 00:00:00');
        $kept = auditRow($this->doctor, AuditAction::Viewed, $this->patient, '2025-10-08 00:00:00');

        $this->artisan('audit:prune')->assertSuccessful();

        expect(AuditLog::pluck('id')->all())->toBe([$kept]);
    });

    it('is scheduled daily and keeps 5 years by default', function () {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event) => str_contains($event->command, 'audit:prune'));

        expect($event)->not->toBeNull()
            ->and($event->expression)->toBe('0 0 * * *')
            ->and(config('clinic.audit_retention_years'))->toBe(5);
    });
});

it('keeps the role as stored on the row', function () {
    auditRow($this->assistant, AuditAction::Viewed, $this->patient, '2026-10-01 09:00:00');

    expect(AuditLog::first()->user_role)->toBe(UserRole::Assistant);
});
