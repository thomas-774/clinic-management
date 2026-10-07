<?php

namespace App\Http\Controllers\Api\V1\Assistant;

use App\Enums\AuditAction;
use App\Http\Controllers\Api\V1\Doctor\PatientController as DoctorPatientController;
use App\Http\Controllers\Controller;
use App\Http\Requests\Assistant\StorePatientRequest;
use App\Http\Requests\Assistant\UpdatePatientRequest;
use App\Http\Resources\ApiResourceCollection;
use App\Http\Resources\AssistantPatientResource;
use App\Http\Resources\PatientListItemResource;
use App\Models\Patient;
use App\Services\AuditLogger;
use App\Services\PatientAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * The front desk's patients (FR-I.2, FR-I.3): contact info and money only.
 */
class PatientController extends Controller
{
    /**
     * GET /assistant/patients?search= — same list and search as the doctor's.
     */
    public function index(Request $request): ApiResourceCollection
    {
        Gate::authorize('viewAny', Patient::class);

        $patients = Patient::query()
            ->forList(trim((string) $request->query('search', '')))
            ->paginate(DoctorPatientController::PER_PAGE)
            ->withQueryString();

        return PatientListItemResource::collection($patients);
    }

    /**
     * POST /assistant/patients — a patient who does not exist yet; the initial
     * password is returned in this response only.
     */
    public function store(StorePatientRequest $request, PatientAccountService $accounts): JsonResponse
    {
        Gate::authorize('create', Patient::class);

        [$patient, $password] = $accounts->create(
            $request->safe()->only(['name', 'phone', 'email']),
            $request->safe()->only(['address', 'date_of_birth', 'gender']),
        );

        return AssistantPatientResource::make($patient)
            ->additional(['data' => ['initial_password' => $password]])
            ->withMessage(__('Patient account created.'))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /assistant/patients/{patient} — contact info, visits' money, balance.
     * Audited like the doctor's page (NFR-S.4).
     */
    public function show(Patient $patient, AuditLogger $audit): AssistantPatientResource
    {
        Gate::authorize('view', $patient);

        $audit->record(AuditAction::Viewed, $patient, $patient);

        return AssistantPatientResource::make($patient->load('user'));
    }

    /**
     * PUT /assistant/patients/{patient} — contact info only.
     */
    public function update(UpdatePatientRequest $request, Patient $patient): AssistantPatientResource
    {
        Gate::authorize('update', $patient);

        DB::transaction(function () use ($request, $patient) {
            $patient->user->update($request->safe()->only(['name', 'phone']));
            $patient->update($request->safe()->only(['address', 'date_of_birth', 'gender']));
        });

        return AssistantPatientResource::make($patient->refresh()->load('user'))
            ->withMessage(__('Patient updated.'));
    }
}
