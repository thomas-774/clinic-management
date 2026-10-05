<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

function insertAppointment(int $doctorId, int $patientId, string $status = 'booked', string $startAt = '2026-10-06 17:00:00'): int
{
    return DB::table('appointments')->insertGetId([
        'doctor_id' => $doctorId,
        'patient_id' => $patientId,
        'start_at' => $startAt,
        'end_at' => date('Y-m-d H:i:s', strtotime($startAt) + 45 * 60),
        'status' => $status,
    ]);
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

describe('appointments (T1-04)', function () {
    beforeEach(function () {
        $this->doctorId = insertUser(['role' => 'doctor', 'phone' => '01099999999']);
    });

    it('rejects two booked appointments at the same time for the same doctor', function () {
        insertAppointment($this->doctorId, insertPatient(['phone' => '01011111111']));

        expect(fn () => insertAppointment($this->doctorId, insertPatient(['phone' => '01022222222'])))
            ->toThrow(QueryException::class);
    });

    it('lets a new booking take the time of a cancelled one', function () {
        insertAppointment($this->doctorId, insertPatient(['phone' => '01011111111']), 'cancelled');
        insertAppointment($this->doctorId, insertPatient(['phone' => '01022222222']), 'booked');

        expect(DB::table('appointments')->count())->toBe(2);
    });

    it('fills active_slot only for slot-holding statuses', function () {
        $patientId = insertPatient();
        $booked = insertAppointment($this->doctorId, $patientId, 'booked', '2026-10-06 17:00:00');
        $noShow = insertAppointment($this->doctorId, $patientId, 'no_show', '2026-10-06 17:45:00');

        expect(DB::table('appointments')->find($booked)->active_slot)->toBe('2026-10-06 17:00:00')
            ->and(DB::table('appointments')->find($noShow)->active_slot)->toBeNull();
    });

    it('frees the slot when a booked appointment is cancelled', function () {
        $first = insertAppointment($this->doctorId, insertPatient(['phone' => '01011111111']));
        DB::table('appointments')->where('id', $first)->update(['status' => 'cancelled']);

        insertAppointment($this->doctorId, insertPatient(['phone' => '01022222222']));

        expect(DB::table('appointments')->where('status', 'booked')->count())->toBe(1);
    });

    it('has the schedule and patient indexes', function () {
        expect(Schema::hasIndex('appointments', ['doctor_id', 'active_slot'], 'unique'))->toBeTrue()
            ->and(Schema::hasIndex('appointments', ['doctor_id', 'start_at']))->toBeTrue()
            ->and(Schema::hasIndex('appointments', ['patient_id']))->toBeTrue();
    });
});

describe('visits and payments (T1-05)', function () {
    it('stores every money column as DECIMAL(10,2)', function () {
        $columns = collect(Schema::getColumns('visits'))->merge(Schema::getColumns('payments'))
            ->whereIn('name', ['total_amount', 'amount']);

        expect($columns)->toHaveCount(2);
        $columns->each(fn ($column) => expect($column['type'])->toBe('decimal(10,2)'));
    });

    it('never stores a remaining balance', function () {
        expect(Schema::hasColumn('visits', 'remaining'))->toBeFalse()
            ->and(Schema::hasColumn('payments', 'remaining'))->toBeFalse();
    });

    it('allows one visit per appointment and walk-in visits without one', function () {
        $patientId = insertPatient();
        $appointmentId = insertAppointment(insertUser(['role' => 'doctor', 'phone' => '01099999999']), $patientId);
        $visit = fn (?int $appointmentId) => DB::table('visits')->insertGetId([
            'patient_id' => $patientId,
            'appointment_id' => $appointmentId,
            'visit_date' => '2026-10-06',
            'work_done' => 'Cleaning',
            'total_amount' => '1500.00',
        ]);

        $visit($appointmentId);
        $visit(null);
        $visit(null);

        expect(fn () => $visit($appointmentId))->toThrow(QueryException::class);
    });

    it('defaults the payment method to cash and keeps exact amounts', function () {
        $visitId = DB::table('visits')->insertGetId([
            'patient_id' => insertPatient(),
            'visit_date' => '2026-10-06',
            'work_done' => 'Cleaning',
            'total_amount' => '1500.00',
        ]);
        DB::table('payments')->insert(['visit_id' => $visitId, 'amount' => '0.10', 'paid_at' => now()]);
        DB::table('payments')->insert(['visit_id' => $visitId, 'amount' => '0.20', 'paid_at' => now()]);

        expect(DB::table('payments')->first()->method)->toBe('cash')
            ->and((string) DB::table('payments')->sum('amount'))->toBe('0.30')
            ->and(Schema::hasIndex('payments', ['paid_at']))->toBeTrue();
    });
});
