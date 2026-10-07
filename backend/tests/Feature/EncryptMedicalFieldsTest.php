<?php

use App\Models\MedicalHistoryEntry;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\User;
use App\Models\Visit;
use App\Support\MedicalEncryption;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/*
 * Medical text encrypted at rest (T11-06, NFR-S.6).
 */

const ILLNESS = 'ألم في الضرس السفلي الأيسر منذ أسبوع';
const HISTORY_TITLE = 'حساسية من البنسلين';
const HISTORY_DETAILS = 'Rash and swelling after amoxicillin (2019)';
const WORK_DONE = 'حشو عصب للضرس ٣٦ — جلسة أولى';
const RX_NOTES = 'أكل طري لمدة يومين';
const RX_INSTRUCTIONS = 'قرص كل 12 ساعة بعد الأكل لمدة 7 أيام';

/** One record per encrypted column, made through the models. */
function encryptedFixture(): array
{
    $patient = Patient::factory()->create(['current_illness' => ILLNESS]);
    $entry = MedicalHistoryEntry::factory()->for($patient)->create(['title' => HISTORY_TITLE, 'details' => HISTORY_DETAILS]);
    $visit = Visit::factory()->for($patient)->create(['work_done' => WORK_DONE]);
    $prescription = Prescription::factory()->for($patient)->create(['notes' => RX_NOTES]);
    $item = PrescriptionItem::factory()->for($prescription)->create(['instructions' => RX_INSTRUCTIONS]);

    return [
        'patients.current_illness' => [$patient, 'current_illness', ILLNESS],
        'medical_history_entries.title' => [$entry, 'title', HISTORY_TITLE],
        'medical_history_entries.details' => [$entry, 'details', HISTORY_DETAILS],
        'visits.work_done' => [$visit, 'work_done', WORK_DONE],
        'prescriptions.notes' => [$prescription, 'notes', RX_NOTES],
        'prescription_items.instructions' => [$item, 'instructions', RX_INSTRUCTIONS],
    ];
}

/** What is stored in the table, without the model. */
function rawValue(string $table, string $column, int $id): ?string
{
    return DB::table($table)->where('id', $id)->value($column);
}

/** Use another APP_KEY (and APP_PREVIOUS_KEYS) from now on, as after a deploy. */
function useKeys(string $key, array $previous = []): void
{
    config(['app.key' => $key, 'app.previous_keys' => $previous]);
    app()->forgetInstance('encrypter');
    Crypt::clearResolvedInstance('encrypter');
}

it('covers exactly the columns of NFR-S.6', function () {
    expect(MedicalEncryption::COLUMNS)->toBe([
        'patients' => ['current_illness'],
        'medical_history_entries' => ['title', 'details'],
        'visits' => ['work_done'],
        'prescriptions' => ['notes'],
        'prescription_items' => ['instructions'],
    ]);
});

it('stores only ciphertext and gives the plain text back through the model', function () {
    foreach (encryptedFixture() as $where => [$model, $column, $plain]) {
        [$table] = explode('.', $where);
        $raw = rawValue($table, $column, $model->id);

        expect($raw)->not->toBe($plain, $where)
            ->and($raw)->not->toContain(mb_substr($plain, 0, 8))
            ->and(Crypt::decryptString($raw))->toBe($plain)
            ->and($model->fresh()->{$column})->toBe($plain);
    }
});

it('keeps null as null', function () {
    $patient = Patient::factory()->create(['current_illness' => null]);

    expect(rawValue('patients', 'current_illness', $patient->id))->toBeNull()
        ->and($patient->fresh()->current_illness)->toBeNull();
});

it('round-trips Arabic text unchanged through the API', function () {
    $doctor = User::factory()->doctor()->create();
    $patient = Patient::factory()->create();

    $this->actingAs($doctor)->postJson("/api/v1/doctor/patients/{$patient->id}/history", [
        'type' => 'allergy', 'title' => HISTORY_TITLE, 'details' => HISTORY_DETAILS, 'recorded_on' => '2026-10-01',
    ])->assertCreated();
    $visitId = $this->actingAs($doctor)->postJson('/api/v1/doctor/visits', [
        'patient_id' => $patient->id, 'work_done' => WORK_DONE, 'total_amount' => 300,
    ])->assertCreated()->json('data.id');
    $this->actingAs($doctor)->postJson("/api/v1/doctor/patients/{$patient->id}/prescriptions", [
        'visit_id' => $visitId, 'notes' => RX_NOTES, 'items' => [['drug_name' => 'Panadol', 'instructions' => RX_INSTRUCTIONS]],
    ])->assertCreated()
        ->assertJsonPath('data.notes', RX_NOTES)
        ->assertJsonPath('data.items.0.instructions', RX_INSTRUCTIONS);

    $this->actingAs($doctor)->getJson("/api/v1/doctor/patients/{$patient->id}")
        ->assertOk()
        ->assertJsonPath('data.history.0.title', HISTORY_TITLE)
        ->assertJsonPath('data.history.0.details', HISTORY_DETAILS)
        ->assertJsonPath('data.visits.0.work_done', WORK_DONE);

    expect(json_encode(DB::table('visits')->get(), JSON_UNESCAPED_UNICODE))->not->toContain('حشو')
        ->and(json_encode(DB::table('medical_history_entries')->get(), JSON_UNESCAPED_UNICODE))->not->toContain('البنسلين');
});

it('does not mark a record changed when the same text is saved again', function () {
    $visit = Visit::factory()->create(['work_done' => WORK_DONE]);

    $loaded = $visit->fresh()->fill(['work_done' => WORK_DONE]);
    expect($loaded->isDirty('work_done'))->toBeFalse();

    $loaded->fill(['work_done' => 'Something else']);
    expect($loaded->isDirty('work_done'))->toBeTrue();
});

describe('key rotation', function () {
    it('clinic:reencrypt leaves every value readable with the new key only', function () {
        $fixture = encryptedFixture();
        $oldKey = config('app.key');
        $newKey = 'base64:'.base64_encode(random_bytes(32));

        // Deploy: new APP_KEY, the old one in APP_PREVIOUS_KEYS — still readable.
        useKeys($newKey, [$oldKey]);
        foreach ($fixture as [$model, $column, $plain]) {
            expect($model->fresh()->{$column})->toBe($plain);
        }

        $this->artisan('clinic:reencrypt')->expectsOutputToContain('Re-encrypted 6 values')->assertSuccessful();

        // The old key is removed: everything still reads.
        useKeys($newKey);
        foreach ($fixture as $where => [$model, $column, $plain]) {
            expect($model->fresh()->{$column})->toBe($plain, $where);
        }

        // And nothing can be read with the old key any more.
        useKeys($oldKey);
        [$visit] = $fixture['visits.work_done'];
        expect(fn () => $visit->fresh()->work_done)->toThrow(DecryptException::class);
    });

    it('does not write audit rows or touch updated_at', function () {
        $fixture = encryptedFixture();
        [$visit] = $fixture['visits.work_done'];
        $updatedAt = DB::table('visits')->where('id', $visit->id)->value('updated_at');
        $this->travel(5)->minutes();

        $this->artisan('clinic:reencrypt')->assertSuccessful();

        expect(DB::table('visits')->where('id', $visit->id)->value('updated_at'))->toBe($updatedAt)
            ->and(DB::table('audit_logs')->count())->toBe(0);
    });
});
