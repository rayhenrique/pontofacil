<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 20.18.18 — Adicionar coluna calendar_snapshot à tabela de snapshots de empregados.
     *
     * Armazena os eventos de calendário utilizados na apuração da competência,
     * garantindo que alterações futuras no calendário não afetem meses já fechados.
     */
    public function up(): void
    {
        Schema::table('closed_period_employee_snapshots', function (Blueprint $table) {
            $table->json('calendar_snapshot')
                ->nullable()
                ->after('time_bank_snapshot')
                ->comment('Eventos do calendário laboral aplicados na competência (20.18.18)');
        });
    }

    public function down(): void
    {
        Schema::table('closed_period_employee_snapshots', function (Blueprint $table) {
            $table->dropColumn('calendar_snapshot');
        });
    }
};
