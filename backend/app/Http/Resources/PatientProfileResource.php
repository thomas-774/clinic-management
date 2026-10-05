<?php

namespace App\Http\Resources;

use App\Models\Patient;
use App\Models\Visit;
use App\Services\PaymentService;
use Illuminate\Http\Request;

/**
 * The patient's own page (FR-B.1 – B.4): personal info, illness, simple
 * history (visible entries only), next appointment and balance. Visits are
 * listed only for what is still owed (date and remaining), without the
 * doctor's work notes (T5-07).
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
        $payments = app(PaymentService::class);
        $visits = $this->visits()->withPaid()->orderByDesc('visit_date')->orderByDesc('id')->get();

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
            'outstanding_balance' => $payments->sumRemaining($visits),
            'unpaid_visits' => $visits
                ->filter(fn (Visit $visit) => bccomp($payments->remaining($visit), '0', 2) > 0)
                ->map(fn (Visit $visit) => [
                    'id' => $visit->id,
                    'visit_date' => $visit->visit_date->format('Y-m-d'),
                    'remaining' => $payments->remaining($visit),
                ])
                ->values(),
        ];
    }
}
