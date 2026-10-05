<?php

namespace Database\Factories;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    /**
     * Define the model's default state: a booked 45-minute slot in the coming days.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = Carbon::today()
            ->addDays(fake()->numberBetween(1, 30))
            ->setTime(17, 0)
            ->addMinutes(45 * fake()->numberBetween(0, 4));

        return [
            'doctor_id' => User::factory()->doctor(),
            'patient_id' => Patient::factory(),
            'start_at' => $start,
            'end_at' => $start->copy()->addMinutes(45),
            'status' => AppointmentStatus::Booked,
        ];
    }

    /**
     * Start at the given time; end_at follows from the duration.
     */
    public function at(Carbon|string $startAt, int $durationMinutes = 45): static
    {
        $start = Carbon::parse($startAt);

        return $this->state(fn (array $attributes) => [
            'start_at' => $start,
            'end_at' => $start->copy()->addMinutes($durationMinutes),
        ]);
    }

    public function checkedIn(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AppointmentStatus::CheckedIn,
            'checked_in_at' => $attributes['start_at'],
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AppointmentStatus::Completed,
            'checked_in_at' => $attributes['start_at'],
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AppointmentStatus::Cancelled,
            'cancelled_at' => now(),
        ]);
    }

    public function noShow(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AppointmentStatus::NoShow,
        ]);
    }
}
