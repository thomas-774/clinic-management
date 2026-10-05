<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Prescription>
 */
class PrescriptionFactory extends Factory
{
    /**
     * Define the model's default state: no visit and no lines (add them with withItems()).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'doctor_id' => User::factory()->doctor(),
            'visit_id' => null,
            'issued_on' => fake()->dateTimeBetween('-6 months')->format('Y-m-d'),
            'notes' => fake()->optional()->sentence(),
        ];
    }

    /**
     * Linked to a visit of the same patient (RX-5).
     */
    public function forVisit(Visit $visit): static
    {
        return $this->state(fn (array $attributes) => [
            'patient_id' => $visit->patient_id,
            'visit_id' => $visit->id,
            'issued_on' => $visit->visit_date->format('Y-m-d'),
        ]);
    }

    /**
     * Adds $count free-text lines numbered 1..$count.
     */
    public function withItems(int $count = 2): static
    {
        return $this->afterCreating(function (Prescription $prescription) use ($count) {
            foreach (range(1, $count) as $position) {
                PrescriptionItem::factory()->for($prescription)->create(['position' => $position]);
            }
        });
    }
}
