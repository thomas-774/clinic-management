<?php

namespace App\Http\Controllers\Api\V1\Assistant;

use App\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Assistant\UpdateAppointmentStatusRequest;
use App\Http\Requests\Doctor\BookForPatientRequest;
use App\Http\Requests\Doctor\ScheduleRangeRequest;
use App\Http\Resources\ApiResourceCollection;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use App\Services\AppointmentService;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

/**
 * The front desk's view of the clinic doctor's schedule: today's queue,
 * check-in, no-show, cancel and booking for a patient (FR-I.6).
 */
class ScheduleController extends Controller
{
    public function __construct(private readonly AppointmentService $appointments) {}

    /**
     * GET /assistant/appointments?from=&to= — the doctor's schedule.
     */
    public function index(ScheduleRangeRequest $request): ApiResourceCollection
    {
        return AppointmentResource::collection($this->appointments->schedule(
            User::clinicDoctor(),
            $request->validated('from'),
            $request->validated('to'),
        ));
    }

    /**
     * POST /assistant/appointments { patient_id, start_at } — same rules as
     * every booking (BookingService).
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

    /**
     * PATCH /assistant/appointments/{appointment}/status { status } — Arrived,
     * No-show or Cancelled; the patient cut-off (BR-5) does not apply.
     */
    public function updateStatus(UpdateAppointmentStatusRequest $request, Appointment $appointment): AppointmentResource
    {
        abort_unless($appointment->doctor_id === User::clinicDoctor()->id, 404);

        $appointment = $this->appointments->changeStatus($appointment, AppointmentStatus::from($request->validated('status')));

        return AppointmentResource::make($appointment->load('patient.user'))->withMessage(__('Status updated.'));
    }
}
