<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Índices aditivos para as leituras mensais de frequência.
     *
     * Cobre os pré-carregamentos por competência (intervalos sobre
     * colunas de data/hora locais) e as consultas diárias do motor PTRP.
     * Nenhuma coluna é alterada ou removida.
     */
    public function up(): void
    {
        Schema::table('punch_events', function (Blueprint $table) {
            $table->index(['employee_id', 'occurred_at_local'], 'punch_events_employee_local_idx');
            $table->index(['user_id', 'occurred_at_local'], 'punch_events_user_local_idx');
        });

        Schema::table('treatment_events', function (Blueprint $table) {
            $table->index(['employee_id', 'status', 'effective_at'], 'treatment_events_employee_status_idx');
            $table->index(['employment_id', 'status', 'effective_at'], 'treatment_events_employment_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('treatment_events', function (Blueprint $table) {
            $table->dropIndex('treatment_events_employment_status_idx');
            $table->dropIndex('treatment_events_employee_status_idx');
        });

        Schema::table('punch_events', function (Blueprint $table) {
            $table->dropIndex('punch_events_user_local_idx');
            $table->dropIndex('punch_events_employee_local_idx');
        });
    }
};
