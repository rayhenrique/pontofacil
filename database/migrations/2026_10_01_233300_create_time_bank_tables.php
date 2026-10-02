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
        Schema::create('time_bank_policies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('enabled')->default(false)->comment('Por padrão desativado; ativado por decisão do Admin/RH');
            $table->string('closing_mode', 20)->default('CARRY_OVER')->comment('CARRY_OVER ou MONTHLY_RESET');
            $table->date('valid_from');
            $table->date('valid_until')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['enabled', 'valid_from', 'valid_until']);
        });

        Schema::create('time_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->unique()->constrained('employees')->restrictOnDelete();
            $table->unsignedBigInteger('employment_id')->nullable()->index();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('time_bank_transactions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('time_bank_account_id')->constrained('time_bank_accounts')->restrictOnDelete();
            $table->string('type', 32)->comment('overtime_credit, compensation_debit, manual_credit, manual_debit, monthly_reset, opening_balance, expiration');
            $table->integer('minutes')->comment('Saldo contábil em minutos (positivo ou negativo)');
            $table->date('reference_date')->comment('Data da jornada ou do evento de referência');
            $table->string('source_type')->nullable();
            $table->string('source_id')->nullable();
            $table->string('description')->nullable();
            $table->text('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['time_bank_account_id', 'reference_date']);
            $table->index(['type', 'reference_date']);
        });

        Schema::create('closed_periods', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->foreignId('policy_id')->nullable()->constrained('time_bank_policies')->nullOnDelete();
            $table->json('policy_snapshot')->nullable();
            $table->string('status', 20)->default('closed')->comment('closed, reopened');
            $table->foreignId('closed_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('closed_at');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['year', 'month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('closed_periods');
        Schema::dropIfExists('time_bank_transactions');
        Schema::dropIfExists('time_bank_accounts');
        Schema::dropIfExists('time_bank_policies');
    }
};
