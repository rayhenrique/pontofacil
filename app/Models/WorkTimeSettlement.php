<?php

namespace App\Models;

use App\Domain\Settlement\Enums\SettlementOriginType;
use App\Domain\Settlement\Enums\WorkTimeSettlementStatus;
use App\Domain\Settlement\Enums\WorkTimeSettlementType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkTimeSettlement extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'employee_id',
        'closed_period_id',
        'settlement_policy_id',
        'policy_version',
        'reference_period',
        'minutes',
        'settlement_type',
        'status',
        'origin_type',
        'origin_identifier',
        'operation_date',
        'execution_date',
        'document_reference',
        'justification',
        'idempotency_key',
        'reversal_of_id',
        'created_by',
        'approved_by',
        'approved_at',
        'executed_by',
        'executed_at',
        'cancelled_by',
        'cancelled_at',
        'metadata',
    ];

    protected $casts = [
        'minutes' => 'integer',
        'settlement_type' => WorkTimeSettlementType::class,
        'status' => WorkTimeSettlementStatus::class,
        'origin_type' => SettlementOriginType::class,
        'operation_date' => 'date',
        'execution_date' => 'date',
        'approved_at' => 'datetime',
        'executed_at' => 'datetime',
        'cancelled_at' => 'datetime',
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

    public function settlementPolicy(): BelongsTo
    {
        return $this->belongsTo(WorkTimeSettlementPolicy::class, 'settlement_policy_id');
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    public function reversals(): HasMany
    {
        return $this->hasMany(self::class, 'reversal_of_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function executedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executed_by');
    }

    public function cancelledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function isCredit(): bool
    {
        return $this->minutes > 0;
    }

    public function isDebit(): bool
    {
        return $this->minutes < 0;
    }

    public function formattedMinutes(): string
    {
        $sign = $this->minutes < 0 ? '-' : '+';
        $abs = abs($this->minutes);
        $hours = intdiv($abs, 60);
        $minutes = $abs % 60;

        return sprintf('%s%02d:%02d', $sign, $hours, $minutes);
    }

    public function scopeForEmployee(Builder $query, int $employeeId): Builder
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopeForPeriod(Builder $query, string $referencePeriod): Builder
    {
        return $query->where('reference_period', $referencePeriod);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', WorkTimeSettlementStatus::Pending);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', WorkTimeSettlementStatus::Approved);
    }

    public function scopeExecuted(Builder $query): Builder
    {
        return $query->where('status', WorkTimeSettlementStatus::Executed);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotIn('status', [
            WorkTimeSettlementStatus::Cancelled,
            WorkTimeSettlementStatus::Reversed,
        ]);
    }
}
