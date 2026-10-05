<?php

namespace App\Models;

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
}
