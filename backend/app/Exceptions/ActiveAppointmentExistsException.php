<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * The patient already holds the allowed number of upcoming appointments
 * (BR-4, config clinic.max_active_appointments) → 422.
 */
class ActiveAppointmentExistsException extends HttpException
{
    public function __construct()
    {
        parent::__construct(Response::HTTP_UNPROCESSABLE_ENTITY, 'You already have an upcoming appointment.');
    }

    /**
     * Same shape as a validation error, so forms can show it on start_at.
     */
    public function render(): JsonResponse
    {
        $message = __($this->getMessage());

        return response()->json([
            'message' => $message,
            'errors' => ['start_at' => [$message]],
        ], $this->getStatusCode());
    }
}
