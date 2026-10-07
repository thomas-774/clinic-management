<?php

namespace App\Http\Controllers\Api\V1\Assistant;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Doctor\StorePaymentRequest;
use App\Http\Resources\ApiResourceCollection;
use App\Http\Resources\AssistantVisitResource;
use App\Models\Visit;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * The front desk collects the money of visits the doctor has priced (FR-I.4, FR-I.5).
 */
class VisitController extends Controller
{
    /**
     * GET /assistant/visits/unpaid?date=YYYY-MM-DD — that day's visits (today
     * by default) that still owe money, oldest first: "Waiting to pay".
     */
    public function unpaid(Request $request): ApiResourceCollection
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $visits = Visit::query()
            ->withPaid()
            ->unpaid()
            ->with(['patient.user:id,name,phone', 'payments.recordedBy:id,name'])
            ->where('visit_date', $validated['date'] ?? today()->toDateString())
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        return AssistantVisitResource::collection($visits);
    }

    /**
     * POST /assistant/visits/{visit}/payments — what the patient pays now,
     * under the same rules as the doctor's installments (PR-2).
     */
    public function storePayment(StorePaymentRequest $request, Visit $visit, PaymentService $payments): JsonResponse
    {
        $payments->addPayment(
            $visit,
            $request->validated('amount'),
            PaymentMethod::tryFrom((string) $request->validated('method')) ?? PaymentMethod::Cash,
            $request->filled('paid_at') ? Carbon::parse($request->validated('paid_at')) : null,
            $request->user(),
        );

        return AssistantVisitResource::make($visit->refresh()->load(['patient.user:id,name,phone', 'payments.recordedBy:id,name']))
            ->withMessage(__('Payment recorded.'))
            ->response()
            ->setStatusCode(201);
    }
}
