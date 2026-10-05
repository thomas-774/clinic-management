<?php

namespace App\Http\Controllers\Api\V1\Doctor;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Doctor\StoreStaffRequest;
use App\Http\Requests\Doctor\UpdateStaffRequest;
use App\Http\Resources\ApiResourceCollection;
use App\Http\Resources\StaffResource;
use App\Models\User;
use App\Support\InitialPassword;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Settings → Staff: the doctor's assistant accounts (FR-I.1).
 */
class StaffController extends Controller
{
    /**
     * GET /doctor/staff — every assistant, active ones first, then by name.
     */
    public function index(): ApiResourceCollection
    {
        $staff = User::query()
            ->where('role', UserRole::Assistant)
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return StaffResource::collection($staff);
    }

    /**
     * POST /doctor/staff — the initial password is returned in this response only.
     */
    public function store(StoreStaffRequest $request): JsonResponse
    {
        $password = InitialPassword::generate();

        $assistant = User::create([
            ...$request->safe()->only(['name', 'phone', 'email']),
            'password' => $password,
            'role' => UserRole::Assistant,
        ]);

        return StaffResource::make($assistant)
            ->additional(['data' => ['initial_password' => $password]])
            ->withMessage(__('Assistant account created.'))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * PUT /doctor/staff/{staff} — edit, (de)activate, reset password. A
     * deactivated assistant or a reset password logs every session out.
     */
    public function update(UpdateStaffRequest $request, User $staff): JsonResponse
    {
        abort_unless($staff->isAssistant(), 404);

        $password = $request->boolean('reset_password') ? InitialPassword::generate() : null;

        DB::transaction(function () use ($request, $staff, $password) {
            $staff->fill($request->safe()->only(['name', 'phone', 'email', 'is_active']));
            if ($password !== null) {
                $staff->password = $password;
            }
            $staff->save();

            if ($password !== null || ! $staff->is_active) {
                $staff->tokens()->delete();
            }
        });

        $resource = StaffResource::make($staff->refresh());
        if ($password !== null) {
            $resource->additional(['data' => ['initial_password' => $password]]);
        }

        return $resource->withMessage(__('Assistant updated.'))->response();
    }
}
