<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The prescription print header and paper size (§5.1 doctor_settings, FR-J.5, FR-J.6).
     */
    public function up(): void
    {
        Schema::table('doctor_settings', function (Blueprint $table) {
            $table->string('clinic_name', 150)->nullable()->after('cancel_cutoff_hours');
            $table->string('doctor_title', 150)->nullable()->after('clinic_name');
            $table->string('clinic_address', 150)->nullable()->after('doctor_title');
            $table->string('clinic_phone', 150)->nullable()->after('clinic_address');
            $table->string('prescription_footer', 255)->nullable()->after('clinic_phone');
            $table->enum('prescription_paper', ['A5', 'A4'])->default('A5')->after('prescription_footer');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('doctor_settings', function (Blueprint $table) {
            $table->dropColumn([
                'clinic_name', 'doctor_title', 'clinic_address', 'clinic_phone',
                'prescription_footer', 'prescription_paper',
            ]);
        });
    }
};
