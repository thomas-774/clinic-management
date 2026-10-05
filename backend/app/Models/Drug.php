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

    /**
     * Trade name or an active ingredient name contains $search, case-insensitive.
     * The ingredient names are read as one JSON array string, lower-cased, so
     * no extra column is needed (MySQL 8).
     */
    public function scopeMatching(Builder $query, string $search): void
    {
        $like = '%'.mb_strtolower(addcslashes($search, '\\%_')).'%';

        $query->where(fn ($q) => $q
            ->where('trade_name', 'like', $like)
            ->orWhereRaw("LOWER(CAST(JSON_EXTRACT(active_ingredients, '$[*].name') AS CHAR)) LIKE ?", [$like]));
    }

    /**
     * Typeahead order (FR-J.2): trade name starts with $search, then trade name
     * contains it, then only an ingredient matches; ties by trade name.
     */
    public function scopeRankedFor(Builder $query, string $search): void
    {
        $escaped = addcslashes($search, '\\%_');

        $query
            ->orderByRaw('CASE WHEN trade_name LIKE ? THEN 0 WHEN trade_name LIKE ? THEN 1 ELSE 2 END', [$escaped.'%', '%'.$escaped.'%'])
            ->orderBy('trade_name')
            ->orderBy('id');
    }

    /**
     * The first line of `uses`, shown under each search suggestion.
     */
    public function shortUse(): string
    {
        return trim(strtok((string) $this->uses, "\r\n") ?: '');
    }
}
