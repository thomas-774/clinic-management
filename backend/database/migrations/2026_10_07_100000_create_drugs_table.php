<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The prescription drug catalogue (§5.1 drugs, FR-J.1). There is no price
     * column on purpose: prices from the PDF are never imported.
     */
    public function up(): void
    {
        Schema::create('drugs', function (Blueprint $table) {
            $table->id();
            $table->string('trade_name', 150);
            $table->string('form', 60);
            $table->string('pack', 60)->nullable();
            $table->string('category', 40);
            $table->json('active_ingredients');
            $table->text('uses');
            $table->text('warnings')->nullable();
            $table->text('suggested_dose')->nullable();
            $table->string('seed_key', 80)->nullable()->unique();
            $table->unsignedSmallInteger('source_page')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'trade_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('drugs');
    }
};
