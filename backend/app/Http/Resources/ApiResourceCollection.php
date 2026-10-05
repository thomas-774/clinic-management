<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Collection returned by ApiResource::collection(); adds the message key.
 */
class ApiResourceCollection extends AnonymousResourceCollection
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
}
