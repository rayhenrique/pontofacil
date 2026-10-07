<?php

namespace App\Observers;

use App\Domain\Compliance\ARP\Actions\RecordArpEventAction;
use App\Models\Establishment;
use Illuminate\Support\Facades\Auth;

class EstablishmentObserver
{
    public function created(Establishment $establishment): void
    {
        if (! $establishment->company_id) {
            return;
        }

        app(RecordArpEventAction::class)->recordEmployerMutation(
            establishment: $establishment,
            details: [
                'mutation_type' => 'creation',
                'identifier_type' => $establishment->identifier_type,
                'identifier_number' => $establishment->identifier_number,
                'legal_name' => $establishment->company?->legal_name ?? $establishment->name,
                'trade_name' => $establishment->company?->trade_name ?? $establishment->name,
                'name' => $establishment->name,
                'city' => $establishment->city,
                'state' => $establishment->state,
            ],
            actor: Auth::user()
        );
    }

    public function updated(Establishment $establishment): void
    {
        $relevantAttributes = ['identifier_number', 'identifier_type', 'name', 'timezone', 'city', 'state', 'address'];
        if (! $establishment->wasChanged($relevantAttributes)) {
            return;
        }

        app(RecordArpEventAction::class)->recordEmployerMutation(
            establishment: $establishment,
            details: [
                'mutation_type' => 'establishment_update',
                'identifier_type' => $establishment->identifier_type,
                'identifier_number' => $establishment->identifier_number,
                'legal_name' => $establishment->company?->legal_name ?? $establishment->name,
                'name' => $establishment->name,
                'city' => $establishment->city,
                'state' => $establishment->state,
                'changes' => array_intersect_key($establishment->getChanges(), array_flip($relevantAttributes)),
            ],
            actor: Auth::user()
        );
    }
}
