<?php

namespace App\Policies;

use App\Models\Patient;
use App\Models\User;

/**
 * A patient may only ever touch their own record; the doctor may touch any (§2).
 */
class PatientPolicy
{
    /**
     * Only the doctor lists patients.
     */
    public function viewAny(User $user): bool
    {
        return $user->isDoctor();
    }

    public function view(User $user, Patient $patient): bool
    {
        return $user->isDoctor() || $this->owns($user, $patient);
    }

    /**
     * Only the doctor creates patient accounts (FR-C.6); patients self-register.
     */
    public function create(User $user): bool
    {
        return $user->isDoctor();
    }

    /**
     * What each role may change is limited by its Form Request (FR-B.5).
     */
    public function update(User $user, Patient $patient): bool
    {
        return $user->isDoctor() || $this->owns($user, $patient);
    }

    /**
     * Medical history is managed by the doctor only (FR-C.4).
     */
    public function manageHistory(User $user, Patient $patient): bool
    {
        return $user->isDoctor();
    }

    private function owns(User $user, Patient $patient): bool
    {
        return $user->isPatient() && $patient->user_id === $user->id;
    }
}
