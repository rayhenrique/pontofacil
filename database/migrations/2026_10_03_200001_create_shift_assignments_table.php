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
        Schema::create('shift_assignments', function (Blueprint $table) {
            $table->id();
            $table->string('shift_code', 36)->unique()->comment('Identificador único para vincular planejamento, apuração e visualização');
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('work_schedule_assignment_id')->nullable()->constrained('work_schedule_assignments')->nullOnDelete();
            $table->foreignId('work_schedule_id')->nullable()->constrained('work_schedules')->nullOnDelete();

            // Instantes com precisão de timezone e data local correspondente
            $table->dateTime('start_at_utc')->comment('Instante de início em UTC');
            $table->dateTime('end_at_utc')->comment('Instante de término em UTC');
            $table->dateTime('start_at_local')->comment('Horário local de início da jornada');
            $table->dateTime('end_at_local')->comment('Horário local de término da jornada');
            $table->string('timezone', 64)->default('America/Sao_Paulo');

            // Métricas e características da jornada
            $table->unsignedInteger('break_minutes')->default(0)->comment('Minutos de intervalo intrajornada previstos');
            $table->unsignedInteger('expected_work_minutes')->default(0)->comment('Minutos esperados de trabalho efetivo');
            $table->string('shift_type')->default('regular')->comment('Tipo: regular, shift_12x36, shift_24x72, extraordinary, swapped, on_call, off_day');
            $table->string('origin')->default('generated_cycle')->comment('Origem: generated_cycle, manual_assignment, swap, extraordinary');
            $table->string('status')->default('scheduled')->comment('Status: scheduled, confirmed, completed, cancelled, swapped, absent');

            $table->boolean('is_day_off')->default(false)->comment('Indica folga da escala');
            $table->boolean('is_night_shift')->default(false)->comment('Indica se incide em período noturno');
            $table->boolean('crosses_midnight')->default(false)->comment('Indica se cruza a meia-noite');

            // Auditoria e permuta
            $table->string('reason')->nullable()->comment('Justificativa para alterações ou designações manuais');
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete()->comment('Responsável pela atribuição');
            $table->foreignId('swapped_with_employee_id')->nullable()->constrained('employees')->nullOnDelete()->comment('Colaborador permutado em caso de troca');
            $table->text('notes')->nullable()->comment('Observações contextuais ou de calendário');

            $table->timestamps();

            $table->index(['employee_id', 'start_at_local']);
            $table->index(['employee_id', 'status']);
            $table->index(['start_at_utc', 'end_at_utc']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shift_assignments');
    }
};
