<?php

namespace Database\Factories;

use App\Enums\DrugCategory;
use App\Models\Drug;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Drug>
 */
class DrugFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'trade_name' => ucfirst(fake()->unique()->lexify('??????')).' '.fake()->randomElement([250, 500, 1000]).' mg',
            'form' => fake()->randomElement(['tablets', 'capsules', 'syrup', 'gel', 'mouthwash']),
            'pack' => fake()->optional()->randomElement(['14 tabs', '20 caps', '100 ml']),
            'category' => fake()->randomElement(DrugCategory::cases()),
            'active_ingredients' => [
                ['name' => fake()->word(), 'note' => fake()->optional()->sentence(3)],
            ],
            'uses' => fake()->sentence(),
            'warnings' => fake()->optional()->sentence(),
            'suggested_dose' => fake()->optional()->sentence(),
            'seed_key' => null,
            'source_page' => null,
            'is_active' => true,
        ];
    }

    public function hidden(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }

    public function category(DrugCategory $category): static
    {
        return $this->state(fn (array $attributes) => ['category' => $category]);
    }
}
