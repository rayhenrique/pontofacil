<?php

namespace App\Domain\TimeClock\Actions;

use App\Models\PunchEvent;
use App\Models\TimeEntry;
use App\Models\User;

class DetermineNextPunchDirectionAction
{
    /**
     * Determina a próxima direção da batida ('in' ou 'out') com base na sequência
     * cronológica real dos fatos persistidos do colaborador (PunchEvent), com fallback legado.
     *
     * Regras fundamentais:
     * 1. Não presume que o primeiro registro de cada dia civil seja 'in' (suporte a jornadas noturnas 22h -> 06h).
     * 2. Ordena estritamente por occurred_at_utc desc (imune a mudanças de fuso horário, virada de mês ou ano).
     * 3. Respeita intervalos que atravessam meia-noite (20h in -> 23h30 out -> 00h30 in -> 05h out).
     * 4. Não introduz bloqueios por escala, tolerância ou horário contratual.
     */
    public function execute(User $user): string
    {
        $lastPunch = PunchEvent::where('user_id', $user->id)
            ->orderBy('occurred_at_utc', 'desc')
            ->first();

        if ($lastPunch) {
            return ($lastPunch->direction === 'in') ? 'out' : 'in';
        }

        // Fallback para histórico legado na tabela time_entries caso não haja PunchEvents registrados
        $lastLegacy = TimeEntry::where('user_id', $user->id)
            ->orderBy('timestamp', 'desc')
            ->first();

        if ($lastLegacy) {
            return ($lastLegacy->type === 'in') ? 'out' : 'in';
        }

        return 'in';
    }
}
