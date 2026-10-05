<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\AuthTokenResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * POST /auth/register — patient sign-up (FR-A.1).
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = DB::transaction(function () use ($request) {
            $user = User::create([
                ...$request->safe()->only(['name', 'phone', 'email', 'password']),
                'role' => UserRole::Patient,
            ]);
            $user->patient()->create($request->safe()->only(['address']));

            return $user;
        });

        return $this->tokenResponse($user, __('Account created.'))->setStatusCode(201);
    }

    /**
     * POST /auth/login — phone or email + password (FR-A.2).
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $login = $request->validated('login');

        $user = User::query()
            ->where(str_contains($login, '@') ? 'email' : 'phone', $login)
            ->first();

        if (! $user || ! Hash::check($request->validated('password'), $user->password)) {
            throw ValidationException::withMessages(['login' => __('auth.failed')]);
        }

        return $this->tokenResponse($user, __('Logged in.'));
    }

    /**
     * POST /auth/logout — revoke the token used for this request (FR-A.3).
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->message(__('Logged out.'));
    }

    /**
     * GET /me — the current user, their role and (for patients) patient id.
     */
    public function me(Request $request): UserResource
    {
        return UserResource::make($request->user()->loadMissing('patient'));
    }

    private function tokenResponse(User $user, string $message): JsonResponse
    {
        $token = $user->createToken('api')->plainTextToken;

        return (new AuthTokenResource($user->loadMissing('patient'), $token))
            ->withMessage($message)
            ->response();
    }
}
