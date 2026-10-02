<?php

namespace App\Models;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TimeBankAccount extends Model
{
    protected $fillable = [
        'employee_id',
        'employment_id',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->employment_id) && ! empty($model->employee_id)) {
                $model->employment_id = $model->employee_id;
            }
        });
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function employment(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(TimeBankTransaction::class)->orderBy('reference_date', 'asc')->orderBy('created_at', 'asc');
    }

    /**
     * Regra Fundamental: O saldo é SEMPRE calculado via SUM no ledger de transações.
     * Nunca armazenado como um número sobrescrito.
     */
    public function currentBalance(): int
    {
        return (int) $this->transactions()->sum('minutes');
    }

    public function balanceUntil(CarbonInterface|string $date): int
    {
        $dateStr = $date instanceof CarbonInterface ? $date->format('Y-m-d') : Carbon::parse($date)->format('Y-m-d');

        return (int) $this->transactions()
            ->where('reference_date', '<=', $dateStr)
            ->sum('minutes');
    }

    public static function formatMinutes(int $minutes): string
    {
        $sign = $minutes < 0 ? '-' : '+';
        $abs = abs($minutes);
        $hours = intdiv($abs, 60);
        $rem = $abs % 60;

        return sprintf('%s%02d:%02d', $sign, $hours, $rem);
    }

    public static function getOrCreateForEmployee(Employee|int $employee): self
    {
        $employeeId = $employee instanceof Employee ? $employee->id : $employee;

        return self::firstOrCreate(
            ['employee_id' => $employeeId],
            [
                'employment_id' => $employeeId,
                'active' => true,
            ]
        );
    }
}
