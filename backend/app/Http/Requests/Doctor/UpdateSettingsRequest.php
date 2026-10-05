<?php

namespace App\Http\Requests\Doctor;

use App\Models\DoctorSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Slot duration, booking window and cancellation cut-off (FR-G.2, BR-5), and
 * the prescription print header and paper size (FR-J.6). Each group can be
 * saved on its own: a field that is not sent keeps its value.
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
            'slot_duration_minutes' => ['sometimes', 'required', 'integer', 'between:10,240'],
            'booking_window_days' => ['sometimes', 'required', 'integer', 'between:1,90'],
            'cancel_cutoff_hours' => ['sometimes', 'required', 'integer', 'between:0,72'],
            'clinic_name' => ['sometimes', 'nullable', 'string', 'max:150'],
            'doctor_title' => ['sometimes', 'nullable', 'string', 'max:150'],
            'clinic_address' => ['sometimes', 'nullable', 'string', 'max:150'],
            'clinic_phone' => ['sometimes', 'nullable', 'string', 'max:150'],
            'prescription_footer' => ['sometimes', 'nullable', 'string', 'max:255'],
            'prescription_paper' => ['sometimes', 'required', Rule::in(DoctorSetting::PAPER_SIZES)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'clinic_name' => __('clinic name'),
            'doctor_title' => __('doctor title'),
            'clinic_address' => __('clinic address'),
            'clinic_phone' => __('clinic phone'),
            'prescription_footer' => __('prescription footer'),
            'prescription_paper' => __('paper size'),
        ];
    }
}
