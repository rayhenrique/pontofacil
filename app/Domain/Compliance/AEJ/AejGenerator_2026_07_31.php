<?php

namespace App\Domain\Compliance\AEJ;

use App\Domain\PTRP\Actions\CalculateDailyJourneyAction;
use App\Models\ClosedPeriod;
use App\Models\Employee;
use App\Models\Establishment;
use App\Models\TimeBankAccount;
use App\Models\TimeBankPolicy;
use App\Models\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AejGenerator_2026_07_31 implements AejGeneratorInterface
{
    public const LAYOUT_VERSION = '0002';

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
        $currentNsr = 0;

        // 1. Cabeçalho (Tipo 1) - 236 caracteres
        $headerNsr = '000000000';
        $headerTipo = '1';
        $idType = ($establishment->identifier_type === 'cpf') ? '2' : '1';
        $idNumber = str_pad(preg_replace('/\D/', '', $establishment->identifier_number), 14, '0', STR_PAD_LEFT);
        $caepfCno = str_pad('', 12, '0', STR_PAD_LEFT);
        $legalName = mb_str_pad(mb_substr($this->sanitize($company->legal_name), 0, 150), 150, ' ', STR_PAD_RIGHT);

        $softwareIdentifier = $isPreview
            ? 'PREVIA-NAO-FECHADA'
            : $company->getInpiFiscalCode();
        $inpiRegistration = mb_str_pad(mb_substr($this->sanitize($softwareIdentifier), 0, 17), 17, ' ', STR_PAD_RIGHT);

        $dtInicio = $startDate->format('dmY');
        $dtFim = $endDate->format('dmY');
        $dtGeracao = $genTime->format('dmY');
        $hrGeracao = $genTime->format('Hi');
        $versao = self::LAYOUT_VERSION;

        $lines[] = $headerNsr.$headerTipo.$idType.$idNumber.$caepfCno.$legalName.$inpiRegistration.$dtInicio.$dtFim.$dtGeracao.$hrGeracao.$versao;

        // 2. Relação de Empregados (Tipo 2) - 249 caracteres
        $countTipo2 = 0;
        foreach ($employeeDataList as $item) {
            $countTipo2++;
            $currentNsr++;

            $nsr = str_pad((string) $currentNsr, 9, '0', STR_PAD_LEFT);
            $tipo = '2';
            $cpfRaw = preg_replace('/\D/', '', $item['employee']['cpf'] ?? '');
            $cpf = str_pad(substr($cpfRaw, 0, 11), 11, '0', STR_PAD_LEFT);
            $nome = mb_str_pad(mb_substr($this->sanitize($item['employee']['name'] ?? ''), 0, 150), 150, ' ', STR_PAD_RIGHT);
            $matricula = mb_str_pad(mb_substr($this->sanitize((string) ($item['employee']['registration_number'] ?? '')), 0, 20), 20, ' ', STR_PAD_RIGHT);
            $dtAdmissao = '00000000';
            $cargo = mb_str_pad(mb_substr($this->sanitize($item['employee']['job_title'] ?? ''), 0, 50), 50, ' ', STR_PAD_RIGHT);

            $lines[] = $nsr.$tipo.$cpf.$nome.$matricula.$dtAdmissao.$cargo;
        }

        // 3. Horários e Escalas (Tipo 3) - 70 caracteres
        $countTipo3 = 0;
        $processedSchedules = [];
        foreach ($employeeDataList as $item) {
            $sched = $item['schedule'];
            $schedId = $sched['id'] ?? 1;
            if (isset($processedSchedules[$schedId])) {
                continue;
            }
            $processedSchedules[$schedId] = true;
            $countTipo3++;
            $currentNsr++;

            $nsr = str_pad((string) $currentNsr, 9, '0', STR_PAD_LEFT);
            $tipo = '3';
            $codigo = str_pad(substr((string) $schedId, 0, 4), 4, '0', STR_PAD_LEFT);
            $descricao = mb_str_pad(mb_substr($this->sanitize($sched['name'] ?? 'Padrao'), 0, 40), 40, ' ', STR_PAD_RIGHT);

            // Obter períodos do primeiro dia útil configurado
            $e1 = '0800';
            $s1 = '1200';
            $e2 = '1300';
            $s2 = '1700';

            $daysConfig = $sched['days_config'] ?? [];
            if (! empty($daysConfig[1]['periods'][0])) {
                $e1 = str_replace(':', '', $daysConfig[1]['periods'][0]['start'] ?? '0800');
                $s1 = str_replace(':', '', $daysConfig[1]['periods'][0]['end'] ?? '1200');
            }
            if (! empty($daysConfig[1]['periods'][1])) {
                $e2 = str_replace(':', '', $daysConfig[1]['periods'][1]['start'] ?? '1300');
                $s2 = str_replace(':', '', $daysConfig[1]['periods'][1]['end'] ?? '1700');
            }

            $e1 = str_pad(substr($e1, 0, 4), 4, '0', STR_PAD_RIGHT);
            $s1 = str_pad(substr($s1, 0, 4), 4, '0', STR_PAD_RIGHT);
            $e2 = str_pad(substr($e2, 0, 4), 4, '0', STR_PAD_RIGHT);
            $s2 = str_pad(substr($s2, 0, 4), 4, '0', STR_PAD_RIGHT);

            $lines[] = $nsr.$tipo.$codigo.$descricao.$e1.$s1.$e2.$s2;
        }

        // 4. Marcações e Tratamentos (Tipo 4) - 48 caracteres
        $countTipo4 = 0;
        foreach ($employeeDataList as $item) {
            $cpfRaw = preg_replace('/\D/', '', $item['employee']['cpf'] ?? '');
            $cpf = str_pad(substr($cpfRaw, 0, 11), 11, '0', STR_PAD_LEFT);

            foreach ($item['journeys'] as $j) {
                $dateFormatted = Carbon::parse($j['date'])->format('dmY');
                $effectivePunches = $j['effective_punches'] ?? [];

                foreach ($effectivePunches as $pIdx => $punch) {
                    $countTipo4++;
                    $currentNsr++;

                    $nsr = str_pad((string) $currentNsr, 9, '0', STR_PAD_LEFT);
                    $tipo = '4';
                    $timeFormatted = str_pad(str_replace(':', '', substr($punch['time'] ?? '00:00', 0, 5)), 4, '0', STR_PAD_RIGHT);

                    // NSR do REP se original, ou zeros se inclusão manual
                    $repNsr = '000000000';
                    $direction = ($pIdx % 2 === 0) ? 'E' : 'S';
                    $origin = ($punch['source'] ?? '') === 'manual' ? 'I' : 'O';
                    $reasonCode = '0000';

                    $lines[] = $nsr.$tipo.$dateFormatted.$timeFormatted.$cpf.$repNsr.$direction.$origin.$reasonCode;
                }
            }
        }

        // 5. Apuração Mensal por Trabalhador (Tipo 5) - 41 caracteres
        $countTipo5 = 0;
        foreach ($employeeDataList as $item) {
            $countTipo5++;
            $currentNsr++;

            $nsr = str_pad((string) $currentNsr, 9, '0', STR_PAD_LEFT);
            $tipo = '5';
            $cpfRaw = preg_replace('/\D/', '', $item['employee']['cpf'] ?? '');
            $cpf = str_pad(substr($cpfRaw, 0, 11), 11, '0', STR_PAD_LEFT);

            $totalWorked = 0;
            $totalOvertime = 0;
            $totalLate = 0;

            foreach ($item['journeys'] as $j) {
                $totalWorked += (int) ($j['worked_minutes'] ?? 0);
                $totalOvertime += (int) ($j['overtime_minutes'] ?? 0);
                $totalLate += (int) ($j['late_minutes'] ?? 0) + (int) ($j['absence_minutes'] ?? 0);
            }

            $hn = $this->minutesToHhmm($totalWorked);
            $he50 = $this->minutesToHhmm($totalOvertime);
            $he100 = '0000';
            $adNoturno = '0000';
            $faltasAtrasos = $this->minutesToHhmm($totalLate);

            $lines[] = $nsr.$tipo.$cpf.$hn.$he50.$he100.$adNoturno.$faltasAtrasos;
        }

        // 6. Movimentação do Banco de Horas (Tipo 6) - 47 caracteres
        $countTipo6 = 0;
        foreach ($employeeDataList as $item) {
            $countTipo6++;
            $currentNsr++;

            $nsr = str_pad((string) $currentNsr, 9, '0', STR_PAD_LEFT);
            $tipo = '6';
            $cpfRaw = preg_replace('/\D/', '', $item['employee']['cpf'] ?? '');
            $cpf = str_pad(substr($cpfRaw, 0, 11), 11, '0', STR_PAD_LEFT);

            $tb = $item['time_bank'] ?? [];
            $balBefore = (int) ($tb['balance_before'] ?? 0);
            $balFinal = (int) ($tb['final_balance'] ?? 0);

            // Calcular créditos e débitos acumulados das jornadas do mês
            $credits = 0;
            $debits = 0;
            foreach ($item['journeys'] as $j) {
                $credits += (int) ($j['bank_credit_minutes'] ?? 0);
                $debits += (int) ($j['bank_debit_minutes'] ?? 0);
            }

            $signBefore = $balBefore < 0 ? '-' : '+';
            $minsBefore = str_pad((string) abs($balBefore), 6, '0', STR_PAD_LEFT);
            $minsCredits = str_pad((string) abs($credits), 6, '0', STR_PAD_LEFT);
            $minsDebits = str_pad((string) abs($debits), 6, '0', STR_PAD_LEFT);
            $signFinal = $balFinal < 0 ? '-' : '+';
            $minsFinal = str_pad((string) abs($balFinal), 6, '0', STR_PAD_LEFT);

            $lines[] = $nsr.$tipo.$cpf.$signBefore.$minsBefore.$minsCredits.$minsDebits.$signFinal.$minsFinal;
        }

        // 7. Trailer (Tipo 9) - 72 caracteres
        $trailerNsr = '999999999';
        $trailerTipo = '9';
        $qtd2 = str_pad((string) $countTipo2, 9, '0', STR_PAD_LEFT);
        $qtd3 = str_pad((string) $countTipo3, 9, '0', STR_PAD_LEFT);
        $qtd4 = str_pad((string) $countTipo4, 9, '0', STR_PAD_LEFT);
        $qtd5 = str_pad((string) $countTipo5, 9, '0', STR_PAD_LEFT);
        $qtd6 = str_pad((string) $countTipo6, 9, '0', STR_PAD_LEFT);
        $totalLinhas = str_pad((string) (count($lines) + 1), 9, '0', STR_PAD_LEFT);

        $trailerBase = $trailerNsr.$trailerTipo.$qtd2.$qtd3.$qtd4.$qtd5.$qtd6.$totalLinhas;

        // Cálculo do Checksum CRC-32
        $contentBeforeCrc = implode("\r\n", $lines)."\r\n".$trailerBase;
        $crcChecksum = sprintf('%08X', crc32($contentBeforeCrc));

        $lines[] = $trailerBase.$crcChecksum;

        $finalContent = implode("\r\n", $lines)."\r\n";

        $cnpjQuery = preg_replace('/\D/', '', $establishment->identifier_number);
        $prefix = $isPreview ? 'AEJ_PREVIA' : 'AEJ';
        $filename = sprintf('%s_%s_%04d%02d.txt', $prefix, $cnpjQuery, $year, $month);

        Log::info('aej.generated', [
            'establishment_id' => $establishment->id,
            'year' => $year,
            'month' => $month,
            'is_preview' => $isPreview,
            'total_records' => count($lines) - 2,
            'crc32' => $crcChecksum,
            'snapshot_hash' => $closedPeriod?->snapshot_hash,
            'filename' => $filename,
        ]);

        return new AejExportResult(
            content: $finalContent,
            filename: $filename,
            establishment: $establishment,
            closedPeriod: $closedPeriod,
            startDate: $startDate,
            endDate: $endDate,
            totalRecords: count($lines) - 2,
            crcChecksum: $crcChecksum,
            isPreview: $isPreview,
            snapshotHash: $closedPeriod?->snapshot_hash,
            signatureStatus: 'AEJ GERADO — NÃO ASSINADO DIGITALMENTE (MODO DE DESENVOLVIMENTO)'
        );
    }

    protected function sanitize(?string $text): string
    {
        if ($text === null) {
            return '';
        }
        $unaccented = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);

        return preg_replace('/[^\x20-\x7E]/', '', $unaccented ?: $text);
    }

    protected function minutesToHhmm(int $minutes): string
    {
        $abs = abs($minutes);
        $hours = intdiv($abs, 60);
        $rem = $abs % 60;

        return sprintf('%02d%02d', min($hours, 99), $rem);
    }
}
