<?php

namespace App\Http\Resources;

use App\Models\MedicalHistoryEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * The patient's "simple history" (FR-B.3): date, title and a short description.
 * Never exposes the type, the visibility flag, or anything of a private entry.
 *
 * @mixin MedicalHistoryEntry
 */
class SimpleHistoryEntryResource extends ApiResource
{
    public const DESCRIPTION_LENGTH = 200;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $visible = $this->patient_visible;

        return [
            'id' => $this->id,
            'recorded_on' => $this->recorded_on->format('Y-m-d'),
            'title' => $visible ? $this->title : null,
            'description' => $visible && $this->details !== null
                ? Str::limit($this->details, self::DESCRIPTION_LENGTH)
                : null,
        ];
    }
}
