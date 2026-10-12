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
        Schema::create('monthly_attendance_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->string('status', 32)->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->text('reservation_notes')->nullable();
            $table->boolean('has_unresolved_occurrences')->default(false);
            $table->unsignedInteger('occurrences_count')->default(0);
            $table->json('occurrences_summary')->nullable();
            $table->integer('expected_minutes')->default(0);
            $table->integer('worked_minutes')->default(0);
            $table->integer('balance_minutes')->default(0);
            $table->foreignId('closed_period_id')->nullable()->constrained('closed_periods')->nullOnDelete();
            $table->timestamps();

            $table->unique(['employee_id', 'year', 'month']);
            $table->index(['year', 'month', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monthly_attendance_reviews');
    }
};
