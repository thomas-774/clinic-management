<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\User;

/**
 * A patient may only touch their own appointments; the doctor may touch any (§2).
 * Time and status rules (BR-5, §4.3) are checked where the change is made.
 */
class AppointmentPolicy
{
    public function view(User $user, Appointment $appointment): bool
    {
        return $user->isDoctor() || $this->owns($user, $appointment);
    }

    public function cancel(User $user, Appointment $appointment): bool
    {
        return $user->isDoctor() || $this->owns($user, $appointment);
    }

    private function owns(User $user, Appointment $appointment): bool
    {
        return $user->isPatient() && $appointment->patient?->user_id === $user->id;
    }
}
