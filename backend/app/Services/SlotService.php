<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Models\BlockedTime;
use App\Models\DoctorSetting;
use App\Models\User;
use App\Models\WorkingHour;
use App\Support\ClinicContext;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Free appointment slots for a date (§4.1). Slots are never stored: they are
 * computed from the working hours and the duration, minus what is booked or
 * blocked.
 */
class SlotService
{
    public function __construct(private readonly User $doctor) {}

    /**
     * The service for the clinic's (single) doctor.
     */
    public static function forClinic(): self
    {
        return new self(app(ClinicContext::class)->doctor());
    }

    public function doctor(): User
    {
        return $this->doctor;
    }

    public function settings(): DoctorSetting
    {
        return $this->doctor->doctorSetting ?? new DoctorSetting;
    }

    public function duration(): int
    {
        return $this->settings()->slot_duration_minutes;
    }

    /**
     * Free slots on $date as [start, end] pairs, in time order.
     *
     * @return list<array{0: Carbon, 1: Carbon}>
     */
    public function generate(CarbonInterface $date): array
    {
        $day = Carbon::parse($date->format('Y-m-d'), config('app.timezone'))->startOfDay();
        $now = Carbon::now(config('app.timezone'));
        $settings = $this->settings();

        // Only today up to the booking window.
        if ($day->lt($now->copy()->startOfDay()) || $day->gt($now->copy()->startOfDay()->addDays($settings->booking_window_days))) {
            return [];
        }

        // 1. Working ranges for that weekday; none = day off.
        $ranges = $this->doctor->workingHours()
            ->where('day_of_week', $day->dayOfWeek)
            ->orderBy('start_time')
            ->get();

        $blocks = $this->doctor->blockedTimes()->where('date', $day->toDateString())->get();

        if ($ranges->isEmpty() || $blocks->contains(fn (BlockedTime $block) => $block->isWholeDay())) {
            return [];
        }

        // 2–3. Each range separately, so a slot never crosses a break.
        $duration = $settings->slot_duration_minutes;
        $slots = $ranges->flatMap(fn (WorkingHour $range) => $this->slotsInRange($day, $range, $duration));

        // 4. Taken by an appointment that still holds its time (any length: FR-G.4).
        $taken = $this->appointmentsOn($day);
        // 5. Blocked time ranges.
        $blocked = $blocks->map(fn (BlockedTime $block) => [
            $day->copy()->setTimeFromTimeString($block->start_time),
            $day->copy()->setTimeFromTimeString($block->end_time),
        ]);

        return $slots
            ->reject(fn (array $slot) => $this->overlapsAny($slot, $taken) || $this->overlapsAny($slot, $blocked))
            // 6. Today: only slots that have not started yet.
            ->filter(fn (array $slot) => $slot[0]->gt($now))
            ->values()
            ->all();
    }

    /**
     * Is $start exactly the start of a free slot right now? (BR-1)
     */
    public function isAvailable(CarbonInterface $start): bool
    {
        $start = Carbon::parse($start->format('Y-m-d H:i:s'), config('app.timezone'));

        return collect($this->generate($start))
            ->contains(fn (array $slot) => $slot[0]->equalTo($start));
    }

    /**
     * @return Collection<int, array{0: Carbon, 1: Carbon}>
     */
    private function slotsInRange(Carbon $day, WorkingHour $range, int $duration): Collection
    {
        $slots = collect();
        $cursor = $day->copy()->setTimeFromTimeString($range->start_time);
        $end = $day->copy()->setTimeFromTimeString($range->end_time);

        while ($cursor->copy()->addMinutes($duration)->lte($end)) {
            $slots->push([$cursor->copy(), $cursor->copy()->addMinutes($duration)]);
            $cursor->addMinutes($duration);
        }

        return $slots;
    }

    /**
     * @return Collection<int, array{0: Carbon, 1: Carbon}>
     */
    private function appointmentsOn(Carbon $day): Collection
    {
        return $this->doctor->doctorAppointments()
            ->whereIn('status', AppointmentStatus::holdingSlot())
            ->where('start_at', '<', $day->copy()->addDay())
            ->where('end_at', '>', $day)
            // A slot is at most 240 minutes, so anything overlapping the day
            // starts after the day before: lets MySQL use (doctor_id, start_at)
            // instead of reading every appointment (T11-08).
            ->where('start_at', '>', $day->copy()->subDay())
            ->get(['start_at', 'end_at'])
            ->map(fn ($appointment) => [$appointment->start_at, $appointment->end_at]);
    }

    /**
     * Two time ranges overlap when a.start < b.end and b.start < a.end.
     *
     * @param  array{0: Carbon, 1: Carbon}  $slot
     * @param  Collection<int, array{0: CarbonInterface, 1: CarbonInterface}>  $ranges
     */
    private function overlapsAny(array $slot, Collection $ranges): bool
    {
        return $ranges->contains(fn (array $range) => $slot[0]->lt($range[1]) && $range[0]->lt($slot[1]));
    }
}
