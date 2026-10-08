<?php

namespace App\Observers;

use App\Domain\Company\Services\CurrentCompany;
use App\Domain\Compliance\ARP\Actions\RecordArpEventAction;
use App\Models\Employee;
use App\Models\Establishment;
use Illuminate\Support\Facades\Auth;

class EmployeeObserver
{
    public function created(Employee $employee): void
    {
        $establishment = $employee->sector?->establishment ?? CurrentCompany::defaultEstablishment() ?? Establishment::first();
        if (! $establishment) {
            return;
        }

        app(RecordArpEventAction::class)->recordWorkerMutation(
            establishment: $establishment,
            employee: $employee,
            mutationType: 'I', // Inclusão
            details: [
                'cpf' => $employee->cpf,
                'name' => $employee->user?->name ?? 'Colaborador',
                'registration_number' => $employee->registration_number,
                'job_title' => $employee->job_title,
            ],
            actor: Auth::user()
        );
    }

    public function updated(Employee $employee): void
    {
        $establishment = $employee->sector?->establishment ?? CurrentCompany::defaultEstablishment() ?? Establishment::first();
        if (! $establishment) {
            return;
        }

        $relevantAttributes = ['cpf', 'registration_number', 'job_title', 'work_schedule_id', 'sector_id'];
        if (! $employee->wasChanged($relevantAttributes)) {
            return;
        }

        app(RecordArpEventAction::class)->recordWorkerMutation(
            establishment: $establishment,
            employee: $employee,
            mutationType: 'A', // Alteração cadastral
            details: [
                'cpf' => $employee->cpf,
                'name' => $employee->user?->name ?? 'Colaborador',
                'registration_number' => $employee->registration_number,
                'job_title' => $employee->job_title,
                'changes' => array_intersect_key($employee->getChanges(), array_flip($relevantAttributes)),
            ],
            actor: Auth::user()
        );
    }

    public function deleting(Employee $employee): void
    {
        $establishment = $employee->sector?->establishment ?? CurrentCompany::defaultEstablishment() ?? Establishment::first();
        if (! $establishment) {
            return;
        }

        app(RecordArpEventAction::class)->recordWorkerMutation(
            establishment: $establishment,
            employee: $employee,
            mutationType: 'E', // Exclusão / Inativação
            details: [
                'cpf' => $employee->cpf,
                'name' => $employee->user?->name ?? 'Colaborador',
                'registration_number' => $employee->registration_number,
                'job_title' => $employee->job_title,
            ],
            actor: Auth::user()
        );
    }
}
