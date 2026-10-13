<?php

namespace Tests\Support;

use App\Domain\PTRP\Actions\ReviewMonthlyAttendanceAction;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Apoio para testes que precisam fechar competências.
 *
 * Como o fechamento exige conferência mensal concluída de todos os
 * servidores abrangidos, este helper registra a conferência de cada
 * colaborador, usando ressalva fundamentada apenas quando há
 * ocorrências (batidas incompletas, pendências ou falta de escala).
 */
class ReviewForClose
{
    /**
     * @param  Collection<int, Employee>|array<int, Employee>  $employees
     */
    public static function reviewAll(
        Collection|array $employees,
        int $year,
        int $month,
        User $reviewer,
        string $ressalva = 'Conferência validada para o fechamento da competência.',
    ): void {
        foreach ($employees as $employee) {
            try {
                app(ReviewMonthlyAttendanceAction::class)->reviewEmployee(
                    $employee,
                    $year,
                    $month,
                    $reviewer,
                );
            } catch (\InvalidArgumentException $e) {
                app(ReviewMonthlyAttendanceAction::class)->reviewEmployee(
                    $employee,
                    $year,
                    $month,
                    $reviewer,
                    $ressalva,
                );
            }
        }
    }
}
