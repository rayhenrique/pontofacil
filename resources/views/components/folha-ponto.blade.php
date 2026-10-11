<?php

use App\Domain\Calendar\Services\WorkCalendarService;
use App\Domain\Company\Services\CurrentCompany;
use App\Domain\PTRP\Services\TimesheetJourneyService;
use App\Enums\UserRole;
use App\Enums\WorkScheduleModality;
use App\Models\ClosedPeriod;
use App\Models\Employee;
use App\Models\Sector;
use App\Models\ShiftAssignment;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Folha de Ponto de Funcionário')] class extends Component
{
    public $userId;
    public $month;
    public $year;

    // Modelos de Apresentação: 'auto', 'administrative', 'nocturnal', 'shifts', 'blank'
    public string $reportModel = 'auto';

    // Modo de preenchimento: 'punches' (dados apurados PTRP) ou 'blank' (em branco p/ preenchimento manual)
    public string $fillMode = 'punches';

    // Rubricas de conferência: se true exibe 'Registro eletrônico' (nunca ✓ fictício)
    public bool $includeRubrica = true;

    // Alternador para exibição do documento A4 completo no mobile
    public bool $showMobileFullDoc = false;

    // Cabeçalho Institucional Oficial
    public $headerState = '';
    public $headerEntity = '';
    public $headerSubEntity = '';
    public $headerAddress = '';
    public $headerCnpj = '';
    public $headerPhone = '';
    public $headerEmail = '';
    public ?string $companyLogoUrl = null;
    public bool $isCompanyConfigured = true;
    public array $missingCompanyFields = [];

    // Metadados Funcionais (carregados do cadastro ou do snapshot congelado)
    public $workload = '40h';
    public $zone = 'Urbana';
    public $jobTitle = '';
    public $contractType = 'CLT';
    public $location = '';
    public bool $isEditedForPreview = false;

    public function mount(?int $userId = null, ?int $month = null, ?int $year = null): void
    {
        $this->month = $month ?? (int) (request('month') ?? now()->month);
        $this->year = $year ?? (int) (request('year') ?? now()->year);

        $currentUser = Auth::user();
        if (! $currentUser) {
            abort(401);
        }

        $targetUserId = $userId ?? (request('userId') !== null ? (int) request('userId') : (int) $currentUser->id);

        $this->validateAuthorizedUserId($targetUserId);
        $this->userId = $targetUserId;

        $this->loadSettings();
        $this->loadEmployeeData();
    }

    public function updatingUserId($value): void
    {
        $this->validateAuthorizedUserId((int) $value);
    }

    public function updatedUserId(): void
    {
        $this->isEditedForPreview = false;
        $this->loadEmployeeData();
    }

    public function updatedReportModel(): void
    {
        // Se selecionar modelo em branco explicitamente, alinha o fillMode
        if ($this->reportModel === 'blank') {
            $this->fillMode = 'blank';
        }
    }

    public function updatedMonth(): void
    {
        $this->isEditedForPreview = false;
        $this->loadEmployeeData();
    }

    public function updatedYear(): void
    {
        $this->isEditedForPreview = false;
        $this->loadEmployeeData();
    }

    /**
     * Validação rigorosa de autorização no servidor (anti-tampering de parâmetros de URL e Livewire).
     */
    protected function validateAuthorizedUserId(int $targetUserId): void
    {
        $currentUser = Auth::user();
        if (! $currentUser) {
            abort(401);
        }

        if ($targetUserId === (int) $currentUser->id) {
            return;
        }

        if ($currentUser->role === UserRole::Admin) {
            if (! User::where('id', $targetUserId)->exists()) {
                abort(404, 'Colaborador não encontrado.');
            }
            return;
        }

        if ($currentUser->role === UserRole::Manager) {
            $managedSectorIds = Sector::where('manager_id', $currentUser->id)->pluck('id');
            $allowedUserIds = Employee::whereIn('sector_id', $managedSectorIds)->pluck('user_id');

            if (! $allowedUserIds->contains($targetUserId)) {
                abort(403, 'Acesso não autorizado à folha de ponto deste colaborador.');
            }
            return;
        }

        abort(403, 'Acesso restrito à própria folha de ponto.');
    }

    public function loadSettings(): void
    {
        $company = CurrentCompany::get();
        $establishment = $company->defaultEstablishment();

        // 1. Prioriza dados reais da empresa e do estabelecimento cadastrado
        $this->headerEntity = $company->header_entity ?: ($company->trade_name ?: $company->legal_name);
        $this->headerState = $company->header_state ?: ($company->state ? 'ESTADO DE ' . $company->state : '');
        $this->headerSubEntity = $company->header_sub_entity ?: '';

        $addr = $company->address ?: ($establishment?->address ?: '');
        $city = $company->city ?: ($establishment?->city ?: '');
        $state = $company->state ?: ($establishment?->state ?: '');
        if ($addr && $city) {
            $addr .= " – {$city}";
        }
        if ($addr && $state) {
            $addr .= " – {$state}";
        }
        $this->headerAddress = $addr;

        $this->headerCnpj = $company->formatted_cnpj ?: ($establishment?->identifier_number ?: '');
        $this->headerPhone = $company->phone ?: '';
        $this->headerEmail = $company->email ?: '';
        $this->companyLogoUrl = $company->logo_url;

        // 2. Sobrescritas autorizadas via SystemSetting se cadastradas
        $settings = SystemSetting::whereIn('key', [
            'report_header_state',
            'report_header_entity',
            'report_header_sub_entity',
            'report_header_address',
            'report_header_cnpj',
            'report_header_phone',
            'report_header_email',
            'company_logo_url',
        ])->pluck('value', 'key');

        if (! empty($settings['report_header_state'])) $this->headerState = $settings['report_header_state'];
        if (! empty($settings['report_header_entity'])) $this->headerEntity = $settings['report_header_entity'];
        if (! empty($settings['report_header_sub_entity'])) $this->headerSubEntity = $settings['report_header_sub_entity'];
        if (! empty($settings['report_header_address'])) $this->headerAddress = $settings['report_header_address'];
        if (! empty($settings['report_header_cnpj'])) $this->headerCnpj = $settings['report_header_cnpj'];
        if (! empty($settings['report_header_phone'])) $this->headerPhone = $settings['report_header_phone'];
        if (! empty($settings['report_header_email'])) $this->headerEmail = $settings['report_header_email'];
        if (! empty($settings['company_logo_url'])) $this->companyLogoUrl = $settings['company_logo_url'];

        // 3. Verificação de completude institucional
        $this->missingCompanyFields = [];
        if (empty(trim((string) $this->headerEntity))) {
            $this->missingCompanyFields[] = 'Razão Social / Nome da Entidade';
        }
        if (empty(trim((string) $this->headerCnpj))) {
            $this->missingCompanyFields[] = 'CNPJ da Empresa';
        }
        if (empty(trim((string) $this->headerAddress))) {
            $this->missingCompanyFields[] = 'Endereço do Estabelecimento';
        }

        $this->isCompanyConfigured = empty($this->missingCompanyFields);
    }

    public function loadEmployeeData(): void
    {
        $targetUser = User::with('employee.sector')->find($this->userId);
        $closedPeriod = ClosedPeriod::findForPeriod($this->year, $this->month);
        $isClosed = $closedPeriod && $closedPeriod->status === 'closed';

        if ($targetUser && $targetUser->employee) {
            $emp = $targetUser->employee;

            // Se competência estiver fechada e não editada na prévia, prioriza snapshot congelado
            if ($isClosed && ! $this->isEditedForPreview) {
                $snapshot = $closedPeriod->currentSnapshots()
                    ->where('employee_id', $emp->id)
                    ->first();

                if ($snapshot && ! empty($snapshot->employee_snapshot)) {
                    $snapEmp = $snapshot->employee_snapshot;
                    $this->jobTitle = $snapEmp['job_title'] ?? ($emp->job_title ?: 'Colaborador');
                    $this->contractType = $snapEmp['contract_type'] ?? ($emp->contract_type ?: 'CLT');
                    $this->workload = $snapEmp['workload'] ?? ($emp->workload ?: '40h');
                    $this->zone = $snapEmp['zone'] ?? ($emp->zone ?: 'Urbana');
                    $this->location = $snapEmp['department'] ?? ($snapEmp['sector_name'] ?? (optional($emp->sector)->name ?? 'Geral'));
                    return;
                }
            }

            $this->location = optional($emp->sector)->name ?? 'Geral';
            $this->jobTitle = $emp->job_title ?: ($targetUser->role === UserRole::Admin ? 'Administrador' : ($targetUser->role === UserRole::Manager ? 'Gestor' : 'Colaborador'));
            $this->contractType = $emp->contract_type ?: ($emp->legal_regime?->label() ?? 'CLT');
            $this->workload = $emp->workload ?: '40h';
            $this->zone = $emp->zone ?: 'Urbana';
        } else {
            $this->location = 'Geral';
            $this->jobTitle = ($targetUser && $targetUser->role === UserRole::Admin) ? 'Administrador' : (($targetUser && $targetUser->role === UserRole::Manager) ? 'Gestor' : 'Colaborador');
            $this->contractType = 'CLT';
            $this->workload = '40h';
            $this->zone = 'Urbana';
        }
    }

    /**
     * Detecta o modelo recomendado de folha com base na escala e plantões vigentes do mês.
     */
    protected function detectRecommendedModel(Employee $employee, Carbon $periodStart, Carbon $periodEnd): string
    {
        // 1. Se houver ShiftAssignment ativo no mês -> modelo de plantões
        $hasShifts = ShiftAssignment::where('employee_id', $employee->id)
            ->whereBetween('start_at_local', [$periodStart, $periodEnd])
            ->exists();

        if ($hasShifts) {
            return 'shifts';
        }

        // 2. Escala de trabalho do colaborador para a competência
        $schedule = $employee->getWorkScheduleForDate($periodStart);
        if ($schedule) {
            if ($schedule->modality?->requiresSpecialAuthorization()
                || in_array($schedule->modality, [
                    WorkScheduleModality::TwelveByThirtySix,
                    WorkScheduleModality::TwentyFourBySeventyTwo,
                    WorkScheduleModality::VariableShifts,
                    WorkScheduleModality::CustomCycle,
                ], true)
            ) {
                return 'shifts';
            }

            // Inspecionar se os períodos da escala atravessam a meia-noite
            $scheduleData = $schedule->schedule_data ?? [];
            foreach ($scheduleData as $dayCfg) {
                $periods = $dayCfg['periods'] ?? [];
                foreach ($periods as $p) {
                    $start = $p['start'] ?? '';
                    $end = $p['end'] ?? '';
                    if (($start && $end && $end < $start) || ((int) substr($start, 0, 2) >= 21)) {
                        return 'nocturnal';
                    }
                }
            }
        }

        return 'administrative';
    }

    public function with(): array
    {
        $currentUser = Auth::user();
        $isAdmin = $currentUser->role === UserRole::Admin;
        $isManager = $currentUser->role === UserRole::Manager;

        $selectableUsers = collect([]);
        if ($isAdmin) {
            $selectableUsers = User::orderBy('name')->get();
        } elseif ($isManager) {
            $managedSectorIds = Sector::where('manager_id', $currentUser->id)->pluck('id');
            $employeeUserIds = Employee::whereIn('sector_id', $managedSectorIds)->pluck('user_id');
            $allowedUserIds = $employeeUserIds->push($currentUser->id)->unique();
            $selectableUsers = User::whereIn('id', $allowedUserIds)->orderBy('name')->get();
        } else {
            $selectableUsers = collect([$currentUser]);
        }

        $canSelectUser = $isAdmin || ($isManager && $selectableUsers->count() > 1);
        $selectedUser = User::with('employee.sector.establishment')->find($this->userId) ?? $currentUser;

        $periodStart = Carbon::createFromDate($this->year, $this->month, 1)->startOfMonth();
        $periodEnd = Carbon::createFromDate($this->year, $this->month, 1)->endOfMonth();
        $daysInMonth = $periodStart->daysInMonth;
        $monthNameUpper = strtoupper($periodStart->locale('pt_BR')->translatedFormat('F'));

        // Consulta unificada à apuração PTRP existente (preserva competências fechadas via snapshot)
        $monthJourneyData = app(TimesheetJourneyService::class)->resolveMonthData($selectedUser, $this->year, $this->month);

        $isClosedPeriod = (bool) ($monthJourneyData['isClosedPeriod'] ?? false);
        $closedPeriod = $monthJourneyData['closedPeriod'] ?? null;
        $snapshotVersion = $monthJourneyData['snapshotVersion'] ?? null;
        $employee = $monthJourneyData['employee'];
        $daysCalculated = $monthJourneyData['daysCalculated'] ?? [];
        $groupedEntries = $monthJourneyData['groupedEntries'] ?? [];

        // Resolução do modelo recomendado (escolha visual nunca altera a apuração)
        if ($this->reportModel === 'blank') {
            $this->fillMode = 'blank';
        }
        $detectedModel = $this->detectRecommendedModel($employee, $periodStart, $periodEnd);
        $activeModel = $this->reportModel === 'auto' ? $detectedModel : $this->reportModel;

        // Obter Escala Aplicada e Calendário Laboral
        $workSchedule = $employee->getWorkScheduleForDate($periodStart);
        $establishment = $employee->sector?->establishment ?? CurrentCompany::get()->defaultEstablishment();
        $calendarService = app(WorkCalendarService::class);

        // Indicadores Totais de Jornada
        $totalWorkedMinutes = (int) ($monthJourneyData['totalWorkedMinutes'] ?? 0);
        $totalScheduledMinutes = 0;
        $totalPhysicalNightMinutes = 0;
        $totalLegalNightMinutes = 0;
        $totalOvertimeMinutes = 0;
        $incompleteDaysCount = 0;
        $isPendingConfigOverall = false;

        foreach ($daysCalculated as $dayData) {
            $totalScheduledMinutes += (int) ($dayData['scheduled_minutes'] ?? 0);
            $totalPhysicalNightMinutes += (int) ($dayData['night_minutes'] ?? 0);
            $totalLegalNightMinutes += (int) ($dayData['night_equivalent_minutes'] ?? 0);
            $totalOvertimeMinutes += (int) ($dayData['overtime_minutes'] ?? 0);
            if (! empty($dayData['is_incomplete'])) {
                $incompleteDaysCount++;
            }
            if (! empty($dayData['is_pending_configuration'])) {
                $isPendingConfigOverall = true;
            }
        }

        // Formatações de Resumo
        $totalWorkedFormatted = sprintf('%dh %02dm', intdiv($totalWorkedMinutes, 60), $totalWorkedMinutes % 60);
        $totalScheduledFormatted = sprintf('%dh %02dm', intdiv($totalScheduledMinutes, 60), $totalScheduledMinutes % 60);
        $totalPhysicalNightFormatted = sprintf('%dh %02dm', intdiv($totalPhysicalNightMinutes, 60), $totalPhysicalNightMinutes % 60);
        $totalLegalNightFormatted = sprintf('%dh %02dm', intdiv($totalLegalNightMinutes, 60), $totalLegalNightMinutes % 60);
        $totalOvertimeFormatted = sprintf('+%dh %02dm', intdiv($totalOvertimeMinutes, 60), $totalOvertimeMinutes % 60);

        // Construção das Linhas de Dados por Modelo
        $administrativeRows = $this->buildAdministrativeRows(
            daysInMonth: $daysInMonth,
            employee: $employee,
            workSchedule: $workSchedule,
            establishment: $establishment,
            calendarService: $calendarService,
            daysCalculated: $daysCalculated,
            groupedEntries: $groupedEntries
        );

        $nocturnalRows = $this->buildNocturnalRows(
            daysInMonth: $daysInMonth,
            employee: $employee,
            workSchedule: $workSchedule,
            establishment: $establishment,
            calendarService: $calendarService,
            daysCalculated: $daysCalculated,
            groupedEntries: $groupedEntries
        );

        $shiftsRows = $this->buildShiftsRows(
            employee: $employee,
            periodStart: $periodStart,
            periodEnd: $periodEnd,
            daysCalculated: $daysCalculated,
            groupedEntries: $groupedEntries,
            workSchedule: $workSchedule
        );

        return [
            'selectedUser' => $selectedUser,
            'employee' => $employee,
            'canSelectUser' => $canSelectUser,
            'selectableUsers' => $selectableUsers,
            'monthNameUpper' => $monthNameUpper,
            'daysInMonth' => $daysInMonth,
            'periodStart' => $periodStart,
            'periodEnd' => $periodEnd,
            'isClosedPeriod' => $isClosedPeriod,
            'closedPeriod' => $closedPeriod,
            'snapshotVersion' => $snapshotVersion,
            'detectedModel' => $detectedModel,
            'activeModel' => $activeModel,
            'workSchedule' => $workSchedule,
            'isPendingConfigOverall' => $isPendingConfigOverall,
            'totalWorkedMinutes' => $totalWorkedMinutes,
            'totalWorkedFormatted' => $totalWorkedFormatted,
            'totalScheduledMinutes' => $totalScheduledMinutes,
            'totalScheduledFormatted' => $totalScheduledFormatted,
            'totalPhysicalNightMinutes' => $totalPhysicalNightMinutes,
            'totalPhysicalNightFormatted' => $totalPhysicalNightFormatted,
            'totalLegalNightMinutes' => $totalLegalNightMinutes,
            'totalLegalNightFormatted' => $totalLegalNightFormatted,
            'totalOvertimeMinutes' => $totalOvertimeMinutes,
            'totalOvertimeFormatted' => $totalOvertimeFormatted,
            'incompleteDaysCount' => $incompleteDaysCount,
            'jobTitle' => $this->jobTitle,
            'contractType' => $this->contractType,
            'workload' => $this->workload,
            'zone' => $this->zone,
            'location' => $this->location,
            'administrativeRows' => $administrativeRows,
            'nocturnalRows' => $nocturnalRows,
            'shiftsRows' => $shiftsRows,
        ];
    }

    /**
     * Constrói as linhas para a Folha de Ponto Administrativa (Convencional).
     */
    protected function buildAdministrativeRows(
        int $daysInMonth,
        Employee $employee,
        ?WorkSchedule $workSchedule,
        $establishment,
        WorkCalendarService $calendarService,
        array $daysCalculated,
        array $groupedEntries
    ): array {
        $rows = [];

        for ($d = 1; $d <= $daysInMonth; $d++) {
            $currentDate = Carbon::createFromDate($this->year, $this->month, $d)->locale('pt_BR');
            $dateKey = $currentDate->format('Y-m-d');
            $dayNum = sprintf('%02d', $d);
            $dayOfWeek = $currentDate->dayOfWeek; // 0=Dom, 6=Sáb

            $isSat = $currentDate->isSaturday();
            $isSun = $currentDate->isSunday();

            // Calendário Laboral
            $calendarDay = $calendarService->resolveDay($currentDate, $establishment);
            $isHoliday = $calendarDay->isHoliday;
            $holidayName = $calendarDay->appliedEvents[0]['name'] ?? ($isHoliday ? 'Feriado' : null);

            // Apuração PTRP para a data
            $dayCalc = $daysCalculated[$dateKey] ?? null;
            $rawEntries = $groupedEntries[$dateKey] ?? collect([]);
            $hasEntries = $rawEntries->isNotEmpty();

            // Plantão agendado para a data
            $shift = ShiftAssignment::where('employee_id', $employee->id)
                ->active()
                ->whereDate('start_at_local', $dateKey)
                ->first();

            // Resolução de Folga da Escala
            $isDayOff = false;
            if ($shift) {
                $isDayOff = $shift->is_day_off;
            } elseif ($dayCalc) {
                $isDayOff = (bool) ($dayCalc['is_day_off'] ?? false);
            } elseif ($workSchedule) {
                $schedDay = $workSchedule->getScheduleForDay($dayOfWeek);
                $isDayOff = ($schedDay['is_day_off'] ?? ($isSat || $isSun));
            } else {
                $isDayOff = ($isSat || $isSun);
            }

            $isWorkShift = $shift ? $shift->isWorkShift() : (! $isDayOff);

            $row = [
                'day' => $dayNum,
                'dateKey' => $dateKey,
                'dayOfWeek' => $dayOfWeek,
                'isSaturday' => $isSat,
                'isSunday' => $isSun,
                'isHoliday' => $isHoliday,
                'isDayOff' => $isDayOff,
                'isWorkShift' => $isWorkShift,
                'hasEntries' => $hasEntries,
                'entr1' => '',
                'rubrica1' => '',
                'saida1' => '',
                'rubrica2' => '',
                'entr2' => '',
                'rubrica3' => '',
                'saida2' => '',
                'rubrica4' => '',
                'observation' => '',
                'statusClass' => '',
            ];

            // 1. Cenário: Modo Preenchimento Manual (Folha em Branco)
            if ($this->fillMode === 'blank') {
                if ($isHoliday) {
                    $row['rubrica1'] = 'FERIADO';
                    $row['rubrica2'] = 'FERIADO';
                    $row['rubrica3'] = 'FERIADO';
                    $row['rubrica4'] = 'FERIADO';
                    $row['observation'] = $holidayName ?? 'Feriado';
                } elseif ($isDayOff) {
                    $label = $isSat ? 'SÁBADO' : ($isSun ? 'DOMINGO' : 'FOLGA');
                    $row['rubrica1'] = $label;
                    $row['rubrica2'] = $label;
                    $row['rubrica3'] = $label;
                    $row['rubrica4'] = $label;
                    $row['observation'] = $isSun ? 'Descanso Semanal Remunerado' : 'Folga da escala';
                } else {
                    $row['entr1'] = ': ';
                    $row['saida1'] = ': ';
                    $row['entr2'] = ': ';
                    $row['saida2'] = ': ';
                }
                $rows[] = $row;
                continue;
            }

            // 2. Cenário: Modo Batidas do Sistema com Marcações Efetivas
            if ($hasEntries) {
                $rubricaText = $this->includeRubrica ? 'Registro eletrônico' : '';
                $effectiveEntries = $rawEntries
                    ->reject(fn ($entry) => ! empty($entry->is_disregarded))
                    ->sortBy('timestamp')
                    ->values();

                // Pareamento pela direção real da marcação (nunca pela posição no array)
                $pairs = [];
                foreach ($effectiveEntries as $entry) {
                    $time = Carbon::parse($entry->timestamp)->format('H:i');
                    $lastIndex = count($pairs) - 1;

                    if (($entry->type ?? 'in') === 'out') {
                        if ($lastIndex >= 0 && $pairs[$lastIndex]['in'] !== null && $pairs[$lastIndex]['out'] === null) {
                            $pairs[$lastIndex]['out'] = $time;
                        } else {
                            $pairs[] = ['in' => null, 'out' => $time];
                        }
                    } else {
                        $pairs[] = ['in' => $time, 'out' => null];
                    }
                }

                $slots = [['entr1', 'rubrica1', 'saida1', 'rubrica2'], ['entr2', 'rubrica3', 'saida2', 'rubrica4']];
                foreach (array_slice($pairs, 0, 2) as $index => $pair) {
                    [$inKey, $inRubricaKey, $outKey, $outRubricaKey] = $slots[$index];
                    $row[$inKey] = $pair['in'] ?? '';
                    $row[$inRubricaKey] = $pair['in'] !== null ? $rubricaText : '';
                    $row[$outKey] = $pair['out'] ?? '';
                    $row[$outRubricaKey] = $pair['out'] !== null ? $rubricaText : '';
                }

                $observations = [];
                $hasMissingExit = collect($pairs)->contains(fn ($pair) => $pair['in'] !== null && $pair['out'] === null);
                $hasMissingEntry = collect($pairs)->contains(fn ($pair) => $pair['in'] === null);
                if ($hasMissingExit) {
                    $observations[] = 'Incompleta: Saída pendente';
                }
                if ($hasMissingEntry) {
                    $observations[] = 'Incompleta: Entrada pendente';
                }

                if (count($pairs) > 2) {
                    $extras = [];
                    foreach (array_slice($pairs, 2) as $pair) {
                        if ($pair['in'] !== null) {
                            $extras[] = "{$pair['in']} (E)";
                        }
                        if ($pair['out'] !== null) {
                            $extras[] = "{$pair['out']} (S)";
                        }
                    }
                    $observations[] = 'Batidas extras: '.implode(', ', $extras);
                }

                $row['observation'] = implode(' | ', $observations);

                // Anexar notas de tratamentos aprovados
                if ($dayCalc && ! empty($dayCalc['notes'])) {
                    $noteStr = implode('; ', $dayCalc['notes']);
                    $row['observation'] = $row['observation'] ? "{$row['observation']} | {$noteStr}" : $noteStr;
                }

                // Identificar dia de fim de semana trabalhado
                if (($isSat || $isSun) && empty($row['observation'])) {
                    $row['observation'] = 'Escala / Plantão fim de semana';
                }

                $rows[] = $row;
                continue;
            }

            // 3. Cenário: Modo Batidas do Sistema sem Marcações no Dia
            if ($isHoliday) {
                $row['rubrica1'] = 'FERIADO';
                $row['rubrica2'] = 'FERIADO';
                $row['rubrica3'] = 'FERIADO';
                $row['rubrica4'] = 'FERIADO';
                $row['observation'] = $holidayName ?? 'Feriado previsto';
            } elseif ($isDayOff) {
                $label = $isSat ? 'SÁBADO' : ($isSun ? 'DOMINGO' : 'FOLGA');
                $row['rubrica1'] = $label;
                $row['rubrica2'] = $label;
                $row['rubrica3'] = $label;
                $row['rubrica4'] = $label;
                $row['observation'] = $isSun ? 'Descanso Semanal Remunerado' : 'Folga da escala';
            } else {
                // Dia de trabalho previsto sem registro
                if ($currentDate->isPast() && ! $currentDate->isToday()) {
                    $row['entr1'] = '--:--';
                    $row['saida1'] = '--:--';
                    $row['observation'] = 'Sem registro / Ausência';
                }
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Constrói as linhas para o Modelo de Jornada Noturna.
     */
    protected function buildNocturnalRows(
        int $daysInMonth,
        Employee $employee,
        ?WorkSchedule $workSchedule,
        $establishment,
        WorkCalendarService $calendarService,
        array $daysCalculated,
        array $groupedEntries
    ): array {
        $rows = [];

        $consumedMorningPunchDates = [];

        for ($d = 1; $d <= $daysInMonth; $d++) {
            $currentDate = Carbon::createFromDate($this->year, $this->month, $d)->locale('pt_BR');
            $dateKey = $currentDate->format('Y-m-d');
            $dayNum = sprintf('%02d', $d);

            $calendarDay = $calendarService->resolveDay($currentDate, $establishment);
            $isHoliday = $calendarDay->isHoliday;
            $holidayName = $calendarDay->appliedEvents[0]['name'] ?? ($isHoliday ? 'Feriado' : null);

            $dayCalc = $daysCalculated[$dateKey] ?? null;
            $rawEntries = $groupedEntries[$dateKey] ?? collect([]);
            
            // Se a primeira batida deste dia foi consumida como término do turno noturno anterior:
            if (in_array($dateKey, $consumedMorningPunchDates, true)) {
                if ($rawEntries->count() <= 1) {
                    $rawEntries = collect([]);
                } else {
                    $rawEntries = $rawEntries->slice(1)->values();
                }
            }

            $hasEntries = $rawEntries->isNotEmpty();

            $shift = ShiftAssignment::where('employee_id', $employee->id)
                ->active()
                ->whereDate('start_at_local', $dateKey)
                ->first();

            $isDayOff = $shift ? $shift->is_day_off : ($dayCalc['is_day_off'] ?? $currentDate->isWeekend());

            $row = [
                'day' => $dayNum,
                'dateFormatted' => $currentDate->format('d/m/Y'),
                'dayOfWeekName' => ucfirst($currentDate->translatedFormat('D')),
                'entryTime' => '--:--',
                'exitTime' => '--:--',
                'crossesMidnight' => false,
                'breakFormatted' => '00 min',
                'physicalWorkedFormatted' => '00h 00m',
                'physicalNightFormatted' => '00h 00m',
                'legalNightFormatted' => '00h 00m',
                'statusLabel' => 'Sem registro',
                'observation' => '',
                'rubrica' => '',
            ];

            if ($this->fillMode === 'blank') {
                if ($isHoliday) {
                    $row['statusLabel'] = 'FERIADO';
                    $row['observation'] = $holidayName ?? 'Feriado';
                } elseif ($isDayOff) {
                    $row['statusLabel'] = 'FOLGA DA ESCALA';
                    $row['observation'] = 'Descanso programado';
                } else {
                    $row['entryTime'] = ': ';
                    $row['exitTime'] = ': ';
                    $row['statusLabel'] = 'Jornada Prevista';
                }
                $rows[] = $row;
                continue;
            }

            if ($hasEntries) {
                $sorted = $rawEntries->sortBy('timestamp')->values();
                $first = $sorted->first();
                $last = $sorted->count() > 1 ? $sorted->last() : null;
                $crosses = false;

                // Se houver apenas uma batida noturna (>= 18h), busca a saída correspondente no dia seguinte (< 12h)
                if (! $last && Carbon::parse($first->timestamp)->hour >= 18) {
                    $nextDate = $currentDate->copy()->addDay();
                    $nextDateKey = $nextDate->format('Y-m-d');
                    $nextEntries = $groupedEntries[$nextDateKey] ?? collect([]);
                    if ($nextEntries->isNotEmpty()) {
                        $nextSorted = $nextEntries->sortBy('timestamp')->values();
                        $nextFirst = $nextSorted->first();
                        if (Carbon::parse($nextFirst->timestamp)->hour <= 12) {
                            $last = $nextFirst;
                            $crosses = true;
                            $consumedMorningPunchDates[] = $nextDateKey;
                        }
                    }
                } elseif ($last) {
                    $lastTime = Carbon::parse($last->timestamp);
                    $firstTime = Carbon::parse($first->timestamp);
                    $crosses = $lastTime->format('Y-m-d') !== $firstTime->format('Y-m-d') || $lastTime->hour < $firstTime->hour;
                }

                $row['entryTime'] = Carbon::parse($first->timestamp)->format('H:i');

                if ($last) {
                    $lastTime = Carbon::parse($last->timestamp);
                    $row['exitTime'] = $lastTime->format('H:i') . ($crosses ? ' (+1d)' : '');
                    $row['crossesMidnight'] = $crosses;
                } else {
                    $row['exitTime'] = '';
                    $row['observation'] = 'Incompleta: Saída pendente';
                }

                if ($dayCalc) {
                    $wm = (int) ($dayCalc['minutes'] ?? 0);
                    $nm = (int) ($dayCalc['night_minutes'] ?? 0);
                    $nlm = (int) ($dayCalc['night_equivalent_minutes'] ?? 0);

                    $row['physicalWorkedFormatted'] = sprintf('%02dh %02dm', intdiv($wm, 60), $wm % 60);
                    $row['physicalNightFormatted'] = sprintf('%02dh %02dm', intdiv($nm, 60), $nm % 60);
                    $row['legalNightFormatted'] = sprintf('%02dh %02dm', intdiv($nlm, 60), $nlm % 60);
                    $row['statusLabel'] = $dayCalc['status_label'] ?? 'Concluída';

                    if (! empty($dayCalc['notes'])) {
                        $row['observation'] = implode('; ', $dayCalc['notes']);
                    }
                }

                $row['rubrica'] = $this->includeRubrica ? 'Registro eletrônico' : '';
                $rows[] = $row;
                continue;
            }

            if ($isHoliday) {
                $row['statusLabel'] = 'FERIADO';
                $row['observation'] = $holidayName ?? 'Feriado previsto';
            } elseif ($isDayOff) {
                $row['statusLabel'] = 'FOLGA DA ESCALA';
                $row['observation'] = 'Descanso programado';
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Constrói as linhas para o Modelo de Escala de Plantões (12×36, 24×72 ou variáveis).
     */
    protected function buildShiftsRows(
        Employee $employee,
        Carbon $periodStart,
        Carbon $periodEnd,
        array $daysCalculated,
        array $groupedEntries,
        ?WorkSchedule $workSchedule
    ): array {
        $rows = [];

        // 1. Prioriza plantões planejados explicitamente em ShiftAssignment
        $shiftAssignments = ShiftAssignment::where('employee_id', $employee->id)
            ->whereBetween('start_at_local', [$periodStart, $periodEnd])
            ->orderBy('start_at_local')
            ->get();

        if ($shiftAssignments->isNotEmpty()) {
            foreach ($shiftAssignments as $shift) {
                $dateKey = $shift->start_at_local->format('Y-m-d');
                $dayCalc = $daysCalculated[$dateKey] ?? null;

                if ($shift->is_day_off) {
                    $rows[] = [
                        'startDate' => $shift->start_at_local->format('d/m/Y'),
                        'startTime' => '--:--',
                        'endDate' => $shift->end_at_local->format('d/m/Y'),
                        'endTime' => '--:--',
                        'intervalFormatted' => '--',
                        'physicalWorkedFormatted' => '00h 00m',
                        'physicalNightFormatted' => '00h 00m',
                        'legalNightFormatted' => '00h 00m',
                        'statusLabel' => 'Folga da Escala',
                        'statusBadge' => 'bg-slate-100 text-slate-700',
                        'observation' => $shift->notes ?: 'Descanso programado da escala',
                        'rubrica' => '--',
                    ];
                    continue;
                }

                $wm = (int) ($dayCalc['minutes'] ?? 0);
                $nm = (int) ($dayCalc['night_minutes'] ?? 0);
                $nlm = (int) ($dayCalc['night_equivalent_minutes'] ?? 0);

                if ($this->fillMode === 'blank') {
                    $rows[] = [
                        'startDate' => $shift->start_at_local->format('d/m/Y'),
                        'startTime' => $shift->start_at_local->format('H:i'),
                        'endDate' => $shift->end_at_local->format('d/m/Y'),
                        'endTime' => $shift->end_at_local->format('H:i'),
                        'intervalFormatted' => $shift->break_minutes > 0 ? "{$shift->break_minutes} min" : 'Sem intervalo',
                        'physicalWorkedFormatted' => '--:--',
                        'physicalNightFormatted' => '--:--',
                        'legalNightFormatted' => '--:--',
                        'statusLabel' => 'Preenchimento Manual',
                        'statusBadge' => 'bg-slate-100 text-slate-700',
                        'observation' => $shift->shift_type?->label() ?? 'Plantão Escala',
                        'rubrica' => '',
                    ];
                    continue;
                }

                $statusLabel = 'Concluído';
                $statusBadge = 'bg-emerald-50 text-emerald-800';
                if ($wm === 0) {
                    if ($shift->start_at_local->isPast()) {
                        $statusLabel = 'Falta / Sem registro';
                        $statusBadge = 'bg-rose-50 text-rose-800';
                    } else {
                        $statusLabel = 'Programado';
                        $statusBadge = 'bg-sky-50 text-sky-800';
                    }
                } elseif (! empty($dayCalc['is_incomplete'])) {
                    $statusLabel = 'Incompleto';
                    $statusBadge = 'bg-amber-50 text-amber-800';
                }

                $obs = $shift->shift_type?->label() ?? 'Plantão 12×36';
                if ($dayCalc && ! empty($dayCalc['notes'])) {
                    $obs .= ' | ' . implode('; ', $dayCalc['notes']);
                }

                $rows[] = [
                    'startDate' => $shift->start_at_local->format('d/m/Y'),
                    'startTime' => $shift->start_at_local->format('H:i'),
                    'endDate' => $shift->end_at_local->format('d/m/Y'),
                    'endTime' => $shift->end_at_local->format('H:i'),
                    'intervalFormatted' => $shift->break_minutes > 0 ? "{$shift->break_minutes} min" : 'Sem intervalo',
                    'physicalWorkedFormatted' => sprintf('%02dh %02dm', intdiv($wm, 60), $wm % 60),
                    'physicalNightFormatted' => sprintf('%02dh %02dm', intdiv($nm, 60), $nm % 60),
                    'legalNightFormatted' => sprintf('%02dh %02dm', intdiv($nlm, 60), $nlm % 60),
                    'statusLabel' => $statusLabel,
                    'statusBadge' => $statusBadge,
                    'observation' => $obs,
                    'rubrica' => $wm > 0 && $this->includeRubrica ? 'Registro eletrônico' : '--',
                ];
            }

            return $rows;
        }

        // 2. Se não houver ShiftAssignment explícito, monta a partir dos turnos reais do mês
        $daysInMonth = $periodStart->daysInMonth;
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $currentDate = Carbon::createFromDate($this->year, $this->month, $d);
            $dateKey = $currentDate->format('Y-m-d');
            $dayCalc = $daysCalculated[$dateKey] ?? null;
            $rawEntries = $groupedEntries[$dateKey] ?? collect([]);

            if ($rawEntries->isNotEmpty()) {
                $sorted = $rawEntries->sortBy('timestamp')->values();
                $first = $sorted->first();
                $last = $sorted->count() > 1 ? $sorted->last() : null;

                $startTime = Carbon::parse($first->timestamp)->format('H:i');
                $endTime = $last ? Carbon::parse($last->timestamp)->format('H:i') : '--:--';
                $endDate = $last ? Carbon::parse($last->timestamp)->format('d/m/Y') : $currentDate->format('d/m/Y');

                $wm = (int) ($dayCalc['minutes'] ?? 0);
                $nm = (int) ($dayCalc['night_minutes'] ?? 0);
                $nlm = (int) ($dayCalc['night_equivalent_minutes'] ?? 0);

                $rows[] = [
                    'startDate' => $currentDate->format('d/m/Y'),
                    'startTime' => $startTime,
                    'endDate' => $endDate,
                    'endTime' => $endTime,
                    'intervalFormatted' => '60 min',
                    'physicalWorkedFormatted' => sprintf('%02dh %02dm', intdiv($wm, 60), $wm % 60),
                    'physicalNightFormatted' => sprintf('%02dh %02dm', intdiv($nm, 60), $nm % 60),
                    'legalNightFormatted' => sprintf('%02dh %02dm', intdiv($nlm, 60), $nlm % 60),
                    'statusLabel' => $dayCalc['status_label'] ?? 'Concluído',
                    'statusBadge' => 'bg-emerald-50 text-emerald-800',
                    'observation' => 'Plantão Registrado',
                    'rubrica' => $this->includeRubrica ? 'Registro eletrônico' : '',
                ];
            }
        }

        return $rows;
    }
};
?>

<div class="print-wrapper py-2 sm:py-6 px-1 sm:px-4 lg:px-8">

    <!-- 1. BARRA SUPERIOR DE CONTROLES E FILTROS (ESCONDIDA NA IMPRESSÃO) -->
    <div class="no-print max-w-5xl mx-auto mb-6 bg-white rounded-2xl shadow-sm border border-gray-200/80 p-4 sm:p-6 space-y-4">
        
        <!-- Cabeçalho Operacional Limpo (Item 11) -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-gray-200 pb-4">
            <div>
                <h1 class="text-xl sm:text-2xl font-bold text-gray-900 flex items-center gap-2">
                    <svg class="w-6 h-6 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                    </svg>
                    Folha de Ponto
                </h1>
                <p class="text-xs sm:text-sm text-gray-500 mt-0.5">
                    Consulte e imprima os registros da competência.
                </p>
            </div>

            <!-- Botões de Ação -->
            <div class="flex items-center gap-2.5">
                <button type="button" 
                        onclick="window.print()" 
                        class="inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white rounded-xl text-sm font-bold shadow-md shadow-indigo-600/20 transition cursor-pointer min-h-[44px]">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24-1.076-.641-2.072-1.185-2.97a9.387 9.387 0 0 0-2.316-2.482M19.5 12c0-1.232-.046-2.453-.138-3.662a4.006 4.006 0 0 0-3.7-3.7 48.678 48.678 0 0 0-7.324 0 4.006 4.006 0 0 0-3.7 3.7c-.017.22-.032.441-.046.662M19.5 12l3-3m-3 3-3-3m-12 3c0 1.232.046 2.453.138 3.662a4.006 4.006 0 0 0 3.7 3.7 48.656 48.656 0 0 0 7.324 0 4.006 4.006 0 0 0 3.7-3.7c.017-.22.032-.441.046-.662M4.5 12l3 3m-3-3-3 3" />
                    </svg>
                    <span>Imprimir</span>
                </button>

                <a href="{{ route('timesheet', ['userId' => $this->userId, 'month' => $this->month, 'year' => $this->year]) }}" 
                   class="inline-flex items-center justify-center px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-sm font-semibold transition min-h-[44px]">
                    Voltar ao espelho
                </a>
            </div>
        </div>

        @if(! $isCompanyConfigured)
            <div class="p-3 bg-amber-50 border border-amber-200 rounded-xl text-amber-800 text-xs flex items-center gap-2">
                <svg class="w-4 h-4 text-amber-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                </svg>
                <span>
                    <strong>Atenção:</strong> Configuração cadastral da empresa incompleta ({{ implode(', ', $missingCompanyFields) }}). 
                    Acesse as configurações do sistema para preencher os dados oficiais da organização.
                </span>
            </div>
        @endif

        <!-- Filtros Principais Bem Organizados -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 pt-1">
            @if($canSelectUser)
            <div>
                <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Colaborador</label>
                <select wire:model.live="userId" class="block w-full px-3 py-2 text-sm border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white min-h-[44px]">
                    @foreach($selectableUsers as $u)
                        <option value="{{ $u->id }}">{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>
            @endif

            <div>
                <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Competência</label>
                <div class="grid grid-cols-2 gap-1.5">
                    <select wire:model.live="month" class="block w-full px-2 py-2 text-sm border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 bg-white min-h-[44px]">
                        @for($m = 1; $m <= 12; $m++)
                            <option value="{{ $m }}">{{ sprintf('%02d', $m) }} - {{ Carbon::create(null, $m, 1)->translatedFormat('F') }}</option>
                        @endfor
                    </select>

                    <select wire:model.live="year" class="block w-full px-2 py-2 text-sm border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 bg-white min-h-[44px]">
                        @for($y = now()->year - 2; $y <= now()->year + 1; $y++)
                            <option value="{{ $y }}">{{ $y }}</option>
                        @endfor
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Modelo da Folha</label>
                <select wire:model.live="reportModel" class="block w-full px-3 py-2 text-sm border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white min-h-[44px]">
                    <option value="auto">Automático (Recomendado: {{ $detectedModel === 'shifts' ? 'Plantões' : ($detectedModel === 'nocturnal' ? 'Jornada Noturna' : 'Administrativo') }})</option>
                    <option value="administrative">Administrativo (Convencional)</option>
                    <option value="nocturnal">Jornada Noturna (Atravessa Meia-Noite)</option>
                    <option value="shifts">Plantões (12×36, 24×72 ou Variáveis)</option>
                    <option value="blank">Folha em Branco (Preenchimento Manual)</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Modo de Preenchimento</label>
                <select wire:model.live="fillMode" class="block w-full px-3 py-2 text-sm border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white font-medium min-h-[44px]">
                    <option value="punches">Batidas do Sistema (Apuração PTRP)</option>
                    <option value="blank">Folha em Branco (Preenchimento Manual)</option>
                </select>
            </div>
        </div>

        <!-- Indicador de Status da Competência no Painel -->
        <div class="flex flex-wrap items-center justify-between gap-2 pt-2 border-t border-gray-100 text-xs">
            <div class="flex items-center gap-2">
                @if($isClosedPeriod)
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-slate-800 text-white font-medium">
                        <svg class="w-3.5 h-3.5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                        </svg>
                        Competência Fechada (Snapshot v{{ $snapshotVersion ?? '1' }})
                    </span>
                    <span class="text-gray-500">Documento histórico congelado. Cálculos e dados não sofrem recálculo.</span>
                @else
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-sky-100 text-sky-800 font-medium">
                        <svg class="w-3.5 h-3.5 text-sky-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                        Competência Aberta (Dados Provisórios)
                    </span>
                    <span class="text-gray-500">Apuração em tempo real sujeita a tratamentos e fechamento.</span>
                @endif
            </div>

            <div class="flex items-center gap-2">
                <label class="flex items-center gap-1.5 cursor-pointer text-gray-700">
                    <input type="checkbox" wire:model.live="includeRubrica" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    <span>Exibir registro eletrônico</span>
                </label>
            </div>
        </div>

        <!-- Campos de Metadados Editáveis na Prévia (Apenas usuários autorizados) -->
        <div x-data="{ showAdvanced: false }" class="pt-2 border-t border-gray-100">
            <button @click="showAdvanced = !showAdvanced" type="button" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800 flex items-center gap-1 focus:outline-hidden">
                <span x-text="showAdvanced ? '− Ocultar Personalização de Prévia' : '+ Ajustar Cargo / Vínculo / Carga Horária na Prévia'"></span>
            </button>

            <div x-show="showAdvanced" x-cloak class="mt-3 p-3 bg-slate-50 border border-slate-200 rounded-xl space-y-2">
                <div class="text-[11px] text-slate-500">
                    <strong>Nota:</strong> Alterações nestes campos afetam apenas esta prévia/impressão e <u>não</u> sobrescrevem o cadastro definitivo do servidor.
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 lg:grid-cols-5 gap-2">
                    <div>
                        <label class="block text-[10px] font-semibold text-gray-600 uppercase mb-0.5">Cargo</label>
                        <input type="text" wire:model.live="jobTitle" wire:change="$set('isEditedForPreview', true)" class="block w-full px-2.5 py-1.5 text-xs border border-gray-300 rounded-lg bg-white">
                    </div>
                    <div>
                        <label class="block text-[10px] font-semibold text-gray-600 uppercase mb-0.5">Vínculo</label>
                        <input type="text" wire:model.live="contractType" wire:change="$set('isEditedForPreview', true)" class="block w-full px-2.5 py-1.5 text-xs border border-gray-300 rounded-lg bg-white">
                    </div>
                    <div>
                        <label class="block text-[10px] font-semibold text-gray-600 uppercase mb-0.5">Carga Horária</label>
                        <input type="text" wire:model.live="workload" wire:change="$set('isEditedForPreview', true)" class="block w-full px-2.5 py-1.5 text-xs border border-gray-300 rounded-lg bg-white">
                    </div>
                    <div>
                        <label class="block text-[10px] font-semibold text-gray-600 uppercase mb-0.5">Zona</label>
                        <input type="text" wire:model.live="zone" wire:change="$set('isEditedForPreview', true)" class="block w-full px-2.5 py-1.5 text-xs border border-gray-300 rounded-lg bg-white">
                    </div>
                    <div>
                        <label class="block text-[10px] font-semibold text-gray-600 uppercase mb-0.5">Local / Setor</label>
                        <input type="text" wire:model.live="location" wire:change="$set('isEditedForPreview', true)" class="block w-full px-2.5 py-1.5 text-xs border border-gray-300 rounded-lg bg-white">
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- 2. VISUALIZAÇÃO MOBILE-FIRST (SMARTPHONES < 640px) (Item 12) -->
    <div class="sm:hidden no-print max-w-xl mx-auto mb-6 space-y-4">
        <!-- Card Executivo Mobile de Resumo -->
        <div class="bg-white rounded-2xl border border-gray-200/80 p-4 shadow-xs space-y-3">
            <div class="flex items-center justify-between border-b border-gray-100 pb-2.5">
                <div>
                    <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Servidor</div>
                    <div class="text-base font-bold text-gray-900">{{ $selectedUser->name }}</div>
                </div>
                <div class="text-right">
                    <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Competência</div>
                    <div class="text-sm font-bold text-indigo-600">{{ $monthNameUpper }}/{{ $year }}</div>
                </div>
            </div>

            <!-- Grid de Indicadores no Mobile -->
            <div class="grid grid-cols-2 gap-2 text-center">
                <div class="p-2.5 bg-slate-50 rounded-xl border border-slate-100">
                    <span class="block text-[10px] uppercase font-bold text-slate-500">Horas Físicas</span>
                    <span class="text-base font-bold font-mono text-slate-900">{{ $totalWorkedFormatted }}</span>
                </div>
                <div class="p-2.5 bg-slate-50 rounded-xl border border-slate-100">
                    <span class="block text-[10px] uppercase font-bold text-slate-500">Horas Noturnas</span>
                    <span class="text-base font-bold font-mono text-indigo-600">{{ $totalPhysicalNightFormatted }}</span>
                </div>
                <div class="p-2.5 bg-slate-50 rounded-xl border border-slate-100">
                    <span class="block text-[10px] uppercase font-bold text-slate-500">Horas Previstas</span>
                    <span class="text-sm font-bold font-mono text-slate-700">{{ $totalScheduledFormatted }}</span>
                </div>
                <div class="p-2.5 bg-slate-50 rounded-xl border border-slate-100">
                    <span class="block text-[10px] uppercase font-bold text-slate-500">Ocorrências</span>
                    <span class="text-sm font-bold font-mono {{ $incompleteDaysCount > 0 ? 'text-amber-600' : 'text-emerald-600' }}">
                        {{ $incompleteDaysCount > 0 ? $incompleteDaysCount . ' pendente(s)' : 'Nenhuma' }}
                    </span>
                </div>
            </div>

            <!-- Botão de Alternância da Prévia A4 Completa -->
            <button type="button" 
                    wire:click="$toggle('showMobileFullDoc')"
                    class="w-full py-2.5 px-4 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 rounded-xl text-xs font-bold transition flex items-center justify-center gap-1.5 min-h-[44px]">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15" />
                </svg>
                <span>{{ $showMobileFullDoc ? 'Ocultar Prévia A4 Completa' : 'Visualizar Documento A4 Completo' }}</span>
            </button>
        </div>

        <!-- Lista Mobile Legível de Jornadas / Plantões -->
        <div class="bg-white rounded-2xl border border-gray-200/80 p-4 shadow-xs space-y-3">
            <h2 class="text-xs font-bold text-gray-700 uppercase tracking-wider">
                Registros do Mês ({{ $activeModel === 'shifts' ? 'Plantões' : 'Jornadas Diárias' }})
            </h2>

            <div class="space-y-2 max-h-96 overflow-y-auto pr-1">
                @if($activeModel === 'shifts')
                    @forelse($shiftsRows as $shift)
                        <div class="p-2.5 rounded-xl border border-gray-100 bg-slate-50/70 text-xs space-y-1">
                            <div class="flex items-center justify-between font-bold">
                                <span>{{ $shift['startDate'] }} {{ $shift['startTime'] }} → {{ $shift['endDate'] }} {{ $shift['endTime'] }}</span>
                                <span class="px-2 py-0.5 rounded text-[10px] {{ $shift['statusBadge'] }}">{{ $shift['statusLabel'] }}</span>
                            </div>
                            <div class="flex items-center justify-between text-[11px] text-gray-600">
                                <span>Físicas: <strong>{{ $shift['physicalWorkedFormatted'] }}</strong></span>
                                <span>Noturnas: <strong>{{ $shift['physicalNightFormatted'] }}</strong></span>
                            </div>
                            @if(!empty($shift['observation']))
                                <div class="text-[10px] text-gray-500 italic">{{ $shift['observation'] }}</div>
                            @endif
                        </div>
                    @empty
                        <div class="text-center py-4 text-xs text-gray-400">Nenhum plantão registrado no período.</div>
                    @endforelse
                @else
                    @foreach($administrativeRows as $row)
                        @if($row['hasEntries'] || $row['isHoliday'] || $row['isDayOff'])
                        <div class="p-2.5 rounded-xl border border-gray-100 {{ $row['hasEntries'] ? 'bg-white' : 'bg-slate-50' }} text-xs space-y-1">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-gray-900">Dia {{ $row['day'] }}</span>
                                @if($row['hasEntries'])
                                    <span class="text-indigo-600 font-mono font-bold">{{ $row['entr1'] }} - {{ $row['saida1'] ?: '--:--' }}</span>
                                @elseif($row['isHoliday'])
                                    <span class="text-amber-700 font-semibold text-[11px]">FERIADO</span>
                                @else
                                    <span class="text-gray-500 text-[11px]">FOLGA DA ESCALA</span>
                                @endif
                            </div>
                            @if(!empty($row['observation']))
                                <div class="text-[10px] text-gray-500">{{ $row['observation'] }}</div>
                            @endif
                        </div>
                        @endif
                    @endforeach
                @endif
            </div>
        </div>
    </div>

    <!-- 3. FOLHA DE PONTO OFICIAL (A4 CENTRALIZADA COM SUPORTE A IMPRESSÃO E MULTIPÁGINAS) -->
    <div class="{{ ! $showMobileFullDoc ? 'hidden sm:block' : 'block' }} overflow-x-auto">
        <div class="print-container max-w-[820px] mx-auto bg-white border border-gray-300 shadow-md p-6 sm:p-8 text-black print-sheet" style="font-family: Arial, Helvetica, 'Times New Roman', sans-serif;">
            
            <!-- CABEÇALHO DA EMPRESA / ESTABELECIMENTO (Item 1 e 8) -->
            <div class="flex items-center gap-4 border-b border-black pb-2 mb-2">
                <!-- Logotipo Real da Empresa -->
                <div class="w-16 h-16 shrink-0 flex items-center justify-center overflow-hidden">
                    @if(!empty($companyLogoUrl))
                        <img src="{{ $companyLogoUrl }}" 
                             alt="Logotipo Institucional" 
                             class="max-h-16 max-w-16 object-contain" 
                             onerror="this.style.display='none'; this.nextElementSibling.classList.remove('hidden');" />
                        <div class="hidden text-gray-400">
                            <svg class="w-12 h-12" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                            </svg>
                        </div>
                    @else
                        <div class="text-gray-400">
                            <svg class="w-12 h-12" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                            </svg>
                        </div>
                    @endif
                </div>

                <!-- Dados Institucionais Centralizados -->
                <div class="flex-1 text-center text-black leading-tight">
                    @if(!empty($headerState))
                        <div class="text-[12px] font-bold tracking-wider uppercase">{{ $headerState }}</div>
                    @endif
                    <div class="text-[14px] font-black uppercase tracking-tight">
                        {{ !empty($headerEntity) ? $headerEntity : 'EMPRESA / ENTIDADE NÃO CONFIGURADA' }}
                    </div>
                    @if(!empty($headerSubEntity))
                        <div class="text-[12px] font-bold uppercase">{{ $headerSubEntity }}</div>
                    @endif
                    <div class="text-[10px] mt-0.5 font-normal">
                        {{ !empty($headerAddress) ? $headerAddress : 'Endereço não cadastrado' }} &nbsp; 
                        <strong>CNPJ:</strong> {{ !empty($headerCnpj) ? $headerCnpj : 'Não cadastrado' }}
                    </div>
                    @if(!empty($headerPhone) || !empty($headerEmail))
                        <div class="text-[10px] font-normal">
                            @if(!empty($headerPhone))<strong>Telefone:</strong> {{ $headerPhone }} &nbsp;@endif
                            @if(!empty($headerEmail))<strong>EMAIL:</strong> {{ $headerEmail }}@endif
                        </div>
                    @endif
                </div>
            </div>

            <!-- TÍTULO DO DOCUMENTO COM INDICAÇÃO DE MODELO -->
            <div class="text-center my-2">
                <h2 class="text-[14px] sm:text-[15px] font-black uppercase tracking-wide">
                    @if($activeModel === 'shifts')
                        FOLHA DE FREQUÊNCIA — ESCALA DE PLANTÕES
                    @elseif($activeModel === 'nocturnal')
                        FOLHA DE PONTO — JORNADA NOTURNA
                    @elseif($activeModel === 'blank' || $fillMode === 'blank')
                        FOLHA DE PONTO — DOCUMENTO PARA PREENCHIMENTO MANUAL
                    @else
                        FOLHA DE PONTO DE FUNCIONÁRIO
                    @endif
                </h2>
                
                <!-- Tarja de Status da Competência (Item 7) -->
                <div class="text-[9px] font-semibold text-gray-700 tracking-wider uppercase mt-0.5">
                    @if($isClosedPeriod)
                        [COMPETÊNCIA FECHADA] Documento consolidado e imutável (Snapshot v{{ $snapshotVersion ?? '1' }})
                    @elseif($fillMode === 'blank' || $activeModel === 'blank')
                        [FORMULÁRIO OFICIAL] Pronto para preenchimento manuscrito e arquivamento
                    @else
                        [COMPETÊNCIA ABERTA] Dados provisórios sujeitos a apuração e fechamento
                    @endif
                    @if($isEditedForPreview)
                        — [PRÉVIA EDITADA] Campos cadastrais ajustados apenas para esta visualização
                    @endif
                </div>
            </div>

            <!-- METADADOS DO SERVIDOR / COMPETÊNCIA -->
            <div class="text-[11px] leading-relaxed mb-2 font-normal space-y-0.5">
                <div class="flex justify-between items-baseline">
                    <div>
                        <strong>Competência/{{ $monthNameUpper }}- Ano:</strong> {{ $year }}
                    </div>
                    <div>
                        <strong>Carga Horária:</strong> {{ $workload ?: '40h' }}
                    </div>
                    <div class="w-32 text-left">
                        <strong>Zona:</strong> {{ $zone ?: 'Urbana' }}
                    </div>
                </div>

                <div class="flex justify-between items-baseline">
                    <div class="flex-1 truncate mr-4">
                        <strong>Nome do Servidor:</strong> <span class="uppercase font-semibold">{{ mb_strtoupper($selectedUser->name) }}</span>
                    </div>
                    <div class="w-48 truncate">
                        <strong>Cargo:</strong> {{ $jobTitle ?: 'Colaborador' }}
                    </div>
                    <div class="w-32 text-left">
                        <strong>Vínculo:</strong> {{ $contractType ?: 'CLT' }}
                    </div>
                </div>

                <div class="flex justify-between items-baseline">
                    <div class="flex-1 truncate mr-4">
                        <strong>Local:</strong> {{ $location ?: (optional(optional($selectedUser->employee)->sector)->name ?? 'Geral') }}
                    </div>
                    <div class="w-56 truncate text-right">
                        <strong>Escala:</strong> {{ $workSchedule?->name ?? 'Padrão Administrativo' }}
                    </div>
                </div>
            </div>

            <!-- QUADRO DE RESUMO DE JORNADA INSTITUCIONAL E LEGAL (Item 6) -->
            <div class="border border-black p-2 mb-3 text-[10px] bg-slate-50/50 print:bg-transparent">
                @if($isPendingConfigOverall)
                    <div class="text-amber-800 font-bold text-center">
                        Apuração pendente de configuração ou conferência.
                    </div>
                @else
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-center">
                        <div>
                            <span class="block text-gray-600 uppercase font-semibold text-[9px]">Horas Físicas</span>
                            <span class="font-mono font-bold text-[11px]">{{ $totalWorkedFormatted }}</span>
                        </div>
                        <div>
                            <span class="block text-gray-600 uppercase font-semibold text-[9px]">Horas Previstas</span>
                            <span class="font-mono text-[11px]">{{ $totalScheduledFormatted }}</span>
                        </div>
                        <div>
                            <span class="block text-gray-600 uppercase font-semibold text-[9px]">Horas Noturnas (Físicas)</span>
                            <span class="font-mono font-bold text-[11px]">{{ $totalPhysicalNightFormatted }}</span>
                        </div>
                        <div>
                            <span class="block text-gray-600 uppercase font-semibold text-[9px]">Equiv. Legal Noturna</span>
                            <span class="font-mono text-[11px]">{{ $totalLegalNightFormatted }}</span>
                        </div>
                    </div>
                @endif
            </div>

            <!-- ========================================================================= -->
            <!-- MODELO 1: ADMINISTRATIVO (CONVENCIONAL) (Item 1, 2, 3) -->
            <!-- ========================================================================= -->
            @if($activeModel === 'administrative' || $activeModel === 'blank')
                <div class="w-full overflow-hidden">
                    <table class="w-full border-collapse border border-black text-center text-[10px] leading-tight">
                        <thead>
                            <tr class="font-bold text-[10px] bg-slate-50 print:bg-transparent">
                                <th colspan="5" class="border border-black py-1 tracking-wide">
                                    Horário Matutino
                                </th>
                                <th colspan="4" class="border border-black py-1 tracking-wide">
                                    Horário Vespertino
                                </th>
                                <th class="border border-black py-1 tracking-wide w-[90px]">
                                    Observações
                                </th>
                            </tr>
                            <tr class="font-bold text-[9px] bg-slate-50 print:bg-transparent">
                                <th class="border border-black py-1 w-[32px]">DIA</th>
                                <th class="border border-black py-1 w-[55px]">ENTR 1</th>
                                <th class="border border-black py-1 w-[75px]">RUBRICA</th>
                                <th class="border border-black py-1 w-[55px]">SAÍDA 1</th>
                                <th class="border border-black py-1 w-[75px]">RUBRICA</th>
                                <th class="border border-black py-1 w-[55px]">ENTR 2</th>
                                <th class="border border-black py-1 w-[75px]">RUBRICA</th>
                                <th class="border border-black py-1 w-[55px]">SAÍDA 2</th>
                                <th class="border border-black py-1 w-[75px]">RUBRICA</th>
                                <th class="border border-black py-1">OCORRÊNCIA</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($administrativeRows as $row)
                                <tr class="h-[18px] sm:h-[19px] print:h-[17px] {{ ($row['isSaturday'] || $row['isSunday']) && ! $row['hasEntries'] ? 'bg-slate-100/50 print:bg-transparent' : '' }}">
                                    <!-- Dia -->
                                    <td class="border border-black font-bold text-[9px]">
                                        {{ $row['day'] }}
                                    </td>

                                    <!-- ENTR 1 -->
                                    <td class="border border-black font-mono text-[9px] {{ $row['entr1'] && $row['entr1'] !== ': ' ? 'font-bold' : 'text-slate-400 print:text-black' }}">
                                        {{ $row['entr1'] }}
                                    </td>

                                    <!-- RUBRICA 1 -->
                                    <td class="border border-black text-[8px] {{ in_array($row['rubrica1'], ['SÁBADO', 'DOMINGO', 'FERIADO']) ? 'font-bold' : '' }}">
                                        {{ $row['rubrica1'] }}
                                    </td>

                                    <!-- SAÍDA 1 -->
                                    <td class="border border-black font-mono text-[9px] {{ $row['saida1'] && $row['saida1'] !== ': ' ? 'font-bold' : 'text-slate-400 print:text-black' }}">
                                        {{ $row['saida1'] }}
                                    </td>

                                    <!-- RUBRICA 2 -->
                                    <td class="border border-black text-[8px] {{ in_array($row['rubrica2'], ['SÁBADO', 'DOMINGO', 'FERIADO']) ? 'font-bold' : '' }}">
                                        {{ $row['rubrica2'] }}
                                    </td>

                                    <!-- ENTR 2 -->
                                    <td class="border border-black font-mono text-[9px] {{ $row['entr2'] && $row['entr2'] !== ': ' ? 'font-bold' : 'text-slate-400 print:text-black' }}">
                                        {{ $row['entr2'] }}
                                    </td>

                                    <!-- RUBRICA 3 -->
                                    <td class="border border-black text-[8px] {{ in_array($row['rubrica3'], ['SÁBADO', 'DOMINGO', 'FERIADO']) ? 'font-bold' : '' }}">
                                        {{ $row['rubrica3'] }}
                                    </td>

                                    <!-- SAÍDA 2 -->
                                    <td class="border border-black font-mono text-[9px] {{ $row['saida2'] && $row['saida2'] !== ': ' ? 'font-bold' : 'text-slate-400 print:text-black' }}">
                                        {{ $row['saida2'] }}
                                    </td>

                                    <!-- RUBRICA 4 -->
                                    <td class="border border-black text-[8px] {{ in_array($row['rubrica4'], ['SÁBADO', 'DOMINGO', 'FERIADO']) ? 'font-bold' : '' }}">
                                        {{ $row['rubrica4'] }}
                                    </td>

                                    <!-- OCORRÊNCIAS / OBSERVAÇÕES -->
                                    <td class="border border-black text-[8px] text-left px-1 truncate max-w-[110px]" title="{{ $row['observation'] }}">
                                        {{ $row['observation'] }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

            <!-- ========================================================================= -->
            <!-- MODELO 2: JORNADA NOTURNA (ATRAVESSA MEIA-NOITE) (Item 2 e 4) -->
            <!-- ========================================================================= -->
            @elseif($activeModel === 'nocturnal')
                <div class="w-full overflow-hidden">
                    <table class="w-full border-collapse border border-black text-center text-[10px] leading-tight">
                        <thead>
                            <tr class="font-bold text-[9px] bg-slate-50 print:bg-transparent">
                                <th class="border border-black py-1 w-[70px]">DATA</th>
                                <th class="border border-black py-1 w-[55px]">ENTRADA</th>
                                <th class="border border-black py-1 w-[65px]">SAÍDA</th>
                                <th class="border border-black py-1 w-[60px]">HORAS FÍS.</th>
                                <th class="border border-black py-1 w-[60px]">H. NOTURNA</th>
                                <th class="border border-black py-1 w-[60px]">EQ. LEGAL</th>
                                <th class="border border-black py-1 w-[70px]">SITUAÇÃO</th>
                                <th class="border border-black py-1">OBSERVAÇÕES</th>
                                <th class="border border-black py-1 w-[80px]">CONFERÊNCIA</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($nocturnalRows as $row)
                                <tr class="h-[18px] sm:h-[19px] print:h-[17px]">
                                    <td class="border border-black font-bold text-[9px]">
                                        {{ $row['day'] }} ({{ $row['dayOfWeekName'] }})
                                    </td>
                                    <td class="border border-black font-mono text-[9px] font-bold">
                                        {{ $row['entryTime'] }}
                                    </td>
                                    <td class="border border-black font-mono text-[9px] font-bold {{ $row['crossesMidnight'] ? 'text-indigo-700 print:text-black' : '' }}">
                                        {{ $row['exitTime'] }}
                                    </td>
                                    <td class="border border-black font-mono text-[9px]">
                                        {{ $row['physicalWorkedFormatted'] }}
                                    </td>
                                    <td class="border border-black font-mono text-[9px] font-bold">
                                        {{ $row['physicalNightFormatted'] }}
                                    </td>
                                    <td class="border border-black font-mono text-[9px]">
                                        {{ $row['legalNightFormatted'] }}
                                    </td>
                                    <td class="border border-black text-[8px] font-semibold">
                                        {{ $row['statusLabel'] }}
                                    </td>
                                    <td class="border border-black text-[8px] text-left px-1 truncate max-w-[130px]">
                                        {{ $row['observation'] }}
                                    </td>
                                    <td class="border border-black text-[8px]">
                                        {{ $row['rubrica'] }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

            <!-- ========================================================================= -->
            <!-- MODELO 3: PLANTÕES (12×36, 24×72 OU VARIÁVEIS) (Item 2 e 4) -->
            <!-- ========================================================================= -->
            @elseif($activeModel === 'shifts')
                <div class="w-full overflow-hidden">
                    <table class="w-full border-collapse border border-black text-center text-[10px] leading-tight">
                        <thead>
                            <tr class="font-bold text-[9px] bg-slate-50 print:bg-transparent">
                                <th class="border border-black py-1 w-[70px]">INÍCIO (DATA)</th>
                                <th class="border border-black py-1 w-[45px]">HORA</th>
                                <th class="border border-black py-1 w-[70px]">FIM (DATA)</th>
                                <th class="border border-black py-1 w-[45px]">HORA</th>
                                <th class="border border-black py-1 w-[55px]">INTERVALO</th>
                                <th class="border border-black py-1 w-[60px]">H. FÍSICAS</th>
                                <th class="border border-black py-1 w-[60px]">H. NOTURNA</th>
                                <th class="border border-black py-1 w-[60px]">EQ. LEGAL</th>
                                <th class="border border-black py-1 w-[70px]">SITUAÇÃO</th>
                                <th class="border border-black py-1">OBSERVAÇÕES</th>
                                <th class="border border-black py-1 w-[80px]">CONFERÊNCIA</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($shiftsRows as $shift)
                                <tr class="h-[18px] sm:h-[19px] print:h-[17px]">
                                    <td class="border border-black font-bold text-[9px]">
                                        {{ $shift['startDate'] }}
                                    </td>
                                    <td class="border border-black font-mono text-[9px] font-bold">
                                        {{ $shift['startTime'] }}
                                    </td>
                                    <td class="border border-black font-bold text-[9px]">
                                        {{ $shift['endDate'] }}
                                    </td>
                                    <td class="border border-black font-mono text-[9px] font-bold">
                                        {{ $shift['endTime'] }}
                                    </td>
                                    <td class="border border-black text-[8px]">
                                        {{ $shift['intervalFormatted'] }}
                                    </td>
                                    <td class="border border-black font-mono text-[9px] font-bold">
                                        {{ $shift['physicalWorkedFormatted'] }}
                                    </td>
                                    <td class="border border-black font-mono text-[9px] font-bold">
                                        {{ $shift['physicalNightFormatted'] }}
                                    </td>
                                    <td class="border border-black font-mono text-[9px]">
                                        {{ $shift['legalNightFormatted'] }}
                                    </td>
                                    <td class="border border-black text-[8px] font-semibold">
                                        {{ $shift['statusLabel'] }}
                                    </td>
                                    <td class="border border-black text-[8px] text-left px-1 truncate max-w-[120px]">
                                        {{ $shift['observation'] }}
                                    </td>
                                    <td class="border border-black text-[8px]">
                                        {{ $shift['rubrica'] }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="11" class="border border-black py-3 text-gray-500 text-xs">
                                        Nenhum plantão agendado ou registrado na competência.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endif

            <!-- BLOCO DE ASSINATURAS FÍSICAS (Item 9) -->
            <div class="mt-8 page-break-avoid text-black">
                <!-- Linha 1: Servidor, Coordenador, Responsável pelo Setor -->
                <div class="grid grid-cols-3 gap-6 text-center text-[10px]">
                    <div>
                        <div class="border-b border-black w-4/5 mx-auto mb-1"></div>
                        <span class="font-bold tracking-wider">SERVIDOR (a)</span>
                    </div>

                    <div>
                        <div class="border-b border-black w-4/5 mx-auto mb-1"></div>
                        <span class="font-bold tracking-wider">Coordenador (a)</span>
                    </div>

                    <div>
                        <div class="border-b border-black w-4/5 mx-auto mb-1"></div>
                        <span class="font-bold tracking-wider">RESPONSÁVEL PELO SETOR</span>
                    </div>
                </div>

                <!-- Linha 2: Setor de Recursos Humanos -->
                <div class="mt-6 text-center text-[10px]">
                    <div class="border-b border-black w-56 mx-auto mb-1"></div>
                    <span class="font-bold tracking-wider">Setor de Recursos Humanos</span>
                </div>
            </div>

        </div>
    </div>

</div>

<!-- ESTILOS ESPECÍFICOS DE IMPRESSÃO (A4 100% CONFORME) (Item 10) -->
<style>
    @media print {
        @page {
            size: A4 portrait;
            margin: 8mm 8mm 8mm 8mm;
        }

        /* Oculta tudo que não faz parte do documento formal */
        .no-print,
        header,
        nav,
        aside,
        button,
        footer,
        .fixed,
        [role="dialog"] {
            display: none !important;
        }

        body, html {
            background: #ffffff !important;
            color: #000000 !important;
            margin: 0 !important;
            padding: 0 !important;
            font-size: 10pt !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        /* Reset all layout wrappers on print */
        div[class*="md:pl-"], div[class*="pl-"], main, .print-wrapper {
            padding: 0 !important;
            margin: 0 !important;
            width: 100% !important;
        }

        .print-container {
            max-width: 100% !important;
            width: 100% !important;
            border: none !important;
            box-shadow: none !important;
            padding: 0 !important;
            margin: 0 auto !important;
        }

        table {
            border-collapse: collapse !important;
            width: 100% !important;
        }

        th, td {
            border: 1px solid #000000 !important;
            padding-top: 1px !important;
            padding-bottom: 1px !important;
        }

        .page-break-avoid {
            page-break-inside: avoid !important;
            break-inside: avoid !important;
        }

        thead {
            display: table-header-group;
        }

        tr {
            break-inside: avoid;
            page-break-inside: avoid;
        }
    }
</style>
