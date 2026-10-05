<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A blocked date, or a blocked time range on a date.
 * start_time and end_time stay "HH:MM:SS" strings; both null = whole day.
 */
#[Fillable(['doctor_id', 'date', 'start_time', 'end_time', 'reason'])]
class BlockedTime extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
        ];
    }

    public function isWholeDay(): bool
    {
        return $this->start_time === null && $this->end_time === null;
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }
}
