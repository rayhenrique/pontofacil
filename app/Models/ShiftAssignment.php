<?php

namespace App\Models;

use App\Enums\ShiftOrigin;
use App\Enums\ShiftStatus;
use App\Enums\ShiftType;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ShiftAssignment extends Model
{
    protected $fillable = [
        'shift_code',
        'employee_id',
        'work_schedule_assignment_id',
        'work_schedule_id',
        'start_at_utc',
        'end_at_utc',
        'start_at_local',
        'end_at_local',
        'timezone',
        'break_minutes',
        'expected_work_minutes',
        'shift_type',
        'origin',
        'status',
        'is_day_off',
        'is_night_shift',
        'crosses_midnight',
        'reason',
        'assigned_by',
        'swapped_with_employee_id',
        'notes',
    ];

    protected $casts = [
        'shift_type' => ShiftType::class,
        'origin' => ShiftOrigin::class,
        'status' => ShiftStatus::class,
        'start_at_utc' => 'datetime',
        'end_at_utc' => 'datetime',
        'start_at_local' => 'datetime',
        'end_at_local' => 'datetime',
        'is_day_off' => 'boolean',
        'is_night_shift' => 'boolean',
        'crosses_midnight' => 'boolean',
        'break_minutes' => 'integer',
        'expected_work_minutes' => 'integer',
    ];

    protected $attributes = [
        'timezone' => 'America/Sao_Paulo',
        'break_minutes' => 0,
        'expected_work_minutes' => 0,
        'shift_type' => 'regular',
        'origin' => 'generated_cycle',
        'status' => 'scheduled',
        'is_day_off' => false,
        'is_night_shift' => false,
        'crosses_midnight' => false,
    ];

    protected static function booted(): void
    {
        static::creating(function (ShiftAssignment $shift) {
            if (empty($shift->shift_code)) {
                $shift->shift_code = (string) Str::uuid();
            }
        });
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function workScheduleAssignment(): BelongsTo
    {
        return $this->belongsTo(WorkScheduleAssignment::class);
    }

    public function workSchedule(): BelongsTo
    {
        return $this->belongsTo(WorkSchedule::class);
    }

    public function assignedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function swappedWithEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'swapped_with_employee_id');
    }

    /**
     * Duração bruta da permanência em minutos.
     */
    public function durationInMinutes(): int
    {
        return (int) $this->start_at_local->diffInMinutes($this->end_at_local);
    }

    /**
     * Duração bruta da permanência em horas.
     */
    public function durationInHours(): float
    {
        return round($this->durationInMinutes() / 60, 2);
    }

    /**
     * Indica se o plantão é de trabalho efetivo (não é folga de escala).
     */
    public function isWorkShift(): bool
    {
        return ! $this->is_day_off;
    }

    /**
     * Verifica se o plantão sobrepõe outro período de tempo.
     */
    public function overlapsWith(CarbonInterface $start, CarbonInterface $end): bool
    {
        return $this->start_at_utc->lt($end) && $this->end_at_utc->gt($start);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [ShiftStatus::Scheduled, ShiftStatus::Confirmed, ShiftStatus::Completed]);
    }

    public function scopeForEmployee(Builder $query, int $employeeId): Builder
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopeForPeriod(Builder $query, CarbonInterface|string $from, CarbonInterface|string $to): Builder
    {
        $fromStr = is_string($from) ? $from : $from->format('Y-m-d H:i:s');
        $toStr = is_string($to) ? $to : $to->format('Y-m-d H:i:s');

        return $query->where(function (Builder $q) use ($fromStr, $toStr) {
            $q->whereBetween('start_at_local', [$fromStr, $toStr])
                ->orWhereBetween('end_at_local', [$fromStr, $toStr])
                ->orWhere(function (Builder $sub) use ($fromStr, $toStr) {
                    $sub->where('start_at_local', '<=', $fromStr)
                        ->where('end_at_local', '>=', $toStr);
                });
        });
    }
}
