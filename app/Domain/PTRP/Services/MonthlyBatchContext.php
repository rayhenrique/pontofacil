<?php

namespace App\Domain\PTRP\Services;

use App\Domain\Company\Services\CurrentCompany;
use App\Domain\PTRP\Actions\CalculateDailyJourneyAction;
use App\Domain\PTRP\DTOs\CalculatedJourney;
use App\Domain\PTRP\DTOs\DailyJourneyPrefetch;
use App\Domain\PTRP\Enums\TimeBankClosingMode;
use App\Domain\PTRP\Enums\TreatmentEventStatus;
use App\Domain\Settlement\Enums\SettlementModality;
use App\Domain\Settlement\Enums\SettlementPolicyApprovalStatus;
use App\Domain\Settlement\Services\WorkTimeSettlementService;
use App\Enums\CalendarEventScope;
use App\Enums\LegalRegime;
use App\Enums\ShiftStatus;
use App\Models\CalendarEvent;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Establishment;
use App\Models\LaborRuleProfile;
use App\Models\PunchEvent;
use App\Models\ShiftAssignment;
use App\Models\TimeBankPolicy;
use App\Models\TimeEntry;
use App\Models\TreatmentEvent;
use App\Models\WorkSchedule;
use App\Models\WorkScheduleAssignment;
use App\Models\WorkTimeSettlementPolicy;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Contexto de apuração mensal com dados pré-carregados.
 *
 * Carrega uma única vez por competência tudo que o motor PTRP consulta
 * por colaborador/dia (plantões, marcações, tratamentos, legados,
 * escalas, perfis jurídicos, políticas de compensação e calendário) e
 * replica em memória, com semântica idêntica, a resolução diária de
 * cada dependência. Compartilhado pela Conferência Mensal e pelos
 * Relatórios para eliminar cálculos e consultas duplicadas.
 *
 * Somente leitura: nenhum método deste contexto grava no banco,
 * exceto a criação única dos perfis jurídicos padrão (firstOrCreate),
 * que o motor também executaria sob demanda.
 */
class MonthlyBatchContext
{
    /** @var Collection<int, Employee> */
    public readonly Collection $employees;

    public readonly Carbon $startDate;

    public readonly Carbon $endDate;

    public readonly int $daysInMonth;

    /** @var array<int, array<string, ShiftAssignment>> */
    protected array $shiftsByEmployeeDate = [];

    /** @var array<int, array<int, ShiftAssignment>> Todos os plantões pré-carregados (qualquer situação). */
    protected array $allShiftsByEmployee = [];

    /** @var array<int, array<string, Collection>> */
    protected array $punchesByEmployeeDate = [];

    /** @var array<int, array<string, Collection>> */
    protected array $treatmentsByEmployeeDate = [];

    /** @var array<int|string, array<string, Collection>> */
    protected array $legacyByUserDate = [];

    /** @var array<int, Collection<int, WorkScheduleAssignment>> */
    protected array $assignmentsByEmployee = [];

    /** @var array<int, WorkSchedule> */
    protected array $schedulesById = [];

    /** @var Collection<int, LaborRuleProfile> */
    protected Collection $allRuleProfiles;

    /** @var Collection<int, WorkTimeSettlementPolicy> */
    protected Collection $allSettlementPolicies;

    /** @var Collection<int, TimeBankPolicy> */
    protected Collection $allTimeBankPolicies;

    /** @var Collection<int, CalendarEvent> */
    protected Collection $monthCalendarEvents;

    protected ?Establishment $defaultEstablishment = null;

    protected bool $defaultEstablishmentResolved = false;

    protected int $companyCount;

    protected bool $hasSettlementPoliciesTable;

    /** @var array<int, array<string, ?WorkSchedule>> */
    protected array $scheduleCache = [];

    /** @var array<int, array<string, ?LaborRuleProfile>> */
    protected array $ruleProfileCache = [];

    /** @var array<int, array<string, WorkTimeSettlementPolicy>> */
    protected array $settlementPolicyCache = [];

    /** @var array<string, WorkTimeSettlementPolicy> */
    protected array $fallbackPolicyCache = [];

    /** @var array<string, Collection<int, CalendarEvent>> */
    protected array $calendarEventsCache = [];

    /** @var array<int, ?Establishment> */
    protected array $establishmentCache = [];

    protected bool $standardCltResolved = false;

    protected ?LaborRuleProfile $standardCltProfile = null;

    protected bool $federalStatutoryResolved = false;

    protected ?LaborRuleProfile $federalStatutoryProfile = null;

    /**
     * @param  Collection<int, Employee>  $employees  Colaboradores já filtrados por escopo.
     */
    public function __construct(Collection $employees, public readonly int $year, public readonly int $month)
    {
        $models = $employees instanceof \Illuminate\Database\Eloquent\Collection
            ? $employees
            : new \Illuminate\Database\Eloquent\Collection($employees->all());

        $models->loadMissing(['user', 'sector.establishment', 'workSchedule']);

        $this->employees = collect($models->keyBy(fn (Employee $employee) => $employee->id)->all());
        $this->startDate = Carbon::createFromDate($year, $month, 1)->startOfDay();
        $this->endDate = $this->startDate->copy()->endOfMonth();
        $this->daysInMonth = $this->endDate->day;

        $this->companyCount = Company::count();
        $this->hasSettlementPoliciesTable = Schema::hasTable('work_time_settlement_policies');

        $this->preload();
    }

    /**
     * Apura a jornada de um dia usando exclusivamente dados pré-carregados.
     */
    public function journeyFor(Employee $employee, CarbonInterface $date): CalculatedJourney
    {
        $prefetch = $this->prefetchFor($employee, $date);
        $dateStr = $date->format('Y-m-d');

        return app(CalculateDailyJourneyAction::class)->execute(
            $employee,
            $date,
            null,
            null,
            $this->settlementPolicyFor($employee, $date),
            $this->punchesByEmployeeDate[$employee->id][$dateStr] ?? collect(),
            $this->treatmentsByEmployeeDate[$employee->id][$dateStr] ?? collect(),
            null,
            $prefetch,
        );
    }

    /**
     * Monta o pré-carregamento diário para o motor.
     */
    public function prefetchFor(Employee $employee, CarbonInterface $date): DailyJourneyPrefetch
    {
        $dateStr = $date->format('Y-m-d');
        $prevStr = $date->copy()->subDay()->format('Y-m-d');
        $nextStr = $date->copy()->addDay()->format('Y-m-d');

        $prevPunches = $this->punchesByEmployeeDate[$employee->id][$prevStr] ?? collect();
        if ($prevPunches->isEmpty() && $employee->user_id !== null) {
            $prevPunches = $this->legacyByUserDate[$employee->user_id][$prevStr] ?? collect();
        }

        $nextPunches = $this->punchesByEmployeeDate[$employee->id][$nextStr] ?? collect();
        if ($nextPunches->isEmpty() && $employee->user_id !== null) {
            $nextPunches = $this->legacyByUserDate[$employee->user_id][$nextStr] ?? collect();
        }

        $establishment = $this->establishmentFor($employee);

        return new DailyJourneyPrefetch(
            shiftResolved: true,
            shift: $this->shiftsByEmployeeDate[$employee->id][$dateStr] ?? null,
            scheduleResolved: true,
            resolvedSchedule: $this->scheduleFor($employee, $date),
            establishment: $establishment,
            ruleProfileResolved: true,
            ruleProfile: $this->ruleProfileFor($employee, $date),
            legacyResolved: true,
            legacyEntries: $employee->user_id !== null
                ? ($this->legacyByUserDate[$employee->user_id][$dateStr] ?? collect())
                : collect(),
            neighborsResolved: true,
            prevShift: $this->prevShiftFor($employee, $prevStr),
            prevDayPunches: $prevPunches,
            nextDayPunches: $nextPunches,
            calendarEvents: $this->calendarEventsFor($establishment, $dateStr),
        );
    }

    /**
     * Replica Employee::getWorkScheduleForDate() sobre atribuições pré-carregadas.
     */
    public function scheduleFor(Employee $employee, CarbonInterface $date): ?WorkSchedule
    {
        $dateStr = $date->format('Y-m-d');

        if (array_key_exists($dateStr, $this->scheduleCache[$employee->id] ?? [])) {
            return $this->scheduleCache[$employee->id][$dateStr];
        }

        $assignment = collect($this->assignmentsByEmployee[$employee->id] ?? [])
            ->filter(fn (WorkScheduleAssignment $item) => $item->effective_from->format('Y-m-d') <= $dateStr
                && ($item->effective_until === null || $item->effective_until->format('Y-m-d') >= $dateStr))
            ->sortByDesc(fn (WorkScheduleAssignment $item) => $item->effective_from->format('Y-m-d'))
            ->first();

        $schedule = null;
        if ($assignment) {
            $schedule = $this->schedulesById[$assignment->work_schedule_id] ?? null;
        }

        if ($schedule === null) {
            $schedule = $employee->relationLoaded('workSchedule') ? $employee->workSchedule : null;
        }

        return $this->scheduleCache[$employee->id][$dateStr] = $schedule;
    }

    /**
     * Replica Employee::getLaborRuleProfileForDate() sobre perfis pré-carregados.
     */
    public function ruleProfileFor(Employee $employee, CarbonInterface $date): ?LaborRuleProfile
    {
        $dateStr = $date->format('Y-m-d');

        if (array_key_exists($dateStr, $this->ruleProfileCache[$employee->id] ?? [])) {
            return $this->ruleProfileCache[$employee->id][$dateStr];
        }

        $profile = null;

        if ($employee->labor_rule_profile_id) {
            $assigned = $this->allRuleProfiles->firstWhere('id', $employee->labor_rule_profile_id);
            if ($assigned && $assigned->isApproved() && $assigned->isEffectiveAt($date)) {
                $profile = $assigned;
            }
        }

        if ($profile === null && $employee->legal_regime === LegalRegime::CLT) {
            $profile = $this->firstActiveProfileForRegime(LegalRegime::CLT, $date)
                ?? $this->standardCltProfile();
        }

        if ($profile === null && $employee->legal_regime === LegalRegime::FederalStatutory) {
            $profile = $this->firstActiveProfileForRegime(LegalRegime::FederalStatutory, $date)
                ?? $this->federalStatutoryProfile();
        }

        if ($profile === null && $employee->legal_regime?->isStatutory()) {
            $profile = $this->firstActiveProfileForRegime($employee->legal_regime, $date);
        }

        return $this->ruleProfileCache[$employee->id][$dateStr] = $profile;
    }

    /**
     * Replica WorkTimeSettlementService::resolvePolicy() sobre políticas pré-carregadas.
     */
    public function settlementPolicyFor(Employee $employee, CarbonInterface $date): WorkTimeSettlementPolicy
    {
        $dateStr = $date->format('Y-m-d');

        if (isset($this->settlementPolicyCache[$employee->id][$dateStr])) {
            return $this->settlementPolicyCache[$employee->id][$dateStr];
        }

        $policy = $this->resolveSettlementPolicy($employee, $date, $dateStr);

        return $this->settlementPolicyCache[$employee->id][$dateStr] = $policy;
    }

    /**
     * Eventos de calendário aplicáveis a um estabelecimento e dia, já ordenados por prioridade.
     *
     * @return Collection<int, CalendarEvent>
     */
    public function calendarEventsFor(?Establishment $establishment, string $dateStr): Collection
    {
        $key = ($establishment?->id ?? 'null').'|'.$dateStr;

        if (isset($this->calendarEventsCache[$key])) {
            return $this->calendarEventsCache[$key];
        }

        $events = $this->monthCalendarEvents
            ->filter(fn (CalendarEvent $event) => $event->event_date->format('Y-m-d') === $dateStr
                && $this->isEventApplicableTo($event, $establishment))
            ->sortByDesc(fn (CalendarEvent $event) => $this->eventScopePriority($event))
            ->values();

        return $this->calendarEventsCache[$key] = $events;
    }

    /**
     * Estabelecimento do colaborador (relação eager) com fallback memorizado.
     */
    public function establishmentFor(Employee $employee): ?Establishment
    {
        if (array_key_exists($employee->id, $this->establishmentCache)) {
            return $this->establishmentCache[$employee->id];
        }

        $establishment = $employee->sector?->establishment;

        if ($establishment === null) {
            if (! $this->defaultEstablishmentResolved) {
                $this->defaultEstablishment = Establishment::first();
                $this->defaultEstablishmentResolved = true;
            }
            $establishment = $this->defaultEstablishment;
        }

        return $this->establishmentCache[$employee->id] = $establishment;
    }

    /**
     * Marcações brutas de um dia (ordem cronológica).
     *
     * @return Collection<int, mixed>
     */
    public function rawPunchesFor(Employee $employee, string $dateStr): Collection
    {
        return $this->punchesByEmployeeDate[$employee->id][$dateStr] ?? collect();
    }

    /**
     * Tratamentos aprovados de um dia.
     *
     * @return Collection<int, mixed>
     */
    public function approvedTreatmentsFor(Employee $employee, string $dateStr): Collection
    {
        return $this->treatmentsByEmployeeDate[$employee->id][$dateStr] ?? collect();
    }

    /**
     * Quantidade de plantões do colaborador no período da competência.
     *
     * Replica o escopo ShiftAssignment::forPeriod() (sem filtro de situação)
     * sobre os dados pré-carregados.
     */
    public function shiftsCountInPeriod(Employee $employee, CarbonInterface $periodStart, CarbonInterface $periodEnd): int
    {
        $from = $periodStart->format('Y-m-d H:i:s');
        $to = $periodEnd->format('Y-m-d H:i:s');
        $count = 0;

        foreach ($this->allShiftsByEmployee[$employee->id] ?? [] as $shift) {
            $start = $shift->start_at_local->format('Y-m-d H:i:s');
            $end = $shift->end_at_local->format('Y-m-d H:i:s');

            if (($start >= $from && $start <= $to)
                || ($end >= $from && $end <= $to)
                || ($start <= $from && $end >= $to)
            ) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Quantidade de tratamentos aprovados do colaborador na competência.
     */
    public function approvedTreatmentsCountFor(Employee $employee): int
    {
        $count = 0;
        foreach ($this->treatmentsByEmployeeDate[$employee->id] ?? [] as $collection) {
            $count += $collection->count();
        }

        return $count;
    }

    /**
     * Fingerprint determinístico (SHA-256) dos insumos da apuração mensal
     * do servidor: competência, vínculo, marcações, tratamentos, escalas,
     * plantões, calendário e regras efetivamente resolvidas.
     *
     * Estável independentemente da ordem de leitura; contém apenas
     * identificadores, datas e conteúdos normativos — nenhum dado
     * sensível (nomes, CPF) em texto claro.
     */
    public function fingerprintFor(Employee $employee): string
    {
        $payload = [
            'competence' => sprintf('%04d-%02d', $this->year, $this->month),
            'employee' => [
                'id' => $employee->id,
                'user_id' => $employee->user_id,
                'sector_id' => $employee->sector_id,
                'legal_regime' => $employee->legal_regime instanceof LegalRegime
                    ? $employee->legal_regime->value
                    : $employee->legal_regime,
            ],
            'assignments' => [],
            'schedules' => [],
            'shifts' => [],
            'punches' => [],
            'legacy_entries' => [],
            'treatments' => [],
            'calendar_events' => [],
            'rule_profiles' => [],
            'settlement_policies' => [],
        ];

        foreach ($this->assignmentsByEmployee[$employee->id] ?? [] as $assignment) {
            $payload['assignments'][] = [
                'id' => $assignment->id,
                'work_schedule_id' => $assignment->work_schedule_id,
                'effective_from' => $assignment->effective_from->format('Y-m-d'),
                'effective_until' => $assignment->effective_until?->format('Y-m-d'),
            ];
        }
        usort($payload['assignments'], fn ($a, $b) => $a['id'] <=> $b['id']);

        $scheduleIds = [];
        $profileIds = [];
        $policyKeys = [];
        for ($d = 1; $d <= $this->daysInMonth; $d++) {
            $date = Carbon::createFromDate($this->year, $this->month, $d);
            $schedule = $this->scheduleFor($employee, $date);
            if ($schedule !== null) {
                $scheduleIds[$schedule->id] = true;
            }
            $profile = $this->ruleProfileFor($employee, $date);
            if ($profile !== null) {
                $profileIds[$profile->id] = true;
            }
            $policy = $this->settlementPolicyFor($employee, $date);
            $policyKeys[$this->settlementPolicyKey($policy)] = $policy;
        }

        foreach (array_keys($scheduleIds) as $scheduleId) {
            $schedule = $this->schedulesById[$scheduleId] ?? null;
            if ($schedule === null) {
                continue;
            }
            $payload['schedules'][] = [
                'id' => $schedule->id,
                'modality' => $schedule->modality?->value ?? (string) $schedule->modality,
                'expected_daily_minutes' => (int) $schedule->expected_daily_minutes,
                'expected_weekly_minutes' => (int) $schedule->expected_weekly_minutes,
                'tolerance_minutes' => (int) $schedule->tolerance_minutes,
                'daily_tolerance_minutes' => (int) $schedule->daily_tolerance_minutes,
                'requires_legal_authorization' => (bool) $schedule->requires_legal_authorization,
                'allowed_legal_regimes' => $schedule->allowed_legal_regimes ?? [],
                'schedule_data' => $schedule->schedule_data ?? [],
                'cycle_data' => $schedule->cycle_data ?? [],
            ];
        }
        usort($payload['schedules'], fn ($a, $b) => $a['id'] <=> $b['id']);

        foreach ($this->allShiftsByEmployee[$employee->id] ?? [] as $shift) {
            $payload['shifts'][] = [
                'id' => $shift->id,
                'work_schedule_id' => $shift->work_schedule_id,
                'start_at_utc' => $shift->start_at_utc->format('Y-m-d H:i:s'),
                'end_at_utc' => $shift->end_at_utc->format('Y-m-d H:i:s'),
                'start_at_local' => $shift->start_at_local->format('Y-m-d H:i:s'),
                'end_at_local' => $shift->end_at_local->format('Y-m-d H:i:s'),
                'timezone' => $shift->timezone,
                'break_minutes' => (int) $shift->break_minutes,
                'expected_work_minutes' => (int) $shift->expected_work_minutes,
                'shift_type' => $shift->shift_type?->value ?? (string) $shift->shift_type,
                'origin' => $shift->origin?->value ?? (string) $shift->origin,
                'status' => $shift->status?->value ?? (string) $shift->status,
                'is_day_off' => (bool) $shift->is_day_off,
                'is_night_shift' => (bool) $shift->is_night_shift,
                'crosses_midnight' => (bool) $shift->crosses_midnight,
            ];
        }
        usort($payload['shifts'], fn ($a, $b) => $a['id'] <=> $b['id']);

        $punchRows = [];
        foreach ($this->punchesByEmployeeDate[$employee->id] ?? [] as $collection) {
            foreach ($collection as $punch) {
                $punchRows[] = [
                    'id' => $punch->id,
                    'employee_id' => $punch->employee_id,
                    'user_id' => $punch->user_id,
                    'occurred_at_local' => $punch->occurred_at_local->format('Y-m-d H:i:s'),
                    'occurred_at_utc' => $punch->occurred_at_utc->format('Y-m-d H:i:s'),
                    'direction' => $punch->direction,
                ];
            }
        }
        usort($punchRows, fn ($a, $b) => [$a['occurred_at_local'], $a['id']] <=> [$b['occurred_at_local'], $b['id']]);
        $payload['punches'] = $punchRows;

        $legacyRows = [];
        if ($employee->user_id !== null) {
            foreach ($this->legacyByUserDate[$employee->user_id] ?? [] as $collection) {
                foreach ($collection as $entry) {
                    $legacyRows[] = [
                        'id' => $entry->id,
                        'timestamp' => Carbon::parse($entry->timestamp)->format('Y-m-d H:i:s'),
                        'type' => $entry->type,
                    ];
                }
            }
        }
        usort($legacyRows, fn ($a, $b) => [$a['timestamp'], $a['id']] <=> [$b['timestamp'], $b['id']]);
        $payload['legacy_entries'] = $legacyRows;

        $treatmentRows = [];
        foreach ($this->treatmentsByEmployeeDate[$employee->id] ?? [] as $collection) {
            foreach ($collection as $treatment) {
                $treatmentRows[$treatment->id] = [
                    'id' => $treatment->id,
                    'employee_id' => $treatment->employee_id,
                    'employment_id' => $treatment->employment_id,
                    'reference_punch_id' => $treatment->reference_punch_id,
                    'type' => $treatment->type?->value ?? (string) $treatment->type,
                    'status' => $treatment->status?->value ?? (string) $treatment->status,
                    'effective_at' => $treatment->effective_at->format('Y-m-d H:i:s'),
                    'new_value_json' => $treatment->new_value_json ?? [],
                    'reason_text' => $treatment->reason_text,
                ];
            }
        }
        ksort($treatmentRows);
        $payload['treatments'] = array_values($treatmentRows);

        $eventIds = [];
        $establishment = $this->establishmentFor($employee);
        for ($d = 1; $d <= $this->daysInMonth; $d++) {
            $dateStr = sprintf('%04d-%02d-%02d', $this->year, $this->month, $d);
            foreach ($this->calendarEventsFor($establishment, $dateStr) as $event) {
                $eventIds[$event->id] = $event;
            }
        }
        ksort($eventIds);
        foreach ($eventIds as $event) {
            $payload['calendar_events'][] = [
                'id' => $event->id,
                'event_date' => $event->event_date->format('Y-m-d'),
                'type' => $event->type?->value ?? (string) $event->type,
                'scope' => $event->scope?->value ?? (string) $event->scope,
                'state' => $event->state,
                'city' => $event->city,
                'establishment_id' => $event->establishment_id,
                'work_behavior' => $event->work_behavior?->value ?? (string) $event->work_behavior,
                'all_day' => (bool) $event->all_day,
                'starts_at' => $event->starts_at,
                'ends_at' => $event->ends_at,
                'requires_compensation' => (bool) $event->requires_compensation,
            ];
        }

        foreach (array_keys($profileIds) as $profileId) {
            $profile = $this->allRuleProfiles->firstWhere('id', $profileId);
            if ($profile === null) {
                continue;
            }
            $payload['rule_profiles'][] = [
                'id' => $profile->id,
                'code' => $profile->code,
                'version' => $profile->version,
                'legal_regime' => $profile->legal_regime instanceof LegalRegime
                    ? $profile->legal_regime->value
                    : $profile->legal_regime,
                'effective_from' => $profile->effective_from->format('Y-m-d'),
                'effective_until' => $profile->effective_until?->format('Y-m-d'),
                'approval_status' => $profile->approval_status?->value ?? (string) $profile->approval_status,
                'configuration' => $profile->configuration ?? [],
            ];
        }
        usort($payload['rule_profiles'], fn ($a, $b) => $a['id'] <=> $b['id']);

        foreach ($policyKeys as $policy) {
            $payload['settlement_policies'][] = $this->settlementPolicyFingerprint($policy);
        }
        usort($payload['settlement_policies'], fn ($a, $b) => json_encode($a) <=> json_encode($b));

        return hash('sha256', json_encode(
            $this->canonicalize($payload),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ));
    }

    /**
     * Chave de agrupamento de políticas resolvidas (inclui o conteúdo
     * das políticas avulsas para distinguir regimes e modalidades).
     */
    protected function settlementPolicyKey(WorkTimeSettlementPolicy $policy): string
    {
        if ($policy->id !== null) {
            return 'id:'.$policy->id;
        }

        return 'inline:'.md5(json_encode(
            $this->canonicalize($this->settlementPolicyFingerprint($policy)),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ));
    }

    /**
     * @return array<string, mixed>
     */
    protected function settlementPolicyFingerprint(WorkTimeSettlementPolicy $policy): array
    {
        return [
            'id' => $policy->id,
            'company_id' => $policy->company_id,
            'establishment_id' => $policy->establishment_id,
            'employee_id' => $policy->employee_id,
            'legal_regime' => $policy->legal_regime instanceof LegalRegime
                ? $policy->legal_regime->value
                : $policy->legal_regime,
            'modality' => $policy->modality?->value ?? (string) $policy->modality,
            'effective_from' => $policy->effective_from?->format('Y-m-d'),
            'effective_until' => $policy->effective_until?->format('Y-m-d'),
            'legal_framework' => $policy->legal_framework,
            'compensation_terms' => $policy->compensation_terms ?? [],
            'carry_over_terms' => $policy->carry_over_terms ?? [],
            'hybrid_rules' => $policy->hybrid_rules ?? [],
            'approval_status' => $policy->approval_status?->value ?? (string) $policy->approval_status,
            'status' => $policy->status,
        ];
    }

    /**
     * Ordena chaves recursivamente para serialização canônica estável.
     *
     * @param  mixed  $value
     * @return mixed
     */
    protected function canonicalize($value)
    {
        if ($value instanceof Collection) {
            $value = $value->all();
        }

        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn ($item) => $this->canonicalize($item), $value);
        }

        ksort($value);

        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalize($item);
        }

        return $value;
    }

    /**
     * Plantão com virada de meia-noite do dia anterior (para apuração noturna).
     */
    protected function prevShiftFor(Employee $employee, string $prevDateStr): ?ShiftAssignment
    {
        $shift = $this->shiftsByEmployeeDate[$employee->id][$prevDateStr] ?? null;

        return $shift && $shift->crosses_midnight ? $shift : null;
    }

    protected function preload(): void
    {
        $employeeIds = array_keys($this->employees->all());
        $userIds = [];
        foreach ($this->employees as $employee) {
            if ($employee->user_id !== null) {
                $userIds[] = $employee->user_id;
            }
        }
        $userIds = array_values(array_unique($userIds));

        $rangeStart = $this->startDate->copy()->subDay()->startOfDay();
        $rangeEnd = $this->endDate->copy()->addDay()->endOfDay();

        $this->preloadShifts($employeeIds, $rangeStart, $rangeEnd);
        $this->preloadSchedules($employeeIds);
        $this->preloadPunches($employeeIds, $userIds, $rangeStart, $rangeEnd);
        $this->preloadTreatments($employeeIds);
        $this->preloadLegacyEntries($userIds, $rangeStart, $rangeEnd);
        $this->preloadRuleProfiles();
        $this->preloadSettlementPolicies();
        $this->preloadCalendarEvents();
    }

    protected function preloadShifts(array $employeeIds, Carbon $rangeStart, Carbon $rangeEnd): void
    {
        if (empty($employeeIds)) {
            return;
        }

        // Sem filtro de situação aqui: a contagem de plantões do relatório
        // considera qualquer situação; o motor filtra os ativos em memória.
        // Colunas restritas às utilizadas pela apuração e pela contagem.
        $shifts = ShiftAssignment::whereIn('employee_id', $employeeIds)
            ->where('start_at_local', '<=', $rangeEnd)
            ->where('end_at_local', '>=', $rangeStart)
            ->orderBy('id', 'asc')
            ->get([
                'id', 'employee_id', 'work_schedule_id', 'shift_code',
                'start_at_utc', 'end_at_utc', 'start_at_local', 'end_at_local',
                'timezone', 'break_minutes', 'expected_work_minutes',
                'shift_type', 'origin', 'status',
                'is_day_off', 'is_night_shift', 'crosses_midnight',
            ]);

        foreach ($shifts as $shift) {
            $this->allShiftsByEmployee[$shift->employee_id][] = $shift;

            if (! in_array($shift->status, [ShiftStatus::Scheduled, ShiftStatus::Confirmed, ShiftStatus::Completed], true)) {
                continue;
            }

            $dateStr = $shift->start_at_local->format('Y-m-d');
            $this->shiftsByEmployeeDate[$shift->employee_id][$dateStr] ??= $shift;
        }
    }

    protected function preloadSchedules(array $employeeIds): void
    {
        if (empty($employeeIds)) {
            $this->assignmentsByEmployee = [];

            return;
        }

        $assignments = WorkScheduleAssignment::with('workSchedule')
            ->whereIn('employee_id', $employeeIds)
            ->orderBy('effective_from', 'asc')
            ->get();

        $scheduleIds = [];
        foreach ($assignments as $assignment) {
            $scheduleIds[] = $assignment->work_schedule_id;
        }
        foreach ($this->employees as $employee) {
            $scheduleIds[] = $employee->work_schedule_id;
        }
        $scheduleIds = array_values(array_unique(array_filter($scheduleIds)));

        $this->schedulesById = $scheduleIds
            ? WorkSchedule::whereIn('id', $scheduleIds)->get()->keyBy(fn (WorkSchedule $schedule) => $schedule->id)->all()
            : [];

        foreach ($assignments as $assignment) {
            $this->assignmentsByEmployee[$assignment->employee_id][] = $assignment;
        }
    }

    protected function preloadPunches(array $employeeIds, array $userIds, Carbon $rangeStart, Carbon $rangeEnd): void
    {
        if (empty($employeeIds) && empty($userIds)) {
            return;
        }

        // Colunas restritas às utilizadas pelo motor (sem hashes fiscais).
        $punches = PunchEvent::where(function ($query) use ($employeeIds, $userIds) {
            $query->whereIn('employee_id', $employeeIds);
            if (! empty($userIds)) {
                $query->orWhereIn('user_id', $userIds);
            }
        })
            ->where('occurred_at_local', '>=', $rangeStart)
            ->where('occurred_at_local', '<=', $rangeEnd)
            ->orderBy('occurred_at_local', 'asc')
            ->orderBy('id', 'asc')
            ->get([
                'id', 'employee_id', 'user_id', 'nsr', 'direction',
                'occurred_at_utc', 'occurred_at_local', 'timezone',
            ]);

        // Agrupar uma única vez por vínculo e por usuário (ordem O(linhas)).
        $byEmployee = [];
        $byUser = [];
        foreach ($punches as $punch) {
            $dateStr = $punch->occurred_at_local->format('Y-m-d');
            if ($punch->employee_id !== null) {
                $byEmployee[$punch->employee_id][$dateStr][] = $punch;
            }
            // Espelha a semântica do orWhere('user_id', ...): nulo combina com nulo.
            $byUser[$punch->user_id][$dateStr][] = $punch;
        }

        foreach ($this->employees as $employee) {
            $merged = [];
            foreach ($byEmployee[$employee->id] ?? [] as $dateStr => $list) {
                foreach ($list as $punch) {
                    $merged[$dateStr][] = $punch;
                }
            }
            foreach ($byUser[$employee->user_id] ?? [] as $dateStr => $list) {
                foreach ($list as $punch) {
                    // Evita duplicar a marcação já incluída pelo vínculo.
                    if ($punch->employee_id !== $employee->id) {
                        $merged[$dateStr][] = $punch;
                    }
                }
            }
            foreach ($merged as $dateStr => $list) {
                $this->punchesByEmployeeDate[$employee->id][$dateStr] = collect($list)
                    ->sort(fn ($a, $b) => [$a->occurred_at_local->timestamp, $a->id] <=> [$b->occurred_at_local->timestamp, $b->id])
                    ->values();
            }
        }
    }

    protected function preloadTreatments(array $employeeIds): void
    {
        if (empty($employeeIds)) {
            return;
        }

        $treatments = TreatmentEvent::where(function ($query) use ($employeeIds) {
            $query->whereIn('employee_id', $employeeIds)
                ->orWhereIn('employment_id', $employeeIds);
        })
            ->where('status', TreatmentEventStatus::Approved->value)
            ->where('effective_at', '>=', $this->startDate->copy()->startOfDay())
            ->where('effective_at', '<=', $this->endDate->copy()->endOfDay())
            ->orderBy('id', 'asc')
            ->get([
                'id', 'employee_id', 'employment_id', 'reference_punch_id',
                'type', 'status', 'effective_at', 'new_value_json', 'reason_text',
            ]);

        foreach ($treatments as $treatment) {
            $dateStr = $treatment->effective_at->format('Y-m-d');
            if ($treatment->employee_id !== null) {
                $byEmployeeLink[$treatment->employee_id][$dateStr][$treatment->id] = $treatment;
            }
            if ($treatment->employment_id !== null && $treatment->employment_id !== $treatment->employee_id) {
                $byEmploymentLink[$treatment->employment_id][$dateStr][$treatment->id] = $treatment;
            }
        }

        foreach ($this->employees as $employee) {
            $merged = [];
            foreach ($byEmployeeLink[$employee->id] ?? [] as $dateStr => $byId) {
                foreach ($byId as $id => $treatment) {
                    $merged[$dateStr][$id] = $treatment;
                }
            }
            foreach ($byEmploymentLink[$employee->id] ?? [] as $dateStr => $byId) {
                foreach ($byId as $id => $treatment) {
                    $merged[$dateStr][$id] = $treatment;
                }
            }
            foreach ($merged as $dateStr => $byId) {
                ksort($byId);
                $this->treatmentsByEmployeeDate[$employee->id][$dateStr] = collect(array_values($byId));
            }
        }
    }

    protected function preloadLegacyEntries(array $userIds, Carbon $rangeStart, Carbon $rangeEnd): void
    {
        if (empty($userIds)) {
            return;
        }

        $entries = TimeEntry::whereIn('user_id', $userIds)
            ->where('timestamp', '>=', $rangeStart)
            ->where('timestamp', '<=', $rangeEnd)
            ->orderBy('timestamp', 'asc')
            ->orderBy('id', 'asc')
            ->get(['id', 'user_id', 'timestamp', 'type']);

        foreach ($entries as $entry) {
            $dateStr = Carbon::parse($entry->timestamp)->format('Y-m-d');
            $this->legacyByUserDate[$entry->user_id][$dateStr][] = $entry;
        }

        foreach ($this->legacyByUserDate as $userId => $byDate) {
            foreach ($byDate as $dateStr => $list) {
                $this->legacyByUserDate[$userId][$dateStr] = collect($list);
            }
        }
    }

    protected function preloadRuleProfiles(): void
    {
        $this->allRuleProfiles = LaborRuleProfile::orderBy('id', 'asc')->get();
    }

    protected function preloadSettlementPolicies(): void
    {
        $this->allSettlementPolicies = $this->hasSettlementPoliciesTable
            ? WorkTimeSettlementPolicy::orderBy('id', 'asc')->get()
            : collect();

        $this->allTimeBankPolicies = TimeBankPolicy::orderBy('id', 'asc')->get();
    }

    protected function preloadCalendarEvents(): void
    {
        $this->monthCalendarEvents = CalendarEvent::where('active', true)
            ->where('event_date', '>=', $this->startDate->copy()->startOfDay())
            ->where('event_date', '<=', $this->endDate->copy()->endOfDay())
            ->orderBy('id', 'asc')
            ->get();
    }

    /**
     * Perfil padrão CLT resolvido uma única vez por competência.
     */
    protected function standardCltProfile(): LaborRuleProfile
    {
        if (! $this->standardCltResolved) {
            $this->standardCltProfile = LaborRuleProfile::createStandardCltProfile();
            $this->allRuleProfiles->push($this->standardCltProfile);
            $this->standardCltResolved = true;
        }

        return $this->standardCltProfile;
    }

    /**
     * Perfil padrão federal resolvido uma única vez por competência.
     */
    protected function federalStatutoryProfile(): LaborRuleProfile
    {
        if (! $this->federalStatutoryResolved) {
            $this->federalStatutoryProfile = LaborRuleProfile::createFederalStatutoryProfile();
            $this->allRuleProfiles->push($this->federalStatutoryProfile);
            $this->federalStatutoryResolved = true;
        }

        return $this->federalStatutoryProfile;
    }

    protected function firstActiveProfileForRegime(LegalRegime $regime, CarbonInterface $date): ?LaborRuleProfile
    {
        $dateStr = $date->format('Y-m-d');

        foreach ($this->allRuleProfiles as $profile) {
            $profileRegime = $profile->legal_regime instanceof LegalRegime
                ? $profile->legal_regime->value
                : $profile->legal_regime;

            if ($profileRegime !== $regime->value) {
                continue;
            }

            if (! $profile->isApproved() || ! $profile->isEffectiveAt($dateStr)) {
                continue;
            }

            return $profile;
        }

        return null;
    }

    protected function isEventApplicableTo(CalendarEvent $event, ?Establishment $establishment): bool
    {
        if ($event->scope === CalendarEventScope::National) {
            return true;
        }

        if ($establishment === null) {
            return false;
        }

        if ($event->scope === CalendarEventScope::State) {
            return ! empty($establishment->state) && $event->state === $establishment->state;
        }

        if ($event->scope === CalendarEventScope::Municipal) {
            return ! empty($establishment->state) && ! empty($establishment->city)
                && $event->state === $establishment->state
                && $event->city === $establishment->city;
        }

        if ($event->scope === CalendarEventScope::Establishment) {
            return (int) $event->establishment_id === (int) $establishment->id;
        }

        return false;
    }

    protected function eventScopePriority(CalendarEvent $event): int
    {
        return match ($event->scope) {
            CalendarEventScope::Establishment => 4,
            CalendarEventScope::Municipal => 3,
            CalendarEventScope::State => 2,
            CalendarEventScope::National => 1,
            default => 0,
        };
    }

    protected function resolveSettlementPolicy(Employee $employee, CarbonInterface $date, string $dateStr): WorkTimeSettlementPolicy
    {
        $regime = $employee->legal_regime;

        if (! $this->hasSettlementPoliciesTable) {
            return WorkTimeSettlementService::buildSafeUnconfiguredPolicy($regime);
        }

        $establishmentId = $employee->sector?->establishment_id;
        $companyId = $employee->sector?->establishment?->company_id;

        if (! $companyId) {
            $companyId = $employee->getAttribute('company_id') ?? null;
        }

        if (! $companyId && $this->companyCount === 1) {
            $companyId = CurrentCompany::get()?->id;
        }

        $candidates = $this->allSettlementPolicies->filter(fn (WorkTimeSettlementPolicy $policy) => $this->isPolicyActiveAt($policy, $dateStr));

        // 1. Política específica do vínculo/colaborador
        $individual = $candidates->filter(fn (WorkTimeSettlementPolicy $policy) => $policy->employee_id === $employee->id
            && ($companyId === null || (int) $policy->company_id === (int) $companyId || ($policy->company_id === null && $this->companyCount <= 1)));

        if ($regime) {
            $match = $this->latestById($individual->filter(fn (WorkTimeSettlementPolicy $policy) => $this->policyRegimeValue($policy) === $regime->value));
            if ($match) {
                return $match;
            }
        }

        $match = $this->latestById($individual->filter(fn (WorkTimeSettlementPolicy $policy) => $policy->legal_regime === null));
        if ($match) {
            return $match;
        }

        // 2. Política do estabelecimento
        if ($establishmentId) {
            $establishment = $employee->sector?->establishment;
            $estCompanyId = $establishment?->company_id ?? $companyId;

            $scoped = $candidates->filter(fn (WorkTimeSettlementPolicy $policy) => (int) $policy->establishment_id === (int) $establishmentId
                && $policy->employee_id === null
                && ($estCompanyId === null || (int) $policy->company_id === (int) $estCompanyId || ($policy->company_id === null && $this->companyCount <= 1)));

            if ($regime) {
                $match = $this->latestById($scoped->filter(fn (WorkTimeSettlementPolicy $policy) => $this->policyRegimeValue($policy) === $regime->value));
                if ($match) {
                    return $match;
                }
            }

            $match = $this->latestById($scoped->filter(fn (WorkTimeSettlementPolicy $policy) => $policy->legal_regime === null));
            if ($match) {
                return $match;
            }
        }

        // 3. Política da empresa
        if ($companyId) {
            $scoped = $candidates->filter(fn (WorkTimeSettlementPolicy $policy) => $policy->employee_id === null
                && $policy->establishment_id === null
                && ((int) $policy->company_id === (int) $companyId || ($policy->company_id === null && $this->companyCount <= 1)));

            if ($regime) {
                $match = $this->latestById($scoped->filter(fn (WorkTimeSettlementPolicy $policy) => $this->policyRegimeValue($policy) === $regime->value));
                if ($match) {
                    return $match;
                }
            }

            $match = $this->latestById($scoped->filter(fn (WorkTimeSettlementPolicy $policy) => $policy->legal_regime === null));
            if ($match) {
                return $match;
            }
        } elseif ($this->companyCount <= 1) {
            $scoped = $candidates->filter(fn (WorkTimeSettlementPolicy $policy) => $policy->employee_id === null
                && $policy->establishment_id === null);

            if ($regime) {
                $match = $this->latestById($scoped->filter(fn (WorkTimeSettlementPolicy $policy) => $this->policyRegimeValue($policy) === $regime->value));
                if ($match) {
                    return $match;
                }
            }

            $match = $this->latestById($scoped->filter(fn (WorkTimeSettlementPolicy $policy) => $policy->legal_regime === null));
            if ($match) {
                return $match;
            }
        }

        // 4. Fallback seguro
        return $this->resolveFallbackPolicy($date, $regime);
    }

    protected function isPolicyActiveAt(WorkTimeSettlementPolicy $policy, string $dateStr): bool
    {
        if ($policy->status !== 'active') {
            return false;
        }

        if ($policy->approval_status !== SettlementPolicyApprovalStatus::Approved) {
            return false;
        }

        $from = $policy->effective_from?->format('Y-m-d');
        if ($from !== null && $dateStr < $from) {
            return false;
        }

        $until = $policy->effective_until?->format('Y-m-d');
        if ($until !== null && $dateStr > $until) {
            return false;
        }

        return true;
    }

    protected function policyRegimeValue(WorkTimeSettlementPolicy $policy): ?string
    {
        return $policy->legal_regime instanceof LegalRegime
            ? $policy->legal_regime->value
            : $policy->legal_regime;
    }

    /**
     * @param  Collection<int, WorkTimeSettlementPolicy>  $policies
     */
    protected function latestById(Collection $policies): ?WorkTimeSettlementPolicy
    {
        $latest = null;
        foreach ($policies as $policy) {
            if ($latest === null || $policy->id > $latest->id) {
                $latest = $policy;
            }
        }

        return $latest;
    }

    protected function resolveFallbackPolicy(CarbonInterface $date, ?LegalRegime $regime): WorkTimeSettlementPolicy
    {
        // O resultado do fallback depende apenas de regime e data:
        // reutiliza a mesma instância imutável em vez de reconstruí-la
        // a cada colaborador/dia.
        $key = ($regime?->value ?? 'null').'|'.$date->format('Y-m-d');

        if (isset($this->fallbackPolicyCache[$key])) {
            return $this->fallbackPolicyCache[$key];
        }

        $dateStr = $date->format('Y-m-d');

        $legacyPolicy = null;
        foreach ($this->allTimeBankPolicies as $candidate) {
            $from = $candidate->valid_from?->format('Y-m-d');
            if ($from !== null && $dateStr < $from) {
                continue;
            }
            $until = $candidate->valid_until?->format('Y-m-d');
            if ($until !== null && $dateStr > $until) {
                continue;
            }
            if ($legacyPolicy === null || $from > ($legacyPolicy->valid_from?->format('Y-m-d') ?? '')) {
                $legacyPolicy = $candidate;
            }
        }

        if ($legacyPolicy) {
            if ($legacyPolicy->enabled) {
                $modality = match ($legacyPolicy->closing_mode) {
                    TimeBankClosingMode::MonthlyReset => SettlementModality::LegacyMonthlyReset,
                    TimeBankClosingMode::CarryOver => SettlementModality::CumulativeBank,
                    default => SettlementModality::CumulativeBank,
                };

                $policy = new WorkTimeSettlementPolicy([
                    'name' => $legacyPolicy->name ?? 'Banco de Horas Geral (Legado)',
                    'modality' => $modality,
                    'legal_regime' => $regime,
                    'effective_from' => $legacyPolicy->valid_from ?? now()->startOfYear(),
                    'effective_until' => $legacyPolicy->valid_until,
                    'legal_framework' => 'Regra geral de banco de horas configurada nas definições do sistema',
                    'approval_status' => SettlementPolicyApprovalStatus::Approved,
                    'status' => 'active',
                    'compensation_terms' => ['max_months' => ($modality === SettlementModality::LegacyMonthlyReset ? 1 : 6)],
                ]);
                $policy->id = $legacyPolicy->id;

                return $this->fallbackPolicyCache[$key] = $policy;
            }

            return $this->fallbackPolicyCache[$key] = new WorkTimeSettlementPolicy([
                'name' => 'Sem Banco de Horas (Desativado nas Configurações)',
                'modality' => SettlementModality::NoBank,
                'legal_regime' => $regime,
                'effective_from' => $legacyPolicy->valid_from ?? Carbon::parse('2020-01-01'),
                'legal_framework' => 'Banco de horas desativado nas configurações do sistema',
                'approval_status' => SettlementPolicyApprovalStatus::Approved,
                'status' => 'active',
                'compensation_terms' => [],
            ]);
        }

        return $this->fallbackPolicyCache[$key] = WorkTimeSettlementService::buildSafeUnconfiguredPolicy($regime);
    }
}
