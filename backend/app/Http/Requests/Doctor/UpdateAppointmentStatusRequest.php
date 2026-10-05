<?php

namespace App\Http\Requests\Doctor;

use App\Enums\AppointmentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * { status }: checked_in, completed, no_show or cancelled (FR-F.3, FR-F.4).
 * Whether the change is allowed from the current status is checked by the
 * controller (§4.3).
 */
class UpdateAppointmentStatusRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(AppointmentStatus::class)->except(AppointmentStatus::Booked)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'status' => __('status'),
        ];
    }
}
