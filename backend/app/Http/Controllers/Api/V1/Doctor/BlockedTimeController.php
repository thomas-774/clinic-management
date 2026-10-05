<?php

namespace App\Http\Controllers\Api\V1\Doctor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Doctor\StoreBlockedTimeRequest;
use App\Http\Resources\ApiResourceCollection;
use App\Http\Resources\BlockedTimeResource;
use App\Models\BlockedTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Holidays and other exceptions to the working hours (FR-G.3).
 */
class BlockedTimeController extends Controller
{
    /**
     * GET /doctor/blocked-times?from=&to= — from defaults to today.
     */
    public function index(Request $request): ApiResourceCollection
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $blocks = $request->user()->blockedTimes()
            ->whereDate('date', '>=', $validated['from'] ?? today()->toDateString())
            ->when($validated['to'] ?? null, fn ($query, $to) => $query->whereDate('date', '<=', $to))
            ->orderBy('date')
            ->orderByRaw('start_time IS NOT NULL') // whole-day blocks first
            ->orderBy('start_time')
            ->get();

        return BlockedTimeResource::collection($blocks);
    }

    /**
     * POST /doctor/blocked-times
     */
    public function store(StoreBlockedTimeRequest $request): JsonResponse
    {
        $block = $request->user()->blockedTimes()->create($request->validated());

        return BlockedTimeResource::make($block->refresh())
            ->withMessage(__('Time blocked.'))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * DELETE /doctor/blocked-times/{blockedTime}
     */
    public function destroy(Request $request, BlockedTime $blockedTime): JsonResponse
    {
        abort_unless($blockedTime->doctor_id === $request->user()->id, 404);

        $blockedTime->delete();

        return $this->message(__('Block removed.'));
    }
}
