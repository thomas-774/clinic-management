<?php

use App\Models\MedicalHistoryEntry;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\Visit;
use App\Support\MedicalEncryption;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * T11-06 migrations up → down → up on a seeded database (NFR-S.6). They change
 * column types, which MySQL commits at once, so this suite migrates a fresh
 * database per test (DatabaseMigrations, tests/Pest.php) instead of wrapping
 * each test in a transaction.
 */

function migration(string $name): object
{
    return require database_path("migrations/{$name}.php");
}

/** Every encrypted value as stored, keyed "table.column.id". */
function storedValues(): array
{
    $values = [];
    foreach (MedicalEncryption::COLUMNS as $table => $columns) {
        foreach (DB::table($table)->orderBy('id')->get() as $row) {
            foreach ($columns as $column) {
                $values["{$table}.{$column}.{$row->id}"] = $row->{$column};
            }
        }
    }

    return $values;
}

/** The same values read through the models. */
function modelValues(): array
{
    $models = [Patient::class => 'patients', MedicalHistoryEntry::class => 'medical_history_entries', Visit::class => 'visits', Prescription::class => 'prescriptions', PrescriptionItem::class => 'prescription_items'];
    $values = [];
    foreach ($models as $model => $table) {
        foreach ($model::orderBy('id')->get() as $record) {
            foreach (MedicalEncryption::COLUMNS[$table] as $column) {
                $values["{$table}.{$column}.{$record->id}"] = $record->{$column};
            }
        }
    }

    return $values;
}

it('encrypts, decrypts back and encrypts again, leaving every value readable', function () {
    $patients = Patient::factory()->count(3)->sequence(['current_illness' => 'ألم في الضرس'], ['current_illness' => null], ['current_illness' => 'Gum bleeding'])->create();
    foreach ($patients as $patient) {
        MedicalHistoryEntry::factory()->for($patient)->create(['title' => 'حساسية من البنسلين', 'details' => null]);
        $visit = Visit::factory()->for($patient)->create(['work_done' => 'حشو للضرس ٣٦']);
        $prescription = Prescription::factory()->for($patient)->for($visit)->create(['notes' => 'أكل طري']);
        PrescriptionItem::factory()->count(2)->for($prescription)->create(['instructions' => 'قرص كل 8 ساعات']);
    }
    $plain = modelValues();
    expect(array_filter($plain))->not->toBeEmpty();

    $widen = migration('2026_10_07_120000_widen_encrypted_medical_columns');
    $encrypt = migration('2026_10_07_120100_encrypt_medical_text');

    // down: plain text again, short columns again.
    $encrypt->down();
    $widen->down();
    expect(storedValues())->toBe($plain)
        ->and(Schema::getColumnType('medical_history_entries', 'title'))->toBe('varchar')
        ->and(Schema::getColumnType('prescription_items', 'instructions'))->toBe('varchar');

    // up: ciphertext stored, plain text read.
    $widen->up();
    $encrypt->up();
    $stored = storedValues();
    foreach ($stored as $key => $value) {
        expect($value === null ? null : Crypt::decryptString($value))->toBe($plain[$key], $key);
        if ($value !== null) {
            expect($value)->not->toBe($plain[$key]);
        }
    }
    expect(modelValues())->toBe($plain)
        ->and(Schema::getColumnType('medical_history_entries', 'title'))->toBe('text');

    // up again: already-encrypted values are skipped, not encrypted twice.
    $encrypt->up();
    expect(storedValues())->toBe($stored)
        ->and(modelValues())->toBe($plain);
});

it('encrypts rows that were stored as plain text before the cast existed', function () {
    $patient = Patient::factory()->create();
    DB::table('patients')->where('id', $patient->id)->update(['current_illness' => 'Plain old illness']);
    DB::table('visits')->insert([
        'patient_id' => $patient->id, 'visit_date' => '2025-01-01', 'work_done' => 'Plain old work', 'total_amount' => 100,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    migration('2026_10_07_120100_encrypt_medical_text')->up();

    expect(DB::table('patients')->value('current_illness'))->not->toBe('Plain old illness')
        ->and($patient->fresh()->current_illness)->toBe('Plain old illness')
        ->and(Visit::sole()->work_done)->toBe('Plain old work');
});
