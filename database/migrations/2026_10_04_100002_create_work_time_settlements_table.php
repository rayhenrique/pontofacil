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
        Schema::create('work_time_settlements', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->foreignId('closed_period_id')->nullable()->constrained('closed_periods')->nullOnDelete();
            $table->foreignId('settlement_policy_id')->nullable()->constrained('work_time_settlement_policies')->nullOnDelete();
            $table->string('policy_version', 32)->nullable()->comment('Versão ou identificador da política aplicada');
            $table->string('reference_period', 7)->comment('Competência YYYY-MM');
            $table->integer('minutes')->comment('Saldo assinado: positivo (+) para crédito/extra, negativo (-) para déficit/débito');
            $table->string('settlement_type', 40)->comment('time_off_compensation, deficit_compensation, payroll_payment, payroll_deduction, carry_over, administrative_adjustment, legacy_monthly_reset, other_policy_destination');
            $table->string('status', 25)->default('pending')->comment('pending, approved, awaiting_execution, executed, cancelled, reversed');
            $table->string('origin_type', 40)->comment('daily_journey, monthly_closing_balance, time_bank_balance, administrative_discrepancy');
            $table->string('origin_identifier', 64)->nullable()->comment('Identificador de origem: data YYYY-MM-DD, ID da jornada ou saldo');
            $table->date('operation_date')->comment('Data do lançamento da destinação');
            $table->date('execution_date')->nullable()->comment('Data em que a destinação foi faticamente realizada (paga ou usufruída)');
            $table->string('document_reference')->nullable()->comment('Número do holerite, recibo, portaria, memorando ou termo');
            $table->text('justification')->comment('Justificativa fundamentada da operação');
            $table->string('idempotency_key', 64)->nullable()->unique()->comment('Chave de idempotência contra duplicidade');
            $table->ulid('reversal_of_id')->nullable()->comment('ID do settlement estornado por esta operação');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->foreignId('executed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('executed_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('cancelled_at')->nullable();
            $table->json('metadata')->nullable()->comment('Histórico de status, dados de auditoria e payload');
            $table->timestamps();

            $table->foreign('reversal_of_id')->references('id')->on('work_time_settlements')->nullOnDelete();
            $table->index(['employee_id', 'reference_period'], 'idx_settlements_emp_period');
            $table->index(['status', 'settlement_type'], 'idx_settlements_status_type');
            $table->index(['operation_date'], 'idx_settlements_op_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_time_settlements');
    }
};
