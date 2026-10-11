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
        Schema::table('closed_period_employee_snapshots', function (Blueprint $table) {
            $table->json('shifts_snapshot')
                ->nullable()
                ->after('schedule_snapshot')
                ->comment('Plantões e escalas cíclicas previstos e realizados do colaborador na competência');

            $table->json('rule_profile_snapshot')
                ->nullable()
                ->after('shifts_snapshot')
                ->comment('Snapshot do perfil de regras jurídicas e versão normativa aplicada');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('closed_period_employee_snapshots', function (Blueprint $table) {
            $table->dropColumn(['shifts_snapshot', 'rule_profile_snapshot']);
        });
    }
};
