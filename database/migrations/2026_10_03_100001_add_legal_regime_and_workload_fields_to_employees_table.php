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
        Schema::table('employees', function (Blueprint $table) {
            $table->string('legal_regime')->nullable()->after('contract_type')->comment('Regime jurídico: clt, federal_statutory, state_statutory, municipal_statutory, other');

            // Carga horária estruturada em minutos
            $table->unsignedInteger('daily_workload_minutes')->nullable()->after('workload')->comment('Carga diária ordinária em minutos (ex: 480 para 8h)');
            $table->unsignedInteger('weekly_workload_minutes')->nullable()->after('daily_workload_minutes')->comment('Carga semanal contratual em minutos (ex: 2400 para 40h)');
            $table->unsignedInteger('monthly_workload_minutes')->nullable()->after('weekly_workload_minutes')->comment('Carga mensal contratual ou meta em minutos');
            $table->string('workload_modality')->default('fixed')->after('monthly_workload_minutes')->comment('Modalidade: fixed ou variable_by_schedule');
            $table->boolean('is_variable_workload')->default(false)->after('workload_modality')->comment('Indica se a meta mensal varia conforme escala');

            // Legislação e referências normativas (estatutários)
            $table->string('normative_jurisdiction')->nullable()->after('is_variable_workload')->comment('Esfera: federal, state, municipal, other');
            $table->string('normative_entity')->nullable()->after('normative_jurisdiction')->comment('Ente federativo ou órgão');
            $table->string('normative_reference')->nullable()->after('normative_entity')->comment('Referência normativa (Lei, Estatuto)');
            $table->string('normative_act_number')->nullable()->after('normative_reference')->comment('Número do ato regulamentar / decreto');
            $table->date('normative_effective_from')->nullable()->after('normative_act_number')->comment('Início da vigência do ato');
            $table->date('normative_effective_until')->nullable()->after('normative_effective_from')->comment('Término da vigência do ato');
            $table->string('normative_validation_status')->default('pending')->after('normative_effective_until')->comment('Status de validação RH/Jurídico: pending, validated, rejected');
            $table->text('normative_notes')->nullable()->after('normative_validation_status')->comment('Observações do RH sobre a legislação');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn([
                'legal_regime',
                'daily_workload_minutes',
                'weekly_workload_minutes',
                'monthly_workload_minutes',
                'workload_modality',
                'is_variable_workload',
                'normative_jurisdiction',
                'normative_entity',
                'normative_reference',
                'normative_act_number',
                'normative_effective_from',
                'normative_effective_until',
                'normative_validation_status',
                'normative_notes',
            ]);
        });
    }
};
