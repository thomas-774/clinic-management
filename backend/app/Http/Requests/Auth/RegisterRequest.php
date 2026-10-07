<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\Concerns\ValidatesPhone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Patient self-registration (FR-A.1).
 */
class RegisterRequest extends FormRequest
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
            // ASVS 2.1.2: 64 characters allowed, more than 128 refused.
            'password' => ['required', 'string', 'confirmed', Password::min(8), 'max:128'],
            'address' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * Trim the phone and treat an empty email as no email.
     */
    protected function prepareForValidation(): void
    {
        $this->normalizePhone();
        $this->merge(['email' => filled($this->email) ? $this->email : null]);
    }
}
