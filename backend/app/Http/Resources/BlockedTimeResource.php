<?php

namespace App\Http\Resources;

use App\Models\BlockedTime;
use Illuminate\Http\Request;

/**
 * @mixin BlockedTime
 */
class BlockedTimeResource extends ApiResource
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
            'date' => $this->date->format('Y-m-d'),
            'whole_day' => $this->isWholeDay(),
            'start_time' => $this->start_time ? substr($this->start_time, 0, 5) : null,
            'end_time' => $this->end_time ? substr($this->end_time, 0, 5) : null,
            'reason' => $this->reason,
        ];
    }
}
