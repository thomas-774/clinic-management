<?php

namespace App\Enums;

enum AppointmentStatus: string
{
    case Booked = 'booked';
    case CheckedIn = 'checked_in';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';

    /**
     * Statuses that keep the slot taken (they fill appointments.active_slot).
     *
     * @return list<self>
     */
    public static function holdingSlot(): array
    {
        return [self::Booked, self::CheckedIn, self::Completed];
    }

    public function holdsSlot(): bool
    {
        return in_array($this, self::holdingSlot(), true);
    }

    /**
     * The lifecycle in §4.3: booked → checked_in / cancelled / no_show,
     * checked_in → completed / cancelled (the patient left without a visit).
     * Every other change is refused.
     */
    public function canTransitionTo(self $to): bool
    {
        return in_array($to, match ($this) {
            self::Booked => [self::CheckedIn, self::Cancelled, self::NoShow],
            self::CheckedIn => [self::Completed, self::Cancelled],
            self::Completed, self::Cancelled, self::NoShow => [],
        }, true);
    }
}
