<?php

namespace App\Http\Requests\Doctor;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The doctor books { patient_id, start_at } on behalf of a patient (§6.3).
 * The slot itself is checked by BookingService (BR-1).
 */
class BookForPatientRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'start_at' => ['required', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'patient_id' => __('patient'),
            'start_at' => __('start time'),
        ];
    }
}
