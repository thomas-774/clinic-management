<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Visit>
 */
class VisitFactory extends Factory
{
    /**
     * Define the model's default state: a walk-in visit with no appointment.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'appointment_id' => null,
            'visit_date' => fake()->dateTimeBetween('-6 months')->format('Y-m-d'),
            'work_done' => fake()->sentence(),
            'total_amount' => fake()->randomElement([300, 500, 750, 1000, 1500, 2000]),
        ];
    }
}
