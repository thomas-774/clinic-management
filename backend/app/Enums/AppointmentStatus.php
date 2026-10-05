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
}
