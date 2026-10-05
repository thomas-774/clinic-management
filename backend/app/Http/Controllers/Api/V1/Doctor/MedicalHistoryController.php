<?php

namespace App\Http\Controllers\Api\V1\Doctor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Doctor\HistoryEntryRequest;
use App\Http\Resources\DetailedHistoryEntryResource;
use App\Models\MedicalHistoryEntry;
use App\Models\Patient;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * The doctor manages a patient's history (FR-C.4). Routes use scoped
 * bindings, so an entry that belongs to another patient is a 404.
 */
class MedicalHistoryController extends Controller
{
    /**
     * POST /doctor/patients/{patient}/history
     */
    public function store(HistoryEntryRequest $request, Patient $patient): JsonResponse
    {
        Gate::authorize('manageHistory', $patient);

        $entry = $patient->medicalHistoryEntries()->create($request->entryAttributes());

        return DetailedHistoryEntryResource::make($entry->refresh())
            ->withMessage(__('History entry added.'))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * PUT /doctor/patients/{patient}/history/{medicalHistoryEntry}
     */
    public function update(HistoryEntryRequest $request, Patient $patient, MedicalHistoryEntry $medicalHistoryEntry): DetailedHistoryEntryResource
    {
        Gate::authorize('manageHistory', $patient);

        $medicalHistoryEntry->update($request->entryAttributes());

        return DetailedHistoryEntryResource::make($medicalHistoryEntry)->withMessage(__('History entry updated.'));
    }

    /**
     * DELETE /doctor/patients/{patient}/history/{medicalHistoryEntry}
     */
    public function destroy(Patient $patient, MedicalHistoryEntry $medicalHistoryEntry): JsonResponse
    {
        Gate::authorize('manageHistory', $patient);

        $medicalHistoryEntry->delete();

        return $this->message(__('History entry deleted.'));
    }
}
