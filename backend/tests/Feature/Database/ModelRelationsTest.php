<?php

use App\Enums\AppointmentStatus;
use App\Enums\HistoryType;
use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\BlockedTime;
use App\Models\DoctorSetting;
use App\Models\MedicalHistoryEntry;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\User;
use App\Models\Visit;
use App\Models\WorkingHour;

beforeEach(function () {
    $this->doctor = User::create([
        'name' => 'Dr. Test', 'phone' => '01099999999', 'password' => 'secret123', 'role' => UserRole::Doctor,
    ]);
    $patientUser = User::create([
        'name' => 'Patient', 'phone' => '01011111111', 'password' => 'secret123', 'role' => UserRole::Patient,
    ]);
    $this->patient = Patient::create(['user_id' => $patientUser->id, 'address' => 'Cairo']);
    $this->appointment = Appointment::create([
        'doctor_id' => $this->doctor->id,
        'patient_id' => $this->patient->id,
        'start_at' => '2026-10-06 17:00:00',
        'end_at' => '2026-10-06 17:45:00',
    ]);
    $this->visit = Visit::create([
        'patient_id' => $this->patient->id,
        'appointment_id' => $this->appointment->id,
        'visit_date' => '2026-10-06',
        'work_done' => 'Root canal, session 1 of 2',
        'total_amount' => 1500,
    ]);
    Payment::create(['visit_id' => $this->visit->id, 'amount' => 1000, 'paid_at' => now()]);
});

it('resolves patient, visit and appointment relations', function () {
    expect($this->patient->user->name)->toBe('Patient')
        ->and($this->patient->user->patient->is($this->patient))->toBeTrue()
        ->and($this->visit->payments)->toHaveCount(1)
        ->and($this->appointment->visit->is($this->visit))->toBeTrue()
        ->and($this->appointment->doctor->is($this->doctor))->toBeTrue()
        ->and($this->patient->appointments)->toHaveCount(1)
        ->and($this->patient->visits)->toHaveCount(1)
        ->and($this->patient->payments)->toHaveCount(1)
        ->and($this->doctor->doctorAppointments)->toHaveCount(1);
});

it('resolves the doctor availability relations', function () {
    DoctorSetting::create(['doctor_id' => $this->doctor->id]);
    WorkingHour::create(['doctor_id' => $this->doctor->id, 'day_of_week' => 6, 'start_time' => '17:00', 'end_time' => '21:00']);
    BlockedTime::create(['doctor_id' => $this->doctor->id, 'date' => '2026-10-10']);

    expect($this->doctor->doctorSetting->slot_duration_minutes)->toBe(45)
        ->and($this->doctor->workingHours)->toHaveCount(1)
        ->and($this->doctor->blockedTimes->first()->isWholeDay())->toBeTrue();
});

it('casts enums, money and flags', function () {
    $entry = MedicalHistoryEntry::create([
        'patient_id' => $this->patient->id,
        'type' => 'allergy',
        'title' => 'Penicillin',
        'recorded_on' => '2026-10-05',
    ])->fresh();

    expect($this->doctor->role)->toBe(UserRole::Doctor)
        ->and($this->doctor->isDoctor())->toBeTrue()
        ->and($this->appointment->fresh()->status)->toBe(AppointmentStatus::Booked)
        ->and($this->visit->fresh()->total_amount)->toBe('1500.00')
        ->and($this->visit->payments->first()->method)->toBe(PaymentMethod::Cash)
        ->and($entry->type)->toBe(HistoryType::Allergy)
        ->and($entry->patient_visible)->toBeFalse()
        ->and($this->patient->medicalHistoryEntries()->patientVisible()->count())->toBe(0);
});

it('hashes passwords and hides them from JSON', function () {
    expect($this->doctor->password)->not->toBe('secret123')
        ->and($this->doctor->toArray())->not->toHaveKey('password');
});
