<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClosedPeriod extends Model
{
    protected $fillable = [
        'year',
        'month',
        'policy_id',
        'policy_snapshot',
        'snapshot_version',
        'snapshot_hash',
        'employees_count',
        'punches_count',
        'treatments_count',
        'generated_at',
        'status',
        'closed_by',
        'closed_at',
        'reopened_by',
        'reopened_at',
        'reopen_reason',
        'notes',
    ];

    protected $casts = [
        'year' => 'integer',
        'month' => 'integer',
        'snapshot_version' => 'integer',
        'employees_count' => 'integer',
        'punches_count' => 'integer',
        'treatments_count' => 'integer',
        'policy_snapshot' => 'array',
        'closed_at' => 'datetime',
        'generated_at' => 'datetime',
        'reopened_at' => 'datetime',
    ];

    public function policy(): BelongsTo
    {
        return $this->belongsTo(TimeBankPolicy::class, 'policy_id');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function reopenedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reopened_by');
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(ClosedPeriodEmployeeSnapshot::class, 'closed_period_id');
    }

    public function currentSnapshots(): HasMany
    {
        return $this->hasMany(ClosedPeriodEmployeeSnapshot::class, 'closed_period_id')
            ->where('version', $this->snapshot_version);
    }

    public static function isClosed(int $year, int $month): bool
    {
        return self::where('year', $year)
            ->where('month', $month)
            ->where('status', 'closed')
            ->exists();
    }

    public static function findForPeriod(int $year, int $month): ?self
    {
        return self::where('year', $year)
            ->where('month', $month)
            ->first();
    }

    public function isReopened(): bool
    {
        return $this->status === 'reopened';
    }
}
