<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
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
    use Auditable;

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
            // Encrypted at rest with APP_KEY (NFR-S.6): never filter or sort on it in SQL.
            'notes' => 'encrypted',
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

    /**
     * The patient this record belongs to, for the audit log (NFR-S.4).
     */
    public function auditPatientId(): ?int
    {
        return $this->patient_id;
    }
}
