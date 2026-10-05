<?php

namespace App\Http\Resources;

use App\Models\Appointment;
use Illuminate\Http\Request;

/**
 * One appointment. Patients also get can_cancel (BR-5), so the frontend does
 * not repeat the cut-off rule; the doctor gets the patient's name and phone.
 *
 * @mixin Appointment
 */
class AppointmentResource extends ApiResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'start_at' => $this->start_at,
            'end_at' => $this->end_at,
            'status' => $this->status,
            'checked_in_at' => $this->checked_in_at,
            'cancelled_at' => $this->cancelled_at,
            'can_cancel' => $this->when($request->user()?->isPatient(), fn () => $this->patientCanCancel()),
            'patient' => $this->whenLoaded('patient', fn () => [
                'id' => $this->patient->id,
                'name' => $this->patient->user->name,
                'phone' => $this->patient->user->phone,
            ]),
        ];
    }
}
