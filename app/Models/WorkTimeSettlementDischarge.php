<?php

namespace App\Models;

use App\Domain\Settlement\Enums\SettlementDischargeType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkTimeSettlementDischarge extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'employee_id',
        'closed_period_id',
        'discharge_type',
        'minutes',
        'reference_period',
        'execution_date',
        'document_reference',
        'description',
        'approved_by',
        'created_by',
        'metadata',
    ];

    protected $casts = [
        'discharge_type' => SettlementDischargeType::class,
        'minutes' => 'integer',
        'execution_date' => 'date',
        'metadata' => 'array',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function closedPeriod(): BelongsTo
    {
        return $this->belongsTo(ClosedPeriod::class);
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function formattedMinutes(): string
    {
        return TimeBankAccount::formatMinutes($this->minutes);
    }
}
