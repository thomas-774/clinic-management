<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Exceptions\ActiveAppointmentExistsException;
use App\Exceptions\SlotUnavailableException;
use App\Models\Appointment;
use App\Models\Patient;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Books appointments (§4.2). Used by both the patient and the doctor
 * endpoints, so the rules are the same whoever books.
 */
class BookingService
{
    public function __construct(private readonly SlotService $slots) {}

    /**
     * The service for the clinic's (single) doctor.
     */
    public static function forClinic(): self
    {
        return new self(SlotService::forClinic());
    }

    /**
     * @throws ActiveAppointmentExistsException BR-4 → 422
     * @throws SlotUnavailableException BR-1 / BR-2 → 409
     */
    public function book(Patient $patient, CarbonInterface $startAt): Appointment
    {
        $startAt = Carbon::instance($startAt)->setTimezone(config('app.timezone'));

        return DB::transaction(function () use ($patient, $startAt) {
            // BR-4: lock the patient row so two bookings by the same patient
            // run one after the other, then count what they already hold.
            Patient::query()->whereKey($patient->getKey())->lockForUpdate()->first();

            $active = $patient->appointments()
                ->whereIn('status', [AppointmentStatus::Booked, AppointmentStatus::CheckedIn])
                ->where('start_at', '>', now())
                ->count();

            if ($active >= config('clinic.max_active_appointments')) {
                throw new ActiveAppointmentExistsException;
            }

            // BR-1: the slots are generated again here; the client's list is not trusted.
            if (! $this->slots->isAvailable($startAt)) {
                throw new SlotUnavailableException;
            }

            try {
                return Appointment::create([
                    'doctor_id' => $this->slots->doctor()->getKey(),
                    'patient_id' => $patient->getKey(),
                    'start_at' => $startAt,
                    // BR-3: stored, so a later duration change does not move it.
                    'end_at' => $startAt->copy()->addMinutes($this->slots->duration()),
                    'status' => AppointmentStatus::Booked,
                ]);
            } catch (UniqueConstraintViolationException) {
                // BR-2: someone took the slot between the check and the insert.
                throw new SlotUnavailableException;
            }
        });
    }
}
