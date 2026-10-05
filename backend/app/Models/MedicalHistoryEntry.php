<?php

namespace App\Models;

use App\Enums\HistoryType;
use Database\Factories\MedicalHistoryEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['patient_id', 'type', 'title', 'details', 'patient_visible', 'recorded_on'])]
class MedicalHistoryEntry extends Model
{
    /** @use HasFactory<MedicalHistoryEntryFactory> */
    use HasFactory;

    /**
     * New entries are private until the doctor shares them (§2).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'patient_visible' => false,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => HistoryType::class,
            'patient_visible' => 'boolean',
            'recorded_on' => 'date:Y-m-d',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * Only the entries shown in the simple history on the patient page.
     */
    public function scopePatientVisible(Builder $query): void
    {
        $query->where('patient_visible', true);
    }
}
