<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class DoctorSeeder extends Seeder
{
    /**
     * Sat–Thu, 17:00–21:00. Friday (5) has no rows, so it is a day off.
     */
    public const WORKING_DAYS = [6, 0, 1, 2, 3, 4];

    /**
     * Create the doctor account with default settings and working hours.
     * Running it again updates the account instead of duplicating it.
     */
    public function run(): void
    {
        $config = config('clinic.doctor');

        if (blank($config['phone']) || blank($config['password'])) {
            throw new RuntimeException('Set DOCTOR_PHONE and DOCTOR_PASSWORD in .env before seeding.');
        }

        $doctor = User::updateOrCreate(
            ['phone' => $config['phone']],
            [
                'name' => $config['name'],
                'email' => $config['email'] ?: null,
                'password' => $config['password'],
                'role' => UserRole::Doctor,
            ],
        );

        $doctor->doctorSetting()->firstOrCreate([], [
            'slot_duration_minutes' => 45,
            'booking_window_days' => 30,
            'cancel_cutoff_hours' => 2,
        ]);

        if ($doctor->workingHours()->doesntExist()) {
            foreach (self::WORKING_DAYS as $day) {
                $doctor->workingHours()->create([
                    'day_of_week' => $day,
                    'start_time' => '17:00:00',
                    'end_time' => '21:00:00',
                ]);
            }
        }
    }
}
