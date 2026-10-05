<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One numbered line of a prescription. drug_name and drug_form are a
     * snapshot taken when the line is saved, so later catalogue edits never
     * change an issued prescription (§5.1 prescription_items, RX-2).
     */
    public function up(): void
    {
        Schema::create('prescription_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prescription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('drug_id')->nullable()->constrained()->nullOnDelete();
            $table->string('drug_name', 150);
            $table->string('drug_form', 60)->nullable();
            $table->string('instructions', 255);
            $table->unsignedTinyInteger('position');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prescription_items');
    }
};
