<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class AssistantSeeder extends Seeder
{
    public const PHONE = '01100000000';

    /**
     * One demo front-desk assistant (Module I): phone 01100000000, password
     * "password". Running it again updates the account instead of duplicating it.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['phone' => self::PHONE],
            [
                'name' => 'Demo Assistant',
                'email' => null,
                'password' => 'password',
                'role' => UserRole::Assistant,
                'is_active' => true,
            ],
        );
    }
}
