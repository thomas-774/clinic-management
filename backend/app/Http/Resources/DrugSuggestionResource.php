<?php

namespace App\Http\Resources;

use App\Models\Drug;
use Illuminate\Http\Request;

/**
 * One typeahead suggestion (FR-J.2); the side note loads the full DrugResource.
 *
 * @mixin Drug
 */
class DrugSuggestionResource extends ApiResource
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
            'trade_name' => $this->trade_name,
            'form' => $this->form,
            'pack' => $this->pack,
            'category' => $this->category,
            'short_use' => $this->shortUse(),
        ];
    }
}
