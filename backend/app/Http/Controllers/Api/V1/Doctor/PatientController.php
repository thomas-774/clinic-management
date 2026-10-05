<?php

namespace App\Http\Controllers\Api\V1\Doctor;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Doctor\StorePatientRequest;
use App\Http\Requests\Doctor\UpdatePatientRequest;
use App\Http\Resources\ApiResourceCollection;
use App\Http\Resources\PatientDetailResource;
use App\Http\Resources\PatientListItemResource;
use App\Http\Resources\PatientResource;
use App\Models\Patient;
use App\Models\User;
use App\Support\InitialPassword;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class PatientController extends Controller
{
    public const PER_PAGE = 20;

    /**
     * GET /doctor/patients?search= — search by name or phone, sorted by name (FR-C.1).
     */
    public function index(Request $request): ApiResourceCollection
    {
        Gate::authorize('viewAny', Patient::class);

        $search = trim((string) $request->query('search', ''));

        $patients = Patient::query()
            ->select('patients.*')
            ->join('users', 'users.id', '=', 'patients.user_id')
            ->with('user')
            ->withMax('visits', 'visit_date')
            ->when($search !== '', function ($query) use ($search) {
                $like = '%'.addcslashes($search, '\\%_').'%';
                $query->where(fn ($q) => $q
                    ->where('users.name', 'like', $like)
                    ->orWhere('users.phone', 'like', $like));
            })
            ->orderBy('users.name')
            ->orderBy('patients.id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return PatientListItemResource::collection($patients);
    }

    /**
     * POST /doctor/patients — create the account; the initial password is
     * returned in this response only and stored hashed (FR-C.6).
     */
    public function store(StorePatientRequest $request): JsonResponse
    {
        Gate::authorize('create', Patient::class);

        $password = InitialPassword::generate();

        $patient = DB::transaction(function () use ($request, $password) {
            $user = User::create([
                ...$request->safe()->only(['name', 'phone', 'email']),
                'password' => $password,
                'role' => UserRole::Patient,
            ]);

            return $user->patient()->create(
                $request->safe()->only(['address', 'date_of_birth', 'gender', 'current_illness']),
            );
        });

        return PatientResource::make($patient->load('user'))
            ->additional(['data' => ['initial_password' => $password]])
            ->withMessage(__('Patient account created.'))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /doctor/patients/{patient} — full profile with detailed history (FR-C.2, C.3).
     */
    public function show(Patient $patient): PatientDetailResource
    {
        Gate::authorize('view', $patient);

        return PatientDetailResource::make($patient->load('user'));
    }

    /**
     * PUT /doctor/patients/{patient} — info and current illness (FR-C.2).
     */
    public function update(UpdatePatientRequest $request, Patient $patient): PatientDetailResource
    {
        Gate::authorize('update', $patient);

        DB::transaction(function () use ($request, $patient) {
            $patient->user->update($request->safe()->only(['name', 'phone']));
            $patient->update($request->safe()->only(['address', 'date_of_birth', 'gender', 'current_illness']));
        });

        return PatientDetailResource::make($patient->refresh()->load('user'))
            ->withMessage(__('Patient updated.'));
    }
}
