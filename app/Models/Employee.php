<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
}
