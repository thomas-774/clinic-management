<?php

namespace App\Models;

use Database\Factories\PrescriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A prescription of 1–15 lines (RX-1); a linked visit belongs to the same patient (RX-5).
 */
#[Fillable(['patient_id', 'doctor_id', 'visit_id', 'issued_on', 'notes'])]
class Prescription extends Model
{
    /** @use HasFactory<PrescriptionFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issued_on' => 'date:Y-m-d',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    /**
     * The lines in print order.
     */
    public function items(): HasMany
    {
        return $this->hasMany(PrescriptionItem::class)->orderBy('position');
    }
}
