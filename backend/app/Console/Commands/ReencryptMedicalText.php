<?php

namespace App\Console\Commands;

use App\Support\MedicalEncryption;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * After an APP_KEY rotation (NFR-S.6): put the old key in APP_PREVIOUS_KEYS
 * and the new one in APP_KEY, run this, then remove the old key. Every
 * encrypted medical value is decrypted (with whichever key works) and saved
 * again with the new key. All or nothing.
 */
class ReencryptMedicalText extends Command
{
    protected $signature = 'clinic:reencrypt';

    protected $description = 'Re-encrypt the medical text with the current APP_KEY after a key rotation';

    public function handle(): int
    {
        $count = DB::transaction(fn () => MedicalEncryption::reencryptAll());

        $this->info("Re-encrypted {$count} values with the current APP_KEY. APP_PREVIOUS_KEYS can be removed now.");

        return self::SUCCESS;
    }
}
