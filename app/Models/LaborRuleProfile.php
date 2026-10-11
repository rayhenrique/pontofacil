<?php

namespace App\Models;

use App\Enums\LaborRuleApprovalStatus;
use App\Enums\LegalRegime;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class LaborRuleProfile extends Model
{
    protected $fillable = [
        'name',
        'code',
        'legal_regime',
        'jurisdiction',
        'legal_reference',
        'effective_from',
        'effective_until',
        'approval_status',
        'approved_by',
        'approved_at',
        'version',
        'description',
        'configuration',
    ];

    protected $casts = [
        'legal_regime' => LegalRegime::class,
        'approval_status' => LaborRuleApprovalStatus::class,
        'effective_from' => 'date',
        'effective_until' => 'date',
        'approved_at' => 'datetime',
        'version' => 'integer',
        'configuration' => 'array',
    ];

    protected $attributes = [
        'jurisdiction' => 'federal',
        'approval_status' => 'approved',
        'version' => 1,
        'configuration' => '[]',
    ];

    protected static function booted(): void
    {
        static::saving(function (LaborRuleProfile $profile) {
            $profile->validateLegalConsistency();
        });
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    /**
     * Valida conformidade das configurações com as fontes jurídicas oficiais.
     * Impede parâmetros arbitrários que violem pisos normativos da CLT, CF e Lei 8.112.
     *
     * @throws ValidationException
     */
    public function validateLegalConsistency(): void
    {
        if (empty(trim((string) $this->legal_reference))) {
            throw ValidationException::withMessages([
                'legal_reference' => 'Todo perfil de regras jurídicas deve possuir fundamentação legal expressa.',
            ]);
        }

        $config = $this->configuration ?? [];
        $regime = $this->legal_regime instanceof LegalRegime ? $this->legal_regime : LegalRegime::tryFrom((string) $this->legal_regime);

        if ($regime === LegalRegime::CLT) {
            $percentage = $config['additional_percentage'] ?? null;
            if ($percentage !== null && (float) $percentage < 20.0) {
                throw ValidationException::withMessages([
                    'configuration.additional_percentage' => 'O adicional noturno para regime CLT não pode ser inferior a 20% (CLT, Art. 73 caput).',
                ]);
            }

            $reducedHourSeconds = $config['reduced_hour_seconds'] ?? 3150;
            if (! empty($config['apply_reduced_hour']) && (int) $reducedHourSeconds > 3150) {
                throw ValidationException::withMessages([
                    'configuration.reduced_hour_seconds' => 'A duração da hora noturna na CLT não pode ser superior a 52min30s (3.150s) para trabalho urbano (CLT, Art. 73, § 1º).',
                ]);
            }
        }

        if ($regime === LegalRegime::FederalStatutory) {
            $percentage = $config['additional_percentage'] ?? null;
            if ($percentage !== null && (float) $percentage < 25.0) {
                throw ValidationException::withMessages([
                    'configuration.additional_percentage' => 'O adicional noturno para servidor público federal não pode ser inferior a 25% (Lei 8.112/1990, Art. 75).',
                ]);
            }

            $reducedHourSeconds = $config['reduced_hour_seconds'] ?? 3150;
            if (! empty($config['apply_reduced_hour']) && (int) $reducedHourSeconds > 3150) {
                throw ValidationException::withMessages([
                    'configuration.reduced_hour_seconds' => 'A hora noturna na Lei 8.112/1990 é de 52min30s (3.150s) (Lei 8.112/1990, Art. 75).',
                ]);
            }
        }
    }

    public function isApproved(): bool
    {
        return $this->approval_status === LaborRuleApprovalStatus::Approved;
    }

    public function isEffectiveAt(CarbonInterface|string $date): bool
    {
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

    public function getNightStartTime(): string
    {
        return $this->configuration['night_start_time'] ?? '22:00';
    }

    public function getNightEndTime(): string
    {
        return $this->configuration['night_end_time'] ?? '05:00';
    }

    public function getAdditionalPercentage(): ?float
    {
        $val = $this->configuration['additional_percentage'] ?? null;

        return $val !== null ? (float) $val : null;
    }

    public function getReducedHourSeconds(): int
    {
        return (int) ($this->configuration['reduced_hour_seconds'] ?? 3150);
    }

    public function shouldApplyReducedHour(): bool
    {
        return ! empty($this->configuration['apply_reduced_hour']);
    }

    public function shouldApplyExtension(): bool
    {
        return ! empty($this->configuration['apply_extension']);
    }

    public function shouldScale12x36CompensateExtension(): bool
    {
        return ! empty($this->configuration['scale_12x36_compensates_extension']);
    }

    public function shouldScale12x36CompensateHolidays(): bool
    {
        return ! empty($this->configuration['scale_12x36_compensates_holidays']);
    }

    public function getHolidayTreatment(): string
    {
        return (string) ($this->configuration['holiday_treatment'] ?? 'normal');
    }

    public function isModalityAllowed(string $modality): bool
    {
        $allowed = $this->configuration['allowed_modalities'] ?? ['standard'];

        return in_array($modality, $allowed, true);
    }

    /**
     * Retorna snapshot imutável para auditoria e congelamento de apurações PTRP.
     */
    public function snapshot(): array
    {
        return [
            'profile_id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'legal_regime' => $this->legal_regime?->value ?? (string) $this->legal_regime,
            'jurisdiction' => $this->jurisdiction,
            'legal_reference' => $this->legal_reference,
            'version' => $this->version,
            'effective_from' => $this->effective_from->format('Y-m-d'),
            'effective_until' => $this->effective_until?->format('Y-m-d'),
            'approval_status' => $this->approval_status?->value ?? (string) $this->approval_status,
            'configuration' => $this->configuration ?? [],
        ];
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('approval_status', LaborRuleApprovalStatus::Approved->value);
    }

    public function scopeActiveAt(Builder $query, CarbonInterface|string $date): Builder
    {
        $dateStr = is_string($date) ? $date : $date->format('Y-m-d');

        return $query->where('effective_from', '<=', $dateStr)
            ->where(function (Builder $q) use ($dateStr) {
                $q->whereNull('effective_until')
                    ->orWhere('effective_until', '>=', $dateStr);
            });
    }

    public function scopeForRegime(Builder $query, LegalRegime|string $regime): Builder
    {
        $val = $regime instanceof LegalRegime ? $regime->value : $regime;

        return $query->where('legal_regime', $val);
    }

    /**
     * Cria ou retorna perfil oficial para CLT Urbana Geral (CLT Art. 59-A e 73).
     */
    public static function createStandardCltProfile(array $attributes = []): self
    {
        return self::firstOrCreate(
            ['code' => 'clt_urban_standard'],
            array_merge([
                'name' => 'CLT Urbana Geral (Arts. 59-A e 73)',
                'legal_regime' => LegalRegime::CLT,
                'jurisdiction' => 'federal',
                'legal_reference' => 'CLT, artigos 59-A e 73; Súmula 60 TST; CF/88, art. 7º, IX',
                'effective_from' => '1943-05-01',
                'approval_status' => LaborRuleApprovalStatus::Approved,
                'version' => 1,
                'description' => 'Perfil geral CLT urbana: adicional noturno de 20%, hora de 52m30s, janela das 22h às 05h, prorrogação Súmula 60 TST e compensação da prorrogação em 12x36 (Art. 59-A).',
                'configuration' => [
                    'night_start_time' => '22:00',
                    'night_end_time' => '05:00',
                    'additional_percentage' => 20.0,
                    'reduced_hour_seconds' => 3150,
                    'apply_reduced_hour' => true,
                    'apply_extension' => true,
                    'scale_12x36_compensates_extension' => true,
                    'scale_12x36_compensates_holidays' => true,
                    'holiday_treatment' => 'scale_offset',
                    'deduct_break_from_night' => true,
                    'allowed_modalities' => ['standard', '12x36', '6x1'],
                ],
            ], $attributes)
        );
    }

    /**
     * Cria ou retorna perfil oficial para Servidor Federal (Lei 8.112/1990 e Lei 15.367/2026).
     */
    public static function createFederalStatutoryProfile(array $attributes = []): self
    {
        return self::firstOrCreate(
            ['code' => 'federal_statutory_8112'],
            array_merge([
                'name' => 'Servidor Público Federal (Lei 8.112/1990 e Lei 15.367/2026)',
                'legal_regime' => LegalRegime::FederalStatutory,
                'jurisdiction' => 'federal',
                'legal_reference' => 'Lei nº 8.112/1990, art. 75; Lei nº 15.367/2026; CF/88, art. 39, § 3º',
                'effective_from' => '1990-12-11',
                'approval_status' => LaborRuleApprovalStatus::Approved,
                'version' => 1,
                'description' => 'Estatuto dos Servidores Públicos Civis da União: adicional noturno de 25%, hora de 52m30s, janela das 22h às 05h.',
                'configuration' => [
                    'night_start_time' => '22:00',
                    'night_end_time' => '05:00',
                    'additional_percentage' => 25.0,
                    'reduced_hour_seconds' => 3150,
                    'apply_reduced_hour' => true,
                    'apply_extension' => true,
                    'scale_12x36_compensates_extension' => false,
                    'scale_12x36_compensates_holidays' => false,
                    'holiday_treatment' => 'normal',
                    'deduct_break_from_night' => true,
                    'allowed_modalities' => ['standard', '12x36', '24x72', 'shift_turn'],
                ],
            ], $attributes)
        );
    }

    /**
     * Cria perfil estatutário municipal ou estadual parametrizado.
     */
    public static function createMunicipalStatutoryProfile(
        string $name,
        string $code,
        string $legalReference,
        array $config = [],
        array $attributes = []
    ): self {
        return self::create(array_merge([
            'name' => $name,
            'code' => $code,
            'legal_regime' => LegalRegime::MunicipalStatutory,
            'jurisdiction' => 'municipal',
            'legal_reference' => $legalReference,
            'effective_from' => '2020-01-01',
            'approval_status' => LaborRuleApprovalStatus::Approved,
            'version' => 1,
            'description' => 'Perfil de estatuto municipal parametrizado.',
            'configuration' => array_merge([
                'night_start_time' => '22:00',
                'night_end_time' => '05:00',
                'additional_percentage' => 20.0,
                'reduced_hour_seconds' => 3150,
                'apply_reduced_hour' => true,
                'apply_extension' => true,
                'scale_12x36_compensates_extension' => false,
                'scale_12x36_compensates_holidays' => false,
                'holiday_treatment' => 'normal',
                'deduct_break_from_night' => true,
                'allowed_modalities' => ['standard', '12x36', '24x72'],
            ], $config),
        ], $attributes));
    }
}
