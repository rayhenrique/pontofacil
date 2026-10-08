<?php

namespace App\Domain\Compliance\AEJ;

use App\Domain\Compliance\Fiscal\Services\FiscalHashService;
use App\Domain\PTRP\Actions\CalculateDailyJourneyAction;
use App\Models\ClosedPeriod;
use App\Models\Employee;
use App\Models\Establishment;
use App\Models\TimeBankAccount;
use App\Models\TimeBankPolicy;
use App\Models\TimeBankTransaction;
use App\Models\WorkSchedule;
use Carbon\Carbon;

class AejGenerator_2026_07_31 implements AejGeneratorInterface
{
    public const LAYOUT_VERSION = '001';

    protected FiscalHashService $fiscalHashService;

    public function __construct(?FiscalHashService $fiscalHashService = null)
    {
        $this->fiscalHashService = $fiscalHashService ?: new FiscalHashService;
    }

    public function generate(
        Establishment $establishment,
        int $year,
        int $month,
        ?Carbon $generationTime = null,
        bool $forcePreview = false,
    ): AejExportResult {
        $company = $establishment->company;
        $genTime = $generationTime ?: Carbon::now($establishment->timezone ?: 'America/Maceio');

        $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endDate = Carbon::createFromDate($year, $month, 1)->endOfMonth();

        $closedPeriod = ClosedPeriod::findForPeriod($year, $month);
        $isClosed = $closedPeriod && $closedPeriod->status === 'closed';

        if (! $isClosed && ! $forcePreview) {
            throw new \DomainException(sprintf(
                'A competência %02d/%04d ainda não foi fechada. Para emitir o AEJ fiscal definitivo, feche a competência formalmente. Para conferência interna, gere como Prévia.',
                $month,
                $year
            ));
        }

        $isPreview = ! $isClosed;

        // 1. Obter snapshots: ou congelados do ClosedPeriod, ou calculados transientes em modo prévia
        $employeeDataList = $this->resolveEmployeeDataList(
            $establishment,
            $year,
            $month,
            $startDate,
            $endDate,
            $isClosed,
            $closedPeriod
        );

        $lines = [];

        $count01 = 0;
        $count02 = 0;
        $count03 = 0;
        $count04 = 0;
        $count05 = 0;
        $count06 = 0;
        $count07 = 0;
        $count08 = 0;

        // 1. Registro 01: Cabeçalho
        $count01++;
        $tpIdtEmpregador = ($establishment->identifier_type === 'cpf') ? '2' : '1';
        $idtEmpregador = preg_replace('/\D/', '', $establishment->identifier_number);
        $caepf = '';
        $cno = '';
        $razaoOuNome = $this->sanitize($company->legal_name);
        $dataInicialAej = $startDate->format('Y-m-d');
        $dataFinalAej = $endDate->format('Y-m-d');
        $dataHoraGerAej = $this->fiscalHashService->formatDateTimeIso($genTime);
        $versaoAej = self::LAYOUT_VERSION;

        $lines[] = implode('|', ['01', $tpIdtEmpregador, $idtEmpregador, $caepf, $cno, $razaoOuNome, $dataInicialAej, $dataFinalAej, $dataHoraGerAej, $versaoAej]);

        // 2. Registro 02: REPs Utilizados
        $count02++;
        $idRepAej = '1';
        $tpRep = '3'; // REP-P
        $nrRep = $company->isRegisteredInpi()
            ? preg_replace('/\D/', '', (string) $company->inpi_registration_number)
            : '';
        $lines[] = implode('|', ['02', $idRepAej, $tpRep, $nrRep]);

        // 3. Registro 03: Vínculos (Empregados)
        $vinculoMap = [];
        $vinculoCounter = 0;
        $cpfCounts = [];
        foreach ($employeeDataList as $item) {
            $cleanCpf = str_pad(substr(preg_replace('/\D/', '', $item['employee']['cpf'] ?? ''), 0, 11), 11, '0', STR_PAD_LEFT);
            $cpfCounts[$cleanCpf] = ($cpfCounts[$cleanCpf] ?? 0) + 1;
        }

        foreach ($employeeDataList as $item) {
            $vinculoCounter++;
            $count03++;
            $empId = $item['employee']['id'] ?? $vinculoCounter;
            $vinculoMap[$empId] = (string) $vinculoCounter;

            $cpf = str_pad(substr(preg_replace('/\D/', '', $item['employee']['cpf'] ?? ''), 0, 11), 11, '0', STR_PAD_LEFT);
            $nomeEmp = $this->sanitize($item['employee']['name'] ?? '');

            $lines[] = implode('|', ['03', (string) $vinculoCounter, $cpf, $nomeEmp]);
        }

        // 4. Registro 04: Horários Contratuais
        $processedSchedules = [];
        $scheduleCounter = 0;
        foreach ($employeeDataList as $item) {
            $sched = $item['schedule'];
            $schedId = (string) ($sched['id'] ?? 1);
            if (isset($processedSchedules[$schedId])) {
                continue;
            }
            $scheduleCounter++;
            $count04++;
            $processedSchedules[$schedId] = (string) $scheduleCounter;

            $weeklyHours = (int) ($sched['weekly_hours'] ?? 40);
            $durJornada = (int) ($weeklyHours * 60 / 5);
            if ($durJornada <= 0) {
                $durJornada = 480;
            }

            $e1 = '0800';
            $s1 = '1200';
            $e2 = '1300';
            $s2 = '1700';

            $daysConfig = $sched['days_config'] ?? [];
            if (! empty($daysConfig[1]['periods'][0])) {
                $e1 = str_pad(str_replace(':', '', $daysConfig[1]['periods'][0]['start'] ?? '0800'), 4, '0', STR_PAD_RIGHT);
                $s1 = str_pad(str_replace(':', '', $daysConfig[1]['periods'][0]['end'] ?? '1200'), 4, '0', STR_PAD_RIGHT);
            }
            if (! empty($daysConfig[1]['periods'][1])) {
                $e2 = str_pad(str_replace(':', '', $daysConfig[1]['periods'][1]['start'] ?? '1300'), 4, '0', STR_PAD_RIGHT);
                $s2 = str_pad(str_replace(':', '', $daysConfig[1]['periods'][1]['end'] ?? '1700'), 4, '0', STR_PAD_RIGHT);
            }

            $lines[] = implode('|', ['04', (string) $scheduleCounter, (string) $durJornada, $e1, $s1, $e2, $s2]);
        }

        // 5. Registro 05: Marcações Tratadas
        // 05|idtVinculoAej|dataHoraMarc|idRepAej|tpMarc|seqEntSaida|fonteMarc|codHorContratual|motivo
        $hasFonteMarcOriginal = false;
        foreach ($employeeDataList as $item) {
            $empId = $item['employee']['id'] ?? 1;
            $idtVinculoAej = $vinculoMap[$empId] ?? '1';

            foreach ($item['journeys'] as $j) {
                $effectivePunches = $j['effective_punches'] ?? [];
                foreach ($effectivePunches as $pIdx => $punch) {
                    $count05++;
                    $ts = isset($punch['timestamp'])
                        ? Carbon::parse($punch['timestamp'])
                        : Carbon::parse($j['date'].' '.($punch['time'] ?? '00:00:00'));

                    $tsLocal = $ts->copy()->setTimezone($establishment->timezone ?: 'America/Maceio');
                    $dataHoraMarc = $this->fiscalHashService->formatDateTimeIso($tsLocal);
                    $idRepAej = '1';
                    $tpMarc = ($pIdx % 2 === 0) ? 'E' : 'S';
                    $pairSeq = (int) floor($pIdx / 2) + 1;
                    $seqEntSaida = (string) $pairSeq;

                    // Mapeamento oficial da fonte da marcação:
                    // O = original do REP
                    // I = incluída manualmente
                    // P = pré-assinalada
                    // X = ponto por exceção
                    // T = outra
                    $rawSource = $punch['source'] ?? 'rep_p';
                    $fonteMarc = match ($rawSource) {
                        'rep_p', 'rep' => 'O',
                        'ptrp_manual', 'manual' => 'I',
                        'pre_assigned' => 'P',
                        'exception' => 'X',
                        default => 'O',
                    };

                    if ($fonteMarc === 'O') {
                        $hasFonteMarcOriginal = true;
                    }

                    // codHorContratual: '1' na primeira entrada do dia, vazio nas demais
                    $codHorContratual = ($tpMarc === 'E' && $pairSeq === 1) ? '1' : '';

                    // motivo: preenchido se tpMarc = 'D' ou fonteMarc = 'I'
                    $motivo = ($tpMarc === 'D' || $fonteMarc === 'I')
                        ? $this->sanitize($punch['reason'] ?? 'Inclusao manual de ponto')
                        : '';

                    $lines[] = implode('|', [
                        '05',
                        $idtVinculoAej,
                        $dataHoraMarc,
                        $idRepAej,
                        $tpMarc,
                        $seqEntSaida,
                        $fonteMarc,
                        $codHorContratual,
                        $motivo,
                    ]);
                }
            }
        }

        // 6. Registro 06: Matrícula eSocial (apenas para empregados com mais de um vínculo no AEJ)
        foreach ($employeeDataList as $item) {
            $cleanCpf = str_pad(substr(preg_replace('/\D/', '', $item['employee']['cpf'] ?? ''), 0, 11), 11, '0', STR_PAD_LEFT);
            if (($cpfCounts[$cleanCpf] ?? 0) > 1) {
                $empId = $item['employee']['id'] ?? 1;
                $idtVinculoAej = $vinculoMap[$empId] ?? '1';
                $regNum = trim((string) ($item['employee']['registration_number'] ?? ''));
                if ($regNum !== '') {
                    $count06++;
                    $lines[] = implode('|', ['06', $idtVinculoAej, $this->sanitize($regNum)]);
                }
            }
        }

        // 7. Registro 07: Ausências e Banco de Horas
        // 07|idtVinculoAej|tipoAusenOuComp|data|qtMinutos|tipoMovBH
        foreach ($employeeDataList as $item) {
            $empId = $item['employee']['id'] ?? 1;
            $idtVinculoAej = $vinculoMap[$empId] ?? '1';

            // Ausências registradas na competência (tipo 2)
            foreach ($item['journeys'] as $j) {
                $absenceMinutes = (int) ($j['absence_minutes'] ?? 0);
                if ($absenceMinutes > 0) {
                    $count07++;
                    $dataAusencia = Carbon::parse($j['date'])->format('Y-m-d');
                    $lines[] = implode('|', ['07', $idtVinculoAej, '2', $dataAusencia, (string) $absenceMinutes, '']);
                }
            }

            // Movimentações reais do banco de horas do PTRP (tipo 3)
            // Se o período estiver fechado, gera exclusivamente dos snapshots congelados
            if ($isClosed) {
                $transactions = $item['time_bank']['transactions'] ?? [];
                foreach ($transactions as $tx) {
                    $qtMinutos = abs((int) ($tx['minutes'] ?? 0));
                    if ($qtMinutos === 0) {
                        continue;
                    }

                    $count07++;
                    $dataMov = $tx['reference_date'];
                    $tipoMovBH = (string) ($tx['tipo_mov_bh'] ?? '1');
                    $lines[] = implode('|', ['07', $idtVinculoAej, '3', $dataMov, (string) $qtMinutos, $tipoMovBH]);
                }
            } else {
                $account = TimeBankAccount::where('employee_id', $empId)->first();
                if ($account) {
                    $transactions = TimeBankTransaction::where('time_bank_account_id', $account->id)
                        ->whereBetween('reference_date', [$startDate->toDateString(), $endDate->toDateString()])
                        ->orderBy('reference_date', 'asc')
                        ->orderBy('created_at', 'asc')
                        ->get();

                    foreach ($transactions as $tx) {
                        $qtMinutos = abs((int) $tx->minutes);
                        if ($qtMinutos === 0) {
                            continue;
                        }

                        $tipoMovBH = ($tx->minutes > 0) ? '1' : '2';

                        $count07++;
                        $dataMov = Carbon::parse($tx->reference_date)->format('Y-m-d');
                        $lines[] = implode('|', ['07', $idtVinculoAej, '3', $dataMov, (string) $qtMinutos, $tipoMovBH]);
                    }
                }
            }
        }

        // 8. Registro 08: Identificação do PTRP / Desenvolvedor
        // 08|nomePrograma|versaoPrograma|tpIdDev|numIdDev|razaoSocialDev|emailDev
        $count08++;
        $nomeSoftware = $this->sanitize((string) config('compliance.software.name', 'PontoFacil'));
        $versaoSoftware = $this->sanitize((string) config('compliance.software.version', '2.5.0'));
        $tpIdtDesenv = (string) (config('compliance.developer.document_type') ?: '1');
        $idtDesenv = preg_replace('/\D/', '', (string) (config('compliance.developer.document') ?? ''));
        $nomeDesenv = $this->sanitize((string) (config('compliance.developer.name') ?? ''));
        $emailDesenv = $this->sanitize((string) (config('compliance.developer.email') ?? ''));

        $lines[] = implode('|', ['08', $nomeSoftware, $versaoSoftware, $tpIdtDesenv, $idtDesenv, $nomeDesenv, $emailDesenv]);

        // 9. Registro 99: Trailer com Totalizadores por Tipo
        $lines[] = implode('|', [
            '99',
            (string) $count01,
            (string) $count02,
            (string) $count03,
            (string) $count04,
            (string) $count05,
            (string) $count06,
            (string) $count07,
            (string) $count08,
        ]);

        // Linha de assinatura externa CAdES (.p7s)
        $lines[] = str_pad('ASSINATURA_DIGITAL_EM_ARQUIVO_P7S', 100, ' ', STR_PAD_RIGHT);

        $finalContent = implode("\r\n", $lines)."\r\n";

        $cnpjClean = preg_replace('/\D/', '', $establishment->identifier_number);
        $prefix = $isPreview ? 'AEJ_PREVIA' : 'AEJ';
        $filename = sprintf('%s_%s_%04d%02d.txt', $prefix, $cnpjClean, $year, $month);

        $totalRecords = $count01 + $count02 + $count03 + $count04 + $count05 + $count06 + $count07 + $count08 + 1;

        // Validação estrutural real do conteúdo gerado pelo PTRP
        $structuralValidation = app(AejValidator::class)->validate($finalContent);
        $structureValid = $structuralValidation['structureValid'];

        // Sem arquivo .p7s real gerado e validado com certificado ICP-Brasil, o arquivo permanece não homologado
        $homologationReason = null;
        if (! $structureValid) {
            $homologationReason = 'structural_error';
        } elseif (! $company->isRegisteredInpi() && $hasFonteMarcOriginal) {
            $homologationReason = 'pending_inpi';
        } elseif (empty($idtDesenv) || empty($nomeDesenv) || empty($emailDesenv)) {
            $homologationReason = 'missing_developer_data';
        } else {
            $homologationReason = 'pending_certificate';
        }

        return new AejExportResult(
            content: $finalContent,
            filename: $filename,
            establishment: $establishment,
            closedPeriod: $closedPeriod,
            startDate: $startDate,
            endDate: $endDate,
            totalRecords: $totalRecords,
            crcChecksum: '',
            isPreview: $isPreview,
            snapshotHash: $closedPeriod?->snapshot_hash,
            signatureStatus: 'pending_certificate',
            isHomologated: false,
            homologationReason: $homologationReason,
            structureValid: $structureValid,
            signatureValid: false,
        );
    }

    protected function sanitize(?string $str): string
    {
        if ($str === null) {
            return '';
        }

        $str = str_replace(["\r", "\n", '|'], ['', '', ' '], $str);
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $str);

        return trim($ascii !== false ? $ascii : $str);
    }

    /**
     * Resolve os dados de empregados e jornadas: via snapshots congelados da competência fechada
     * ou por apuração transiente calculada para modo prévia.
     *
     * @return array<int, array{employee: array<string, mixed>, schedule: array<string, mixed>, journeys: array<int, mixed>, treatments: array<int, mixed>, time_bank: array<string, mixed>}>
     */
    protected function resolveEmployeeDataList(
        Establishment $establishment,
        int $year,
        int $month,
        Carbon $startDate,
        Carbon $endDate,
        bool $isClosed,
        ?ClosedPeriod $closedPeriod,
    ): array {
        if ($isClosed && $closedPeriod) {
            $snapshots = $closedPeriod->currentSnapshots()->get();
            $employeeDataList = [];
            foreach ($snapshots as $snap) {
                $employeeDataList[] = [
                    'employee' => $snap->employee_snapshot,
                    'schedule' => $snap->schedule_snapshot,
                    'journeys' => $snap->journey_snapshot,
                    'treatments' => $snap->treatment_snapshot,
                    'time_bank' => $snap->time_bank_snapshot,
                ];
            }

            return $employeeDataList;
        }

        // Modo Prévia: apuração em tempo real
        $employees = Employee::with(['user', 'sector.establishment', 'workSchedule'])->get();
        $calcAction = app(CalculateDailyJourneyAction::class);
        $policy = TimeBankPolicy::forDate($endDate);
        $employeeDataList = [];

        foreach ($employees as $emp) {
            $empEstablishment = $emp->sector?->establishment ?? $establishment;
            $employeeSnapshot = [
                'id' => $emp->id,
                'name' => $emp->user?->name ?? 'Colaborador '.$emp->id,
                'cpf' => $emp->cpf,
                'registration_number' => $emp->registration_number,
                'job_title' => $emp->job_title,
                'department' => $emp->sector?->name,
                'establishment_id' => $empEstablishment?->id,
                'establishment_name' => $empEstablishment?->name,
                'establishment_identifier' => $empEstablishment?->identifier_number,
            ];

            $schedule = $emp->workSchedule ?? WorkSchedule::first() ?? WorkSchedule::createDefault40h();
            $scheduleSnapshot = [
                'id' => $schedule->id,
                'name' => $schedule->name,
                'type' => $schedule->type,
                'weekly_hours' => $schedule->weekly_hours,
                'tolerance_minutes' => $schedule->tolerance_minutes,
                'daily_tolerance_minutes' => $schedule->daily_tolerance_minutes,
                'days_config' => $schedule->days_config,
            ];

            $journeysData = [];
            for ($d = 1; $d <= $endDate->day; $d++) {
                $dayDate = Carbon::createFromDate($year, $month, $d);
                $journeysData[] = $calcAction->execute($emp, $dayDate, $schedule)->toArray();
            }

            $account = TimeBankAccount::getOrCreateForEmployee($emp);
            $transactions = TimeBankTransaction::where('time_bank_account_id', $account->id)
                ->whereBetween('reference_date', [$startDate->toDateString(), $endDate->toDateString()])
                ->orderBy('reference_date', 'asc')
                ->orderBy('created_at', 'asc')
                ->get();

            $frozenTransactions = [];
            foreach ($transactions as $tx) {
                $qtMinutos = abs((int) $tx->minutes);
                if ($qtMinutos === 0) {
                    continue;
                }
                $tipoMovBH = ($tx->minutes > 0) ? '1' : '2';
                $frozenTransactions[] = [
                    'reference_date' => Carbon::parse($tx->reference_date)->format('Y-m-d'),
                    'minutes' => $qtMinutos,
                    'tipo_mov_bh' => $tipoMovBH,
                ];
            }

            $timeBankSnapshot = [
                'policy_mode' => $policy?->enabled ? $policy->closing_mode->value : 'DISABLED',
                'balance_before' => $account->balanceUntil($startDate->copy()->subSecond()),
                'balance_at_closing' => $account->balanceUntil($endDate),
                'reset_applied' => 0,
                'final_balance' => $account->balanceUntil($endDate),
                'transactions' => $frozenTransactions,
            ];

            $employeeDataList[] = [
                'employee' => $employeeSnapshot,
                'schedule' => $scheduleSnapshot,
                'journeys' => $journeysData,
                'treatments' => [],
                'time_bank' => $timeBankSnapshot,
            ];
        }

        return $employeeDataList;
    }
}
