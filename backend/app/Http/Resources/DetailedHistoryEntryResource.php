<?php

namespace App\Http\Resources;

use App\Models\MedicalHistoryEntry;
use Illuminate\Http\Request;

/**
 * Full history entry for the doctor (FR-C.3), including private entries.
 *
 * @mixin MedicalHistoryEntry
 */
class DetailedHistoryEntryResource extends ApiResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'patient_id' => $this->patient_id,
            'type' => $this->type,
            'title' => $this->title,
            'details' => $this->details,
            'patient_visible' => $this->patient_visible,
            'recorded_on' => $this->recorded_on->format('Y-m-d'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
