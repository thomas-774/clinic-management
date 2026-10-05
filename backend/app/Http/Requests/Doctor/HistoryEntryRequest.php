<?php

namespace App\Http\Requests\Doctor;

use App\Enums\HistoryType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create or edit a medical history entry (FR-C.4). Entries are private
 * unless patient_visible is true.
 */
class HistoryEntryRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(HistoryType::class)],
            'title' => ['required', 'string', 'max:150'],
            'details' => ['nullable', 'string', 'max:5000'],
            'patient_visible' => ['sometimes', 'boolean'],
            'recorded_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function entryAttributes(): array
    {
        return [
            ...$this->safe()->only(['type', 'title', 'details', 'recorded_on']),
            'patient_visible' => $this->boolean('patient_visible'),
        ];
    }
}
