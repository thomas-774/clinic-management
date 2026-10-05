<?php

namespace App\Http\Controllers\Api\V1\Doctor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Doctor\StorePrescriptionRequest;
use App\Http\Resources\ApiResourceCollection;
use App\Http\Resources\PrescriptionListItemResource;
use App\Http\Resources\PrescriptionResource;
use App\Models\Patient;
use App\Models\Prescription;
use App\Services\PrescriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Prescriptions (Module J). Doctor only: patients and the assistant never
 * see them in v1 (FR-J.7).
 */
class PrescriptionController extends Controller
{
    public function __construct(private readonly PrescriptionService $prescriptions) {}

    /**
     * GET /doctor/patients/{patient}/prescriptions — newest first, with drug names (FR-J.7).
     */
    public function index(Patient $patient): ApiResourceCollection
    {
        Gate::authorize('managePrescriptions', $patient);

        $prescriptions = $patient->prescriptions()
            ->with('items')
            ->orderByDesc('issued_on')
            ->orderByDesc('id')
            ->get();

        return PrescriptionListItemResource::collection($prescriptions);
    }

    /**
     * POST /doctor/patients/{patient}/prescriptions
     */
    public function store(StorePrescriptionRequest $request, Patient $patient): JsonResponse
    {
        Gate::authorize('managePrescriptions', $patient);

        $prescription = $this->prescriptions->save($patient, $request->user(), $request->validated());

        return $this->resource($prescription)
            ->withMessage(__('Prescription saved.'))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /doctor/prescriptions/{prescription} — with patient name / age and the print header (FR-J.5).
     */
    public function show(Prescription $prescription): PrescriptionResource
    {
        Gate::authorize('managePrescriptions', $prescription->patient);

        return $this->resource($prescription);
    }

    /**
     * PUT /doctor/prescriptions/{prescription} — replaces the lines.
     */
    public function update(StorePrescriptionRequest $request, Prescription $prescription): PrescriptionResource
    {
        Gate::authorize('managePrescriptions', $prescription->patient);

        $this->prescriptions->save($prescription->patient, $request->user(), $request->validated(), $prescription);

        return $this->resource($prescription)->withMessage(__('Prescription updated.'));
    }

    /**
     * DELETE /doctor/prescriptions/{prescription} — the lines go with it.
     */
    public function destroy(Prescription $prescription): JsonResponse
    {
        Gate::authorize('managePrescriptions', $prescription->patient);

        DB::transaction(fn () => $prescription->delete());

        return response()->json(['data' => null, 'message' => __('Prescription deleted.')]);
    }

    private function resource(Prescription $prescription): PrescriptionResource
    {
        return PrescriptionResource::make($prescription->fresh(['items', 'patient.user', 'doctor.doctorSetting']));
    }
}
