<?php

namespace Database\Factories;

use App\Enums\HistoryType;
use App\Models\MedicalHistoryEntry;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MedicalHistoryEntry>
 */
class MedicalHistoryEntryFactory extends Factory
{
    /**
     * Example titles per entry type.
     *
     * @var array<string, list<string>>
     */
    protected const TITLES = [
        'condition' => ['Diabetes type 2', 'Hypertension', 'Asthma', 'Hypothyroidism'],
        'allergy' => ['Penicillin', 'Latex', 'Ibuprofen', 'Pollen'],
        'surgery' => ['Appendectomy', 'Tonsillectomy', 'Knee arthroscopy'],
        'medication' => ['Metformin 500 mg', 'Amlodipine 5 mg', 'Levothyroxine 50 mcg'],
        'note' => ['Prefers evening appointments', 'Anxious about injections', 'Follow-up in 3 months'],
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(HistoryType::cases());

        return [
            'patient_id' => Patient::factory(),
            'type' => $type,
            'title' => fake()->randomElement(self::TITLES[$type->value]),
            'details' => fake()->optional(0.8)->paragraph(),
            'patient_visible' => fake()->boolean(60),
            'recorded_on' => fake()->dateTimeBetween('-5 years')->format('Y-m-d'),
        ];
    }

    public function visible(): static
    {
        return $this->state(fn (array $attributes) => ['patient_visible' => true]);
    }

    public function private(): static
    {
        return $this->state(fn (array $attributes) => ['patient_visible' => false]);
    }
}
