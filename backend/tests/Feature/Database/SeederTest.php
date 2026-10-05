<?php

use App\Enums\UserRole;
use App\Models\Drug;
use App\Models\MedicalHistoryEntry;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\User;
use Database\Seeders\AssistantSeeder;
use Database\Seeders\DoctorSeeder;
use Database\Seeders\PrescriptionSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    config()->set('clinic.doctor', [
        'name' => 'Dr. Seeded',
        'phone' => '01000000000',
        'email' => 'doctor@clinic.test',
        'password' => 'doctor-secret',
    ]);
});

it('seeds the doctor, default settings, working hours and 10 patients', function () {
    $this->seed();

    $doctor = User::where('role', UserRole::Doctor)->sole();

    expect($doctor->phone)->toBe('01000000000')
        ->and(Hash::check('doctor-secret', $doctor->password))->toBeTrue()
        ->and($doctor->doctorSetting->only(['slot_duration_minutes', 'booking_window_days', 'cancel_cutoff_hours']))
        ->toBe(['slot_duration_minutes' => 45, 'booking_window_days' => 30, 'cancel_cutoff_hours' => 2])
        ->and(Patient::count())->toBe(10)
        ->and(User::where('role', UserRole::Patient)->count())->toBe(10);
});

it('gives Sat–Thu one 17:00–21:00 range and leaves Friday off', function () {
    $this->seed();

    $hours = User::where('role', UserRole::Doctor)->sole()->workingHours;

    expect($hours)->toHaveCount(6)
        ->and($hours->pluck('day_of_week')->sort()->values()->all())->toBe([0, 1, 2, 3, 4, 6])
        ->and($hours->every(fn ($h) => $h->start_time === '17:00:00' && $h->end_time === '21:00:00'))->toBeTrue();
});

it('gives every patient both visible and private history entries', function () {
    $this->seed();

    Patient::with('medicalHistoryEntries')->get()->each(function (Patient $patient) {
        expect($patient->medicalHistoryEntries)->toHaveCount(3)
            ->and($patient->medicalHistoryEntries->contains('patient_visible', true))->toBeTrue()
            ->and($patient->medicalHistoryEntries->contains('patient_visible', false))->toBeTrue();
    });
    expect(MedicalHistoryEntry::count())->toBe(30);
});

it('seeds one active demo assistant, once', function () {
    $this->seed();
    $this->seed(AssistantSeeder::class);

    $assistant = User::where('role', UserRole::Assistant)->sole();

    expect($assistant->phone)->toBe(AssistantSeeder::PHONE)
        ->and($assistant->is_active)->toBeTrue()
        ->and(Hash::check('password', $assistant->password))->toBeTrue();
});

it('does not duplicate the doctor when run twice', function () {
    $this->seed(DoctorSeeder::class);
    $this->seed(DoctorSeeder::class);

    $doctor = User::where('role', UserRole::Doctor)->sole();
    expect($doctor->workingHours)->toHaveCount(6);
});

it('fills the demo prescription header without overwriting edits (T9-06)', function () {
    $this->seed(DoctorSeeder::class);

    $settings = User::where('role', UserRole::Doctor)->sole()->doctorSetting;
    expect($settings->only(array_keys(DoctorSeeder::DEMO_PRINT_HEADER)))->toBe(DoctorSeeder::DEMO_PRINT_HEADER)
        ->and($settings->prescription_paper)->toBe('A5');

    $settings->update(['clinic_name' => 'My Clinic', 'prescription_paper' => 'A4']);
    $this->seed(DoctorSeeder::class);

    expect($settings->refresh())
        ->clinic_name->toBe('My Clinic')
        ->prescription_paper->toBe('A4')
        ->doctor_title->toBe(DoctorSeeder::DEMO_PRINT_HEADER['doctor_title']);
});

it('seeds one demo prescription for each of two patients, once (T9-14)', function () {
    $this->seed();
    $this->seed(PrescriptionSeeder::class);

    $prescriptions = Prescription::with('items')->orderBy('patient_id')->get();
    $firstTwo = Patient::orderBy('id')->take(2)->pluck('id')->all();

    expect($prescriptions)->toHaveCount(2)
        ->and($prescriptions->pluck('patient_id')->all())->toBe($firstTwo)
        ->and($prescriptions[0]->items->pluck('drug_name')->all())->toBe(['Augmentin 1 g', 'Brufen 400 mg', 'Panadol Extra'])
        ->and($prescriptions[0]->items->pluck('drug_id')->filter()->count())->toBe(2)
        ->and($prescriptions[0]->items[0]->drug_form)->toBe(Drug::where('seed_key', 'augmentin-1g-tabs')->value('form'))
        ->and($prescriptions[1]->items->pluck('drug_name')->all())->toBe(['Flagyl 500 mg', 'Hexitol'])
        ->and($prescriptions[1]->issued_on->format('Y-m-d'))->toBe(today()->subDays(10)->format('Y-m-d'));
});

it('refuses to seed without doctor credentials', function () {
    config()->set('clinic.doctor.phone', null);

    $this->seed(DoctorSeeder::class);
})->throws(RuntimeException::class);
