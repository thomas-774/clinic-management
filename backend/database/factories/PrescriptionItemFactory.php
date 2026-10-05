<?php

namespace Database\Factories;

use App\Models\Drug;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PrescriptionItem>
 */
class PrescriptionItemFactory extends Factory
{
    /**
     * Define the model's default state: a free-text line.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'prescription_id' => Prescription::factory(),
            'drug_id' => null,
            'drug_name' => ucfirst(fake()->lexify('??????')).' '.fake()->randomElement([250, 500]).' mg',
            'drug_form' => fake()->optional()->randomElement(['tablets', 'capsules', 'syrup']),
            'instructions' => fake()->randomElement([
                '1 tablet every 12 hours after meals for 5 days',
                '1 capsule every 8 hours for 3 days',
                'Rinse twice a day for one week',
            ]),
            'position' => 1,
        ];
    }

    /**
     * A catalogue line, with the drug's name and form copied onto it (RX-2).
     */
    public function forDrug(Drug $drug): static
    {
        return $this->state(fn (array $attributes) => [
            'drug_id' => $drug->id,
            'drug_name' => $drug->trade_name,
            'drug_form' => $drug->form,
        ]);
    }
}
