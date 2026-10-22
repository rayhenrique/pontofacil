<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeLaborRuleProfileAssignment extends Model
{
    protected $fillable = [
        'employee_id',
        'labor_rule_profile_id',
        'effective_from',
        'effective_until',
        'reason',
        'assigned_by',
        'notes',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_until' => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function laborRuleProfile(): BelongsTo
    {
        return $this->belongsTo(LaborRuleProfile::class);
    }

    public function assignedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /**
     * Scope para filtrar atribuição ativa na data especificada.
     */
    public function scopeActiveAt(Builder $query, CarbonInterface|string $date): Builder
    {
        $dateStr = is_string($date) ? $date : $date->format('Y-m-d');

        return $query->where('effective_from', '<=', $dateStr)
            ->where(function (Builder $q) use ($dateStr) {
                $q->whereNull('effective_until')
                    ->orWhere('effective_until', '>=', $dateStr);
            });
    }

    /**
     * Scope para filtrar atribuições de um colaborador específico.
     */
    public function scopeForEmployee(Builder $query, int $employeeId): Builder
    {
        return $query->where('employee_id', $employeeId);
    }

    /**
     * Verifica se o período desta atribuição conflita com um intervalo fornecido.
     */
    public function conflictsWith(CarbonInterface|string $from, CarbonInterface|string|null $until): bool
    {
        $fromStr = is_string($from) ? $from : $from->format('Y-m-d');
        $untilStr = $until ? (is_string($until) ? $until : $until->format('Y-m-d')) : null;

        $thisFrom = $this->effective_from->format('Y-m-d');
        $thisUntil = $this->effective_until?->format('Y-m-d');

        if ($untilStr === null) {
            if ($thisUntil === null) {
                return true;
            }

            return $fromStr <= $thisUntil;
        }

        if ($thisUntil === null) {
            return $untilStr >= $thisFrom;
        }

        return $fromStr <= $thisUntil && $untilStr >= $thisFrom;
    }
}
