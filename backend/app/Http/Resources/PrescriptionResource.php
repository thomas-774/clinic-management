<?php

namespace App\Http\Resources;

use App\Models\DoctorSetting;
use App\Models\Prescription;
use Illuminate\Http\Request;

/**
 * A full prescription for the form and the print page (FR-J.4, FR-J.5): the
 * lines, the patient's name and age on the issue date, and the print header
 * from the doctor's settings. Drug side notes are never part of it (RX-4).
 *
 * @mixin Prescription
 */
class PrescriptionResource extends ApiResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $patient = $this->patient;
        $doctor = $this->doctor;
        $settings = $doctor->doctorSetting ?? new DoctorSetting;

        return [
            'id' => $this->id,
            'patient_id' => $this->patient_id,
            'visit_id' => $this->visit_id,
            'issued_on' => $this->issued_on->format('Y-m-d'),
            'notes' => $this->notes,
            'items' => PrescriptionItemResource::collection($this->items),
            'patient' => [
                'id' => $patient->id,
                'name' => $patient->user->name,
                // Age on the day the prescription was issued; null without a date of birth.
                'age' => $patient->date_of_birth ? (int) $patient->date_of_birth->diffInYears($this->issued_on) : null,
            ],
            'print' => [
                'doctor_name' => $doctor->name,
                'doctor_title' => $settings->doctor_title,
                'clinic_name' => $settings->clinic_name,
                'clinic_address' => $settings->clinic_address,
                'clinic_phone' => $settings->clinic_phone,
                'footer' => $settings->prescription_footer,
                'paper' => $settings->prescription_paper,
            ],
        ];
    }
}
