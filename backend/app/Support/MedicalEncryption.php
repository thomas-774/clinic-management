<?php

namespace App\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * The medical text kept encrypted at rest with the `encrypted` cast (NFR-S.6,
 * T11-06), and bulk work on it straight in the tables — no models, so no
 * audit rows, no casts and no `updated_at` changes. Used by the data
 * migration and by `clinic:reencrypt` after a key rotation.
 */
class MedicalEncryption
{
    /** table => encrypted columns */
    public const COLUMNS = [
        'patients' => ['current_illness'],
        'medical_history_entries' => ['title', 'details'],
        'visits' => ['work_done'],
        'prescriptions' => ['notes'],
        'prescription_items' => ['instructions'],
    ];

    private const CHUNK = 500;

    /**
     * Encrypts every plain value; values that already decrypt are left as
     * they are, so it can run twice. Returns the number of values encrypted.
     */
    public static function encryptAll(): int
    {
        return self::rewrite(fn (string $value) => self::decrypts($value) ? null : Crypt::encryptString($value));
    }

    /**
     * Turns every encrypted value back into plain text (the data migration's
     * down()). Returns the number of values decrypted.
     */
    public static function decryptAll(): int
    {
        return self::rewrite(fn (string $value) => self::decrypts($value) ? Crypt::decryptString($value) : null);
    }

    /**
     * Decrypts with the current or a previous key (APP_PREVIOUS_KEYS) and
     * encrypts again with the current key only. Returns the number of values.
     */
    public static function reencryptAll(): int
    {
        return self::rewrite(fn (string $value) => Crypt::encryptString(Crypt::decryptString($value)));
    }

    /**
     * @param  callable(string): ?string  $change  the new value, or null to leave it
     */
    private static function rewrite(callable $change): int
    {
        $changed = 0;

        foreach (self::COLUMNS as $table => $columns) {
            DB::table($table)->select(['id', ...$columns])->orderBy('id')->chunkById(self::CHUNK, function ($rows) use ($table, $columns, $change, &$changed) {
                foreach ($rows as $row) {
                    $values = [];
                    foreach ($columns as $column) {
                        if ($row->{$column} !== null && ($new = $change($row->{$column})) !== null) {
                            $values[$column] = $new;
                        }
                    }
                    if ($values !== []) {
                        DB::table($table)->where('id', $row->id)->update($values);
                        $changed += count($values);
                    }
                }
            });
        }

        return $changed;
    }

    private static function decrypts(string $value): bool
    {
        try {
            Crypt::decryptString($value);

            return true;
        } catch (DecryptException) {
            return false;
        }
    }
}
