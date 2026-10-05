<?php

namespace App\Http\Resources;

use App\Models\Patient;
use App\Services\PaymentService;
use Illuminate\Http\Request;

/**
 * The assistant's patient page (FR-I.3): contact info, the next appointment,
 * the visits' money and the outstanding balance. No current illness, no
 * history, no work done.
 *
 * @mixin Patient
 */
class AssistantPatientResource extends ApiResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $visits = $this->visits()->withPaid()->with('payments.recordedBy:id,name')
            ->orderByDesc('visit_date')
            ->orderByDesc('id')
            ->get();

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'name' => $this->user->name,
            'phone' => $this->user->phone,
            'email' => $this->user->email,
            'address' => $this->address,
            'date_of_birth' => $this->date_of_birth?->format('Y-m-d'),
            'gender' => $this->gender,
            'next_appointment' => ($next = $this->nextAppointment()) ? AppointmentResource::make($next) : null,
            'visits' => AssistantVisitResource::collection($visits),
            'outstanding_balance' => app(PaymentService::class)->sumRemaining($visits),
        ];
    }
}
