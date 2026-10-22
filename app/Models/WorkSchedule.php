<?php

namespace App\Models;

use App\Enums\LegalRegime;
use App\Enums\WorkScheduleModality;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkSchedule extends Model
{
    protected $fillable = [
        'code',
        'name',
        'description',
        'modality',
        'cycle_days',
        'tolerance_minutes',
        'daily_tolerance_minutes',
        'expected_daily_minutes',
        'expected_weekly_minutes',
        'expected_monthly_minutes',
        'timezone',
        'requires_legal_authorization',
        'allowed_legal_regimes',
        'schedule_data',
        'cycle_data',
        'active',
    ];

    protected $casts = [
        'modality' => WorkScheduleModality::class,
        'cycle_days' => 'integer',
        'tolerance_minutes' => 'integer',
        'daily_tolerance_minutes' => 'integer',
        'expected_daily_minutes' => 'integer',
        'expected_weekly_minutes' => 'integer',
        'expected_monthly_minutes' => 'integer',
        'requires_legal_authorization' => 'boolean',
        'allowed_legal_regimes' => 'array',
        'schedule_data' => 'array',
        'cycle_data' => 'array',
        'active' => 'boolean',
    ];

    protected $attributes = [
        'schedule_data' => '[]',
        'cycle_data' => '[]',
        'tolerance_minutes' => 5,
        'daily_tolerance_minutes' => 10,
        'timezone' => 'America/Sao_Paulo',
        'active' => true,
    ];

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function getDailyMinutesAttribute(): int
    {
        return (int) ($this->expected_daily_minutes ?? 0);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(WorkScheduleAssignment::class);
    }

    public function shiftAssignments(): HasMany
    {
        return $this->hasMany(ShiftAssignment::class);
    }

    /**
     * Verifica se o regime jurídico informado possui autorização para este modelo de escala.
     */
    public function isAuthorizedForRegime(LegalRegime|string|null $regime): bool
    {
        if (! $this->requires_legal_authorization) {
            return true;
        }

        if ($regime === null) {
            return false;
        }

        $regimeValue = $regime instanceof LegalRegime ? $regime->value : $regime;
        $allowed = $this->allowed_legal_regimes ?? [];

        return in_array($regimeValue, $allowed, true);
    }

    /**
     * Valida a autorização normativa da escala especial para o colaborador.
     *
     * Retorna null quando a atribuição é permitida ou uma mensagem
     * explicativa (sem jargão) quando irregular. Plantões 12×36 e 24×72
     * nunca são autorizados apenas pelo vínculo: exigem lista de regimes
     * da escala e perfil normativo aprovado com a modalidade permitida,
     * configurado pelo RH. Nenhuma norma municipal ou estadual é presumida.
     */
    public function modalityAuthorizationErrorFor(Employee $employee, ?CarbonInterface $date = null): ?string
    {
        $modality = $this->modality;
        $modalityName = $modality?->label() ?? 'Especial';
        $targetDate = $date ?? Carbon::today();
        $dateStr = $targetDate->format('d/m/Y');

        if ($this->requires_legal_authorization && ! $this->isAuthorizedForRegime($employee->legal_regime)) {
            $regimeLabel = $employee->legal_regime?->label() ?? 'não informado';

            return "A escala '{$this->name}' ({$modalityName}) requer autorização normativa específica não habilitada para o regime do colaborador ({$regimeLabel}).";
        }

        $modalityKey = match ($modality) {
            WorkScheduleModality::TwelveByThirtySix => '12x36',
            WorkScheduleModality::TwentyFourBySeventyTwo => '24x72',
            default => null,
        };

        if ($modalityKey === null) {
            return null;
        }

        $profile = $employee->getLaborRuleProfileForDate($targetDate);

        if ($profile === null) {
            $regimeName = $employee->legal_regime?->label() ?? 'não informado';

            // Diagnóstico detalhado caso haja um perfil diretamente atribuído com pendências
            $assigned = $employee->labor_rule_profile_id ? $employee->laborRuleProfile : null;
            if ($assigned) {
                if ($assigned->isDraft()) {
                    return "A escala '{$this->name}' ({$modalityName}) exige perfil normativo aprovado. O perfil '{$assigned->name}' atribuído ao colaborador está em rascunho e ainda não foi aprovado pelo RH em Configurações → Perfis Normativos.";
                }
                if ($assigned->isRevoked()) {
                    return "A escala '{$this->name}' ({$modalityName}) exige perfil normativo ativo. O perfil '{$assigned->name}' foi revogado e não autoriza novas atribuições em Configurações → Perfis Normativos.";
                }
                if (! $assigned->isEffectiveAt($targetDate)) {
                    return "A escala '{$this->name}' ({$modalityName}) exige perfil normativo vigente. O perfil '{$assigned->name}' não é válido para a data selecionada ({$dateStr}) em Configurações → Perfis Normativos.";
                }
                if ($assigned->legal_regime !== $employee->legal_regime) {
                    return "A escala '{$this->name}' ({$modalityName}) exige perfil compatível. O perfil '{$assigned->name}' é do regime {$assigned->legal_regime?->label()}, incompatível com o colaborador ({$regimeName}) em Configurações → Perfis Normativos.";
                }
            }

            return "Este funcionário está cadastrado como {$regimeName} e não possui um perfil normativo aprovado que autorize o plantão {$modalityKey} nesta data ({$dateStr}). Peça ao RH para configurar a norma aplicável em Configurações → Perfis Normativos — o registro de ponto segue liberado.";
        }

        if (! $profile->isModalityAllowed($modalityKey)) {
            return "A escala '{$this->name}' ({$modalityName}) não está autorizada no perfil normativo '{$profile->name}' vigente para o regime do colaborador. Peça ao RH para configurar a norma em Configurações → Perfis Normativos.";
        }

        return null;
    }

    /**
     * Retorna a configuração de horários para o dia da semana fornecido (0 = Domingo ... 6 = Sábado).
     */
    public function getScheduleForDay(int $dayOfWeek): array
    {
        $days = $this->schedule_data ?? [];

        return $days[$dayOfWeek] ?? [
            'is_work_day' => in_array($dayOfWeek, [1, 2, 3, 4, 5]),
            'is_day_off' => in_array($dayOfWeek, [0, 6]),
            'periods' => in_array($dayOfWeek, [1, 2, 3, 4, 5]) ? [
                ['start' => '08:00', 'end' => '12:00'],
                ['start' => '14:00', 'end' => '18:00'],
            ] : [],
            'break_minutes' => in_array($dayOfWeek, [1, 2, 3, 4, 5]) ? 120 : 0,
            'expected_minutes' => in_array($dayOfWeek, [1, 2, 3, 4, 5]) ? 480 : 0,
        ];
    }

    public function isWorkDay(int $dayOfWeek): bool
    {
        $day = $this->getScheduleForDay($dayOfWeek);

        return ! empty($day['is_work_day']);
    }

    public function isDayOff(int $dayOfWeek): bool
    {
        $day = $this->getScheduleForDay($dayOfWeek);

        return ! empty($day['is_day_off']);
    }

    public function expectedMinutesForDate(CarbonInterface $date): int
    {
        $day = $this->getScheduleForDay($date->dayOfWeek);

        return (int) ($day['expected_minutes'] ?? 0);
    }

    public function calculateWeeklyMinutes(): int
    {
        if ($this->modality === WorkScheduleModality::TwelveByThirtySix) {
            // Média de 3,5 plantões por semana (42h ou 36h de trabalho efetivo por semana)
            $daily = $this->expected_daily_minutes ?: 720;

            return (int) round($daily * 3.5);
        }

        if ($this->modality === WorkScheduleModality::TwentyFourBySeventyTwo) {
            // 1 plantão a cada 4 dias: média de 1,75 plantões por semana
            $daily = $this->expected_daily_minutes ?: 1440;

            return (int) round($daily * 1.75);
        }

        $sum = 0;
        for ($i = 0; $i <= 6; $i++) {
            $day = $this->getScheduleForDay($i);
            $sum += (int) ($day['expected_minutes'] ?? 0);
        }

        return $sum ?: ($this->expected_weekly_minutes ?: 0);
    }

    /**
     * Calcula a carga horária prevista real no mês a partir do calendário real da competência.
     * Nunca presume 200h nem valores arbitrários.
     */
    public function calculateMonthlyExpectedMinutes(int $year, int $month, ?CarbonInterface $anchorDate = null): int
    {
        $startDate = Carbon::createFromDate($year, $month, 1)->startOfDay();
        $daysInMonth = $startDate->daysInMonth;
        $totalMinutes = 0;

        if ($this->modality === WorkScheduleModality::TwelveByThirtySix || $this->modality === WorkScheduleModality::TwentyFourBySeventyTwo) {
            $anchorStr = $this->cycle_data['anchor_date'] ?? null;
            $anchor = $anchorDate ? $anchorDate->copy()->startOfDay() : ($anchorStr ? Carbon::parse($anchorStr)->startOfDay() : $startDate->copy());
            $cycleLength = $this->modality === WorkScheduleModality::TwelveByThirtySix ? 2 : 4;
            $workMinutes = (int) ($this->expected_daily_minutes ?: ($this->modality === WorkScheduleModality::TwelveByThirtySix ? 720 : 1440));

            for ($day = 1; $day <= $daysInMonth; $day++) {
                $curDate = Carbon::createFromDate($year, $month, $day)->startOfDay();
                $diffDays = (int) $anchor->diffInDays($curDate, false);
                $cycleIndex = ($diffDays % $cycleLength + $cycleLength) % $cycleLength;

                if ($cycleIndex === 0) {
                    $totalMinutes += $workMinutes;
                }
            }

            return $totalMinutes;
        }

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $curDate = Carbon::createFromDate($year, $month, $day);
            $dayOfWeek = $curDate->dayOfWeek;
            $dayConfig = $this->getScheduleForDay($dayOfWeek);

            if (! empty($dayConfig['is_work_day'])) {
                $totalMinutes += (int) ($dayConfig['expected_minutes'] ?? 0);
            }
        }

        return $totalMinutes;
    }

    /**
     * Factory padrão de jornada de 40h semanais (Segunda a Sexta, 08h-12h e 14h-18h).
     */
    public static function createDefault40h(): self
    {
        $days = [];
        for ($i = 0; $i <= 6; $i++) {
            $isWork = in_array($i, [1, 2, 3, 4, 5]);
            $days[$i] = [
                'day_name' => match ($i) {
                    0 => 'Domingo',
                    1 => 'Segunda-feira',
                    2 => 'Terça-feira',
                    3 => 'Quarta-feira',
                    4 => 'Quinta-feira',
                    5 => 'Sexta-feira',
                    6 => 'Sábado',
                },
                'is_work_day' => $isWork,
                'is_day_off' => ! $isWork,
                'periods' => $isWork ? [
                    ['start' => '08:00', 'end' => '12:00'],
                    ['start' => '14:00', 'end' => '18:00'],
                ] : [],
                'break_minutes' => $isWork ? 120 : 0,
                'expected_minutes' => $isWork ? 480 : 0,
            ];
        }

        return self::create([
            'name' => 'Jornada Administrativa Padrão 40h',
            'description' => 'Segunda a Sexta das 08:00 às 12:00 e das 14:00 às 18:00 com 2h de intervalo intrajornada',
            'tolerance_minutes' => 5,
            'daily_tolerance_minutes' => 10,
            'schedule_data' => $days,
            'active' => true,
        ]);
    }
}
