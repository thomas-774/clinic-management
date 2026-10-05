<?php

namespace App\Http\Resources;

use App\Models\Patient;
use App\Services\PaymentService;
use Illuminate\Http\Request;

/**
 * The doctor's patient page (FR-C.2 – C.5): info, every history entry
 * (private ones included), the visit timeline with balances and the
 * patient's outstanding balance (FR-D.5, FR-D.7).
 *
 * @mixin Patient
 */
class PatientDetailResource extends PatientResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // One query for the visits (with their paid sums) and one for all payments.
        $visits = $this->visits()->withPaid()->with('payments')
            ->orderByDesc('visit_date')
            ->orderByDesc('id')
            ->get();

        return [
            ...parent::toArray($request),
            'history' => DetailedHistoryEntryResource::collection(
                $this->medicalHistoryEntries()->orderByDesc('recorded_on')->orderByDesc('id')->get(),
            ),
            'visits' => VisitResource::collection($visits),
            'outstanding_balance' => app(PaymentService::class)->sumRemaining($visits),
        ];
    }
}
