<?php

namespace App\Http\Resources;

use App\Models\Prescription;
use Illuminate\Http\Request;

/**
 * A row of the patient's prescriptions list (FR-J.7): date and drug names.
 *
 * @mixin Prescription
 */
class PrescriptionListItemResource extends ApiResource
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
            'issued_on' => $this->issued_on->format('Y-m-d'),
            'visit_id' => $this->visit_id,
            'notes' => $this->notes,
            'drug_names' => $this->items->pluck('drug_name')->all(),
        ];
    }
}
