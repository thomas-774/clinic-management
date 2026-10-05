<?php

namespace App\Http\Requests\Doctor;

/**
 * Edit any field of a drug, or hide / show it with `is_active` alone (FR-J.6).
 * Every field is optional; a field that is sent follows the same rules as on create.
 */
class UpdateDrugRequest extends StoreDrugRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return collect(parent::rules())
            ->map(fn (array $rules, string $field) => str_contains($field, '.*.') || in_array('sometimes', $rules, true)
                ? $rules
                : ['sometimes', ...$rules])
            ->all();
    }
}
