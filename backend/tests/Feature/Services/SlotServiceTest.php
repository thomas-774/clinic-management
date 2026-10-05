<?php

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use App\Services\SlotService;
use Illuminate\Support\Carbon;

/*
 * §4.1 worked examples. "Now" is Monday 2026-10-05 08:00 (Cairo); most cases
 * use Tuesday 2026-10-06 (day_of_week 2). Friday 2026-10-09 is the day off.
 */

const SLOT_TUESDAY = '2026-10-06';

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-05 08:00:00'));
    $this->doctor = User::factory()->doctor()->create();
});

/**
 * @param  array<int, list<array{0: string, 1: string}>>  $week  day_of_week => [[start, end], …]
 */
function slotClinic(User $doctor, int $duration, array $week = [2 => [['17:00', '21:00']]], int $window = 30): SlotService
{
    $doctor->doctorSetting()->create(['slot_duration_minutes' => $duration, 'booking_window_days' => $window]);
    foreach ($week as $day => $ranges) {
        foreach ($ranges as [$start, $end]) {
            $doctor->workingHours()->create(['day_of_week' => $day, 'start_time' => $start, 'end_time' => $end]);
        }
    }

    return new SlotService($doctor);
}

/** @return list<string> slot start times as "H:i" */
function startTimes(SlotService $service, string $date = SLOT_TUESDAY): array
{
    return collect($service->generate(Carbon::parse($date)))->map(fn (array $slot) => $slot[0]->format('H:i'))->all();
}

function bookSlot(User $doctor, string $start, int $minutes, string $status = 'booked'): Appointment
{
    return Appointment::factory()->for($doctor, 'doctor')->for(Patient::factory())->at($start, $minutes)->create(['status' => $status]);
}

describe('slot lengths (17:00–21:00)', function () {
    it('makes five 45-minute slots and drops 20:45', function () {
        expect(startTimes(slotClinic($this->doctor, 45)))->toBe(['17:00', '17:45', '18:30', '19:15', '20:00']);
    });

    it('makes four 60-minute slots, the last ending exactly at 21:00', function () {
        $slots = slotClinic($this->doctor, 60)->generate(Carbon::parse(SLOT_TUESDAY));

        expect(collect($slots)->map(fn ($s) => $s[0]->format('H:i'))->all())->toBe(['17:00', '18:00', '19:00', '20:00'])
            ->and(end($slots)[1]->format('H:i'))->toBe('21:00');
    });

    it('makes eight 30-minute slots from 17:00 to 20:30', function () {
        $times = startTimes(slotClinic($this->doctor, 30));

        expect($times)->toHaveCount(8)
            ->and($times[0])->toBe('17:00')
            ->and(end($times))->toBe('20:30');
    });

    it('returns start and end for every slot', function () {
        [$start, $end] = slotClinic($this->doctor, 45)->generate(Carbon::parse(SLOT_TUESDAY))[1];

        expect($start->format('Y-m-d H:i'))->toBe('2026-10-06 17:45')
            ->and($end->format('Y-m-d H:i'))->toBe('2026-10-06 18:30');
    });
});

describe('days with a break', function () {
    it('restarts the cursor for each range at 60 minutes', function () {
        $service = slotClinic($this->doctor, 60, [2 => [['10:00', '13:00'], ['17:00', '21:00']]]);

        expect(startTimes($service))->toBe(['10:00', '11:00', '12:00', '17:00', '18:00', '19:00', '20:00']);
    });

    it('never lets a slot cross the break at 45 minutes', function () {
        $service = slotClinic($this->doctor, 45, [2 => [['10:00', '12:30'], ['17:00', '21:00']]]);

        expect(startTimes($service))->toBe(['10:00', '10:45', '11:30', '17:00', '17:45', '18:30', '19:15', '20:00']);
    });

    it('does not depend on the order the ranges were saved in', function () {
        $service = slotClinic($this->doctor, 60, [2 => [['17:00', '21:00'], ['10:00', '13:00']]]);

        expect(startTimes($service))->toBe(['10:00', '11:00', '12:00', '17:00', '18:00', '19:00', '20:00']);
    });
});

describe('days off and blocks', function () {
    it('returns nothing on a day without working hours (Friday)', function () {
        expect(startTimes(slotClinic($this->doctor, 45), '2026-10-09'))->toBe([]);
    });

    it('returns nothing on a whole-day block', function () {
        $service = slotClinic($this->doctor, 45);
        $this->doctor->blockedTimes()->create(['date' => SLOT_TUESDAY, 'reason' => 'Holiday']);

        expect(startTimes($service))->toBe([]);
    });

    it('drops slots inside a blocked time range', function () {
        $service = slotClinic($this->doctor, 60);
        $this->doctor->blockedTimes()->create(['date' => SLOT_TUESDAY, 'start_time' => '18:00', 'end_time' => '19:00']);

        expect(startTimes($service))->toBe(['17:00', '19:00', '20:00']);
    });

    it('ignores blocks on other dates', function () {
        $service = slotClinic($this->doctor, 60);
        $this->doctor->blockedTimes()->create(['date' => '2026-10-07']);

        expect(startTimes($service))->toHaveCount(4);
    });
});

describe('existing appointments', function () {
    it('removes a booked 17:45 slot but not a cancelled one', function () {
        $service = slotClinic($this->doctor, 45);
        bookSlot($this->doctor, SLOT_TUESDAY.' 17:45', 45);
        bookSlot($this->doctor, SLOT_TUESDAY.' 18:30', 45, 'cancelled');

        expect(startTimes($service))->toBe(['17:00', '18:30', '19:15', '20:00']);
    });

    it('treats checked-in and completed as taken, no-show as free', function () {
        $service = slotClinic($this->doctor, 60);
        bookSlot($this->doctor, SLOT_TUESDAY.' 17:00', 60, 'checked_in');
        bookSlot($this->doctor, SLOT_TUESDAY.' 18:00', 60, 'completed');
        bookSlot($this->doctor, SLOT_TUESDAY.' 19:00', 60, 'no_show');

        expect(startTimes($service))->toBe(['19:00', '20:00']);
    });

    it('respects an old 60-minute booking after the duration changed to 45', function () {
        $service = slotClinic($this->doctor, 45);
        bookSlot($this->doctor, SLOT_TUESDAY.' 18:00', 60); // 18:00–19:00

        // 17:45–18:30 and 18:30–19:15 overlap it.
        expect(startTimes($service))->toBe(['17:00', '19:15', '20:00']);
    });

    it('ignores another doctor\'s appointments', function () {
        $service = slotClinic($this->doctor, 60);
        bookSlot(User::factory()->doctor()->create(), SLOT_TUESDAY.' 17:00', 60);

        expect(startTimes($service))->toHaveCount(4);
    });
});

describe('dates and time', function () {
    it('shows only slots after now on today', function () {
        $service = slotClinic($this->doctor, 45);
        $this->travelTo(Carbon::parse(SLOT_TUESDAY.' 18:10:00'));

        expect(startTimes($service))->toBe(['18:30', '19:15', '20:00']);
    });

    it('drops a slot that starts exactly now', function () {
        $service = slotClinic($this->doctor, 45);
        $this->travelTo(Carbon::parse(SLOT_TUESDAY.' 18:30:00'));

        expect(startTimes($service))->toBe(['19:15', '20:00']);
    });

    it('returns nothing for a past date', function () {
        expect(startTimes(slotClinic($this->doctor, 45, [0 => [['17:00', '21:00']]]), '2026-10-04'))->toBe([]);
    });

    it('allows the last day of the booking window and nothing after it', function () {
        $everyDay = array_fill(0, 7, [['17:00', '21:00']]);
        $service = slotClinic($this->doctor, 60, $everyDay, window: 30);

        expect(startTimes($service, '2026-11-04'))->toHaveCount(4)   // today + 30
            ->and(startTimes($service, '2026-11-05'))->toBe([]);    // today + 31
    });
});

describe('isAvailable', function () {
    it('accepts only the exact start of a free slot', function () {
        $service = slotClinic($this->doctor, 45);
        bookSlot($this->doctor, SLOT_TUESDAY.' 17:45', 45);

        expect($service->isAvailable(Carbon::parse(SLOT_TUESDAY.' 17:00')))->toBeTrue()
            ->and($service->isAvailable(Carbon::parse(SLOT_TUESDAY.' 17:45')))->toBeFalse() // taken
            ->and($service->isAvailable(Carbon::parse(SLOT_TUESDAY.' 17:10')))->toBeFalse() // not a slot start
            ->and($service->isAvailable(Carbon::parse(SLOT_TUESDAY.' 20:45')))->toBeFalse() // would end after closing
            ->and($service->isAvailable(Carbon::parse('2026-10-09 17:00')))->toBeFalse(); // Friday
    });
});

it('uses the default 45 minutes when the doctor has no settings row', function () {
    $this->doctor->workingHours()->create(['day_of_week' => 2, 'start_time' => '17:00', 'end_time' => '21:00']);

    expect(startTimes(new SlotService($this->doctor)))->toBe(['17:00', '17:45', '18:30', '19:15', '20:00']);
});
