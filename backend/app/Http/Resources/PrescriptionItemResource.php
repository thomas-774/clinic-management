<?php

namespace App\Http\Resources;

use App\Models\PrescriptionItem;
use Illuminate\Http\Request;

/**
 * One prescription line with its name / form snapshot (RX-2).
 *
 * @mixin PrescriptionItem
 */
class PrescriptionItemResource extends ApiResource
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
            'position' => $this->position,
            'drug_id' => $this->drug_id,
            'drug_name' => $this->drug_name,
            'drug_form' => $this->drug_form,
            'instructions' => $this->instructions,
        ];
    }
}
