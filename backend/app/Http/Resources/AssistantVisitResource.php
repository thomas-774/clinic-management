<?php

namespace App\Http\Resources;

use App\Models\Visit;
use App\Services\PaymentService;
use Illuminate\Http\Request;

/**
 * A visit as the assistant sees it: the money only, never `work_done`
 * (FR-I.3, FR-I.4). The patient is added when loaded (waiting-to-pay list).
 *
 * @mixin Visit
 */
class AssistantVisitResource extends ApiResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $payments = app(PaymentService::class);

        return [
            'id' => $this->id,
            'patient_id' => $this->patient_id,
            'visit_date' => $this->visit_date->format('Y-m-d'),
            'total_amount' => $this->total_amount,
            'paid' => $payments->paid($this->resource),
            'remaining' => $payments->remaining($this->resource),
            'payment_status' => $payments->status($this->resource),
            'patient' => $this->whenLoaded('patient', fn () => [
                'id' => $this->patient->id,
                'name' => $this->patient->user->name,
                'phone' => $this->patient->user->phone,
            ]),
            'payments' => PaymentResource::collection($this->whenLoaded('payments', fn () => $this->payments->sortBy('paid_at')->values())),
        ];
    }
}
