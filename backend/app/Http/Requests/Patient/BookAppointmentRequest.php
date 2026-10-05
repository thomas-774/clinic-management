<?php

namespace App\Http\Requests\Patient;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Book { start_at } (FR-E.3). Whether it is a free slot is checked by
 * BookingService against freshly generated slots (BR-1).
 */
class BookAppointmentRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'start_at' => ['required', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'start_at' => __('start time'),
        ];
    }
}
