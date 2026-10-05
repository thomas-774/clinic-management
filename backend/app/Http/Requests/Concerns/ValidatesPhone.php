<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Rule;

/**
 * Phone numbers are the login, so every form stores them the same way:
 * digits with an optional leading +, spaces and dashes removed.
 */
trait ValidatesPhone
{
    /**
     * @return array<int, mixed>
     */
    protected function phoneRules(?int $ignoreUserId = null): array
    {
        return [
            'string',
            'max:20',
            'regex:/^\+?\d{8,15}$/',
            Rule::unique('users', 'phone')->ignore($ignoreUserId),
        ];
    }

    protected function normalizePhone(): void
    {
        if (is_string($this->phone)) {
            $this->merge(['phone' => preg_replace('/[\s-]/', '', $this->phone)]);
        }
    }
}
