<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'doctor_id', 'slot_duration_minutes', 'booking_window_days', 'cancel_cutoff_hours',
    'clinic_name', 'doctor_title', 'clinic_address', 'clinic_phone',
    'prescription_footer', 'prescription_paper',
])]
class DoctorSetting extends Model
{
    public const PAPER_SIZES = ['A5', 'A4'];

    /**
     * The prescription print header fields (FR-J.5, FR-J.6).
     */
    public const PRINT_HEADER_FIELDS = ['clinic_name', 'doctor_title', 'clinic_address', 'clinic_phone', 'prescription_footer'];

    /**
     * Same defaults as the migration, so a new model has them before saving.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'slot_duration_minutes' => 45,
        'booking_window_days' => 30,
        'cancel_cutoff_hours' => 2,
        'prescription_paper' => 'A5',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'slot_duration_minutes' => 'integer',
            'booking_window_days' => 'integer',
            'cancel_cutoff_hours' => 'integer',
        ];
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }
}
