<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // No SQLite (usado em testes locais/in-memory), foreign keys de tabelas já criadas
        // não suportam dropForeign sem recriação de tabela. Em MySQL/PostgreSQL, alteramos as constraints.
        if (DB::getDriverName() === 'mysql') {
            Schema::table('treatment_events', function (Blueprint $table) {
                $table->dropForeign(['employee_id']);
                $table->dropForeign(['requested_by']);

                $table->foreign('employee_id')->references('id')->on('employees')->restrictOnDelete();
                $table->foreign('requested_by')->references('id')->on('users')->restrictOnDelete();
            });

            Schema::table('time_bank_accounts', function (Blueprint $table) {
                $table->dropForeign(['employee_id']);
                $table->foreign('employee_id')->references('id')->on('employees')->restrictOnDelete();
            });

            Schema::table('time_bank_transactions', function (Blueprint $table) {
                $table->dropForeign(['time_bank_account_id']);
                $table->foreign('time_bank_account_id')->references('id')->on('time_bank_accounts')->restrictOnDelete();
            });

            Schema::table('closed_periods', function (Blueprint $table) {
                $table->dropForeign(['closed_by']);
                $table->foreign('closed_by')->references('id')->on('users')->restrictOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            Schema::table('treatment_events', function (Blueprint $table) {
                $table->dropForeign(['employee_id']);
                $table->dropForeign(['requested_by']);

                $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
                $table->foreign('requested_by')->references('id')->on('users')->cascadeOnDelete();
            });

            Schema::table('time_bank_accounts', function (Blueprint $table) {
                $table->dropForeign(['employee_id']);
                $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
            });

            Schema::table('time_bank_transactions', function (Blueprint $table) {
                $table->dropForeign(['time_bank_account_id']);
                $table->foreign('time_bank_account_id')->references('id')->on('time_bank_accounts')->cascadeOnDelete();
            });

            Schema::table('closed_periods', function (Blueprint $table) {
                $table->dropForeign(['closed_by']);
                $table->foreign('closed_by')->references('id')->on('users')->cascadeOnDelete();
            });
        }
    }
};
