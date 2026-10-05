<?php

namespace App\Http\Controllers\Api\V1\Doctor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Doctor\UpdateSettingsRequest;
use App\Http\Resources\DoctorSettingResource;
use App\Models\DoctorSetting;
use Illuminate\Http\Request;

/**
 * The doctor's booking settings (FR-G.2). Changes only affect slots generated
 * from now on; booked appointments keep their stored times (FR-G.4, BR-3).
 */
class SettingsController extends Controller
{
    /**
     * GET /doctor/settings
     */
    public function show(Request $request): DoctorSettingResource
    {
        return DoctorSettingResource::make($this->settings($request));
    }

    /**
     * PUT /doctor/settings
     */
    public function update(UpdateSettingsRequest $request): DoctorSettingResource
    {
        $settings = $this->settings($request);
        $settings->update($request->validated());

        return DoctorSettingResource::make($settings)->withMessage(__('Settings saved.'));
    }

    /**
     * The row is seeded, but create it with the defaults if it is missing.
     */
    private function settings(Request $request): DoctorSetting
    {
        return $request->user()->doctorSetting()->firstOrCreate([]);
    }
}
