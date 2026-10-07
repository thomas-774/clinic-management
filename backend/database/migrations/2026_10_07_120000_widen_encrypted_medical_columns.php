<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Ciphertext is several times longer than the text it holds (NFR-S.6,
     * T11-06), so the two short columns become `text`. The length limits stay
     * in the Form Requests (title 150, instructions 255).
     */
    public function up(): void
    {
        Schema::table('medical_history_entries', function (Blueprint $table) {
            $table->text('title')->change();
        });

        Schema::table('prescription_items', function (Blueprint $table) {
            $table->text('instructions')->change();
        });
    }

    /**
     * Reverse the migrations. Runs after the data migration's down(), so the
     * values are plain text again and fit.
     */
    public function down(): void
    {
        Schema::table('medical_history_entries', function (Blueprint $table) {
            $table->string('title', 150)->change();
        });

        Schema::table('prescription_items', function (Blueprint $table) {
            $table->string('instructions', 255)->change();
        });
    }
};
