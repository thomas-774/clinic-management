<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Base class for every API resource: responses have the shape { data, message } (§6.2).
 *
 *     return UserResource::make($user)->withMessage(__('Logged in.'));
 */
abstract class ApiResource extends JsonResource
{
    protected ?string $message = null;

    public function withMessage(?string $message): static
    {
        $this->message = $message;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        return array_merge(parent::with($request), ['message' => $this->message]);
    }

    /**
     * Collections of this resource share the same { data, message } shape.
     */
    protected static function newCollection($resource): ApiResourceCollection
    {
        return new ApiResourceCollection($resource, static::class);
    }
}
