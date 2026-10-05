<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * Response of register / login: { data: { token, user }, message }.
 */
class AuthTokenResource extends ApiResource
{
    public function __construct(User $user, private string $token)
    {
        parent::__construct($user);
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'token' => $this->token,
            'user' => UserResource::make($this->resource),
        ];
    }
}
