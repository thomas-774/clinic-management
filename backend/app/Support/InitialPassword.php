<?php

namespace App\Support;

/**
 * The first password of an account made by the doctor or the assistant
 * (FR-C.6, FR-I.1, FR-I.2); it is shown once and given to the person.
 */
class InitialPassword
{
    /**
     * Letters and digits that cannot be confused when read out over the phone.
     */
    private const ALPHABET = 'abcdefghjkmnpqrstuvwxyz23456789';

    public static function generate(int $length = 8): string
    {
        return collect(range(1, $length))
            ->map(fn () => self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)])
            ->implode('');
    }
}
