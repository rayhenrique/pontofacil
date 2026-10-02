<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClosedPeriodEmployeeSnapshot extends Model
{
    use HasUlids;

    protected $fillable = [
        'id',
        'closed_period_id',
        'employee_id',
        'version',
        'employee_snapshot',
        'schedule_snapshot',
        'journey_snapshot',
        'treatment_snapshot',
        'time_bank_snapshot',
        'snapshot_hash',
    ];

    protected $casts = [
        'version' => 'integer',
        'employee_snapshot' => 'array',
        'schedule_snapshot' => 'array',
        'journey_snapshot' => 'array',
        'treatment_snapshot' => 'array',
        'time_bank_snapshot' => 'array',
    ];

    public function closedPeriod(): BelongsTo
    {
        return $this->belongsTo(ClosedPeriod::class, 'closed_period_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
