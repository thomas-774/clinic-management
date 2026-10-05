<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Login with phone or email + password (FR-A.2).
 */
class LoginRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'login' => ['required', 'string', 'max:150'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'login' => __('phone or email'),
        ];
    }

    /**
     * Phone numbers may be typed with spaces or dashes.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->login)) {
            $login = trim($this->login);

            $this->merge([
                'login' => str_contains($login, '@') ? $login : preg_replace('/[\s-]/', '', $login),
            ]);
        }
    }
}
