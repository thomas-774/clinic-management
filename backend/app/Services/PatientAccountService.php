<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Patient;
use App\Models\User;
use App\Support\InitialPassword;
use Illuminate\Support\Facades\DB;

/**
 * A patient account made by the doctor (FR-C.6) or the assistant (FR-I.2):
 * the user and patient rows together, with a generated first password.
 */
class PatientAccountService
{
    /**
     * @param  array<string, mixed>  $user  name, phone, email
     * @param  array<string, mixed>  $patient  address, date_of_birth, gender (and current_illness for the doctor)
     * @return array{0: Patient, 1: string} the patient (user loaded) and the plain initial password
     */
    public function create(array $user, array $patient): array
    {
        $password = InitialPassword::generate();

        $record = DB::transaction(function () use ($user, $patient, $password) {
            $account = User::create([
                ...$user,
                'password' => $password,
                'role' => UserRole::Patient,
            ]);

            return $account->patient()->create($patient);
        });

        return [$record->load('user'), $password];
    }
}
