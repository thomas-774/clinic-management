<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

function insertUser(array $overrides = []): int
{
    return DB::table('users')->insertGetId(array_merge([
        'name' => 'Test User',
        'phone' => '01000000001',
        'password' => bcrypt('password'),
        'role' => 'patient',
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides));
}

function insertPatient(array $userOverrides = []): int
{
    return DB::table('patients')->insertGetId(['user_id' => insertUser($userOverrides), 'address' => 'Cairo']);
}

describe('users and patients (T1-01)', function () {
    it('rejects a duplicate phone number', function () {
        insertUser(['phone' => '01011111111']);

        expect(fn () => insertUser(['phone' => '01011111111']))->toThrow(QueryException::class);
    });

    it('allows several users without an email', function () {
        insertUser(['phone' => '01011111111', 'email' => null]);
        insertUser(['phone' => '01022222222', 'email' => null]);

        expect(DB::table('users')->count())->toBe(2);
    });

    it('allows only one patient row per user and deletes it with the user', function () {
        $userId = insertUser();
        DB::table('patients')->insert(['user_id' => $userId, 'address' => 'Cairo']);

        expect(fn () => DB::table('patients')->insert(['user_id' => $userId, 'address' => 'Giza']))
            ->toThrow(QueryException::class);

        DB::table('users')->where('id', $userId)->delete();
        expect(DB::table('patients')->count())->toBe(0);
    });
});

describe('medical_history_entries (T1-02)', function () {
    it('makes a new entry private by default', function () {
        $id = DB::table('medical_history_entries')->insertGetId([
            'patient_id' => insertPatient(),
            'type' => 'allergy',
            'title' => 'Penicillin',
            'recorded_on' => '2026-10-05',
        ]);

        expect((bool) DB::table('medical_history_entries')->find($id)->patient_visible)->toBeFalse();
    });

    it('rejects an unknown entry type', function () {
        expect(fn () => DB::table('medical_history_entries')->insert([
            'patient_id' => insertPatient(),
            'type' => 'unknown',
            'title' => 'X',
            'recorded_on' => '2026-10-05',
        ]))->toThrow(QueryException::class);
    });
});

describe('availability tables (T1-03)', function () {
    it('fills doctor settings with the default values', function () {
        $doctorId = insertUser(['role' => 'doctor']);
        DB::table('doctor_settings')->insert(['doctor_id' => $doctorId]);

        $settings = DB::table('doctor_settings')->where('doctor_id', $doctorId)->first();

        expect($settings->slot_duration_minutes)->toBe(45)
            ->and($settings->booking_window_days)->toBe(30)
            ->and($settings->cancel_cutoff_hours)->toBe(2);
        expect(fn () => DB::table('doctor_settings')->insert(['doctor_id' => $doctorId]))
            ->toThrow(QueryException::class);
    });

    it('allows several working-hour ranges on the same weekday', function () {
        $doctorId = insertUser(['role' => 'doctor']);
        DB::table('working_hours')->insert([
            ['doctor_id' => $doctorId, 'day_of_week' => 2, 'start_time' => '10:00', 'end_time' => '13:00'],
            ['doctor_id' => $doctorId, 'day_of_week' => 2, 'start_time' => '17:00', 'end_time' => '21:00'],
        ]);

        expect(DB::table('working_hours')->where('day_of_week', 2)->count())->toBe(2);
    });

    it('allows a whole-day block with no times', function () {
        $doctorId = insertUser(['role' => 'doctor']);
        DB::table('blocked_times')->insert(['doctor_id' => $doctorId, 'date' => '2026-10-06']);

        $block = DB::table('blocked_times')->first();
        expect($block->start_time)->toBeNull()->and($block->end_time)->toBeNull();
    });

    it('has the lookup indexes', function () {
        expect(Schema::hasIndex('working_hours', ['doctor_id', 'day_of_week']))->toBeTrue()
            ->and(Schema::hasIndex('blocked_times', ['doctor_id', 'date']))->toBeTrue()
            ->and(Schema::hasIndex('working_hours', ['doctor_id', 'day_of_week'], 'unique'))->toBeFalse();
    });
});
