<?php

namespace App\Http\Requests\Assistant;

use App\Http\Requests\Doctor\UpdatePatientRequest as DoctorUpdatePatientRequest;
use Illuminate\Support\Arr;

/**
 * The assistant edits contact info only; the illness stays with the doctor (FR-I.3).
 */
class UpdatePatientRequest extends DoctorUpdatePatientRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return Arr::except(parent::rules(), ['current_illness']);
    }
}
