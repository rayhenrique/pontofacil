<?php

namespace App\Domain\Compliance\AEJ;

use App\Domain\Compliance\Fiscal\Services\FiscalHashService;
use App\Domain\PTRP\Actions\CalculateDailyJourneyAction;
use App\Models\ClosedPeriod;
use App\Models\Employee;
use App\Models\Establishment;
use App\Models\TimeBankAccount;
use App\Models\TimeBankPolicy;
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
        $employeeDataList = [];
        if ($isClosed) {
            $snapshots = $closedPeriod->currentSnapshots()->get();
            foreach ($snapshots as $snap) {
                $employeeDataList[] = [
                    'employee' => $snap->employee_snapshot,
                    'schedule' => $snap->schedule_snapshot,
                    'journeys' => $snap->journey_snapshot,
                    'treatments' => $snap->treatment_snapshot,
                    'time_bank' => $snap->time_bank_snapshot,
                ];
            }
        } else {
            // Modo Prévia: apuração em tempo real
            $employees = Employee::with(['user', 'sector.establishment', 'workSchedule'])->get();
            $calcAction = app(CalculateDailyJourneyAction::class);
            $policy = TimeBankPolicy::forDate($endDate);

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
                $timeBankSnapshot = [
                    'policy_mode' => $policy?->enabled ? $policy->closing_mode->value : 'DISABLED',
                    'balance_before' => $account->balanceUntil($startDate->copy()->subSecond()),
                    'balance_at_closing' => $account->balanceUntil($endDate),
                    'reset_applied' => 0,
                    'final_balance' => $account->balanceUntil($endDate),
                ];

                $employeeDataList[] = [
                    'employee' => $employeeSnapshot,
                    'schedule' => $scheduleSnapshot,
                    'journeys' => $journeysData,
                    'treatments' => [],
                    'time_bank' => $timeBankSnapshot,
                ];
            }
        }

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
        $nrRep = $isPreview ? 'PREVIA-NAO-FECHADA' : trim($company->getInpiFiscalCode());
        $lines[] = implode('|', ['02', $idRepAej, $tpRep, $nrRep]);

        // 3. Registro 03: Vínculos (Empregados)
        $vinculoMap = [];
        $vinculoCounter = 0;
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
                    $numNsr = (string) ($punch['nsr'] ?? ($pIdx + 1));
                    $repId = '1';
                    $tpMarc = ($pIdx % 2 === 0) ? 'E' : 'S';
                    $tipoFonte = ($punch['source'] ?? '') === 'ptrp_manual' ? '2' : '1';
                    $motivo = ($tipoFonte === '2') ? ($this->sanitize($punch['reason'] ?? 'Inclusao manual de ponto')) : '';

                    $lines[] = implode('|', ['05', $idtVinculoAej, $dataHoraMarc, $numNsr, $repId, $tpMarc, $tipoFonte, $motivo]);
                }
            }
        }

        // 6. Registro 06: Matrícula eSocial
        foreach ($employeeDataList as $item) {
            $empId = $item['employee']['id'] ?? 1;
            $idtVinculoAej = $vinculoMap[$empId] ?? '1';
            $regNum = trim((string) ($item['employee']['registration_number'] ?? ''));
            if ($regNum !== '') {
                $count06++;
                $lines[] = implode('|', ['06', $idtVinculoAej, $this->sanitize($regNum)]);
            }
        }

        // 7. Registro 07: Ausências e Banco de Horas
        foreach ($employeeDataList as $item) {
            $empId = $item['employee']['id'] ?? 1;
            $idtVinculoAej = $vinculoMap[$empId] ?? '1';

            // Ausências registradas na competência
            foreach ($item['journeys'] as $j) {
                $absenceMinutes = (int) ($j['absence_minutes'] ?? 0);
                if ($absenceMinutes > 0) {
                    $count07++;
                    $dataAusencia = Carbon::parse($j['date'])->format('Y-m-d');
                    $lines[] = implode('|', ['07', $idtVinculoAej, '2', $dataAusencia, (string) $absenceMinutes]);
                }
            }

            // Movimentação / Saldo do Banco de Horas
            $timeBank = $item['time_bank'] ?? [];
            if (($timeBank['policy_mode'] ?? 'DISABLED') !== 'DISABLED') {
                $count07++;
                $closingDate = $endDate->format('Y-m-d');
                $finalBalance = (int) ($timeBank['final_balance'] ?? 0);
                $lines[] = implode('|', ['07', $idtVinculoAej, '3', $closingDate, (string) $finalBalance]);
            }
        }

        // 8. Registro 08: Identificação do PTRP / Desenvolvedor
        $count08++;
        $tpIdtDesenv = '1';
        $idtDesenv = preg_replace('/\D/', '', $company->cnpj ?: $establishment->identifier_number);
        $nomeDesenv = $this->sanitize($company->legal_name);
        $nomeSoftware = $this->sanitize($company->rep_p_software_name ?: 'PontoFacil');
        $versaoSoftware = $this->sanitize($company->rep_p_software_version ?: '2.0.0');

        $lines[] = implode('|', ['08', $tpIdtDesenv, $idtDesenv, $nomeDesenv, $nomeSoftware, $versaoSoftware]);

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

        $finalContent = implode("\r\n", $lines)."\r\n";

        $cnpjClean = preg_replace('/\D/', '', $establishment->identifier_number);
        $prefix = $isPreview ? 'AEJ_PREVIA' : 'AEJ';
        $filename = sprintf('%s_%s_%04d%02d.txt', $prefix, $cnpjClean, $year, $month);

        $totalRecords = $count01 + $count02 + $count03 + $count04 + $count05 + $count06 + $count07 + $count08 + 1;

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
            snapshotHash: $closedPeriod?->snapshot_hash
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
}
