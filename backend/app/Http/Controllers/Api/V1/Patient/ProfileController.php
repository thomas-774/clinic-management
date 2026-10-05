<?php

namespace App\Http\Controllers\Api\V1\Patient;

use App\Http\Controllers\Controller;
use App\Http\Requests\Patient\UpdateOwnProfileRequest;
use App\Http\Resources\PatientProfileResource;
use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * The logged-in patient's own record. There is no patient route that takes
 * an id, so a patient can never ask for someone else's data.
 */
class ProfileController extends Controller
{
    /**
     * GET /patient/profile (FR-B.1 – B.4).
     */
    public function show(Request $request): PatientProfileResource
    {
        return PatientProfileResource::make($this->ownPatient($request));
    }

    /**
     * PATCH /patient/profile — phone and address only (FR-B.5).
     */
    public function update(UpdateOwnProfileRequest $request): PatientProfileResource
    {
        $patient = $this->ownPatient($request);
        Gate::authorize('update', $patient);

        DB::transaction(function () use ($request, $patient) {
            if ($request->has('phone')) {
                $patient->user->update(['phone' => $request->validated('phone')]);
            }
            if ($request->has('address')) {
                $patient->update(['address' => $request->validated('address')]);
            }
        });

        return PatientProfileResource::make($patient->refresh())->withMessage(__('Profile updated.'));
    }

    private function ownPatient(Request $request): Patient
    {
        $patient = $request->user()->patient()->with('user')->firstOrFail();
        Gate::authorize('view', $patient);

        return $patient;
    }
}
