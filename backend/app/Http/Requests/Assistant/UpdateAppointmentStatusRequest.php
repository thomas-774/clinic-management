<?php

namespace App\Http\Requests\Assistant;

use App\Enums\AppointmentStatus;
use App\Http\Requests\Doctor\UpdateAppointmentStatusRequest as DoctorUpdateAppointmentStatusRequest;
use Illuminate\Validation\Rule;

/**
 * { status }: checked_in, no_show or cancelled (FR-I.6). Completed comes only
 * from the doctor's visit.
 */
class UpdateAppointmentStatusRequest extends DoctorUpdateAppointmentStatusRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(AppointmentStatus::class)->only([
                AppointmentStatus::CheckedIn,
                AppointmentStatus::NoShow,
                AppointmentStatus::Cancelled,
            ])],
        ];
    }
}
