<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\Payment;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'visit_id' => Visit::factory(),
            'amount' => fake()->randomElement([100, 200, 250, 500]),
            'method' => PaymentMethod::Cash,
            'paid_at' => fake()->dateTimeBetween('-6 months'),
        ];
    }
}
