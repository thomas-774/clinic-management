<?php

namespace App\Http\Requests\Doctor;

use App\Http\Requests\Concerns\ValidatesPhone;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The doctor creates an account for a patient who called by phone (FR-C.6).
 */
class StorePatientRequest extends FormRequest
{
    use ValidatesPhone;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', ...$this->phoneRules()],
            'email' => ['nullable', 'string', 'email', 'max:150', 'unique:users,email'],
            'address' => ['required', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'gender' => ['nullable', 'in:male,female'],
            'current_illness' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->normalizePhone();
        $this->merge(['email' => filled($this->email) ? $this->email : null]);
    }
}
