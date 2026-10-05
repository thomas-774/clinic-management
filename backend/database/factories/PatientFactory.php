<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Patient>
 */
class PatientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->patient(),
            'address' => fake()->streetAddress().', '.fake()->city(),
            'date_of_birth' => fake()->optional(0.8)->dateTimeBetween('-80 years', '-5 years')?->format('Y-m-d'),
            'gender' => fake()->optional(0.9)->randomElement(['male', 'female']),
            'current_illness' => fake()->optional(0.7)->sentence(),
        ];
    }
}
