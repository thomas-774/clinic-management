<?php

namespace App\Http\Requests\Doctor;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Slot duration, booking window and cancellation cut-off (FR-G.2, BR-5).
 */
class UpdateSettingsRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'slot_duration_minutes' => ['required', 'integer', 'between:10,240'],
            'booking_window_days' => ['required', 'integer', 'between:1,90'],
            'cancel_cutoff_hours' => ['required', 'integer', 'between:0,72'],
        ];
    }
}
