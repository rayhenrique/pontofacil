<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fase 20.18 — Calendário Laboral: Feriados, Pontos Facultativos e Dias Especiais.
     *
     * Diferencia feriado legal de ponto facultativo e suporta eventos parciais,
     * comportamento configurável sobre a jornada e escopo territorial hierárquico.
     */
    public function up(): void
    {
        Schema::create('calendar_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->date('event_date');

            // 20.18.2 — Tipo do evento
            $table->string('type', 32)->comment('holiday, optional_day, institutional_closure, special_workday');

            // 20.18.3 — Escopo territorial
            $table->string('scope', 24)->comment('national, state, municipal, establishment');
            $table->char('state', 2)->nullable()->comment('UF do estado, quando scope = state ou municipal');
            $table->string('city')->nullable()->comment('Município, quando scope = municipal');
            $table->foreignId('establishment_id')->nullable()->constrained('establishments')->nullOnDelete()
                ->comment('Quando scope = establishment');

            // 20.18.4 — Comportamento sobre a jornada
            $table->string('work_behavior', 32)
                ->comment('no_work_expected, normal_workday, reduced_workday, optional_no_work, optional_with_compensation');

            // 20.18.6 — Suporte a eventos parciais (meio período)
            $table->boolean('all_day')->default(true);
            $table->time('starts_at')->nullable()->comment('Início do período afetado, quando all_day = false');
            $table->time('ends_at')->nullable()->comment('Fim do período afetado, quando all_day = false');

            // 20.18.12 — Compensação obrigatória
            $table->boolean('requires_compensation')->default(false);

            // 20.18.16 — Referência legal para auditoria
            $table->string('legal_reference')->nullable()->comment('Lei/Portaria/Decreto que fundamenta o evento');

            $table->text('notes')->nullable();
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Índices de consulta para o motor de jornada
            $table->index(['event_date', 'scope', 'active']);
            $table->index(['event_date', 'establishment_id', 'active']);
            $table->index(['type', 'event_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_events');
    }
};
