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
        Schema::create('treatment_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->unsignedBigInteger('employment_id')->nullable()->index();
            $table->string('reference_punch_id', 26)->nullable()->index();
            $table->foreign('reference_punch_id')->references('id')->on('punch_events')->nullOnDelete();

            $table->string('type', 40)->comment('manual_punch_added, punch_disregarded, absence_added, absence_justified, classification_override');
            $table->string('status', 20)->default('pending')->comment('pending, approved, rejected');

            $table->dateTime('effective_at')->comment('Data e hora de vigência da marcação/ocorrência');
            $table->json('old_value_json')->nullable();
            $table->json('new_value_json')->nullable();

            $table->string('reason_code', 32)->nullable();
            $table->text('reason_text')->comment('Justificativa obrigatória');
            $table->string('attachment_path')->nullable();

            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('decided_at')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->timestamps();

            $table->index(['employee_id', 'effective_at']);
            $table->index(['status', 'effective_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('treatment_events');
    }
};
