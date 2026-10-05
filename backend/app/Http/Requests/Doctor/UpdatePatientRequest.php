<?php

namespace App\Http\Requests\Doctor;

use App\Http\Requests\Concerns\ValidatesPhone;
use App\Models\Patient;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The doctor edits a patient's info and current illness (FR-C.2).
 */
class UpdatePatientRequest extends FormRequest
{
    use ValidatesPhone;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Patient $patient */
        $patient = $this->route('patient');

        return [
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', ...$this->phoneRules($patient->user_id)],
            'address' => ['required', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'gender' => ['nullable', 'in:male,female'],
            'current_illness' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->normalizePhone();
    }
}
