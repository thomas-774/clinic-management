<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One working time range on a weekday (0 = Sunday … 6 = Saturday).
 * start_time and end_time stay "HH:MM:SS" strings.
 */
#[Fillable(['doctor_id', 'day_of_week', 'start_time', 'end_time'])]
class WorkingHour extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
        ];
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }
}
