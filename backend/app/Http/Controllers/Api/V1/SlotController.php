<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\SlotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class SlotController extends Controller
{
    /**
     * GET /slots?date=YYYY-MM-DD — free slots for a date (FR-E.2).
     * Booked, blocked and past slots are not listed at all.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $service = SlotService::forClinic();
        $slots = $service->generate(Carbon::parse($validated['date']));

        return response()->json([
            'data' => array_map(fn (array $slot) => [
                'start_at' => $slot[0],
                'end_at' => $slot[1],
            ], $slots),
            'meta' => [
                'date' => $validated['date'],
                'duration' => $service->duration(),
                // Patients cannot read the doctor settings; the date picker needs its limit (FR-E.1).
                'booking_window_days' => $service->settings()->booking_window_days,
            ],
            'message' => null,
        ]);
    }
}
