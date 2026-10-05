<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('doctor_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('slot_duration_minutes')->default(45);
            $table->unsignedSmallInteger('booking_window_days')->default(30);
            $table->unsignedSmallInteger('cancel_cutoff_hours')->default(2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('doctor_settings');
    }
};
