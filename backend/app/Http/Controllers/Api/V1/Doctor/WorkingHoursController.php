<?php

namespace App\Http\Controllers\Api\V1\Doctor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Doctor\UpdateWorkingHoursRequest;
use App\Models\User;
use App\Models\WorkingHour;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Weekly working hours: each weekday has zero or more time ranges (FR-G.1).
 */
class WorkingHoursController extends Controller
{
    /**
     * GET /doctor/working-hours — always 7 days (0 = Sunday … 6 = Saturday).
     */
    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->week($request->user()), 'message' => null]);
    }

    /**
     * PUT /doctor/working-hours — replaces the whole week.
     */
    public function update(UpdateWorkingHoursRequest $request): JsonResponse
    {
        $doctor = $request->user();

        DB::transaction(function () use ($doctor, $request) {
            $doctor->workingHours()->delete();
            $doctor->workingHours()->createMany($request->rows());
        });

        return response()->json(['data' => $this->week($doctor), 'message' => __('Working hours saved.')]);
    }

    /**
     * @return list<array{day_of_week: int, ranges: list<array{start_time: string, end_time: string}>}>
     */
    private function week(User $doctor): array
    {
        $byDay = $doctor->workingHours()->orderBy('start_time')->get()->groupBy('day_of_week');

        return collect(range(0, 6))->map(fn (int $day) => [
            'day_of_week' => $day,
            'ranges' => ($byDay[$day] ?? collect())->map(fn (WorkingHour $hour) => [
                'start_time' => substr($hour->start_time, 0, 5),
                'end_time' => substr($hour->end_time, 0, 5),
            ])->values()->all(),
        ])->all();
    }
}
