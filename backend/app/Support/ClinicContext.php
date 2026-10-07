<?php

namespace App\Support;

use App\Models\DoctorSetting;
use App\Models\User;

/**
 * The one place that answers "which clinic is this request for?" (NFR-T.1).
 * Bound as a scoped singleton, so it is resolved once per request (or queued
 * job) and never shared across them.
 *
 * Today there is one clinic with one doctor, so the clinic is that doctor
 * (`User::clinicDoctor()`). When the app becomes multi-clinic
 * (docs/adr/0001-multi-clinic-tenancy.md) only this class changes: it will
 * read the clinic of the logged-in user instead.
 */
final class ClinicContext
{
    private ?User $doctor = null;

    /**
     * The clinic's doctor: schedule, slots, prescriptions and print headers
     * belong to this account.
     */
    public function doctor(): User
    {
        return $this->doctor ??= User::clinicDoctor();
    }

    public function doctorId(): int
    {
        return $this->doctor()->id;
    }

    /**
     * The clinic's settings, or the defaults when the doctor never saved any.
     */
    public function settings(): DoctorSetting
    {
        return $this->doctor()->doctorSetting ?? new DoctorSetting;
    }
}
