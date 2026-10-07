<?php

namespace App\Models;

use App\Domain\Company\Services\CurrentCompany;
use App\Domain\Compliance\ARP\Actions\RecordArpEventAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Employee extends Model
{
    protected $fillable = [
        'user_id',
        'sector_id',
        'work_schedule_id',
        'registration_number',
        'cpf',
        'phone',
        'job_title',
        'contract_type',
        'workload',
        'zone',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function sector()
    {
        return $this->belongsTo(Sector::class);
    }

    public function workSchedule()
    {
        return $this->belongsTo(WorkSchedule::class);
    }

    public function timeBankAccount()
    {
        return $this->hasOne(TimeBankAccount::class);
    }

    public function treatmentEvents()
    {
        return $this->hasMany(TreatmentEvent::class);
    }

    /**
     * Inativa / desliga fiscalmente o trabalhador, gerando o evento de mutação tipo 'E' na ARP.
     */
    public function inactivate(?string $reason = null): void
    {
        $establishment = $this->sector?->establishment ?? CurrentCompany::defaultEstablishment() ?? Establishment::first();
        if ($establishment) {
            app(RecordArpEventAction::class)->recordWorkerMutation(
                establishment: $establishment,
                employee: $this,
                mutationType: 'E',
                details: [
                    'cpf' => $this->cpf,
                    'name' => $this->user?->name ?? 'Colaborador',
                    'registration_number' => $this->registration_number,
                    'reason' => $reason ?? 'Inativação/desligamento do trabalhador',
                ],
                actor: Auth::user()
            );
        }
    }
}
