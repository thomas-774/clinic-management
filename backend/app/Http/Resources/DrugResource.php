<?php

namespace App\Http\Resources;

use App\Models\Drug;
use Illuminate\Http\Request;

/**
 * A full catalogue drug: the side note (FR-J.3) and Settings → Drugs (FR-J.6).
 * The catalogue has no price (FR-J.1).
 *
 * @mixin Drug
 */
class DrugResource extends ApiResource
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
            'active_ingredients' => $this->active_ingredients,
            'uses' => $this->uses,
            'warnings' => $this->warnings,
            'suggested_dose' => $this->suggested_dose,
            'source_page' => $this->source_page,
            'is_active' => $this->is_active,
        ];
    }
}
