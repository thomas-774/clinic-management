<?php

use App\Enums\AppointmentStatus;
use App\Exceptions\ActiveAppointmentExistsException;
use App\Exceptions\SlotUnavailableException;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use App\Services\BookingService;
use Database\Seeders\DoctorSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/*
 * BookingService (T4-01). Seeded clinic: Sat–Thu 17:00–21:00, 45 minutes.
 * "Now" is Monday 2026-10-05 08:00 (Cairo).
 */

beforeEach(function () {
    config()->set('clinic.doctor', ['name' => 'Dr', 'phone' => '01000000000', 'email' => null, 'password' => 'password']);
    $this->seed(DoctorSeeder::class);
    $this->travelTo('2026-10-05 08:00:00');
    $this->doctor = User::clinicDoctor();
    $this->patient = Patient::factory()->create();
});

function booking(): BookingService
{
    return BookingService::forClinic();
}

it('books a free slot with end_at = start + duration (BR-3)', function () {
    $appointment = booking()->book($this->patient, Carbon::parse('2026-10-06 17:45'));

    expect($appointment->fresh())
        ->doctor_id->toBe($this->doctor->id)
        ->patient_id->toBe($this->patient->id)
        ->status->toBe(AppointmentStatus::Booked)
        ->and($appointment->start_at->format('Y-m-d H:i'))->toBe('2026-10-06 17:45')
        ->and($appointment->end_at->format('Y-m-d H:i'))->toBe('2026-10-06 18:30');
});

it('accepts the start time in another time zone', function () {
    $appointment = booking()->book($this->patient, Carbon::parse('2026-10-06T14:00:00Z'));

    expect($appointment->start_at->format('Y-m-d H:i'))->toBe('2026-10-06 17:00');
});

it('keeps the stored end_at when the duration changes later', function () {
    $appointment = booking()->book($this->patient, Carbon::parse('2026-10-06 17:00'));
    $this->doctor->doctorSetting->update(['slot_duration_minutes' => 60]);

    expect($appointment->fresh()->end_at->format('H:i'))->toBe('17:45');
});

it('rejects a time that is not a generated slot (BR-1)', function (string $start) {
    expect(fn () => booking()->book($this->patient, Carbon::parse($start)))->toThrow(SlotUnavailableException::class);
    expect(Appointment::count())->toBe(0);
})->with([
    'not on the grid' => '2026-10-06 17:10',
    'after closing' => '2026-10-06 20:45',
    'day off (Friday)' => '2026-10-09 17:00',
    'in the past' => '2026-10-04 17:00',
    'beyond the booking window' => '2026-11-10 17:00',
]);

it('rejects a slot another patient already holds', function () {
    booking()->book(Patient::factory()->create(), Carbon::parse('2026-10-06 17:00'));

    expect(fn () => booking()->book($this->patient, Carbon::parse('2026-10-06 17:00')))->toThrow(SlotUnavailableException::class);
});

it('allows one upcoming appointment per patient (BR-4)', function () {
    booking()->book($this->patient, Carbon::parse('2026-10-06 17:00'));

    expect(fn () => booking()->book($this->patient, Carbon::parse('2026-10-07 17:00')))->toThrow(ActiveAppointmentExistsException::class);
    expect($this->patient->appointments()->count())->toBe(1);
});

it('reads the limit from config', function () {
    config()->set('clinic.max_active_appointments', 2);

    booking()->book($this->patient, Carbon::parse('2026-10-06 17:00'));
    booking()->book($this->patient, Carbon::parse('2026-10-07 17:00'));

    expect(fn () => booking()->book($this->patient, Carbon::parse('2026-10-08 17:00')))->toThrow(ActiveAppointmentExistsException::class);
});

it('does not count cancelled, no-show or past appointments', function () {
    $mine = Appointment::factory()->for($this->doctor, 'doctor')->for($this->patient);
    $mine->at('2026-10-06 17:00')->cancelled()->create();
    $mine->at('2026-10-06 17:45')->noShow()->create();
    $mine->at('2026-10-04 17:00')->completed()->create();

    expect(booking()->book($this->patient, Carbon::parse('2026-10-06 17:00')))->toBeInstanceOf(Appointment::class);
});

it('turns a unique-index clash after the slot check into SlotUnavailableException, not a 500 (BR-2)', function () {
    // Another request inserts the same slot between our check and our insert.
    Appointment::creating(function () {
        DB::table('appointments')->insert([
            'doctor_id' => User::clinicDoctor()->id,
            'patient_id' => Patient::factory()->create()->id,
            'start_at' => '2026-10-06 17:00:00',
            'end_at' => '2026-10-06 17:45:00',
            'status' => 'booked',
        ]);
    });

    expect(fn () => booking()->book($this->patient, Carbon::parse('2026-10-06 17:00')))->toThrow(SlotUnavailableException::class);
    expect($this->patient->appointments()->count())->toBe(0);
});

it('renders the errors as 409 and 422 in the request language', function () {
    $this->withHeader('Accept-Language', 'en');
    Route::get('/api/v1/_test/slot', fn () => throw new SlotUnavailableException);
    Route::get('/api/v1/_test/active', fn () => throw new ActiveAppointmentExistsException);

    $this->getJson('/api/v1/_test/slot')->assertConflict()->assertExactJson(['message' => 'Slot no longer available.']);
    $this->getJson('/api/v1/_test/active')->assertUnprocessable()
        ->assertJsonPath('message', 'You already have an upcoming appointment.')
        ->assertJsonValidationErrors(['start_at']);
    $this->withHeader('Accept-Language', 'ar')->getJson('/api/v1/_test/slot')->assertJsonPath('message', 'هذا الموعد لم يعد متاحًا.');
});
