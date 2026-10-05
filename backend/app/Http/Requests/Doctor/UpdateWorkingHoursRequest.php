<?php

namespace App\Http\Requests\Doctor;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * The whole week at once (FR-G.1):
 *
 *     { "days": [ { "day_of_week": 2, "ranges": [ { "start_time": "10:00", "end_time": "13:00" }, … ] }, … ] }
 *
 * A day that is missing or has no ranges is a day off.
 */
class UpdateWorkingHoursRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'days' => ['present', 'array', 'max:7'],
            'days.*.day_of_week' => ['required', 'integer', 'between:0,6', 'distinct'],
            'days.*.ranges' => ['present', 'array', 'max:10'],
            'days.*.ranges.*.start_time' => ['required', 'date_format:H:i'],
            'days.*.ranges.*.end_time' => ['required', 'date_format:H:i', 'after:days.*.ranges.*.start_time'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'days.*.day_of_week' => __('day'),
            'days.*.ranges.*.start_time' => __('start time'),
            'days.*.ranges.*.end_time' => __('end time'),
        ];
    }

    /**
     * Ranges on the same day must not overlap (touching, e.g. 13:00 / 13:00, is fine).
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            foreach ($this->input('days', []) as $dayIndex => $day) {
                $ranges = collect($day['ranges'] ?? [])->map(fn ($range, $i) => [...$range, 'index' => $i])
                    ->sortBy('start_time')->values();

                for ($i = 1; $i < $ranges->count(); $i++) {
                    if ($ranges[$i]['start_time'] < $ranges[$i - 1]['end_time']) {
                        $validator->errors()->add(
                            "days.{$dayIndex}.ranges.{$ranges[$i]['index']}.start_time",
                            __('Time ranges on the same day must not overlap.'),
                        );
                    }
                }
            }
        }];
    }

    /**
     * @return list<array{day_of_week: int, start_time: string, end_time: string}>
     */
    public function rows(): array
    {
        return collect($this->validated('days'))
            ->flatMap(fn ($day) => collect($day['ranges'])->map(fn ($range) => [
                'day_of_week' => (int) $day['day_of_week'],
                'start_time' => $range['start_time'],
                'end_time' => $range['end_time'],
            ]))
            ->all();
    }
}
