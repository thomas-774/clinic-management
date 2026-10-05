<?php

namespace App\Models;

use App\Enums\DrugCategory;
use Database\Factories\DrugFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A catalogue drug (FR-J.1). Hidden drugs (is_active = false) are never
 * suggested by the search but stay readable on old prescriptions (RX-3).
 */
#[Fillable([
    'trade_name', 'form', 'pack', 'category', 'active_ingredients', 'uses',
    'warnings', 'suggested_dose', 'seed_key', 'source_page', 'is_active',
])]
class Drug extends Model
{
    /** @use HasFactory<DrugFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => DrugCategory::class,
            'active_ingredients' => 'array',
            'source_page' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
