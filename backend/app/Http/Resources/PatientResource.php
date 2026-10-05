<?php

namespace App\Http\Resources;

use App\Models\Patient;
use Illuminate\Http\Request;

/**
 * Personal info shared by the patient page and the doctor patient page (FR-B.1, FR-B.2, FR-C.2).
 *
 * @mixin Patient
 */
class PatientResource extends ApiResource
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
            'user_id' => $this->user_id,
            'name' => $this->user->name,
            'phone' => $this->user->phone,
            'email' => $this->user->email,
            'address' => $this->address,
            'date_of_birth' => $this->date_of_birth?->format('Y-m-d'),
            'gender' => $this->gender,
            'current_illness' => $this->current_illness,
        ];
    }
}
