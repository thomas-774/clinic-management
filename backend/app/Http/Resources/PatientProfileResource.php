<?php

namespace App\Http\Resources;

use App\Models\Patient;
use Illuminate\Http\Request;

/**
 * The patient's own page (FR-B.1 – B.4): personal info, illness, simple
 * history (visible entries only), next appointment and balance.
 *
 * @mixin Patient
 */
class PatientProfileResource extends PatientResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'simple_history' => SimpleHistoryEntryResource::collection(
                $this->medicalHistoryEntries()
                    ->patientVisible()
                    ->orderByDesc('recorded_on')
                    ->orderByDesc('id')
                    ->get(),
            ),
            'next_appointment' => ($next = $this->nextAppointment()) ? AppointmentResource::make($next) : null,
            'outstanding_balance' => '0.00', // T5-07
        ];
    }
}
