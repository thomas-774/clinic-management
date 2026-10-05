<?php

namespace App\Http\Resources;

use App\Models\Payment;
use Illuminate\Http\Request;

/**
 * @mixin Payment
 */
class PaymentResource extends ApiResource
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
            'amount' => $this->amount,
            'method' => $this->method,
            'paid_at' => $this->paid_at,
            'recorded_by_name' => $this->whenLoaded('recordedBy', fn () => $this->recordedBy?->name),
        ];
    }
}
