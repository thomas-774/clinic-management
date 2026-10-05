<?php

namespace App\Http\Resources;

use App\Models\Patient;
use Illuminate\Http\Request;

/**
 * The doctor's patient page (FR-C.2 – C.5): info, every history entry
 * (private ones included) and the visit timeline.
 *
 * @mixin Patient
 */
class PatientDetailResource extends PatientResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'history' => DetailedHistoryEntryResource::collection(
                $this->medicalHistoryEntries()->orderByDesc('recorded_on')->orderByDesc('id')->get(),
            ),
            'visits' => [], // T5-07
        ];
    }
}
