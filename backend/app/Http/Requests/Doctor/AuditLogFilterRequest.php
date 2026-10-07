<?php

namespace App\Http\Requests\Doctor;

use App\Enums\AuditAction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filters of Settings → Activity (NFR-S.5): ?patient_id=&user_id=&action=&from=&to=,
 * dates inclusive "YYYY-MM-DD" in clinic time.
 */
class AuditLogFilterRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'patient_id' => ['nullable', 'integer'],
            'user_id' => ['nullable', 'integer'],
            'action' => ['nullable', Rule::enum(AuditAction::class)],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
