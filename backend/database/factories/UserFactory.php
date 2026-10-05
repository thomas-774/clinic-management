<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state (a patient user).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => fake()->unique()->numerify('01#########'),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => UserRole::Patient,
            'remember_token' => Str::random(10),
        ];
    }

    public function patient(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Patient,
        ]);
    }

    public function doctor(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Doctor,
        ]);
    }

    public function withoutEmail(): static
    {
        return $this->state(fn (array $attributes) => [
            'email' => null,
        ]);
    }
}
