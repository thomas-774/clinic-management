<?php

namespace App\Policies;

use App\Models\Patient;
use App\Models\User;

/**
 * A patient may only ever touch their own record; the doctor may touch any (§2).
 * The assistant may list, create, view and update any patient, but the
 * assistant's resources and form requests leave out all medical data (FR-I.3).
 */
class PatientPolicy
{
    /**
     * The doctor and the assistant list patients.
     */
    public function viewAny(User $user): bool
    {
        return $this->isStaff($user);
    }

    public function view(User $user, Patient $patient): bool
    {
        return $this->isStaff($user) || $this->owns($user, $patient);
    }

    /**
     * The doctor (FR-C.6) and the assistant (FR-I.2) create patient accounts;
     * patients self-register.
     */
    public function create(User $user): bool
    {
        return $this->isStaff($user);
    }

    /**
     * What each role may change is limited by its Form Request (FR-B.5, FR-I.3).
     */
    public function update(User $user, Patient $patient): bool
    {
        return $this->isStaff($user) || $this->owns($user, $patient);
    }

    /**
     * Medical history is managed by the doctor only (FR-C.4).
     */
    public function manageHistory(User $user, Patient $patient): bool
    {
        return $user->isDoctor();
    }

    private function isStaff(User $user): bool
    {
        return $user->isDoctor() || $user->isAssistant();
    }

    private function owns(User $user, Patient $patient): bool
    {
        return $user->isPatient() && $patient->user_id === $user->id;
    }
}
