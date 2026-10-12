<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_attendance_report_logs', function (Blueprint $table) {
            $table->id();
            $table->integer('year');
            $table->tinyInteger('month');
            $table->foreignId('establishment_id')->nullable()->constrained('establishments')->nullOnDelete();
            $table->foreignId('sector_id')->nullable()->constrained('sectors')->nullOnDelete();
            $table->foreignId('generated_by')->constrained('users')->cascadeOnDelete();
            $table->string('format', 20)->default('pdf'); // 'pdf', 'csv', 'view'
            $table->boolean('is_provisional')->default(false);
            $table->json('filters')->nullable();
            $table->json('totals_snapshot')->nullable();

            // Encaminhamento explícito ao setor de pagamento
            $table->timestamp('dispatched_to_payroll_at')->nullable();
            $table->foreignId('dispatched_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('dispatch_notes')->nullable();

            $table->timestamps();

            $table->index(['year', 'month']);
            $table->index('generated_by');
            $table->index('dispatched_to_payroll_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_attendance_report_logs');
    }
};
