<?php

namespace App\Models;

use App\Enums\LegalRegime;
use App\Enums\WorkScheduleModality;
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

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(WorkScheduleAssignment::class);
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
