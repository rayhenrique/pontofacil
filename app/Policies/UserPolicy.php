<?php

namespace App\Policies;

use App\Domain\Company\Services\SectorScopeService;
use App\Enums\UserRole;
use App\Models\Employee;
use App\Models\MonthlyAttendanceReportLog;
use App\Models\Sector;
use App\Models\ShiftAssignment;
use App\Models\User;

class UserPolicy
{
    public function __construct(
        protected SectorScopeService $scopeService,
    ) {}

    public function manageTimeEntries(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function manageEmployees(User $user): bool
    {
        return $user->role === UserRole::Admin || $user->role === UserRole::Manager;
    }

    public function manageTreatments(User $user): bool
    {
        return $user->role === UserRole::Admin || $user->role === UserRole::Manager;
    }

    public function viewFiscalizacao(User $user): bool
    {
        return $user->role === UserRole::Admin || $user->role === UserRole::Auditor;
    }

    public function manageSchedules(User $user): bool
    {
        return $this->scopeService->canManageSchedules($user);
    }

    public function manageSectorScope(User $user, Sector $sector): bool
    {
        return $this->scopeService->canManageSector($user, (int) $sector->id);
    }

    public function manageEmployeeScope(User $user, Employee $employee): bool
    {
        return $this->scopeService->canManageEmployee($user, $employee);
    }

    public function manageShiftScope(User $user, ShiftAssignment $shift): bool
    {
        return $this->scopeService->canManageShift($user, $shift);
    }

    public function accessReportLog(User $user, MonthlyAttendanceReportLog $log): bool
    {
        return $this->scopeService->canAccessReportLog($user, $log);
    }
}
