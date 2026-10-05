<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

abstract class Controller
{
    /**
     * A response with no resource to return, in the { data, message } shape.
     */
    protected function message(string $message, int $status = 200, mixed $data = null): JsonResponse
    {
        return response()->json(['data' => $data, 'message' => $message], $status);
    }
}
