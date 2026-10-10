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
        Schema::table('work_schedules', function (Blueprint $table) {
            $table->string('code')->nullable()->after('id')->comment('Código identificador da escala (ex: ESC-40H-ADM)');
            $table->string('modality')->default('fixed_weekly')->after('name')->comment('Modalidade: fixed_weekly, alternating_weekly, 5x2, 6x1, 12x36, 24x72, custom_cycle, variable_shifts');
            $table->unsignedInteger('cycle_days')->default(7)->after('modality')->comment('Duração do ciclo em dias');
            $table->json('cycle_data')->nullable()->after('schedule_data')->comment('Definição estruturada de dias e turnos do ciclo');
            $table->unsignedInteger('expected_daily_minutes')->nullable()->after('cycle_days');
            $table->unsignedInteger('expected_weekly_minutes')->nullable()->after('expected_daily_minutes');
            $table->unsignedInteger('expected_monthly_minutes')->nullable()->after('expected_weekly_minutes');
            $table->string('timezone')->default('America/Sao_Paulo')->after('expected_monthly_minutes');
            $table->boolean('requires_legal_authorization')->default(false)->after('timezone')->comment('Indica se requer amparo legal específico (ex: 12x36, 24x72)');
            $table->json('allowed_legal_regimes')->nullable()->after('requires_legal_authorization')->comment('Regimes jurídicos autorizados a utilizar este modelo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_schedules', function (Blueprint $table) {
            $table->dropColumn([
                'code',
                'modality',
                'cycle_days',
                'cycle_data',
                'expected_daily_minutes',
                'expected_weekly_minutes',
                'expected_monthly_minutes',
                'timezone',
                'requires_legal_authorization',
                'allowed_legal_regimes',
            ]);
        });
    }
};
