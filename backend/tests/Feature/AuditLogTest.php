<?php

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\Drug;
use App\Models\MedicalHistoryEntry;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Support\Facades\DB;

/*
 * Audit log of medical and money records (T11-04, NFR-S.4).
 */

const SECRET_ILLNESS = 'SECRET-ILLNESS Diabetes type 2';

beforeEach(function () {
    $this->doctor = User::factory()->doctor()->create();
    $this->assistant = User::factory()->assistant()->create();
    $this->patient = Patient::factory()->create(['current_illness' => SECRET_ILLNESS]);
});

/** The rows for one kind of record, oldest first. */
function auditRows(string $model, ?AuditAction $action = null)
{
    return AuditLog::query()
        ->where('auditable_type', (new $model)->getMorphClass())
        ->when($action, fn ($q) => $q->where('action', $action))
        ->orderBy('id')
        ->get();
}

function expectOneRow(string $model, AuditAction $action, User $user, int $patientId, ?array $fields = null): AuditLog
{
    $rows = auditRows($model, $action);
    expect($rows)->toHaveCount(1);
    $row = $rows->first();
    expect($row->user_id)->toBe($user->id)
        ->and($row->user_role)->toBe($user->role)
        ->and($row->patient_id)->toBe($patientId)
        ->and($row->changed_fields)->toBe($fields)
        ->and($row->ip)->toBe('127.0.0.1')
        ->and($row->created_at)->not->toBeNull();

    return $row;
}

it('writes nothing for seeders and factories (no user)', function () {
    Visit::factory()->for($this->patient)->has(Payment::factory())->create();

    expect(AuditLog::count())->toBe(0);
});

describe('writes', function () {
    it('logs a patient created by the doctor', function () {
        $id = $this->actingAs($this->doctor)->postJson('/api/v1/doctor/patients', [
            'name' => 'New Patient', 'phone' => '01044445555', 'address' => 'Giza', 'current_illness' => SECRET_ILLNESS,
        ])->assertCreated()->json('data.id');

        expectOneRow(Patient::class, AuditAction::Created, $this->doctor, $id);
    });

    it('logs the names of changed patient fields, never their values', function () {
        $this->actingAs($this->doctor)->putJson("/api/v1/doctor/patients/{$this->patient->id}", [
            'name' => $this->patient->user->name, 'phone' => $this->patient->user->phone,
            'address' => 'New address 5', 'current_illness' => 'SECRET-NEW toothache',
        ])->assertOk();

        $row = expectOneRow(Patient::class, AuditAction::Updated, $this->doctor, $this->patient->id, ['address', 'current_illness']);
        $raw = json_encode(DB::table('audit_logs')->get());
        expect($raw)->not->toContain('SECRET')
            ->and($raw)->not->toContain('New address 5')
            ->and($row->auditable_id)->toBe($this->patient->id);
    });

    it('logs an assistant editing contact info', function () {
        $this->actingAs($this->assistant)->putJson("/api/v1/assistant/patients/{$this->patient->id}", [
            'name' => $this->patient->user->name, 'phone' => $this->patient->user->phone, 'address' => 'Somewhere 9',
        ])->assertOk();

        expectOneRow(Patient::class, AuditAction::Updated, $this->assistant, $this->patient->id, ['address']);
    });

    it('logs history entries created, updated and deleted', function () {
        $url = "/api/v1/doctor/patients/{$this->patient->id}/history";
        $body = ['type' => 'allergy', 'title' => 'SECRET-ALLERGY Penicillin', 'recorded_on' => today()->toDateString()];

        $id = $this->actingAs($this->doctor)->postJson($url, $body)->assertCreated()->json('data.id');
        $this->actingAs($this->doctor)->putJson("{$url}/{$id}", [...$body, 'details' => 'SECRET-DETAILS rash'])->assertOk();
        $this->actingAs($this->doctor)->deleteJson("{$url}/{$id}")->assertOk();

        expectOneRow(MedicalHistoryEntry::class, AuditAction::Created, $this->doctor, $this->patient->id);
        expectOneRow(MedicalHistoryEntry::class, AuditAction::Updated, $this->doctor, $this->patient->id, ['details']);
        expectOneRow(MedicalHistoryEntry::class, AuditAction::Deleted, $this->doctor, $this->patient->id);
        expect(json_encode(DB::table('audit_logs')->get()))->not->toContain('SECRET');
    });

    it('logs a visit and its first payment, one row each, then a visit update', function () {
        $id = $this->actingAs($this->doctor)->postJson('/api/v1/doctor/visits', [
            'patient_id' => $this->patient->id, 'work_done' => 'SECRET-WORK filling', 'total_amount' => 500, 'paid_now' => 200,
        ])->assertCreated()->json('data.id');

        expectOneRow(Visit::class, AuditAction::Created, $this->doctor, $this->patient->id);
        expectOneRow(Payment::class, AuditAction::Created, $this->doctor, $this->patient->id);

        $this->actingAs($this->doctor)->putJson("/api/v1/doctor/visits/{$id}", ['work_done' => 'SECRET-WORK 2', 'total_amount' => 600])->assertOk();

        expectOneRow(Visit::class, AuditAction::Updated, $this->doctor, $this->patient->id, ['work_done', 'total_amount']);
        expect(AuditLog::count())->toBe(3);
    });

    it('logs an installment taken by the assistant', function () {
        $visit = Visit::factory()->for($this->patient)->create(['total_amount' => 500]);

        $this->actingAs($this->assistant)->postJson("/api/v1/assistant/visits/{$visit->id}/payments", ['amount' => 100])->assertCreated();

        expectOneRow(Payment::class, AuditAction::Created, $this->assistant, $this->patient->id);
    });

    it('logs a prescription with its lines, and its deletion', function () {
        $drug = Drug::factory()->create();

        $id = $this->actingAs($this->doctor)->postJson("/api/v1/doctor/patients/{$this->patient->id}/prescriptions", [
            'notes' => 'SECRET-NOTES',
            'items' => [['drug_id' => $drug->id, 'instructions' => 'SECRET-1 tablet'], ['drug_name' => 'Mouthwash', 'instructions' => 'twice a day']],
        ])->assertCreated()->json('data.id');

        expectOneRow(Prescription::class, AuditAction::Created, $this->doctor, $this->patient->id);
        expect(auditRows(PrescriptionItem::class, AuditAction::Created))->toHaveCount(2)
            ->each(fn ($row) => $row->patient_id->toBe($this->patient->id));

        $this->actingAs($this->doctor)->deleteJson("/api/v1/doctor/prescriptions/{$id}")->assertOk();

        expectOneRow(Prescription::class, AuditAction::Deleted, $this->doctor, $this->patient->id);
        expect(json_encode(DB::table('audit_logs')->get()))->not->toContain('SECRET');
    });

    it('does not log a patient editing their own profile', function () {
        $this->actingAs($this->patient->user)->patchJson('/api/v1/patient/profile', ['address' => 'My new address'])->assertOk();

        expect(AuditLog::count())->toBe(0);
    });
});

describe('reads', function () {
    it('logs one viewed row when the doctor or the assistant opens a patient', function (string $role, string $url) {
        $user = $this->{$role};

        $this->actingAs($user)->getJson(sprintf($url, $this->patient->id))->assertOk();

        expectOneRow(Patient::class, AuditAction::Viewed, $user, $this->patient->id);
        expect(AuditLog::count())->toBe(1);
    })->with([
        'doctor' => ['doctor', '/api/v1/doctor/patients/%d'],
        'assistant' => ['assistant', '/api/v1/assistant/patients/%d'],
    ]);

    it('logs each opening, by each user', function () {
        $this->actingAs($this->doctor)->getJson("/api/v1/doctor/patients/{$this->patient->id}")->assertOk();
        $this->actingAs($this->doctor)->getJson("/api/v1/doctor/patients/{$this->patient->id}")->assertOk();
        $this->actingAs($this->assistant)->getJson("/api/v1/assistant/patients/{$this->patient->id}")->assertOk();

        expect(auditRows(Patient::class, AuditAction::Viewed)->pluck('user_id')->all())
            ->toBe([$this->doctor->id, $this->doctor->id, $this->assistant->id]);
    });

    it('does not log lists, one row per item or otherwise', function () {
        Visit::factory()->for($this->patient)->create();

        $this->actingAs($this->doctor)->getJson('/api/v1/doctor/patients')->assertOk();
        $this->actingAs($this->assistant)->getJson('/api/v1/assistant/patients')->assertOk();
        $this->actingAs($this->assistant)->getJson('/api/v1/assistant/visits/unpaid')->assertOk();

        expect(AuditLog::count())->toBe(0);
    });

    it('does not log a patient reading their own data', function () {
        $this->actingAs($this->patient->user)->getJson('/api/v1/patient/profile')->assertOk();
        $this->actingAs($this->patient->user)->getJson('/api/v1/patient/appointments')->assertOk();

        expect(AuditLog::count())->toBe(0);
    });

    it('logs a prescription viewed by the form and printed by the print page', function () {
        $prescription = Prescription::factory()->for($this->patient)->has(PrescriptionItem::factory(), 'items')->create();

        $this->actingAs($this->doctor)->getJson("/api/v1/doctor/prescriptions/{$prescription->id}")->assertOk();
        $this->actingAs($this->doctor)->getJson("/api/v1/doctor/prescriptions/{$prescription->id}?purpose=print")->assertOk();

        expectOneRow(Prescription::class, AuditAction::Viewed, $this->doctor, $this->patient->id);
        expectOneRow(Prescription::class, AuditAction::Printed, $this->doctor, $this->patient->id);
    });

    it('logs a visit file as exported', function () {
        ['doctor' => $doctor, 'visit' => $visit] = visitReportFixture();

        $this->actingAs($doctor)->get("/api/v1/doctor/visits/{$visit->id}/export?format=pdf")->assertOk();

        expectOneRow(Visit::class, AuditAction::Exported, $doctor, $visit->patient_id);
    });

    it('does not log a refused read', function () {
        $this->actingAs($this->patient->user)->getJson("/api/v1/doctor/patients/{$this->patient->id}")->assertForbidden();

        expect(AuditLog::count())->toBe(0);
    });
});

describe('integrity', function () {
    it('refuses to update or delete a row through the model', function () {
        $this->actingAs($this->doctor)->getJson("/api/v1/doctor/patients/{$this->patient->id}")->assertOk();
        $row = AuditLog::firstOrFail();

        expect(fn () => $row->update(['action' => AuditAction::Deleted]))->toThrow(LogicException::class)
            ->and(fn () => $row->delete())->toThrow(LogicException::class)
            ->and(AuditLog::firstOrFail()->action)->toBe(AuditAction::Viewed);
    });

    it('rolls back the visit, its payment and the appointment when the audit write fails', function () {
        AuditLog::creating(fn () => throw new RuntimeException('audit store down'));

        $this->actingAs($this->doctor)->postJson('/api/v1/doctor/visits', [
            'patient_id' => $this->patient->id, 'work_done' => 'Filling', 'total_amount' => 500, 'paid_now' => 200,
        ])->assertServerError();

        expect(Visit::count())->toBe(0)
            ->and(Payment::count())->toBe(0)
            ->and(AuditLog::count())->toBe(0);
    });

    it('rolls back a history entry when the audit write fails', function () {
        AuditLog::creating(fn () => throw new RuntimeException('audit store down'));

        $this->actingAs($this->doctor)->postJson("/api/v1/doctor/patients/{$this->patient->id}/history", [
            'type' => 'note', 'title' => 'Note', 'recorded_on' => today()->toDateString(),
        ])->assertServerError();

        expect(MedicalHistoryEntry::count())->toBe(0);
    });
});
