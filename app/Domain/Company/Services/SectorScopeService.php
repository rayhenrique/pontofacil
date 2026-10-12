<?php

namespace App\Domain\Company\Services;

use App\Models\Employee;
use App\Models\Establishment;
use App\Models\MonthlyAttendanceReportLog;
use App\Models\Sector;
use App\Models\ShiftAssignment;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class SectorScopeService
{
    /**
     * Identificadores dos setores gerenciados pelo usuário.
     *
     * @return array<int>
     */
    public function managedSectorIds(User $user): array
    {
        if (! $user->isManager()) {
            return [];
        }

        return Sector::where('manager_id', $user->id)->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * Pode operar escalas e plantões (criar, atribuir, editar, cancelar, gerar, trocar).
     */
    public function canManageSchedules(User $user): bool
    {
        return $user->isAdmin() || $user->isManager();
    }

    public function canManageSector(User $user, ?int $sectorId): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if (! $user->isManager() || $sectorId === null) {
            return false;
        }

        return in_array($sectorId, $this->managedSectorIds($user), true);
    }

    public function canManageEmployee(User $user, ?Employee $employee): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if (! $user->isManager() || $employee === null || $employee->sector_id === null) {
            return false;
        }

        return in_array((int) $employee->sector_id, $this->managedSectorIds($user), true);
    }

    public function canManageShift(User $user, ?ShiftAssignment $shift): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if (! $user->isManager() || $shift === null) {
            return false;
        }

        $employee = $shift->relationLoaded('employee') ? $shift->employee : Employee::find($shift->employee_id);

        return $this->canManageEmployee($user, $employee);
    }

    public function canManageEstablishment(User $user, ?int $establishmentId): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if (! $user->isManager()) {
            return false;
        }

        if ($establishmentId === null) {
            return true;
        }

        $managedIds = $this->managedSectorIds($user);

        if (empty($managedIds)) {
            return false;
        }

        return Sector::whereIn('id', $managedIds)->where('establishment_id', $establishmentId)->exists();
    }

    /**
     * Verifica se o usuário pode despachar um log de relatório ao setor de pagamento.
     */
    public function canAccessReportLog(User $user, MonthlyAttendanceReportLog $log): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if (! $user->isManager()) {
            return false;
        }

        $managedIds = $this->managedSectorIds($user);

        if (empty($managedIds)) {
            return false;
        }

        if ($log->sector_id !== null && ! in_array((int) $log->sector_id, $managedIds, true)) {
            return false;
        }

        if ($log->establishment_id !== null) {
            $hasManagedInEstablishment = Sector::whereIn('id', $managedIds)
                ->where('establishment_id', (int) $log->establishment_id)
                ->exists();

            if (! $hasManagedInEstablishment) {
                return false;
            }
        }

        $filters = is_array($log->filters) ? $log->filters : [];

        $filterSectorId = isset($filters['sector_id']) && $filters['sector_id'] !== null && $filters['sector_id'] !== ''
            ? (int) $filters['sector_id']
            : null;

        if ($filterSectorId !== null && ! in_array($filterSectorId, $managedIds, true)) {
            return false;
        }

        $filterEmployeeId = isset($filters['employee_id']) && $filters['employee_id'] !== null && $filters['employee_id'] !== ''
            ? (int) $filters['employee_id']
            : null;

        if ($filterEmployeeId !== null) {
            $employee = Employee::find($filterEmployeeId);

            if (! $this->canManageEmployee($user, $employee)) {
                return false;
            }
        }

        $filterEstablishmentId = isset($filters['establishment_id']) && $filters['establishment_id'] !== null && $filters['establishment_id'] !== ''
            ? (int) $filters['establishment_id']
            : null;

        if ($filterEstablishmentId !== null && ! $this->canManageEstablishment($user, $filterEstablishmentId)) {
            return false;
        }

        if ($log->sector_id === null && $filterSectorId === null && $filterEmployeeId === null && $filterEstablishmentId === null && $log->establishment_id === null) {
            return (int) $log->generated_by === (int) $user->id;
        }

        return true;
    }

    /**
     * Valida os filtros de relatório no servidor. Rejeita escopo fora da permissão.
     *
     * @throws AuthorizationException
     */
    public function validateReportFilters(User $user, ?int $establishmentId, ?int $sectorId, ?int $employeeId): void
    {
        if ($user->isAdmin()) {
            return;
        }

        if (! $user->isManager()) {
            throw new AuthorizationException('Acesso não autorizado.');
        }

        if ($sectorId !== null && ! $this->canManageSector($user, $sectorId)) {
            throw new AuthorizationException('Acesso não autorizado.');
        }

        if ($establishmentId !== null && ! $this->canManageEstablishment($user, $establishmentId)) {
            throw new AuthorizationException('Acesso não autorizado.');
        }

        if ($employeeId !== null) {
            $employee = Employee::find($employeeId);

            if (! $this->canManageEmployee($user, $employee)) {
                throw new AuthorizationException('Acesso não autorizado.');
            }
        }
    }

    /**
     * Valida os filtros de escala/plantão no servidor.
     *
     * @throws AuthorizationException
     */
    public function validateShiftFilters(User $user, ?int $sectorId, ?int $employeeId): void
    {
        if ($user->isAdmin()) {
            return;
        }

        if (! $user->isManager()) {
            throw new AuthorizationException('Acesso não autorizado.');
        }

        if ($sectorId !== null && ! $this->canManageSector($user, $sectorId)) {
            throw new AuthorizationException('Acesso não autorizado.');
        }

        if ($employeeId !== null) {
            $employee = Employee::find($employeeId);

            if (! $this->canManageEmployee($user, $employee)) {
                throw new AuthorizationException('Acesso não autorizado.');
            }
        }
    }

    /**
     * @throws AuthorizationException
     */
    public function authorizeSchedules(User $user): void
    {
        if (! $this->canManageSchedules($user)) {
            throw new AuthorizationException('Acesso não autorizado.');
        }
    }

    /**
     * @throws AuthorizationException
     */
    public function authorizeSector(User $user, ?int $sectorId): void
    {
        if (! $this->canManageSector($user, $sectorId)) {
            throw new AuthorizationException('Acesso não autorizado.');
        }
    }

    /**
     * @throws AuthorizationException
     */
    public function authorizeEmployee(User $user, ?Employee $employee): void
    {
        if (! $this->canManageEmployee($user, $employee)) {
            throw new AuthorizationException('Acesso não autorizado.');
        }
    }

    /**
     * @throws AuthorizationException
     */
    public function authorizeShift(User $user, ?ShiftAssignment $shift): void
    {
        if (! $this->canManageShift($user, $shift)) {
            throw new AuthorizationException('Acesso não autorizado.');
        }
    }

    /**
     * @throws AuthorizationException
     */
    public function authorizeEstablishment(User $user, ?int $establishmentId): void
    {
        if (! $this->canManageEstablishment($user, $establishmentId)) {
            throw new AuthorizationException('Acesso não autorizado.');
        }
    }

    /**
     * @throws AuthorizationException
     */
    public function authorizeReportLog(User $user, MonthlyAttendanceReportLog $log): void
    {
        if (! $this->canAccessReportLog($user, $log)) {
            throw new AuthorizationException('Acesso não autorizado.');
        }
    }

    /**
     * Estabelecimentos com ao menos um setor gerenciado pelo usuário.
     *
     * @return array<int>
     */
    public function managedEstablishmentIds(User $user): array
    {
        if ($user->isAdmin()) {
            return Establishment::pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        if (! $user->isManager()) {
            return [];
        }

        $managedIds = $this->managedSectorIds($user);

        if (empty($managedIds)) {
            return [];
        }

        return Sector::whereIn('id', $managedIds)->distinct()->pluck('establishment_id')->map(fn ($id) => (int) $id)->all();
    }
}
