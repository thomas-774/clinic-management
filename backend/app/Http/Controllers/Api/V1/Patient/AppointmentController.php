<?php

namespace App\Http\Controllers\Api\V1\Patient;

use App\Http\Controllers\Controller;
use App\Http\Requests\Patient\BookAppointmentRequest;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Models\Patient;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/**
 * The logged-in patient's own appointments (FR-E.3, FR-E.4).
 */
class AppointmentController extends Controller
{
    /**
     * GET /patient/appointments — { upcoming, past }; upcoming soonest first,
     * past (including cancelled and no-show) newest first.
     */
    public function index(Request $request): JsonResponse
    {
        $appointments = $this->ownPatient($request)->appointments()
            ->with('doctor.doctorSetting')
            ->orderBy('start_at')
            ->get();

        [$upcoming, $past] = $appointments->partition(fn (Appointment $appointment) => $appointment->isUpcoming());

        return response()->json([
            'data' => [
                'upcoming' => AppointmentResource::collection($upcoming->values())->resolve($request),
                'past' => AppointmentResource::collection($past->reverse()->values())->resolve($request),
            ],
            'message' => null,
        ]);
    }

    /**
     * POST /patient/appointments { start_at }
     */
    public function store(BookAppointmentRequest $request): JsonResponse
    {
        $appointment = BookingService::forClinic()->book(
            $this->ownPatient($request),
            Carbon::parse($request->validated('start_at')),
        );

        return AppointmentResource::make($appointment)
            ->withMessage(__('Appointment booked.'))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * PATCH /patient/appointments/{appointment}/cancel — own and booked only,
     * until the cancellation cut-off (BR-5).
     */
    public function cancel(Appointment $appointment): AppointmentResource
    {
        Gate::authorize('cancel', $appointment);

        abort_unless($appointment->patientCanCancel(), 422, 'Too late to cancel online, please call the clinic.');

        $appointment->cancel();

        return AppointmentResource::make($appointment)->withMessage(__('Appointment cancelled.'));
    }

    private function ownPatient(Request $request): Patient
    {
        return $request->user()->patient()->firstOrFail();
    }
}
