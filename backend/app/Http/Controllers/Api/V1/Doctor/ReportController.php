<?php

namespace App\Http\Controllers\Api\V1\Doctor;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApiResourceCollection;
use App\Http\Resources\ReportPaymentResource;
use App\Services\ReportService;
use App\Support\Period;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Dashboard and reports (Module H). Revenue is money received on the payment
 * date (PR-4); outstanding balances are always reported separately (FR-H.2).
 */
class ReportController extends Controller
{
    public const PER_PAGE = 20;

    public function __construct(private readonly ReportService $reports) {}

    /**
     * GET /doctor/reports/summary?period=day|week|month (FR-H.1, FR-H.2).
     */
    public function summary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period' => ['required', Rule::in(Period::NAMES)],
        ]);

        [$from, $to] = Period::for($validated['period']);

        return response()->json([
            'data' => [
                'period' => $validated['period'],
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'patients_seen' => $this->reports->patientsSeen($from, $to),
                'revenue' => $this->reports->revenue($from, $to),
                'outstanding' => $this->reports->outstanding(),
            ],
            'message' => null,
        ]);
    }

    /**
     * GET /doctor/reports/payments?from=&to= — payments in the range, newest
     * first, 20 per page, with the totals of the whole range in meta (FR-H.3).
     * Both dates are inclusive; the default range is today.
     */
    public function payments(Request $request): ApiResourceCollection
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $from = Carbon::parse($validated['from'] ?? today()->toDateString())->startOfDay();
        $to = Carbon::parse($validated['to'] ?? $from->toDateString())->endOfDay();

        $page = $this->reports->payments($from, $to)
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        // "range", not from/to: the paginator already uses meta.from/meta.to for row numbers.
        return ReportPaymentResource::collection($page)->additional(['meta' => [
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'totals' => $this->reports->paymentTotals($from, $to),
        ]]);
    }

    /**
     * GET /doctor/reports/daily-revenue?month=YYYY-MM — one row per day,
     * 0.00 on days without payments (FR-H.4). The default is this month.
     */
    public function dailyRevenue(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
        ]);

        $month = isset($validated['month'])
            ? Carbon::createFromFormat('!Y-m', $validated['month'])
            : today();

        return response()->json([
            'data' => $this->reports->dailyRevenue($month),
            'meta' => ['month' => $month->format('Y-m')],
            'message' => null,
        ]);
    }
}
