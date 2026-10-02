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
        Schema::dropIfExists('closed_period_employee_snapshots');

        Schema::create('closed_period_employee_snapshots', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('closed_period_id')->constrained('closed_periods')->restrictOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->unsignedInteger('version')->default(1);

            $table->json('employee_snapshot')->comment('Dados cadastrais, cargo, matrícula, vínculo, estabelecimento');
            $table->json('schedule_snapshot')->comment('Escala esperada, horários, tolerâncias');
            $table->json('journey_snapshot')->comment('Apuração diária completa de todas as jornadas e batidas do mês');
            $table->json('treatment_snapshot')->comment('Tratamentos aprovados, inclusões e desconsiderações da competência');
            $table->json('time_bank_snapshot')->comment('Saldos inicial, créditos, débitos, encerramento/reset e saldo transportado');

            $table->string('snapshot_hash', 64)->comment('Hash SHA-256 do snapshot individual do empregado');
            $table->timestamps();

            $table->index(['closed_period_id', 'employee_id'], 'cpes_period_emp_idx');
            $table->index(['closed_period_id', 'version'], 'cpes_period_ver_idx');
            $table->index(['employee_id', 'created_at'], 'cpes_emp_created_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('closed_period_employee_snapshots');
    }
};
