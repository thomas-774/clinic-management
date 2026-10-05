<?php

namespace App\Http\Requests\Assistant;

use App\Http\Requests\Doctor\StorePatientRequest as DoctorStorePatientRequest;
use Illuminate\Support\Arr;

/**
 * The assistant registers a patient who does not exist yet (FR-I.2): the
 * doctor's form without the current illness, which is medical data.
 */
class StorePatientRequest extends DoctorStorePatientRequest
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
