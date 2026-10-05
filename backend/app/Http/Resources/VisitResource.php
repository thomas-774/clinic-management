<?php

namespace App\Http\Resources;

use App\Models\Visit;
use App\Services\PaymentService;
use Illuminate\Http\Request;

/**
 * One visit with its money (§6.4). paid, remaining and payment_status are
 * always computed on the server (PR-1, PR-3). Amounts are "1500.00" strings.
 *
 * @mixin Visit
 */
class VisitResource extends ApiResource
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
            'appointment_id' => $this->appointment_id,
            'visit_date' => $this->visit_date->format('Y-m-d'),
            'work_done' => $this->work_done,
            'total_amount' => $this->total_amount,
            'paid' => $payments->paid($this->resource),
            'remaining' => $payments->remaining($this->resource),
            'payment_status' => $payments->status($this->resource),
            'payments' => PaymentResource::collection($this->whenLoaded('payments', fn () => $this->payments->sortBy('paid_at')->values())),
        ];
    }
}
