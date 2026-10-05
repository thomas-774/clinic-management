<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Patient self-registration (FR-A.1).
 */
class RegisterRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:20', 'regex:/^\+?\d{8,15}$/', 'unique:users,phone'],
            'email' => ['nullable', 'string', 'email', 'max:150', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', Password::min(8)],
            'address' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * Trim the phone and treat an empty email as no email.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone' => is_string($this->phone) ? preg_replace('/[\s-]/', '', $this->phone) : $this->phone,
            'email' => filled($this->email) ? $this->email : null,
        ]);
    }
}
