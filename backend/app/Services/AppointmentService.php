<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The schedule and status changes shared by the doctor (FR-F.1 – F.4) and
 * the assistant (FR-I.6).
 */
class AppointmentService
{
    /**
     * Every appointment of $doctor from $from to $to (both inclusive,
     * "YYYY-MM-DD"; today by default), ordered by time.
     *
     * @return Collection<int, Appointment>
     */
    public function schedule(User $doctor, ?string $from = null, ?string $to = null): Collection
    {
        $start = Carbon::parse($from ?? today()->toDateString())->startOfDay();
        $end = Carbon::parse($to ?? $start->toDateString())->addDay()->startOfDay();

        return $doctor->doctorAppointments()
            ->with('patient.user')
            ->where('start_at', '>=', $start)
            ->where('start_at', '<', $end)
            ->orderBy('start_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * Moves the appointment to $to if §4.3 allows it from its current status.
     * The row is re-read under a lock, so two quick clicks cannot both pass.
     *
     * @throws ValidationException on `status`
     */
    public function changeStatus(Appointment $appointment, AppointmentStatus $to): Appointment
    {
        return DB::transaction(function () use ($appointment, $to) {
            $locked = Appointment::query()->lockForUpdate()->findOrFail($appointment->getKey());

            if (! $locked->status->canTransitionTo($to)) {
                throw ValidationException::withMessages(['status' => __('This status change is not allowed.')]);
            }

            $locked->transitionTo($to);

            return $locked;
        });
    }
}
