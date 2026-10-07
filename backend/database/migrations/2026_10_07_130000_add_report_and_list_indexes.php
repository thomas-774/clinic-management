<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T11-08 (NFR-P.3), from the EXPLAIN plans recorded in the task file:
 * - visits (visit_date, patient_id, total_amount): reports filter on the
 *   visit date since revenue counts by visit date; the extra columns let the
 *   outstanding totals read the index instead of the rows.
 * - visits (patient_id, visit_date): a patient's visits newest first and the
 *   patients list's "last visit".
 * - payments (visit_id, amount): every balance sum reads the index only.
 * - users (name): the patients list is sorted by name.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->index(['visit_date', 'patient_id', 'total_amount']);
            $table->index(['patient_id', 'visit_date']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->index(['visit_id', 'amount']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['name']);
        });

        // MySQL dropped the foreign key's own visit_id index when the wider
        // one was added; the key needs it back before the wider one can go.
        Schema::table('payments', function (Blueprint $table) {
            $table->index('visit_id', 'payments_visit_id_foreign');
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['visit_id', 'amount']);
        });

        Schema::table('visits', function (Blueprint $table) {
            $table->dropIndex(['patient_id', 'visit_date']);
            $table->dropIndex(['visit_date', 'patient_id', 'total_amount']);
        });
    }
};
