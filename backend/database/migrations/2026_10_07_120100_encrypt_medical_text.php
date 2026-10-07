<?php

use App\Support\MedicalEncryption;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Encrypts the medical text already stored (NFR-S.6, T11-06), in chunks
     * of 500 rows; values that already decrypt are skipped. From here on the
     * models' `encrypted` cast keeps it encrypted.
     */
    public function up(): void
    {
        DB::transaction(fn () => MedicalEncryption::encryptAll());
    }

    /**
     * Reverse the migrations: every value back to plain text, so the columns
     * can shrink again (previous migration's down()).
     */
    public function down(): void
    {
        DB::transaction(fn () => MedicalEncryption::decryptAll());
    }
};
