<?php

namespace App\Http\Controllers\Api\V1\Doctor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Doctor\BookForPatientRequest;
use App\Http\Resources\ApiResourceCollection;
use App\Http\Resources\AppointmentResource;
use App\Models\Patient;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * The doctor's schedule (FR-F.1, FR-F.2) and booking on behalf of a patient.
 */
class ScheduleController extends Controller
{
    /**
     * GET /doctor/appointments?from=&to= — every status, ordered by time.
     * Both dates are inclusive; the default range is today.
     */
    public function index(Request $request): ApiResourceCollection
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $from = Carbon::parse($validated['from'] ?? today()->toDateString())->startOfDay();
        $to = Carbon::parse($validated['to'] ?? $from->toDateString())->addDay()->startOfDay();

        $appointments = $request->user()->doctorAppointments()
            ->with('patient.user')
            ->where('start_at', '>=', $from)
            ->where('start_at', '<', $to)
            ->orderBy('start_at')
            ->orderBy('id')
            ->get();

        return AppointmentResource::collection($appointments);
    }

    /**
     * POST /doctor/appointments { patient_id, start_at } — same rules as the
     * patient booking (BookingService).
     */
    public function store(BookForPatientRequest $request): JsonResponse
    {
        $appointment = BookingService::forClinic()->book(
            Patient::findOrFail($request->validated('patient_id')),
            Carbon::parse($request->validated('start_at')),
        );

        return AppointmentResource::make($appointment->load('patient.user'))
            ->withMessage(__('Appointment booked.'))
            ->response()
            ->setStatusCode(201);
    }
}
