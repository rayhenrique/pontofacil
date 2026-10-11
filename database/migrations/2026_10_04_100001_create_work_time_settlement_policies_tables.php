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
        Schema::create('work_time_settlement_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('establishment_id')->nullable()->constrained('establishments')->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('legal_regime', 32)->nullable()->comment('CLT, federal_statutory, state_statutory, municipal_statutory');
            $table->string('name');
            $table->string('modality', 32)->default('cumulative_bank')->comment('no_bank, monthly_compensation, cumulative_bank, direct_payroll, time_off, hybrid, legacy_monthly_reset');
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->text('legal_framework')->comment('Fundamentação jurídica: Lei, CCT, ACT, acordo individual');
            $table->json('compensation_terms')->nullable()->comment('Condições de compensação: prazo em meses, tolerâncias, teto diário');
            $table->json('carry_over_terms')->nullable()->comment('Condições de transporte: limite de horas, expiração');
            $table->json('hybrid_rules')->nullable()->comment('Regras do modelo misto: teto mensal/diário no banco, excedente em folha');
            $table->string('approval_status', 24)->default('approved')->comment('approved, pending_approval, draft, rejected');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->string('status', 20)->default('active')->comment('active, inactive');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'approval_status', 'effective_from', 'effective_until'], 'idx_settlement_policies_active');
            $table->index(['employee_id', 'effective_from'], 'idx_settlement_policies_emp');
            $table->index(['establishment_id', 'effective_from'], 'idx_settlement_policies_est');
            $table->index(['company_id', 'effective_from'], 'idx_settlement_policies_comp');
        });

        Schema::create('work_time_settlement_discharges', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->foreignId('closed_period_id')->nullable()->constrained('closed_periods')->nullOnDelete();
            $table->string('discharge_type', 32)->comment('payroll_paid, payroll_deducted, time_off_enjoyed, compensated_in_period, carried_over, monthly_reset_legacy, term_of_settlement');
            $table->integer('minutes')->comment('Minutos quitados/destinados (positivo para crédito, negativo para débito)');
            $table->string('reference_period', 7)->comment('Competência YYYY-MM');
            $table->date('execution_date')->comment('Data de comprovação da execução/quitação');
            $table->string('document_reference')->nullable()->comment('Número do holerite, recibo, portaria ou memorando');
            $table->string('description');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'reference_period'], 'idx_discharges_emp_period');
            $table->index(['discharge_type', 'execution_date'], 'idx_discharges_type_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_time_settlement_discharges');
        Schema::dropIfExists('work_time_settlement_policies');
    }
};
