<?php

namespace App\Http\Requests\Doctor;

use App\Http\Requests\Concerns\ValidatesPhone;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The doctor adds an assistant account (FR-I.1).
 */
class StoreStaffRequest extends FormRequest
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
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->normalizePhone();
        $this->merge(['email' => filled($this->email) ? $this->email : null]);
    }
}
