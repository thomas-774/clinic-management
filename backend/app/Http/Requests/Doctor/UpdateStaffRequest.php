<?php

namespace App\Http\Requests\Doctor;

use App\Http\Requests\Concerns\ValidatesPhone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Edit, deactivate or reset the password of an assistant (FR-I.1).
 * Every field is optional, so the Activate switch can send `is_active` alone.
 */
class UpdateStaffRequest extends FormRequest
{
    use ValidatesPhone;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $id = $this->route('staff')?->id;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'phone' => ['sometimes', 'required', ...$this->phoneRules($id)],
            'email' => ['sometimes', 'nullable', 'string', 'email', 'max:150', Rule::unique('users', 'email')->ignore($id)],
            'is_active' => ['sometimes', 'boolean'],
            'reset_password' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->normalizePhone();
        if ($this->has('email')) {
            $this->merge(['email' => filled($this->email) ? $this->email : null]);
        }
    }
}
