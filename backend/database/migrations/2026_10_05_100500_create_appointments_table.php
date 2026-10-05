<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * active_slot equals start_at while the appointment holds its slot
     * (booked, checked_in, completed) and NULL otherwise. The unique index on
     * (doctor_id, active_slot) blocks double booking (BR-2) while letting a
     * cancelled or no-show slot be booked again, since MySQL allows many NULLs.
     */
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->enum('status', ['booked', 'checked_in', 'completed', 'cancelled', 'no_show'])->default('booked');
            $table->dateTime('checked_in_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->dateTime('active_slot')->nullable()
                ->storedAs("CASE WHEN status IN ('booked', 'checked_in', 'completed') THEN start_at END");
            $table->timestamps();

            $table->unique(['doctor_id', 'active_slot']);
            $table->index(['doctor_id', 'start_at']);
            $table->index('patient_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
