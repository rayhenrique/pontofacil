<?php

namespace App\Observers;

use App\Domain\Compliance\ARP\Actions\RecordArpEventAction;
use App\Models\Company;
use Illuminate\Support\Facades\Auth;

class CompanyObserver
{
    public function updated(Company $company): void
    {
        $relevantAttributes = ['legal_name', 'cnpj', 'rep_p_software_name', 'inpi_registration_number', 'inpi_registration_status'];
        if (! $company->wasChanged($relevantAttributes)) {
            return;
        }

        $defaultEst = $company->defaultEstablishment();
        if (! $defaultEst) {
            return;
        }

        app(RecordArpEventAction::class)->recordEmployerMutation(
            establishment: $defaultEst,
            details: [
                'mutation_type' => 'company_update',
                'identifier_type' => 'cnpj',
                'identifier_number' => $company->cnpj,
                'legal_name' => $company->legal_name,
                'trade_name' => $company->trade_name,
                'inpi_registration' => $company->getInpiFiscalCode(),
                'changes' => array_intersect_key($company->getChanges(), array_flip($relevantAttributes)),
            ],
            actor: Auth::user()
        );
    }
}
