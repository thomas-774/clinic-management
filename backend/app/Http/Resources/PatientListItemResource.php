<?php

namespace App\Http\Resources;

use App\Models\Patient;
use Illuminate\Http\Request;

/**
 * One row of the doctor's patient list (FR-C.1).
 *
 * @mixin Patient
 */
class PatientListItemResource extends ApiResource
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
            'name' => $this->user->name,
            'phone' => $this->user->phone,
            'last_visit_date' => $this->visits_max_visit_date
                ? substr((string) $this->visits_max_visit_date, 0, 10)
                : null,
        ];
    }
}
