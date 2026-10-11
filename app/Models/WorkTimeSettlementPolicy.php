<?php

namespace App\Models;

use App\Domain\Settlement\Enums\SettlementModality;
use App\Domain\Settlement\Enums\SettlementPolicyApprovalStatus;
use App\Enums\LegalRegime;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class WorkTimeSettlementPolicy extends Model
{
    protected $fillable = [
        'company_id',
        'establishment_id',
        'employee_id',
        'legal_regime',
        'name',
        'modality',
        'effective_from',
        'effective_until',
        'legal_framework',
        'compensation_terms',
        'carry_over_terms',
        'hybrid_rules',
        'approval_status',
        'approved_by',
        'approved_at',
        'status',
        'created_by',
        'notes',
    ];

    protected $casts = [
        'legal_regime' => LegalRegime::class,
        'modality' => SettlementModality::class,
        'effective_from' => 'date',
        'effective_until' => 'date',
        'compensation_terms' => 'array',
        'carry_over_terms' => 'array',
        'hybrid_rules' => 'array',
        'approval_status' => SettlementPolicyApprovalStatus::class,
        'approved_at' => 'datetime',
    ];

    protected $attributes = [
        'modality' => 'cumulative_bank',
        'approval_status' => 'approved',
        'status' => 'active',
        'compensation_terms' => '[]',
        'carry_over_terms' => '[]',
        'hybrid_rules' => '[]',
    ];

    protected static function booted(): void
    {
        static::saving(function (WorkTimeSettlementPolicy $policy) {
            $policy->validateLegalConsistency();
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function establishment(): BelongsTo
    {
        return $this->belongsTo(Establishment::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Valida consistência jurídica das regras com a CLT, CF/88 e estatutos oficiais.
     * Impede aplicar regras privadas da CLT a estatutários sem autorização expressa.
     *
     * @throws ValidationException
     */
    public function validateLegalConsistency(): void
    {
        if (empty(trim((string) $this->legal_framework))) {
            throw ValidationException::withMessages([
                'legal_framework' => 'Toda política de compensação deve possuir fundamentação normativa ou legal expressa.',
            ]);
        }

        $regime = $this->legal_regime instanceof LegalRegime ? $this->legal_regime : LegalRegime::tryFrom((string) $this->legal_regime);

        // Se o regime for estatutário, vedar fundamentação unicamente em CLT privada
        if ($regime && $regime->isStatutory()) {
            $lowerFramework = strtolower((string) $this->legal_framework);
            if (str_contains($lowerFramework, 'clt') && ! str_contains($lowerFramework, 'lei') && ! str_contains($lowerFramework, 'estatuto') && ! str_contains($lowerFramework, 'decreto')) {
                throw ValidationException::withMessages([
                    'legal_framework' => 'Servidores estatutários não são regidos pela CLT. A política deve indicar lei ou estatuto do ente federativo aplicável.',
                ]);
            }
        }
    }

    public function isApproved(): bool
    {
        return $this->approval_status === SettlementPolicyApprovalStatus::Approved;
    }

    public function isActiveAt(CarbonInterface|string $date): bool
    {
        if ($this->status !== 'active' || ! $this->isApproved()) {
            return false;
        }

        $dateStr = is_string($date) ? $date : $date->format('Y-m-d');
        $fromStr = $this->effective_from->format('Y-m-d');
        $untilStr = $this->effective_until?->format('Y-m-d');

        if ($dateStr < $fromStr) {
            return false;
        }

        if ($untilStr !== null && $dateStr > $untilStr) {
            return false;
        }

        return true;
    }

    public function operatesTimeBank(): bool
    {
        return $this->modality->operatesTimeBankLedger();
    }

    public function getMaxCompensationMonths(): int
    {
        return (int) ($this->compensation_terms['max_months'] ?? 6);
    }

    public function getBankMonthlyLimitMinutes(): ?int
    {
        $val = $this->hybrid_rules['bank_monthly_limit_minutes'] ?? null;

        return $val !== null ? (int) $val : null;
    }

    public function getScopeType(): string
    {
        if ($this->employee_id) {
            return 'employee';
        }
        if ($this->establishment_id) {
            return 'establishment';
        }

        return 'company';
    }

    public function getScopeLabel(): string
    {
        return match ($this->getScopeType()) {
            'employee' => 'Vínculo / Colaborador Individual',
            'establishment' => 'Estabelecimento / Unidade',
            'company' => 'Empresa (Geral / Instalação)',
        };
    }

    public function scopeActiveAt(Builder $query, CarbonInterface|string $date): Builder
    {
        $dateStr = is_string($date) ? $date : $date->format('Y-m-d');

        return $query->where('status', 'active')
            ->where('effective_from', '<=', $dateStr)
            ->where(function (Builder $q) use ($dateStr) {
                $q->whereNull('effective_until')
                    ->orWhere('effective_until', '>=', $dateStr);
            });
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('approval_status', SettlementPolicyApprovalStatus::Approved->value);
    }

    public function scopeForEmployee(Builder $query, int $employeeId): Builder
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopeForEstablishment(Builder $query, int $establishmentId): Builder
    {
        return $query->where('establishment_id', $establishmentId)->whereNull('employee_id');
    }

    public function scopeForCompany(Builder $query, ?int $companyId = null): Builder
    {
        return $query->whereNull('employee_id')
            ->whereNull('establishment_id')
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId));
    }

    /**
     * Retorna snapshot imutável para auditoria e congelamento em fechamentos PTRP.
     */
    public function snapshot(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'modality' => $this->modality?->value,
            'modality_label' => $this->modality?->label(),
            'scope' => $this->getScopeType(),
            'legal_regime' => $this->legal_regime?->value,
            'legal_framework' => $this->legal_framework,
            'effective_from' => $this->effective_from?->toDateString(),
            'effective_until' => $this->effective_until?->toDateString(),
            'compensation_terms' => $this->compensation_terms ?? [],
            'carry_over_terms' => $this->carry_over_terms ?? [],
            'hybrid_rules' => $this->hybrid_rules ?? [],
            'approval_status' => $this->approval_status?->value,
        ];
    }
}
