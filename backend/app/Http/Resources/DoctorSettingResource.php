<?php

namespace App\Http\Resources;

use App\Models\DoctorSetting;
use Illuminate\Http\Request;

/**
 * @mixin DoctorSetting
 */
class DoctorSettingResource extends ApiResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'slot_duration_minutes' => $this->slot_duration_minutes,
            'booking_window_days' => $this->booking_window_days,
            'cancel_cutoff_hours' => $this->cancel_cutoff_hours,
        ];
    }
}
