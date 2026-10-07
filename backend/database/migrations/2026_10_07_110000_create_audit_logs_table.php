<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Who read or changed a medical or money record (NFR-S.4, T11-04). Rows are
     * never updated; only `audit:prune` (T11-05) deletes old ones.
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_role', 20)->nullable();
            $table->string('action', 20);
            $table->string('auditable_type', 100);
            $table->unsignedBigInteger('auditable_id');
            $table->unsignedBigInteger('patient_id')->nullable()->index();
            $table->json('changed_fields')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['patient_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index('created_at');
            $table->index(['auditable_type', 'auditable_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
