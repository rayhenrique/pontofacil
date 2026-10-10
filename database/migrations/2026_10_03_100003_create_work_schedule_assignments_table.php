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
        Schema::create('work_schedule_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('work_schedule_id')->constrained('work_schedules')->restrictOnDelete();
            $table->date('effective_from')->comment('Início da vigência da escala para o colaborador');
            $table->date('effective_until')->nullable()->comment('Término da vigência (null = vigente por tempo indeterminado)');
            $table->string('reason')->comment('Motivo da atribuição ou alteração de escala');
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete()->comment('Usuário responsável pela atribuição');
            $table->text('notes')->nullable()->comment('Observações administrativas ou comprobatórias');
            $table->timestamps();

            $table->index(['employee_id', 'effective_from']);
            $table->index(['effective_from', 'effective_until']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_schedule_assignments');
    }
};
