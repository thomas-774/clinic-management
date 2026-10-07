<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\PrescriptionItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A prescription line. drug_name / drug_form are a snapshot (RX-2); drug_id is
 * null for a free-text line, or when the catalogue drug was removed.
 */
#[Fillable(['prescription_id', 'drug_id', 'drug_name', 'drug_form', 'instructions', 'position'])]
class PrescriptionItem extends Model
{
    use Auditable;

    /** @use HasFactory<PrescriptionItemFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            // Encrypted at rest with APP_KEY (NFR-S.6): never filter or sort on it in SQL.
            'instructions' => 'encrypted',
        ];
    }

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }

    public function drug(): BelongsTo
    {
        return $this->belongsTo(Drug::class);
    }

    /**
     * The patient this record belongs to, for the audit log (NFR-S.4).
     * Through the prescription, without lazy-loading it.
     */
    public function auditPatientId(): ?int
    {
        return $this->relationLoaded('prescription') ? $this->prescription?->patient_id : Prescription::whereKey($this->prescription_id)->value('patient_id');
    }
}
