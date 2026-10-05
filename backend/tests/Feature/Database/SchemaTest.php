<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

function insertUser(array $overrides = []): int
{
    return DB::table('users')->insertGetId(array_merge([
        'name' => 'Test User',
        'phone' => '01000000001',
        'password' => bcrypt('password'),
        'role' => 'patient',
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides));
}

describe('users and patients (T1-01)', function () {
    it('rejects a duplicate phone number', function () {
        insertUser(['phone' => '01011111111']);

        expect(fn () => insertUser(['phone' => '01011111111']))->toThrow(QueryException::class);
    });

    it('allows several users without an email', function () {
        insertUser(['phone' => '01011111111', 'email' => null]);
        insertUser(['phone' => '01022222222', 'email' => null]);

        expect(DB::table('users')->count())->toBe(2);
    });

    it('allows only one patient row per user and deletes it with the user', function () {
        $userId = insertUser();
        DB::table('patients')->insert(['user_id' => $userId, 'address' => 'Cairo']);

        expect(fn () => DB::table('patients')->insert(['user_id' => $userId, 'address' => 'Giza']))
            ->toThrow(QueryException::class);

        DB::table('users')->where('id', $userId)->delete();
        expect(DB::table('patients')->count())->toBe(0);
    });
});
