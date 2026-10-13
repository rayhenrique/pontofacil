<?php

namespace App\Domain\PTRP\Services;

use App\Models\Employee;
use Illuminate\Support\Collection;

/**
 * Fábrica do contexto de apuração mensal compartilhado.
 *
 * Ponto único de construção do MonthlyBatchContext para a Conferência
 * Mensal e os Relatórios, garantindo que ambos apurem a competência
 * sobre exatamente o mesmo conjunto pré-carregado.
 */
class MonthlyJourneyBatchService
{
    /**
     * @param  Collection<int, Employee>  $employees  Colaboradores já filtrados por escopo.
     */
    public function forCompetence(Collection $employees, int $year, int $month): MonthlyBatchContext
    {
        return new MonthlyBatchContext($employees, $year, $month);
    }
}
