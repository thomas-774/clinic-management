<?php

namespace App\Http\Controllers\Api\V1\Doctor;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Doctor\StoreVisitRequest;
use App\Http\Requests\Doctor\UpdateVisitRequest;
use App\Http\Resources\VisitResource;
use App\Models\Appointment;
use App\Models\Visit;
use App\Services\PaymentService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Visits and their payments (Module D).
 */
class VisitController extends Controller
{
    /**
     * POST /doctor/visits — the visit, its first payment and the appointment
     * set to Completed, all in one transaction (§6.4).
     */
    public function store(StoreVisitRequest $request): JsonResponse
    {
        $visit = DB::transaction(function () use ($request) {
            $appointment = $request->filled('appointment_id')
                ? Appointment::query()->lockForUpdate()->findOrFail($request->integer('appointment_id'))
                : null;

            try {
                $visit = Visit::create([
                    'patient_id' => $request->integer('patient_id'),
                    'appointment_id' => $appointment?->id,
                    'visit_date' => today(),
                    'work_done' => $request->validated('work_done'),
                    'total_amount' => PaymentService::money($request->validated('total_amount')),
                ]);
            } catch (UniqueConstraintViolationException) {
                // Another request saved a visit for this appointment first.
                throw ValidationException::withMessages(['appointment_id' => __('This appointment already has a visit.')]);
            }

            $paidNow = PaymentService::money($request->validated('paid_now'));
            if (bccomp($paidNow, '0', 2) > 0) {
                $visit->payments()->create([
                    'amount' => $paidNow,
                    'method' => $request->validated('method') ?? PaymentMethod::Cash,
                    'paid_at' => now(),
                ]);
            }

            if ($appointment) {
                // §4.3: Completed follows Checked In; a patient seen straight
                // from Booked is checked in on the way.
                if ($appointment->status === AppointmentStatus::Booked) {
                    $appointment->transitionTo(AppointmentStatus::CheckedIn);
                }
                $appointment->transitionTo(AppointmentStatus::Completed);
            }

            return $visit;
        });

        return VisitResource::make($visit->load('payments'))
            ->withMessage(__('Visit saved.'))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * PUT /doctor/visits/{visit} — work done and total; remaining follows.
     */
    public function update(UpdateVisitRequest $request, Visit $visit, PaymentService $payments): VisitResource
    {
        $visit = DB::transaction(function () use ($request, $visit, $payments) {
            // Locked, so a payment arriving now cannot push paid above the new total.
            $locked = Visit::query()->lockForUpdate()->findOrFail($visit->getKey());
            $payments->assertTotalAllowed($locked, $request->validated('total_amount'));

            $locked->update([
                'work_done' => $request->validated('work_done'),
                'total_amount' => PaymentService::money($request->validated('total_amount')),
            ]);

            return $locked;
        });

        return VisitResource::make($visit->load('payments'))->withMessage(__('Visit updated.'));
    }
}
